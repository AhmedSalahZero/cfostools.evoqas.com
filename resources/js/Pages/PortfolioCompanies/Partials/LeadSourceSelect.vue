<template>
  <div>
    <select
      v-if="!isCustom"
      v-model="selected"
      @change="onSelectChange"
      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition"
    >
      <option value="">— Select Lead Source —</option>
      <option v-for="source in mergedOptions" :key="source" :value="source">{{ source }}</option>
      <option value="__new__">+ Add more</option>
    </select>

    <input
      v-else
      :value="modelValue"
      type="text"
      placeholder="Enter lead source"
      @input="$emit('update:modelValue', $event.target.value)"
      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition"
    />

    <button
      v-if="isCustom && mergedOptions.length"
      type="button"
      @click="switchToSelect"
      class="text-xs text-mp-teal hover:text-white mt-1 transition-colors"
    >
      ← Pick from list
    </button>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  options:    { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const isCustom = ref(false)
const selected = ref('')

const DEFAULT_OPTIONS = [
  'Direct Contact',
  'Referral',
  'Website',
  'Social Media',
  'Advertisement',
  'Event',
]

const mergedOptions = computed(() => {
  const set = new Set([...DEFAULT_OPTIONS, ...props.options.filter(Boolean)])
  if (props.modelValue) set.add(props.modelValue)
  return [...set]
})

watch(() => props.modelValue, (val) => {
  if (!val) {
    selected.value = ''
    isCustom.value = false
    return
  }
  if (mergedOptions.value.includes(val) && !isCustom.value) {
    selected.value = val
  } else if (val) {
    isCustom.value = true
  }
}, { immediate: true })

function onSelectChange() {
  if (selected.value === '__new__') {
    isCustom.value = true
    emit('update:modelValue', '')
  } else {
    isCustom.value = false
    emit('update:modelValue', selected.value)
  }
}

function switchToSelect() {
  isCustom.value = false
  selected.value = props.modelValue && mergedOptions.value.includes(props.modelValue)
    ? props.modelValue
    : ''
}
</script>
