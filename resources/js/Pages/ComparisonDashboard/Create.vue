<template>
  <Head title="New Comparison Dashboard" />
  <AuthenticatedLayout>
    <div class="max-w-5xl mx-auto px-6 py-8">
      <div class="flex items-center gap-3 mb-6">
        <Link :href="route('comparison-dashboard.index', company.id)" class="text-mp-muted hover:text-mp-text-secondary text-sm">← Comparison Dashboards</Link>
      </div>

      <h1 class="text-xl font-bold text-mp-text-secondary mb-1">New Comparison Dashboard</h1>
      <p class="text-sm text-mp-muted mb-8">Pick any 2 to 5 periods to compare — a quarter vs another quarter, this year vs last, whatever fits.</p>

      <div class="bg-mp-card border border-mp-border rounded-2xl p-6 space-y-6">
        <div>
          <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Name</label>
          <input v-model="form.name" type="text" placeholder="e.g. Q3 2026 Board Update"
            class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
        </div>

        <div>
          <div class="flex items-center justify-between mb-3">
            <label class="block text-xs font-semibold text-white uppercase tracking-widest">Periods to Compare</label>
            <select v-model.number="periodCount" class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-1.5 text-mp-text-secondary text-xs focus:outline-none focus:ring-2 focus:ring-mp-teal">
              <option :value="2">2 Periods</option>
              <option :value="3">3 Periods</option>
              <option :value="4">4 Periods</option>
              <option :value="5">5 Periods</option>
            </select>
          </div>

          <div class="space-y-3">
            <div v-for="(p, i) in form.periods" :key="i" class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-mp-card-hover/50 border border-mp-border rounded-lg p-4">
              <div>
                <label class="block text-xs text-mp-muted mb-1.5">Period {{ i+1 }} Label</label>
                <input v-model="p.label" type="text" placeholder="e.g. Q3 2025"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
              </div>
              <div>
                <label class="block text-xs text-mp-muted mb-1.5">From</label>
                <input v-model="p.from" type="date"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
              </div>
              <div>
                <label class="block text-xs text-mp-muted mb-1.5">To</label>
                <input v-model="p.to" type="date"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button @click="submit" :disabled="!canSubmit || saving"
            class="bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 disabled:cursor-not-allowed text-mp-text-secondary text-sm font-medium px-6 py-2.5 rounded-lg transition-colors">
            {{ saving ? 'Creating…' : 'Create Dashboard' }}
          </button>
          <span v-if="error" class="text-mp-danger text-xs">{{ error }}</span>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({ company: Object })

const periodCount = ref(2)
const form = ref({
  name: '',
  periods: [{ label: '', from: '', to: '' }, { label: '', from: '', to: '' }],
})
const saving = ref(false)
const error  = ref('')

watch(periodCount, (n) => {
  while (form.value.periods.length < n) form.value.periods.push({ label: '', from: '', to: '' })
  while (form.value.periods.length > n) form.value.periods.pop()
})

const canSubmit = computed(() =>
  form.value.name.trim().length > 0 &&
  form.value.periods.every(p => p.label.trim() && p.from && p.to)
)

function submit() {
  saving.value = true
  error.value = ''
  router.post(route('comparison-dashboard.store', props.company.id), form.value, {
    onError: (errs) => { error.value = Object.values(errs)[0] || 'Something went wrong.'; saving.value = false },
    onFinish: () => { saving.value = false },
  })
}
</script>
