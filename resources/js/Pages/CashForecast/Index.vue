<template>
  <Head :title="`Cash Forecast — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-mp-text-secondary">

      <!-- ═══════════════════ HEADER ═══════════════════ -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">

          <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
            class="flex items-center gap-2 text-sm text-mp-muted hover:text-mp-text-secondary transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Financial Statement
          </Link>

          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-mp-text-secondary">💧 Cash Flow Forecast</h1>
              <p class="text-mp-muted text-sm mt-0.5">{{ company.name }} · 12-month outlook</p>
            </div>
           
            <!-- Statement selector -->
            <div class="flex items-center gap-3">
              <span class="text-xs text-mp-muted">Based on statement:</span>
              <select v-model="selectedStatementId" @change="changeStatement"
                class="bg-mp-card-hover border border-mp-border text-mp-text-secondary text-sm rounded-lg px-3 py-2 focus:border-mp-teal focus:outline-none min-w-52">
                <option v-for="s in allStatements" :key="s.id" :value="s.id">{{ s.label }}</option>
              </select>

               <a :href="`/companies/${company.id}/sales`"
                class="flex items-center  gap-1.5 px-4 py-2 rounded-lg text-sm bg-mp-teal hover:bg-mp-teal-dark text-mp-text-secondary font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                Sales Dashboard
              </a>

              <a :href="`/companies/${company.id}/expenses`"
                class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm bg-mp-gold hover:bg-mp-gold-dark text-mp-text-secondary font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                Expenses Dashboard
              </a>

            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- ═══════════════════ KPI CARDS ═══════════════════ -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-mp-muted mb-1">Total Cash In</p>
            <p class="text-mp-success font-bold text-lg tabular-nums">{{ formatNum(kpis.totalIn) }}</p>
            <p class="text-xs text-mp-muted mt-0.5">over 12 months</p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-mp-muted mb-1">Total Cash Out</p>
            <p class="text-mp-danger font-bold text-lg tabular-nums">{{ formatNum(kpis.totalOut) }}</p>
            <p class="text-xs text-mp-muted mt-0.5">over 12 months</p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-mp-muted mb-1">Net Position</p>
            <p :class="kpis.netTotal >= 0 ? 'text-mp-success' : 'text-mp-danger'" class="font-bold text-lg tabular-nums">
              {{ kpis.netTotal >= 0 ? '+' : '' }}{{ formatNum(kpis.netTotal) }}
            </p>
            <p class="text-xs text-mp-muted mt-0.5">12-month net</p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-mp-muted mb-1">Accumulated End</p>
            <p :class="kpis.accumulated >= 0 ? 'text-white' : 'text-mp-danger'" class="font-bold text-lg tabular-nums">
              {{ formatNum(kpis.accumulated) }}
            </p>
            <p class="text-xs text-mp-muted mt-0.5">cumulative cash</p>
          </div>
        </div>

        <!-- ═══════════════════ CHART ═══════════════════ -->
        <div class="bg-mp-card rounded-xl border border-mp-border p-5">
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-sm font-semibold text-mp-text-secondary">📈 Net Cash & Accumulated Balance</h2>
            <div class="flex items-center gap-4 text-xs text-mp-muted">
              <div class="flex items-center gap-1.5"><div class="w-4 h-0.5 bg-mp-teal"></div> Net Cash / Month</div>
              <div class="flex items-center gap-1.5"><div class="w-4 h-0.5 bg-mp-success border-dashed"></div> Accumulated</div>
            </div>
          </div>
          <div class="relative h-64">
            <canvas ref="chartCanvas"></canvas>
          </div>
        </div>

        <!-- ═══════════════════ MONTHLY GRID TABLE ═══════════════════ -->
        <div class="bg-mp-card rounded-xl border border-mp-border overflow-x-auto">
          <div class="px-5 py-4 border-b border-mp-border">
            <h2 class="text-sm font-semibold text-mp-text-secondary">📋 Monthly Breakdown</h2>
          </div>
          <table class="w-full text-sm" style="min-width: max-content;">
            <thead>
              <tr class="border-b border-mp-border bg-mp-card-hover/50">
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3 min-w-28">Month</th>
                <th class="text-right text-xs font-semibold text-mp-success uppercase tracking-widest px-5 py-3 min-w-36">Cash In</th>
                <th class="text-right text-xs font-semibold text-mp-danger uppercase tracking-widest px-5 py-3 min-w-36">Cash Out</th>
                <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3 min-w-36">Net</th>
                <th class="text-right text-xs font-semibold text-mp-success uppercase tracking-widest px-5 py-3 min-w-36">Accumulated</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
              <tr v-for="row in grid" :key="row.month"
                class="hover:bg-mp-card-hover/30 transition-colors">
                <td class="px-5 py-3 text-mp-text font-medium">{{ row.label }}</td>
                <td class="px-5 py-3 text-right text-mp-success tabular-nums font-semibold">
                  {{ row.cash_in > 0 ? formatNum(row.cash_in) : '—' }}
                </td>
                <td class="px-5 py-3 text-right text-mp-danger tabular-nums font-semibold">
                  {{ row.cash_out > 0 ? formatNum(row.cash_out) : '—' }}
                </td>
                <td class="px-5 py-3 text-right tabular-nums font-bold"
                  :class="row.net >= 0 ? 'text-white' : 'text-mp-danger'">
                  {{ row.net >= 0 ? '+' : '' }}{{ formatNum(row.net) }}
                </td>
                <td class="px-5 py-3 text-right tabular-nums font-bold"
                  :class="row.accumulated >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                  {{ formatNum(row.accumulated) }}
                </td>
              </tr>
            </tbody>
            <!-- Totals row -->
            <tfoot>
              <tr class="bg-mp-card-hover/60 border-t-2 border-mp-border">
                <td class="px-5 py-3 text-mp-text-secondary font-bold text-xs uppercase tracking-wide">Total</td>
                <td class="px-5 py-3 text-right text-mp-success font-bold tabular-nums">{{ formatNum(kpis.totalIn) }}</td>
                <td class="px-5 py-3 text-right text-mp-danger font-bold tabular-nums">{{ formatNum(kpis.totalOut) }}</td>
                <td class="px-5 py-3 text-right font-bold tabular-nums"
                  :class="kpis.netTotal >= 0 ? 'text-white' : 'text-mp-danger'">
                  {{ kpis.netTotal >= 0 ? '+' : '' }}{{ formatNum(kpis.netTotal) }}
                </td>
                <td class="px-5 py-3 text-right font-bold tabular-nums"
                  :class="kpis.accumulated >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                  {{ formatNum(kpis.accumulated) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- ═══════════════════ TWO COLUMN: SETTLEMENT + MANUAL ═══════════════════ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

          <!-- Settlement Sources -->
          <div class="bg-mp-card rounded-xl border border-mp-border">
            <div class="px-5 py-4 border-b border-mp-border flex items-center justify-between">
              <div>
                <h2 class="text-sm font-semibold text-mp-text-secondary">🏦 Balance Sheet Settlements</h2>
                <p class="text-xs text-mp-muted mt-0.5">From line item schedules on the financial statement</p>
              </div>
              <Link v-if="activeStatement"
                :href="`/portfolio-companies/${company.id}/financial-statements/${activeStatement.id}/edit`"
                class="text-xs text-white hover:text-white transition-colors">
                Edit Schedules →
              </Link>
            </div>

            <div v-if="!settlementData.length" class="px-5 py-8 text-center text-mp-muted text-sm">
              No settlement schedules yet.<br>
              <span class="text-xs">Edit the financial statement and add monthly schedules to balance sheet line items.</span>
            </div>

            <div v-else class="divide-y divide-gray-800">
              <div v-for="item in settlementData" :key="item.line_item_id"
                class="px-5 py-4">
                <div class="flex items-center justify-between mb-2">
                  <div>
                    <span class="text-mp-text-secondary text-sm font-medium">{{ item.line_item_label }}</span>
                    <span class="ml-2 text-xs text-mp-muted">{{ item.section_label }}</span>
                  </div>
                  <span :class="item.cash_direction === 'in' ? 'bg-mp-success/50 text-mp-success' : 'bg-mp-danger/50 text-mp-danger'"
                    class="text-xs font-semibold px-2 py-0.5 rounded-full">
                    {{ item.cash_direction === 'in' ? '↑ In' : '↓ Out' }}
                  </span>
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <div v-for="sch in item.schedules.filter(s => s.amount > 0)" :key="sch.month"
                    class="text-xs bg-mp-card-hover border border-mp-border rounded px-2 py-1">
                    <span class="text-mp-muted">{{ formatMonthLabel(sch.month) }}:</span>
                    <span class="text-mp-text-secondary font-semibold ml-1">{{ formatNum(sch.amount) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Manual Entries -->
          <div class="bg-mp-card rounded-xl border border-mp-border">
            <div class="px-5 py-4 border-b border-mp-border flex items-center justify-between">
              <div>
                <h2 class="text-sm font-semibold text-mp-text-secondary">✏️ Manual Forecast Entries</h2>
                <p class="text-xs text-mp-muted mt-0.5">Additional cash flows not in the balance sheet</p>
              </div>
              <button @click="openNewEntry"
                class="flex items-center gap-1.5 text-xs bg-mp-teal hover:bg-mp-teal-dark text-mp-text-secondary px-3 py-1.5 rounded-lg transition-colors font-medium">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Entry
              </button>
            </div>

            <div v-if="!localEntries.length" class="px-5 py-8 text-center text-mp-muted text-sm">
              No manual entries yet. Click "Add Entry" to add a forecast cash flow.
            </div>

            <div v-else class="divide-y divide-gray-800 max-h-96 overflow-y-auto">
              <div v-for="entry in localEntries" :key="entry.id"
                class="px-5 py-3 flex items-start justify-between gap-3 hover:bg-mp-card-hover/30 transition-colors">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span :class="entry.type === 'in' ? 'text-mp-success' : 'text-mp-danger'" class="font-semibold text-sm">
                      {{ entry.type === 'in' ? '↑' : '↓' }} {{ entry.description }}
                    </span>
                    <span class="text-xs bg-mp-card-hover border border-mp-border text-mp-muted px-1.5 py-0.5 rounded">
                      {{ entry.category }}
                    </span>
                    <span v-if="entry.is_recurring"
                      class="text-xs bg-mp-gold/50 border border-mp-gold text-white px-1.5 py-0.5 rounded">
                      🔁 recurring
                    </span>
                  </div>
                  <div class="text-xs text-mp-muted mt-0.5">
                    {{ formatMonthLabel(entry.month) }}
                    <span v-if="entry.is_recurring && entry.recurring_end_month">
                      → {{ formatMonthLabel(entry.recurring_end_month) }}
                    </span>
                    · <span class="font-semibold text-mp-text">{{ formatNum(entry.amount) }} {{ company.currency }}</span>
                  </div>
                  <p v-if="entry.notes" class="text-xs text-mp-muted mt-0.5 truncate">{{ entry.notes }}</p>
                </div>
                <div class="flex items-center gap-1.5 flex-shrink-0">
                  <button @click="editEntry(entry)"
                    class="w-7 h-7 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-teal text-mp-muted hover:text-mp-text-secondary transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </button>
                  <button @click="deleteEntry(entry.id)"
                    class="w-7 h-7 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-mp-muted hover:text-mp-text-secondary transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ═══════════════════ ENTRY MODAL ═══════════════════ -->
    <div v-if="entryModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
      <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-lg shadow-2xl">

        <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
          <h3 class="text-mp-text-secondary font-bold">{{ entryModal.id ? 'Edit Entry' : 'New Forecast Entry' }}</h3>
          <button @click="entryModal.open = false"
            class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-mp-muted hover:text-mp-text-secondary transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <div class="px-6 py-5 space-y-4">

          <!-- Type -->
          <div>
            <label class="block text-xs text-white uppercase tracking-widest mb-2 font-semibold">Direction</label>
            <div class="flex gap-3">
              <button v-for="t in [{key:'in',label:'↑ Cash In'},{key:'out',label:'↓ Cash Out'}]" :key="t.key"
                @click="entryModal.form.type = t.key"
                :class="[
                  'flex-1 py-2.5 rounded-lg text-sm font-semibold border transition-colors',
                  entryModal.form.type === t.key
                    ? (t.key === 'in' ? 'bg-mp-success border-mp-success text-mp-text-secondary' : 'bg-mp-danger border-mp-danger text-mp-text-secondary')
                    : 'bg-mp-card-hover border-mp-border text-mp-muted hover:text-mp-text-secondary'
                ]">
                {{ t.label }}
              </button>
            </div>
          </div>

          <!-- Description -->
          <div>
            <label class="block text-xs text-white uppercase tracking-widest mb-1.5 font-semibold">Description *</label>
            <input v-model="entryModal.form.description" type="text" placeholder="e.g. Loan repayment to bank"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
          </div>

          <!-- Amount + Category -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-white uppercase tracking-widest mb-1.5 font-semibold">Amount *</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mp-muted text-xs">{{ company.currency }}</span>
                <input v-model.number="entryModal.form.amount" type="number" min="0" step="0.01"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg pl-10 pr-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
              </div>
            </div>
            <div>
              <label class="block text-xs text-white uppercase tracking-widest mb-1.5 font-semibold">Category *</label>
              <select v-model="entryModal.form.category"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                <option value="operating">Operating</option>
                <option value="investing">Investing</option>
                <option value="financing">Financing</option>
              </select>
            </div>
          </div>

          <!-- Month -->
          <div>
            <label class="block text-xs text-white uppercase tracking-widest mb-1.5 font-semibold">Month *</label>
            <input v-model="entryModal.form.month" type="month"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
          </div>

          <!-- Recurring -->
          <div class="bg-mp-card-hover/50 rounded-lg p-4 border border-mp-border">
            <label class="flex items-center gap-3 cursor-pointer select-none">
              <div @click="entryModal.form.is_recurring = !entryModal.form.is_recurring"
                :class="entryModal.form.is_recurring ? 'bg-mp-gold-dark' : 'bg-mp-muted'"
                class="relative w-10 h-5 rounded-full transition-colors cursor-pointer flex-shrink-0">
                <div :class="entryModal.form.is_recurring ? 'translate-x-5' : 'translate-x-0.5'"
                  class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"></div>
              </div>
              <span class="text-sm text-mp-text font-medium">🔁 Recurring monthly</span>
            </label>
            <div v-if="entryModal.form.is_recurring" class="mt-3">
              <label class="block text-xs text-mp-muted mb-1.5">Repeat until (month)</label>
              <input v-model="entryModal.form.recurring_end_month" type="month"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-gold"/>
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs text-white uppercase tracking-widest mb-1.5 font-semibold">Notes</label>
            <textarea v-model="entryModal.form.notes" rows="2" placeholder="Optional..."
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal resize-none"/>
          </div>

          <!-- Error -->
          <p v-if="entryModal.error" class="text-mp-danger text-xs">{{ entryModal.error }}</p>
        </div>

        <div class="px-6 py-4 border-t border-mp-border flex justify-end gap-3">
          <button @click="entryModal.open = false"
            class="px-4 py-2 rounded-lg bg-mp-card-hover hover:bg-mp-page text-mp-text text-sm font-medium transition-colors">
            Cancel
          </button>
          <button @click="saveEntry" :disabled="entryModal.saving"
            class="px-5 py-2 rounded-lg bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 text-mp-text-secondary text-sm font-semibold transition-colors flex items-center gap-2">
            <svg v-if="entryModal.saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            {{ entryModal.saving ? 'Saving...' : 'Save Entry' }}
          </button>
        </div>
      </div>
    </div>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:         Object,
  allStatements:   Array,
  activeStatement: Object,
  forecastMonths:  Array,
  settlementData:  Array,
  manualEntries:   Array,
  grid:            Array,
})

const selectedStatementId = ref(props.activeStatement?.id ?? null)
const localEntries        = ref([...props.manualEntries])
watch(() => props.manualEntries, (newEntries) => {
  localEntries.value = [...newEntries]
}, { deep: true })
const chartCanvas         = ref(null)
let   chartInstance       = null

// ── KPIs ──
const kpis = computed(() => {
  const totalIn  = props.grid.reduce((s, r) => s + r.cash_in,  0)
  const totalOut = props.grid.reduce((s, r) => s + r.cash_out, 0)
  const last     = props.grid[props.grid.length - 1]
  return {
    totalIn,
    totalOut,
    netTotal:    totalIn - totalOut,
    accumulated: last?.accumulated ?? 0,
  }
})

// ── Change statement ──
function changeStatement() {
  router.visit(`/portfolio-companies/${props.company.id}/cash-forecast/${selectedStatementId.value}`)
}

// ── Chart ──
onMounted(async () => {
  await nextTick()
  buildChart()
})

watch(() => props.grid, async () => {
  await nextTick()
  buildChart()
}, { deep: true })

function buildChart() {
  if (!chartCanvas.value) return

  const labels  = props.grid.map(r => r.label)
  const netData = props.grid.map(r => r.net)
  const accData = props.grid.map(r => r.accumulated)

  // Destroy existing
  if (chartInstance) { chartInstance.destroy(); chartInstance = null }

  const ctx = chartCanvas.value.getContext('2d')

  // Use Chart.js from CDN (loaded globally in the app, or import)
  if (typeof Chart === 'undefined') {
    // Fallback: load Chart.js dynamically
    const script = document.createElement('script')
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js'
    script.onload = () => renderChart(ctx, labels, netData, accData)
    document.head.appendChild(script)
  } else {
    renderChart(ctx, labels, netData, accData)
  }
}

function renderChart(ctx, labels, netData, accData) {
  chartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label:            'Net Cash / Month',
          data:             netData,
          borderColor:      '#00b4c8',
          backgroundColor:  'rgba(96,165,250,0.08)',
          borderWidth:      2,
          pointRadius:      4,
          pointBackgroundColor: '#00b4c8',
          tension:          0.3,
          fill:             true,
          yAxisID:          'y',
        },
        {
          label:            'Accumulated Balance',
          data:             accData,
          borderColor:      '#10b981',
          backgroundColor:  'transparent',
          borderWidth:      2.5,
          borderDash:       [5, 4],
          pointRadius:      4,
          pointBackgroundColor: '#10b981',
          tension:          0.3,
          fill:             false,
          yAxisID:          'y',
        },
      ],
    },
    options: {
      responsive:          true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend:  { display: false },
        tooltip: {
          backgroundColor: '#112240',
          borderColor:     '#1490a833',
          borderWidth:     1,
          titleColor:      '#ffffff',
          bodyColor:       '#e2e8f0',
          callbacks: {
            label: ctx => ` ${ctx.dataset.label}: ${Number(ctx.raw).toLocaleString('en-US', { maximumFractionDigits: 0 })}`,
          },
        },
      },
      scales: {
        x: {
          grid:  { color: '#112240' },
          ticks: { color: '#64748b', font: { size: 11 } },
        },
        y: {
          grid:  { color: '#112240' },
          ticks: { color: '#64748b', font: { size: 11 },
            callback: v => Number(v).toLocaleString('en-US', { notation: 'compact', maximumFractionDigits: 1 }),
          },
        },
      },
    },
  })
}

// ── Entry Modal ──
const entryModal = ref({
  open:  false,
  id:    null,
  saving: false,
  error: '',
  form: {
    type:                 'in',
    category:             'operating',
    description:          '',
    amount:               0,
    month:                '',
    is_recurring:         false,
    recurring_end_month:  '',
    notes:                '',
  },
})

function openNewEntry() {
  const firstMonth = props.forecastMonths?.[0] ?? ''
  entryModal.value = {
    open: true, id: null, saving: false, error: '',
    form: {
      type: 'in', category: 'operating', description: '',
      amount: 0, month: firstMonth, is_recurring: false,
      recurring_end_month: '', notes: '',
    },
  }
}

function editEntry(entry) {
  entryModal.value = {
    open: true, id: entry.id, saving: false, error: '',
    form: { ...entry },
  }
}

async function saveEntry() {
  const f = entryModal.value.form
  if (!f.description || !f.amount || !f.month) {
    entryModal.value.error = 'Description, amount and month are required.'
    return
  }
  entryModal.value.saving = true
  entryModal.value.error  = ''

  const xsrfToken = decodeURIComponent(
    (document.cookie.split(';').map(c => c.trim()).find(c => c.startsWith('XSRF-TOKEN=')) || '')
    .split('=').slice(1).join('=')
  )
  const isEdit = !!entryModal.value.id
  const url       = isEdit
    ? `/portfolio-companies/${props.company.id}/cash-forecast/entries/${entryModal.value.id}`
    : `/portfolio-companies/${props.company.id}/cash-forecast/entries`

  try {
    const res = await fetch(url, {
      method:  isEdit ? 'PUT' : 'POST',
      headers: {
        'Content-Type':    'application/json',
        'Accept':          'application/json',
        'X-XSRF-TOKEN':    xsrfToken,
        'X-Requested-With':'XMLHttpRequest',
      },
      body: JSON.stringify({
        ...f,
        amount: String(f.amount),
        financial_statement_id: props.activeStatement?.id ?? null,
      }),
    })

    if (res.ok) {
      const data = await res.json()
      entryModal.value.open = false
      // Reload page to recalculate grid
      router.reload({ only: ['grid', 'manualEntries'] })
    } else {
      entryModal.value.error = 'Failed to save. Please check your inputs.'
    }
  } finally {
    entryModal.value.saving = false
  }
}

async function deleteEntry(id) {
  if (!confirm('Delete this entry?')) return
  const xsrfToken2 = decodeURIComponent((document.cookie.split(';').map(c=>c.trim()).find(c=>c.startsWith('XSRF-TOKEN='))||'').split('=').slice(1).join('='))
  await fetch(`/portfolio-companies/${props.company.id}/cash-forecast/entries/${id}`, {
    method: 'DELETE',
    headers: { 'X-XSRF-TOKEN': xsrfToken2, 'X-Requested-With': 'XMLHttpRequest' },
  })
  router.reload({ only: ['grid', 'manualEntries'] })
}

// ── Helpers ──
function formatMonthLabel(ym) {
  if (!ym) return ''
  const [y, m] = ym.split('-')
  return new Date(+y, +m - 1, 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
}

function formatNum(val) {
  if (val === null || val === undefined) return '—'
  return parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}
</script>