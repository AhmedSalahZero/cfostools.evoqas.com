<template>
  <Head title="New Survey" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center gap-3 mb-1">
            <Link :href="`/portfolio-companies/${company.id}/surveys`" class="text-white hover:text-white text-sm transition-colors">
              ← {{ company.name }}
            </Link>
          </div>
          <h1 class="text-2xl font-bold text-white">New Survey</h1>
          <p class="text-white text-sm mt-1">Create a blank survey or start from an organization template</p>
        </div>
      </div>

      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <Link :href="`/portfolio-companies/${company.id}/surveys/create?blank=1`"
          class="block bg-mp-card border border-mp-border hover:border-mp-gold/60 rounded-2xl p-6 transition-colors">
          <p class="text-xs text-white uppercase tracking-widest font-semibold mb-2">Start fresh</p>
          <h2 class="text-lg font-bold text-white mb-1">Create a new survey</h2>
          <p class="text-white/70 text-sm">Build questions from scratch, optionally using the Question Bank.</p>
        </Link>

        <div class="bg-mp-card border border-mp-border rounded-2xl p-6">
          <p class="text-xs text-white uppercase tracking-widest font-semibold mb-2">Use a template</p>
          <h2 class="text-lg font-bold text-white mb-1">Choose from existing templates</h2>
          <p class="text-white/70 text-sm mb-5">Templates marked in this organization. Saving creates a new survey for {{ company.name }}.</p>

          <div v-if="templates.length === 0" class="border border-dashed border-mp-border rounded-xl p-8 text-center">
            <p class="text-white font-medium mb-1">No templates yet</p>
            <p class="text-white/60 text-sm">Mark a survey as Template from the builder.</p>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Link v-for="tpl in templates" :key="tpl.id"
              :href="`/portfolio-companies/${company.id}/surveys/create?from_template=${tpl.id}`"
              class="block bg-mp-card-hover/50 hover:bg-mp-card-hover border border-mp-border hover:border-mp-gold/50 rounded-xl p-4 transition-all">
              <h3 class="text-white font-semibold leading-snug mb-1">{{ tpl.title }}</h3>
              <p v-if="tpl.prepared_by" class="text-white/60 text-xs mb-2">By {{ tpl.prepared_by }}</p>
              <p class="text-white/70 text-xs">{{ tpl.question_count }} question{{ Number(tpl.question_count) === 1 ? '' : 's' }}</p>
            </Link>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({
  company: Object,
  templates: Array,
})
</script>
