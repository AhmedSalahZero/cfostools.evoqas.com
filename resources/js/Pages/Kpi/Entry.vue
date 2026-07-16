<template>
  <Head :title="`KPI Entry — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- PAGE HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
          <Link :href="route('kpi.dashboard', company.id)"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to KPI Dashboard
          </Link>
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white">KPI Data Entry</h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }} — Enter targets and actuals</p>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success"
          class="bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          ✅ {{ $page.props.flash.success }}
        </div>

        <!-- Period Selector -->
        <div class="bg-mp-card border border-mp-border rounded-xl p-4 flex flex-wrap gap-4 items-end">
          <div>
            <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1">Period Type</label>
            <select v-model="form.period_type" @change="onPeriodChange"
              class="bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal">
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="annual">Annual</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1">Period</label>
            <input v-if="form.period_type === 'monthly'" type="month" v-model="form.period_label" @change="onPeriodChange"
              class="bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal" />
            <input v-else-if="form.period_type === 'annual'" type="number" v-model="form.period_label" @change="onPeriodChange"
              min="2000" max="2100" placeholder="2025"
              class="bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal w-28" />
            <select v-else v-model="form.period_label" @change="onPeriodChange"
              class="bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal">
              <option v-for="q in quarterOptions" :key="q" :value="q">{{ q }}</option>
            </select>
          </div>
        </div>

        <!-- Financial KPIs -->
        <div>
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">💰 Financial KPIs</p>
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">KPI</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">
                      Unit
                      <span v-if="companyCurrency !== 'USD'" class="text-xs text-white ml-1">
                        ({{ companyCurrency }})
                      </span>
                    </th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Target</th>
                    <th class="text-cneter text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Actual</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Notes</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Source</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="row in financialRows" :key="row.kpi_definition_id"
                    class="hover:bg-mp-card-hover/50 transition-colors">
                    <td class="px-6 py-3 text-white font-medium">{{ row.name }}</td>
                    <td class="px-4 py-3 text-white text-xs">
                    <span v-if="row.unit === 'currency'">
                      {{ companyCurrency }}
                    </span>
                    <span v-else>
                      {{ row.unit }}
                    </span>
                  </td>
                    <td class="px-4 py-3">
                      <input type="number" step="any"
                        v-model="form.entries[row._idx].target"
                        class="bg-mp-card-hover text-white rounded-lg px-3 py-1.5 text-sm text-right w-36 border border-mp-border focus:outline-none focus:border-mp-teal"
                        placeholder="—" />
                    </td>
                    <td class="px-4 py-3">
                      <div v-if="row.auto_synced" class="text-right pr-2">
                        <span class="text-white text-sm font-semibold">{{ formatValue(row.actual, row.unit) }}</span>
                        <span class="block text-xs text-white mt-0.5">⚡ Auto-synced</span>
                      </div>
                      <input v-else type="number" step="any"
                        v-model="form.entries[row._idx].actual"
                        class="bg-mp-card-hover text-white rounded-lg px-3 py-1.5 text-sm text-right w-36 border border-mp-border focus:outline-none focus:border-mp-teal"
                        placeholder="—" />
                    </td>
                    <td class="px-4 py-3">
                      <input type="text"
                        v-model="form.entries[row._idx].notes"
                        class="bg-mp-card-hover text-white rounded-lg px-3 py-1.5 text-sm w-48 border border-mp-border focus:outline-none focus:border-mp-teal"
                        placeholder="Optional note" />
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span :class="['text-xs px-2 py-0.5 rounded-full font-medium',
                        row.source === 'auto_fs' ? 'bg-mp-teal-subtle text-white border border-mp-teal' : 'bg-mp-card-hover text-white border border-mp-border']">
                        {{ row.source === 'auto_fs' ? '⚡ Auto' : '✏️ Manual' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Non-Financial KPIs -->
        <div>
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">📋 Non-Financial KPIs</p>
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">KPI</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Unit</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Target</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Actual</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Notes</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="row in nonFinancialRows" :key="row.kpi_definition_id"
                    class="hover:bg-mp-card-hover/50 transition-colors">
                    <td class="px-6 py-3 text-white font-medium">{{ row.name }}</td>
                    <td class="px-4 py-3 text-white capitalize text-xs">{{ row.unit }}</td>
                    <td class="px-4 py-3">
                      <input type="number" step="any"
                        v-model="form.entries[row._idx].target"
                        class="bg-mp-card-hover text-white rounded-lg px-3 py-1.5 text-sm text-right w-36 border border-mp-border focus:outline-none focus:border-mp-teal"
                        placeholder="—" />
                    </td>
                    <td class="px-4 py-3">
                      <input type="number" step="any"
                        v-model="form.entries[row._idx].actual"
                        class="bg-mp-card-hover text-white rounded-lg px-3 py-1.5 text-sm text-right w-36 border border-mp-border focus:outline-none focus:border-mp-teal"
                        placeholder="—" />
                    </td>
                    <td class="px-4 py-3">
                      <input type="text"
                        v-model="form.entries[row._idx].notes"
                        class="bg-mp-card-hover text-white rounded-lg px-3 py-1.5 text-sm w-48 border border-mp-border focus:outline-none focus:border-mp-teal"
                        placeholder="Optional note" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end pb-6">
          <button @click="submit" :disabled="form.processing"
            class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            {{ form.processing ? 'Saving...' : 'Save KPI Data' }}
          </button>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>


<script setup>
import { computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:     Object,
  rows:        Array,
  periodType:  String,
  periodLabel: String,
})

// This now reads the real currency from the company
const companyCurrency = computed(() => {
  return (props.company?.invested_currency || 'USD').toUpperCase().trim()
})

const entriesInit = props.rows.map(row => ({
  kpi_definition_id: row.kpi_definition_id,
  target:            row.target ?? '',
  actual:            row.actual ?? '',
  notes:             row.notes ?? '',
}))

const form = useForm({
  period_type:  props.periodType,
  period_label: props.periodLabel,
  entries:      entriesInit,
})

const rowsWithIdx      = props.rows.map((row, i) => ({ ...row, _idx: i }))
const financialRows    = computed(() => rowsWithIdx.filter(r => r.category === 'financial'))
const nonFinancialRows = computed(() => rowsWithIdx.filter(r => r.category === 'non_financial'))

const quarterOptions = computed(() => {
  const opts = []
  const currentYear = new Date().getFullYear()
  const from = currentYear - 3
  const to   = currentYear + 2
  for (let y = from; y <= to; y++)
    for (let q = 1; q <= 4; q++)
      opts.push(`${y}-Q${q}`)
  return opts
})

function onPeriodChange() {
  router.get(route('kpi.entry', props.company.id), {
    period_type:  form.period_type,
    period_label: form.period_label,
  }, { preserveState: true, preserveScroll: true })
}

function submit() {
  form.post(route('kpi.save-entry', props.company.id))
}

// NEW FORMAT FUNCTION – uses the real company currency
function formatValue(val, unit) {
  if (val === null || val === undefined || val === '') return '—'

  if (unit === 'currency') {
    try {
      const formatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: companyCurrency.value,
        maximumFractionDigits: 0,
        minimumFractionDigits: 0,
      })
      return formatter.format(val)
    } catch (err) {
      console.warn('Invalid currency code:', companyCurrency.value)
      return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(val) + ' ' + companyCurrency.value
    }
  }

  if (unit === 'percentage') return val.toFixed(1) + '%'
  if (unit === 'ratio')      return val.toFixed(2) + 'x'

  return new Intl.NumberFormat('en-US').format(val)
}
</script>








<!-- <script setup>
import { computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:     Object,
  rows:        Array,
  periodType:  String,
  periodLabel: String,
})

const entriesInit = props.rows.map(row => ({
  kpi_definition_id: row.kpi_definition_id,
  target:            row.target ?? '',
  actual:            row.actual ?? '',
  notes:             row.notes ?? '',
}))

const form = useForm({
  period_type:  props.periodType,
  period_label: props.periodLabel,
  entries:      entriesInit,
})

const rowsWithIdx      = props.rows.map((row, i) => ({ ...row, _idx: i }))
const financialRows    = computed(() => rowsWithIdx.filter(r => r.category === 'financial'))
const nonFinancialRows = computed(() => rowsWithIdx.filter(r => r.category === 'non_financial'))

const quarterOptions = computed(() => {
  const opts = []
  const currentYear = new Date().getFullYear()
  const from = currentYear - 3
  const to   = currentYear + 2
  for (let y = from; y <= to; y++)
    for (let q = 1; q <= 4; q++)
      opts.push(`${y}-Q${q}`)
  return opts
})

function onPeriodChange() {
  router.get(route('kpi.entry', props.company.id), {
    period_type:  form.period_type,
    period_label: form.period_label,
  })
}

function submit() {
  form.post(route('kpi.save-entry', props.company.id))
}

function formatValue(val, unit) {
  if (val === null || val === undefined || val === '') return '—'
  if (unit === 'currency')   return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(val)
  if (unit === 'percentage') return val.toFixed(1) + '%'
  if (unit === 'ratio')      return val.toFixed(2) + 'x'
  return new Intl.NumberFormat('en-US').format(val)
}
</script> -->