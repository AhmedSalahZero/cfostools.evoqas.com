/**
 * Opening balance -> monthly flows.
 * Reads the shape OpeningBalanceController saves (flat arrays), with the old
 * `sections` shape as a fallback. Settlement schedules are keyed BY MONTH
 * LABEL ("Oct 2026"), not by array position.
 */

const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

export const labelOf = ym => {
  const [y, m] = String(ym).slice(0, 7).split('-').map(Number)
  return `${MON[m - 1]} ${y}`
}

export const rowsOf = (ob, key) => ob?.[key] ?? ob?.sections?.[key] ?? []

function scheduleByMonth(row, timeline) {
  const arr = new Array(timeline.length).fill(0)
  const idx = new Map()
  timeline.forEach((ym, i) => {
    idx.set(labelOf(ym), i)
    idx.set(String(ym).slice(0, 7), i)
  })
  for (const s of (row.schedule ?? [])) {
    const key = String(s.month)
    const i = idx.get(key) ?? idx.get(key.slice(0, 7))
    if (i != null) arr[i] += Number(s.amount) || 0
  }
  return arr
}

export function buildOpeningFlows(openingBalance, timeline) {
  const n = timeline.length
  const mk = () => new Array(n).fill(0)
  const out = {
    receiptsByMonth: mk(),
    clPaymentsByMonth: mk(),
    ltPaymentsByMonth: mk(),
    otherCABalance: mk(),
    clBalance: mk(),
    ltBalance: mk(),
    preDepByMonth: mk(),
    preDepAdminByMonth: mk(),
    preDepMfgByMonth: mk(),
    otherNonCurrent: 0,
    otherCAOpening: 0,
    clOpening: 0,
    ltOpening: 0,
  }
  const ob = openingBalance ?? {}

  const settle = (key, flowKey, balKey) => {
    for (const row of rowsOf(ob, key)) {
      const amt = Number(row.amount) || 0
      const sch = scheduleByMonth(row, timeline)
      let settled = 0
      for (let m = 0; m < n; m++) {
        const x = Math.min(sch[m], Math.max(0, amt - settled))
        settled += x
        out[flowKey][m] += x
        out[balKey][m] += amt - settled
      }
    }
  }

  settle('current_assets',        'receiptsByMonth',   'otherCABalance')
  settle('current_liabilities',   'clPaymentsByMonth', 'clBalance')
  settle('long_term_liabilities', 'ltPaymentsByMonth', 'ltBalance')

  out.otherNonCurrent = rowsOf(ob, 'other_non_current').reduce((s, r) => s + (Number(r.amount) || 0), 0)
  out.otherCAOpening  = rowsOf(ob, 'current_assets').reduce((s, r) => s + (Number(r.amount) || 0), 0)
  out.clOpening       = rowsOf(ob, 'current_liabilities').reduce((s, r) => s + (Number(r.amount) || 0), 0)
  out.ltOpening       = rowsOf(ob, 'long_term_liabilities').reduce((s, r) => s + (Number(r.amount) || 0), 0)

  for (const fa of (ob.fixed_assets ?? [])) {
    const md   = Number(fa.monthly_dep) || 0
    const left = Number(fa.dep_months_remaining) || 0
    const mfgPct = fa.dep_mfg_pct != null && fa.dep_mfg_pct !== ''
      ? Number(fa.dep_mfg_pct) / 100
      : 0
    const adminPct = 1 - mfgPct
    for (let m = 0; m < n && m < left; m++) {
      out.preDepByMonth[m]      += md
      out.preDepAdminByMonth[m] += md * adminPct
      out.preDepMfgByMonth[m]   += md * mfgPct
    }
  }
  return out
}
