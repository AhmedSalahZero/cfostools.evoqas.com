/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  InvestaWatch — Study Engine: Shared Utilities
 *  File: resources/js/Utils/StudyEngine/engineUtils.js
 * ═══════════════════════════════════════════════════════════════════════════
 */

export function addMonths(yyyymm, n) {
  const [y, m] = yyyymm.split('-').map(Number)
  const d = new Date(y, m - 1 + n)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

export function toYM(s) {
  if (!s) return null
  return String(s).slice(0, 7)
}

export function monthDiff(startYM, endYM) {
  const [sy, sm] = startYM.split('-').map(Number)
  const [ey, em] = endYM.split('-').map(Number)
  return (ey - sy) * 12 + (em - sm)
}

export function buildTimeline(startYM, totalMonths) {
  return Array.from({ length: totalMonths }, (_, i) => addMonths(startYM, i))
}

export function resolvePolicyTranches(policy) {
  const preset = policy?.preset
  if (preset && preset !== 'custom') {
    const presetDays = { cash: 0, quarterly: 90, semi_annual: 180, annual: 360 }
    const days = presetDays[preset] ?? 0
    return [{ pct: 100, days }]
  }
  return policy?.tranches ?? [{ pct: 100, days: 0 }]
}

export function applyPaymentPolicy(arr, idx, amount, policy) {
  if (!amount || amount === 0) return
  const tranches = resolvePolicyTranches(policy)
  for (const t of tranches) {
    const pct = Number(t.pct) || 0
    const days = Number(t.days) || 0
    if (pct === 0) continue
    const target = idx + Math.round(days / 30)
    if (target < arr.length) arr[target] += (amount * pct) / 100
  }
}

export function applyCollectionPolicy(arr, idx, amount, policy) {
  if (!amount || amount === 0) return
  const tranches = resolvePolicyTranches(policy)
  for (const t of tranches) {
    const pct = Number(t.pct) || 0
    const days = Number(t.days) || 0
    if (pct === 0) continue
    const target = idx + Math.round(days / 30)
    if (target < arr.length) arr[target] += (amount * pct) / 100
  }
}

export function slugifyKeyPart(v) {
  return String(v ?? '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '') || 'unnamed'
}

export function makeSupplierPaymentKey(type, name) {
  return `supplierPay_${type}_${slugifyKeyPart(name)}`
}

export function makeExpenseKey(name) {
  return `expensePay_${slugifyKeyPart(name)}`
}

export function makePLExpenseKey(name) {
  return `expensePL_${slugifyKeyPart(name)}`
}

export const CASH_POLICY       = { tranches: [{ pct: 100, days: 0  }] }
export const THIRTY_DAY_POLICY = { tranches: [{ pct: 100, days: 30 }] }
