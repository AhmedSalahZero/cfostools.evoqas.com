<template>
  <Head :title="`Financial Statements — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <Link :href="`/portfolio-companies`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Portfolio Companies
          </Link>
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-bold text-white">📄 Financial Statements</h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }}</p>
            </div>
            <div class="flex items-center gap-3">
              <Link v-if="statements.length >= 1"
                :href="`/portfolio-companies/${company.id}/cash-forecast`"
                class="flex items-center gap-2 bg-mp-teal-dark hover:bg-mp-teal text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                💧 Cash Forecast
              </Link>
              <Link v-if="statements.length >= 2"
                :href="`/portfolio-companies/${company.id}/financial-statements/compare?mode=monthly`"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                📊 Multi-Period View
              </Link>
              <button v-if="selected.length >= 2"
                @click="goCompare"
                class="flex items-center gap-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Compare {{ selected.length }} Selected
              </button>
              <Link :href="`/portfolio-companies/${company.id}/financial-statements/create`"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Statement
              </Link>
              <Link :href="`/portfolio-companies/${company.id}/financial-statements/upload`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page text-white hover:text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Upload Excel
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- CONTENT -->
      <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success"
          class="mb-6 bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <!-- Compare hint -->
        <div v-if="statements.length >= 2 && selected.length < 2"
          class="mb-4 bg-mp-gold/30 border border-mp-gold/50 text-white px-4 py-3 rounded-lg text-sm flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          Tip: Tick the checkboxes on 2 or more statements to enable the multi-period comparison view.
        </div>

        <!-- Empty state -->
        <div v-if="statements.length === 0"
          class="bg-mp-card rounded-xl border border-mp-border border-dashed p-16 text-center">
          <div class="w-14 h-14 bg-mp-success/50 rounded-xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-mp-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <p class="text-white font-medium mb-1">No financial statements yet</p>
          <p class="text-white text-sm mb-5">Add the first income statement, balance sheet and cash flow for {{ company.name }}</p>
          <Link :href="`/portfolio-companies/${company.id}/financial-statements/create`"
            class="bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors inline-block">
            + New Statement
          </Link>
        </div>

        <!-- Year-Grouped Table -->
        <div v-else class="space-y-4">
          <template v-for="(group, year) in statementsByYear" :key="year">
            <div class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">

              <!-- Year Header Row (Father) -->
              <div
                class="flex items-center justify-between px-5 py-3 bg-mp-card-hover/70 border-b border-mp-border cursor-pointer select-none"
                @click="toggleYear(year)">
                <div class="flex items-center gap-3">
                  <!-- Chevron -->
                  <svg
                    class="w-4 h-4 text-white transition-transform duration-200"
                    :class="expandedYears[year] !== false ? 'rotate-90' : 'rotate-0'"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                  <!-- Year Badge -->
                  <span class="text-xs font-bold text-white uppercase tracking-widest bg-mp-teal-subtle/50 border border-mp-teal/60 px-3 py-1 rounded-full">
                    {{ year }}
                  </span>
                  <span class="text-xs text-white">{{ group.length }} statement{{ group.length !== 1 ? 's' : '' }}</span>
                </div>
                <!-- Year-level stats -->
                <div class="flex items-center gap-4 text-xs text-white">
                  <span v-if="group.filter(s => s.status === 'final').length > 0"
                    class="text-mp-success font-medium">
                    {{ group.filter(s => s.status === 'final').length }} Final
                  </span>
                  <span v-if="group.filter(s => s.status === 'draft').length > 0"
                    class="text-mp-warning font-medium">
                    {{ group.filter(s => s.status === 'draft').length }} Draft
                  </span>
                </div>
              </div>

              <!-- Children rows -->
              <div v-show="expandedYears[year] !== false">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-mp-border/60">
                      <th class="px-4 py-3 w-10">
                        <span class="text-xs text-white uppercase tracking-widest">Cmp</span>
                      </th>
                      <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Period</th>
                      <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Currency</th>
                      <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Status</th>
                      <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Created</th>
                      <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-800/60">
                    <template v-for="stmt in group" :key="stmt.id">
                      <tr class="hover:bg-mp-card-hover/40 transition-colors">

                        <!-- Checkbox -->
                        <td class="px-4 py-4 text-center">
                          <input type="checkbox"
                            :value="stmt.id"
                            v-model="selected"
                            class="w-4 h-4 rounded border-mp-border bg-mp-page text-white focus:ring-mp-gold cursor-pointer"/>
                        </td>

                        <!-- Period -->
                        <td class="px-6 py-4">
                          <div class="font-semibold text-white">{{ formatDate(stmt.period_from) }}</div>
                          <div class="text-s text-white mt-0.5">to {{ formatDate(stmt.period_to) }}</div>
                        </td>

                        <!-- Currency -->
                        <td class="px-6 py-4 text-white font-medium">{{ stmt.currency }}</td>

                        <!-- Status -->
                        <td class="px-6 py-4">
                          <span :class="stmt.status === 'final'
                            ? 'bg-mp-success/15 text-mp-success border border-mp-success'
                            : 'bg-mp-warning/15 text-mp-warning border border-mp-warning'"
                            class="text-xs font-semibold px-2.5 py-1 rounded-full uppercase tracking-wide">
                            {{ stmt.status === 'final' ? '✓ Final' : '✏ Draft' }}
                          </span>
                        </td>

                        <!-- Created -->
                        <td class="px-6 py-4 text-white text-xs">{{ stmt.created_at }}</td>

                        <!-- Actions -->
                        <td class="px-6 py-4">
                          <div class="flex items-center justify-center gap-2">
                            <!-- View -->
                            <Link :href="`/portfolio-companies/${company.id}/financial-statements/${stmt.id}`"
                              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-success text-white hover:text-white transition-colors"
                              title="View & Analysis">
                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                              </svg>
                            </Link>
                            <!-- Export -->
                            <a :href="`/portfolio-companies/${company.id}/financial-statements/${stmt.id}/export`"
                              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-success text-white hover:text-white transition-colors"
                              title="Export to Excel">
                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                              </svg>
                            </a>
                            <!-- Copy -->
                            <button @click="confirmCopy(stmt)"
                              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-gold-dark text-white hover:text-white transition-colors"
                              title="Copy Statement">
                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                              </svg>
                            </button>
                            <!-- Edit -->
                            <Link :href="`/portfolio-companies/${company.id}/financial-statements/${stmt.id}/edit`"
                              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-teal text-white hover:text-white transition-colors"
                              title="Edit">
                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                              </svg>
                            </Link>
                            <!-- Delete -->
                            <button @click="confirmDelete(stmt)"
                              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors"
                              title="Delete">
                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                              </svg>
                            </button>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>

            </div>
          </template>
        </div>

      </div>
    </div>

    <!-- ── Delete Confirm Modal ── -->
    <div v-if="deleteTarget"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
      <div class="bg-mp-card border border-mp-border rounded-2xl p-6 w-full max-w-md mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 bg-mp-danger/50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-mp-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <h3 class="text-lg font-bold text-white">Delete Statement?</h3>
        </div>
        <p class="text-white text-sm mb-6">
          This will permanently delete the statement for
          <span class="text-white font-semibold">{{ formatDate(deleteTarget.period_from) }} — {{ formatDate(deleteTarget.period_to) }}</span>
          including all line items and calculated ratios. This cannot be undone.
        </p>
        <div class="flex gap-3 justify-end">
          <button @click="deleteTarget = null"
            class="px-4 py-2 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
            Cancel
          </button>
          <button @click="doDelete"
            class="px-4 py-2 rounded-lg bg-mp-danger hover:bg-mp-danger text-white text-sm font-medium transition-colors">
            Yes, Delete
          </button>
        </div>
      </div>
    </div>

    <!-- ── Copy Confirm Modal ── -->
    <div v-if="copyTarget"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
      <div class="bg-mp-card border border-mp-border rounded-2xl p-6 w-full max-w-md mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 bg-mp-gold/50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
          </div>
          <h3 class="text-lg font-bold text-white">Copy Statement</h3>
        </div>
        <p class="text-white text-sm mb-5">
          A copy of
          <span class="text-white font-semibold">{{ formatDate(copyTarget.period_from) }} — {{ formatDate(copyTarget.period_to) }}</span>
          will be created as a <span class="text-mp-warning font-semibold">Draft</span> with all the same line items and structure.
          You can then edit the dates and amounts.
        </p>

        <!-- New period inputs -->
        <div class="grid grid-cols-2 gap-3 mb-5">
          <div>
            <label class="text-xs text-white uppercase tracking-widest font-semibold block mb-1">New Period From</label>
            <input type="date" v-model="copyForm.period_from"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white focus:border-mp-teal focus:outline-none"/>
          </div>
          <div>
            <label class="text-xs text-white uppercase tracking-widest font-semibold block mb-1">New Period To</label>
            <input type="date" v-model="copyForm.period_to"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white focus:border-mp-teal focus:outline-none"/>
          </div>
        </div>

        <!-- Copy amounts toggle -->
        <div class="flex items-center gap-3 mb-5 bg-mp-card-hover/60 rounded-lg px-3 py-2.5">
          <input type="checkbox" id="copyAmounts" v-model="copyForm.copy_amounts"
            class="w-4 h-4 rounded border-mp-border bg-mp-page text-white focus:ring-mp-gold"/>
          <label for="copyAmounts" class="text-sm text-white cursor-pointer select-none">
            Also copy the amounts <span class="text-white text-xs">(uncheck to copy structure only, with all amounts set to 0)</span>
          </label>
        </div>

        <div v-if="copyError" class="text-mp-danger text-xs mb-3">{{ copyError }}</div>

        <div class="flex gap-3 justify-end">
          <button @click="copyTarget = null; copyError = null"
            class="px-4 py-2 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
            Cancel
          </button>
          <button @click="doCopy" :disabled="copyLoading"
            class="px-4 py-2 rounded-lg bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
            <svg v-if="copyLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            {{ copyLoading ? 'Copying…' : '📋 Create Copy' }}
          </button>
        </div>
      </div>
    </div>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:    Object,
  statements: Array,
})

const selected     = ref([])
const deleteTarget = ref(null)
const copyTarget   = ref(null)
const copyError    = ref(null)
const copyLoading  = ref(false)

const copyForm = reactive({
  period_from:  '',
  period_to:    '',
  copy_amounts: true,
})

// ── Group statements by the YEAR of period_to ──────────────────────────────
const statementsByYear = computed(() => {
  const groups = {}
  ;[...props.statements]
    .sort((a, b) => new Date(b.period_to) - new Date(a.period_to))
    .forEach(stmt => {
      const year = new Date(stmt.period_to).getFullYear()
      if (!groups[year]) groups[year] = []
      groups[year].push(stmt)
    })
  // Sort years descending
  return Object.fromEntries(
    Object.entries(groups).sort(([a], [b]) => Number(b) - Number(a))
  )
})

// ── Expand/collapse years — all expanded by default ────────────────────────
const expandedYears = reactive({})

function toggleYear(year) {
  // If undefined → was expanded (default), now collapse
  expandedYears[year] = expandedYears[year] === false ? true : false
}

// ── Date helpers ───────────────────────────────────────────────────────────
function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

// ── Delete ─────────────────────────────────────────────────────────────────
function confirmDelete(stmt) {
  deleteTarget.value = stmt
}

function doDelete() {
  router.delete(
    `/portfolio-companies/${props.company.id}/financial-statements/${deleteTarget.value.id}`,
    { onFinish: () => { deleteTarget.value = null } }
  )
}

// ── Copy ───────────────────────────────────────────────────────────────────
function confirmCopy(stmt) {
  copyTarget.value = stmt
  copyError.value  = null
  copyForm.copy_amounts = true

  // Pre-fill dates: suggest next month after the source statement's period_to
  const srcTo  = new Date(stmt.period_to)
  const nextFrom = new Date(srcTo)
  nextFrom.setDate(nextFrom.getDate() + 1)
  const nextTo = new Date(nextFrom)
  nextTo.setMonth(nextTo.getMonth() + 1)
  nextTo.setDate(nextTo.getDate() - 1)

  copyForm.period_from = nextFrom.toISOString().slice(0, 10)
  copyForm.period_to   = nextTo.toISOString().slice(0, 10)
}

function doCopy() {
  if (!copyForm.period_from || !copyForm.period_to) {
    copyError.value = 'Please enter both period dates.'
    return
  }
  if (copyForm.period_from >= copyForm.period_to) {
    copyError.value = 'Period To must be after Period From.'
    return
  }

  copyLoading.value = true
  copyError.value   = null

  router.post(
    `/portfolio-companies/${props.company.id}/financial-statements/${copyTarget.value.id}/copy`,
    {
      period_from:  copyForm.period_from,
      period_to:    copyForm.period_to,
      copy_amounts: copyForm.copy_amounts,
    },
    {
      onSuccess: () => {
        copyTarget.value  = null
        copyLoading.value = false
      },
      onError: (errors) => {
        copyError.value   = errors.period_from || errors.period_to || 'An error occurred.'
        copyLoading.value = false
      },
      onFinish: () => {
        copyLoading.value = false
      },
    }
  )
}

function goCompare() {
  const ids = selected.value.map(id => `ids[]=${id}`).join('&')
  router.visit(`/portfolio-companies/${props.company.id}/financial-statements/compare?mode=custom&${ids}`)
}
</script>