<template>
  <Head :title="dashboard.name" />
  <div class="cd-report">
    <div class="topbar">
      <div class="brand">◆ {{ companyName }} · Shared Analysis</div>
      <div style="color:#93A9CB; font-size:12px;">Read-only · numbers reflect live data</div>
    </div>

    <div class="hero">
      <div class="hero-inner">
        <div class="eyebrow">Comparison Dashboard</div>
        <h1>{{ dashboard.name }}</h1>
        <p class="sub">{{ periodsLabel }}</p>
        <div class="kpi-row" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
          <div class="kpi-card" v-for="(hp, i) in heroPairs" :key="i">
            <div class="label">Net Sales · {{ hp.label_a }} → {{ hp.label_b }}</div>
            <div class="value tabular" :class="hp.raw_pct >= 0 ? 'green' : 'red'">
              {{ hp.raw_pct >= 0 ? '+' : '' }}{{ hp.raw_pct }}%
            </div>
            <div class="detail">{{ fmt(hp.net_sales_a) }} → {{ fmt(hp.net_sales_b) }}</div>
            <div v-if="hp.was_aligned" style="margin-top:10px; font-size:11px; color:#93C5FD; background:rgba(37,99,235,.15); border:1px solid rgba(37,99,235,.35); border-radius:6px; padding:6px 8px;">
              ℹ Periods were different lengths, so {{ hp.label_a }} uses the same calendar months as {{ hp.label_b }} for a fair comparison.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Executive Summary -->
    <div class="section" style="padding-bottom:0;" v-if="notes.hero_summary">
      <div class="card">
        <h3 style="font-size:16px; margin-bottom:10px;">🧭 Executive Summary</h3>
        <div class="note" style="margin-top:0;">{{ notes.hero_summary.note }}</div>
      </div>
    </div>

    <div class="section" style="padding-bottom:0;" v-if="takeaways.length">
      <div class="card">
        <h3 style="font-size:16px; margin-bottom:18px;">📌 Key Takeaways — At a Glance</h3>
        <div class="takeaway-grid">
          <div class="takeaway-item" v-for="(t, i) in takeaways" :key="t.key" :class="t.tone">
            <template v-if="notes[t.key] && notes[t.key].is_auto === false">
              <span class="tk-text" style="white-space:pre-wrap;">{{ notes[t.key].note }}</span>
            </template>
            <template v-else>
              <span class="tk-stat">{{ t.stat }}</span>
              <span class="tk-text">{{ t.text }}</span>
            </template>
          </div>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section-head"><span class="section-tag">01 · ZOOM OUT</span></div>
      <h2>Overall Performance</h2>
      <div class="grid-2">
        <div class="card">
          <h3>Net Sales Trend by Period</h3>
          <div class="chart-wrap tall"><canvas ref="zoomOutChart"></canvas></div>
        </div>
        <div class="card">
          <h3>Quick Overview</h3>
          <table>
            <thead>
              <tr>
                <th>Metric</th>
                <th class="num" v-for="(r,i) in zoomOut" :key="i">{{ r.label }} ({{ monthsLabel(r.days) }})</th>
              </tr>
            </thead>
            <tbody>
              <tr><td>Net Sales</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.net_sales) }}</td></tr>
              <tr><td>Daily Average</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.daily_avg) }}/day</td></tr>
              <tr><td>Transactions</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ r.transactions.toLocaleString() }}</td></tr>
              <tr><td>Customers</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ r.customers.toLocaleString() }}</td></tr>
              <tr><td>Avg Price / Unit</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.avg_price_per_unit) }}</td></tr>
              <tr><td>Avg Value / Transaction</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.avg_value_per_transaction) }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-if="notes.zoom_out" class="note" style="margin-top:18px;">{{ notes.zoom_out.note }}</div>
    </div>

    <div class="section" v-for="pair in zoomIn" :key="pair.section_key">
      <div class="section-head"><span class="section-tag">02 · ZOOM IN</span></div>
      <h2>{{ pair.period_a.label }} → {{ pair.period_b.label }}</h2>

      <h3 style="font-family:var(--display); font-size:16px; color:var(--navy); margin:8px 0 12px;">Customer Nature Analysis</h3>
      <div class="grid-2">
        <div>
          <p style="font-size:12px; color:var(--muted); margin-bottom:8px; font-weight:600;">{{ pair.period_a.label }} ({{ pair.customer_nature_a.year }})</p>
          <CustomerNatureCards :nature="pair.customer_nature_a" />
        </div>
        <div>
          <p style="font-size:12px; color:var(--muted); margin-bottom:8px; font-weight:600;">{{ pair.period_b.label }} ({{ pair.customer_nature_b.year }})</p>
          <CustomerNatureCards :nature="pair.customer_nature_b" />
        </div>
      </div>

      <div class="grid-2" style="margin-top:20px;">
        <div class="card">
          <h3>Biggest Category Movements</h3>
          <table>
            <thead>
              <tr>
                <th>Category</th>
                <th class="num">{{ pair.compare_period_a.label }}</th>
                <th class="num">{{ pair.compare_period_b.label }}</th>
                <th class="num">Change</th>
                <th class="num">Change %</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(c,i) in pair.category_breakdown.slice(0,8)" :key="i">
                <td>{{ c.label }}</td>
                <td class="num val-cell">{{ fmt(c.value_a) }}</td>
                <td class="num val-cell">{{ fmt(c.value_b) }}</td>
                <td class="num"><span class="badge" :class="c.change>=0?'up':'down'">{{ c.change>=0?'▲':'▼' }} {{ fmt(Math.abs(c.change)) }}</span></td>
                <td class="num"><span class="badge" :class="c.change>=0?'up':'down'">{{ c.change_pct !== null ? (c.change_pct>=0?'+':'') + c.change_pct + '%' : 'N/A' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card">
          <h3>Biggest Sales-Person Movements</h3>
          <table>
            <thead>
              <tr>
                <th>Sales Person</th>
                <th class="num">{{ pair.compare_period_a.label }}</th>
                <th class="num">{{ pair.compare_period_b.label }}</th>
                <th class="num">Change</th>
                <th class="num">Change %</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s,i) in pair.salesperson_breakdown.slice(0,8)" :key="i">
                <td>{{ s.label }}</td>
                <td class="num val-cell">{{ fmt(s.value_a) }}</td>
                <td class="num val-cell">{{ fmt(s.value_b) }}</td>
                <td class="num"><span class="badge" :class="s.change>=0?'up':'down'">{{ s.change>=0?'▲':'▼' }} {{ fmt(Math.abs(s.change)) }}</span></td>
                <td class="num"><span class="badge" :class="s.change>=0?'up':'down'">{{ s.change_pct !== null ? (s.change_pct>=0?'+':'') + s.change_pct + '%' : 'N/A' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-if="notes[pair.section_key]" class="note" style="margin-top:18px;">{{ notes[pair.section_key].note }}</div>
    </div>

    <div class="section" v-for="pair in vanishing" :key="pair.section_key">
      <div class="section-head"><span class="section-tag">03 · SUSTAINABILITY RISK</span></div>
      <h2>{{ pair.period_a.label }} → {{ pair.period_b.label }}: Products & Customers That Vanished</h2>
      <p class="lede">Made up at least {{ pair.threshold_pct }}% of net sales in {{ pair.compare_period_a.label }} (≈ {{ fmtM(pair.threshold_value) }}), collapsing to under 5% of that value by {{ pair.compare_period_b.label }}.</p>

      <h3 style="font-family:var(--display); font-size:16px; color:var(--navy); margin:20px 0 12px;">Products</h3>
      <div class="summary-banner">
        <div><div class="num">{{ pair.products_count }}</div><div class="lbl">Products affected</div></div>
        <div><div class="num">{{ fmtM(pair.products_total) }}</div><div class="lbl">Revenue not repeated</div></div>
        <div><div class="num">{{ pair.products_cutoff }}</div><div class="lbl">Products = ~85% of that revenue</div></div>
      </div>
      <table class="vanish-table" v-if="pair.products.length">
        <thead><tr><th>#</th><th>Name</th><th>Trend</th><th>Value</th><th>Period</th></tr></thead>
        <tbody>
          <tr v-for="(p,i) in visibleRows(pair.products, pair.section_key + '_products', pair.products_cutoff)" :key="i">
            <td class="tabular">{{ i+1 }}</td>
            <td class="name-cell">{{ p.name }}</td>
            <td v-html="sparkline(p.value_a, p.value_b)"></td>
            <td class="val-cell"><span class="from">{{ fmtM(p.value_a) }}</span><span class="arrow">→</span><span class="to">{{ fmtM(p.value_b) }}</span></td>
            <td class="period-cell">{{ pair.compare_period_a.label }} → {{ pair.compare_period_b.label }}</td>
          </tr>
        </tbody>
      </table>
      <button v-if="pair.products.length > pair.products_cutoff" @click="toggleExpand(pair.section_key + '_products')"
        style="margin-top:10px; font-size:12px; color:#2563EB; background:none; border:none; cursor:pointer;">
        {{ expanded[pair.section_key + '_products'] ? 'Show Less' : `Show ${pair.products.length - pair.products_cutoff} More (${pair.products.length} total)` }}
      </button>

      <h3 style="font-family:var(--display); font-size:16px; color:var(--navy); margin:28px 0 12px;">Customers</h3>
      <div class="summary-banner">
        <div><div class="num">{{ pair.customers_count }}</div><div class="lbl">Accounts affected</div></div>
        <div><div class="num">{{ fmtM(pair.customers_total) }}</div><div class="lbl">Revenue not repeated</div></div>
        <div><div class="num">{{ pair.customers_cutoff }}</div><div class="lbl">Accounts = ~85% of that revenue</div></div>
      </div>
      <table class="vanish-table" v-if="pair.customers.length">
        <thead><tr><th>#</th><th>Name</th><th>Trend</th><th>Value</th><th>Period</th></tr></thead>
        <tbody>
          <tr v-for="(c,i) in visibleRows(pair.customers, pair.section_key + '_customers', pair.customers_cutoff)" :key="i">
            <td class="tabular">{{ i+1 }}</td>
            <td class="name-cell">{{ c.name }}</td>
            <td v-html="sparkline(c.value_a, c.value_b)"></td>
            <td class="val-cell"><span class="from">{{ fmtM(c.value_a) }}</span><span class="arrow">→</span><span class="to">{{ fmtM(c.value_b) }}</span></td>
            <td class="period-cell">{{ pair.compare_period_a.label }} → {{ pair.compare_period_b.label }}</td>
          </tr>
        </tbody>
      </table>
      <button v-if="pair.customers.length > pair.customers_cutoff" @click="toggleExpand(pair.section_key + '_customers')"
        style="margin-top:10px; font-size:12px; color:#2563EB; background:none; border:none; cursor:pointer;">
        {{ expanded[pair.section_key + '_customers'] ? 'Show Less' : `Show ${pair.customers.length - pair.customers_cutoff} More (${pair.customers.length} total)` }}
      </button>
      <div v-if="notes[pair.section_key]" class="note" style="margin-top:18px;">{{ notes[pair.section_key].note }}</div>
    </div>

    <div class="section">
      <div class="section-head"><span class="section-tag">04 · RANK MOVEMENT</span></div>
      <h2>Top 100 Customers — Rank Movement</h2>
      <div class="leaderboard" ref="lbCustomers"></div>
    </div>
    <div class="section">
      <h2>Top 100 Products — Rank Movement</h2>
      <div class="leaderboard" ref="lbProducts"></div>
      <div v-if="notes.top_customers_products" class="note" style="margin-top:18px;">{{ notes.top_customers_products.note }}</div>
    </div>

    <div class="section" v-if="productConcentration.length">
      <div class="section-head"><span class="section-tag">05 · CONCENTRATION</span></div>
      <h2>Product Concentration by Category</h2>
      <div v-for="pc in productConcentration" :key="pc.period.label" style="margin-bottom:28px;">
        <h3 style="font-family:var(--display); font-size:15px; color:var(--navy); margin-bottom:12px;">{{ pc.period.label }}</h3>
        <div class="card" style="padding:0;">
          <table>
            <thead>
              <tr>
                <th>Category</th>
                <th class="num">Total Products</th>
                <th class="num">Core (~85% value)</th>
                <th class="num">Core Customers</th>
                <th class="num">Long Tail (~15% value)</th>
                <th class="num">Tail Customers</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cat in pc.categories" :key="cat.category">
                <td>{{ cat.category }}</td>
                <td class="num val-cell">{{ cat.total_products }}</td>
                <td class="num val-cell">{{ cat.core_count }} <span style="color:var(--muted); font-size:11px;">({{ cat.core_pct }}%)</span></td>
                <td class="num val-cell">{{ cat.core_customers }}</td>
                <td class="num val-cell">{{ cat.tail_count }} <span style="color:var(--muted); font-size:11px;">({{ cat.tail_pct }}%)</span></td>
                <td class="num val-cell">{{ cat.tail_customers }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-if="notes.product_concentration" class="note" style="margin-top:18px;">{{ notes.product_concentration.note }}</div>
    </div>

    <!-- Business Mix — Branch / Sales Channel / Business Sector / etc.
         Only for dimensions the uploaded sales data actually has. -->
    <div class="section" v-for="(dim, di) in dimensions" :key="dim.field">
      <div class="section-head"><span class="section-tag">{{ sectionTag(6 + di) }} · BUSINESS MIX</span></div>
      <h2>{{ dim.label }} Analysis</h2>
      <p class="lede">How net sales are split across {{ dim.label.toLowerCase() }} in each period, and where the biggest shifts happened between periods.</p>

      <div class="grid-3" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
        <div class="card" v-for="pb in dim.periods" :key="pb.period.label">
          <h3>{{ pb.period.label }}</h3>
          <div v-if="pb.rows.length">
            <div class="wf-item" v-for="row in pb.rows" :key="row.label">
              <div class="wf-label">
                <span class="name" :style="row.is_other ? 'color:var(--muted); font-weight:500;' : ''">{{ row.label }}</span>
                <span class="val">{{ row.pct }}%</span>
              </div>
              <div class="wf-track"><div class="wf-fill" :style="`width:${row.pct}%; background:${row.is_other ? 'var(--border)' : 'var(--blue)'};`"></div></div>
            </div>
            <div style="margin-top:10px; font-size:11px; color:var(--muted);">{{ pb.distinct_count }} distinct {{ dim.label.toLowerCase() }}{{ pb.distinct_count===1?'':'s' }} · {{ fmt(pb.total) }} total</div>
          </div>
          <div v-else style="font-size:12px; color:var(--muted);">No data in this period.</div>
        </div>
      </div>

      <div class="grid-2" style="margin-top:24px;" v-if="dim.movements.length">
        <div class="card" v-for="mv in dim.movements" :key="mv.period_a.label + '__' + mv.period_b.label">
          <h3>{{ mv.period_a.label }} → {{ mv.period_b.label }} Movement</h3>
          <table>
            <thead>
              <tr>
                <th>{{ dim.label }}</th>
                <th class="num">{{ mv.compare_period_a.label }}</th>
                <th class="num">{{ mv.compare_period_b.label }}</th>
                <th class="num">Change</th>
                <th class="num">Change %</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r,i) in mv.rows" :key="i">
                <td>{{ r.label }}</td>
                <td class="num val-cell">{{ fmt(r.value_a) }}</td>
                <td class="num val-cell">{{ fmt(r.value_b) }}</td>
                <td class="num"><span class="badge" :class="r.change>=0?'up':'down'">{{ r.change>=0?'▲':'▼' }} {{ fmt(Math.abs(r.change)) }}</span></td>
                <td class="num"><span class="badge" :class="r.change>=0?'up':'down'">{{ r.change_pct !== null ? (r.change_pct>=0?'+':'') + r.change_pct + '%' : 'N/A' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-if="notes[dim.section_key]" class="note" style="margin-top:18px;">{{ notes[dim.section_key].note }}</div>
    </div>

    <footer>
      <div class="inner">Generated by CFOs Tools · numbers reflect live sales data as of today</div>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, defineComponent, h } from 'vue'
import { Head } from '@inertiajs/vue3'
import '@/styles/comparison-dashboard.css'

const props = defineProps({ companyName: String, dashboard: Object, zoomOut: Array, zoomIn: Array, vanishing: Array, top50: Object, heroPairs: Array, takeaways: Array, productConcentration: Array, dimensions: { type: Array, default: () => [] }, notes: Object })

function fmt(n) { return Math.round(parseFloat(n) || 0).toLocaleString('en-US') }
function fmtM(n) { return ((parseFloat(n) || 0) / 1e6).toFixed(1) + 'M' }
function monthsLabel(days) { return Math.round(days / 30.44) + ' mo' }
function sectionTag(n) { return String(n).padStart(2, '0') }

const periodsLabel = computed(() => props.dashboard.periods.map(p => p.label).join(' · '))

const expanded = ref({})
function toggleExpand(key) { expanded.value[key] = !expanded.value[key] }
function visibleRows(rows, key, cutoff) { return expanded.value[key] ? rows : rows.slice(0, cutoff) }

const NATURE_META = {
  new:              { label: 'New',               color: '#0E7490' },
  repeating:        { label: 'Repeating',          color: '#16A34A' },
  active:           { label: 'Active (3+ yrs)',    color: '#16A34A' },
  stop:             { label: 'Stop',               color: '#D97706' },
  dead:             { label: 'Dead',               color: '#DC2626' },
  stop_reactivated: { label: 'Reactivated',        color: '#A16207' },
  dead_reactivated: { label: 'Dead Reactivated',   color: '#7C3AED' },
}
const CustomerNatureCards = defineComponent({
  props: { nature: Object },
  setup(p) {
    return () => h('div', {}, [
      h('div', { style: 'display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:8px; margin-bottom:10px;' },
        p.nature.categories.map(cat => {
          const meta = NATURE_META[cat.label] || { label: cat.label, color: 'var(--muted)' }
          return h('div', {
            key: cat.label,
            style: `border:1px solid var(--border); border-left:3px solid ${meta.color}; border-radius:8px; padding:10px;`,
          }, [
            h('div', { style: `font-size:10px; text-transform:uppercase; letter-spacing:.04em; font-weight:700; color:${meta.color}; margin-bottom:4px;` }, meta.label),
            h('div', { style: 'font-family:var(--mono); font-size:20px; font-weight:700; color:var(--navy);' }, cat.count.toLocaleString()),
            h('div', { style: 'font-size:10px; color:var(--muted); font-family:var(--mono);' }, fmtM(cat.total_sales)),
          ])
        })
      ),
      h('div', { class: 'stat-chip' }, [h('span', { class: 'l' }, 'Retention Rate'), h('span', { class: 'v tabular' }, p.nature.retention_rate + '%')]),
      h('div', { class: 'stat-chip' }, [h('span', { class: 'l' }, 'Churn (Dead)'), h('span', { class: 'v tabular', style: 'color:var(--red)' }, p.nature.churn_dead)]),
      h('div', { class: 'stat-chip' }, [h('span', { class: 'l' }, 'Reactivated'), h('span', { class: 'v tabular' }, p.nature.reactivated)]),
      h('div', { class: 'stat-chip' }, [h('span', { class: 'l' }, 'New This Year'), h('span', { class: 'v tabular' }, p.nature.new_this_year)]),
    ])
  },
})

function sparkline(vFrom, vTo) {
  const max = Math.max(vFrom, 1)
  const x0 = 4, y0 = 36, x1 = 30, y1 = 34 - (vFrom / max) * 28, x2 = 86, y2 = 36
  const m1 = (x0 + x1) / 2, m2 = (x1 + x2) / 2
  const path = `M${x0},${y0} C${m1},${y0} ${m1},${y1} ${x1},${y1} C${m2},${y1} ${m2},${y2} ${x2},${y2}`
  return `<svg viewBox="0 0 90 40"><path d="${path}" stroke="#D97706" stroke-width="2.5" fill="none" stroke-linecap="round"/>
    <circle cx="${x1}" cy="${y1.toFixed(1)}" r="3" fill="#D97706"/><circle cx="${x2}" cy="${y2}" r="3" fill="#DC2626"/></svg>`
}

const lbCustomers = ref(null)
const lbProducts = ref(null)

function renderLeaderboard(container, columns) {
  const names = columns.map(col => new Set(col.rows.map(r => r.name)))
  const limit = columns[0]?.limit || 100
  container.innerHTML = columns.map((col, ci) => {
    const topNValue = col.rows.reduce((s, r) => s + r.value, 0)
    return `
    <div class="lb-col">
      <div class="lb-col-head" style="display:block;">
        <div style="display:flex; align-items:baseline; justify-content:space-between; gap:10px;">
          <span>${col.label}</span>
          <span class="lb-col-total" style="white-space:nowrap;">${fmtM(topNValue)}</span>
        </div>
        <div style="font-family:var(--mono); font-weight:500; font-size:10.5px; color:#9DB6DE; margin-top:5px; line-height:1.5;" title="Both figures are a share of this period's TOTAL net sales — all customers/products, not just the Top ${limit}">Top ${limit} = ${col.top_n_share}% of total sales<br>Top 10 = ${col.top10_share}% of total sales</div>
      </div>
      <div class="lb-col-body">
        ${col.rows.map((r, i) => {
          const inOtherCount = columns.filter((_, j) => j !== ci && names[j].has(r.name)).length
          const dotColor = inOtherCount === columns.length - 1 ? '#16A34A' : inOtherCount > 0 ? '#D97706' : '#CBD5E1'
          const priorCols = columns.slice(0, ci)
          const prevLine = priorCols.map((pc, pj) => {
            const rk = r[`rank_${pj}`]
            return `${pc.label.slice(0,4)} ${rk != null ? '#'+rk : 'new'}`
          }).join(' · ')
          return `<div class="lb-row">
            <div class="lb-rankcol"><span class="lb-rank">#${i+1}</span>${prevLine ? `<span class="lb-prev">${prevLine}</span>` : ''}</div>
            <span class="lb-persist" style="background:${dotColor}"></span>
            <span class="lb-name" title="${r.name}">${r.name}</span>
            <span class="lb-value">${fmtM(r.value)}</span>
          </div>`
        }).join('')}
      </div>
    </div>`
  }).join('')
}

let Chart = null
async function loadChartJs() {
  if (window.Chart) { Chart = window.Chart; return }
  await new Promise((resolve) => {
    const s = document.createElement('script')
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
    s.onload = () => { Chart = window.Chart; resolve() }
    document.head.appendChild(s)
  })
}

const zoomOutChart = ref(null)
onMounted(async () => {
  await loadChartJs()
  await nextTick()
  new Chart(zoomOutChart.value.getContext('2d'), {
    type: 'line',
    data: {
      labels: props.zoomOut.map(r => r.label),
      datasets: [
        { label: 'Net Sales', data: props.zoomOut.map(r => r.net_sales), borderColor: '#00b4c8', backgroundColor: 'transparent', borderWidth: 3, tension: 0.4, yAxisID: 'y', pointRadius: 4, pointBackgroundColor: '#00b4c8' },
        { label: 'Growth %', data: props.zoomOut.map(r => r.growth_pct), borderColor: '#10b981', backgroundColor: 'transparent', borderWidth: 2, borderDash: [6, 4], tension: 0.4, yAxisID: 'y1', pointRadius: 4, pointBackgroundColor: '#10b981' },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top', labels: { boxWidth: 14 } } },
      scales: {
        y:  { position: 'left',  ticks: { callback: v => fmtM(v) } },
        y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => v + '%' } },
      },
    },
  })
  renderLeaderboard(lbCustomers.value, props.top50.customers)
  renderLeaderboard(lbProducts.value, props.top50.products)
})
</script>
