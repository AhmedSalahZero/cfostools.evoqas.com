<template>
  <Head :title="`Upload Financial Model — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- Header -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-3xl mx-auto px-6 py-6">
          <Link
            :href="`/portfolio-companies/${company.id}/financial-planning`"
            class="text-white hover:text-white flex items-center gap-2 mb-3 text-sm"
          >
            ← Back to Models
          </Link>
          <h1 class="text-3xl font-bold">Upload New Financial Planning Model</h1>
        </div>
      </div>

      <div class="max-w-3xl mx-auto px-6 py-10">
        <form @submit.prevent="submit" class="space-y-6">

          <!-- Model Type selector — most important, shown first -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Model Type</p>
            <p class="text-white text-sm mb-5">
              Choose the type that matches your Excel file. This determines how you interact with it inside CFOs Tools.
            </p>

            <div class="grid grid-cols-2 gap-4">

              <!-- Complex -->
              <button
                type="button"
                @click="form.model_type = 'complex'"
                :class="[
                  'relative text-left p-5 rounded-xl border-2 transition-all',
                  form.model_type === 'complex'
                    ? 'border-mp-warning bg-mp-warning/20'
                    : 'border-mp-border bg-mp-card-hover/50 hover:border-mp-border'
                ]"
              >
                <div class="flex items-start gap-3">
                  <div :class="[
                    'w-5 h-5 rounded-full border-2 flex items-center justify-center mt-0.5 shrink-0 transition-colors',
                    form.model_type === 'complex' ? 'border-mp-warning bg-mp-warning' : 'border-mp-border'
                  ]">
                    <div v-if="form.model_type === 'complex'" class="w-2 h-2 rounded-full bg-white"></div>
                  </div>
                  <div>
                    <div class="flex items-center gap-2 mb-1">
                      <span class="font-semibold text-white">Complex Model</span>
                      <span class="text-xs bg-mp-warning/60 text-mp-warning border border-mp-warning/50 px-1.5 py-0.5 rounded-full">
                        Assumption Editor
                      </span>
                    </div>
                    <p class="text-white text-xs leading-relaxed">
                      Multi-sheet models with thousands of cross-sheet formulas. You edit key assumptions only — then download the updated Excel to run in Excel.
                    </p>
                    <p class="text-white text-xs mt-2 italic">
                      Example: 5-year business plan, valuation model, full P&L
                    </p>
                  </div>
                </div>
              </button>

              <!-- Simple -->
              <button
                type="button"
                @click="form.model_type = 'simple'"
                :class="[
                  'relative text-left p-5 rounded-xl border-2 transition-all',
                  form.model_type === 'simple'
                    ? 'border-mp-gold bg-mp-gold/20'
                    : 'border-mp-border bg-mp-card-hover/50 hover:border-mp-border'
                ]"
              >
                <div class="flex items-start gap-3">
                  <div :class="[
                    'w-5 h-5 rounded-full border-2 flex items-center justify-center mt-0.5 shrink-0 transition-colors',
                    form.model_type === 'simple' ? 'border-mp-gold bg-mp-gold' : 'border-mp-border'
                  ]">
                    <div v-if="form.model_type === 'simple'" class="w-2 h-2 rounded-full bg-white"></div>
                  </div>
                  <div>
                    <div class="flex items-center gap-2 mb-1">
                      <span class="font-semibold text-white">Simple Model</span>
                      <span class="text-xs bg-mp-gold/60 text-white border border-mp-gold/50 px-1.5 py-0.5 rounded-full">
                        Live Editor
                      </span>
                    </div>
                    <p class="text-white text-xs leading-relaxed">
                      Self-contained spreadsheet with basic formulas. Edit cells directly in the browser — formulas recalculate live as you type.
                    </p>
                    <p class="text-white text-xs mt-2 italic">
                      Example: KPI tracker, budget summary, simple calculator
                    </p>
                  </div>
                </div>
              </button>

            </div>
            <p v-if="form.errors.model_type" class="text-mp-danger text-xs mt-3">
              {{ form.errors.model_type }}
            </p>
          </div>

          <!-- Model details -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6 space-y-5">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">Model Details</p>

            <div>
              <label class="block text-sm font-medium text-white mb-2">Model Name <span class="text-mp-danger">*</span></label>
              <input
                v-model="form.name"
                type="text"
                placeholder="e.g. TechnoMetal 5Y Business Plan v2"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-mp-gold transition-colors"
                required
              />
              <p v-if="form.errors.name" class="text-mp-danger text-xs mt-1">{{ form.errors.name }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-white mb-2">Version <span class="text-white">(optional)</span></label>
                <input
                  v-model="form.version"
                  type="text"
                  placeholder="e.g. v1.2"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-mp-gold transition-colors"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-white mb-2">Notes <span class="text-white">(optional)</span></label>
                <input
                  v-model="form.notes"
                  type="text"
                  placeholder="Any notes about this version"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-mp-gold transition-colors"
                />
              </div>
            </div>
          </div>

          <!-- File upload -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Excel File</p>

            <div
              class="border-2 border-dashed rounded-xl p-10 text-center transition-colors"
              :class="form.file
                ? 'border-mp-success bg-mp-success/10'
                : 'border-mp-border hover:border-mp-gold'"
              @dragover.prevent
              @drop.prevent="onDrop"
            >
              <div v-if="!form.file">
                <svg class="w-12 h-12 text-white mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <p class="text-white mb-1">Drag & drop your Excel file here</p>
                <p class="text-white text-sm mb-4">or</p>
                <label class="cursor-pointer bg-mp-gold-dark hover:bg-mp-gold px-5 py-2.5 rounded-lg font-medium text-sm transition-colors">
                  Browse File
                  <input type="file" accept=".xlsx,.xls" @change="onFileSelect" class="hidden" />
                </label>
                <p class="text-white text-xs mt-3">.xlsx or .xls — max 20MB</p>
              </div>

              <div v-else class="flex items-center justify-center gap-4">
                <div class="w-12 h-12 bg-mp-success/50 border border-mp-success rounded-xl flex items-center justify-center">
                  <svg class="w-6 h-6 text-mp-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                </div>
                <div class="text-left">
                  <p class="font-medium text-mp-success">{{ form.file.name }}</p>
                  <p class="text-white text-sm">{{ (form.file.size / 1024 / 1024).toFixed(2) }} MB</p>
                </div>
                <button
                  type="button"
                  @click="form.file = null"
                  class="text-white hover:text-mp-danger ml-2 transition-colors"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
            </div>
            <p v-if="form.errors.file" class="text-mp-danger text-xs mt-2">{{ form.errors.file }}</p>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="form.processing || !form.file || !form.name || !form.model_type"
            class="w-full py-3.5 rounded-xl font-semibold text-white transition-all disabled:opacity-40 disabled:cursor-not-allowed"
            :class="form.model_type === 'simple'
              ? 'bg-mp-gold-dark hover:bg-mp-gold'
              : 'bg-mp-warning hover:bg-mp-warning'"
          >
            <span v-if="form.processing" class="flex items-center justify-center gap-2">
              <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
              </svg>
              Uploading...
            </span>
            <span v-else>
              Upload {{ form.model_type === 'simple' ? 'Simple Model' : form.model_type === 'complex' ? 'Complex Model' : 'Model' }}
            </span>
          </button>

        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({ company: Object })

const form = useForm({
  name:       '',
  model_type: 'complex',  // default to complex
  version:    '',
  notes:      '',
  file:       null,
})

function onFileSelect(e) {
  form.file = e.target.files[0] || null
}

function onDrop(e) {
  const file = e.dataTransfer.files[0]
  if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
    form.file = file
  }
}

function submit() {
  form.post(
    route('financial-planning.process-upload', props.company.id),
    { forceFormData: true }
  )
}
</script>