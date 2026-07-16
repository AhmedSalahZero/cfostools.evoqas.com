<template>
  <div>
    <select
      v-if="!isCustom"
      v-model="selected"
      @change="onSelectChange"
      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition"
    >
      <option value="" disabled>Select service…</option>
      <optgroup v-if="options.length" label="Existing services">
        <option v-for="name in options" :key="name" :value="name">{{ name }}</option>
      </optgroup>
      <option value="__new__">+ Add new service</option>
    </select>

    <input
      v-else
      :value="modelValue"
      type="text"
      placeholder="e.g. Strategic Advisory"
      @input="$emit('update:modelValue', $event.target.value)"
      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition"
    />

    <button
      v-if="isCustom && options.length"
      type="button"
      @click="switchToSelect"
      class="text-xs text-mp-teal hover:text-white mt-1 transition-colors"
    >
      ← Pick from existing
    </button>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue:      { type: String, default: '' },
  existingNames:   { type: Array, default: () => [] },
  namesFromAbove:  { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const isCustom = ref(false)
const selected = ref('')

const options = computed(() => {
  const set = new Set([
    ...props.existingNames,
    ...props.namesFromAbove.filter(n => n && n.trim()),
  ])
  if (props.modelValue) set.add(props.modelValue)
  return [...set].sort((a, b) => a.localeCompare(b))
})

watch(() => props.modelValue, (val) => {
  if (!val) {
    selected.value = ''
    isCustom.value = false
    return
  }
  if (options.value.includes(val) && !isCustom.value) {
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
  selected.value = props.modelValue && options.value.includes(props.modelValue)
    ? props.modelValue
    : ''
}
</script>
