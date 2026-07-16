<template>
  <div class="bg-mp-card rounded-xl border border-mp-border p-6">
    <p class="text-xs font-semibold text-white uppercase tracking-widest mb-6">Contract Information</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

      <!-- Name -->
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
          Contract Name <span class="text-mp-danger">*</span>
        </label>
        <input v-model="localForm.name" type="text" placeholder="e.g. Annual Consulting Services 2026"
          class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
        <p v-if="errors.name" class="text-mp-danger text-xs mt-1">{{ errors.name }}</p>
      </div>

      <!-- Code (read-only on edit) -->
      <div v-if="localForm.code" class="md:col-span-2">
        <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Contract Code</label>
        <p class="text-white font-mono text-sm bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3">{{ localForm.code }}</p>
      </div>

      <!-- Start Date -->
      <div>
        <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Start Date</label>
        <input type="date" v-model="localForm.start_date"
          class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
        <p v-if="errors.start_date" class="text-mp-danger text-xs mt-1">{{ errors.start_date }}</p>
      </div>

      <!-- End Date -->
      <div>
        <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">End Date</label>
        <input type="date" v-model="localForm.end_date"
          class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
        <p v-if="errors.end_date" class="text-mp-danger text-xs mt-1">{{ errors.end_date }}</p>
      </div>

      <!-- Currency -->
      <div>
        <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Currency <span class="text-mp-danger">*</span></label>
        <select v-model="localForm.currency"
          class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition">
          <option value="EGP">EGP — Egyptian Pound</option>
          <option value="USD">USD — US Dollar</option>
          <option value="EUR">EUR — Euro</option>
          <option value="GBP">GBP — British Pound</option>
          <option value="SAR">SAR — Saudi Riyal</option>
          <option value="AED">AED — UAE Dirham</option>
        </select>
        <p v-if="errors.currency" class="text-mp-danger text-xs mt-1">{{ errors.currency }}</p>
      </div>

      <!-- Notes -->
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Notes</label>
        <textarea v-model="localForm.notes" rows="3" placeholder="Any notes about this contract..."
          class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition resize-none"></textarea>
        <p v-if="errors.notes" class="text-mp-danger text-xs mt-1">{{ errors.notes }}</p>
      </div>

    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: Object,
  errors:     { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])

const localForm = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})
</script>
