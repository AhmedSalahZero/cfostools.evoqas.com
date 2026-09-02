<template>
  <Head :title="dashboard.name" />
  <div class="cd-report">
    <div class="topbar">
      <div class="brand">◆ {{ companyName }} · Shared Analysis</div>
      <div style="color:#93A9CB; font-size:12px;">Read-only · numbers reflect live data</div>
    </div>

    <div class="hero">
      <div class="hero-inner">
        <div class="eyebrow">Expense Comparison Dashboard</div>
        <h1>{{ dashboard.name }}</h1>
        <p class="sub">{{ periodsLabel }}</p>
        <div class="kpi-row" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
          <div class="kpi-card" v-for="(hp, i) in heroPairs" :key="i">
            <div class="label">Total Expense · {{ hp.label_a }} → {{ hp.label_b }}</div>
            <div class="value tabular" :class="hp.raw_pct >= 0 ? 'red' : 'green'">
              {{ hp.raw_pct >= 0 ? '+' : '' }}{{ hp.raw_pct }}%
            </div>
            <div class="detail">{{ fmt(hp.expense_a) }} → {{ fmt(hp.expense_b) }}</div>
            <div v-if="hp.ratio_change !== null" style="margin-top:8px; font-size:12px; color:#B9CBEA;">
              Expense/Revenue: {{ hp.ratio_a }}% → {{ hp.ratio_b }}%
              <span :class="hp.ratio_change >= 0 ? 'red' : 'green'" style="font-family:var(--mono); font-weight:600;">
                ({{ hp.ratio_change >= 0 ? '+' : '' }}{{ hp.ratio_change }}pt)
              </span>
            </div>
            <div v-if="hp.was_aligned" style="margin-top:10px; font-size:11px; color:#93C5FD; background:rgba(37,99,235,.15); border:1px solid rgba(37,99,235,.35); border-radius:6px; padding:6px 8px;">
              ℹ Periods were different lengths, so {{ hp.label_a }} uses the same calendar months as {{ hp.label_b }} for a fair comparison.
            </div>
          </div>
        </div>
      </div>
    </div>

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
      <h2>Overall Expense Performance</h2>
      <div class="grid-2">
        <div class="card">
          <h3>Total Expense Trend by Period</h3>
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
              <tr><td>Total Expense</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.total_expense) }}</td></tr>
              <tr><td>Daily Average</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.daily_avg) }}/day</td></tr>
              <tr><td>Categories</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ r.category_count.toLocaleString() }}</td></tr>
              <tr><td>Expense Items</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ r.item_count.toLocaleString() }}</td></tr>
              <tr><td>Avg / Category</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.avg_per_category) }}</td></tr>
              <tr><td>Avg / Item</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.avg_per_item) }}</td></tr>
              <tr><td>Revenue</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ r.revenue > 0 ? fmt(r.revenue) : '—' }}</td></tr>
              <tr><td>Expense / Revenue</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ r.ratio_pct !== null ? r.ratio_pct + '%' : 'N/A' }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-if="notes.zoom_out" class="note" style="margin-top:18px;">{{ notes.zoom_out.note }}</div>
    </div>

    <div class="section" v-for="pair in zoomIn" :key="pair.section_key">
      <div class="section-head"><span class="section-tag">02 · ZOOM IN</span></div>
      <h2>{{ pair.period_a.label }} → {{ pair.period_b.label }}</h2>
      <div class="grid-2">
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
                <td class="num"><span class="badge" :class="c.change>=0?'down':'up'">{{ c.change>=0?'▲':'▼' }} {{ fmt(Math.abs(c.change)) }}</span></td>
                <td class="num"><span class="badge" :class="c.change>=0?'down':'up'">{{ c.change_pct !== null ? (c.change_pct>=0?'+':'') + c.change_pct + '%' : 'N/A' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card">
          <h3>Biggest Expense Item Movements</h3>
          <table>
            <thead>
              <tr>
                <th>Expense Item</th>
                <th class="num">{{ pair.compare_period_a.label }}</th>
                <th class="num">{{ pair.compare_period_b.label }}</th>
                <th class="num">Change</th>
                <th class="num">Change %</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(s,i) in pair.item_breakdown.slice(0,8)" :key="i">
                <td>{{ s.label }}</td>
                <td class="num val-cell">{{ fmt(s.value_a) }}</td>
                <td class="num val-cell">{{ fmt(s.value_b) }}</td>
                <td class="num"><span class="badge" :class="s.change>=0?'down':'up'">{{ s.change>=0?'▲':'▼' }} {{ fmt(Math.abs(s.change)) }}</span></td>
                <td class="num"><span class="badge" :class="s.change>=0?'down':'up'">{{ s.change_pct !== null ? (s.change_pct>=0?'+':'') + s.change_pct + '%' : 'N/A' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-if="notes[pair.section_key]" class="note" style="margin-top:18px;">{{ notes[pair.section_key].note }}</div>
    </div>

    <div class="section" v-for="pair in vanishing" :key="pair.section_key">
      <div class="section-head"><span class="section-tag">03 · COST CHANGES</span></div>
      <h2>{{ pair.period_a.label }} → {{ pair.period_b.label }}: Expenses That Didn't Repeat</h2>
      <p class="lede">Made up at least {{ pair.threshold_pct }}% of total expense in {{ pair.compare_period_a.label }} (≈ {{ fmtM(pair.threshold_value) }}), collapsing to under 5% of that value by {{ pair.compare_period_b.label }}.</p>
      <div class="summary-banner">
        <div><div class="num">{{ pair.items_count }}</div><div class="lbl">Items affected</div></div>
        <div><div class="num">{{ fmtM(pair.items_total) }}</div><div class="lbl">Expense not repeated</div></div>
        <div><div class="num">{{ pair.items_cutoff }}</div><div class="lbl">Items = ~85% of that value</div></div>
      </div>
      <table class="vanish-table" v-if="pair.items.length">
        <thead><tr><th>#</th><th>Name</th><th>Trend</th><th>Value</th><th>Period</th></tr></thead>
        <tbody>
          <tr v-for="(p,i) in pair.items.slice(0, pair.items_cutoff)" :key="i">
            <td class="tabular">{{ i+1 }}</td>
            <td class="name-cell">{{ p.name }}</td>
            <td v-html="sparkline(p.value_a, p.value_b)"></td>
            <td class="val-cell"><span class="from">{{ fmtM(p.value_a) }}</span><span class="arrow">→</span><span class="to">{{ fmtM(p.value_b) }}</span></td>
            <td class="period-cell">{{ pair.compare_period_a.label }} → {{ pair.compare_period_b.label }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="notes[pair.section_key]" class="note" style="margin-top:18px;">{{ notes[pair.section_key].note }}</div>
    </div>

    <div class="section">
      <div class="section-head"><span class="section-tag">04 · RANK MOVEMENT</span></div>
      <h2>Top 100 Expense Items — Rank Movement</h2>
      <div class="leaderboard" ref="lbItems"></div>
      <div v-if="notes.top_expense_items" class="note" style="margin-top:18px;">{{ notes.top_expense_items.note }}</div>
    </div>

    <div class="section" v-if="concentration.length">
      <div class="section-head"><span class="section-tag">05 · CONCENTRATION</span></div>
      <h2>Expense Concentration by Category</h2>
      <div v-for="pc in concentration" :key="pc.period.label" style="margin-bottom:28px;">
        <h3 style="font-family:var(--display); font-size:15px; color:var(--navy); margin-bottom:12px;">{{ pc.period.label }}</h3>
        <div class="card" style="padding:0;">
          <table>
            <thead>
              <tr><th>Category</th><th class="num">Total Items</th><th class="num">Core (~85% value)</th><th class="num">Long Tail (~15% value)</th></tr>
            </thead>
            <tbody>
              <tr v-for="cat in pc.categories" :key="cat.category">
                <td>{{ cat.category }}</td>
                <td class="num val-cell">{{ cat.total_items }}</td>
                <td class="num val-cell">{{ cat.core_count }} <span style="color:var(--muted); font-size:11px;">({{ cat.core_pct }}%)</span></td>
                <td class="num val-cell">{{ cat.tail_count }} <span style="color:var(--muted); font-size:11px;">({{ cat.tail_pct }}%)</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-if="notes.expense_concentration" class="note" style="margin-top:18px;">{{ notes.expense_concentration.note }}</div>
    </div>

    <div class="section" v-if="fixedVariable.length">
      <div class="section-head"><span class="section-tag">06 · COST STRUCTURE</span></div>
      <h2>Fixed vs Variable Costs</h2>
      <div class="grid-3" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
        <div class="card" v-for="fv in fixedVariable" :key="fv.period.label">
          <h3>{{ fv.period.label }}</h3>
          <div v-if="!fv.has_revenue" style="font-size:12px; color:var(--amber-dark); background:var(--amber-light); border-radius:8px; padding:10px 12px; margin-bottom:12px;">
            No sales data for this period — everything defaults to Fixed.
          </div>
          <div class="wf-item">
            <div class="wf-label"><span class="name">Fixed</span><span class="val">{{ fmt(fv.fixed_total) }} ({{ fv.fixed_pct }}%)</span></div>
            <div class="wf-track"><div class="wf-fill" :style="`width:${fv.fixed_pct}%; background:var(--blue);`"></div></div>
          </div>
          <div class="wf-item">
            <div class="wf-label"><span class="name">Variable</span><span class="val">{{ fmt(fv.variable_total) }} ({{ fv.variable_pct }}%)</span></div>
            <div class="wf-track"><div class="wf-fill" :style="`width:${fv.variable_pct}%; background:var(--amber);`"></div></div>
          </div>
        </div>
      </div>
      <div v-if="notes.fixed_variable" class="note" style="margin-top:18px;">{{ notes.fixed_variable.note }}</div>
    </div>

    <div class="section" v-if="volatility.length">
      <div class="section-head"><span class="section-tag">07 · VOLATILITY</span></div>
      <h2>Volatility & Outliers</h2>
      <div v-for="v in volatility" :key="v.period.label" style="margin-bottom:28px;">
        <h3 style="font-family:var(--display); font-size:15px; color:var(--navy); margin-bottom:12px;">{{ v.period.label }} — {{ v.items_with_outliers }} item(s) with outliers</h3>
        <div class="card" style="padding:0;" v-if="v.items.length">
          <table>
            <thead><tr><th>Category</th><th>Item</th><th class="num">Avg</th><th class="num">Min</th><th class="num">Max</th><th class="num">Outliers</th></tr></thead>
            <tbody>
              <tr v-for="it in v.items" :key="it.category + it.item">
                <td style="font-size:12px; color:var(--muted);">{{ it.category }}</td>
                <td>{{ it.item }}</td>
                <td class="num val-cell">{{ fmt(it.avg) }}</td>
                <td class="num val-cell" style="color:var(--green-dark);">{{ fmt(it.min) }}</td>
                <td class="num val-cell" style="color:var(--red);">{{ fmt(it.max) }}</td>
                <td class="num">
                  <span style="display:inline-block; white-space:nowrap; font-size:11px; background:rgba(217,119,6,.12); color:var(--amber-dark); border:1px solid rgba(217,119,6,.4); padding:2px 8px; border-radius:20px; font-weight:600;">
                    {{ it.outlier_count }} outlier{{ it.outlier_count > 1 ? 's' : '' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-if="notes.volatility" class="note" style="margin-top:18px;">{{ notes.volatility.note }}</div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { Head } from '@inertiajs/vue3'
import '@/styles/comparison-dashboard.css'

const props = defineProps({ companyName: String, dashboard: Object, zoomOut: Array, zoomIn: Array, vanishing: Array, top100: Object, heroPairs: Array, takeaways: Array, concentration: Array, fixedVariable: Array, volatility: Array, notes: Object })

function fmt(n) { return Math.round(parseFloat(n) || 0).toLocaleString('en-US') }
function fmtM(n) { return ((parseFloat(n) || 0) / 1e6).toFixed(1) + 'M' }
function monthsLabel(days) { return Math.round(days / 30.44) + ' mo' }

const periodsLabel = computed(() => props.dashboard.periods.map(p => p.label).join(' · '))

function sparkline(vFrom, vTo) {
  const max = Math.max(vFrom, 1)
  const x0 = 4, y0 = 36, x1 = 30, y1 = 34 - (vFrom / max) * 28, x2 = 86, y2 = 36
  const m1 = (x0 + x1) / 2, m2 = (x1 + x2) / 2
  const path = `M${x0},${y0} C${m1},${y0} ${m1},${y1} ${x1},${y1} C${m2},${y1} ${m2},${y2} ${x2},${y2}`
  return `<svg viewBox="0 0 90 40"><path d="${path}" stroke="#D97706" stroke-width="2.5" fill="none" stroke-linecap="round"/>
    <circle cx="${x1}" cy="${y1.toFixed(1)}" r="3" fill="#D97706"/><circle cx="${x2}" cy="${y2}" r="3" fill="#DC2626"/></svg>`
}

const lbItems = ref(null)
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
        <div style="font-family:var(--mono); font-weight:500; font-size:10.5px; color:#9DB6DE; margin-top:5px; line-height:1.5;">Top ${limit} = ${col.top_n_share}% of total expense<br>Top 10 = ${col.top10_share}% of total expense</div>
      </div>
      <div class="lb-col-body">
        ${col.rows.map((r, i) => {
          const inOtherCount = columns.filter((_, j) => j !== ci && names[j].has(r.name)).length
          const dotColor = inOtherCount === columns.length - 1 ? '#16A34A' : inOtherCount > 0 ? '#D97706' : '#CBD5E1'
          const priorCols = columns.slice(0, ci)
          const prevLine = priorCols.map((pc, pj) => {
            const rk = r[`rank_${pj}`]
            const periodsBack = ci - pj
            return `Yr${periodsBack} ${rk != null ? '#'+rk : 'new'}`
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
        { label: 'Total Expense', data: props.zoomOut.map(r => r.total_expense), borderColor: '#D97706', backgroundColor: 'transparent', borderWidth: 3, tension: 0.4, yAxisID: 'y', pointRadius: 4, pointBackgroundColor: '#D97706' },
        { label: 'Growth %', data: props.zoomOut.map(r => r.growth_pct), borderColor: '#DC2626', backgroundColor: 'transparent', borderWidth: 2, borderDash: [6, 4], tension: 0.4, yAxisID: 'y1', pointRadius: 4, pointBackgroundColor: '#DC2626' },
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
  renderLeaderboard(lbItems.value, props.top100.items)
})
</script>
