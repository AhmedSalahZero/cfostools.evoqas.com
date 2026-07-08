<template>
  <Head :title="`P&L Mapping — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <Link :href="`/portfolio-companies/${company.id}`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to {{ company.name }}
          </Link>
          <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white">P&L Category Mapping</h1>
              <p class="text-white text-sm mt-1">{{ company.name }} — Assign each expense category to its P&L line. Done once, reused everywhere.</p>
            </div>
            <div class="flex items-center gap-3">
              <Link :href="`/companies/${company.id}/profitability`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Dashboard
              </Link>
              <button @click="saveAll" :disabled="saving"
                class="flex items-center gap-2 bg-mp-success hover:bg-mp-success disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ saving ? 'Saving...' : 'Save Mappings' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Success flash -->
        <div v-if="saved" class="bg-mp-success/40 border border-mp-success rounded-xl px-5 py-3 flex items-center gap-3">
          <svg class="w-5 h-5 text-mp-success flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          <p class="text-mp-success text-sm font-medium">Mappings saved successfully!</p>
        </div>

        <!-- Info box -->
        <div class="bg-mp-teal-subtle/30 border border-mp-teal/50 rounded-xl p-5">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">How the P&L waterfall is built</p>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <div v-for="(label, key) in plLines" :key="key"
              class="flex items-center gap-2.5 bg-mp-card/60 rounded-lg px-3 py-2">
              <span class="w-3 h-3 rounded-full flex-shrink-0" :style="`background:${lineColor(key)}`"></span>
              <div>
                <p class="text-xs font-semibold text-white">{{ lineShort(key) }}</p>
                <p class="text-xs text-white mt-0.5 leading-tight">{{ label }}</p>
              </div>
            </div>
          </div>
          <div class="mt-4 text-xs text-white leading-relaxed">
            <strong class="text-white">Formula:</strong>
            Revenue − COGS = <span class="text-mp-success">Gross Profit</span> →
            − OpEx − Other = <span class="text-white">EBITDA</span> →
            − D&A = <span class="text-white">EBIT</span> →
            − Interest = <span class="text-mp-warning">EBT</span> →
            − Tax = <span class="text-mp-success">Net Profit</span>
          </div>
        </div>

        <!-- ── Bulk actions ── -->
        <div class="flex items-center gap-3 flex-wrap">
          <p class="text-xs text-white uppercase tracking-widest font-semibold">Bulk assign:</p>
          <button v-for="(label, key) in plLines" :key="key"
            @click="assignAll(key)"
            class="text-xs px-3 py-1.5 rounded-lg border transition-colors font-medium"
            :style="`border-color:${lineColor(key)}40; color:${lineColor(key)}; background:${lineColor(key)}15`">
            All → {{ lineShort(key) }}
          </button>
        </div>

        <!-- ── Category table ── -->
        <div class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
          <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">
              {{ categories.length }} Expense Categories
            </p>
            <p class="text-xs text-white">{{ unmappedCount }} unmapped</p>
          </div>

          <div class="divide-y divide-gray-800">
            <div v-for="cat in categories" :key="cat"
              class="flex items-center justify-between px-6 py-4 hover:bg-mp-card-hover/40 transition-colors group">

              <!-- Category name -->
              <div class="flex items-center gap-3 min-w-0 flex-1">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                  :style="`background:${localMappings[cat] ? lineColor(localMappings[cat]) : '#1490a8'}`"></span>
                <span class="text-white font-medium text-sm truncate">{{ cat }}</span>
              </div>

              <!-- P&L line selector — pill buttons -->
              <div class="flex items-center gap-1.5 flex-wrap justify-end ml-4">
                <button v-for="(label, key) in plLines" :key="key"
                  @click="localMappings[cat] = key"
                  class="text-xs px-3 py-1.5 rounded-full border transition-all font-medium whitespace-nowrap"
                  :class="localMappings[cat] === key
                    ? 'text-white border-transparent'
                    : 'text-white border-mp-border hover:border-mp-border hover:text-white bg-transparent'"
                  :style="localMappings[cat] === key ? `background:${lineColor(key)}; border-color:${lineColor(key)}` : ''">
                  {{ lineShort(key) }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Save button (bottom) -->
        <div class="flex justify-end pt-2">
          <button @click="saveAll" :disabled="saving"
            class="flex items-center gap-2 bg-mp-success hover:bg-mp-success disabled:opacity-50 text-white font-medium px-8 py-3 rounded-xl transition-colors text-sm">
            <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            {{ saving ? 'Saving...' : 'Save All Mappings' }}
          </button>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'

const props = defineProps({
  company:    Object,
  categories: Array,
  mappings:   Object,
  plLines:    Object,
})

const saving = ref(false)
const saved  = ref(false)

// Local copy of mappings — reactive object keyed by category
const localMappings = reactive({ ...props.mappings })

const unmappedCount = computed(() =>
  props.categories.filter(c => !localMappings[c]).length
)

const LINE_COLORS = {
  cogs:     '#ef4444',
  opex:     '#f59e0b',
  da:       '#c9a84c',
  interest: '#00b4c8',
  tax:      '#00b4c8',
  other:    '#64748b',
}

const LINE_SHORT = {
  cogs:     'COGS',
  opex:     'OpEx',
  da:       'D&A',
  interest: 'Interest',
  tax:      'Tax',
  other:    'Other',
}

function lineColor(key) { return LINE_COLORS[key] ?? '#64748b' }
function lineShort(key) { return LINE_SHORT[key]  ?? key }

function assignAll(plLine) {
  props.categories.forEach(cat => { localMappings[cat] = plLine })
}

async function saveAll() {
  saving.value = true
  saved.value  = false
  try {
    await axios.post(`/companies/${props.company.id}/profitability/mappings`, {
      mappings: { ...localMappings }
    })
    saved.value = true
    setTimeout(() => { saved.value = false }, 3000)
  } catch (e) {
    console.error(e)
  } finally {
    saving.value = false
  }
}
</script>