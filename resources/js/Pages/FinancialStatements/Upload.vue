<template>
  <Head :title="`Upload Statement — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Statements
          </Link>
          <h1 class="text-2xl font-bold text-white">📤 Upload Financial Statement</h1>
          <p class="text-white text-sm mt-0.5">{{ company.name }} · Import a new period from Excel</p>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- No data warning -->
        <div v-if="!hasData"
          class="bg-mp-warning/40 border border-mp-warning rounded-xl p-6 text-center">
          <div class="text-4xl mb-3">⚠️</div>
          <h3 class="text-mp-warning font-semibold mb-2">No Statements Found</h3>
          <p class="text-mp-warning/80 text-sm mb-4">
            You need to create at least one financial statement manually first.
            That statement becomes the structure template for all future Excel uploads.
          </p>
          <Link :href="`/portfolio-companies/${company.id}/financial-statements/create`"
            class="inline-block bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            Create First Statement Manually
          </Link>
        </div>

        <template v-else>

          <!-- HOW IT WORKS -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-5">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">How It Works</p>
            <div class="grid grid-cols-3 gap-4 text-center text-sm">
              <div class="bg-mp-card-hover/50 rounded-lg p-4">
                <div class="text-2xl mb-2">1️⃣</div>
                <p class="text-white font-medium mb-1">Pick a Template</p>
                <p class="text-white text-xs">Choose an existing statement whose structure you want to reuse</p>
              </div>
              <div class="bg-mp-card-hover/50 rounded-lg p-4">
                <div class="text-2xl mb-2">2️⃣</div>
                <p class="text-white font-medium mb-1">Download & Fill</p>
                <p class="text-white text-xs">Download the Excel template and fill in the numbers for the new period</p>
              </div>
              <div class="bg-mp-card-hover/50 rounded-lg p-4">
                <div class="text-2xl mb-2">3️⃣</div>
                <p class="text-white font-medium mb-1">Upload</p>
                <p class="text-white text-xs">Upload the filled file and set the period dates — done!</p>
              </div>
            </div>
          </div>

          <!-- STEP 1: PICK TEMPLATE -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">
              Step 1 — Select Template Statement
            </p>
            <p class="text-white text-xs mb-3">
              The template defines which line items your Excel must contain.
              Pick the statement whose structure matches what you want to upload.
            </p>
            <select v-model="selectedStatementId"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
              <option value="" disabled>— Select a statement —</option>
              <option v-for="stmt in statements" :key="stmt.id" :value="stmt.id">
                {{ stmt.label }}
              </option>
            </select>

            <!-- Download template button -->
            <div class="mt-4" v-if="selectedStatementId">
              <a :href="downloadUrl"
                class="inline-flex items-center gap-2 bg-mp-success hover:bg-mp-success text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download Excel Template
              </a>
              <p class="text-white text-xs mt-2">
                Fill in column B on each sheet (Income Statement, Balance Sheet, Cash Flow) then upload below.
              </p>
            </div>
          </div>

          <!-- STEP 2: PERIOD & STATUS -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">
              Step 2 — New Statement Period
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs text-white mb-1.5">From *</label>
                <input v-model="form.period_from" type="date"
                  class="w-full bg-mp-card-hover border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"
                  :class="errors.period_from ? 'border-mp-danger' : 'border-mp-border'"/>
                <p v-if="errors.period_from" class="text-mp-danger text-xs mt-1">{{ errors.period_from }}</p>
              </div>
              <div>
                <label class="block text-xs text-white mb-1.5">To *</label>
                <input v-model="form.period_to" type="date"
                  class="w-full bg-mp-card-hover border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"
                  :class="errors.period_to ? 'border-mp-danger' : 'border-mp-border'"/>
                <p v-if="errors.period_to" class="text-mp-danger text-xs mt-1">{{ errors.period_to }}</p>
              </div>
              <div>
                <label class="block text-xs text-white mb-1.5">Status</label>
                <select v-model="form.status"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                  <option value="draft">Draft</option>
                  <option value="final">Final</option>
                </select>
              </div>
            </div>
            <div class="mt-4">
              <label class="block text-xs text-white mb-1.5">Notes (optional)</label>
              <input v-model="form.notes" type="text" placeholder="e.g. Audited, Preliminary..."
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
            </div>
          </div>

          <!-- STEP 3: FILE UPLOAD -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">
              Step 3 — Upload Filled Excel File
            </p>

            <!-- Drop zone -->
            <div
              @dragover.prevent="dragging = true"
              @dragleave="dragging = false"
              @drop.prevent="onDrop"
              @click="$refs.fileInput.click()"
              :class="[
                'border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-colors',
                dragging
                  ? 'border-mp-teal bg-mp-teal-subtle/20'
                  : selectedFile
                    ? 'border-mp-success bg-mp-success/20'
                    : 'border-mp-border hover:border-mp-border hover:bg-mp-card-hover/30'
              ]">
              <input ref="fileInput" type="file" accept=".xlsx,.xls" class="hidden" @change="onFileSelect"/>

              <!-- No file yet -->
              <div v-if="!selectedFile">
                <svg class="w-10 h-10 text-white mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <p class="text-white font-medium mb-1">Drop your Excel file here</p>
                <p class="text-white text-sm">or click to browse · .xlsx / .xls · max 10MB</p>
              </div>

              <!-- File selected -->
              <div v-else class="flex items-center justify-center gap-3">
                <div class="w-10 h-10 bg-mp-success/50 rounded-xl flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5 text-mp-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                </div>
                <div class="text-left">
                  <p class="text-white font-medium text-sm">{{ selectedFile.name }}</p>
                  <p class="text-white text-xs mt-0.5">{{ formatBytes(selectedFile.size) }} · Click to change</p>
                </div>
              </div>
            </div>

            <!-- File error -->
            <p v-if="errors.file" class="text-mp-danger text-sm mt-3 whitespace-pre-line bg-mp-danger/30 border border-mp-danger rounded-lg px-4 py-3">
              ⚠️ {{ errors.file }}
            </p>
          </div>

          <!-- SUBMIT -->
          <div class="flex items-center justify-between">
            <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
              class="text-white hover:text-white text-sm transition-colors">
              Cancel
            </Link>
            <button @click="submitUpload" :disabled="uploading || !selectedFile || !selectedStatementId"
              class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-semibold px-8 py-3 rounded-lg transition-colors">
              <svg v-if="!uploading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
              </svg>
              <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ uploading ? 'Validating & Importing...' : 'Upload & Import' }}
            </button>
          </div>

        </template>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
  company:    Object,
  statements: Array,
  hasData:    Boolean,
})

const selectedStatementId = ref('')
const selectedFile        = ref(null)
const dragging            = ref(false)
const uploading           = ref(false)
const errors              = ref({})

const form = ref({
  period_from: '',
  period_to:   '',
  status:      'draft',
  notes:       '',
})

// Download URL — passes selected statement_id as query param
const downloadUrl = computed(() =>
  `/portfolio-companies/${props.company.id}/financial-statements/download-template?statement_id=${selectedStatementId.value}`
)

function onFileSelect(e) {
  const file = e.target.files[0]
  if (file) selectedFile.value = file
}

function onDrop(e) {
  dragging.value = false
  const file = e.dataTransfer.files[0]
  if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
    selectedFile.value = file
  }
}

function formatBytes(bytes) {
  if (bytes < 1024)        return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function submitUpload() {
  errors.value = {}

  if (!selectedStatementId.value) { errors.value.file = 'Please select a template statement first.'; return }
  if (!form.value.period_from)    { errors.value.period_from = 'Required'; return }
  if (!form.value.period_to)      { errors.value.period_to   = 'Required'; return }
  if (!selectedFile.value)        { errors.value.file = 'Please select a file to upload.'; return }

  uploading.value = true

  const data = new FormData()
  data.append('statement_id', selectedStatementId.value)
  data.append('period_from',  form.value.period_from)
  data.append('period_to',    form.value.period_to)
  data.append('status',       form.value.status)
  data.append('notes',        form.value.notes)
  data.append('file',         selectedFile.value)

  router.post(
    `/portfolio-companies/${props.company.id}/financial-statements/upload`,
    data,
    {
      forceFormData: true,
      onError:  (e) => { errors.value = e; uploading.value = false },
      onFinish: ()  => { uploading.value = false },
    }
  )
}
</script>
