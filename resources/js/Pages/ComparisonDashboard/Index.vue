<template>
  <Head title="Comparison Dashboards" />
  <AuthenticatedLayout>
    <div class="max-w-7xl mx-auto px-6 py-8">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-xl font-bold text-mp-text-secondary">Comparison Dashboards</h1>
          <p class="text-sm text-mp-muted mt-1">Saved period comparisons — always computed live, shareable by link.</p>
        </div>
        <Link :href="route('comparison-dashboard.create', company.id)"
          class="bg-mp-teal hover:bg-mp-teal-dark text-mp-text-secondary text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
          + New Dashboard
        </Link>
      </div>

      <div v-if="dashboards.length === 0" class="bg-mp-card border border-mp-border rounded-2xl p-10 text-center text-mp-muted text-sm">
        No comparison dashboards yet. Create one to compare any periods and share the results.
      </div>

      <div v-else class="space-y-3">
        <div v-for="d in dashboards" :key="d.id" class="bg-mp-card border border-mp-border rounded-xl p-5 flex items-center justify-between hover:border-mp-teal/50 transition-colors">
          <Link :href="route('comparison-dashboard.show', { company: company.id, dashboard: d.id })" class="flex-1">
            <p class="text-mp-text-secondary font-semibold text-sm">{{ d.name }}</p>
            <p class="text-mp-muted text-xs mt-1">{{ d.periods.length }} periods · {{ d.periods.map(p => p.label).join(' · ') }}</p>
          </Link>
          <div class="flex items-center gap-3">
            <span v-if="d.is_public" class="text-xs text-mp-success bg-mp-success/20 px-2.5 py-1 rounded-full">Shared</span>
            <span class="text-xs text-mp-muted">{{ new Date(d.created_at).toLocaleDateString() }}</span>
            <button @click="remove(d)" class="text-mp-danger hover:text-red-400 text-xs">Delete</button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({ company: Object, dashboards: Array })

function remove(d) {
  if (!confirm(`Delete "${d.name}"? This cannot be undone.`)) return
  router.delete(route('comparison-dashboard.destroy', { company: props.company.id, dashboard: d.id }))
}
</script>
