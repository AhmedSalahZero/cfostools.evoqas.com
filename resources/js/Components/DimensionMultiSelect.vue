<template>
  <div>
    <div class="flex items-center justify-between mb-2">
      <label class="block text-xs font-semibold text-white uppercase tracking-widest">{{ label }}</label>
      <div class="flex items-center gap-2 text-xs">
        <button type="button" @click="selectAll" class="text-mp-teal hover:underline">Select All</button>
        <span class="text-mp-muted">/</span>
        <button type="button" @click="deselectAll" class="text-mp-muted hover:underline">Deselect All</button>
      </div>
    </div>

    <div class="bg-mp-card-hover border border-mp-border rounded-lg p-2">
      <input v-model="search" @input="onSearchInput" type="text" placeholder="Search all items…"
        class="w-full bg-mp-page border border-mp-border rounded px-2 py-1.5 text-xs text-mp-text-secondary mb-2 focus:outline-none focus:ring-1 focus:ring-mp-teal" />

      <div class="max-h-56 overflow-y-auto space-y-0.5">
        <div v-if="loading" class="text-mp-muted text-xs px-2 py-3 text-center">Loading…</div>
        <label v-for="item in displayItems" :key="item.label"
          class="flex items-center justify-between gap-2 px-2 py-1.5 rounded hover:bg-mp-page cursor-pointer text-xs">
          <span class="flex items-center gap-2 truncate">
            <input type="checkbox" :checked="isSelected(item.label)" @change="toggle(item.label)"
              class="rounded border-mp-border" />
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
      <span v-if="modelValue.length" class="text-mp-muted text-xs">{{ modelValue.length }} selected</span>
      <span v-else-if="hasMore"
        class="inline-flex items-center gap-1.5 text-xs text-mp-teal bg-mp-teal/10 border border-mp-teal/30 rounded-full px-2.5 py-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        All items included — report will show Top 500 + Others
      </span>
      <span v-else class="text-mp-muted text-xs">None selected — Select All to include all {{ topItems.length }} items</span>
      <span v-if="!search.trim() && hasMore" class="text-mp-muted text-xs">
        &nbsp;· showing top 500 by value here, search to find others
      </span>
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
  modelValue: { type: Array, default: () => [] },
  label:      { type: String, default: 'Items' },
})
const emit = defineEmits(['update:modelValue'])

const topItems    = ref([])
const hasMore     = ref(false)
const searchItems = ref([])
const search      = ref('')
const loading     = ref(false)
let searchTimer   = null
let requestToken   = 0

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
        limit:     301, // ask for one extra so we can tell if there are truly more than 300
      },
    })
    if (token === requestToken) {
      const items = data.items || []
      hasMore.value = items.length > 300
      topItems.value = items.slice(0, 300)
    }
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

function isSelected(label) {
  return props.modelValue.includes(label)
}
function toggle(label) {
  const set = new Set(props.modelValue)
  set.has(label) ? set.delete(label) : set.add(label)
  emit('update:modelValue', Array.from(set))
}
// "Select All": if every item fits under 300, actually check them all so the
// boxes visibly reflect it — same report result either way, but this makes
// clicking it feel like it did something. Past 300 items, checking only the
// visible top 300 would wrongly hide the "Others" bucket, so we instead just
// clear the filter and let the report apply its own Top 300 + Others.
function selectAll() {
  if (hasMore.value) {
    emit('update:modelValue', [])
  } else {
    emit('update:modelValue', topItems.value.map(i => i.label))
  }
}
function deselectAll() {
  emit('update:modelValue', [])
}

function fmtShort(v) {
  const n = parseFloat(v) || 0
  if (Math.abs(n) >= 1e6) return (n / 1e6).toFixed(1) + 'M'
  if (Math.abs(n) >= 1e3) return (n / 1e3).toFixed(1) + 'K'
  return n.toFixed(0)
}

watch(() => [props.dimension, props.dateFrom, props.dateTo, props.metric], loadTop, { immediate: true })
</script>