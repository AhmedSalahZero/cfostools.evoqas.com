<template>
  <Head :title="`Assumptions — ${model.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- Header -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-5xl mx-auto px-6 py-5">
          <Link
            :href="`/portfolio-companies/${company.id}/financial-planning`"
            class="text-white hover:text-white flex items-center gap-2 mb-3 text-sm"
          >
            ← Back to Models
          </Link>
          <div class="flex justify-between items-center">
            <div>
              <div class="flex items-center gap-3 mb-1">
                <h1 class="text-2xl font-bold">{{ model.name }}</h1>
                <span class="text-xs bg-mp-warning/50 text-mp-warning border border-mp-warning/50 px-2 py-0.5 rounded-full">
                  Complex Model
                </span>
              </div>
              <p class="text-white text-sm">
                Edit the input assumptions below, then download the updated Excel to run your full model.
              </p>
            </div>
            <div v-if="assumptions.length === 0" class="p-6 bg-mp-warning border border-mp-warning rounded">
              <p class="text-mp-warning">
                No assumption inputs were found in the spreadsheet.
              </p>
              <p class="text-sm text-mp-warning mt-2">
                Expected sheet containing "assumption" in name.<br>
                Current sheet used: <strong>{{ fallbackSheetName || 'First sheet' }}</strong><br>
                Make sure your sheet has rows with "Input→" or "Input" markers in columns C or D.
              </p>
            </div>
            <div class="flex items-center gap-3">
              <a
                :href="`/portfolio-companies/${company.id}/financial-planning/${model.id}/download`"
                class="inline-flex items-center gap-2 bg-mp-page hover:bg-mp-muted px-4 py-2.5 rounded-lg text-sm font-medium transition-colors"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download Excel
              </a>
              <button
                @click="saveChanges"
                :disabled="saving || !hasChanges"
                class="inline-flex items-center gap-2 bg-mp-warning hover:bg-mp-warning px-5 py-2.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-4-4v8m0 0l3-3m-3 3l-3-3"/>
                </svg>
                {{ saving ? 'Saving...' : hasChanges ? `Save ${changeCount} Change${changeCount !== 1 ? 's' : ''}` : 'No Changes' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Flash message -->
      <div v-if="flashMsg" class="max-w-5xl mx-auto px-6 pt-4">
        <div
          :class="flashMsg.type === 'success'
            ? 'bg-mp-success/40 border-mp-success text-mp-success'
            : 'bg-mp-danger/40 border-mp-danger text-mp-danger'"
          class="border rounded-lg px-4 py-3 text-sm flex items-start gap-2"
        >
          <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          {{ flashMsg.text }}
        </div>
      </div>

      <!-- No assumptions found -->
      <div v-if="assumptions.length === 0" class="max-w-5xl mx-auto px-6 py-16 text-center">
        <div class="bg-mp-card rounded-xl border border-mp-border py-16">
          <svg class="w-12 h-12 text-white mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p class="text-white text-lg mb-2">No input assumptions found</p>
          <p class="text-white text-sm">
            The system looks for a sheet named <span class="text-white font-mono">Assumption_Sheet</span>
            with rows where column D contains "Input→".
            Make sure your Excel file follows this structure.
          </p>
        </div>
      </div>

      <!-- Assumption sections -->
      <div v-else class="max-w-5xl mx-auto px-6 py-8 space-y-6">

        <!-- Changed indicator bar -->
        <div v-if="hasChanges"
          class="bg-mp-warning/20 border border-mp-warning/50 rounded-xl px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-2 text-mp-warning text-sm">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd"
                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                clip-rule="evenodd"/>
            </svg>
            You have <strong>{{ changeCount }} unsaved change{{ changeCount !== 1 ? 's' : '' }}</strong>
          </div>
          <button @click="resetChanges" class="text-mp-warning hover:text-mp-warning text-sm underline">
            Reset all
          </button>
        </div>

        <!-- Each section -->
        <div
          v-for="section in assumptions"
          :key="section.title"
          class="bg-mp-card rounded-xl border border-mp-border overflow-hidden"
        >
          <!-- Section header -->
          <div class="bg-mp-card-hover/60 px-6 py-3 border-b border-mp-border">
            <h3 class="text-sm font-semibold text-white uppercase tracking-widest">
              {{ section.title }}
            </h3>
          </div>

          <!-- Items grid -->
          <div class="divide-y divide-gray-800/50">
            <div
              v-for="item in section.items"
              :key="`${item.row}-${item.col}`"
              class="px-6 py-4"
              :class="isChanged(item) ? 'bg-mp-warning/10' : ''"
            >

              <!-- Single-value input -->
              <div v-if="!item.multi_year" class="flex items-center gap-4">
                <div class="flex-1 min-w-0">
                  <label class="block text-sm font-medium text-white">
                    {{ item.label }}
                    <span v-if="isChanged(item)" class="ml-2 text-xs text-mp-warning">● modified</span>
                  </label>
                  <p v-if="item.unit" class="text-xs text-white mt-0.5">{{ item.unit }}</p>
                </div>

                <div class="w-56 shrink-0">
                  <!-- Date input -->
                  <input
                    v-if="item.type === 'date'"
                    type="date"
                    :value="formatDateForInput(getVal(item))"
                    @change="onChange(item, $event.target.value)"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mp-warning transition-colors"
                    :class="isChanged(item) ? 'border-mp-warning' : ''"
                  />
                  <!-- Percent input -->
                  <div v-else-if="item.type === 'percent'" class="relative">
                    <input
                      type="number"
                      step="0.01"
                      :value="toPercent(getVal(item))"
                      @change="onChange(item, $event.target.value)"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 pr-8 text-white text-sm focus:outline-none focus:border-mp-warning transition-colors"
                      :class="isChanged(item) ? 'border-mp-warning' : ''"
                    />
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-white text-sm">%</span>
                  </div>
                  <!-- Number input -->
                  <input
                    v-else-if="item.type === 'number'"
                    type="number"
                    :value="getVal(item)"
                    @change="onChange(item, $event.target.value)"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mp-warning transition-colors"
                    :class="isChanged(item) ? 'border-mp-warning' : ''"
                  />
                  <!-- Text input -->
                  <input
                    v-else
                    type="text"
                    :value="getVal(item)"
                    @change="onChange(item, $event.target.value)"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mp-warning transition-colors"
                    :class="isChanged(item) ? 'border-mp-warning' : ''"
                  />
                </div>
              </div>

              <!-- Multi-year input (Year 1, Year 2, ...) -->
              <div v-else>
                <div class="flex items-center gap-2 mb-3">
                  <label class="text-sm font-medium text-white">{{ item.label }}</label>
                  <span v-if="isChanged(item)" class="text-xs text-mp-warning">● modified</span>
                </div>
                <div class="grid gap-3"
                  :style="`grid-template-columns: repeat(${item.year_values.length}, minmax(0, 1fr))`">
                  <div
                    v-for="(yv, idx) in item.year_values"
                    :key="yv.col"
                  >
                    <p class="text-xs text-white mb-1 text-center">Year {{ idx + 1 }}</p>
                    <div class="relative">
                      <input
                        type="number"
                        step="0.001"
                        :value="item.type === 'percent' ? toPercent(getYearVal(item, yv.col, yv.value)) : getYearVal(item, yv.col, yv.value)"
                        @change="onYearChange(item, yv.col, $event.target.value)"
                        class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mp-warning transition-colors text-center"
                        :class="isYearChanged(item, yv.col) ? 'border-mp-warning' : ''"
                      />
                      <span v-if="item.type === 'percent'"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-white text-xs">%</span>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Bottom save button -->
        <div class="flex justify-end pt-2 pb-8">
          <button
            @click="saveChanges"
            :disabled="saving || !hasChanges"
            class="inline-flex items-center gap-2 bg-mp-warning hover:bg-mp-warning px-8 py-3 rounded-xl font-semibold transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
          >
            <svg v-if="!saving" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-4-4v8m0 0l3-3m-3 3l-3-3"/>
            </svg>
            {{ saving ? 'Saving...' : 'Save Assumptions & Update Excel' }}
          </button>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:     Object,
  model:       Object,
  assumptions: Array,   // [{ title, items: [{ row, col, label, unit, value, type, multi_year, year_values }] }]
})

// ── State ─────────────────────────────────────────────────────────────────
const saving   = ref(false)
const flashMsg = ref(null)
let   flashTimer = null

// changes = { "row_col": { row, col, value, unit }, ... }
const changes = ref({})

// ── Flash from Inertia ─────────────────────────────────────────────────────
const page = usePage()
onMounted(() => {
  if (page.props.flash?.success) showFlash('success', page.props.flash.success)
  if (page.props.flash?.error)   showFlash('error',   page.props.flash.error)
})
onBeforeUnmount(() => { if (flashTimer) clearTimeout(flashTimer) })

// ── Computed ───────────────────────────────────────────────────────────────
const hasChanges  = computed(() => Object.keys(changes.value).length > 0)
const changeCount = computed(() => Object.keys(changes.value).length)

// ── Helpers ────────────────────────────────────────────────────────────────
function changeKey(row, col) {
  return `${row}_${col}`
}

function getVal(item) {
  const key = changeKey(item.row, item.col)
  return changes.value[key]?.value ?? item.value
}

function getYearVal(item, col, originalValue) {
  const key = changeKey(item.row, col)
  return changes.value[key]?.value ?? originalValue
}

function isChanged(item) {
  if (item.multi_year) {
    return item.year_values.some(yv => changeKey(item.row, yv.col) in changes.value)
  }
  return changeKey(item.row, item.col) in changes.value
}

function isYearChanged(item, col) {
  return changeKey(item.row, col) in changes.value
}

function toPercent(val) {
  if (val === null || val === undefined || val === '') return ''
  const n = parseFloat(val)
  return isNaN(n) ? val : +(n * 100).toFixed(4)
}

function formatDateForInput(val) {
  if (!val) return ''
  // val might be a date string like "2025-01-01 00:00:00"
  return String(val).substring(0, 10)
}

// ── onChange handlers ──────────────────────────────────────────────────────
function onChange(item, newVal) {
  const key = changeKey(item.row, item.col)
  // If value is back to original, remove from changes
  if (String(newVal) === String(item.value) ||
      (item.type === 'percent' && String(toPercent(item.value)) === String(newVal))) {
    delete changes.value[key]
    changes.value = { ...changes.value }
    return
  }
  changes.value = {
    ...changes.value,
    [key]: { row: item.row, col: item.col, value: newVal, unit: item.unit },
  }
}

function onYearChange(item, col, newVal) {
  const key = changeKey(item.row, col)
  const originalYv = item.year_values.find(y => y.col === col)
  const origVal = originalYv?.value

  if (String(newVal) === String(origVal) ||
      (item.type === 'percent' && String(toPercent(origVal)) === String(newVal))) {
    delete changes.value[key]
    changes.value = { ...changes.value }
    return
  }
  changes.value = {
    ...changes.value,
    [key]: { row: item.row, col: col, value: newVal, unit: item.unit },
  }
}

function resetChanges() {
  changes.value = {}
}

// ── Save ───────────────────────────────────────────────────────────────────
function saveChanges() {
  if (!hasChanges.value) return
  saving.value = true

  const payload = Object.values(changes.value)

  router.post(
    route('financial-planning.save-assumptions', {
      company: props.company.id,
      model:   props.model.id,
    }),
    { changes: payload },
    {
      preserveScroll: true,
      onSuccess: () => {
        showFlash('success', 'Assumptions saved successfully. Download the updated Excel to run your full model.')
        changes.value = {}
      },
      onError: () => {
        showFlash('error', 'Save failed. Please try again.')
      },
      onFinish: () => {
        saving.value = false
      },
    }
  )
}

// ── Flash helper ───────────────────────────────────────────────────────────
function showFlash(type, text) {
  flashMsg.value = { type, text }
  if (flashTimer) clearTimeout(flashTimer)
  flashTimer = setTimeout(() => { flashMsg.value = null }, 7000)
}
</script>