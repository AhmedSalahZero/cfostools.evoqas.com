/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  InvestaWatch — Study Engine: Step 5 — Fixed Assets & Depreciation
 *  File: resources/js/Utils/StudyEngine/calcFixedAssets.js
 *
 *  Calculation rules:
 *  - PUP Phase (Start → End Date): payments hit Projects Under Progress;
 *    loan drawn in sync; interest capitalized (IAS 23); no depreciation
 *  - Transfer at End Date: PUP → Fixed Assets; depreciation starts month after end date
 *  - Depreciation: straight-line; Admin % → SG&A; Mfg % → COGS (allocated by product)
 *  - Replacement: every N years after end date; new layer added; new loan if debt-funded
 * ═══════════════════════════════════════════════════════════════════════════
 */

import { toYM, monthDiff } from './engineUtils.js'

export function calcFixedAssets(study, fixedAssetsData, productNames) {
  const totalMonths = study.duration_years * 12
  const startYM     = toYM(study.study_start_date)

  const depByMonth          = new Array(totalMonths).fill(0)
  const depAdminByMonth     = new Array(totalMonths).fill(0)
  const capexCashByMonth    = new Array(totalMonths).fill(0)
  const loanDrawdownByMonth = new Array(totalMonths).fill(0)
  const loanInterestByMonth = new Array(totalMonths).fill(0)
  const loanRepayByMonth    = new Array(totalMonths).fill(0)
  const depMfgByProduct     = (productNames ?? []).map(() => new Array(totalMonths).fill(0))

  let totalCapex = 0, totalDebtDrawn = 0, totalEquityFunded = 0
  const grossFAArr  = new Array(totalMonths).fill(0)
  const accumDepArr = new Array(totalMonths).fill(0)
  const loanBalArr  = new Array(totalMonths).fill(0)

  for (const asset of (fixedAssetsData ?? [])) {
    const total = Number(asset.total) || 0
    if (!total) continue

    const depDur    = Number(asset.depreciation_duration) || 0
    const adminPct  = (Number(asset.admin_dep_pct) || 0) / 100
    const mfgPct    = (Number(asset.mfg_dep_pct)  || 100) / 100
    const equityPct = (asset.equity_pct != null ? Number(asset.equity_pct) : 0) / 100
    const debtPct   = (asset.debt_pct   != null
      ? Number(asset.debt_pct)
      : (100 - (asset.equity_pct != null ? Number(asset.equity_pct) : 0))) / 100
    const equityAmt = total * equityPct
    const debtAmt   = total * debtPct

    const pupSt  = Math.max(0, asset.start_date ? monthDiff(startYM, toYM(asset.start_date)) : 0)
    const pupEn  = Math.max(pupSt, Math.min(totalMonths - 1, asset.end_date ? monthDiff(startYM, toYM(asset.end_date)) : pupSt))
    const depSt  = pupEn + 1
    const depMo  = depDur * 12
    const depEn  = Math.min(totalMonths - 1, depSt + depMo - 1)
    const pupCnt = Math.max(1, pupEn - pupSt + 1)

    totalCapex        += total
    totalDebtDrawn    += debtAmt
    totalEquityFunded += equityAmt

    const assetLoanDraw = new Array(totalMonths).fill(0)

    // ── CAPEX cash flows ──────────────────────────────────────────────────
    if (asset.payment_term === 'cash') {
      capexCashByMonth[pupSt]    += total
      loanDrawdownByMonth[pupSt] += debtAmt
      assetLoanDraw[pupSt]       += debtAmt
    } else if (asset.payment_term === 'customize' && asset.custom_payment) {
      for (const t of (asset.custom_payment.tranches ?? [])) {
        const rate = (Number(t.rate) || 0) / 100
        const idx  = Math.min(totalMonths - 1, pupSt + Math.round((Number(t.days) || 0) / 30))
        capexCashByMonth[idx]    += total * rate
        loanDrawdownByMonth[idx] += total * rate * debtPct
        assetLoanDraw[idx]       += total * rate * debtPct
      }
    } else if (asset.payment_term === 'installment' && asset.installment_config) {
      const cfg = asset.installment_config
      const rp  = (Number(cfg.reservation_pct) || 0) / 100
      const cp  = (Number(cfg.contractual_pct)  || 0) / 100
      const rem = 1 - rp - cp
      capexCashByMonth[pupSt]    += total * rp
      loanDrawdownByMonth[pupSt] += total * rp * debtPct
      assetLoanDraw[pupSt]       += total * rp * debtPct
      const mo = (total * cp) / pupCnt
      for (let m = pupSt; m <= pupEn && m < totalMonths; m++) {
        capexCashByMonth[m]    += mo
        loanDrawdownByMonth[m] += mo * debtPct
        assetLoanDraw[m]       += mo * debtPct
      }
      capexCashByMonth[pupEn]    += total * rem
      loanDrawdownByMonth[pupEn] += total * rem * debtPct
      assetLoanDraw[pupEn]       += total * rem * debtPct
    } else {
      // Default: spread evenly across PUP period
      const mo = total / pupCnt
      for (let m = pupSt; m <= pupEn && m < totalMonths; m++) {
        capexCashByMonth[m]    += mo
        loanDrawdownByMonth[m] += mo * debtPct
        assetLoanDraw[m]       += mo * debtPct
      }
    }

    const annRate   = (Number(asset.interest_pct) || 0) / 100
    const monthRate = annRate / 12

    // IAS 23: capitalise interest ONLY during the construction/PUP phase
    const hasPupPeriod = pupCnt > 1
    let capInt = 0, runBal = 0
    if (hasPupPeriod) {
      for (let m = pupSt; m <= pupEn && m < totalMonths; m++) {
        runBal += assetLoanDraw[m]
        capInt += runBal * monthRate
      }
    }
    const grossFA    = total + capInt
    const monthlyDep = depDur > 0 ? grossFA / depMo : 0

    // ── Depreciation ──────────────────────────────────────────────────────
    if (monthlyDep > 0) {
      for (let m = depSt; m <= depEn && m < totalMonths; m++) {
        depByMonth[m]      += monthlyDep
        depAdminByMonth[m] += monthlyDep * adminPct
        const mDep  = monthlyDep * mfgPct
        const alloc = asset.product_allocation ?? []
        for (let pi2 = 0; pi2 < (productNames?.length ?? 0); pi2++) {
          const found = alloc.find(a => a.product_name === productNames[pi2])
          const pct   = found
            ? (Number(found.pct) || 0) / 100
            : (alloc.length === 0 ? 1 / Math.max(1, productNames.length) : 0)
          if (depMfgByProduct[pi2]) depMfgByProduct[pi2][m] += mDep * pct
        }
      }
    }

    // ── Loan repayment schedule ───────────────────────────────────────────
    if (debtAmt > 0 && annRate > 0) {
      const grace    = Number(asset.grace_months) || 0
      const tenor    = Number(asset.tenor_months)  || 60
      const interval = asset.installment_interval === 'quarterly'   ? 3
                     : asset.installment_interval === 'semi_annual'  ? 6
                     : asset.installment_interval === 'annual'       ? 12
                     : 1
      const repSt  = pupEn + 1 + grace
      const loanAmt = debtAmt + capInt
      for (let m = pupEn + 1; m < repSt && m < totalMonths; m++) loanInterestByMonth[m] += loanAmt * monthRate
      let rem = loanAmt
      const ppI = rem / Math.ceil(tenor / interval)
      for (let m = repSt; m < repSt + tenor && m < totalMonths; m++) {
        loanInterestByMonth[m] += rem * monthRate
        if ((m - repSt) % interval === 0) {
          const p = Math.min(ppI, rem)
          loanRepayByMonth[m] += p
          rem = Math.max(0, rem - p)
        }
      }
    }

    // ── Replacement ───────────────────────────────────────────────────────
    if (asset.replacement_cost_pct > 0 && asset.replacement_interval) {
      const ry   = { '1y': 1, '2y': 2, '3y': 3, '5y': 5 }[asset.replacement_interval] ?? 10
      const ri   = ry * 12
      const rAmt = total * (Number(asset.replacement_cost_pct) / 100)
      let rIdx   = pupEn + ri
      while (rIdx < totalMonths) {
        capexCashByMonth[rIdx]    += rAmt * equityPct
        loanDrawdownByMonth[rIdx] += rAmt * debtPct
        if (depDur > 0) {
          const rdpm = rAmt / depMo
          for (let dm = rIdx + 1; dm < rIdx + 1 + depMo && dm < totalMonths; dm++) {
            depByMonth[dm]      += rdpm
            depAdminByMonth[dm] += rdpm * adminPct
            const mD2 = rdpm * mfgPct
            const al2 = asset.product_allocation ?? []
            for (let pi2 = 0; pi2 < (productNames?.length ?? 0); pi2++) {
              const found = al2.find(a => a.product_name === productNames[pi2])
              const pct   = found
                ? (Number(found.pct) || 0) / 100
                : (al2.length === 0 ? 1 / Math.max(1, productNames.length) : 0)
              if (depMfgByProduct[pi2]) depMfgByProduct[pi2][dm] += mD2 * pct
            }
          }
        }
        rIdx += ri
      }
    }
  }

  // ── Cumulative BS arrays ──────────────────────────────────────────────────
  let gR = 0, aR = 0, lR = 0
  for (let m = 0; m < totalMonths; m++) {
    gR += capexCashByMonth[m]
    aR += depByMonth[m]
    lR  = Math.max(0, lR + loanDrawdownByMonth[m] - loanRepayByMonth[m])
    grossFAArr[m]  = gR
    accumDepArr[m] = aR
    loanBalArr[m]  = lR
  }

  return {
    depByMonth, depAdminByMonth, depMfgByProduct,
    capexCashByMonth, loanDrawdownByMonth, loanInterestByMonth, loanRepayByMonth,
    grossFAByMonth:  grossFAArr,
    accumDepByMonth: accumDepArr,
    netFAByMonth:    grossFAArr.map((g, i) => g - accumDepArr[i]),
    loanBalByMonth:  loanBalArr,
    totalCapex, totalDebtDrawn, totalEquityFunded,
  }
}
