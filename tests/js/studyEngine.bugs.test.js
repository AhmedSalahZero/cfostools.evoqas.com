import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { runStudy } from '../../resources/js/Utils/StudyResultsEngine.js'
import { buildOpeningFlows } from '../../resources/js/Utils/StudyEngine/openingBalance.js'

const mk = (price, vol) => Array.from({ length: 12 }, (_, i) => ({
  price,
  volume: Math.round(vol * (1 + 0.05 * Math.sin(i / 12 * 2 * Math.PI))),
}))

const UNITS = 4400
const COST_BOOK = 10800
const INV = UNITS * COST_BOOK
const CASH = 20_000_000
const RESERVE = 1_000_000
const cash0 = { preset: 'cash', tranches: [{ pct: 100, days: 0 }] }

function openingBalance({ ar = 0, ap = 0, netFA = 0, invAmt = INV } = {}) {
  const assets = invAmt + CASH + ar + netFA
  const paid = assets - ap - RESERVE
  return {
    source: 'manual',
    cash_bank: CASH,
    paid_up_capital: paid,
    legal_reserve: RESERVE,
    retained_earnings: 0,
    fixed_assets: netFA
      ? [{ gross_amount: netFA + 3_000_000, accum_dep: 3_000_000, monthly_dep: netFA / 60, dep_months_remaining: 60 }]
      : [],
    inventory: [{ label: 'Machines', type: 'trading', amount: invAmt }],
    other_non_current: [],
    long_term_liabilities: [],
    current_assets: ar
      ? [{
          label: 'Trade receivables',
          amount: ar,
          schedule: [
            { month: 'Oct 2026', amount: ar / 2 },
            { month: 'Nov 2026', amount: ar / 2 },
          ],
        }]
      : [],
    current_liabilities: ap
      ? [{
          label: 'Trade payables',
          amount: ap,
          schedule: [
            { month: 'Oct 2026', amount: ap / 2 },
            { month: 'Dec 2026', amount: ap / 2 },
          ],
        }]
      : [],
    equity: [],
    totals: {
      gross_fa: netFA ? netFA + 3_000_000 : 0,
      accum_dep: netFA ? 3_000_000 : 0,
      net_fa: netFA,
      inventory: invAmt,
      current_assets: ar,
      other_non_current: 0,
      long_term_liabilities: 0,
      current_liabilities: ap,
      equity: paid + RESERVE,
      total_assets: assets,
      total_liabilities: ap,
    },
    is_balanced: true,
  }
}

function study(o = {}) {
  const n = o.years ?? 3
  const s = {
    study_start_date: '2026-09-01',
    duration_years: n,
    study_currency: 'EGP',
    corporate_tax_rate: o.tax ?? 22.5,
    required_investment_return_pct: 30,
    perpetual_growth_rate_pct: 4,
  }
  const proj = {
    products: [{
      year1_months: mk(15000, 2200),
      year2_months: mk(15500, 2300),
      annual_years: Array.from({ length: Math.max(0, n - 2) }, (_, i) => ({ price: 16000 + i * 500, volume: 27600 })),
      market_split: { local_pct: 100, export_pct: 0 },
      local_allocation: { dimension: 'none', rows: [] },
      collection_local: { preset: 'custom', tranches: [{ pct: 50, days: 0 }, { pct: 25, days: 30 }, { pct: 25, days: 60 }] },
      collection_export: cash0,
    }],
  }
  const cogs = [Object.assign({
    nature: 'trading',
    unit_purchase_cost: 11000,
    annual_cost_increase_pct: 10,
    inventory_days: 60,
    beginning_inventory_units: UNITS,
    beginning_inventory_value: INV,
    purchase_payment_policy: { preset: 'custom', tranches: [{ pct: 100, days: 60 }] },
  }, o.cogs || {})]
  return {
    study: s,
    products: [{ name: 'Machines', nature: 'trading', vat_rate: 14, withhold_tax_rate: 1 }],
    projections: proj,
    cogsData: cogs,
    manpowerData: [],
    expensesData: o.expenses || [],
    fixedAssetsData: o.fa || [],
    rawMaterials: [],
    openingBalance: o.ob === undefined ? openingBalance(o.obOpts) : o.ob,
  }
}

const run = o => runStudy(study(o))
const imb = r => r.bs.map(b => b.totalAssets - b.totalLiabEquity)
const maxAbs = a => Math.max(...a.map(Math.abs))
const almost = (a, b, eps = 1) => Math.abs(a - b) < eps

describe('Study engine regression (B01–B18)', () => {
  it('T00 baseline (stock + cash + equity only) balances', () => {
    const base = run({})
    assert.ok(maxAbs(imb(base)) < 1, `imbalance ${maxAbs(imb(base))}`)
  })

  it('B01/B04 T01–T02 opening payables appear and cash falls on scheduled months', () => {
    const base = run({})
    const ap = run({ obOpts: { ap: 4_000_000 } })
    const apCash = ap.cf.map((c, i) => c.cumulativeCash - base.cf[i].cumulativeCash)
    assert.ok(maxAbs(imb(ap)) < 1, `imbalance ${maxAbs(imb(ap))}`)
    assert.ok(almost(ap.bs[0].openCLRemaining, 4_000_000))
    assert.ok(almost(ap.cf[1].openingPayablesPaid, 2_000_000))
    assert.ok(almost(ap.cf[3].openingPayablesPaid, 2_000_000))
    assert.ok(almost(apCash[1], -2e6) && almost(apCash[3], -4e6), `cash ${apCash.slice(0, 4)}`)
  })

  it('B02/B04 T03–T04 opening receivables appear and cash rises on scheduled months', () => {
    const base = run({})
    const ar = run({ obOpts: { ar: 6_000_000 } })
    const arCash = ar.cf.map((c, i) => c.cumulativeCash - base.cf[i].cumulativeCash)
    assert.ok(maxAbs(imb(ar)) < 1, `imbalance ${maxAbs(imb(ar))}`)
    assert.ok(almost(ar.bs[0].otherCA, 6_000_000))
    assert.ok(almost(ar.cf[1].openingReceipts, 3_000_000))
    assert.ok(almost(ar.cf[2].openingReceipts, 3_000_000))
    assert.ok(almost(arCash[1], 3e6) && almost(arCash[2], 6e6), `cash ${arCash.slice(0, 4)}`)
  })

  it('B03 T05 schedule is read by month label, not array position', () => {
    const one = run({
      ob: Object.assign(openingBalance({}), {
        current_liabilities: [{ label: 'x', amount: 1000, schedule: [{ month: 'Dec 2026', amount: 1000 }] }],
      }),
    })
    const carried = one.bs.slice(0, 5).map(b => Math.round(b.openCLRemaining || 0))
    assert.deepEqual(carried, [1000, 1000, 1000, 0, 0])
  })

  it('B03 old sections shape still settles by month label', () => {
    const timeline = Array.from({ length: 6 }, (_, i) => {
      const d = new Date(2026, 8 + i, 1)
      return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    })
    const flows = buildOpeningFlows({
      sections: {
        current_liabilities: [{ amount: 1000, schedule: [{ month: 'Dec 2026', amount: 1000 }] }],
      },
    }, timeline)
    assert.deepEqual(flows.clBalance.slice(0, 5).map(Math.round), [1000, 1000, 1000, 0, 0])
    assert.equal(Math.round(flows.clPaymentsByMonth[3]), 1000)
  })

  it('B05 T06–T07 existing fixed assets depreciate through P&L and the sheet balances', () => {
    const fa = run({ obOpts: { netFA: 5_000_000 } })
    assert.ok(maxAbs(imb(fa)) < 1, `imbalance ${maxAbs(imb(fa))}`)
    assert.ok(almost(fa.plByYear[0].totalDep, 1_000_000, 1000), `dep ${fa.plByYear[0].totalDep}`)
  })

  it('B06 T08 VAT receivable is a current asset when input VAT exceeds output VAT', () => {
    const nc = run({ ob: null, cogs: { beginning_inventory_units: 0, beginning_inventory_value: 0 } })
    assert.ok(maxAbs(imb(nc)) < 1, `imbalance ${maxAbs(imb(nc))}`)
    assert.ok(nc.bs[0].vatReceivable > 0)
    assert.equal(nc.bs[0].vatPayable, 0)
  })

  it('B06 T09 7-year baseline stays balanced when unit cost outruns price', () => {
    const long = run({ years: 7 })
    assert.ok(maxAbs(imb(long)) < 1, `imbalance ${maxAbs(imb(long))}`)
  })

  it('B07 T10a/T10b admin vs manufacturing depreciation reaches EBIT once', () => {
    const base = run({})
    const faAsset = (a, m) => [{
      total: 6_000_000, depreciation_duration: 5, start_date: '2026-09', end_date: '2026-09',
      payment_term: 'cash', equity_pct: 100, debt_pct: 0, interest_pct: 0, replacement_cost_pct: 0,
      product_allocation: [], admin_dep_pct: a, mfg_dep_pct: m,
    }]
    for (const [a, m] of [[50, 50], [100, 0]]) {
      const r = run({ fa: faAsset(a, m) })
      const drop = base.plByYear[1].ebit - r.plByYear[1].ebit
      const shown = r.plByYear[1].mfgDep + r.plByYear[1].adminDep
      assert.ok(almost(drop, 1_200_000, 1000), `EBIT drop ${drop} for ${a}/${m}`)
      assert.ok(almost(shown, 1_200_000, 1000), `shown dep ${shown} for ${a}/${m}`)
      assert.ok(maxAbs(imb(r)) < 1, `imbalance ${maxAbs(imb(r))}`)
    }
  })

  it('B08 T11 expense paid 60 days later creates accrued expense', () => {
    const ex1 = run({
      expenses: [{
        expense_name: 'Rent', category: 'general_admin', expense_type: 'fixed_recurring',
        amount: 500000, annual_increase_pct: 0,
        payment_policy: { preset: 'custom', tranches: [{ pct: 100, days: 60 }] },
      }],
    })
    assert.ok(maxAbs(imb(ex1)) < 1, `imbalance ${maxAbs(imb(ex1))}`)
    assert.ok(almost(ex1.bs[0].accruedExp, 500000))
    assert.ok(almost(ex1.bs[1].accruedExp, 1_000_000))
  })

  it('B08 T12 prepaid one-time expense is a current asset', () => {
    const ex2 = run({
      expenses: [{
        expense_name: 'Licence', category: 'general_admin', expense_type: 'one_time',
        amount: 1_200_000, amortization_months: 12, start_date: '2026-09', payment_policy: cash0,
      }],
    })
    assert.ok(maxAbs(imb(ex2)) < 1, `imbalance ${maxAbs(imb(ex2))}`)
    assert.ok(almost(ex2.bs[0].prepaidExp, 1_100_000))
  })

  it('B10 T13 capitalised interest is added to the asset and the loan', () => {
    const loanAsset = e => [Object.assign({
      total: 10_000_000, depreciation_duration: 5, start_date: '2026-09', end_date: '2027-02',
      payment_term: 'cash', equity_pct: 100, debt_pct: 0, interest_pct: 0, grace_months: 0,
      tenor_months: 36, admin_dep_pct: 0, mfg_dep_pct: 100, replacement_cost_pct: 0, product_allocation: [],
    }, e)]
    const eq = run({ years: 7, fa: loanAsset({}) })
    const ln = run({ years: 7, fa: loanAsset({ equity_pct: 0, debt_pct: 100, interest_pct: 20 }) })
    const diff = imb(ln)[83] - imb(eq)[83]
    assert.ok(Math.abs(diff) < 1, `imbalance difference ${diff}`)
    assert.ok(ln.bs[83].accumDep <= ln.bs[83].grossFA + 1)
    assert.ok(ln.bs[5].grossFA >= 10_999_000, `gross FA after PUP ${ln.bs[5].grossFA}`)
  })

  it('B11 T14 opening stock value 0 auto-calculates as units × unit cost', () => {
    const v0 = run({ cogs: { beginning_inventory_value: 0 } })
    const vAuto = run({ cogs: { beginning_inventory_value: UNITS * 11000 }, obOpts: { invAmt: UNITS * 11000 } })
    assert.ok(almost(v0.pl[0].cogs, vAuto.pl[0].cogs))
    assert.ok(almost(v0.pl[0].cogs, 24_200_000, 1))
  })

  it('B12 T15 MOIC counts the terminal value once', () => {
    const k = run({}).kpis
    const totalR = k.fcff.reduce((s, f) => s + Math.max(0, f), 0) + Math.max(0, k.terminalValue)
    const moicOk = totalR / k.totalInvestment
    assert.ok(Math.abs(k.moic - moicOk) < 0.01, `reported ${k.moic} vs ${moicOk}`)
  })

  it('B13 T16 tax-loss carry-forward reduces later years', () => {
    const loss = run({
      expenses: [{
        expense_name: 'Start-up costs', category: 'general_admin', expense_type: 'fixed_recurring',
        amount: 12_000_000, annual_increase_pct: 0, start_date: '2026-09', end_date: '2027-08',
        payment_policy: cash0,
      }],
    })
    const byYear = {}
    loss.pl.forEach((p, i) => {
      const yr = loss.timeline[i].slice(0, 4)
      byYear[yr] = (byYear[yr] || 0) + p.ebt
    })
    let pool = 0, expectTax = 0
    for (const yr of Object.keys(byYear).sort()) {
      const e = byYear[yr]
      if (e < 0) pool += -e
      else {
        const use = Math.min(pool, e)
        pool -= use
        expectTax += (e - use) * 0.225
      }
    }
    const bookedTax = loss.pl.reduce((s, p) => s + p.tax, 0)
    assert.ok(almost(bookedTax, expectTax, 1000), `booked ${bookedTax} expected ${expectTax}`)
  })

  it('B15 T17 corporate tax is booked at calendar December (and last month)', () => {
    const base = run({})
    const taxMonths = base.pl.map((p, i) => p.tax > 0 ? base.timeline[i] : null).filter(Boolean)
    assert.ok(
      taxMonths.every(t => t.endsWith('-12') || t === base.timeline[base.timeline.length - 1]),
      `tax booked in ${taxMonths.join(', ')}`,
    )
  })

  it('B09 T18a/T18b legal reserve is 5% of the calendar year profit in December', () => {
    const lr0 = run({ tax: 0 })
    assert.ok(maxAbs(imb(lr0)) < 1, `imbalance ${maxAbs(imb(lr0))}`)
    const lr = run({})
    const dec2027 = lr.bs[15]
    const np2027 = lr.pl.slice(4, 16).reduce((s, p) => s + p.netProfit, 0)
    assert.ok(
      almost((dec2027.legalReserveAccum - lr.bs[3].legalReserveAccum), 0.05 * np2027, 1000),
      `moved ${dec2027.legalReserveAccum - lr.bs[3].legalReserveAccum} vs 5% ${0.05 * np2027}`,
    )
  })

  it('B14 warns when opening-balance inventory disagrees with COGS', () => {
    const r = run({
      obOpts: { invAmt: 1 },
    })
    assert.ok((r.warnings || []).some(w => /Opening inventory/.test(w)))
  })

  it('B16 FCFF deducts the yearly change in working capital', () => {
    const r = run({})
    const nwcOf = b => (b.ar || 0) + (b.inventory || 0) + (b.vatReceivable || 0) + (b.prepaidExp || 0) + (b.otherCA || 0)
      - (b.ap || 0) - (b.accruedExp || 0) - (b.openCLRemaining || 0)
    const openingNwc = INV
    const endY1 = nwcOf(r.bsByYear[0])
    const taxRate = 0.225
    const expected = r.plByYear[0].ebit * (1 - taxRate) + r.plByYear[0].totalDep
      - (r.cfByYear[0]?.capexPaid || 0) - (endY1 - openingNwc)
    assert.ok(almost(r.kpis.fcff[0], expected, 1), `fcff ${r.kpis.fcff[0]} expected ${expected}`)
  })

  it('B17 finance-category expenses sit below EBIT, not in opex', () => {
    const base = run({})
    const fin = run({
      expenses: [{
        expense_name: 'Bank charges', category: 'finance', expense_type: 'fixed_recurring',
        amount: 100000, annual_increase_pct: 0, payment_policy: cash0,
      }],
    })
    assert.ok(almost(fin.pl[0].ebitda, base.pl[0].ebitda, 1), 'EBITDA should be unchanged')
    assert.ok(almost(fin.pl[0].ebit, base.pl[0].ebit, 1), 'EBIT should be unchanged')
    assert.ok(almost(fin.pl[0].finCost, 100000))
    assert.ok(almost(fin.pl[0].opexCost, 0))
  })

  it('B18 calcCOGS no longer prints the full dataset', () => {
    const logs = []
    const orig = console.log
    console.log = (...args) => { logs.push(args) }
    try { run({}) } finally { console.log = orig }
    assert.equal(logs.length, 0, `unexpected console.log ${JSON.stringify(logs.slice(0, 2))}`)
  })
})
