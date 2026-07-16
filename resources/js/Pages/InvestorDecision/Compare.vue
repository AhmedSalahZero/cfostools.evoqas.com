<template>
  <Head title="Compare Prospects" />
  <AuthenticatedLayout>
  <div class="min-h-screen bg-mp-page text-white">

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="border-b border-mp-border bg-mp-card sticky top-0 z-30">
      <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <a href="/investor-decision" class="text-white hover:text-white text-sm transition-colors">← Tool Home</a>
          <span class="text-white">|</span>
          <span class="text-white font-semibold">Head-to-Head Comparison</span>
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-8">

      <!-- ── Prospect Selectors ──────────────────────────────────────────── -->
      <div class="grid grid-cols-2 gap-6 mb-8">
        <div class="bg-mp-card border border-mp-gold/50 rounded-2xl p-5">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Prospect A</p>
          <select v-model="selectedA" @change="reload"
            class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-sm text-white focus:border-mp-gold focus:outline-none">
            <option value="">— Select Prospect A —</option>
            <option v-for="p in prospects" :key="p.id" :value="p.id" :disabled="p.id == selectedB">{{ p.name }}</option>
          </select>
          <div v-if="companyA" class="mt-3 flex items-center gap-2">
            <div class="w-6 h-6 rounded bg-mp-page flex items-center justify-center overflow-hidden">
              <img v-if="companyA.logo" :src="`/storage/${companyA.logo}`" class="w-full h-full object-contain"/>
              <span v-else class="text-xs text-white font-bold">{{ companyA.name.charAt(0) }}</span>
            </div>
            <span class="text-sm font-semibold text-white">{{ companyA.name }}</span>
            <span class="text-xs text-white">{{ companyA.sector }}</span>
          </div>
        </div>
        <div class="bg-mp-card border border-mp-teal/50 rounded-2xl p-5">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Prospect B</p>
          <select v-model="selectedB" @change="reload"
            class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none">
            <option value="">— Select Prospect B —</option>
            <option v-for="p in prospects" :key="p.id" :value="p.id" :disabled="p.id == selectedA">{{ p.name }}</option>
          </select>
          <div v-if="companyB" class="mt-3 flex items-center gap-2">
            <div class="w-6 h-6 rounded bg-mp-page flex items-center justify-center overflow-hidden">
              <img v-if="companyB.logo" :src="`/storage/${companyB.logo}`" class="w-full h-full object-contain"/>
              <span v-else class="text-xs text-white font-bold">{{ companyB.name.charAt(0) }}</span>
            </div>
            <span class="text-sm font-semibold text-white">{{ companyB.name }}</span>
            <span class="text-xs text-white">{{ companyB.sector }}</span>
          </div>
        </div>
      </div>

      <!-- ── Empty state ──────────────────────────────────────────────────── -->
      <div v-if="!companyA || !companyB" class="text-center py-20 text-white">
        <div class="text-5xl mb-4">⚡</div>
        <p class="text-lg font-semibold text-white mb-2">Select two prospects to compare</p>
        <p class="text-sm">Choose Prospect A and Prospect B above to start the comparison.</p>
      </div>

      <template v-else>

        <!-- ── Winner Banner ────────────────────────────────────────────────── -->
        <div v-if="winner" class="mb-8 rounded-2xl p-6 border flex items-center gap-4"
          :class="winner === 'A' ? 'bg-mp-gold/20 border-mp-gold/50' : 'bg-mp-teal-subtle/20 border-mp-teal/50'">
          <span class="text-4xl">🏆</span>
          <div class="flex-1">
            <p class="text-lg font-bold text-white">
              <span :class="winner === 'A' ? 'text-white' : 'text-white'">
                {{ winner === 'A' ? companyA.name : companyB.name }}
              </span>
              wins on overall scorecard
            </p>
            <p class="text-sm text-white mt-0.5">
              Score: <strong :class="winner === 'A' ? 'text-white' : 'text-white'">{{ Math.round(scoreA) }}/100</strong>
              vs <strong class="text-white">{{ Math.round(scoreB) }}/100</strong>
              — {{ Math.abs(Math.round(scoreA - scoreB)) }} point margin
            </p>
          </div>
          <div class="text-right">
            <a :href="`/investor-decision/${winner === 'A' ? companyA.id : companyB.id}/evaluate`"
              class="inline-block px-4 py-2 bg-white/10 hover:bg-white/20 border border-white/20 text-white text-sm font-medium rounded-lg transition-colors">
              Deep Dive →
            </a>
          </div>
        </div>

        <!-- ── Score Comparison Cards ─────────────────────────────────────── -->
        <div class="grid grid-cols-2 gap-6 mb-8">
          <template v-for="(co, side) in [{company: companyA, score: scoreA, color: 'violet'}, {company: companyB, score: scoreB, color: 'blue'}]" :key="side">
            <div class="bg-mp-card border rounded-2xl p-6"
              :class="side === 0 ? 'border-mp-gold/50' : 'border-mp-teal/50'">
              <div class="flex items-center justify-between mb-5">
                <p class="text-sm font-bold text-white">{{ co.company.name }}</p>
                <span class="text-2xl font-black" :class="side === 0 ? 'text-white' : 'text-white'">
                  {{ Math.round(co.score) }}
                </span>
              </div>
              <!-- Score bars per dimension -->
              <div class="space-y-2.5">
                <div v-for="dim in allDimensions" :key="dim.key">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-white">{{ dim.label }}</span>
                    <span class="text-xs font-semibold" :class="side === 0 ? 'text-white' : 'text-white'">
                      {{ autoScores(co.company)[dim.key] }}/10
                    </span>
                  </div>
                  <div class="h-1.5 bg-mp-card-hover rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500"
                      :class="side === 0 ? 'bg-mp-gold' : 'bg-mp-teal'"
                      :style="{width: autoScores(co.company)[dim.key] * 10 + '%'}"></div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- ── Radar Chart ──────────────────────────────────────────────────── -->
        <div class="bg-mp-card border border-mp-border rounded-2xl p-6 mb-8">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-6 text-center">Score Radar</p>
          <div class="flex justify-center">
            <svg viewBox="-10 -10 220 220" class="w-72 h-72">
              <!-- Grid rings -->
              <template v-for="ring in [2,4,6,8,10]" :key="ring">
                <polygon :points="radarRingPoints(ring)" fill="none" stroke="#112240" stroke-width="1"/>
              </template>
              <!-- Axis lines + labels -->
              <template v-for="(dim, i) in allDimensions" :key="dim.key">
                <line :x1="100" :y1="100" :[`x2`]="radarPoint(i, 10).x" :[`y2`]="radarPoint(i, 10).y"
                  stroke="#1490a833" stroke-width="1"/>
                <text :x="radarLabel(i).x" :y="radarLabel(i).y"
                  fill="#64748b" font-size="7.5" text-anchor="middle" dominant-baseline="middle">
                  {{ dim.shortLabel }}
                </text>
              </template>
              <!-- Company A polygon -->
              <polygon :points="radarPolygonPoints(companyA)" fill="#7c3aed" fill-opacity="0.25"
                stroke="#7c3aed" stroke-width="2" stroke-linejoin="round"/>
              <!-- Company B polygon -->
              <polygon :points="radarPolygonPoints(companyB)" fill="#009eb5" fill-opacity="0.25"
                stroke="#009eb5" stroke-width="2" stroke-linejoin="round"/>
            </svg>
          </div>
          <!-- Legend -->
          <div class="flex justify-center gap-8 mt-2">
            <div class="flex items-center gap-2 text-xs text-white">
              <div class="w-4 h-1 rounded bg-mp-gold"></div>{{ companyA.name }}
            </div>
            <div class="flex items-center gap-2 text-xs text-white">
              <div class="w-4 h-1 rounded bg-mp-teal"></div>{{ companyB.name }}
            </div>
          </div>
        </div>

        <!-- ── Financial Comparison Table ──────────────────────────────────── -->
        <div class="bg-mp-card border border-mp-border rounded-2xl p-6 mb-8">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-5">Financial Metrics Comparison</p>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs text-white pb-3 pr-4 w-1/3">Metric</th>
                  <th class="text-center text-xs font-semibold text-white pb-3 px-4">{{ companyA.name }}</th>
                  <th class="text-center text-xs font-semibold text-white pb-3 px-4">{{ companyB.name }}</th>
                  <th class="text-center text-xs text-white pb-3 pl-4">Edge</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <template v-for="row in comparisonRows" :key="row.label">
                  <tr class="hover:bg-mp-card-hover/30">
                    <td class="py-3 pr-4 text-xs text-white">{{ row.label }}</td>
                    <td class="py-3 px-4 text-center font-semibold"
                      :class="row.winnerA ? 'text-white' : 'text-white'">
                      {{ row.valA }}
                    </td>
                    <td class="py-3 px-4 text-center font-semibold"
                      :class="row.winnerB ? 'text-white' : 'text-white'">
                      {{ row.valB }}
                    </td>
                    <td class="py-3 pl-4 text-center">
                      <span v-if="row.winnerA" class="text-xs px-2 py-0.5 rounded-full bg-mp-gold/40 text-white border border-mp-gold/40">A</span>
                      <span v-else-if="row.winnerB" class="text-xs px-2 py-0.5 rounded-full bg-mp-teal-subtle/40 text-white border border-mp-teal/40">B</span>
                      <span v-else class="text-xs text-white">—</span>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ── Data Availability Summary ───────────────────────────────────── -->
        <div class="grid grid-cols-2 gap-6 mb-8">
          <template v-for="(co, side) in [companyA, companyB]" :key="side">
            <div class="bg-mp-card border rounded-2xl p-5"
              :class="side === 0 ? 'border-mp-gold/40' : 'border-mp-teal/40'">
              <p class="text-xs font-semibold mb-4" :class="side === 0 ? 'text-white' : 'text-white'">
                {{ co.name }} — Data Coverage
              </p>
              <div class="space-y-2">
                <div v-for="ds in dataSources" :key="ds.key" class="flex items-center justify-between">
                  <span class="text-xs text-white">{{ ds.label }}</span>
                  <span class="text-xs font-semibold"
                    :class="co[ds.key]?.has_data ? 'text-mp-success' : 'text-white'">
                    {{ co[ds.key]?.has_data ? '✓ Available' : '— No data' }}
                  </span>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- ── Action Row ───────────────────────────────────────────────────── -->
        <div class="flex gap-4 justify-center">
          <a :href="`/investor-decision/${companyA.id}/evaluate`"
            class="px-6 py-3 bg-mp-gold-dark hover:bg-mp-gold text-white font-semibold rounded-xl transition-colors text-sm">
            Deep Dive: {{ companyA.name }} →
          </a>
          <a :href="`/investor-decision/${companyB.id}/evaluate`"
            class="px-6 py-3 bg-mp-teal hover:bg-mp-teal-dark text-white font-semibold rounded-xl transition-colors text-sm">
            Deep Dive: {{ companyB.name }} →
          </a>
        </div>

      </template>
    </div>
  </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  prospects: { type: Array, default: () => [] },
  companyA:  { type: Object, default: null },
  companyB:  { type: Object, default: null },
})

const selectedA = ref(props.companyA?.id || '')
const selectedB = ref(props.companyB?.id || '')

function reload() {
  if (selectedA.value && selectedB.value) {
    router.visit(`/investor-decision/compare?a=${selectedA.value}&b=${selectedB.value}`)
  }
}

// ── Dimension config ───────────────────────────────────────────────────────
const allDimensions = [
  { key: 'revenue_growth',   label: 'Revenue Growth',    shortLabel: 'Rev Growth', weight: 15 },
  { key: 'profitability',    label: 'Profitability',      shortLabel: 'Margin',    weight: 15 },
  { key: 'financial_health', label: 'Financial Health',   shortLabel: 'Health',    weight: 10 },
  { key: 'sales_momentum',   label: 'Sales Momentum',     shortLabel: 'Sales',     weight: 10 },
  { key: 'kpi_performance',  label: 'KPI Performance',    shortLabel: 'KPIs',      weight: 10 },
  { key: 'team_quality',     label: 'Management Team',    shortLabel: 'Team',      weight: 10 },
  { key: 'market_size',      label: 'Market Size',        shortLabel: 'Market',    weight: 10 },
  { key: 'competitive_moat', label: 'Competitive Moat',   shortLabel: 'Moat',      weight: 10 },
  { key: 'esg_governance',   label: 'ESG & Governance',   shortLabel: 'ESG',       weight: 5  },
  { key: 'exit_potential',   label: 'Exit Potential',     shortLabel: 'Exit',      weight: 5  },
]

function autoScores(co) {
  const f = co?.financials || {}
  const s = co?.salesData  || {}
  const k = co?.kpiData    || {}

  const rg = (() => {
    if (s.has_data && s.revenue_growth !== null) {
      const g = s.revenue_growth
      return g >= 30 ? 9 : g >= 15 ? 7 : g >= 5 ? 5 : 3
    }
    return 5
  })()

  const pr = (() => {
    if (!f.has_data) return 5
    const m = f.net_margin || 0
    return m >= 20 ? 9 : m >= 10 ? 7 : m >= 5 ? 6 : m >= 0 ? 4 : 2
  })()

  const fh = (() => {
    if (!f.has_data) return 5
    const de = f.debt_to_equity || 0
    return de <= 0.3 ? 9 : de <= 0.8 ? 7 : de <= 1.5 ? 5 : 3
  })()

  const sm = (() => {
    if (!s.has_data) return 5
    const g = s.revenue_growth || 0
    return g >= 20 ? 9 : g >= 10 ? 7 : g >= 0 ? 5 : 3
  })()

  const kp = (() => {
    if (!k.has_data) return 5
    return k.health_score >= 80 ? 9 : k.health_score >= 60 ? 7 : k.health_score >= 40 ? 5 : 3
  })()

  return {
    revenue_growth: rg, profitability: pr, financial_health: fh,
    sales_momentum: sm, kpi_performance: kp,
    team_quality: 5, market_size: 5, competitive_moat: 5, esg_governance: 5, exit_potential: 5,
  }
}

function calcScore(co) {
  if (!co) return 0
  const sc = autoScores(co)
  return allDimensions.reduce((sum, d) => sum + (sc[d.key] || 5) * d.weight, 0) / 10
}

const scoreA = computed(() => calcScore(props.companyA))
const scoreB = computed(() => calcScore(props.companyB))
const winner = computed(() => {
  if (!props.companyA || !props.companyB) return null
  if (Math.abs(scoreA.value - scoreB.value) < 1) return null
  return scoreA.value > scoreB.value ? 'A' : 'B'
})

// ── Radar chart ────────────────────────────────────────────────────────────
const N = allDimensions.length
const CX = 100, CY = 100, R = 85

function radarPoint(i, val) {
  const angle = (i / N) * 2 * Math.PI - Math.PI / 2
  const r = (val / 10) * R
  return { x: CX + r * Math.cos(angle), y: CY + r * Math.sin(angle) }
}

function radarLabel(i) {
  const angle = (i / N) * 2 * Math.PI - Math.PI / 2
  const r = R + 18
  return { x: CX + r * Math.cos(angle), y: CY + r * Math.sin(angle) }
}

function radarRingPoints(val) {
  return Array.from({ length: N }, (_, i) => {
    const p = radarPoint(i, val)
    return `${p.x},${p.y}`
  }).join(' ')
}

function radarPolygonPoints(co) {
  if (!co) return ''
  const sc = autoScores(co)
  return allDimensions.map((d, i) => {
    const p = radarPoint(i, sc[d.key] || 5)
    return `${p.x},${p.y}`
  }).join(' ')
}

// ── Comparison table ───────────────────────────────────────────────────────
const comparisonRows = computed(() => {
  if (!props.companyA || !props.companyB) return []
  const a = props.companyA
  const b = props.companyB
  const fa = a.financials || {}
  const fb = b.financials || {}
  const sa = a.salesData  || {}
  const sb = b.salesData  || {}
  const ka = a.kpiData    || {}
  const kb = b.kpiData    || {}

  const rows = [
    { label: 'Revenue',        rawA: fa.revenue,      rawB: fb.revenue,      fmt: fmtM, higher: true },
    { label: 'Gross Margin %', rawA: fa.gross_margin, rawB: fb.gross_margin, fmt: v => v !== null ? v + '%' : '—', higher: true },
    { label: 'EBITDA Margin %',rawA: fa.ebitda_margin,rawB: fb.ebitda_margin,fmt: v => v !== null ? v + '%' : '—', higher: true },
    { label: 'Net Margin %',   rawA: fa.net_margin,   rawB: fb.net_margin,   fmt: v => v !== null ? v + '%' : '—', higher: true },
    { label: 'ROE %',          rawA: fa.roe,          rawB: fb.roe,          fmt: v => v !== null ? v + '%' : '—', higher: true },
    { label: 'Debt/Equity',    rawA: fa.debt_to_equity,rawB: fb.debt_to_equity,fmt: v => v !== null ? v + 'x' : '—', higher: false },
    { label: 'Total Revenue (Sales)', rawA: sa.total_revenue, rawB: sb.total_revenue, fmt: fmtM, higher: true },
    { label: 'Customers',      rawA: sa.customer_count, rawB: sb.customer_count, fmt: v => v?.toLocaleString() || '—', higher: true },
    { label: 'Sales Growth %', rawA: sa.revenue_growth, rawB: sb.revenue_growth, fmt: v => v !== null ? v + '%' : '—', higher: true },
    { label: 'KPI Health %',   rawA: ka.health_score,  rawB: kb.health_score,  fmt: v => v !== null ? v + '%' : '—', higher: true },
    { label: 'Entry Valuation',rawA: a.entry_valuation, rawB: b.entry_valuation, fmt: fmtM, higher: false },
    { label: 'Deal Size',      rawA: a.invested_amount, rawB: b.invested_amount, fmt: fmtM, higher: false },
    { label: 'Equity Stake %', rawA: a.equity_stake,    rawB: b.equity_stake,    fmt: v => v ? v + '%' : '—', higher: true },
  ]

  return rows.map(r => {
    const valA = r.fmt(r.rawA)
    const valB = r.fmt(r.rawB)
    const numA = r.rawA ?? null
    const numB = r.rawB ?? null
    let winnerA = false, winnerB = false
    if (numA !== null && numB !== null && numA !== numB) {
      winnerA = r.higher ? numA > numB : numA < numB
      winnerB = !winnerA
    }
    return { label: r.label, valA, valB, winnerA, winnerB }
  })
})

// ── Data sources checklist ─────────────────────────────────────────────────
const dataSources = [
  { key: 'financials', label: 'Financial Statements' },
  { key: 'salesData',  label: 'Sales Analysis' },
  { key: 'budgetData', label: 'Budget & Variance' },
  { key: 'kpiData',    label: 'KPI Tracking' },
  { key: 'studyData',  label: 'Financial Study' },
]

function fmtM(v) {
  if (v === null || v === undefined) return '—'
  if (Math.abs(v) >= 1e9) return (v / 1e9).toFixed(1) + 'B'
  if (Math.abs(v) >= 1e6) return (v / 1e6).toFixed(1) + 'M'
  if (Math.abs(v) >= 1e3) return (v / 1e3).toFixed(0) + 'K'
  return Number(v).toLocaleString()
}
</script>