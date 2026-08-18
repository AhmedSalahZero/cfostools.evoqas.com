<template>
  <Head title="To Do List" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ══ PAGE HEADER ══ -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                ✅ To Do List
              </h1>
              <p class="text-white text-sm mt-1">
                {{ counts.total }} task{{ counts.total !== 1 ? 's' : '' }} ·
                <span v-if="counts.overdue > 0" class="text-mp-danger font-semibold">{{ counts.overdue }} overdue</span>
                <span v-if="counts.overdue > 0 && counts.due_today > 0"> · </span>
                <span v-if="counts.due_today > 0" class="text-white font-semibold">{{ counts.due_today }} due today</span>
              </p>
            </div>
            <button @click="openModal()"
              class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              New Task
            </button>
          </div>

          <!-- KPI CARDS -->
          <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-6">
            <div v-for="card in kpiCards" :key="card.label"
              @click="filterStatus = card.filter"
              :class="[
                'bg-mp-card-hover border rounded-xl p-4 cursor-pointer transition-all hover:border-mp-teal/60',
                filterStatus === card.filter ? 'border-mp-teal bg-mp-teal-subtle/30' : 'border-mp-border'
              ]">
              <p class="text-xs text-white uppercase tracking-widest">{{ card.label }}</p>
              <p class="text-2xl font-bold mt-1" :class="card.color">{{ card.value }}</p>
            </div>
          </div>

          <!-- FILTERS ROW -->
          <div class="flex flex-wrap items-center gap-2 mt-4">
            <!-- Status filter pills -->
            <div class="flex gap-1 bg-mp-card-hover rounded-lg p-1">
              <button v-for="f in statusFilters" :key="f.value" @click="filterStatus = f.value"
                :class="filterStatus === f.value ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
                class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors">
                {{ f.label }}
              </button>
            </div>
            <!-- Priority filter -->
            <div class="flex gap-1 bg-mp-card-hover rounded-lg p-1">
              <button v-for="f in priorityFilters" :key="f.value" @click="filterPriority = f.value"
                :class="priorityFilterClass(f.value)"
                class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors">
                {{ f.label }}
              </button>
            </div>
            <!-- Search -->
            <input v-model="search" placeholder="🔍 Search tasks..."
              class="ml-auto bg-mp-card-hover border border-mp-border text-white text-sm rounded-lg px-3 py-1.5 w-52 focus:outline-none focus:border-mp-teal placeholder-gray-500"/>
          </div>
        </div>
      </div>

      <!-- ══ TASK LIST ══ -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Empty state -->
        <div v-if="filteredTasks.length === 0"
          class="text-center py-20 bg-mp-card border border-mp-border rounded-2xl">
          <div class="text-5xl mb-4">📋</div>
          <p class="text-white font-semibold text-lg">No tasks found</p>
          <p class="text-white text-sm mt-2">
            {{ tasks.length === 0 ? 'Click "New Task" to add your first task.' : 'Try adjusting the filters.' }}
          </p>
          <button v-if="tasks.length === 0" @click="openModal()"
            class="mt-4 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            + Add First Task
          </button>
        </div>

        <!-- Task cards -->
        <div v-else class="space-y-3">
          <template v-for="task in filteredTasks" :key="task.id">
            <div :class="[
              'bg-mp-card border rounded-xl overflow-hidden transition-all',
              task.is_overdue ? 'border-mp-danger/60' : task.is_due_today ? 'border-mp-gold/60' : 'border-mp-border',
              'hover:border-mp-border'
            ]">
              <div class="p-4">
                <div class="flex items-start gap-3">
                  <!-- Status circle toggle -->
                  <button @click="cycleStatus(task)"
                    :class="statusCircleClass(task.status)"
                    class="mt-0.5 w-5 h-5 rounded-full border-2 flex-shrink-0 flex items-center justify-center transition-all">
                    <svg v-if="task.status === 'completed'" class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                  </button>

                  <!-- Content -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                      <div class="flex-1">
                        <p :class="['font-semibold text-sm', task.status === 'completed' ? 'line-through text-white' : 'text-white']">
                          {{ task.title }}
                        </p>
                        <p v-if="task.description" class="text-white text-xs mt-0.5 line-clamp-2">{{ task.description }}</p>
                      </div>
                      <!-- Actions -->
                      <div class="flex items-center gap-1 flex-shrink-0">
                        <span :class="priorityBadge(task.priority)" class="text-xs font-semibold px-2 py-0.5 rounded-full">
                          {{ priorityLabel(task.priority) }}
                        </span>
                        <span :class="statusBadge(task.status)" class="text-xs font-semibold px-2 py-0.5 rounded-full">
                          {{ statusLabel(task.status) }}
                        </span>
                        <button @click="openModal(task)"
                          class="p-1.5 text-white hover:text-white hover:bg-mp-card-hover rounded transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                          </svg>
                        </button>
                        <button @click="confirmDelete(task)"
                          class="p-1.5 text-white hover:text-mp-danger hover:bg-mp-card-hover rounded transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                          </svg>
                        </button>
                      </div>
                    </div>

                    <!-- Meta row -->
                    <div class="flex flex-wrap items-center gap-3 mt-2 text-xs text-white">
                      <!-- Dates -->
                      <span v-if="task.expected_end_date" :class="task.is_overdue ? 'text-mp-danger font-semibold' : task.is_due_today ? 'text-white font-semibold' : ''">
                        📅 Due: {{ formatDate(task.expected_end_date) }}
                        <span v-if="task.is_overdue"> ⚠️ Overdue</span>
                        <span v-else-if="task.is_due_today"> ⏰ Today!</span>
                      </span>
                      <span v-if="task.expected_start_date">🚀 Start: {{ formatDate(task.expected_start_date) }}</span>
                      <span v-if="task.expected_duration_days">⏱ {{ task.expected_duration_days }}d planned</span>
                      <span v-if="task.company_name" class="text-white">🏢 {{ task.company_name }}</span>
                      <span v-if="task.delay_days !== null && task.delay_days !== 0"
                        :class="task.delay_days > 0 ? 'text-mp-danger' : 'text-mp-success'">
                        {{ task.delay_days > 0 ? `+${task.delay_days}d delay` : `${Math.abs(task.delay_days)}d early` }}
                      </span>
                      <span v-if="task.actual_end_date && task.status === 'completed'" class="text-mp-success">
                        ✅ Completed: {{ formatDate(task.actual_end_date) }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- ══════════════════════════════════════════
           ADD / EDIT MODAL
      ═══════════════════════════════════════════ -->
      <Teleport to="body">
        <div v-if="modal.open" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4" @click.self="modal.open = false">
          <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border flex-shrink-0">
              <h2 class="text-white font-bold text-lg">{{ modal.editing ? 'Edit Task' : 'New Task' }}</h2>
              <button @click="modal.open = false" class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Body -->
            <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

              <!-- Title -->
              <div>
                <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1.5">Task Name *</label>
                <input v-model="form.title" placeholder="What needs to be done?"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-mp-teal placeholder-gray-500"/>
                <p v-if="errors.title" class="text-mp-danger text-xs mt-1">{{ errors.title }}</p>
              </div>

              <!-- Description -->
              <div>
                <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1.5">Description</label>
                <textarea v-model="form.description" rows="3" placeholder="Add details, context, or links..."
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-mp-teal placeholder-gray-500 resize-none"/>
              </div>

              <!-- Priority + Status row -->
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1.5">Priority</label>
                  <select v-model="form.priority"
                    class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-mp-teal">
                    <option value="low">🟢 Low</option>
                    <option value="medium">🟡 Medium</option>
                    <option value="high">🔴 High</option>
                  </select>
                </div>
                <div>
                  <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1.5">Status</label>
                  <select v-model="form.status"
                    class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-mp-teal">
                    <option value="not_started">⭕ Not Started</option>
                    <option value="in_progress">🔵 In Progress</option>
                    <option value="completed">✅ Completed</option>
                    <option value="cancelled">❌ Cancelled</option>
                  </select>
                </div>
              </div>

              <!-- Related Company -->
              <div>
                <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1.5">Related Company (optional)</label>
                <select v-model="form.portfolio_company_id"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-mp-teal">
                  <option :value="null">— None —</option>
                  <option v-for="co in companies" :key="co.id" :value="co.id">{{ co.name }}</option>
                </select>
              </div>

              <!-- Divider: Planned Timeline -->
              <div>
                <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3 flex items-center gap-2">
                  <span class="flex-1 h-px bg-mp-card-hover"></span>
                  📅 Planned Timeline
                  <span class="flex-1 h-px bg-mp-card-hover"></span>
                </p>
                <div class="grid grid-cols-3 gap-3">
                  <div>
                    <label class="text-xs text-white block mb-1">Start Date</label>
                    <input type="date" v-model="form.expected_start_date"
                      @change="calcExpectedEnd"
                      class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-mp-teal"/>
                  </div>
                  <div>
                    <label class="text-xs text-white block mb-1">Duration (days)</label>
                    <input type="number" v-model.number="form.expected_duration_days" min="1"
                      @input="calcExpectedEnd"
                      placeholder="e.g. 7"
                      class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-mp-teal placeholder-gray-500"/>
                  </div>
                  <div>
                    <label class="text-xs text-white block mb-1">End Date</label>
                    <input type="date" v-model="form.expected_end_date"
                      class="w-full bg-mp-card-hover border border-mp-border text-sm rounded-lg px-3 py-2.5 focus:outline-none focus:border-mp-teal"
                      :class="form.expected_end_date ? 'text-white border-mp-border' : 'text-white border-mp-border'"/>
                  </div>
                </div>
                <p class="text-xs text-white mt-1.5">💡 Fill Start Date + Duration and End Date auto-calculates — or set End Date directly.</p>
              </div>

              <!-- Actual Timeline (visible when editing or status != not_started) -->
              <div v-if="modal.editing || form.status !== 'not_started'">
                <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3 flex items-center gap-2">
                  <span class="flex-1 h-px bg-mp-card-hover"></span>
                  📊 Actual Timeline
                  <span class="flex-1 h-px bg-mp-card-hover"></span>
                </p>
                <div class="grid grid-cols-3 gap-3">
                  <div>
                    <label class="text-xs text-white block mb-1">Actual Start</label>
                    <input type="date" v-model="form.actual_start_date"
                      @change="calcActualEnd"
                      class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-mp-teal"/>
                  </div>
                  <div>
                    <label class="text-xs text-white block mb-1">Actual Duration</label>
                    <input type="number" v-model.number="form.actual_duration_days" min="1"
                      @input="calcActualEnd"
                      placeholder="e.g. 10"
                      class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-mp-teal placeholder-gray-500"/>
                  </div>
                  <div>
                    <label class="text-xs text-white block mb-1">Actual End</label>
                    <input type="date" v-model="form.actual_end_date"
                      class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-mp-teal"/>
                  </div>
                </div>
                <!-- Delay indicator -->
                <div v-if="computedDelay !== null" class="mt-2 text-xs font-semibold"
                  :class="computedDelay > 0 ? 'text-mp-danger' : computedDelay < 0 ? 'text-mp-success' : 'text-white'">
                  {{ computedDelay > 0 ? `⚠️ ${computedDelay} day(s) behind schedule` : computedDelay < 0 ? `✅ ${Math.abs(computedDelay)} day(s) ahead of schedule` : '✅ On schedule' }}
                </div>
              </div>

              <!-- Completion Notes (shown when completed) -->
              <div v-if="form.status === 'completed'">
                <label class="text-xs font-semibold text-white uppercase tracking-widest block mb-1.5">Completion Notes</label>
                <textarea v-model="form.completion_notes" rows="2" placeholder="Any notes on how this was completed..."
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:border-mp-teal placeholder-gray-500 resize-none"/>
              </div>

              <!-- Reminder toggle -->
              <div class="flex items-center gap-3">
                <button @click="form.reminder_enabled = !form.reminder_enabled"
                  :class="form.reminder_enabled ? 'bg-mp-teal' : 'bg-mp-page'"
                  class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors flex-shrink-0">
                  <span :class="form.reminder_enabled ? 'translate-x-5' : 'translate-x-1'"
                    class="inline-block h-3 w-3 transform rounded-full bg-white transition-transform"/>
                </button>
                <span class="text-sm text-white">🔔 Alert me on the due date</span>
              </div>

            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-mp-border flex justify-end gap-3 flex-shrink-0">
              <button @click="modal.open = false"
                class="px-4 py-2 text-sm text-white hover:text-white bg-mp-card-hover hover:bg-mp-page rounded-lg transition-colors">
                Cancel
              </button>
              <button @click="saveTask" :disabled="saving"
                class="px-5 py-2 text-sm font-semibold bg-mp-teal hover:bg-mp-teal text-white rounded-lg transition-colors disabled:opacity-50 flex items-center gap-2">
                <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                {{ modal.editing ? 'Save Changes' : 'Create Task' }}
              </button>
            </div>
          </div>
        </div>
      </Teleport>

      <!-- ══ DELETE CONFIRM MODAL ══ -->
      <Teleport to="body">
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4" @click.self="deleteTarget = null">
          <div class="bg-mp-card border border-mp-danger/50 rounded-2xl w-full max-w-sm p-6 shadow-2xl">
            <div class="text-center mb-5">
              <div class="text-4xl mb-3">🗑️</div>
              <p class="text-white font-semibold">Delete this task?</p>
              <p class="text-white text-sm mt-1">"{{ deleteTarget.title }}"</p>
              <p class="text-mp-danger text-xs mt-2">This cannot be undone.</p>
            </div>
            <div class="flex gap-3">
              <button @click="deleteTarget = null"
                class="flex-1 py-2 text-sm text-white bg-mp-card-hover hover:bg-mp-page rounded-lg transition-colors">
                Cancel
              </button>
              <button @click="deleteTask"
                class="flex-1 py-2 text-sm font-semibold bg-mp-danger hover:bg-mp-danger text-white rounded-lg transition-colors">
                Delete
              </button>
            </div>
          </div>
        </div>
      </Teleport>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  tasks:     { type: Array,  default: () => [] },
  companies: { type: Array,  default: () => [] },
  counts:    { type: Object, default: () => ({}) },
})

// ── State ──────────────────────────────────────────────────────────────────
const tasks         = ref([...props.tasks])
const filterStatus  = ref('all')
const filterPriority = ref('all')
const search        = ref('')
const saving        = ref(false)
const deleteTarget  = ref(null)

const modal = reactive({ open: false, editing: false, id: null })

const emptyForm = () => ({
  title: '', description: '', priority: 'medium', status: 'not_started',
  portfolio_company_id: null,
  expected_start_date: '', expected_duration_days: null, expected_end_date: '',
  actual_start_date:   '', actual_duration_days:   null, actual_end_date: '',
  reminder_enabled: true, completion_notes: '',
})
const form   = reactive(emptyForm())
const errors = reactive({})

// ── Filters ─────────────────────────────────────────────────────────────────
const statusFilters = [
  { label: 'All',         value: 'all' },
  { label: '⭕ Not Started', value: 'not_started' },
  { label: '🔵 In Progress', value: 'in_progress' },
  { label: '✅ Completed',   value: 'completed' },
  { label: '🔴 Overdue',     value: 'overdue' },
]
const priorityFilters = [
  { label: 'All Priority', value: 'all' },
  { label: '🔴 High',  value: 'high' },
  { label: '🟡 Medium', value: 'medium' },
  { label: '🟢 Low',   value: 'low' },
]

function priorityFilterClass(value) {
  if (filterPriority.value !== value) {
    return 'text-white hover:text-white'
  }

  if (value === 'high') {
    return 'bg-mp-danger text-white'
  }

  if (value === 'medium') {
    return 'bg-mp-gold text-black'
  }

  if (value === 'low') {
    return 'bg-mp-success text-black'
  }

  return 'bg-mp-muted text-black'
}

const filteredTasks = computed(() => {
  return tasks.value.filter(t => {
    if (filterStatus.value === 'overdue' && !t.is_overdue) return false
    if (filterStatus.value !== 'all' && filterStatus.value !== 'overdue' && t.status !== filterStatus.value) return false
    if (filterPriority.value !== 'all' && t.priority !== filterPriority.value) return false
    if (search.value) {
      const q = search.value.toLowerCase()
      if (!t.title.toLowerCase().includes(q) && !(t.description || '').toLowerCase().includes(q)) return false
    }
    return true
  })
})

// ── KPI Cards ───────────────────────────────────────────────────────────────
const counts = ref({ ...props.counts })
const kpiCards = computed(() => [
  { label: 'Total',      value: counts.value.total,       color: 'text-white',    filter: 'all' },
  { label: 'In Progress', value: counts.value.in_progress, color: 'text-white', filter: 'in_progress' },
  { label: 'Completed',  value: counts.value.completed,   color: 'text-mp-success', filter: 'completed' },
  { label: 'Overdue',    value: counts.value.overdue,     color: 'text-mp-danger',  filter: 'overdue' },
  { label: 'Due Today',  value: counts.value.due_today,   color: 'text-white', filter: 'overdue' },
])

// ── Helpers ──────────────────────────────────────────────────────────────────
function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function addDays(dateStr, days) {
  if (!dateStr || !days) return ''
  const d = new Date(dateStr)
  d.setDate(d.getDate() + days - 1)
  return d.toISOString().split('T')[0]
}

function calcExpectedEnd() {
  if (form.expected_start_date && form.expected_duration_days) {
    form.expected_end_date = addDays(form.expected_start_date, form.expected_duration_days)
  }
}
function calcActualEnd() {
  if (form.actual_start_date && form.actual_duration_days) {
    form.actual_end_date = addDays(form.actual_start_date, form.actual_duration_days)
  }
}

const computedDelay = computed(() => {
  if (!form.actual_end_date || !form.expected_end_date) return null
  const a = new Date(form.actual_end_date)
  const e = new Date(form.expected_end_date)
  return Math.round((a - e) / 86400000)
})

// ── Badge helpers ────────────────────────────────────────────────────────────
function priorityBadge(p) {
  return p === 'high' ? 'bg-mp-danger/50 text-mp-danger border border-mp-danger/40'
       : p === 'medium' ? 'bg-mp-gold/50 text-white border border-mp-gold/40'
       : 'bg-mp-success/50 text-mp-success border border-mp-success/40'
}
function priorityLabel(p) {
  return p === 'high' ? '🔴 High' : p === 'medium' ? '🟡 Medium' : '🟢 Low'
}
function statusBadge(s) {
  return s === 'completed' ? 'bg-mp-success/50 text-mp-success border border-mp-success/40'
       : s === 'in_progress' ? 'bg-mp-teal-subtle/50 text-white border border-mp-teal/40'
       : s === 'cancelled' ? 'bg-mp-card-hover text-white border border-mp-border'
       : 'bg-mp-card-hover text-white border border-mp-border'
}
function statusLabel(s) {
  return s === 'completed' ? '✅ Done' : s === 'in_progress' ? '🔵 In Progress'
       : s === 'cancelled' ? '❌ Cancelled' : '⭕ Not Started'
}
function statusCircleClass(s) {
  return s === 'completed' ? 'border-mp-success bg-mp-success'
       : s === 'in_progress' ? 'border-mp-teal bg-mp-teal/30'
       : 'border-mp-border bg-transparent hover:border-mp-border'
}

// ── Modal ────────────────────────────────────────────────────────────────────
function openModal(task = null) {
  Object.assign(errors, {})
  if (task) {
    Object.assign(form, {
      title:                    task.title,
      description:              task.description || '',
      priority:                 task.priority,
      status:                   task.status,
      portfolio_company_id:     task.portfolio_company_id,
      expected_start_date:      task.expected_start_date || '',
      expected_duration_days:   task.expected_duration_days,
      expected_end_date:        task.expected_end_date || '',
      actual_start_date:        task.actual_start_date || '',
      actual_duration_days:     task.actual_duration_days,
      actual_end_date:          task.actual_end_date || '',
      reminder_enabled:         task.reminder_enabled,
      completion_notes:         task.completion_notes || '',
    })
    modal.editing = true
    modal.id      = task.id
  } else {
    Object.assign(form, emptyForm())
    modal.editing = false
    modal.id      = null
  }
  modal.open = true
}

function getCsrfToken() {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function saveTask() {
  if (!form.title.trim()) { errors.title = 'Task name is required.'; return }
  saving.value = true
  const url    = modal.editing ? `/tasks/${modal.id}` : '/tasks'
  const method = modal.editing ? 'PUT' : 'POST'
  try {
    const res  = await fetch(url, {
      method,
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
      body: JSON.stringify(form),
    })
    const data = await res.json()
    if (data.success) {
      if (modal.editing) {
        const idx = tasks.value.findIndex(t => t.id === modal.id)
        if (idx !== -1) tasks.value[idx] = data.task
      } else {
        tasks.value.unshift(data.task)
        counts.value.total++
      }
      rebuildCounts()
      modal.open = false
    }
  } finally {
    saving.value = false
  }
}

// ── Delete ───────────────────────────────────────────────────────────────────
function confirmDelete(task) { deleteTarget.value = task }
async function deleteTask() {
  const id = deleteTarget.value.id
  await fetch(`/tasks/${id}`, {
    method: 'DELETE',
    credentials: 'include',
    headers: { 'X-XSRF-TOKEN': getCsrfToken() },
  })
  tasks.value = tasks.value.filter(t => t.id !== id)
  rebuildCounts()
  deleteTarget.value = null
}

// ── Quick status cycle ───────────────────────────────────────────────────────
const statusCycle = { not_started: 'in_progress', in_progress: 'completed', completed: 'not_started', cancelled: 'not_started' }
async function cycleStatus(task) {
  const next = statusCycle[task.status]
  await fetch(`/tasks/${task.id}/status`, {
    method: 'PATCH',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
    body: JSON.stringify({ status: next }),
  })
  task.status = next
  rebuildCounts()
}

function rebuildCounts() {
  const c = { total: tasks.value.length, not_started: 0, in_progress: 0, completed: 0, overdue: 0, due_today: 0 }
  tasks.value.forEach(t => {
    if (t.status === 'not_started') c.not_started++
    if (t.status === 'in_progress') c.in_progress++
    if (t.status === 'completed')   c.completed++
    if (t.is_overdue)   c.overdue++
    if (t.is_due_today) c.due_today++
  })
  Object.assign(counts.value, c)
}
</script>