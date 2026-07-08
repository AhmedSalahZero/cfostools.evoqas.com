<template>
  <Head :title="`Model Studio — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <div class="flex items-center gap-2 text-sm text-white mb-1">
                <Link href="/portfolio-companies" class="hover:text-white transition-colors">Portfolio Companies</Link>
                <span>/</span>
                <span class="text-white">{{ company.name }}</span>
                <span>/</span>
                <span class="text-white">🧮 Financial Model Studio</span>
              </div>
              <h1 class="text-2xl font-bold text-white">Financial Model Studio</h1>
              <p class="text-white text-sm mt-1">{{ workbooks.length }} workbook{{ workbooks.length !== 1 ? 's' : '' }}</p>
            </div>
            <button
              @click="showCreate = true"
              class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              New Workbook
            </button>
          </div>
        </div>
      </div>

      <!-- CONTENT -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div v-if="$page.props.flash?.success"
          class="mb-6 bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <!-- Empty state -->
        <div v-if="workbooks.length === 0"
          class="bg-mp-card rounded-xl border border-mp-border border-dashed p-16 text-center">
          <div class="text-5xl mb-4">🧮</div>
          <p class="text-white font-semibold text-lg mb-1">No workbooks yet</p>
          <p class="text-white text-sm mb-6">Create your first financial model with multi-sheet support and live formulas</p>
          <button @click="showCreate = true"
            class="bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors">
            + New Workbook
          </button>
        </div>

        <!-- Workbook grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="wb in workbooks" :key="wb.id"
            class="bg-mp-card border border-mp-border rounded-xl p-5 hover:border-mp-teal transition-colors group">
            <div class="flex items-start justify-between mb-4">
              <div class="w-12 h-12 rounded-xl bg-mp-teal-subtle/60 border border-mp-teal/40 flex items-center justify-center text-2xl">
                🧮
              </div>
              <div class="flex gap-1">
                <Link :href="`/portfolio-companies/${company.id}/model-studio/${wb.id}`"
                  class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-teal text-white hover:text-white transition-colors"
                  title="Open editor">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                  </svg>
                </Link>
                <button @click="startRename(wb)"
                  class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-warning text-white hover:text-white transition-colors"
                  title="Rename">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </button>
                <button @click="confirmDel(wb)"
                  class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors"
                  title="Delete">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </div>
            <Link :href="`/portfolio-companies/${company.id}/model-studio/${wb.id}`"
              class="block font-semibold text-white hover:text-white transition-colors mb-1">
              {{ wb.name }}
            </Link>
            <p class="text-xs text-white">{{ wb.sheet_count }} sheet{{ wb.sheet_count !== 1 ? 's' : '' }}</p>
            <p class="text-xs text-white mt-2">Last saved: {{ wb.last_saved_at ?? wb.updated_at }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- CREATE MODAL -->
    <Teleport to="body">
      <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-md p-6 shadow-2xl">
          <h3 class="text-lg font-bold text-white mb-4">New Workbook</h3>
          <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1">Workbook Name</label>
          <input v-model="createName" @keyup.enter="submitCreate"
            type="text" placeholder="e.g. 5-Year Revenue Model"
            class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-mp-teal placeholder-gray-600"
            autofocus />
          <div class="flex gap-3">
            <button @click="showCreate = false; createName = ''"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
              Cancel
            </button>
            <button @click="submitCreate" :disabled="!createName.trim()"
              :class="['flex-1 px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors',
                createName.trim() ? 'bg-mp-teal hover:bg-mp-teal-dark text-white' : 'bg-mp-page text-white cursor-not-allowed']">
              Create Workbook
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- RENAME MODAL -->
    <Teleport to="body">
      <div v-if="renameModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-md p-6 shadow-2xl">
          <h3 class="text-lg font-bold text-white mb-4">Rename Workbook</h3>
          <input v-model="renameModal.name" @keyup.enter="submitRename"
            type="text"
            class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-mp-teal"
            autofocus />
          <div class="flex gap-3">
            <button @click="renameModal.show = false"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">Cancel</button>
            <button @click="submitRename"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-semibold transition-colors">Save</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- DELETE MODAL -->
    <Teleport to="body">
      <div v-if="delModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-md p-6 shadow-2xl">
          <h3 class="text-lg font-bold text-white mb-2">Delete Workbook?</h3>
          <p class="text-white text-sm mb-5">
            "<span class="text-white font-medium">{{ delModal.workbook?.name }}</span>" will be permanently deleted.
          </p>
          <div class="flex gap-3">
            <button @click="delModal.show = false"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">Cancel</button>
            <button @click="submitDelete"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-danger hover:bg-mp-danger text-white text-sm font-semibold transition-colors">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({ company: Object, workbooks: Array })

// Create
const showCreate = ref(false)
const createName = ref('')
const createForm = useForm({ name: '' })

function submitCreate() {
  if (!createName.value.trim()) return
  createForm.name = createName.value
  createForm.post(`/portfolio-companies/${props.company.id}/model-studio`, {
    onSuccess: () => { showCreate.value = false; createName.value = '' }
  })
}

// Rename
const renameModal = reactive({ show: false, workbook: null, name: '' })
const renameForm  = useForm({ name: '' })

function startRename(wb) {
  renameModal.workbook = wb
  renameModal.name     = wb.name
  renameModal.show     = true
}

function submitRename() {
  renameForm.name = renameModal.name
  renameForm.post(`/portfolio-companies/${props.company.id}/model-studio/${renameModal.workbook.id}/rename`, {
    onSuccess: () => { renameModal.show = false }
  })
}

// Delete
const delModal  = reactive({ show: false, workbook: null })
const delForm   = useForm({})

function confirmDel(wb) { delModal.workbook = wb; delModal.show = true }

function submitDelete() {
  delForm.delete(`/portfolio-companies/${props.company.id}/model-studio/${delModal.workbook.id}`, {
    onSuccess: () => { delModal.show = false }
  })
}
</script>