<template>
  <Head title="My Tasks" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <h1 class="text-2xl font-bold text-white">My Tasks</h1>
          <p class="text-white text-sm mt-1">
            {{ tasks.length }} assigned task{{ tasks.length !== 1 ? 's' : '' }}
            <span v-if="unseenCount > 0"> · {{ unseenCount }} new</span>
          </p>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div v-if="tasks.length === 0" class="bg-mp-card border border-mp-border rounded-2xl p-12 text-center">
          <div class="text-5xl mb-4">🗂️</div>
          <p class="text-white font-semibold text-lg">No assigned tasks</p>
          <p class="text-white text-sm mt-2">Tasks assigned to you from projects will appear here.</p>
        </div>

        <div v-else class="space-y-3">
          <div v-for="task in tasks" :key="task.id"
            :class="task.seen ? 'border-mp-border' : 'border-mp-teal/60'"
            class="bg-mp-card border rounded-xl p-4">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <h2 class="text-white font-semibold">{{ task.name }}</h2>
                  <span v-if="!task.seen" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-mp-teal text-white">New</span>
                </div>
                <p class="text-white text-sm mt-1">{{ task.company_name }} · {{ task.project_name }}</p>
                <p class="text-white/80 text-xs mt-2">
                  Status: {{ task.status.replaceAll('_', ' ') }} · Priority: {{ task.priority }} · Progress: {{ task.progress_pct }}%
                  <span v-if="task.due_date"> · Due {{ task.due_date }}</span>
                </p>
              </div>

              <Link :href="`/portfolio-companies/${task.company_id}/projects/${task.project_id}`"
                class="px-4 py-2 rounded-lg bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-semibold transition-colors">
                Open Project
              </Link>
            </div>
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
  tasks: { type: Array, default: () => [] },
  unseenCount: { type: Number, default: 0 },
})
</script>
