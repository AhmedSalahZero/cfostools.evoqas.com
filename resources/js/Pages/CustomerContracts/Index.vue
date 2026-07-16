<template>
  <Head :title="`Contracts — ${customer.name}`" />

  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- PAGE HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="w-full px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <Link :href="`/portfolio-companies`"
                class="flex items-center gap-2 text-sm text-white/60 hover:text-white transition-colors mb-2 w-fit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Customers
              </Link>
              <h1 class="text-2xl font-bold text-white">{{ customer.name }} — Contracts</h1>
              <p class="text-white/60 text-sm mt-1">
                {{ running.length }} active · {{ finished.length }} finished
              </p>
            </div>
            <Link v-if="isAdmin"
              :href="`/portfolio-companies/${customer.id}/contracts/create`"
              class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Contract
            </Link>
          </div>

          <!-- TABS -->
          <div class="flex gap-1 mt-6 border-b border-mp-border -mb-[1px]">
            <button @click="activeTab = 'running'"
              :class="['px-5 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors',
                activeTab === 'running' ? 'border-mp-teal text-white bg-mp-teal-subtle/20' : 'border-transparent text-white/60 hover:text-white']">
              ▶ Active
              <span :class="['ml-2 text-xs px-2 py-0.5 rounded-full font-semibold',
                activeTab === 'running' ? 'bg-mp-teal text-white' : 'bg-mp-card-hover text-white/60']">{{ running.length }}</span>
            </button>
            <button @click="activeTab = 'finished'"
              :class="['px-5 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors',
                activeTab === 'finished' ? 'border-mp-border text-white bg-mp-card-hover/20' : 'border-transparent text-white/60 hover:text-white']">
              ✓ Finished
              <span :class="['ml-2 text-xs px-2 py-0.5 rounded-full font-semibold',
                activeTab === 'finished' ? 'bg-mp-card-hover text-white' : 'bg-mp-card-hover text-white/60']">{{ finished.length }}</span>
            </button>
            <button @click="activeTab = 'draft'"
              :class="['px-5 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors',
                activeTab === 'draft' ? 'border-mp-gold text-white bg-mp-gold/10' : 'border-transparent text-white/60 hover:text-white']">
              ✎ Draft
              <span :class="['ml-2 text-xs px-2 py-0.5 rounded-full font-semibold',
                activeTab === 'draft' ? 'bg-mp-gold-dark text-white' : 'bg-mp-card-hover text-white/60']">{{ drafts.length }}</span>
            </button>
          </div>
        </div>
      </div>

      <!-- CONTENT -->
      <div class="w-full px-4 sm:px-6 lg:px-8 py-8">

        <div v-if="$page.props.flash?.success" class="mb-6 bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <div v-if="activeList.length === 0" class="bg-mp-card rounded-xl border border-mp-border border-dashed p-16 text-center">
          <div class="w-14 h-14 bg-mp-teal-subtle/50 rounded-xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <p class="text-white font-medium mb-1">No {{ activeTab }} contracts</p>
          <Link v-if="isAdmin" :href="`/portfolio-companies/${customer.id}/contracts/create`"
            class="inline-block mt-3 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            + Add Contract
          </Link>
        </div>

        <div v-else class="space-y-4">
          <div v-for="contract in activeList" :key="contract.id"
            class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">

            <!-- Contract Row -->
            <div class="flex items-center gap-4 px-6 py-4 cursor-pointer hover:bg-mp-card-hover/30 transition-colors"
              @click="toggleExpand(contract.id)">

              <!-- expand icon -->
              <svg :class="['w-4 h-4 text-white/40 transition-transform flex-shrink-0', expanded.has(contract.id) ? 'rotate-90' : '']"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>

              <!-- name + code -->
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-white text-sm">{{ contract.name }}</p>
                <p v-if="contract.code" class="text-xs text-white/50 mt-0.5">{{ contract.code }}</p>
              </div>

              <!-- dates -->
              <div class="hidden sm:block text-xs text-white/50 text-right flex-shrink-0">
                <p>{{ fmtDate(contract.start_date) }} → {{ fmtDate(contract.end_date) }}</p>
              </div>

              <!-- amount -->
              <div class="text-right flex-shrink-0">
                <p class="font-bold text-white text-sm">{{ fmtAmt(contract.amount) }}</p>
                <p class="text-xs text-white/50">{{ contract.currency }}</p>
              </div>

              <!-- status badge -->
              <span :class="statusClass(contract.status)" class="text-xs font-semibold px-2.5 py-1 rounded-full uppercase tracking-wide flex-shrink-0">
                {{ statusLabel(contract.status) }}
              </span>

              <!-- services count -->
              <span class="text-xs text-white/50 flex-shrink-0">
                {{ contract.services.length }} service{{ contract.services.length !== 1 ? 's' : '' }}
              </span>

              <!-- actions -->
              <div class="flex items-center gap-2 flex-shrink-0" @click.stop>
                <!-- Activate (draft only) -->
                <button v-if="isAdmin && contract.status === 'draft'"
                  @click="markStatus(contract, 'running')"
                  class="px-2.5 py-1 rounded-lg bg-mp-teal/20 hover:bg-mp-teal text-mp-teal hover:text-white text-xs font-medium transition-colors"
                  title="Activate contract">
                  Activate
                </button>
                <!-- Mark Finished (active only) -->
                <button v-if="isAdmin && contract.status === 'running'"
                  @click="markStatus(contract, 'finished')"
                  class="px-2.5 py-1 rounded-lg bg-mp-card-hover hover:bg-mp-success text-white/70 hover:text-white text-xs font-medium transition-colors"
                  title="Mark as Finished">
                  Mark Finished
                </button>
                <!-- Edit -->
                <Link v-if="isAdmin"
                  :href="`/portfolio-companies/${customer.id}/contracts/${contract.id}/edit`"
                  class="w-7 h-7 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-teal text-white/60 hover:text-white transition-colors"
                  title="Edit">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </Link>
                <!-- Delete -->
                <button v-if="isAdmin"
                  @click="confirmDelete(contract)"
                  class="w-7 h-7 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white/60 hover:text-white transition-colors"
                  title="Delete">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Expanded Services -->
            <div v-if="expanded.has(contract.id)" class="border-t border-mp-border bg-mp-page/50">
              <div v-if="contract.services.length === 0" class="px-8 py-4 text-sm text-white/40">
                No services defined for this contract.
              </div>
              <table v-else class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border/50">
                    <th class="text-left text-xs font-semibold text-white/50 uppercase tracking-widest px-8 py-2.5">#</th>
                    <th class="text-left text-xs font-semibold text-white/50 uppercase tracking-widest px-4 py-2.5">Service</th>
                    <th class="text-left text-xs font-semibold text-white/50 uppercase tracking-widest px-4 py-2.5">Description</th>
                    <th class="text-left text-xs font-semibold text-white/50 uppercase tracking-widest px-4 py-2.5">Period</th>
                    <th class="text-left text-xs font-semibold text-white/50 uppercase tracking-widest px-4 py-2.5">Execution</th>
                    <th class="text-right text-xs font-semibold text-white/50 uppercase tracking-widest px-8 py-2.5">Amount</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-mp-border/30">
                  <tr v-for="(svc, idx) in contract.services" :key="svc.id" class="hover:bg-mp-card-hover/20">
                    <td class="px-8 py-3 text-white/40 text-xs">{{ idx + 1 }}</td>
                    <td class="px-4 py-3 text-white font-medium">{{ svc.name }}</td>
                    <td class="px-4 py-3 text-white/60 text-xs">{{ svc.description || '—' }}</td>
                    <td class="px-4 py-3 text-white/60 text-xs">
                      <span v-if="svc.start_date || svc.end_date">
                        {{ fmtDate(svc.start_date) }} → {{ fmtDate(svc.end_date) }}
                      </span>
                      <span v-else>—</span>
                    </td>
                    <td class="px-4 py-3 text-white/60 text-xs">
                      <span v-if="svc.execution_total_pct > 0">{{ svc.execution_total_pct }}%</span>
                      <span v-else-if="svc.milestones?.length">{{ svc.milestones.length }} milestone(s)</span>
                      <span v-else>—</span>
                    </td>
                    <td class="px-8 py-3 text-right font-semibold text-white">
                      {{ fmtAmt(svc.amount) }}
                      <span class="text-xs text-white/50 ml-1">{{ contract.currency }}</span>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="border-t border-mp-border/50">
                    <td colspan="5" class="px-8 py-3 text-xs font-semibold text-white/50 uppercase tracking-widest">Total</td>
                    <td class="px-8 py-3 text-right font-bold text-mp-teal">
                      {{ fmtAmt(contract.amount) }}
                      <span class="text-xs text-white/50 ml-1">{{ contract.currency }}</span>
                    </td>
                  </tr>
                </tfoot>
              </table>

              <!-- Notes -->
              <div v-if="contract.notes" class="px-8 py-3 border-t border-mp-border/30 text-xs text-white/50">
                <span class="font-semibold text-white/40 uppercase tracking-widest mr-2">Notes:</span>
                {{ contract.notes }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- DELETE MODAL -->
    <Teleport to="body">
      <div v-if="deleteModal.show"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        @click.self="deleteModal.show = false">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-md bg-mp-card border border-mp-danger/50 rounded-2xl shadow-2xl p-6">
          <h3 class="text-lg font-bold text-white text-center mb-1">Delete Contract</h3>
          <p class="text-white/60 text-sm text-center mb-4">
            Delete <span class="text-mp-danger font-semibold">{{ deleteModal.contract?.name }}</span> and all its services?
          </p>
          <div class="flex gap-3">
            <button @click="deleteModal.show = false"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover text-white text-sm font-medium transition-colors">
              Cancel
            </button>
            <button @click="executeDelete"
              :disabled="deleteModal.loading"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-danger text-white text-sm font-semibold transition-colors disabled:opacity-50">
              {{ deleteModal.loading ? 'Deleting…' : 'Yes, Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  customer:  Object,
  contracts: Array,
})

const page    = usePage()
const isAdmin = computed(() => {
  const roles = Object.values(page.props.auth?.user?.roles ?? {})
  return roles.includes('super-admin') || roles.includes('admin')
})

const activeTab = ref('running')
const expanded  = ref(new Set())

const running  = computed(() => props.contracts.filter(c => c.status === 'running'))
const finished = computed(() => props.contracts.filter(c => c.status === 'finished'))
const drafts   = computed(() => props.contracts.filter(c => c.status === 'draft'))

const activeList = computed(() => {
  if (activeTab.value === 'running')  return running.value
  if (activeTab.value === 'finished') return finished.value
  return drafts.value
})

function toggleExpand(id) {
  const s = new Set(expanded.value)
  s.has(id) ? s.delete(id) : s.add(id)
  expanded.value = s
}

function statusLabel(status) {
  const map = { running: 'Active', finished: 'Finished', draft: 'Draft' }
  return map[status] || status
}

function statusClass(status) {
  const map = {
    running:  'bg-mp-teal/15 text-mp-teal border border-mp-teal',
    finished: 'bg-mp-success/15 text-mp-success border border-mp-success',
    draft:    'bg-mp-gold/15 text-mp-gold border border-mp-gold',
  }
  return map[status] || 'bg-mp-card-hover text-white'
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

function fmtAmt(v) {
  if (v === null || v === undefined) return '—'
  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function markStatus(contract, status) {
  const route = status === 'running'
    ? `/portfolio-companies/${props.customer.id}/contracts/${contract.id}/mark-running`
    : `/portfolio-companies/${props.customer.id}/contracts/${contract.id}/mark-finished`
  router.put(route)
}

// DELETE
const deleteModal = reactive({ show: false, contract: null, loading: false })

function confirmDelete(contract) {
  deleteModal.contract = contract
  deleteModal.loading  = false
  deleteModal.show     = true
}

function executeDelete() {
  deleteModal.loading = true
  router.delete(`/portfolio-companies/${props.customer.id}/contracts/${deleteModal.contract.id}`, {
    onSuccess: () => { deleteModal.show = false; deleteModal.loading = false },
    onError:   () => { deleteModal.loading = false; alert('Something went wrong.') },
  })
}
</script>
