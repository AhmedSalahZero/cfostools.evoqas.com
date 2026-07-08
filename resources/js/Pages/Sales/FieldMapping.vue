<template>
  <Head :title="`Field Mapping — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <Link :href="`/portfolio-companies/${company.id}`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-2 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to {{ company.name }}
          </Link>
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-bold text-white">Sales Field Mapping</h1>
              <p class="text-white text-sm mt-1">Select the fields that match your company's sales data structure</p>
            </div>
            <!-- Step indicators -->
            <div class="hidden md:flex items-center gap-2">
              <div class="flex items-center gap-1.5 bg-mp-teal text-white text-xs font-semibold px-3 py-1.5 rounded-full">
                <span class="w-4 h-4 rounded-full bg-white text-white flex items-center justify-center text-xs font-bold">1</span>
                Field Mapping
              </div>
              <div class="w-6 h-px bg-mp-border"></div>
              <div class="flex items-center gap-1.5 bg-mp-card-hover text-white text-xs font-semibold px-3 py-1.5 rounded-full">
                <span class="w-4 h-4 rounded-full bg-mp-page flex items-center justify-center text-xs">2</span>
                Upload Data
              </div>
              <div class="w-6 h-px bg-mp-border"></div>
              <div class="flex items-center gap-1.5 bg-mp-card-hover text-white text-xs font-semibold px-3 py-1.5 rounded-full">
                <span class="w-4 h-4 rounded-full bg-mp-page flex items-center justify-center text-xs">3</span>
                Reports
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success" class="bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <!-- Info card -->
        <div class="bg-mp-card border border-mp-border rounded-xl p-4 flex items-start gap-3">
          <svg class="w-5 h-5 text-white flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div class="text-sm text-white">
            <p class="font-medium mb-1">How it works</p>
            <p class="text-white">Select the fields your company uses. Then download the Excel template which will contain only those columns. Fill it with your data and upload it back.</p>
          </div>
        </div>

        <!-- Field selection card -->
        <div class="bg-mp-card rounded-xl border border-mp-border p-6">

          <!-- Header row -->
          <div class="flex items-center justify-between mb-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">
              Available Fields
              <span class="ml-2 bg-mp-teal text-white text-xs px-2 py-0.5 rounded-full">{{ activeCount }} selected</span>
            </p>
            <div class="flex items-center gap-3">
              <button type="button" @click="selectAll"
                class="text-xs text-white hover:text-white transition-colors font-medium">
                Select All
              </button>
              <span class="text-white">|</span>
              <button type="button" @click="deselectAll"
                class="text-xs text-white hover:text-white transition-colors font-medium">
                Deselect All
              </button>
            </div>
          </div>

          <!-- Fields grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <div v-for="field in localFields" :key="field.key"
              @click="toggleField(field.key)"
              :class="field.is_active
                ? 'border-mp-teal bg-mp-teal-subtle/20 text-white'
                : 'border-mp-border bg-mp-card-hover/20 text-white hover:border-mp-border'"
              class="flex items-center gap-3 px-4 py-3 rounded-lg border cursor-pointer transition-all">

              <!-- Checkbox -->
              <div :class="field.is_active ? 'bg-mp-teal border-mp-teal' : 'bg-transparent border-mp-border'"
                class="w-5 h-5 rounded border-2 flex items-center justify-center flex-shrink-0 transition-colors">
                <svg v-if="field.is_active" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
              </div>

              <!-- Field label -->
              <span class="text-sm font-medium">{{ field.label }}</span>

              <!-- Required badge for key financial fields -->
              <span v-if="isKeyField(field.key)"
                class="ml-auto text-xs bg-mp-teal-subtle text-white border border-mp-teal px-2 py-0.5 rounded-full">
                Key
              </span>
            </div>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex flex-col sm:flex-row items-center gap-4">

          <!-- Save mapping button -->
          <button @click="saveMapping" :disabled="saving"
            class="w-full sm:w-auto flex items-center justify-center gap-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 text-white text-sm font-medium px-8 py-3 rounded-lg transition-colors">
            <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ saving ? 'Saving...' : 'Save Field Mapping' }}
          </button>

          <!-- Download template button -->
          <a :href="`/companies/${company.id}/sales/download-template`"
            class="w-full sm:w-auto flex items-center justify-center gap-2 bg-mp-success hover:bg-mp-success text-white text-sm font-medium px-8 py-3 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Download Excel Template
          </a>

          <!-- Go to upload -->
          <Link :href="`/companies/${company.id}/sales/upload`"
            class="w-full sm:w-auto flex items-center justify-center gap-2 bg-mp-card-hover hover:bg-mp-page text-white hover:text-white text-sm font-medium px-8 py-3 rounded-lg transition-colors border border-mp-border">
            Next: Upload Data
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </Link>

        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company: Object,
  fields:  { type: Array, default: () => [] },
})

// Local copy so we can toggle without submitting
const localFields = ref(props.fields.map(f => ({ ...f })))
const saving      = ref(false)

const activeCount = computed(() => localFields.value.filter(f => f.is_active).length)

const KEY_FIELDS = ['date', 'sales_value', 'net_sales_value', 'customer_name', 'quantity']

function isKeyField(key) { return KEY_FIELDS.includes(key) }

function toggleField(key) {
  const f = localFields.value.find(f => f.key === key)
  if (f) f.is_active = !f.is_active
}

function selectAll()   { localFields.value.forEach(f => f.is_active = true) }
function deselectAll() { localFields.value.forEach(f => f.is_active = false) }

const form = useForm({})

function saveMapping() {
  saving.value = true
  form.transform(() => ({
    fields: localFields.value.map(f => ({ key: f.key, active: f.is_active }))
  })).post(route('sales.save-field-mapping', props.company.id), {
    onFinish: () => { saving.value = false }
  })
}
</script>