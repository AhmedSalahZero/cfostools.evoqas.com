<template>
  <Head title="Expense Analysis — Upload" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <Link :href="`/portfolio-companies/${company.id}`"
                class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-2 w-fit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to {{ company.name }}
              </Link>
              <h1 class="text-2xl font-bold text-white">Expense Analysis</h1>
              <p class="text-white text-sm mt-1">{{ company.name }} — Upload expense data</p>
            </div>
            <!-- Nav tabs -->
            <div class="flex gap-2">
              <a :href="`/companies/${company.id}/expenses`"
                class="px-4 py-2 rounded-lg text-sm text-white hover:text-white hover:bg-mp-card-hover transition-colors">
                Dashboard
              </a>
              <span class="px-4 py-2 rounded-lg text-sm bg-mp-teal text-white font-medium">Upload</span>
              <a :href="`/companies/${company.id}/expenses/reports`"
                class="px-4 py-2 rounded-lg text-sm text-white hover:text-white hover:bg-mp-card-hover transition-colors">
                Reports
              </a>
              <a :href="`/companies/${company.id}/sales`"
                class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm bg-mp-gold hover:bg-mp-gold-dark text-white font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                Sales Dashboard
              </a>
              
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success" class="bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <!-- TEMPLATE DOWNLOAD -->
        <div class="bg-mp-card rounded-xl border border-mp-border p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-2">Step 1 — Download Template</p>
          <p class="text-white text-sm mb-4">
            Download the Excel template, fill in your expense data, then upload below.
            The template has 5 columns: <span class="text-white font-medium">Date, Expense Category, Expense Sub Category, Expense Name, Expense Amount</span>.
          </p>
          <a :href="`/companies/${company.id}/expenses/download-template`"
            class="inline-flex items-center gap-2 bg-mp-success hover:bg-mp-success text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Download Excel Template
          </a>
          <div class="mt-4 bg-mp-card-hover rounded-lg p-4">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-2">Column Guide</p>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-2 text-xs">
              <div class="bg-mp-page rounded p-2">
                <p class="font-semibold text-white">Date</p>
                <p class="text-white mt-0.5">Required</p>
              </div>
              <div class="bg-mp-page rounded p-2">
                <p class="font-semibold text-white">Expense Category</p>
                <p class="text-white mt-0.5">Required (e.g. COGS)</p>
              </div>
              <div class="bg-mp-page rounded p-2">
                <p class="font-semibold text-white">Expense Sub Category</p>
                <p class="text-mp-success mt-0.5">Optional</p>
              </div>
              <div class="bg-mp-page rounded p-2">
                <p class="font-semibold text-white">Expense Name</p>
                <p class="text-white mt-0.5">Required (e.g. Salaries)</p>
              </div>
              <div class="bg-mp-page rounded p-2">
                <p class="font-semibold text-white">Expense Amount</p>
                <p class="text-white mt-0.5">Required (numeric)</p>
              </div>
            </div>
          </div>
        </div>

        <!-- UPLOAD FORM -->
        <div class="bg-mp-card rounded-xl border border-mp-border p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-6">Step 2 — Upload Your File</p>

          <form @submit.prevent="submitUpload" class="space-y-6">

            <!-- Drag & Drop -->
            <div
              @dragover.prevent="dragging = true"
              @dragleave="dragging = false"
              @drop.prevent="handleDrop"
              @click="$refs.fileInput.click()"
              :class="[
                'border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-colors',
                dragging ? 'border-mp-teal bg-mp-teal-subtle/20' : 'border-mp-border hover:border-mp-border'
              ]"
            >
              <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="handleFileSelect" />
              <div v-if="!selectedFile">
                <svg class="w-10 h-10 mx-auto text-white mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <p class="text-white font-medium">Drag & drop your Excel file here</p>
                <p class="text-white text-sm mt-1">or click to browse — .xlsx, .xls, .csv supported</p>
                <p class="text-white text-xs mt-2">Maximum 50,000 rows</p>
              </div>
              <div v-else class="flex items-center justify-center gap-3">
                <svg class="w-8 h-8 text-mp-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="text-left">
                  <p class="font-medium text-white">{{ selectedFile.name }}</p>
                  <p class="text-xs text-white">{{ (selectedFile.size / 1024).toFixed(1) }} KB</p>
                </div>
                <button type="button" @click.stop="clearFile" class="ml-4 text-white hover:text-mp-danger transition-colors">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Period & Date Format -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period From <span class="text-mp-danger">*</span></label>
                <input type="date" v-model="form.period_from"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-mp-teal transition"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period To <span class="text-mp-danger">*</span></label>
                <input type="date" v-model="form.period_to"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-mp-teal transition"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Date Format in File <span class="text-mp-danger">*</span></label>
                <select v-model="form.date_format"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-mp-teal transition">
                  <option value="DD/MM/YYYY">DD/MM/YYYY (e.g. 25/01/2024)</option>
                  <option value="MM/DD/YYYY">MM/DD/YYYY (e.g. 01/25/2024)</option>
                  <option value="YYYY/MM/DD">YYYY/MM/DD (e.g. 2024/01/25)</option>
                  <option value="DD-MM-YYYY">DD-MM-YYYY (e.g. 25-01-2024)</option>
                  <option value="MM-DD-YYYY">MM-DD-YYYY (e.g. 01-25-2024)</option>
                  <option value="YYYY-MM-DD">YYYY-MM-DD (e.g. 2024-01-25)</option>
                </select>
              </div>
            </div>

            <div class="flex justify-end">
              <button type="submit" :disabled="!selectedFile || uploading"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-8 py-3 rounded-lg transition-colors">
                <svg v-if="uploading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                {{ uploading ? 'Uploading...' : 'Upload File' }}
              </button>
            </div>
          </form>
        </div>

        <!-- UPLOAD HISTORY -->
        <div v-if="uploads.length > 0" class="bg-mp-card rounded-xl border border-mp-border p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Upload History</p>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-mp-border">
                <th class="text-left text-xs text-white uppercase tracking-widest px-4 py-3">Uploaded</th>
                <th class="text-left text-xs text-white uppercase tracking-widest px-4 py-3">Period</th>
                <th class="text-right text-xs text-white uppercase tracking-widest px-4 py-3">Rows</th>
                <th class="text-center text-xs text-white uppercase tracking-widest px-4 py-3">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
              <tr v-for="u in uploadList" :key="u.id" class="hover:bg-mp-card-hover/40 transition-colors">
                <td class="px-4 py-3 text-white">{{ u.created_at }}</td>
                <td class="px-4 py-3 text-white">{{ u.period_from }} → {{ u.period_to }}</td>
                <td class="px-4 py-3 text-right text-white">{{ u.row_count.toLocaleString() }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="{
                    'bg-mp-success/15 text-mp-success border border-mp-success': u.status === 'completed',
                    'bg-mp-danger/15 text-mp-danger border border-mp-danger': u.status === 'failed',
                    'bg-mp-warning/15 text-mp-warning border border-mp-warning': u.status === 'processing',
                  }" class="text-xs font-semibold px-2.5 py-1 rounded-full uppercase tracking-wide">
                    {{ u.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <button @click="confirmDelete(u)" :disabled="u.deleting"
                    class="flex items-center gap-1.5 text-xs text-mp-danger hover:text-mp-danger bg-mp-danger/30 hover:bg-mp-danger/60 border border-mp-danger/50 px-3 py-1.5 rounded-lg transition-colors disabled:opacity-50 mx-auto">
                    <svg v-if="u.deleting" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    {{ u.deleting ? '...' : 'Delete' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
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
          <button @click="deleteTarget = null" class="flex-1 bg-mp-card-hover hover:bg-mp-page text-white font-medium py-2.5 rounded-lg transition-colors text-sm">Cancel</button>
          <button @click="executeDelete" class="flex-1 bg-mp-danger hover:bg-mp-danger text-white font-medium py-2.5 rounded-lg transition-colors text-sm">Yes, Delete Everything</button>
        </div>
      </div>
    </div>
  </Teleport>

</AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, watch } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

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
const deleteTarget  = ref(null)
const deleteSuccess = ref(false)
const dragging      = ref(false)
const selectedFile = ref(null)
const uploading    = ref(false)

const form = useForm({
  file:        null,
  period_from: '',
  period_to:   '',
  date_format: 'DD/MM/YYYY',
})

function handleDrop(e) {
  dragging.value = false
  const file = e.dataTransfer.files[0]
  if (file) selectedFile.value = file
}

function handleFileSelect(e) {
  const file = e.target.files[0]
  if (file) selectedFile.value = file
}

function clearFile() {
  selectedFile.value = null
}

function confirmDelete(upload) { deleteTarget.value = upload }

async function executeDelete() {
  const upload = deleteTarget.value
  if (!upload) return
  deleteTarget.value = null
  upload.deleting = true
  try {
    await axios.delete(`/companies/${props.company.id}/expenses/uploads/${upload.id}`)
    const idx = uploadList.findIndex(u => u.id === upload.id)
    if (idx !== -1) uploadList.splice(idx, 1)
    deleteSuccess.value = true
    setTimeout(() => { deleteSuccess.value = false }, 4000)
  } catch(e) { console.error(e); upload.deleting = false }
}

function submitUpload() {
  if (!selectedFile.value) return
  uploading.value = true
  form.file = selectedFile.value
  form.post(`/companies/${props.company.id}/expenses/upload`, {
    onFinish: () => { uploading.value = false },
  })
}
</script>