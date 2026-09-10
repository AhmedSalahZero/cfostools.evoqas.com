<template>
  <div>
    <div class="flex items-center justify-between mb-2">
      <label class="block text-xs font-semibold text-white uppercase tracking-widest">{{ label }}</label>
      <button v-if="modelValue" type="button" @click="clear" class="text-mp-muted hover:underline text-xs">Clear</button>
    </div>

    <div class="bg-mp-card-hover border border-mp-border rounded-lg p-2">
      <input v-model="search" @input="onSearchInput" type="text" placeholder="Search all items…"
        class="w-full bg-mp-page border border-mp-border rounded px-2 py-1.5 text-xs text-mp-text-secondary mb-2 focus:outline-none focus:ring-1 focus:ring-mp-teal" />

      <div class="max-h-56 overflow-y-auto space-y-0.5">
        <div v-if="loading" class="text-mp-muted text-xs px-2 py-3 text-center">Loading…</div>
        <label v-for="item in displayItems" :key="item.label"
          class="flex items-center justify-between gap-2 px-2 py-1.5 rounded hover:bg-mp-page cursor-pointer text-xs">
          <span class="flex items-center gap-2 truncate">
            <input type="radio" :name="radioName" :checked="modelValue === item.label" @change="select(item.label)"
              class="border-mp-border" />
            <span class="text-mp-text-secondary truncate">{{ item.label }}</span>
          </span>
          <span class="text-mp-muted flex-shrink-0 ml-2">{{ fmtShort(item.value) }}</span>
        </label>
        <div v-if="!loading && displayItems.length === 0" class="text-mp-muted text-xs px-2 py-3 text-center">
          {{ search.trim() ? 'No matches' : 'No items in this period' }}
        </div>
      </div>
    </div>

    <div class="mt-1.5">
      <span v-if="modelValue" class="text-mp-teal text-xs font-medium">Selected: {{ modelValue }}</span>
      <span v-else class="text-mp-muted text-xs">None selected — the top seller in range will be used automatically</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  companyId:  [String, Number],
  dimension:  String,
  dateFrom:   String,
  dateTo:     String,
  metric:     String,
  modelValue: { type: String, default: null },
  label:      { type: String, default: 'Item' },
})
const emit = defineEmits(['update:modelValue'])

const radioName = `dss-${Math.random().toString(36).slice(2)}`

const topItems    = ref([])
const searchItems = ref([])
const search      = ref('')
const loading     = ref(false)
let searchTimer   = null
let requestToken  = 0

async function loadTop() {
  if (!props.dimension || !props.dateFrom || !props.dateTo) { topItems.value = []; return }
  const token = ++requestToken
  loading.value = true
  try {
    const { data } = await axios.get(route('sales.dimension-items', props.companyId), {
      params: {
        dimension: props.dimension,
        date_from: props.dateFrom,
        date_to:   props.dateTo,
        metric:    props.metric,
        limit:     300,
      },
    })
    if (token === requestToken) topItems.value = (data.items || []).slice(0, 300)
  } finally {
    if (token === requestToken) loading.value = false
  }
}

async function runSearch() {
  if (!search.value.trim()) { searchItems.value = []; return }
  const token = ++requestToken
  loading.value = true
  try {
    const { data } = await axios.get(route('sales.dimension-items', props.companyId), {
      params: {
        dimension: props.dimension,
        date_from: props.dateFrom,
        date_to:   props.dateTo,
        metric:    props.metric,
        search:    search.value.trim(),
      },
    })
    if (token === requestToken) searchItems.value = data.items || []
  } finally {
    if (token === requestToken) loading.value = false
  }
}

function onSearchInput() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(runSearch, 300)
}

const displayItems = computed(() => (search.value.trim() ? searchItems.value : topItems.value))

function select(label) {
  emit('update:modelValue', label)
}
function clear() {
  emit('update:modelValue', null)
}

function fmtShort(v) {
  const n = parseFloat(v) || 0
  if (Math.abs(n) >= 1e6) return (n / 1e6).toFixed(1) + 'M'
  if (Math.abs(n) >= 1e3) return (n / 1e3).toFixed(1) + 'K'
  return n.toFixed(0)
}

watch(() => [props.dimension, props.dateFrom, props.dateTo, props.metric], loadTop, { immediate: true })
</script>
