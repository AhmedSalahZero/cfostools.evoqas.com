/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  InvestaWatch — Study Engine: Steps 6–12 — Financials
 *  File: resources/js/Utils/StudyEngine/buildFinancials.js
 *
 *  Contains:
 *   calcVATPayable    (Step 6)
 *   buildPL           (Step 7)
 *   calcCorpTaxBalance(Step 8)
 *   buildCashFlow     (Step 9)
 *   buildBalanceSheet (Step 10)
 *   aggregatePLByYear (Step 11)
 *   aggregateCFByYear (Step 11)
 *   aggregateBSByYear (Step 11)
 *   calcKPIs          (Step 12)
 * ═══════════════════════════════════════════════════════════════════════════
 */

import { toYM, startMonthIndex, makeSupplierPaymentKey, makeExpenseKey, makePLExpenseKey } from './engineUtils.js'

const TAX_LOSS_CARRY_YEARS = 5

// ─────────────────────────────────────────────────────────────────────────────
//  STEP 6 — VAT PAYABLE
// ─────────────────────────────────────────────────────────────────────────────
export function calcVATPayable(totalMonths, salesVatByMonth, purchaseVatByMonth) {
  const netVatByMonth  = new Array(totalMonths).fill(0)
  const vatPaidByMonth = new Array(totalMonths).fill(0)
  const vatBalByMonth  = new Array(totalMonths).fill(0)

  for (let m = 0; m < totalMonths; m++) {
    netVatByMonth[m] = (salesVatByMonth[m] || 0) - (purchaseVatByMonth[m] || 0)
  }

  let bal = 0
  for (let m = 0; m < totalMonths; m++) {
    if (m > 0 && bal > 0) { vatPaidByMonth[m] = bal; bal = 0 }
    bal += netVatByMonth[m]
    vatBalByMonth[m] = bal
  }

  return { netVatByMonth, vatPaidByMonth, vatBalByMonth }
}

// ─────────────────────────────────────────────────────────────────────────────
//  STEP 7 — P&L
// ─────────────────────────────────────────────────────────────────────────────
export function buildPL(study, revenue, cogs, manpower, expenses, fa) {
  const totalMonths = study.duration_years * 12
  const taxRate     = (Number(study.corporate_tax_rate) || 0) / 100
  const pl          = []

  // First pass: compute EBT monthly (no tax yet)
  const ebtByMonth = []
  for (let m = 0; m < totalMonths; m++) {
    const rev        = revenue.revenueByMonth[m] || 0
    const cogsCost   = cogs.cogsByMonth[m]        || 0
    const mfgDep     = fa.depMfgByProduct.reduce((s, a) => s + (a[m] || 0), 0)
    const totalCogs  = cogsCost + mfgDep
    const grossProfit   = rev - totalCogs
    const totalManpower = manpower.manpowerByMonth[m] || 0
    const capitalizedMfgLabor = cogs.mfgLaborCapitalizedByMonth?.[m] || 0
    const manpowerCost  = Math.max(0, totalManpower - capitalizedMfgLabor)
    const opexCost      = expenses.expensesByMonth[m] || 0
    const adminDep      = fa.depAdminByMonth[m]       || 0
    const ohUnabsorbed  = cogs.ohUnabsorbedByMonth?.[m] || 0
    const ebitda        = grossProfit - (manpowerCost + opexCost + ohUnabsorbed) + mfgDep
    const ebit          = ebitda - (mfgDep + adminDep)
    const finCost       = (fa.loanInterestByMonth[m] || 0) + (expenses.financeByMonth?.[m] || 0)
    const ebt           = ebit - finCost
    ebtByMonth.push({ rev, cogsCost, mfgDep, totalCogs, grossProfit, manpowerCost, opexCost, ohUnabsorbed, adminDep, ebitda, ebit, finCost, ebt })
  }

  // Second pass: book tax at calendar year-end (December) and at the last study month.
  // Losses carry forward for TAX_LOSS_CARRY_YEARS (Egyptian CIT: 5 years).
  let lossPool = []
  const startM0 = startMonthIndex(study)
  for (let m = 0; m < totalMonths; m++) {
    const e = ebtByMonth[m]
    let tax = 0
    const calM = (startM0 + m) % 12
    if (calM === 11 || m === totalMonths - 1) {
      const yearStart = Math.max(0, m - calM)
      const annualEBT = ebtByMonth.slice(yearStart, m + 1).reduce((s, x) => s + x.ebt, 0)
      const calYear = parseInt(String(study.study_start_date).slice(0, 4), 10)
        + Math.floor((startM0 + m) / 12)
      lossPool = lossPool.filter(l => l.expiresYear >= calYear)
      if (annualEBT < 0) {
        lossPool.push({ remain: -annualEBT, expiresYear: calYear + TAX_LOSS_CARRY_YEARS })
        tax = 0
      } else {
        let taxable = annualEBT
        for (const l of lossPool) {
          if (taxable <= 0) break
          const use = Math.min(l.remain, taxable)
          l.remain -= use
          taxable -= use
        }
        lossPool = lossPool.filter(l => l.remain > 0)
        tax = taxable * taxRate
      }
    }
    const netProfit   = e.ebt - tax
    const cogsDetail  = cogs.cogsByProductDetail.map(d => ({
      name:        d.name,
      nature:      d.nature,
      rmCogs:      d.rmCogs[m]      || 0,
      dlCogs:      d.dlCogs[m]      || 0,
      ohCogs:      d.ohCogs[m]      || 0,
      tradingCogs: d.tradingCogs[m] || 0,
      serviceCogs: d.serviceCogs[m] || 0,
      mfgDep:      (fa.depMfgByProduct[cogs.cogsByProductDetail.indexOf(d)] || [])[m] || 0,
    }))
    const expensesDetail = Object.entries(expenses.expensesPLByName ?? {}).map(([name, arr]) => ({
      name,
      plKey: makePLExpenseKey(name),
      value: arr[m] || 0,
    }))
    pl.push({
      month: m,
      revenue: e.rev, cogs: e.totalCogs, rawCogs: e.cogsCost, mfgDep: e.mfgDep,
      grossProfit: e.grossProfit, grossMarginPct: e.rev > 0 ? (e.grossProfit / e.rev) * 100 : 0,
      manpowerCost: e.manpowerCost, opexCost: e.opexCost, ohUnabsorbed: e.ohUnabsorbed, adminDep: e.adminDep,
      totalOpEx: e.manpowerCost + e.opexCost + e.ohUnabsorbed + e.adminDep,
      ebitda: e.ebitda, ebitdaMarginPct: e.rev > 0 ? (e.ebitda / e.rev) * 100 : 0,
      totalDep: fa.depByMonth[m] || 0,
      ebit: e.ebit, finCost: e.finCost, ebt: e.ebt,
      tax, netProfit,
      netMarginPct: e.rev > 0 ? (netProfit / e.rev) * 100 : 0,
      cogsDetail,
      expensesDetail,
      ...Object.fromEntries(expensesDetail.map(ed => [ed.plKey, ed.value])),
    })
  }
  return pl
}

// ─────────────────────────────────────────────────────────────────────────────
//  STEP 8 — CORPORATE TAX BALANCE
// ─────────────────────────────────────────────────────────────────────────────
export function calcCorpTaxBalance(study, pl, revenue) {
  const totalMonths = study.duration_years * 12
  const startYM     = toYM(study.study_start_date)
  const startMonth  = parseInt(startYM.split('-')[1]) - 1  // 0-indexed

  const corpTaxPaidByMonth = new Array(totalMonths).fill(0)
  const corpTaxBalByMonth  = new Array(totalMonths).fill(0)
  let runningBal = 0

  for (let m = 0; m < totalMonths; m++) {
    runningBal -= (revenue.debitWhtByMonth[m] || 0)
    runningBal += pl[m].tax
    const calMonth = (startMonth + m) % 12
    if (calMonth === 3 && runningBal > 0) {
      corpTaxPaidByMonth[m] = runningBal
      runningBal = 0
    }
    corpTaxBalByMonth[m] = runningBal
  }

  return { corpTaxPaidByMonth, corpTaxBalByMonth }
}

// ─────────────────────────────────────────────────────────────────────────────
//  STEP 9 — CASH FLOW (2-pass: find min cash, inject equity)
// ─────────────────────────────────────────────────────────────────────────────
export function buildCashFlow(study, revenue, cogs, manpower, expenses, fa, vatCalc, corpTax, openingCash, opening = null) {
  const totalMonths     = study.duration_years * 12
  const rmPaymentNames  = Object.keys(cogs.rmPaymentsByName ?? {})
  const ohPaymentNames  = Object.keys(cogs.ohPaymentsByName ?? {})
  const expPaymentNames = Object.keys(expenses.expensesCashByName ?? {})

  function runCF(injection) {
    const cf = []
    let cum  = openingCash
    for (let m = 0; m < totalMonths; m++) {
      const rec          = revenue.receiptsByMonth[m]        || 0
      const vatOut       = vatCalc.vatPaidByMonth[m]         || 0
      const cogsP        = cogs.cogsPaymentsByMonth[m]       || 0
      const creditWhtOut = cogs.creditWhtPaidByMonth[m]      || 0
      const manP         = manpower.manpowerCashByMonth[m]   || 0
      const expP         = expenses.expensesCashByMonth[m]   || 0
      const intP         = fa.loanInterestByMonth[m]         || 0
      const corpTaxOut   = corpTax.corpTaxPaidByMonth[m]     || 0
      const capex        = fa.capexCashByMonth[m]            || 0
      const loanIn       = fa.loanDrawdownByMonth[m]         || 0
      const loanOut      = fa.loanRepayByMonth[m]            || 0
      const inject       = m === 0 ? injection : 0
      const openRec      = opening?.receiptsByMonth[m]       || 0
      const openCLPaid   = opening?.clPaymentsByMonth[m]     || 0
      const openLTPaid   = opening?.ltPaymentsByMonth[m]     || 0

      const operatingCF = rec + openRec - openCLPaid - vatOut - cogsP - creditWhtOut - manP - expP - corpTaxOut
      const investingCF = -capex
      const financingCF = loanIn - loanOut + inject - openLTPaid
      const netCF       = operatingCF + investingCF + financingCF - intP
      cum += netCF

      cf.push({
        month: m,
        receipts: rec, openingReceipts: openRec, openingPayablesPaid: openCLPaid, openingDebtPaid: openLTPaid,
        vatPaid: vatOut, cogsPaid: cogsP,
        creditWhtPaid: creditWhtOut, manpowerPaid: manP, expensesPaid: expP,
        corpTaxPaid: corpTaxOut, interestPaid: intP, operatingCF,
        capexPaid: capex, investingCF,
        loanDrawdown: loanIn, loanRepay: loanOut, equityInjection: inject, financingCF,
        netCF, cumulativeCash: cum,
      })

      const row = cf[cf.length - 1]
      for (const name of rmPaymentNames)  row[makeSupplierPaymentKey('rm', name)] = cogs.rmPaymentsByName?.[name]?.[m] || 0
      for (const name of ohPaymentNames)  row[makeSupplierPaymentKey('oh', name)] = cogs.ohPaymentsByName?.[name]?.[m] || 0
      for (const name of expPaymentNames) row[makeExpenseKey(name)]               = expenses.expensesCashByName?.[name]?.[m] || 0
    }
    return cf
  }

  const pass1               = runCF(0)
  const minCash             = Math.min(0, ...pass1.map(r => r.cumulativeCash))
  const requiredEquityTopUp = -minCash
  const cf                  = runCF(requiredEquityTopUp)
  return { cf, requiredEquityTopUp }
}

// ─────────────────────────────────────────────────────────────────────────────
//  OPENING BALANCE HELPERS
// ─────────────────────────────────────────────────────────────────────────────
function calcPreExistingDep(fixedAssets, m) {
  return (fixedAssets ?? []).reduce((sum, fa) => {
    const monthlyDep    = Number(fa.monthly_dep          || 0)
    const monthsLeft    = Number(fa.dep_months_remaining || 0)
    const depMonthsUsed = Math.min(m + 1, monthsLeft)
    return sum + (monthlyDep * depMonthsUsed)
  }, 0)
}

function operatingNwc(b) {
  return (b.ar || 0) + (b.inventory || 0) + (b.vatReceivable || 0) + (b.prepaidExp || 0) + (b.otherCA || 0)
    - (b.ap || 0) - (b.accruedExp || 0) - (b.openCLRemaining || 0)
}

// ─────────────────────────────────────────────────────────────────────────────
//  STEP 10 — BALANCE SHEET (monthly)
// ─────────────────────────────────────────────────────────────────────────────
export function buildBalanceSheet(study, pl, cf, fa, revenue, cogs, vatCalc, corpTax, openingBalance, requiredEquityTopUp, openingEquity = {}, expensesCalc = null, opening = null) {
  const totalMonths = study.duration_years * 12
  const { openingPaidUpCapital = 0, openingLegalReserve = 0, openingRetainedEarnings = 0 } = openingEquity

  const ob         = openingBalance ?? {}
  const openNetFA  = ob.totals?.net_fa != null
    ? Number(ob.totals.net_fa)
    : ((ob.sections?.non_current_assets ?? []).reduce((s, r) => s + (Number(r.amount) || 0), 0))

  const paidUpCapital    = openingPaidUpCapital + requiredEquityTopUp

  let legalReserveAccum = openingLegalReserve
  const legalReserveCap = paidUpCapital * 0.5
  const startM0 = startMonthIndex(study)

  const bs = []
  let retainedEarnings = openingRetainedEarnings
  let cumExpPL = 0, cumExpCash = 0, yearProfit = 0

  for (let m = 0; m < totalMonths; m++) {
    const currentProfit = pl[m].netProfit

    const preExistingAccumDep = calcPreExistingDep(ob.fixed_assets ?? [], m)
    const grossFA  = openNetFA + fa.grossFAByMonth[m]
    const accumDep = fa.accumDepByMonth[m] + preExistingAccumDep
    const netFA    = grossFA - accumDep

    const cash              = cf[m].cumulativeCash
    const ar                = revenue.arByMonth[m]             || 0
    const inventory         = cogs.inventoryByMonth[m]         || 0
    const corpTaxPrepayment = Math.max(0, -(corpTax.corpTaxBalByMonth[m] || 0))
    cumExpPL   += (expensesCalc?.expensesByMonth?.[m] || 0) + (expensesCalc?.financeByMonth?.[m] || 0)
    cumExpCash += expensesCalc?.expensesCashByMonth?.[m] || 0
    const prepaidExp    = Math.max(0, cumExpCash - cumExpPL)
    const accruedExp    = Math.max(0, cumExpPL - cumExpCash)
    const vatReceivable = Math.max(0, -(vatCalc.vatBalByMonth[m] || 0))
    const otherCA       = opening ? opening.otherCABalance[m] : 0
    const otherNCA      = opening ? opening.otherNonCurrent : 0
    const totalCA       = cash + ar + inventory + corpTaxPrepayment + vatReceivable + prepaidExp + otherCA
    const totalAssets   = netFA + otherNCA + totalCA

    const openLTLRemaining = opening ? opening.ltBalance[m] : 0
    const longTermDebt     = Math.max(0, fa.loanBalByMonth[m]) + openLTLRemaining

    const openCLRemaining  = opening ? opening.clBalance[m] : 0
    const ap               = cogs.apByMonth[m]                 || 0
    const vatPayable       = Math.max(0, vatCalc.vatBalByMonth[m] || 0)
    const corpTaxPayable   = Math.max(0, corpTax.corpTaxBalByMonth[m] || 0)
    const creditWhtPayable = cogs.creditWhtBalByMonth[m]       || 0

    const totalCL   = ap + vatPayable + corpTaxPayable + creditWhtPayable + openCLRemaining + accruedExp
    const totalLiab = longTermDebt + totalCL

    yearProfit += currentProfit
    const isYearEnd = ((startM0 + m) % 12) === 11
    let legalReserveTransfer = 0
    if (isYearEnd) {
      if (yearProfit > 0 && legalReserveAccum < legalReserveCap) {
        legalReserveTransfer = Math.min(yearProfit * 0.05, legalReserveCap - legalReserveAccum)
        legalReserveAccum += legalReserveTransfer
      }
      yearProfit = 0
    }

    const equityPaidUp   = paidUpCapital
    const equityLegalRes = legalReserveAccum
    const equityRetained = retainedEarnings - legalReserveTransfer
    const equityProfit   = currentProfit
    const totalEquity    = equityPaidUp + equityLegalRes + equityRetained + equityProfit
    const totalLiabEq    = totalLiab + totalEquity

    bs.push({
      month: m,
      grossFA, accumDep, netFA,
      cash, ar, inventory, corpTaxPrepayment, vatReceivable, prepaidExp, otherCA, otherNCA, accruedExp,
      totalCurrentAssets: totalCA, totalAssets,
      longTermDebt, openLTLRemaining,
      ap, vatPayable, corpTaxPayable, creditWhtPayable, openCLRemaining,
      totalCurrentLiabilities: totalCL, totalLiabilities: totalLiab,
      legalReserveTransfer, legalReserveAccum,
      equityPaidUp, equityLegalRes, equityRetained, equityProfit,
      totalEquity, totalLiabEquity: totalLiabEq,
    })

    retainedEarnings += currentProfit - legalReserveTransfer
  }

  return bs
}

// ─────────────────────────────────────────────────────────────────────────────
//  STEP 11 — AGGREGATE TO ANNUAL
// ─────────────────────────────────────────────────────────────────────────────
function aggYears(data, fields, n) {
  return Array.from({ length: n }, (_, y) => {
    const row = { year: y + 1 }
    for (const f of fields) row[f] = 0
    for (let m = y * 12; m < (y + 1) * 12 && m < data.length; m++)
      for (const f of fields) row[f] += data[m][f] || 0
    return row
  })
}

export function aggregatePLByYear(pl, n) {
  return aggYears(pl, ['revenue','cogs','rawCogs','mfgDep','grossProfit','manpowerCost','opexCost','ohUnabsorbed','adminDep','totalOpEx','ebitda','totalDep','ebit','finCost','ebt','tax','netProfit'], n)
    .map((y, yi) => {
      const yearMonths = pl.slice(yi * 12, (yi + 1) * 12)
      const cogsDetail = yearMonths[0]?.cogsDetail?.map((d, di) => ({
        name:   d.name,
        nature: d.nature,
        rmCogs:      yearMonths.reduce((s, m) => s + (m.cogsDetail?.[di]?.rmCogs      || 0), 0),
        dlCogs:      yearMonths.reduce((s, m) => s + (m.cogsDetail?.[di]?.dlCogs      || 0), 0),
        ohCogs:      yearMonths.reduce((s, m) => s + (m.cogsDetail?.[di]?.ohCogs      || 0), 0),
        tradingCogs: yearMonths.reduce((s, m) => s + (m.cogsDetail?.[di]?.tradingCogs || 0), 0),
        serviceCogs: yearMonths.reduce((s, m) => s + (m.cogsDetail?.[di]?.serviceCogs || 0), 0),
        mfgDep:      yearMonths.reduce((s, m) => s + (m.cogsDetail?.[di]?.mfgDep      || 0), 0),
      })) ?? []

      const expenseKeys  = Object.keys(yearMonths[0] ?? {}).filter(k => k.startsWith('expensePL_'))
      const expensePLAgg = {}
      for (const k of expenseKeys) {
        expensePLAgg[k] = yearMonths.reduce((s, m) => s + (m[k] || 0), 0)
      }

      return {
        ...y,
        ...expensePLAgg,
        grossMarginPct:  y.revenue > 0 ? y.grossProfit / y.revenue * 100 : 0,
        ebitdaMarginPct: y.revenue > 0 ? y.ebitda      / y.revenue * 100 : 0,
        netMarginPct:    y.revenue > 0 ? y.netProfit   / y.revenue * 100 : 0,
        cogsDetail,
      }
    })
}

export function aggregateCFByYear(cf, n) {
  const baseFields = ['receipts','openingReceipts','openingPayablesPaid','openingDebtPaid','vatPaid','cogsPaid','creditWhtPaid','manpowerPaid','expensesPaid','corpTaxPaid','interestPaid','operatingCF','capexPaid','investingCF','loanDrawdown','loanRepay','equityInjection','financingCF','netCF']
  const dynamicSupplierFields = Object.keys(cf[0] ?? {}).filter(k => k.startsWith('supplierPay_rm_') || k.startsWith('supplierPay_oh_'))
  const dynamicExpenseFields  = Object.keys(cf[0] ?? {}).filter(k => k.startsWith('expensePay_'))
  const yrs = aggYears(cf, [...baseFields, ...dynamicSupplierFields, ...dynamicExpenseFields], n)
  yrs.forEach((y, i) => {
    const last = Math.min((i + 1) * 12 - 1, cf.length - 1)
    y.cumulativeCash = cf[last]?.cumulativeCash ?? 0
    y.year = i + 1
  })
  return yrs
}

export function aggregateBSByYear(bs, n) {
  return Array.from({ length: n }, (_, y) => ({
    year: y + 1,
    ...bs[Math.min((y + 1) * 12 - 1, bs.length - 1)],
  }))
}

// ─────────────────────────────────────────────────────────────────────────────
//  STEP 12 — KPIs
// ─────────────────────────────────────────────────────────────────────────────
export function calcKPIs(study, plByYear, cfByYear, fa, openingBalance, requiredEquityTopUp, bsByYear = [], openingNwc = 0) {
  const wacc       = (Number(study.required_investment_return_pct) || 10) / 100
  const perpGrowth = (Number(study.perpetual_growth_rate_pct) || 3) / 100
  const taxRate    = (Number(study.corporate_tax_rate) || 0) / 100
  const openEq     = openingBalance?.paid_up_capital != null
    ? Number(openingBalance.paid_up_capital)
    : (openingBalance?.sections?.equity ?? []).reduce((s, r) => s + (Number(r.amount) || 0), 0)

  const totalInvestment = fa.totalEquityFunded + requiredEquityTopUp + openEq

  let prevNwc = openingNwc
  const fcff = plByYear.map((y, i) => {
    const nwc = operatingNwc(bsByYear[i] || {})
    const dNwc = nwc - prevNwc
    prevNwc = nwc
    return y.ebit * (1 - taxRate) + y.totalDep - (cfByYear[i]?.capexPaid || 0) - dNwc
  })
  const last  = fcff[fcff.length - 1] || 0
  const tv    = wacc > perpGrowth && last > 0 ? (last * (1 + perpGrowth)) / (wacc - perpGrowth) : 0

  let npv = -totalInvestment
  fcff.forEach((f, i) => { npv += f / Math.pow(1 + wacc, i + 1) })
  npv += tv / Math.pow(1 + wacc, fcff.length)

  const stream = [-totalInvestment, ...fcff.slice(0, -1), (fcff[fcff.length - 1] || 0) + tv]
  const npvAt  = r => stream.reduce((s, c, i) => s + c / Math.pow(1 + r, i), 0)
  let r = wacc
  for (let i = 0; i < 200; i++) {
    const f  = npvAt(r)
    const df = stream.reduce((s, c, i2) => s - i2 * c / Math.pow(1 + r, i2 + 1), 0)
    if (Math.abs(df) < 1e-10) break
    const nr = r - f / df
    if (Math.abs(nr - r) < 1e-8) { r = nr; break }
    r = Math.max(-0.99, Math.min(100, nr))
  }
  const irr = r

  const totalR = fcff.reduce((s, f) => s + Math.max(0, f), 0) + Math.max(0, tv)
  const moic   = totalInvestment > 0 ? totalR / totalInvestment : 0

  let paybackMonth = null, cum = -totalInvestment
  for (let y = 0; y < fcff.length; y++) {
    const prev = cum; cum += fcff[y]
    if (cum >= 0 && paybackMonth === null)
      paybackMonth = y * 12 + Math.round((fcff[y] > 0 ? Math.abs(prev) / fcff[y] : 0) * 12)
  }

  let beMonth = null, cumP = 0
  for (let y = 0; y < plByYear.length; y++) {
    const prev = cumP; cumP += plByYear[y].netProfit
    if (cumP >= 0 && beMonth === null)
      beMonth = y * 12 + Math.round((plByYear[y].netProfit > 0 ? Math.abs(prev) / plByYear[y].netProfit : 0) * 12)
  }

  const avg = arr => arr.length ? arr.reduce((a, b) => a + b, 0) / arr.length : 0

  return {
    npv, irr: irr * 100, moic,
    paybackMonths: paybackMonth, paybackYears: paybackMonth !== null ? paybackMonth / 12 : null,
    breakEvenMonth: beMonth,    breakEvenYears: beMonth !== null ? beMonth / 12 : null,
    totalInvestment, totalFixedAssetCapex: fa.totalCapex,
    workingCapitalInjection: requiredEquityTopUp,
    totalEquityFunded: fa.totalEquityFunded, totalDebt: fa.totalDebtDrawn,
    terminalValue: tv, fcff,
    peakRevenue: Math.max(...plByYear.map(y => y.revenue)),
    peakProfit:  Math.max(...plByYear.map(y => y.netProfit)),
    avgGrossMarginPct:  avg(plByYear.map(y => y.grossMarginPct)),
    avgNetMarginPct:    avg(plByYear.map(y => y.netMarginPct)),
    avgEbitdaMarginPct: avg(plByYear.map(y => y.ebitdaMarginPct)),
    wacc: wacc * 100, perpGrowthRate: perpGrowth * 100,
  }
}
