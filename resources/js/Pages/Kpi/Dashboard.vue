<template>
  <Head :title="`KPI Dashboard — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- PAGE HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
          <Link :href="`/portfolio-companies/${company.id}`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to {{ company.name }}
          </Link>
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white">KPI Dashboard</h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }} — Performance tracking</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
              <Link :href="route('kpi.entry', { company: company.id, period_type: selectedType, period_label: selectedLabel })"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Enter / Edit KPIs
              </Link>
              <Link :href="route('kpi.library', company.id)"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                KPI Library
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">

        <!-- Period Selector -->
        <div class="bg-mp-card border border-mp-border rounded-xl p-4 flex flex-wrap gap-4 items-end">
          <div>
            <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1">Period Type</label>
            <select v-model="selectedType" @change="onTypeChange"
              class="bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal">
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="annual">Annual</option>
            </select>
          </div>

          <div>
            <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1">Period</label>

            <!-- Monthly: native month picker -->
            <input
              v-if="selectedType === 'monthly'"
              type="month"
              v-model="selectedLabel"
              @change="onPeriodChange"
              class="bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal"
            />

            <!-- Annual: simple number input -->
            <input
              v-else-if="selectedType === 'annual'"
              type="number"
              v-model="selectedLabel"
              @change="onPeriodChange"
              min="2000"
              max="2100"
              placeholder="2025"
              class="bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal w-28"
            />

            <!-- Quarterly: dropdown of pre-built options -->
            <select
              v-else
              v-model="selectedLabel"
              @change="onPeriodChange"
              class="bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal"
            >
              <option v-for="q in quarterOptions" :key="q" :value="q">{{ q }}</option>
            </select>
          </div>

          <!-- ── Bug Fix 2: category filter buttons now use localFilterCat (local reactive ref)
               instead of filterCat that was filtering the readonly props.cards ── -->
          <div class="flex gap-2 ml-auto">
            <button
              v-for="cat in ['all', 'financial', 'non_financial']"
              :key="cat"
              @click="localFilterCat = cat"
              :class="[
                'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                localFilterCat === cat
                  ? 'bg-mp-teal text-white'
                  : 'bg-mp-card-hover border border-mp-border text-white hover:text-white'
              ]"
            >
              {{ cat === 'all' ? 'All' : cat === 'financial' ? '💰 Financial' : '📋 Non-Financial' }}
            </button>
          </div>
        </div>

        <!-- Summary Cards -->
        <div>
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Performance Overview</p>
          <div v-if="filteredCards.length === 0" class="text-white text-sm text-center py-10">
            No KPIs to display for this filter.
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-for="card in filteredCards" :key="card.id"
              class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden hover:border-mp-teal transition-colors">

              <!-- Status dot -->
              <div class="flex items-start justify-between mb-3">
                <p class="text-xs font-semibold uppercase tracking-widest"
                  :class="card.category === 'financial' ? 'text-white' : 'text-white'">
                  {{ card.category === 'financial' ? 'Financial' : 'Non-Financial' }}
                </p>
                <span :class="['w-2.5 h-2.5 rounded-full mt-0.5 flex-shrink-0', statusDot(card.status)]"></span>
              </div>

              <p class="text-m text-white font-medium mb-2 leading-tight">{{ card.name }}</p>

              <!-- Actual value -->
              <p class="text-2xl font-bold text-white mb-1">
                {{ formatValue(card.actual, card.unit) }}
              </p>
              <p class="text-l text-mp-success mb-3">Target: {{ formatValue(card.target, card.unit) }}</p>

              <!-- Variance + status -->
              <div v-if="card.actual !== null && card.target !== null" class="flex items-center gap-2">
                <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', varianceBadge(card.status)]">
                  {{ card.variance_pct !== null ? (card.variance_pct > 0 ? '+' : '') + card.variance_pct.toFixed(1) + '%' : '—' }}
                </span>
                <span :class="['text-xs font-bold uppercase tracking-wide', statusText(card.status)]">
                  {{ statusLabel(card.status) }}
                </span>
              </div>
              <div v-else class="text-xs text-white italic">No data entered</div>

              <!-- Sparkline -->
              <div class="mt-4 flex items-end gap-0.5 h-7">
                <div v-for="(t, i) in card.trend" :key="i"
                  :style="{ height: sparkHeight(t.actual, card.trend) + '%' }"
                  :class="['flex-1 rounded-sm', t.actual !== null ? 'bg-mp-teal/60' : 'bg-mp-page']">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Detail Table -->
        <div>
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Detailed Breakdown</p>
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">KPI</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Category</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Target</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Actual</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Variance</th>
                    <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Status</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Source</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="card in filteredCards" :key="'t' + card.id"
                    class="hover:bg-mp-card-hover/50 transition-colors">
                    <td class="px-6 py-3 text-white font-medium">{{ card.name }}</td>
                    <td class="px-4 py-3">
                      <span :class="['text-xs px-2 py-0.5 rounded-full font-semibold',
                        card.category === 'financial'
                          ? 'bg-mp-teal-subtle text-white border border-mp-teal'
                          : 'bg-mp-gold/15 text-white border border-mp-gold']">
                        {{ card.category === 'financial' ? 'Financial' : 'Non-Financial' }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-right text-white">{{ formatValue(card.target, card.unit) }}</td>
                    <td class="px-4 py-3 text-right text-white font-semibold">{{ formatValue(card.actual, card.unit) }}</td>
                    <td class="px-4 py-3 text-right">
                      <span v-if="card.variance_pct !== null"
                        :class="['text-xs px-2 py-0.5 rounded-full font-semibold', varianceBadge(card.status)]">
                        {{ (card.variance_pct > 0 ? '+' : '') + card.variance_pct.toFixed(1) + '%' }}
                      </span>
                      <span v-else class="text-white">—</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span :class="['text-xs px-2.5 py-1 rounded-full font-semibold', statusBadge(card.status)]">
                        {{ statusLabel(card.status) }}
                      </span>
                    </td>
                    <td class="px-4 py-3">
                      <span :class="['text-xs px-2 py-0.5 rounded-full font-medium',
                        card.source === 'auto_fs'
                          ? 'bg-mp-teal-subtle text-white border border-mp-teal'
                          : 'bg-mp-card-hover text-white border border-mp-border']">
                        {{ card.source === 'auto_fs' ? '⚡ Auto' : '✏️ Manual' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:     Object,
  cards:       Array,
  periodType:  String,
  periodLabel: String,
})

const companyCurrency = computed(() => {
  return (props.company?.invested_currency || 'USD').toUpperCase().trim()
})

const selectedType  = ref(props.periodType  ?? 'monthly')
const selectedLabel = ref(props.periodLabel ?? '')

// ── Bug Fix 2: use a local ref for category filter (not mutating props) ──────
const localFilterCat = ref('all')

// ── Bug Fix 3: when the period TYPE changes, reset the label to a valid
//    default for the new type so the picker is never left with a stale value ──
function onTypeChange() {
  const now = new Date()

  if (selectedType.value === 'monthly') {
    const y = now.getFullYear()
    const m = String(now.getMonth() + 1).padStart(2, '0')
    selectedLabel.value = `${y}-${m}`

  } else if (selectedType.value === 'quarterly') {
    const y = now.getFullYear()
    const q = Math.ceil((now.getMonth() + 1) / 3)
    selectedLabel.value = `${y}-Q${q}`

  } else {
    // annual
    selectedLabel.value = String(now.getFullYear())
  }

  // Now fire the actual navigation with the fresh label
  onPeriodChange()
}

function onPeriodChange() {
  if (!selectedLabel.value) return
  router.get(
    route('kpi.dashboard', props.company.id),
    { period_type: selectedType.value, period_label: String(selectedLabel.value) },
    { preserveState: true }
  )
}

// Quarter options: 3 years back → 2 years forward
const quarterOptions = computed(() => {
  const opts = []
  const now  = new Date()
  const from = now.getFullYear() - 3
  const to   = now.getFullYear() + 2
  for (let y = from; y <= to; y++)
    for (let q = 1; q <= 4; q++)
      opts.push(`${y}-Q${q}`)
  return opts
})

// ── Bug Fix 2: filteredCards now uses localFilterCat (reactive) ──────────────
const filteredCards = computed(() => {
  const all = props.cards ?? []
  if (localFilterCat.value === 'all') return all
  return all.filter(c => c.category === localFilterCat.value)
})

function formatValue(val, unit) {
  if (val === null || val === undefined) return '—'

  if (unit === 'currency') {
    try {
      return new Intl.NumberFormat('en-US', {
        style:                'currency',
        currency:             companyCurrency.value,
        maximumFractionDigits: 0,
        minimumFractionDigits: 0,
      }).format(val)
    } catch {
      return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(val) + ' ' + companyCurrency.value
    }
  }

  if (unit === 'percentage') return val.toFixed(1) + '%'
  if (unit === 'ratio')      return val.toFixed(2) + 'x'
  return new Intl.NumberFormat('en-US').format(val)
}

function statusDot(s) {
  return { on_track: 'bg-mp-success', watch: 'bg-mp-warning', at_risk: 'bg-mp-danger', no_data: 'bg-mp-muted' }[s] ?? 'bg-mp-muted'
}
function statusText(s) {
  return { on_track: 'text-mp-success', watch: 'text-mp-warning', at_risk: 'text-mp-danger', no_data: 'text-white' }[s] ?? 'text-white'
}
function statusLabel(s) {
  return { on_track: 'On Track', watch: 'Watch', at_risk: 'At Risk', no_data: 'No Data' }[s] ?? '—'
}
function statusBadge(s) {
  return {
    on_track: 'bg-mp-success/15 text-mp-success border border-mp-success',
    watch:    'bg-mp-warning/15 text-mp-warning border border-mp-warning',
    at_risk:  'bg-mp-danger/15 text-mp-danger border border-mp-danger',
    no_data:  'bg-mp-card-hover text-white',
  }[s] ?? 'bg-mp-card-hover text-white'
}
function varianceBadge(s) {
  return {
    on_track: 'bg-mp-success/50 text-mp-success',
    watch:    'bg-mp-warning/50 text-mp-warning',
    at_risk:  'bg-mp-danger/50 text-mp-danger',
    no_data:  'bg-mp-card-hover text-white',
  }[s] ?? 'bg-mp-card-hover text-white'
}
function sparkHeight(val, trend) {
  const vals = (trend ?? []).map(t => t.actual).filter(v => v !== null)
  if (!vals.length || val === null) return 20
  const min = Math.min(...vals), max = Math.max(...vals)
  if (max === min) return 50
  return Math.max(10, ((val - min) / (max - min)) * 100)
}
</script>