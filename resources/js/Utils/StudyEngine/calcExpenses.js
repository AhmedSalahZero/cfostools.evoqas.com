/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  InvestaWatch — Study Engine: Step 4 — OPEX Expenses
 *  File: resources/js/Utils/StudyEngine/calcExpenses.js
 * ═══════════════════════════════════════════════════════════════════════════
 */

import { toYM, monthDiff, applyPaymentPolicy, CASH_POLICY } from './engineUtils.js'

export function calcExpenses(study, expensesData, revenueByMonth) {
  const totalMonths = study.duration_years * 12
  const startYM     = toYM(study.study_start_date)
  const byMonth     = new Array(totalMonths).fill(0)
  const cashByMonth = new Array(totalMonths).fill(0)
  const byCat = {
    sales:         new Array(totalMonths).fill(0),
    marketing:     new Array(totalMonths).fill(0),
    general_admin: new Array(totalMonths).fill(0),
    finance:       new Array(totalMonths).fill(0),
  }

  const expensesPLByName   = {}
  const expensesCashByName = {}

  const ensureExpArray = (map, name) => {
    if (!map[name]) map[name] = new Array(totalMonths).fill(0)
    return map[name]
  }

  for (const row of (expensesData ?? [])) {
    const expName = row.expense_name || row.name || 'Unnamed Expense'
    const ck = row.category === 'sales' ? 'sales'
             : row.category === 'marketing' ? 'marketing'
             : row.category === 'finance' ? 'finance'
             : 'general_admin'
    const si = row.start_date ? Math.max(0, monthDiff(startYM, toYM(row.start_date))) : 0
    const ei = row.end_date   ? Math.min(totalMonths - 1, monthDiff(startYM, toYM(row.end_date))) : totalMonths - 1

    const plArr   = ensureExpArray(expensesPLByName,   expName)
    const cashArr = ensureExpArray(expensesCashByName, expName)

    if (row.expense_type === 'pct_revenue') {
      for (let m = si; m <= ei && m < totalMonths; m++) {
        const cost = (revenueByMonth[m] || 0) * ((Number(row.amount) || 0) / 100)
          * Math.pow(1 + (Number(row.annual_increase_pct) || 0) / 100, Math.floor(m / 12))
        byMonth[m] += cost
        plArr[m]   += cost
        if (byCat[ck]) byCat[ck][m] += cost
        applyPaymentPolicy(cashByMonth, m, cost, row.payment_policy ?? CASH_POLICY)
        applyPaymentPolicy(cashArr,     m, cost, row.payment_policy ?? CASH_POLICY)
      }
    } else if (row.expense_type === 'fixed_recurring') {
      for (let m = si; m <= ei && m < totalMonths; m++) {
        const cost = (Number(row.amount) || 0)
          * Math.pow(1 + (Number(row.annual_increase_pct) || 0) / 100, Math.floor(m / 12))
        byMonth[m] += cost
        plArr[m]   += cost
        if (byCat[ck]) byCat[ck][m] += cost
        applyPaymentPolicy(cashByMonth, m, cost, row.payment_policy ?? CASH_POLICY)
        applyPaymentPolicy(cashArr,     m, cost, row.payment_policy ?? CASH_POLICY)
      }
    } else if (row.expense_type === 'one_time') {
      const total   = Number(row.amount) || 0
      const amort   = Math.max(1, Number(row.amortization_months) || 1)
      const monthly = total / amort
      for (let m = si; m < si + amort && m < totalMonths; m++) {
        byMonth[m] += monthly
        plArr[m]   += monthly
        if (byCat[ck]) byCat[ck][m] += monthly
      }
      applyPaymentPolicy(cashByMonth, si, total, row.payment_policy ?? CASH_POLICY)
      applyPaymentPolicy(cashArr,     si, total, row.payment_policy ?? CASH_POLICY)
    }
  }

  return {
    expensesByMonth: byMonth,
    expensesCashByMonth: cashByMonth,
    expensesByCategory: byCat,
    expensesPLByName,
    expensesCashByName,
  }
}
