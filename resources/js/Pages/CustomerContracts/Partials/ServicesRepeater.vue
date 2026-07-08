<template>
  <div class="bg-mp-card rounded-xl border border-mp-border p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <p class="text-xs font-semibold text-white uppercase tracking-widest">Services</p>
        <p class="text-xs text-white/50 mt-1">Each service contributes to the total contract value</p>
      </div>
      <button type="button" @click="addService"
        class="flex items-center gap-2 bg-mp-teal/20 hover:bg-mp-teal text-mp-teal hover:text-white text-xs font-medium px-3 py-2 rounded-lg transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Add Service
      </button>
    </div>

    <p v-if="errors['services']" class="text-mp-danger text-xs mb-4">{{ errors['services'] }}</p>

    <div class="space-y-4">
      <div v-for="(svc, idx) in localServices" :key="idx"
        class="bg-mp-page/50 border border-mp-border/60 rounded-xl p-4 relative">

        <div class="flex items-center justify-between mb-4">
          <span class="text-xs font-semibold text-white/50 uppercase tracking-widest">Service {{ idx + 1 }}</span>
          <div class="flex items-center gap-2">
            <button type="button" @click="openExecution(idx)"
              class="text-xs font-medium px-2.5 py-1.5 rounded-lg bg-mp-teal/20 hover:bg-mp-teal text-mp-teal hover:text-white transition-colors">
              Execution Details
            </button>
            <button type="button" v-if="localServices.length > 1" @click="removeService(idx)"
              class="w-6 h-6 flex items-center justify-center rounded-lg bg-mp-danger/20 hover:bg-mp-danger text-mp-danger hover:text-white transition-colors">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-white/70 uppercase tracking-widest mb-1.5">
              Service Name <span class="text-mp-danger">*</span>
            </label>
            <ServiceNameSelect
              v-model="svc.name"
              :existing-names="existingServices"
              :names-from-above="namesFromAbove(idx)"
            />
            <p v-if="errors[`services.${idx}.name`]" class="text-mp-danger text-xs mt-1">{{ errors[`services.${idx}.name`] }}</p>
            <p v-if="svc.milestones?.length" class="text-xs text-mp-teal mt-1">
              {{ svc.milestones.length }} execution milestone(s) · {{ milestonePct(svc) }}% total
            </p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-white/70 uppercase tracking-widest mb-1.5">
              Amount ({{ currency }}) <span class="text-mp-danger">*</span>
            </label>
            <input v-model="svc.amount" type="text" placeholder="e.g. 500,000"
              @input="onAmountInput(svc)"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
            <p v-if="errors[`services.${idx}.amount`]" class="text-mp-danger text-xs mt-1">{{ errors[`services.${idx}.amount`] }}</p>
          </div>

          <div>
            <label class="block text-xs font-semibold text-white/70 uppercase tracking-widest mb-1.5">Description</label>
            <input v-model="svc.description" type="text" placeholder="Optional details..."
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
          </div>

          <div>
            <label class="block text-xs font-semibold text-white/70 uppercase tracking-widest mb-1.5">Start Date</label>
            <input v-model="svc.start_date" type="date"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
          </div>

          <div>
            <label class="block text-xs font-semibold text-white/70 uppercase tracking-widest mb-1.5">End Date</label>
            <input v-model="svc.end_date" type="date"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
          </div>

        </div>
      </div>
    </div>

    <div class="mt-4 flex items-center justify-end gap-2 text-sm">
      <span class="text-white/50">Services subtotal:</span>
      <span class="font-bold text-white">
        {{ fmtAmt(subtotal) }} <span class="text-xs text-white/50">{{ currency }}</span>
      </span>
    </div>

    <ExecutionDetailsModal
      :show="executionModal.show"
      :milestones="executionModal.milestones"
      :service-amount="executionModal.amount"
      :currency="currency"
      @close="executionModal.show = false"
      @save="onExecutionSave"
    />
  </div>
</template>

<script setup>
import { computed, reactive } from 'vue'
import ServiceNameSelect from './ServiceNameSelect.vue'
import ExecutionDetailsModal from './ExecutionDetailsModal.vue'

const props = defineProps({
  modelValue:       Array,
  currency:         { type: String, default: 'EGP' },
  errors:           { type: Object, default: () => ({}) },
  existingServices: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue'])

const localServices = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const executionModal = reactive({
  show: false,
  idx: 0,
  milestones: [],
  amount: 0,
})

const subtotal = computed(() =>
  props.modelValue.reduce((sum, s) => sum + (parseFloat(s.amount) || 0), 0)
)

function namesFromAbove(idx) {
  return props.modelValue.slice(0, idx).map(s => s.name).filter(Boolean)
}

function milestonePct(svc) {
  return (svc.milestones || []).reduce((sum, m) => sum + (parseFloat(m.execution_percentage) || 0), 0).toFixed(1)
}

function fmtAmt(v) {
  return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function emptyService() {
  return { name: '', description: '', amount: '', start_date: '', end_date: '', milestones: [] }
}

function addService() {
  emit('update:modelValue', [...props.modelValue, emptyService()])
}

function removeService(idx) {
  emit('update:modelValue', props.modelValue.filter((_, i) => i !== idx))
}

function onAmountInput(svc) {
  svc.amount = svc.amount.replace(/[^0-9.]/g, '')
  recalcMilestoneAmounts(svc)
}

function recalcMilestoneAmounts(svc) {
  const amt = parseFloat(svc.amount) || 0
  if (!svc.milestones?.length) return
  svc.milestones.forEach(m => {
    const pct = parseFloat(m.execution_percentage) || 0
    m.amount = Math.round(amt * pct / 100 * 100) / 100
  })
}

function openExecution(idx) {
  const svc = props.modelValue[idx]
  executionModal.idx = idx
  executionModal.milestones = svc.milestones ? [...svc.milestones] : []
  executionModal.amount = svc.amount
  executionModal.show = true
}

function onExecutionSave(milestones) {
  const updated = [...props.modelValue]
  updated[executionModal.idx] = {
    ...updated[executionModal.idx],
    milestones,
  }
  emit('update:modelValue', updated)
}
</script>
