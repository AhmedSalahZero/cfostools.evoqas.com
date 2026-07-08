<template>
  <Teleport to="body">
    <div v-if="show"
      class="fixed inset-0 z-[110] flex items-center justify-center p-4"
      @click.self="$emit('close')">
      <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
      <div class="relative z-10 w-full max-w-4xl bg-mp-card border border-mp-border rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
          <h3 class="text-lg font-bold text-white">Execution Details</h3>
          <button type="button" @click="$emit('close')" class="text-white/50 hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="p-6 overflow-x-auto">
          <p class="text-xs text-white/50 mb-4">
            Service amount: <span class="text-white font-semibold">{{ fmtAmt(serviceAmount) }} {{ currency }}</span>
            · Total %: <span :class="totalPct > 100 ? 'text-mp-danger' : 'text-mp-teal'" class="font-semibold">{{ totalPct.toFixed(1) }}%</span>
          </p>

          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-mp-border">
                <th class="text-left text-xs font-semibold text-white/50 uppercase tracking-widest px-3 py-2">#</th>
                <th class="text-left text-xs font-semibold text-white/50 uppercase tracking-widest px-3 py-2">Execution %</th>
                <th class="text-left text-xs font-semibold text-white/50 uppercase tracking-widest px-3 py-2">Amount</th>
                <th class="text-left text-xs font-semibold text-white/50 uppercase tracking-widest px-3 py-2">Start Date</th>
                <th class="text-left text-xs font-semibold text-white/50 uppercase tracking-widest px-3 py-2">End Date</th>
                <th class="text-left text-xs font-semibold text-white/50 uppercase tracking-widest px-3 py-2">Collection Days</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in localRows" :key="i" class="border-b border-mp-border/40">
                <td class="px-3 py-2 text-white/40">{{ i + 1 }}</td>
                <td class="px-3 py-2">
                  <input
                    v-model="row.execution_percentage"
                    type="text"
                    placeholder="0"
                    @input="onPctInput(row)"
                    class="w-24 bg-mp-card-hover border border-mp-border rounded-lg px-2 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-teal"
                  />
                </td>
                <td class="px-3 py-2">
                  <input
                    :value="fmtAmt(row.amount)"
                    type="text"
                    readonly
                    class="w-32 bg-mp-page border border-mp-border/50 rounded-lg px-2 py-1.5 text-white/70 text-sm"
                  />
                </td>
                <td class="px-3 py-2">
                  <input v-model="row.start_date" type="date"
                    class="bg-mp-card-hover border border-mp-border rounded-lg px-2 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-teal" />
                </td>
                <td class="px-3 py-2">
                  <input v-model="row.end_date" type="date"
                    class="bg-mp-card-hover border border-mp-border rounded-lg px-2 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-teal" />
                </td>
                <td class="px-3 py-2">
                  <input
                    v-model="row.collection_days"
                    type="text"
                    placeholder="0"
                    @input="row.collection_days = row.collection_days.replace(/[^0-9]/g, '')"
                    class="w-20 bg-mp-card-hover border border-mp-border rounded-lg px-2 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-teal"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-end gap-3 px-6 py-4 border-t border-mp-border">
          <button type="button" @click="$emit('close')"
            class="px-4 py-2.5 rounded-lg bg-mp-card-hover text-white text-sm font-medium transition-colors">
            Cancel
          </button>
          <button type="button" @click="save" :disabled="totalPct > 100"
            class="px-4 py-2.5 rounded-lg bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-semibold transition-colors disabled:opacity-50">
            Save
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  show:           { type: Boolean, default: false },
  milestones:     { type: Array, default: () => [] },
  serviceAmount:  { type: [Number, String], default: 0 },
  currency:       { type: String, default: 'EGP' },
})

const emit = defineEmits(['close', 'save'])

const emptyRow = () => ({
  execution_percentage: '',
  amount: 0,
  start_date: '',
  end_date: '',
  collection_days: '',
})

const localRows = ref(Array.from({ length: 5 }, () => emptyRow()))

watch(() => props.show, (open) => {
  if (!open) return
  const incoming = props.milestones?.length ? props.milestones : []
  localRows.value = Array.from({ length: 5 }, (_, i) => {
    const m = incoming[i]
    if (!m) return emptyRow()
    return {
      execution_percentage: m.execution_percentage != null ? String(m.execution_percentage) : '',
      amount: m.amount ?? 0,
      start_date: m.start_date ?? '',
      end_date: m.end_date ?? '',
      collection_days: m.collection_days != null ? String(m.collection_days) : '',
    }
  })
}, { immediate: true })

const totalPct = computed(() =>
  localRows.value.reduce((sum, r) => sum + (parseFloat(r.execution_percentage) || 0), 0)
)

function onPctInput(row) {
  row.execution_percentage = row.execution_percentage.replace(/[^0-9.]/g, '')
  const pct = parseFloat(row.execution_percentage) || 0
  const amt = parseFloat(props.serviceAmount) || 0
  row.amount = Math.round(amt * pct / 100 * 100) / 100
}

function fmtAmt(v) {
  if (v === null || v === undefined || v === '') return '—'
  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function save() {
  const out = localRows.value
    .map((r, i) => ({
      milestone_index: i + 1,
      execution_percentage: parseFloat(r.execution_percentage) || 0,
      amount: r.amount || 0,
      start_date: r.start_date || null,
      end_date: r.end_date || null,
      collection_days: parseInt(r.collection_days, 10) || 0,
    }))
    .filter(m => m.execution_percentage > 0 || m.start_date || m.end_date || m.collection_days > 0)

  emit('save', out)
  emit('close')
}
</script>
