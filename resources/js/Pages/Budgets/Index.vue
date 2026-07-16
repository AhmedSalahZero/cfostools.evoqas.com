<template>
  <Head :title="`Budget & Variance — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ── HEADER (same pattern as Financial Statements) ───────────────── -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

          <!-- Back link -->
          <Link :href="`/portfolio-companies/${company.id}`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to {{ company.name }}
          </Link>

          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-bold text-white">📊 Budget & Variance</h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }}</p>
            </div>

            <div class="flex items-center gap-3">

              <!-- Financial Statements link -->
              <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page text-white hover:text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Financial Statements
              </Link>

              <!-- Cash Forecast link -->
              <Link :href="`/portfolio-companies/${company.id}/cash-forecast`"
                class="flex items-center gap-2 bg-mp-teal-dark hover:bg-mp-teal text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                💧 Cash Forecast
              </Link>

              <!-- KPI Dashboard link -->
              <Link :href="`/portfolio-companies/${company.id}/kpi`"
                class="flex items-center gap-2 bg-mp-warning/60 hover:bg-mp-warning text-mp-warning text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                KPI Dashboard
              </Link>

              <!-- New Budget button -->
              <Link :href="route('budgets.create', company.id)"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Budget Statement
              </Link>

            </div>
          </div>
        </div>
      </div>

      <!-- ── CONTENT ──────────────────────────────────────────────────────── -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success"
          class="mb-6 bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <!-- Empty state -->
        <div v-if="budgets.length === 0"
          class="bg-mp-card rounded-xl border border-mp-border border-dashed p-16 text-center">
          <div class="w-14 h-14 bg-mp-teal-subtle/50 rounded-xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
          </div>
          <p class="text-white font-medium mb-1">No budget statements yet</p>
          <p class="text-white text-sm mb-5">Create your first pro-forma budget to start tracking actual vs. budget performance for {{ company.name }}</p>
          <Link :href="route('budgets.create', company.id)"
            class="bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors inline-block">
            + Create First Budget
          </Link>
        </div>

        <!-- Budget list table -->
        <div v-else class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-mp-border">
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">Budget Name</th>
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">Year</th>
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">Currency</th>
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">Status</th>
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">Created</th>
                <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
              <tr v-for="budget in budgets" :key="budget.id"
                class="hover:bg-mp-card-hover/50 transition-colors">

                <!-- Name -->
                <td class="px-6 py-4">
                  <div class="font-semibold text-white">{{ budget.name }}</div>
                  <div v-if="budget.notes" class="text-xs text-white mt-0.5 truncate max-w-xs">{{ budget.notes }}</div>
                </td>

                <!-- Year -->
                <td class="px-6 py-4">
                  <div class="inline-flex items-center bg-mp-teal-subtle/40 border border-mp-teal/40 rounded-lg px-3 py-1">
                    <span class="text-sm font-bold text-white">{{ budget.year }}</span>
                  </div>
                </td>

                <!-- Currency -->
                <td class="px-6 py-4 text-white font-medium">{{ budget.currency }}</td>

                <!-- Status -->
                <td class="px-6 py-4">
                  <span :class="budget.status === 'final'
                    ? 'bg-mp-success/15 text-mp-success border border-mp-success'
                    : 'bg-mp-warning/15 text-mp-warning border border-mp-warning'"
                    class="text-xs font-semibold px-2.5 py-1 rounded-full uppercase tracking-wide">
                    {{ budget.status === 'final' ? '✓ Final' : '✏ Draft' }}
                  </span>
                </td>

                <!-- Created -->
                <td class="px-6 py-4 text-white text-xs">{{ budget.created_at }}</td>

                <!-- Actions -->
                <td class="px-6 py-4">
                  <div class="flex items-center justify-center gap-2">

                    <!-- Enter Actuals -->
                    <Link :href="route('budgets.actuals', [company.id, budget.id])"
                      class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-gold-dark text-white hover:text-white transition-colors"
                      title="Enter Actuals">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </Link>

                    <!-- Variance Report -->
                    <Link :href="route('budgets.show', [company.id, budget.id])"
                      class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-success text-white hover:text-white transition-colors"
                      title="Variance Report">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                      </svg>
                    </Link>

                    <!-- Edit -->
                    <Link :href="route('budgets.edit', [company.id, budget.id])"
                      class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-teal text-white hover:text-white transition-colors"
                      title="Edit Budget">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </Link>

                    <!-- Delete -->
                    <button @click="confirmDelete(budget)"
                      class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors"
                      title="Delete">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>

                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <!-- ── DELETE CONFIRMATION MODAL ────────────────────────────────────── -->
    <div v-if="deleteTarget"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">
      <div class="bg-mp-card border border-mp-border rounded-2xl p-6 w-full max-w-md mx-4 shadow-2xl">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 bg-mp-danger/50 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-mp-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <h3 class="text-lg font-bold text-white">Delete Budget Statement?</h3>
        </div>
        <p class="text-white text-sm mb-6">
          This will permanently delete
          <span class="text-white font-semibold">{{ deleteTarget.name }}</span>
          and all associated groups, line items, and actuals. This cannot be undone.
        </p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null"
            class="flex-1 bg-mp-card-hover hover:bg-mp-page text-white text-sm font-semibold py-2.5 rounded-lg transition-colors">
            Cancel
          </button>
          <button @click="doDelete"
            class="flex-1 bg-mp-danger hover:bg-mp-danger text-white text-sm font-semibold py-2.5 rounded-lg transition-colors">
            Delete Permanently
          </button>
        </div>
      </div>
    </div>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company: Object,
  budgets: Array,
})

const deleteTarget = ref(null)

function confirmDelete(budget) {
  deleteTarget.value = budget
}

function doDelete() {
  router.delete(route('budgets.destroy', [props.company.id, deleteTarget.value.id]), {
    onFinish: () => { deleteTarget.value = null },
  })
}
</script>