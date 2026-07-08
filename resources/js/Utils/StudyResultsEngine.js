/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  InvestaWatch — Financial Study Results Engine  v5.0
 *  File: resources/js/Utils/StudyResultsEngine.js
 *
 *  This is the master entry point. All calculation logic has been split into:
 *
 *    StudyEngine/engineUtils.js      — shared helpers & constants
 *    StudyEngine/calcRevenue.js      — Step 1: revenue, VAT, AR
 *    StudyEngine/calcCOGS.js         — Step 2: COGS, inventory, AP
 *    StudyEngine/calcManpower.js     — Step 3: salary, headcount
 *    StudyEngine/calcExpenses.js     — Step 4: OPEX expenses
 *    StudyEngine/calcFixedAssets.js  — Step 5: CAPEX, PUP, depreciation, loans
 *    StudyEngine/buildFinancials.js  — Steps 6–12: VAT, P&L, Tax, CF, BS, KPIs
 *
 *  Usage in ResultsStep.vue is UNCHANGED — still import { runStudy } from this file.
 * ═══════════════════════════════════════════════════════════════════════════
 */

import { calcRevenue }      from './StudyEngine/calcRevenue.js'
import { calcCOGS }         from './StudyEngine/calcCOGS.js'
import { calcManpower }     from './StudyEngine/calcManpower.js'
import { calcExpenses }     from './StudyEngine/calcExpenses.js'
import { calcFixedAssets }  from './StudyEngine/calcFixedAssets.js'
import {
  calcVATPayable,
  buildPL,
  calcCorpTaxBalance,
  buildCashFlow,
  buildBalanceSheet,
  aggregatePLByYear,
  aggregateCFByYear,
  aggregateBSByYear,
  calcKPIs,
} from './StudyEngine/buildFinancials.js'
import { makeSupplierPaymentKey, makeExpenseKey, makePLExpenseKey } from './StudyEngine/engineUtils.js'

// ─────────────────────────────────────────────────────────────────────────────
//  MASTER ENTRY POINT
//  Signature is identical to v4.0 — no changes needed in ResultsStep.vue
// ─────────────────────────────────────────────────────────────────────────────
export function runStudy(data) {
  const {
    study, products = [], projections = {},
    cogsData = [], manpowerData = [], expensesData = [],
    fixedAssetsData = [], openingBalance = null, manualOverrides = {},
    rawMaterials = [],  // from Create.vue Step 1 general_assumptions.raw_materials
  } = data

  if (!study?.study_start_date || !study?.duration_years)
    return { error: 'Study missing start date or duration' }

  const productNames = products.map(p => p.name || '')

  // Opening Balance fields (new format)
  const openingCash             = Number(openingBalance?.cash_bank           || 0)
  const openingPaidUpCapital    = Number(openingBalance?.paid_up_capital     || 0)
  const openingLegalReserve     = Number(openingBalance?.legal_reserve       || 0)
  const openingRetainedEarnings = Number(openingBalance?.retained_earnings   || 0)

  // ── Run calculation modules ───────────────────────────────────────────────
  // NOTE: FA runs before calcCOGS — the BOM inventory engine needs depMfgByProduct
  const revenue  = calcRevenue(study, projections, products)
  const manpower = calcManpower(study, manpowerData)
  const expenses = calcExpenses(study, expensesData, revenue.revenueByMonth)
  const fa       = calcFixedAssets(study, fixedAssetsData, productNames)

  // Match projections by product NAME (bulletproof against ordering differences)
  const projProductsList = Array.isArray(projections)
    ? projections
    : (Array.isArray(projections?.products) ? projections.products : [])

  const projectionsArr = products.map((p, i) => {
    const byName = projProductsList.find(pp => pp?.name && pp.name === p.name)
    return byName ?? projProductsList[i] ?? {}
  })

  const cogsCalc = calcCOGS(
    study, cogsData,
    revenue.revenueByProduct, revenue.volumeByProduct,
    products, projectionsArr, manpowerData, fa.depMfgByProduct,
    rawMaterials,
  )

  if (manualOverrides?.totalInvestment != null)
    fa.totalEquityFunded = Number(manualOverrides.totalInvestment)

  const vatCalc  = calcVATPayable(study.duration_years * 12, revenue.salesVatByMonth, cogsCalc.purchaseVatByMonth)
  const pl       = buildPL(study, revenue, cogsCalc, manpower, expenses, fa)
  const corpTax  = calcCorpTaxBalance(study, pl, revenue)
  const { cf, requiredEquityTopUp } = buildCashFlow(study, revenue, cogsCalc, manpower, expenses, fa, vatCalc, corpTax, openingCash)

  const bs = buildBalanceSheet(
    study, pl, cf, fa, revenue, cogsCalc, vatCalc, corpTax, openingBalance, requiredEquityTopUp,
    { openingPaidUpCapital, openingLegalReserve, openingRetainedEarnings },
  )

  const plByYear = aggregatePLByYear(pl, study.duration_years)
  const cfByYear = aggregateCFByYear(cf, study.duration_years)
  const bsByYear = aggregateBSByYear(bs, study.duration_years)
  const kpis     = calcKPIs(study, plByYear, cfByYear, fa, openingBalance, requiredEquityTopUp)

  const revenueByProductByYear = revenue.revenueByProduct.map(arr =>
    Array.from({ length: study.duration_years }, (_, y) =>
      arr.slice(y * 12, (y + 1) * 12).reduce((s, v) => s + (v || 0), 0)
    )
  )

  return {
    pl, cf, bs, plByYear, cfByYear, bsByYear,
    revenueByProductByYear, kpis,
    timeline: revenue.timeline, productNames,
    currency: study.study_currency || 'USD',
    durationYears: study.duration_years,
    startYear: new Date(study.study_start_date).getFullYear(),
    requiredEquityTopUp,
    supplierPaymentBreakdownMeta: {
      rawMaterials: Object.keys(cogsCalc.rmPaymentsByName ?? {}).map(name => ({
        name,
        key: makeSupplierPaymentKey('rm', name),
      })),
      overheads: Object.keys(cogsCalc.ohPaymentsByName ?? {}).map(name => ({
        name,
        key: makeSupplierPaymentKey('oh', name),
      })),
    },
    expenseBreakdownMeta: {
      plItems: Object.keys(expenses.expensesPLByName ?? {}).map(name => ({
        name,
        plKey: makePLExpenseKey(name),
      })),
      cashItems: Object.keys(expenses.expensesCashByName ?? {}).map(name => ({
        name,
        cashKey: makeExpenseKey(name),
      })),
    },
  }
}

export default { runStudy }
