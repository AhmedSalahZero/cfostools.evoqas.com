<template>
  <Head :title="`Financial Studies — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ── PAGE HEADER ── -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <Link
            :href="`/portfolio-companies`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-4 w-fit"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Portfolio Companies
          </Link>
          <div class="flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3 mb-1">
                <span class="w-9 h-9 rounded-lg bg-mp-gold-dark flex items-center justify-center text-sm font-bold flex-shrink-0">
                  {{ company.name.charAt(0).toUpperCase() }}
                </span>
                <div>
                  <p class="text-xs text-white uppercase tracking-widest font-semibold">{{ company.name }}</p>
                  <h1 class="text-2xl font-bold text-white leading-tight">Financial Studies</h1>
                </div>
              </div>
              <p class="text-white text-sm mt-1 ml-12">
                {{ studies.length }} {{ studies.length === 1 ? 'study' : 'studies' }} created
              </p>
            </div>
            <Link
              :href="`/portfolio-companies/${company.id}/financial-studies/create`"
              class="flex items-center gap-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              New Study
            </Link>
          </div>
        </div>
      </div>

      <!-- ── MAIN CONTENT ── -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success"
          class="mb-6 bg-mp-success/60 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          {{ $page.props.flash.success }}
        </div>

        <!-- Empty state -->
        <div v-if="studies.length === 0"
          class="bg-mp-card rounded-xl border border-mp-border border-dashed p-16 text-center">
          <div class="w-16 h-16 bg-mp-gold/50 rounded-2xl flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <p class="text-white font-semibold text-lg mb-1">No studies yet</p>
          <p class="text-white text-sm mb-6 max-w-sm mx-auto">
            Create your first financial feasibility study for {{ company.name }} to project revenues, costs, and returns.
          </p>
          <Link
            :href="`/portfolio-companies/${company.id}/financial-studies/create`"
            class="inline-flex items-center gap-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Create First Study
          </Link>
        </div>

        <!-- Studies grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
          <div
            v-for="study in studies"
            :key="study.id"
            class="bg-mp-card border border-mp-border rounded-xl overflow-hidden hover:border-mp-border transition-colors group"
          >
            <!-- Card header -->
            <div class="p-5 border-b border-mp-border">
              <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span :class="[
                      'text-xs font-semibold px-2 py-0.5 rounded-full uppercase tracking-wide',
                      businessTypeBadge(study.business_type)
                    ]">
                      {{ study.business_type }}
                    </span>
                    <span class="text-xs text-white">{{ study.duration_years }}Y</span>
                  </div>
                  <h3 class="font-semibold text-white text-base leading-tight truncate">
                    {{ study.name }}
                  </h3>
                </div>
                <span class="text-xs font-mono text-white bg-mp-card-hover px-2 py-1 rounded flex-shrink-0">
                  {{ study.study_currency }}
                </span>
              </div>
            </div>

            <!-- Card body -->
            <div class="p-5">
              <div class="grid grid-cols-2 gap-3 text-xs mb-4">
                <div>
                  <p class="text-white uppercase tracking-widest font-semibold mb-1">Start</p>
                  <p class="text-white">{{ formatDate(study.study_start_date) }}</p>
                </div>
                <div>
                  <p class="text-white uppercase tracking-widest font-semibold mb-1">End</p>
                  <p class="text-white">{{ formatDate(study.study_end_date) }}</p>
                </div>
                <div v-if="study.business_sector">
                  <p class="text-white uppercase tracking-widest font-semibold mb-1">Sector</p>
                  <p class="text-white truncate">{{ study.business_sector }}</p>
                </div>
                <div>
                  <p class="text-white uppercase tracking-widests font-semibold mb-1">Created</p>
                  <p class="text-white">{{ formatDate(study.created_at) }}</p>
                </div>
              </div>

              <!-- Step progress bar (real, dynamic) -->
              <div class="mb-4">
                <div class="flex items-center justify-between mb-1.5">
                  <p class="text-xs text-white">Completion</p>
                  <p class="text-xs font-medium" :class="completedSteps(study) === 8 ? 'text-mp-success' : 'text-white'">
                    {{ completedSteps(study) }} / 8 steps
                  </p>
                </div>
                <!-- 8 individual segment dots -->
                <div class="flex gap-1">
                  <div
                    v-for="(step, i) in stepFlags(study)"
                    :key="i"
                    :title="step.label"
                    :class="[
                      'flex-1 h-2 rounded-full transition-colors',
                      step.done ? 'bg-mp-success' : 'bg-mp-card-hover'
                    ]"
                  ></div>
                </div>
                <p v-if="completedSteps(study) === 8"
                  class="text-xs text-mp-success mt-1.5 font-medium">
                  ✓ All steps complete
                </p>
                <p v-else class="text-xs text-white mt-1.5">
                  Next: {{ nextStep(study) }}
                </p>
              </div>

              <!-- Action buttons -->
              <div class="flex items-center gap-2">

                <!-- Edit Setup -->
                <Link
                  :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/edit`"
                  class="flex items-center justify-center gap-1.5 bg-mp-card-hover hover:bg-mp-page text-white hover:text-white text-xs font-medium px-3 py-2 rounded-lg transition-colors"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  Edit
                </Link>

                <!-- View Report -->
                <Link
                  :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/report`"
                  class="flex items-center justify-center gap-1.5 bg-mp-teal-subtle/40 hover:bg-mp-teal-subtle/60 text-white hover:text-white text-xs font-medium px-3 py-2 rounded-lg transition-colors border border-mp-teal/40"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  Report
                </Link>

                <!-- Continue wizard -->
                <Link
                  :href="continueLink(study)"
                  class="flex-1 flex items-center justify-center gap-1.5 bg-mp-gold/30 hover:bg-mp-gold/60 text-white text-xs font-medium px-3 py-2 rounded-lg transition-colors border border-mp-gold/40"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                  {{ completedSteps(study) === 8 ? 'Results' : 'Continue →' }}
                </Link>

                <!-- Delete -->
                <button
                  type="button"
                  @click="confirmDelete(study)"
                  class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger/50 text-white hover:text-mp-danger transition-colors"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>

              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ── DELETE MODAL ── -->
    <Teleport to="body">
      <div v-if="deleteModal.show"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        @click.self="deleteModal.show = false">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-md bg-mp-card border border-mp-danger/50 rounded-2xl shadow-2xl p-6">
          <div class="flex items-center justify-center w-12 h-12 rounded-full bg-mp-danger/40 border border-mp-danger/50 mx-auto mb-4">
            <svg class="w-6 h-6 text-mp-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="text-lg font-bold text-white text-center mb-1">Delete Study</h3>
          <p class="text-white text-sm text-center mb-1">You are about to permanently delete</p>
          <p class="text-mp-danger font-semibold text-center text-base mb-5">{{ deleteModal.study?.name }}</p>
          <p class="text-white text-xs mb-2">Type <span class="text-white font-mono font-bold">DELETE</span> to confirm</p>
          <input
            v-model="deleteModal.confirmation"
            type="text"
            placeholder="Type DELETE here..."
            class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-mp-danger placeholder-gray-600"
          />
          <div class="flex gap-3">
            <button
              @click="deleteModal.show = false; deleteModal.confirmation = ''"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors"
            >Cancel</button>
            <button
              @click="executeDelete"
              :disabled="deleteModal.confirmation !== 'DELETE' || deleteModal.loading"
              :class="[
                'flex-1 px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors',
                deleteModal.confirmation === 'DELETE' && !deleteModal.loading
                  ? 'bg-mp-danger hover:bg-mp-danger text-white'
                  : 'bg-mp-page text-white cursor-not-allowed'
              ]"
            >
              <span v-if="deleteModal.loading">Deleting…</span>
              <span v-else>Yes, Delete</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company: { type: Object, required: true },
  studies: { type: Array,  default: () => [] },
})

// ── Step definitions (8 steps) ────────────────────────────────────────────
const STEPS = [
  { label: 'Setup',           flag: null,                 route: 'edit'            },
  { label: 'Sales',           flag: 'has_sales',          route: 'sales'           },
  { label: 'COGS',            flag: 'has_cogs',           route: 'cogs'            },
  { label: 'Manpower',        flag: 'has_manpower',       route: 'manpower'        },
  { label: 'Expenses',        flag: 'has_expenses',       route: 'expenses'        },
  { label: 'Fixed Assets',    flag: 'has_fixed_assets',   route: 'fixed-assets'    },
  { label: 'Opening Balance', flag: 'has_opening_balance',route: 'opening-balance' },
  { label: 'Results',         flag: 'has_results',        route: 'results'         },
]

// Return array of { label, done } for the 8 step segments
function stepFlags(study) {
  return STEPS.map(s => ({
    label: s.label,
    // Step 1 (Setup) is always done once the study exists
    done: s.flag === null ? true : !!study[s.flag],
  }))
}

function completedSteps(study) {
  return stepFlags(study).filter(s => s.done).length
}

// Link to the first incomplete step, or Results if all done
function continueLink(study) {
  const base = `/portfolio-companies/${props.company.id}/financial-studies/${study.id}`
  const flags = stepFlags(study)
  const firstIncomplete = flags.findIndex(s => !s.done)
  if (firstIncomplete === -1) return `${base}/results`
  const step = STEPS[firstIncomplete]
  return step.route === 'edit' ? `${base}/edit` : `${base}/${step.route}`
}

// Label for the next incomplete step
function nextStep(study) {
  const flags = stepFlags(study)
  const first = flags.findIndex(s => !s.done)
  return first === -1 ? 'Complete ✓' : `Step ${first + 1}: ${STEPS[first].label}`
}

// ── Delete modal ──────────────────────────────────────────────────────────
const deleteModal = reactive({
  show: false, study: null, confirmation: '', loading: false,
})

function confirmDelete(study) {
  deleteModal.study        = study
  deleteModal.confirmation = ''
  deleteModal.loading      = false
  deleteModal.show         = true
}

function executeDelete() {
  if (deleteModal.confirmation !== 'DELETE' || deleteModal.loading) return
  deleteModal.loading = true
  router.delete(
    `/portfolio-companies/${props.company.id}/financial-studies/${deleteModal.study.id}`,
    {
      onSuccess: () => { deleteModal.show = false; deleteModal.loading = false },
      onError:   () => { deleteModal.loading = false; alert('Something went wrong.') },
    }
  )
}

// ── Helpers ───────────────────────────────────────────────────────────────
function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short' })
}

function businessTypeBadge(type) {
  const map = {
    manufacturing: 'bg-mp-teal-subtle/60 text-white border border-mp-teal/50',
    trading:       'bg-mp-gold/60 text-white border border-mp-gold/50',
    service:       'bg-mp-teal-subtle/60 text-white border border-mp-teal/50',
    mixed:         'bg-mp-gold/60 text-white border border-mp-gold/50',
  }
  return map[type] || 'bg-mp-card-hover text-white'
}
</script>