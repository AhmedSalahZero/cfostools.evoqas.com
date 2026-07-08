/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  InvestaWatch — Study Engine: Step 1 — Revenue
 *  File: resources/js/Utils/StudyEngine/calcRevenue.js
 *
 *  ACCOUNTING:
 *  Invoice issued to customer:
 *    Net Revenue (P&L)         100,000   → P&L line
 *    + Sales VAT               14,000    → Adds to AR, VAT Payable
 *    Gross invoice             114,000   → AR increases by this amount
 *
 *  When customer pays (per collection policy):
 *    Cash received = (Revenue + VAT − Debit WHT) per tranche
 *    Debit WHT = Revenue collected × WHT rate  → Corporate Tax prepayment
 *    AR decreases by (Revenue + VAT) of that tranche
 *
 *  AR End Balance = Total Dues (cum) − Total Collections (cum)
 *  VAT Payable (net) = Sales VAT invoiced − Purchase VAT on COGS
 *  Debit WHT accumulates monthly → offsets corporate tax at year-end
 * ═══════════════════════════════════════════════════════════════════════════
 */

import { toYM, buildTimeline, resolvePolicyTranches, CASH_POLICY } from './engineUtils.js'

// productDefs = props.products from Create.vue (step 1) — carries vat_rate & withhold_tax_rate
// projections  = props.projections from SalesProjection.vue (step 2) — carries prices, volumes, collection policies
export function calcRevenue(study, projections, productDefs) {
  const totalMonths = study.duration_years * 12
  const startYM     = toYM(study.study_start_date)
  const timeline    = buildTimeline(startYM, totalMonths)
  const products    = projections?.products ?? []
  const defs        = productDefs ?? []

  const revenueByMonth              = new Array(totalMonths).fill(0)
  const salesVatInvoicedByMonth     = new Array(totalMonths).fill(0)
  const arGrossInvoicedByMonth      = new Array(totalMonths).fill(0)
  const arGrossClearedByMonth       = new Array(totalMonths).fill(0)
  const receiptsByMonth             = new Array(totalMonths).fill(0)
  const debitWhtByMonth             = new Array(totalMonths).fill(0)
  const salesVatByMonth             = new Array(totalMonths).fill(0)

  const revenueByProduct = []
  const volumeByProduct  = []

  for (let pi = 0; pi < products.length; pi++) {
    const prod = products[pi]
    const def    = defs[pi] ?? prod
    const vatPct = (Number(def.vat_rate)          || Number(prod.vat_rate)          || 0) / 100
    const whtPct = (Number(def.withhold_tax_rate) || Number(prod.withhold_tax_rate) || 0) / 100

    const localPct  = (prod.market_split?.local_pct  ?? 100) / 100
    const exportPct = (prod.market_split?.export_pct ?? 0)   / 100

    const prodRev   = new Array(totalMonths).fill(0)
    const prodVol   = new Array(totalMonths).fill(0)
    const prodLocal = new Array(totalMonths).fill(0)
    const prodExp   = new Array(totalMonths).fill(0)

    // Year 1 — monthly
    for (let mi = 0; mi < 12 && mi < totalMonths; mi++) {
      const mo  = prod.year1_months?.[mi] ?? {}
      const rev = (Number(mo.price) || 0) * (Number(mo.volume) || 0)
      prodRev[mi]   = rev
      prodVol[mi]   = Number(mo.volume) || 0
      prodLocal[mi] = rev * localPct
      prodExp[mi]   = rev * exportPct
      revenueByMonth[mi] += rev
    }
    // Year 2 — monthly
    for (let mi = 0; mi < 12 && mi + 12 < totalMonths; mi++) {
      const mo  = prod.year2_months?.[mi] ?? {}
      const rev = (Number(mo.price) || 0) * (Number(mo.volume) || 0)
      const idx = mi + 12
      prodRev[idx]   = rev
      prodVol[idx]   = Number(mo.volume) || 0
      prodLocal[idx] = rev * localPct
      prodExp[idx]   = rev * exportPct
      revenueByMonth[idx] += rev
    }
    // Year 3+ — annual spread across months
    for (let yi = 0; yi < (prod.annual_years ?? []).length; yi++) {
      const yr   = prod.annual_years[yi]
      const aRev = (Number(yr.price) || 0) * (Number(yr.volume) || 0)
      const aVol = Number(yr.volume) || 0
      const base = (yi + 2) * 12
      for (let mi = 0; mi < 12 && base + mi < totalMonths; mi++) {
        const idx = base + mi
        prodRev[idx]   = aRev / 12
        prodVol[idx]   = aVol / 12
        prodLocal[idx] = (aRev / 12) * localPct
        prodExp[idx]   = (aRev / 12) * exportPct
        revenueByMonth[idx] += aRev / 12
      }
    }

    const colLocal     = prod.collection_local  ?? CASH_POLICY
    const colExport    = prod.collection_export ?? CASH_POLICY
    const hasBreakdown = prod.local_allocation?.dimension !== 'none'
                      && (prod.local_allocation?.rows?.length ?? 0) > 0

    for (let m = 0; m < totalMonths; m++) {
      const localRev  = prodLocal[m]
      const exportRev = prodExp[m]
      if (localRev === 0 && exportRev === 0) continue

      // VAT only on local sales (export is zero-rated in Egypt)
      const vatOnLocal  = localRev * vatPct
      const localGross  = localRev + vatOnLocal
      const exportGross = exportRev  // no VAT on exports

      salesVatByMonth[m]         += vatOnLocal
      salesVatInvoicedByMonth[m] += vatOnLocal
      arGrossInvoicedByMonth[m]  += localGross + exportGross

      const applyLocal = (grossAmt, revAmt, policy) => {
        if (!grossAmt) return
        const tranches = policy?.tranches ?? [{ pct: 100, days: 0 }]
        for (const t of tranches) {
          const pct  = (Number(t.pct) || 0) / 100
          const days = Number(t.days) || 0
          if (pct === 0) continue
          const target = m + Math.round(days / 30)
          if (target >= totalMonths) continue
          const grossTranche = grossAmt * pct
          const revTranche   = revAmt   * pct
          const wht          = revTranche * whtPct
          receiptsByMonth[target]       += grossTranche - wht
          debitWhtByMonth[target]       += wht
          arGrossClearedByMonth[target] += grossTranche
        }
      }

      const applyExport = (grossAmt, policy) => {
        if (!grossAmt) return
        const tranches = policy?.tranches ?? [{ pct: 100, days: 0 }]
        for (const t of tranches) {
          const pct  = (Number(t.pct) || 0) / 100
          const days = Number(t.days) || 0
          if (pct === 0) continue
          const target = m + Math.round(days / 30)
          if (target >= totalMonths) continue
          receiptsByMonth[target]       += grossAmt * pct
          arGrossClearedByMonth[target] += grossAmt * pct
        }
      }

      if (hasBreakdown) {
        for (const row of prod.local_allocation.rows) {
          const frac = (Number(row.pct) || 0) / 100
          const pol  = row.collection_policy ?? colLocal
          applyLocal(localGross * frac, localRev * frac, pol)
        }
      } else {
        applyLocal(localGross, localRev, colLocal)
      }
      applyExport(exportGross, colExport)
    }

    revenueByProduct.push(prodRev)
    volumeByProduct.push(prodVol)
  }

  // AR End Balance = cumulative invoiced − cumulative cleared
  const arByMonth = new Array(totalMonths).fill(0)
  let cumARInv = 0, cumARClr = 0
  for (let m = 0; m < totalMonths; m++) {
    cumARInv += arGrossInvoicedByMonth[m]
    cumARClr += arGrossClearedByMonth[m]
    arByMonth[m] = Math.max(0, cumARInv - cumARClr)
  }

  return {
    revenueByMonth,
    revenueByProduct,
    volumeByProduct,
    receiptsByMonth,
    salesVatByMonth,
    debitWhtByMonth,
    arByMonth,
    arGrossInvoicedByMonth,
    arGrossClearedByMonth,
    timeline,
  }
}
