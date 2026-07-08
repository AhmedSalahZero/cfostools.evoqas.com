/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  InvestaWatch — Study Engine: Step 2 — COGS
 *  File: resources/js/Utils/StudyEngine/calcCOGS.js
 *
 *  ACCOUNTING:
 *  Supplier invoice received:
 *    Purchase amount (COGS)    100,000   → P&L COGS
 *    + Purchase VAT            14,000    → Input VAT (reduces net VAT payable)
 *    Gross payable             114,000   → AP increases by this amount
 *
 *  When we pay supplier (per payment policy):
 *    Cash paid = (Purchase + Purchase VAT − Credit WHT) per tranche
 *    Credit WHT = Purchase amount paid × Credit WHT rate → liability to pay quarterly
 *    AP decreases by (Purchase + Purchase VAT) of that tranche
 *
 *  MANUFACTURING — Full Inventory Statement Engine (v5.0):
 *    Step A: Finished Goods Quantity Statement
 *    Step B: Raw Material Quantity Statement
 *    Step C: Raw Material Value Statement (weighted average cost)
 *    Step D: Finished Goods Value Statement (weighted average cost)
 * ═══════════════════════════════════════════════════════════════════════════
 */

import {
  toYM, monthDiff, resolvePolicyTranches, applyPaymentPolicy, CASH_POLICY,
} from './engineUtils.js'

// productDefs      = props.products from Create.vue (step 1) — carries vat_rate & withhold_tax_rate
// projections      = props.projections from SalesProjection (has inventory_coverage_days, beg_inv_qty etc.)
// manpowerData     = from ManpowerStep
// depMfgByProduct  = from calcFixedAssets — manufacturing depreciation per product per month
// rawMaterialDefs  = from Create.vue general_assumptions.raw_materials
export function calcCOGS(study, cogsData, revenueByProduct, volumeByProduct, productDefs, projections, manpowerData, depMfgByProduct, rawMaterialDefs) {
  const totalMonths = study.duration_years * 12
  const startYM     = toYM(study.study_start_date)

  const cogsByMonth        = new Array(totalMonths).fill(0)
  const cogsIncurred       = new Array(totalMonths).fill(0)
  const purchaseVatByMonth = new Array(totalMonths).fill(0)

  // AP components (gross = COGS + Purchase VAT)
  const apGrossInvoicedByMonth = new Array(totalMonths).fill(0)
  const apGrossPaidByMonth     = new Array(totalMonths).fill(0)

  // Cash out to suppliers = (COGS + Purchase VAT − Credit WHT) paid
  const cogsPaymentsByMonth    = new Array(totalMonths).fill(0)
  const creditWhtByMonth       = new Array(totalMonths).fill(0)

  const cogsByProduct              = []
  const inventoryByMonth           = new Array(totalMonths).fill(0)
  const rmPaymentsByName           = {}
  const ohPaymentsByName           = {}
  const mfgLaborCapitalizedByMonth = new Array(totalMonths).fill(0)
  const ohUnabsorbedByMonth        = new Array(totalMonths).fill(0)
  const cogsByProductDetail        = []

  // ── Helper: ensure named array in a map ──────────────────────────────────
  const ensureNamedArray = (map, name, fallback) => {
    const k = String(name || fallback)
    if (!map[k]) map[k] = new Array(totalMonths).fill(0)
    return map[k]
  }

  // ── Helper: apply a supplier payment with specific VAT and Credit WHT rates ──
  const applySupplierPayment = (cogsAmt, idx, policy, vatPct, cWhtPct, breakdownArr = null) => {
    if (!cogsAmt) return
    const vatOnPurchase = cogsAmt * vatPct
    const grossPayable  = cogsAmt + vatOnPurchase
    apGrossInvoicedByMonth[idx] += grossPayable
    purchaseVatByMonth[idx]     += vatOnPurchase   // input VAT (at invoice date)

    const tranches = resolvePolicyTranches(policy)
    for (const t of tranches) {
      const pct    = (Number(t.pct) || 0) / 100
      const days   = Number(t.days) || 0
      if (pct === 0) continue
      const target = idx + Math.round(days / 30)
      if (target >= totalMonths) continue
      const cogsT     = cogsAmt      * pct
      const grossT    = grossPayable * pct
      const creditWht = cogsT * cWhtPct

      const paidCash = grossT - creditWht
      cogsPaymentsByMonth[target] += paidCash
      if (breakdownArr) breakdownArr[target] += paidCash
      creditWhtByMonth[target]    += creditWht
      apGrossPaidByMonth[target]  += grossT
    }
  }

  // ── Pre-compute manufacturing product names ───────────────────────────────
  const mfgProductNames = (productDefs ?? []).filter(p => p.nature === 'manufacturing').map(p => p.name)

  // directLaborByProductByMonth[pi_within_mfg][m] = direct labor cost allocated to this mfg product
  // indirectLaborByProductByMonth[pi_within_mfg][m] = indirect labor cost
  const directLaborByProductByMonth   = mfgProductNames.map(() => new Array(totalMonths).fill(0))
  const indirectLaborByProductByMonth = mfgProductNames.map(() => new Array(totalMonths).fill(0))

  for (const row of (manpowerData ?? [])) {
    if (row.dept !== 'direct_labor' && row.dept !== 'indirect_labor') continue
    const alloc = row.product_allocation ?? []
    if (alloc.length === 0) continue  // unallocated labor doesn't go into COGS

    const base   = (Number(row.net_salary) || 0) * (1 + (Number(row.salary_taxes_pct) || 0) / 100 + (Number(row.social_insurance_pct) || 0) / 100)
    const annInc = (Number(row.annual_increase_pct) || 0) / 100

    for (let m = 0; m < totalMonths; m++) {
      const year  = Math.floor(m / 12)
      const gross = base * Math.pow(1 + annInc, year)
      const count = year === 0 ? (row.y1_count?.[m % 12] ?? 0) : year === 1 ? (row.y2_count?.[m % 12] ?? 0) : (row.annual_count?.[year - 2] ?? 0)
      const cost  = gross * count
      if (!cost) continue

      for (const a of alloc) {
        const mpi = mfgProductNames.indexOf(a.product_name)
        if (mpi < 0) continue
        const frac = (Number(a.pct) || 0) / 100
        if (row.dept === 'direct_labor')   directLaborByProductByMonth[mpi][m]   += cost * frac
        if (row.dept === 'indirect_labor') indirectLaborByProductByMonth[mpi][m] += cost * frac
      }
    }
  }

  // ── Map the global product index (pi) to mfg-only index (mpi) ──
  let mfgCounter = -1
  console.log(cogsData)

  for (let pi = 0; pi < cogsData.length; pi++) {
    const cog     = cogsData[pi]
    const prodCog = new Array(totalMonths).fill(0)
    const prodDef = (productDefs ?? [])[pi] ?? {}

    // Per-product COGS breakdown arrays (for P&L drill-down)
    const detailRM      = new Array(totalMonths).fill(0)  // Raw Material cost
    const detailDL      = new Array(totalMonths).fill(0)  // Direct + Indirect Labor cost
    const detailOH      = new Array(totalMonths).fill(0)  // Manufacturing Overheads
    const detailTrading = new Array(totalMonths).fill(0)  // Trading COGS
    const detailService = new Array(totalMonths).fill(0)  // Service COGS

    // ══════════════════════════════════════════════════════════════════════════
    //  MANUFACTURING  —  Full Inventory Statement Engine
    // ══════════════════════════════════════════════════════════════════════════
    if (cog.nature === 'manufacturing') {
      mfgCounter++
      const mpi = mfgCounter  // index within mfg-only arrays

      // ── Finished Goods opening inventory ─────────────────────────────────
      // PRIMARY SOURCE: cog object (cogsData from DB) — CogsStep now embeds
      // beg_inv_qty / beg_inv_amount / beg_inv_breakdown / inventory_coverage_days
      // directly from Step 2 (SalesProjection) via the fgInventory prop.
      // FALLBACK: projections array (Step 2 data) matched by product name.
      const _cogProductName = cog.product_name ?? prodDef.name ?? ''
      const _projFallback = (() => {
        if (!Array.isArray(projections)) return {}
        const byName = projections.find(pp => pp?.name && pp.name === _cogProductName)
        return byName ?? projections[pi] ?? {}
      })()

      // Read from cogsData first (embedded by CogsStep), fall back to projections
      const fgCoverageDays = Number(cog.inventory_coverage_days ?? _projFallback.inventory_coverage_days ?? 30)
      let   fgBegQty       = Number(cog.beg_inv_qty    ?? _projFallback.beg_inv_qty    ?? 0)
      const fgBegAmount    = Number(cog.beg_inv_amount ?? _projFallback.beg_inv_amount ?? 0)

      // beg_inv_breakdown: { raw_material_pct, direct_labor_pct, overheads_pct }
      const _rawBreakdown = cog.beg_inv_breakdown ?? _projFallback.beg_inv_breakdown ?? null
      const fgBreakdown   = (_rawBreakdown && typeof _rawBreakdown === 'object')
        ? _rawBreakdown
        : { raw_material_pct: 84, direct_labor_pct: 3, overheads_pct: 13 }

      const _rmPct = Number(fgBreakdown.raw_material_pct ?? 84)
      const _dlPct = Number(fgBreakdown.direct_labor_pct ??  3)
      const _ohPct = Number(fgBreakdown.overheads_pct    ?? 13)

      // Opening FG value split by bucket — these seed the weighted-average cost pool
      let fgBegRM = fgBegAmount * _rmPct / 100
      let fgBegDL = fgBegAmount * _dlPct / 100
      let fgBegOH = fgBegAmount * _ohPct / 100

      // Pre-compute last-year average monthly sold volume (fallback for last month)
      const lastYrStart  = Math.max(0, totalMonths - 12)
      const lastYrAvgVol = Array.from({ length: 12 }, (_, i) => volumeByProduct[pi]?.[lastYrStart + i] || 0).reduce((s, v) => s + v, 0) / 12

      // Shared overheads (from CogsStep, already injected into cog.overheads by save logic)
      const overheads = cog.overheads ?? []
      const rmMethod  = cog.rm_method ?? 'bom'

      if (rmMethod === 'pct_selling') {
        // ════════════════════════════════════════════════════════════════════
        //  % OF SELLING PRICE MODE — with FG Inventory Statement
        //
        //  The pct_selling % represents the RM cost of MANUFACTURING a unit.
        //  It only applies to units that are actually manufactured this month.
        //  Units sold from the beginning inventory pool cost nothing new to
        //  purchase — they already carry their cost from the opening breakdown.
        //
        //  Flow per month:
        //  Step A: FG Qty Statement — same as BOM (determine mfgQty needed)
        //  Step B: RM purchase cost = mfgQty × unit_selling_price × pct
        //          (only purchased when manufacturing happens)
        //  Step C: FG Value pool = beginning pool + production cost this month
        //  Step D: COGS drawn from pool by weighted avg × soldQty
        //  Step E: FG end balance carries forward to Balance Sheet
        // ════════════════════════════════════════════════════════════════════

        const lastYrStart2  = Math.max(0, totalMonths - 12)
        const lastYrAvgVol2 = Array.from({ length: 12 }, (_, i) => volumeByProduct[pi]?.[lastYrStart2 + i] || 0).reduce((s, v) => s + v, 0) / 12

        for (let m = 0; m < totalMonths; m++) {
          const year    = Math.floor(m / 12)
          const soldQty = volumeByProduct[pi]?.[m] || 0
          const rev     = revenueByProduct[pi]?.[m] || 0

          // ── Step A: FG Quantity Statement ─────────────────────────────────
          const isLastMonth2  = (m + 1 >= totalMonths)
          const nextSoldQty2  = isLastMonth2 ? lastYrAvgVol2 : (volumeByProduct[pi]?.[m + 1] || 0)
          const targetFgEnd2  = nextSoldQty2 * (fgCoverageDays / 30)
          let   mfgQty2       = 0
          if (fgBegQty < soldQty + targetFgEnd2) {
            mfgQty2 = soldQty + targetFgEnd2 - fgBegQty
          }
          const totalAvailFgQty2 = fgBegQty + mfgQty2

          // ── Step B: RM purchase cost — ONLY when manufacturing happens ────
          // pct_selling % = RM cost as % of selling price per manufactured unit.
          // Unit selling price = rev / soldQty (avoid div/0).
          // No purchase when mfgQty2 = 0 (beginning stock covers demand).
          let rmProdCostThisMonth = 0
          if (mfgQty2 > 0 && rev > 0 && soldQty > 0) {
            const unitPrice = rev / soldQty
            for (let rmi = 0; rmi < (cog.raw_materials ?? []).length; rmi++) {
              const rm        = cog.raw_materials[rmi]
              const rmVatPct  = (Number(rm.vat_rate)          || 0) / 100
              const rmCWhtPct = (Number(rm.withhold_tax_rate) || 0) / 100
              const annChange = (Number(rm.annual_change_pct) || 0) / 100
              const pct       = (Number(rm.pct_selling)       || 0) / 100
              const rmCostPerUnit = unitPrice * pct * Math.pow(1 + annChange, year)
              const rmCostTotal   = rmCostPerUnit * mfgQty2
              if (!rmCostTotal) continue

              rmProdCostThisMonth += rmCostTotal
              const rmName         = rm.name || rm.raw_material_name || `Raw Material ${rmi + 1}`
              const rmBreakdownArr = ensureNamedArray(rmPaymentsByName, rmName, `Raw Material ${rmi + 1}`)
              applySupplierPayment(rmCostTotal, m, rm.payment_policy ?? CASH_POLICY, rmVatPct, rmCWhtPct, rmBreakdownArr)
            }
          }

          // ── Manufacturing overheads allocated to this product (same treatment as BOM mode) ──
          let ohCostThisMonth2 = 0
          for (const oh of overheads) {
            const si = oh.start_date ? Math.max(0, monthDiff(startYM, toYM(oh.start_date))) : 0
            const ei = oh.end_date   ? Math.min(totalMonths - 1, monthDiff(startYM, toYM(oh.end_date))) : totalMonths - 1
            if (m < si || m > ei) continue

            let allocFrac = 0
            if (oh.method === 'fixed_monthly') {
              const alloc = oh.product_allocation ?? []
              if (alloc.length === 0) {
                allocFrac = mfgProductNames.length > 0 ? 1 / mfgProductNames.length : 1
              } else {
                const found = alloc.find(a => a.product_name === prodDef.name)
                allocFrac = found ? (Number(found.pct) || 0) / 100 : 0
              }
            } else {
              const applyTo = oh.apply_to_products ?? []
              if (applyTo.length === 0 || applyTo.includes(prodDef.name)) {
                const cnt = applyTo.length === 0 ? mfgProductNames.length : applyTo.length
                allocFrac = cnt > 0 ? 1 / cnt : 1
              }
            }
            if (!allocFrac) continue

            let ohTotal = 0
            if (oh.method === 'fixed_monthly') {
              ohTotal = (Number(oh.amount) || 0) * Math.pow(1 + (Number(oh.annual_increase_pct) || 0) / 100, year)
            } else if (oh.method === 'cost_per_unit') {
              ohTotal = (Number(oh.amount) || 0) * Math.pow(1 + (Number(oh.annual_increase_pct) || 0) / 100, year) * mfgQty2
            } else if (oh.method === 'pct_revenue') {
              ohTotal = (revenueByProduct[pi]?.[m] || 0) * ((Number(oh.pct_revenue) || 0) / 100) * Math.pow(1 + (Number(oh.annual_change_pct) || 0) / 100, year)
            }

            const ohAllocated = ohTotal * allocFrac
            ohCostThisMonth2 += ohAllocated
            const ohName         = oh.name || oh.expense_name || `Overhead ${oh.method || ''}`.trim() || 'Overhead'
            const ohBreakdownArr = ensureNamedArray(ohPaymentsByName, ohName, 'Overhead')
            applyPaymentPolicy(ohBreakdownArr,        m, ohAllocated, oh.payment_policy ?? CASH_POLICY)
            applyPaymentPolicy(cogsPaymentsByMonth,   m, ohAllocated, oh.payment_policy ?? CASH_POLICY)
            applyPaymentPolicy(apGrossPaidByMonth,    m, ohAllocated, oh.payment_policy ?? CASH_POLICY)
            apGrossInvoicedByMonth[m] += ohAllocated
          }

          // ── Step C & D: FG Value pool + weighted-avg COGS ─────────────────
          // Pool = beginning RM/DL/OH values + this month's production cost.
          // In pct_selling mode, RM comes from pct_selling while labor still comes
          // from manpower allocation (DL + IL) and should be capitalized into FG labor.
          const dlCostThisMonth2 = mfgQty2 > 0 ? (directLaborByProductByMonth[mpi]?.[m]   || 0) : 0
          const ilCostThisMonth2 = mfgQty2 > 0 ? (indirectLaborByProductByMonth[mpi]?.[m] || 0) : 0
          // Only capitalize labor into FG inventory when there is revenue this month.
          // When rev === 0 the labor cannot be matched against sales → it stays in
          // OPEX manpower (i.e. we do NOT deduct it from manpowerByMonth via capitalization).
          if (rev > 0) {
            mfgLaborCapitalizedByMonth[m] += dlCostThisMonth2 + ilCostThisMonth2
          }
          const totalAvailRM2     = fgBegRM + rmProdCostThisMonth
          const totalAvailDL2     = fgBegDL + dlCostThisMonth2 + ilCostThisMonth2
          const ohCostProduction2 = mfgQty2 > 0 ? ohCostThisMonth2 : 0
          const totalAvailOH2     = fgBegOH + ohCostProduction2

          const cogsRM2    = totalAvailFgQty2 > 0 ? (totalAvailRM2 / totalAvailFgQty2) * soldQty : 0
          const cogsDL2    = totalAvailFgQty2 > 0 ? (totalAvailDL2 / totalAvailFgQty2) * soldQty : 0
          const cogsOH2    = totalAvailFgQty2 > 0 ? (totalAvailOH2 / totalAvailFgQty2) * soldQty : 0
          const totalCOGS2 = cogsRM2 + cogsDL2 + cogsOH2

          if (totalCOGS2 > 0) {
            prodCog[m]     += totalCOGS2
            cogsByMonth[m] += totalCOGS2
            cogsIncurred[m]+= totalCOGS2
            detailRM[m]    += cogsRM2
            detailDL[m]    += cogsDL2
            detailOH[m]    += cogsOH2
          }

          // ── Step E: FG end balance carry-forward ──────────────────────────
          fgBegRM  = Math.max(0, totalAvailRM2 - cogsRM2)
          fgBegDL  = Math.max(0, totalAvailDL2 - cogsDL2)
          fgBegOH  = Math.max(0, totalAvailOH2 - cogsOH2)
          fgBegQty = Math.max(0, totalAvailFgQty2 - soldQty)

          // FG remaining balance → Balance Sheet inventory
          inventoryByMonth[m] += fgBegRM + fgBegDL + fgBegOH

          // Unabsorbed fixed overheads: no revenue → period cost → OPEX
          if (rev === 0 && ohCostThisMonth2 > 0) {
            ohUnabsorbedByMonth[m] += ohCostThisMonth2
          }
        }

      } else {
        // ════════════════════════════════════════════════════════════════════
        //  BOM (BILL OF MATERIALS) MODE — Full Inventory Statement Engine
        // ════════════════════════════════════════════════════════════════════

        for (let m = 0; m < totalMonths; m++) {
          const year    = Math.floor(m / 12)
          const soldQty = volumeByProduct[pi]?.[m] || 0
          const rev     = revenueByProduct[pi]?.[m] || 0

          // ── Step A: Finished Goods Quantity Statement ──────────────────────
          const isLastMonth = (m + 1 >= totalMonths)
          const nextSoldQty = isLastMonth ? lastYrAvgVol : (volumeByProduct[pi]?.[m + 1] || 0)
          const targetFgEnd = nextSoldQty * (fgCoverageDays / 30)

          let mfgQty = 0
          if (fgBegQty < soldQty + targetFgEnd) {
            mfgQty = soldQty + targetFgEnd - fgBegQty
          }
          const totalAvailFgQty = fgBegQty + mfgQty

          // ── Step B & C: Raw Material Inventory Statements (one per RM) ──────
          let rmDispersedValueThisMonth = 0

          for (let rmi = 0; rmi < (cog.raw_materials ?? []).length; rmi++) {
            const rm        = cog.raw_materials[rmi]
            const rmVatPct  = (Number(rm.vat_rate)          || 0) / 100
            const rmCWhtPct = (Number(rm.withhold_tax_rate) || 0) / 100
            const rmDef     = (rawMaterialDefs ?? [])[rmi] ?? {}
            const rmCovDays = Number(rmDef.rm_inventory_coverage_days || rm.inventory_days || 30)
            const cpu       = (Number(rm.cost_per_unit) || 0) * Math.pow(1 + (Number(rm.annual_increase_pct) || 0) / 100, year)

            // Units of RM needed to manufacture mfgQty finished units
            const rmDispersedQty = mfgQty * (Number(rm.qty_per_unit) || 0)

            // Initialise RM running inventory (first month only)
            if (rm._rmInvQty === undefined) {
              rm._rmInvQty = Number(rm.beg_inventory_qty   || 0)
              rm._rmInvVal = Number(rm.beg_inventory_value || 0) || (rm._rmInvQty * cpu)
            }

            // Target RM end stock = next month's dispersion × coverage ratio
            const nextMfgQty = isLastMonth ? mfgQty : (() => {
              const nxt = volumeByProduct[pi]?.[m + 1] || 0
              const nxtTarget = nxt * (fgCoverageDays / 30)
              const nxtBegFg  = Math.max(0, totalAvailFgQty - soldQty)
              return nxtBegFg < nxt + nxtTarget ? nxt + nxtTarget - nxtBegFg : 0
            })()
            const nextRmDisp  = nextMfgQty * (Number(rm.qty_per_unit) || 0)
            const targetRmEnd = nextRmDisp * (rmCovDays / 30)

            // Purchase decision
            let rmPurchasedQty  = 0
            let totalAvailRmQty = 0
            if (rm._rmInvQty >= rmDispersedQty + targetRmEnd) {
              // Condition 1: existing stock sufficient — no purchase
              totalAvailRmQty = rm._rmInvQty
            } else {
              // Condition 2: must purchase
              totalAvailRmQty = rmDispersedQty + targetRmEnd
              rmPurchasedQty  = totalAvailRmQty - rm._rmInvQty
            }

            const rmPurchasedVal  = rmPurchasedQty * cpu
            const totalAvailRmVal = rm._rmInvVal + rmPurchasedVal
            const avgRmCost       = totalAvailRmQty > 0 ? totalAvailRmVal / totalAvailRmQty : cpu

            // RM Dispersed Value → feeds into FG weighted avg cost
            const rmDispersedVal       = avgRmCost * rmDispersedQty
            rmDispersedValueThisMonth += rmDispersedVal

            // RM End Balance (carry forward)
            rm._rmInvQty = Math.max(0, totalAvailRmQty - rmDispersedQty)
            rm._rmInvVal = Math.max(0, totalAvailRmVal - rmDispersedVal)

            // Supplier AP / VAT / WHT
            if (rmPurchasedVal > 0) {
              const rmName         = rm.name || rm.raw_material_name || `Raw Material ${rmi + 1}`
              const rmBreakdownArr = ensureNamedArray(rmPaymentsByName, rmName, `Raw Material ${rmi + 1}`)
              applySupplierPayment(rmPurchasedVal, m, rm.payment_policy ?? CASH_POLICY, rmVatPct, rmCWhtPct, rmBreakdownArr)
            }
          }  // end per-RM loop

          // ── Overheads cost this month (allocated to this product) ──────────
          let ohCostThisMonth = 0
          for (const oh of overheads) {
            const si = oh.start_date ? Math.max(0, monthDiff(startYM, toYM(oh.start_date))) : 0
            const ei = oh.end_date   ? Math.min(totalMonths - 1, monthDiff(startYM, toYM(oh.end_date))) : totalMonths - 1
            if (m < si || m > ei) continue

            let allocFrac = 0
            if (oh.method === 'fixed_monthly') {
              const alloc = oh.product_allocation ?? []
              if (alloc.length === 0) {
                allocFrac = mfgProductNames.length > 0 ? 1 / mfgProductNames.length : 1
              } else {
                const found = alloc.find(a => a.product_name === prodDef.name)
                allocFrac = found ? (Number(found.pct) || 0) / 100 : 0
              }
            } else {
              const applyTo = oh.apply_to_products ?? []
              if (applyTo.length === 0 || applyTo.includes(prodDef.name)) {
                const cnt = applyTo.length === 0 ? mfgProductNames.length : applyTo.length
                allocFrac = cnt > 0 ? 1 / cnt : 1
              }
            }
            if (!allocFrac) continue

            let ohTotal = 0
            if (oh.method === 'fixed_monthly') {
              ohTotal = (Number(oh.amount) || 0) * Math.pow(1 + (Number(oh.annual_increase_pct) || 0) / 100, year)
            } else if (oh.method === 'cost_per_unit') {
              ohTotal = (Number(oh.amount) || 0) * Math.pow(1 + (Number(oh.annual_increase_pct) || 0) / 100, year) * mfgQty
            } else if (oh.method === 'pct_revenue') {
              ohTotal = (revenueByProduct[pi]?.[m] || 0) * ((Number(oh.pct_revenue) || 0) / 100) * Math.pow(1 + (Number(oh.annual_change_pct) || 0) / 100, year)
            }

            const ohAllocated = ohTotal * allocFrac
            ohCostThisMonth  += ohAllocated
            const ohName         = oh.name || oh.expense_name || `Overhead ${oh.method || ''}`.trim() || 'Overhead'
            const ohBreakdownArr = ensureNamedArray(ohPaymentsByName, ohName, 'Overhead')
            applyPaymentPolicy(ohBreakdownArr,        m, ohAllocated, oh.payment_policy ?? CASH_POLICY)
            applyPaymentPolicy(cogsPaymentsByMonth,   m, ohAllocated, oh.payment_policy ?? CASH_POLICY)
            applyPaymentPolicy(apGrossPaidByMonth,    m, ohAllocated, oh.payment_policy ?? CASH_POLICY)
            apGrossInvoicedByMonth[m] += ohAllocated
          }

          // ── Labor & Depreciation allocated to this product ─────────────────
          // IMPORTANT: Production costs (DL, IL, OH, Dep) only enter the FG pool when
          // manufacturing actually happens this month (mfgQty > 0).
          // When mfgQty = 0, the beginning inventory pool sits unchanged — we only
          // draw COGS from it. Adding costs in a zero-production month would override
          // (inflate) the beginning inventory breakdown values incorrectly.
          // Both direct labor (DL) and indirect labor (IL) feed the "Labor" (DL) pool
          // in the FG breakdown — they must NOT be separated into the OH pool.
          const dlCostThisMonth  = mfgQty > 0 ? (directLaborByProductByMonth[mpi]?.[m]   || 0) : 0
          const ilCostThisMonth  = mfgQty > 0 ? (indirectLaborByProductByMonth[mpi]?.[m] || 0) : 0
          const mfgDepThisMonth  = mfgQty > 0 ? (depMfgByProduct?.[pi]?.[m]              || 0) : 0
          const ohCostProduction = mfgQty > 0 ? ohCostThisMonth : 0
          // Indirect labor is a labor cost, not an overhead cost — keep it in the DL pool.
          const totalMfgOHCost   = ohCostProduction + mfgDepThisMonth
          // Only capitalize labor into FG inventory when there is revenue this month.
          // When rev === 0 the labor cannot be matched against sales → it stays in
          // OPEX manpower (i.e. we do NOT deduct it from manpowerByMonth via capitalization).
          if (rev > 0) {
            mfgLaborCapitalizedByMonth[m] += dlCostThisMonth + ilCostThisMonth
          }

          // ── Step D: Finished Goods Value Statement (weighted avg cost) ──────
          // Pool = beginning FG breakdown value (carry-forward) + this month's production cost
          // (only when mfgQty > 0).
          const totalAvailRM = fgBegRM + (mfgQty > 0 ? rmDispersedValueThisMonth : 0)
          const totalAvailDL = fgBegDL + dlCostThisMonth + ilCostThisMonth
          const totalAvailOH = fgBegOH + totalMfgOHCost

          const cogsRM       = totalAvailFgQty > 0 ? (totalAvailRM / totalAvailFgQty) * soldQty : 0
          const cogsDL       = totalAvailFgQty > 0 ? (totalAvailDL / totalAvailFgQty) * soldQty : 0
          const cogsOH_total = totalAvailFgQty > 0 ? (totalAvailOH / totalAvailFgQty) * soldQty : 0

          const totalCOGSThisProduct = cogsRM + cogsDL + cogsOH_total
          prodCog[m]     += totalCOGSThisProduct
          cogsByMonth[m] += totalCOGSThisProduct
          cogsIncurred[m]+= totalCOGSThisProduct
          detailRM[m]    += cogsRM
          detailDL[m]    += cogsDL
          detailOH[m]    += cogsOH_total

          // End FG inventory (carry forward to next month as beginning balance)
          fgBegRM  = Math.max(0, totalAvailRM - cogsRM)
          fgBegDL  = Math.max(0, totalAvailDL - cogsDL)
          fgBegOH  = Math.max(0, totalAvailOH - cogsOH_total)
          fgBegQty = Math.max(0, totalAvailFgQty - soldQty)

          // BS: Finished Goods inventory end balance = sum of three breakdown buckets
          inventoryByMonth[m] += fgBegRM + fgBegDL + fgBegOH

          // Unabsorbed fixed overheads: no revenue → period cost → OPEX
          if (rev === 0 && ohCostThisMonth > 0) {
            ohUnabsorbedByMonth[m] += ohCostThisMonth
          }

        }  // end per-month loop (BOM)

        // Clean up transient RM state after each product loop
        for (const rm of (cog.raw_materials ?? [])) {
          delete rm._rmInvQty
          delete rm._rmInvVal
        }
      }  // end BOM mode

    // ══════════════════════════════════════════════════════════════════════════
    //  TRADING  —  Inventory Statement Method
    // ══════════════════════════════════════════════════════════════════════════
    } else if (cog.nature === 'trading') {
      // For trading: vat_rate and withhold_tax_rate live on the step-1 product definition
      const tradDef       = (productDefs ?? [])[pi] ?? {}
      const tradVatPct    = (Number(tradDef.vat_rate)              || 0) / 100
      const tradCWhtPct   = (Number(tradDef.withhold_tax_rate)     || 0) / 100
      const inventoryDays = Number(cog.inventory_days)             || 30
      const annualCostInc = (Number(cog.annual_cost_increase_pct)  || 0) / 100

      // Running inventory state (carry across months)
      let invQty = Number(cog.beginning_inventory_units) || 0
      let invVal = Number(cog.beginning_inventory_value) || 0

      // Pre-compute average monthly volume of the last 12 months of the study.
      // Used as a proxy for "next month's sales" when in the final month so that
      // ending inventory doesn't collapse to zero.
      const lastYearStart  = Math.max(0, totalMonths - 12)
      const lastYearVols   = Array.from({ length: 12 }, (_, i) => volumeByProduct[pi]?.[lastYearStart + i] || 0)
      const lastYearAvgVol = lastYearVols.reduce((s, v) => s + v, 0) / 12

      for (let m = 0; m < totalMonths; m++) {
        const year    = Math.floor(m / 12)
        const cpu     = (Number(cog.unit_purchase_cost) || 0) * Math.pow(1 + annualCostInc, year)
        const soldQty = volumeByProduct[pi]?.[m] || 0

        // Target end inventory = next month's sold qty × coverage ratio.
        const isLastMonth  = (m + 1 >= totalMonths)
        const nextSoldQty  = isLastMonth ? lastYearAvgVol : (volumeByProduct[pi]?.[m + 1] || 0)
        const targetEndQty = nextSoldQty * (inventoryDays / 30)

        // Condition check: do we need to purchase?
        const neededQty   = soldQty + targetEndQty
        let purchasedQty  = 0
        let totalAvailQty = 0

        if (invQty >= neededQty) {
          // Condition 1: existing inventory covers sales + target end stock → no purchase
          purchasedQty  = 0
          totalAvailQty = invQty
        } else {
          // Condition 2: must purchase to meet sales + target end inventory
          totalAvailQty = neededQty
          purchasedQty  = neededQty - invQty
        }

        // Purchase value
        const purchasedVal = purchasedQty * cpu

        // Trigger AP / supplier payment for the purchased amount
        if (purchasedVal > 0) {
          const tradName         = prodDef.name || `Trading Product ${pi + 1}`
          const tradBreakdownArr = ensureNamedArray(rmPaymentsByName, tradName, `Trading Product ${pi + 1}`)
          applySupplierPayment(purchasedVal, m, cog.purchase_payment_policy ?? CASH_POLICY, tradVatPct, tradCWhtPct, tradBreakdownArr)
        }

        // Total available value = beginning balance + purchases (weighted avg cost method)
        const totalAvailVal = invVal + purchasedVal

        // Weighted average cost per unit
        const avgCost  = totalAvailQty > 0 ? totalAvailVal / totalAvailQty : cpu

        // COGS for the month = sold qty × avg cost
        const cogsCost = avgCost * soldQty

        // End inventory balance (quantity and value)
        const endQty = totalAvailQty - soldQty
        const endVal = totalAvailVal - cogsCost

        // Carry forward (next month's beginning balance)
        invQty = Math.max(0, endQty)
        invVal = Math.max(0, endVal)

        if (!cogsCost && !purchasedVal) {
          inventoryByMonth[m] += invVal   // carry the unchanged inventory to BS
          continue
        }

        prodCog[m]       += cogsCost
        cogsByMonth[m]   += cogsCost
        cogsIncurred[m]  += cogsCost
        detailTrading[m] += cogsCost

        inventoryByMonth[m] += invVal   // record end-of-month inventory value on BS
      }

    // ══════════════════════════════════════════════════════════════════════════
    //  SERVICE
    // ══════════════════════════════════════════════════════════════════════════
    } else if (cog.nature === 'service') {
      // For service: no purchase VAT or credit WHT typically (services invoiced directly)
      const si = cog.service_start_date ? Math.max(0, monthDiff(startYM, toYM(cog.service_start_date))) : 0
      const ei = cog.service_end_date   ? Math.min(totalMonths - 1, monthDiff(startYM, toYM(cog.service_end_date))) : totalMonths - 1
      for (let m = si; m <= ei && m < totalMonths; m++) {
        const year = Math.floor(m / 12)
        const cost = cog.service_method === 'pct_revenue'
          ? (revenueByProduct[pi]?.[m] || 0) * ((Number(cog.service_pct) || 0) / 100)
            * Math.pow(1 + (Number(cog.service_annual_change) || 0) / 100, year)
          : (Number(cog.service_amount) || 0) * Math.pow(1 + (Number(cog.service_annual_increase) || 0) / 100, year)
        if (!cost) continue
        prodCog[m]        += cost
        cogsByMonth[m]    += cost
        cogsIncurred[m]   += cost
        detailService[m]  += cost
        applyPaymentPolicy(cogsPaymentsByMonth, m, cost, cog.service_payment_policy ?? CASH_POLICY)
        applyPaymentPolicy(apGrossPaidByMonth,  m, cost, cog.service_payment_policy ?? CASH_POLICY)
        apGrossInvoicedByMonth[m] += cost
      }
    }

    cogsByProduct.push(prodCog)
    cogsByProductDetail.push({
      name:        prodDef.name  || `Product ${pi + 1}`,
      nature:      cog.nature,
      rmCogs:      detailRM,
      dlCogs:      detailDL,
      ohCogs:      detailOH,
      tradingCogs: detailTrading,
      serviceCogs: detailService,
    })
  }

  // ── AP End Balance = cumulative gross invoiced − cumulative gross paid ─────
  const apByMonth = new Array(totalMonths).fill(0)
  let cumAPInv = 0, cumAPPaid = 0
  for (let m = 0; m < totalMonths; m++) {
    cumAPInv  += apGrossInvoicedByMonth[m]
    cumAPPaid += apGrossPaidByMonth[m]
    apByMonth[m] = Math.max(0, cumAPInv - cumAPPaid)
  }

  // ── Credit WHT Payable: accumulate monthly, pay in April / July / October / January
  //
  //  Quarter rule (Egyptian tax):
  //    Jan + Feb + Mar  → paid in April   (calendar month 4)
  //    Apr + May + Jun  → paid in July    (calendar month 7)
  //    Jul + Aug + Sep  → paid in October (calendar month 10)
  //    Oct + Nov + Dec  → paid in January (calendar month 1) of the NEXT year
  //
  //  Payment months by 0-indexed calendar month: 3 (Apr), 6 (Jul), 9 (Oct), 0 (Jan)
  const startMonth0           = parseInt(startYM.split('-')[1]) - 1  // 0-indexed calendar month of study start
  const creditWhtPaidByMonth  = new Array(totalMonths).fill(0)
  const creditWhtBalByMonth   = new Array(totalMonths).fill(0)
  let cumCreditWht = 0
  for (let m = 0; m < totalMonths; m++) {
    cumCreditWht += creditWhtByMonth[m]
    const calMonth       = (startMonth0 + m) % 12
    const isPaymentMonth = (calMonth === 3 || calMonth === 6 || calMonth === 9 || calMonth === 0)
    if (isPaymentMonth && cumCreditWht > 0) {
      creditWhtPaidByMonth[m] = cumCreditWht
      cumCreditWht = 0
    }
    creditWhtBalByMonth[m] = cumCreditWht
  }

  return {
    cogsByMonth,
    cogsByProduct,
    cogsByProductDetail,
    cogsPaymentsByMonth,
    purchaseVatByMonth,
    apByMonth,
    creditWhtByMonth,
    creditWhtPaidByMonth,
    creditWhtBalByMonth,
    inventoryByMonth,
    mfgLaborCapitalizedByMonth,
    ohUnabsorbedByMonth,
    rmPaymentsByName,
    ohPaymentsByName,
  }
}