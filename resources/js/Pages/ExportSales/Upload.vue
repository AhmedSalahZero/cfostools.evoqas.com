<template>
  <Head :title="`Upload Export Sales Data — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <Link :href="`/portfolio-companies/${company.id}`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-2 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to {{ company.name }}
          </Link>
          <div class="flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3 mb-1">
                <span class="text-xs font-semibold text-mp-success uppercase tracking-widest bg-mp-success/40 border border-mp-success/50 px-2.5 py-1 rounded-full">
                  Export Sales
                </span>
              </div>
              <h1 class="text-2xl font-bold text-white">Upload Export Sales Data</h1>
              <p class="text-white text-sm mt-1">Upload your filled Excel template to import export/trade data</p>
            </div>
            <div class="hidden md:flex items-center gap-2">
              <Link :href="`/companies/${company.id}/export-sales/field-mapping`"
                class="flex items-center gap-1.5 bg-mp-card-hover text-white text-xs font-semibold px-3 py-1.5 rounded-full hover:bg-mp-page transition-colors">
                <span class="w-4 h-4 rounded-full bg-mp-success text-white flex items-center justify-center text-xs">✓</span>
                Field Mapping
              </Link>
              <div class="w-6 h-px bg-mp-page"></div>
              <div class="flex items-center gap-1.5 bg-mp-success text-white text-xs font-semibold px-3 py-1.5 rounded-full">
                <span class="w-4 h-4 rounded-full bg-white text-mp-success flex items-center justify-center text-xs font-bold">2</span>
                Upload Data
              </div>
              <div class="w-6 h-px bg-mp-page"></div>
              <div class="flex items-center gap-1.5 bg-mp-card-hover text-white text-xs font-semibold px-3 py-1.5 rounded-full">
                <span class="w-4 h-4 rounded-full bg-mp-page flex items-center justify-center text-xs">3</span>
                Reports
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Flash success -->
        <div v-if="$page.props.flash?.success"
          class="bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          {{ $page.props.flash.success }}
        </div>

        <!-- Flash error -->
        <div v-if="$page.props.errors?.file"
          class="bg-mp-danger/15 border border-mp-danger text-mp-danger px-4 py-3 rounded-lg text-sm flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          {{ $page.props.errors.file }}
        </div>

        <!-- Delete success -->
        <div v-if="deleteSuccess"
          class="bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Upload and all its data deleted successfully.
        </div>

        <!-- Info banner -->
        <div class="bg-mp-success/30 border border-mp-success/50 rounded-xl p-4 flex items-start gap-3">
          <svg class="w-5 h-5 text-mp-success flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="text-sm text-mp-success">
            <p class="font-medium mb-0.5">Upload Limits</p>
            <p class="text-mp-success text-xs">
              Maximum <span class="font-bold text-white">50,000 rows</span> per upload.
              For larger datasets, split by date range into multiple uploads.
              Supported formats: <span class="font-bold text-white">.xlsx, .xls</span>
            </p>
          </div>
        </div>

        <!-- Upload form -->
        <div class="bg-mp-card rounded-xl border border-mp-border p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-6">Upload New File</p>

          <form @submit.prevent="submit" enctype="multipart/form-data">

            <!-- Drop zone -->
            <div
              @dragover.prevent="dragOver = true"
              @dragleave.prevent="dragOver = false"
              @drop.prevent="onDrop"
              :class="dragOver ? 'border-mp-success bg-mp-success/20' : 'border-mp-border hover:border-mp-border'"
              class="border-2 border-dashed rounded-xl p-8 text-center transition-all cursor-pointer mb-6"
              @click="fileInput.click()">

              <input ref="fileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="onFileSelect" />

              <div v-if="!selectedFile">
                <svg class="w-10 h-10 text-white mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-white text-sm font-medium mb-1">Drop your Excel file here or click to browse</p>
                <p class="text-white text-xs">Accepts .xlsx and .xls files</p>
              </div>
              <div v-else class="flex items-center justify-center gap-4">
                <div class="w-10 h-10 bg-mp-success/15 rounded-lg flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5 text-mp-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                </div>
                <div class="text-left">
                  <p class="text-white text-sm font-medium">{{ selectedFile.name }}</p>
                  <p class="text-white text-xs">{{ formatFileSize(selectedFile.size) }}</p>
                </div>
                <button type="button" @click.stop="clearFile"
                  class="ml-auto text-white hover:text-mp-danger transition-colors">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Date range + format -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period From</label>
                <input v-model="form.period_from" type="date"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-success"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period To</label>
                <input v-model="form.period_to" type="date"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-success"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Date Format in File</label>
                <select v-model="form.date_format"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-success">
                  <option v-for="f in dateFormats" :key="f.value" :value="f.value">
                    {{ f.value }} — {{ f.example }}
                  </option>
                </select>
              </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center gap-3">
              <button type="submit" :disabled="form.processing || !selectedFile"
                class="flex items-center gap-2 bg-mp-success hover:bg-mp-success disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium px-6 py-3 rounded-lg transition-colors text-sm">
                <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                {{ form.processing ? 'Uploading & Processing...' : 'Upload & Import' }}
              </button>
              <Link :href="`/companies/${company.id}/export-sales/reports`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page text-white hover:text-white text-sm font-medium px-6 py-3 rounded-lg transition-colors border border-mp-border">
                Go to Reports
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </Link>
            </div>

          </form>
        </div>

        <!-- Upload History -->
        <div v-if="uploadList.length > 0" class="bg-mp-card rounded-xl border border-mp-border p-6">
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">Upload History</p>
            <p class="text-xs text-white">Click 🗑 to delete an upload and all its data rows</p>
          </div>
          <div class="space-y-3">
            <template v-for="upload in uploadList" :key="upload.id">
              <div class="flex items-center gap-4 p-3 bg-mp-card-hover/50 rounded-lg border border-mp-border">
                <div :class="upload.status === 'completed' ? 'bg-mp-success/15' : upload.status === 'failed' ? 'bg-mp-danger/15' : 'bg-mp-warning/15'"
                  class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0">
                  <svg v-if="upload.status === 'completed'" class="w-4 h-4 text-mp-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  <svg v-else-if="upload.status === 'failed'" class="w-4 h-4 text-mp-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                  <svg v-else class="w-4 h-4 text-mp-warning animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-white text-sm font-medium">{{ upload.period_from }} → {{ upload.period_to }}</p>
                  <p class="text-white text-xs">
                    {{ (upload.row_count || 0).toLocaleString() }} rows ·
                    Format: <span class="text-white font-mono">{{ upload.date_format }}</span> ·
                    {{ upload.created_at }}
                  </p>
                </div>
                <span :class="statusClass(upload.status)"
                  class="text-xs font-semibold px-2.5 py-1 rounded-full border capitalize flex-shrink-0">
                  {{ upload.status }}
                </span>
                <button @click="confirmDelete(upload)" :disabled="upload.deleting"
                  class="flex items-center gap-1.5 text-xs text-mp-danger hover:text-mp-danger bg-mp-danger/30 hover:bg-mp-danger/60 border border-mp-danger/50 px-3 py-1.5 rounded-lg transition-colors disabled:opacity-50 flex-shrink-0">
                  <svg v-if="upload.deleting" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                  </svg>
                  <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                  {{ upload.deleting ? 'Deleting...' : 'Delete' }}
                </button>
              </div>
            </template>
          </div>
        </div>

      </div>
    </div>

    <!-- Confirm Delete Modal -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4" @click.self="deleteTarget = null">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-md shadow-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-mp-danger/15 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-mp-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
            </div>
            <div>
              <p class="text-white font-bold">Delete Upload?</p>
              <p class="text-white text-sm">This cannot be undone.</p>
            </div>
          </div>
          <div class="bg-mp-card-hover rounded-xl p-4 mb-5">
            <p class="text-white text-sm font-medium mb-1">{{ deleteTarget?.period_from }} → {{ deleteTarget?.period_to }}</p>
            <p class="text-mp-danger text-xs font-semibold">⚠️ All {{ (deleteTarget?.row_count || 0).toLocaleString() }} data rows will be permanently deleted.</p>
          </div>
          <div class="flex gap-3">
            <button @click="deleteTarget = null"
              class="flex-1 bg-mp-card-hover hover:bg-mp-page text-white font-medium py-2.5 rounded-lg transition-colors text-sm">
              Cancel
            </button>
            <button @click="executeDelete"
              class="flex-1 bg-mp-danger hover:bg-mp-danger text-white font-medium py-2.5 rounded-lg transition-colors text-sm">
              Yes, Delete Everything
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'

const props = defineProps({
  company: Object,
  uploads: { type: Array, default: () => [] },
})

const uploadList    = reactive(props.uploads.map(u => ({ ...u, deleting: false })))
watch(() => props.uploads, (rows) => {
  rows.forEach((row) => {
    const existing = uploadList.find(u => u.id === row.id)
    if (existing) Object.assign(existing, row, { deleting: existing.deleting })
    else uploadList.unshift({ ...row, deleting: false })
  })
}, { deep: true })
let pollTimer = null
onMounted(() => {
  pollTimer = setInterval(() => {
    if (uploadList.some(u => u.status === 'processing')) {
      router.reload({ only: ['uploads'], preserveScroll: true })
    }
  }, 4000)
})
onBeforeUnmount(() => { if (pollTimer) clearInterval(pollTimer) })
const dragOver      = ref(false)
const selectedFile  = ref(null)
const fileInput     = ref(null)
const deleteTarget  = ref(null)
const deleteSuccess = ref(false)

const dateFormats = [
  { value: 'DD/MM/YYYY', example: '25/02/2026' },
  { value: 'MM/DD/YYYY', example: '02/25/2026' },
  { value: 'YYYY/MM/DD', example: '2026/02/25' },
  { value: 'DD-MM-YYYY', example: '25-02-2026' },
  { value: 'MM-DD-YYYY', example: '02-25-2026' },
  { value: 'YYYY-MM-DD', example: '2026-02-25' },
]

const form = useForm({ file: null, period_from: '', period_to: '', date_format: 'DD/MM/YYYY' })

function onFileSelect(e) {
  const file = e.target.files[0]
  if (file) { selectedFile.value = file; form.file = file }
}
function onDrop(e) {
  dragOver.value = false
  const file = e.dataTransfer.files[0]
  if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
    selectedFile.value = file; form.file = file
  }
}
function clearFile() {
  selectedFile.value = null; form.file = null
  if (fileInput.value) fileInput.value.value = ''
}
function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
function statusClass(status) {
  return {
    completed:  'bg-mp-success/15 text-mp-success border-mp-success',
    failed:     'bg-mp-danger/15 text-mp-danger border-mp-danger',
    processing: 'bg-mp-warning/15 text-mp-warning border-mp-warning',
  }[status] || 'bg-mp-card-hover text-white border-mp-border'
}
function submit() {
  form.post(route('export-sales.process-upload', props.company.id), { forceFormData: true })
}
function confirmDelete(upload) { deleteTarget.value = upload }
async function executeDelete() {
  const upload = deleteTarget.value
  if (!upload) return
  deleteTarget.value = null
  upload.deleting = true
  try {
    await axios.delete(`/companies/${props.company.id}/export-sales/uploads/${upload.id}`)
    const idx = uploadList.findIndex(u => u.id === upload.id)
    if (idx !== -1) uploadList.splice(idx, 1)
    deleteSuccess.value = true
    setTimeout(() => { deleteSuccess.value = false }, 4000)
  } catch (e) {
    console.error(e)
    upload.deleting = false
  }
}
</script>
