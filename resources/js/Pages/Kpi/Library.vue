<template>
  <Head :title="`KPI Library — ${company.name}`" />
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
              <h1 class="text-2xl font-bold text-white">KPI Library</h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }} — Manage tracked KPIs</p>
            </div>
            <button @click="openAddModal"
              class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors w-fit">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Custom KPI
            </button>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success"
          class="bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          ✅ {{ $page.props.flash.success }}
        </div>

        <!-- Financial KPIs -->
        <div>
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">💰 Financial KPIs</p>
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">KPI Name</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Unit</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Source</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Direction</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Type</th>
                  <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Active</th>
                  <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="def in financialDefs" :key="def.id">
                  <tr :class="['hover:bg-mp-card-hover/50 transition-colors', !def.is_active && 'opacity-40']">
                    <td class="px-6 py-4">{{ def.name }}</td>
                    <td class="px-4 py-4">{{ formatUnit(def.unit) }}</td>
                    <td class="px-4 py-4">
                      <span :class="sourceBadge(def.source)" class="text-xs px-2 py-0.5 rounded-full font-medium">{{ sourceLabel(def.source) }}</span>
                    </td>
                    <td class="px-4 py-4">
                      <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                        :class="def.higher_is_better ? 'bg-mp-success/15 text-mp-success border border-mp-success' : 'bg-mp-danger/15 text-mp-danger border border-mp-danger'">
                        {{ def.higher_is_better ? '↑ Higher Better' : '↓ Lower Better' }}
                      </span>
                    </td>
                    <td class="px-4 py-4">{{ def.organization_id ? 'Custom' : 'Standard' }}</td>
                    <td class="px-4 py-4 text-center">
                      <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" :checked="def.is_active" class="sr-only peer"
                          @change="toggleActive(def)" />
                        <div class="w-11 h-6 bg-mp-page peer-focus:outline-none rounded-full peer
                          peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white
                          after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-mp-border
                          after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-mp-teal">
                        </div>
                      </label>
                    </td>
                    <td class="px-4 py-4 text-center">
                      <div class="flex justify-center gap-2">
                        <button v-if="def.organization_id" @click="openEditModal(def)" class="text-white hover:text-white">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                        </button>
                        <button v-if="def.organization_id" @click="confirmDelete(def)" class="text-mp-danger hover:text-mp-danger">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                        <span v-if="!def.organization_id" class="text-xs text-white italic">Standard</span>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Non-Financial KPIs -->
        <div>
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">📊 Non-Financial KPIs</p>
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">KPI Name</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Unit</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Source</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Direction</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Type</th>
                  <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Active</th>
                  <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-4 py-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="def in nonFinancialDefs" :key="def.id">
                  <tr :class="['hover:bg-mp-card-hover/50 transition-colors', !def.is_active && 'opacity-40']">
                    <td class="px-6 py-4">{{ def.name }}</td>
                    <td class="px-4 py-4">{{ formatUnit(def.unit) }}</td>
                    <td class="px-4 py-4">
                      <span :class="sourceBadge(def.source)" class="text-xs px-2 py-0.5 rounded-full font-medium">{{ sourceLabel(def.source) }}</span>
                    </td>
                    <td class="px-4 py-4">
                      <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                        :class="def.higher_is_better ? 'bg-mp-success/15 text-mp-success border border-mp-success' : 'bg-mp-danger/15 text-mp-danger border border-mp-danger'">
                        {{ def.higher_is_better ? '↑ Higher Better' : '↓ Lower Better' }}
                      </span>
                    </td>
                    <td class="px-4 py-4">{{ def.organization_id ? 'Custom' : 'Standard' }}</td>
                    <td class="px-4 py-4 text-center">
                      <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" :checked="def.is_active" class="sr-only peer"
                          @change="toggleActive(def)" />
                        <div class="w-11 h-6 bg-mp-page peer-focus:outline-none rounded-full peer
                          peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white
                          after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-mp-border
                          after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-mp-teal">
                        </div>
                      </label>
                    </td>
                    <td class="px-4 py-4 text-center">
                      <div class="flex justify-center gap-2">
                        <button v-if="def.organization_id" @click="openEditModal(def)" class="text-white hover:text-white">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                          </svg>
                        </button>
                        <button v-if="def.organization_id" @click="confirmDelete(def)" class="text-mp-danger hover:text-mp-danger">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                        <span v-if="!def.organization_id" class="text-xs text-white italic">Standard</span>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Add/Edit Modal -->
        <Teleport to="body">
          <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4">
            <div class="bg-mp-card rounded-xl p-6 w-full max-w-lg border border-mp-border">
              <h2 class="text-lg font-bold text-white mb-6">{{ isEditMode ? 'Edit KPI' : 'Add Custom KPI' }}</h2>
              <div class="space-y-4">
                <div>
                  <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1">Name</label>
                  <input v-model="kpiForm.name" type="text"
                    class="w-full bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal"
                    placeholder="e.g. Customer Acquisition Cost" />
                </div>
                <div>
                  <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1">Category</label>
                  <select v-model="kpiForm.category"
                    class="w-full bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal">
                    <option value="financial">Financial</option>
                    <option value="non_financial">Non-Financial</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1">Unit</label>
                  <select v-model="kpiForm.unit"
                    class="w-full bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal">
                    <option value="currency">Currency ($)</option>
                    <option value="number">Number</option>
                    <option value="percentage">Percentage (%)</option>
                    <option value="ratio">Ratio</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1">Direction</label>
                  <select v-model="kpiForm.higher_is_better"
                    class="w-full bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal">
                    <option :value="true">↑ Higher is Better (e.g. Revenue, Profit)</option>
                    <option :value="false">↓ Lower is Better (e.g. Costs, Churn)</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1">Description (Optional)</label>
                  <textarea v-model="kpiForm.description" rows="2"
                    class="w-full bg-mp-card-hover text-white rounded-lg px-3 py-2 text-sm border border-mp-border focus:outline-none focus:border-mp-teal"
                    placeholder="What does this KPI measure?"></textarea>
                </div>
              </div>

              <div class="flex justify-end gap-3 mt-6">
                <button @click="closeModal"
                  class="px-4 py-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white rounded-lg text-sm transition-colors">
                  Cancel
                </button>
                <button @click="submitKpi" :disabled="kpiForm.processing"
                  class="px-4 py-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 text-white rounded-lg text-sm font-semibold transition-colors">
                  {{ kpiForm.processing ? 'Saving...' : (isEditMode ? 'Update KPI' : 'Add KPI') }}
                </button>
              </div>
            </div>
          </div>
        </Teleport>

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
          <div v-if="showDeleteConfirm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4">
            <div class="bg-mp-card rounded-xl p-6 w-full max-w-md border border-mp-border">
              <h2 class="text-lg font-bold text-white mb-4">Confirm Delete</h2>
              <p class="text-white mb-6">Are you sure you want to delete "{{ selectedDef?.name }}"? This action cannot be undone.</p>
              <div class="flex justify-end gap-3">
                <button @click="showDeleteConfirm = false"
                  class="px-4 py-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white rounded-lg text-sm transition-colors">
                  Cancel
                </button>
                <button @click="deleteKpi" :disabled="deleting"
                  class="px-4 py-2 bg-mp-danger hover:bg-mp-danger disabled:opacity-50 text-white rounded-lg text-sm font-semibold transition-colors">
                  {{ deleting ? 'Deleting...' : 'Delete' }}
                </button>
              </div>
            </div>
          </div>
        </Teleport>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:     Object,
  definitions: Array,
})

const showModal        = ref(false)
const isEditMode       = ref(false)
const selectedDef      = ref(null)
const showDeleteConfirm = ref(false)
const deleting         = ref(false)
const toggling         = ref(null)   // tracks which def id is currently being toggled

const financialDefs    = computed(() => (props.definitions ?? []).filter(d => d.category === 'financial'))
const nonFinancialDefs = computed(() => (props.definitions ?? []).filter(d => d.category === 'non_financial'))

const kpiForm = useForm({
  name:             '',
  category:         'financial',
  unit:             'currency',
  higher_is_better: true,
  description:      '',
})

// ── Bug Fix 1: toggleActive was missing entirely — now implemented ─────────
function toggleActive(def) {
  if (toggling.value === def.id) return   // prevent double-clicks
  toggling.value = def.id

  router.patch(
    route('kpi.toggle-active', { company: props.company.id, definition: def.id }),
    {},
    {
      preserveScroll: true,
      onSuccess: () => { toggling.value = null },
      onError:   () => { toggling.value = null },
    }
  )
}

function openAddModal() {
  isEditMode.value = false
  kpiForm.reset()
  showModal.value = true
}

function openEditModal(def) {
  selectedDef.value = def
  isEditMode.value = true
  kpiForm.name             = def.name
  kpiForm.category         = def.category
  kpiForm.unit             = def.unit
  kpiForm.higher_is_better = def.higher_is_better
  kpiForm.description      = def.description ?? ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  kpiForm.reset()
  selectedDef.value = null
}

function submitKpi() {
  if (isEditMode.value) {
    kpiForm.patch(route('kpi.update-custom', { company: props.company.id, definition: selectedDef.value.id }), {
      onSuccess: () => closeModal()
    })
  } else {
    kpiForm.post(route('kpi.store-custom', props.company.id), {
      onSuccess: () => closeModal()
    })
  }
}

function confirmDelete(def) {
  selectedDef.value = def
  showDeleteConfirm.value = true
}

function deleteKpi() {
  deleting.value = true
  router.delete(route('kpi.delete-custom', { company: props.company.id, definition: selectedDef.value.id }), {
    onSuccess: () => {
      showDeleteConfirm.value = false
      deleting.value = false
      selectedDef.value = null
    },
    onError: () => { deleting.value = false },
  })
}

function formatUnit(unit) {
  if (!unit) return ''
  return unit.charAt(0).toUpperCase() + unit.slice(1)
}

function sourceBadge(source) {
  return source === 'auto_fs'
    ? 'bg-mp-teal-subtle text-white border border-mp-teal'
    : 'bg-mp-card-hover text-white border border-mp-border'
}

function sourceLabel(source) {
  return source === 'auto_fs' ? '⚡ Auto' : '✏️ Manual'
}
</script>