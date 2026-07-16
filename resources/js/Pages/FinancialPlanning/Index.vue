<template>
  <Head :title="`Financial Planning — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- Header -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-6 py-6">
          <Link
            :href="`/portfolio-companies/${company.id}`"
            class="text-white hover:text-white flex items-center gap-2 mb-3 text-sm"
          >
            ← Back to {{ company.name }}
          </Link>
          <div class="flex justify-between items-center">
            <div>
              <h1 class="text-3xl font-bold">Financial Planning Models</h1>
              <p class="text-white text-sm mt-1">
                Complex models → Assumption Editor &nbsp;|&nbsp; Simple models → Live Editor
              </p>
            </div>
            <Link
              :href="`/portfolio-companies/${company.id}/financial-planning/upload`"
              class="bg-mp-gold-dark hover:bg-mp-gold px-5 py-2.5 rounded-lg font-medium flex items-center gap-2 transition-colors"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
              </svg>
              Upload New Model
            </Link>
          </div>
        </div>
      </div>

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="max-w-7xl mx-auto px-6 pt-4">
        <div class="bg-mp-success/40 border border-mp-success text-white rounded-lg px-4 py-3 text-sm flex items-center gap-2">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          {{ $page.props.flash.success }}
        </div>
      </div>

      <!-- Content -->
      <div class="max-w-7xl mx-auto px-6 py-8">

        <!-- Empty state -->
        <div v-if="models.length === 0"
          class="text-center py-20 bg-mp-card rounded-xl border border-mp-border">
          <div class="w-16 h-16 bg-mp-card-hover rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <p class="text-white text-lg mb-4">No financial planning models yet</p>
          <Link
            :href="`/portfolio-companies/${company.id}/financial-planning/upload`"
            class="inline-block bg-mp-gold-dark hover:bg-mp-gold px-6 py-3 rounded-lg font-medium transition-colors"
          >
            Upload your first model
          </Link>
        </div>

        <!-- Models table -->
        <div v-else class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
          <table class="w-full">
            <thead class="bg-mp-card-hover/80">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Model Name</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Type</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Version</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Uploaded</th>
                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">By</th>
                <th class="px-6 py-4 text-right text-xs font-semibold text-white uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
              <tr v-for="m in models" :key="m.id"
                class="hover:bg-mp-card-hover/40 transition-colors">

                <!-- Name + filename -->
                <td class="px-6 py-4">
                  <div class="font-medium text-white">{{ m.name }}</div>
                  <div class="text-xs text-white mt-0.5">{{ m.original_filename }}</div>
                </td>

                <!-- Type badge -->
                <td class="px-6 py-4">
                  <span v-if="m.model_type === 'complex'"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-mp-warning/50 text-mp-warning border border-mp-warning/50">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                    </svg>
                    Complex
                  </span>
                  <span v-else
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-mp-success/50 text-mp-success border border-mp-success/50">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Simple
                  </span>
                </td>

                <!-- Version -->
                <td class="px-6 py-4 text-white text-sm">
                  {{ m.version || '—' }}
                </td>

                <!-- Date -->
                <td class="px-6 py-4 text-white text-sm">
                  {{ m.uploaded_at }}
                </td>

                <!-- Uploader -->
                <td class="px-6 py-4 text-white text-sm">
                  {{ m.uploader }}
                </td>

                <!-- Actions -->
                <td class="px-6 py-4">
                  <div class="flex items-center justify-end gap-2">

                    <!-- Open button: Assumption Editor for complex, Live Editor for simple -->
                    <Link
                      v-if="m.model_type === 'complex'"
                      :href="`/portfolio-companies/${company.id}/financial-planning/${m.id}/assumptions`"
                      class="inline-flex items-center gap-1.5 bg-mp-warning hover:bg-mp-warning px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                      Edit Assumptions
                    </Link>
                    <Link
                      v-else
                      :href="`/portfolio-companies/${company.id}/financial-planning/${m.id}/live`"
                      class="inline-flex items-center gap-1.5 bg-mp-gold-dark hover:bg-mp-gold px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M13 10V3L4 14h7v7l9-11h-7z"/>
                      </svg>
                      Live Editor
                    </Link>

                    <!-- Download -->
                    <a
                      :href="`/portfolio-companies/${company.id}/financial-planning/${m.id}/download`"
                      class="inline-flex items-center gap-1.5 bg-mp-page hover:bg-mp-muted px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                      </svg>
                      Download
                    </a>

                    <!-- Delete -->
                    <button
                      @click="confirmDelete(m)"
                      class="inline-flex items-center gap-1.5 bg-mp-danger/50 hover:bg-mp-danger/20 border border-mp-danger/50 px-3 py-1.5 rounded-lg text-xs font-medium text-mp-danger hover:text-mp-danger transition-colors"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                      Delete
                    </button>

                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <Teleport to="body">
      <div v-if="deleteTarget"
        class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 px-4"
        @click.self="deleteTarget = null"
      >
        <div class="bg-mp-card border border-mp-border rounded-2xl p-8 max-w-md w-full shadow-2xl">
          <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 bg-mp-danger/50 border border-mp-danger rounded-full flex items-center justify-center shrink-0">
              <svg class="w-6 h-6 text-mp-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
            </div>
            <div>
              <h3 class="text-lg font-bold text-white">Delete Model</h3>
              <p class="text-white text-sm">This action cannot be undone</p>
            </div>
          </div>

          <p class="text-white mb-2">
            Are you sure you want to delete <span class="text-white font-semibold">{{ deleteTarget?.name }}</span>?
          </p>
          <p class="text-white text-sm mb-8">
            The Excel file will be permanently removed from the server.
          </p>

          <div class="flex gap-3">
            <button
              @click="deleteTarget = null"
              class="flex-1 bg-mp-card-hover hover:bg-mp-page text-white py-2.5 rounded-lg font-medium transition-colors"
            >
              Cancel
            </button>
            <button
              @click="executeDelete"
              :disabled="deleting"
              class="flex-1 bg-mp-danger hover:bg-mp-danger text-white py-2.5 rounded-lg font-medium transition-colors disabled:opacity-50"
            >
              {{ deleting ? 'Deleting...' : 'Yes, Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company: Object,
  models:  Array,
})

const deleteTarget = ref(null)
const deleting     = ref(false)

function confirmDelete(model) {
  deleteTarget.value = model
}

function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true

  router.delete(
    route('financial-planning.destroy', {
      company: props.company.id,
      model:   deleteTarget.value.id,
    }),
    {
      onSuccess: () => {
        deleteTarget.value = null
        deleting.value     = false
      },
      onError: () => {
        deleting.value = false
      },
    }
  )
}
</script>