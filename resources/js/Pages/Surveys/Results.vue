<template>
  <Head :title="`Results — ${survey.title}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <Link :href="`/portfolio-companies/${company.id}/surveys`"
                class="text-white hover:text-white text-sm transition-colors mb-2 inline-flex items-center gap-1">
                ← Surveys
              </Link>
              <h1 class="text-xl font-bold text-white flex items-center gap-2">
                📊 Results: {{ survey.title }}
              </h1>
              <p v-if="survey.prepared_by" class="text-white text-xs mt-0.5">By {{ survey.prepared_by }}</p>
            </div>
            <div class="flex items-center gap-3">
              <span :class="statusBadge(survey.status)" class="text-xs font-semibold px-3 py-1.5 rounded-full uppercase">
                {{ survey.status }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- KPI CARDS -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
          <div class="bg-mp-card border border-mp-border rounded-2xl p-5">
            <p class="text-xs text-white uppercase tracking-widest font-semibold mb-2">Total Responses</p>
            <p class="text-3xl font-bold text-white">{{ demographics.total }}</p>
          </div>
          <div class="bg-mp-card border border-mp-border rounded-2xl p-5">
            <p class="text-xs text-white uppercase tracking-widest font-semibold mb-2">Questions</p>
            <p class="text-3xl font-bold text-white">{{ questions.length }}</p>
          </div>
          <div class="bg-mp-card border border-mp-border rounded-2xl p-5">
            <p class="text-xs text-white uppercase tracking-widest font-semibold mb-2">Companies</p>
            <p class="text-3xl font-bold text-white">{{ Object.keys(demographics.companies ?? {}).length }}</p>
          </div>
          <div class="bg-mp-card border border-mp-border rounded-2xl p-5">
            <p class="text-xs text-white uppercase tracking-widest font-semibold mb-2">Status</p>
            <p class="text-lg font-bold" :class="survey.status === 'active' ? 'text-mp-success' : 'text-mp-danger'">
              {{ survey.status === 'active' ? '🟢 Accepting' : '🔴 Closed' }}
            </p>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

          <!-- QUESTIONS ANALYTICS (left 2/3) -->
          <div class="lg:col-span-2 space-y-6">
            <h2 class="text-sm text-white uppercase tracking-widest font-semibold">Question Analysis</h2>

            <template v-for="(q, qi) in questions" :key="q.id">
              <div class="bg-mp-card border border-mp-border rounded-2xl p-6">
                <div class="flex items-start gap-3 mb-4">
                  <span class="text-white text-sm font-mono mt-0.5">{{ qi + 1 }}.</span>
                  <div>
                    <p class="text-white font-medium text-base leading-snug">{{ q.question_text }}</p>
                    <span :class="typeColor(q.question_type)" class="text-xs font-medium px-2 py-0.5 rounded mt-1 inline-block">{{ typeLabel(q.question_type) }}</span>
                  </div>
                </div>

                <!-- No data -->
                <div v-if="!q.analytics || (Array.isArray(q.analytics) && q.analytics.length === 0)" class="text-white text-sm italic">No responses yet</div>

                <!-- MCQ / Yes/No / Dropdown — bar chart -->
                <div v-else-if="['mcq', 'mcq_multi', 'yes_no', 'dropdown'].includes(q.question_type)" class="space-y-3">
                  <div v-for="opt in q.analytics" :key="opt.label">
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-sm text-white">{{ opt.label }}</span>
                      <span class="text-sm font-semibold text-white">{{ opt.count }} <span class="text-white font-normal">({{ opt.pct }}%)</span></span>
                    </div>
                    <div class="h-3 bg-mp-card-hover rounded-full overflow-hidden">
                      <div class="h-full rounded-full transition-all duration-700"
                        :class="q.question_type === 'yes_no' && opt.label === 'Yes' ? 'bg-mp-success' : q.question_type === 'yes_no' && opt.label === 'No' ? 'bg-mp-danger' : 'bg-mp-gold'"
                        :style="`width: ${opt.pct}%`"></div>
                    </div>
                  </div>
                </div>

                <!-- Rating -->
                <div v-else-if="q.question_type === 'rating'" class="space-y-3">
                  <div class="flex items-center gap-4 mb-4">
                    <div class="text-center">
                      <p class="text-4xl font-bold text-white">{{ q.analytics.avg ?? '—' }}</p>
                      <p class="text-xs text-white mt-1">Average / {{ q.rating_max }}</p>
                    </div>
                    <div class="flex-1">
                      <!-- Star fill bar -->
                      <div class="h-3 bg-mp-card-hover rounded-full overflow-hidden mb-1">
                        <div class="h-full bg-gradient-to-r from-mp-gold to-mp-warning rounded-full transition-all duration-700"
                          :style="`width: ${q.analytics.avg ? (q.analytics.avg / q.rating_max * 100) : 0}%`"></div>
                      </div>
                      <p class="text-xs text-white">{{ q.analytics.total }} responses</p>
                    </div>
                  </div>
                  <div class="space-y-2">
                    <div v-for="d in [...(q.analytics.distribution ?? [])].reverse()" :key="d.label">
                      <div class="flex items-center gap-3">
                        <span class="text-white text-sm font-mono w-5 text-right">{{ d.label }}</span>
                        <div class="flex-1 h-2.5 bg-mp-card-hover rounded-full overflow-hidden">
                          <div class="h-full bg-mp-gold/70 rounded-full" :style="`width: ${d.pct}%`"></div>
                        </div>
                        <span class="text-xs text-white w-14 text-right">{{ d.count }} ({{ d.pct }}%)</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Number -->
                <div v-else-if="q.question_type === 'number'" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                  <template v-for="(label, key) in { avg: 'Average', min: 'Min', max: 'Max', sum: 'Total' }" :key="key">
                    <div class="bg-mp-card-hover rounded-xl p-3 text-center">
                      <p class="text-xs text-white mb-1">{{ label }}</p>
                      <p class="text-lg font-bold text-white">{{ q.analytics[key] !== null ? Number(q.analytics[key]).toLocaleString() : '—' }}</p>
                    </div>
                  </template>
                  <div class="col-span-full text-xs text-white">{{ q.analytics.total }} responses</div>
                </div>

                <!-- Short text -->
                <div v-else-if="q.question_type === 'short_text'" class="space-y-2">
                  <div v-if="Array.isArray(q.analytics) && q.analytics.length === 0" class="text-white text-sm italic">No text responses</div>
                  <div v-for="(text, ti) in q.analytics.slice(0, showAll[q.id] ? q.analytics.length : 5)" :key="ti"
                    class="bg-mp-card-hover/60 rounded-xl px-4 py-3 text-sm text-white leading-relaxed border border-mp-border/50">
                    "{{ text }}"
                  </div>
                  <button v-if="Array.isArray(q.analytics) && q.analytics.length > 5 && !showAll[q.id]"
                    @click="showAll[q.id] = true"
                    class="text-white hover:text-white text-xs font-medium transition-colors">
                    + Show {{ q.analytics.length - 5 }} more responses
                  </button>
                </div>

              </div>
            </template>
          </div>

          <!-- RESPONDENT PANEL (right 1/3) -->
          <div class="space-y-5">

            <!-- Gender donut -->
            <div class="bg-mp-card border border-mp-border rounded-2xl p-5">
              <p class="text-xs text-white uppercase tracking-widest font-semibold mb-4">Gender Breakdown</p>
              <div v-if="Object.keys(demographics.gender ?? {}).length === 0" class="text-white text-xs">No gender data</div>
              <div v-else class="space-y-2">
                <template v-for="(count, gender) in demographics.gender" :key="gender">
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-white capitalize">{{ genderLabel(gender) }}</span>
                    <span class="text-sm font-semibold text-white">{{ count }}</span>
                  </div>
                  <div class="h-2 bg-mp-card-hover rounded-full overflow-hidden">
                    <div class="h-full rounded-full" :class="genderColor(gender)"
                      :style="`width: ${demographics.total > 0 ? Math.round(count/demographics.total*100) : 0}%`"></div>
                  </div>
                </template>
              </div>
            </div>

            <!-- Age groups -->
            <div class="bg-mp-card border border-mp-border rounded-2xl p-5">
              <p class="text-xs text-white uppercase tracking-widest font-semibold mb-4">Age Groups</p>
              <div class="space-y-2">
                <template v-for="(count, group) in demographics.age_groups" :key="group">
                  <div v-if="group !== 'Not provided' || count > 0" class="flex items-center gap-3">
                    <span class="text-xs text-white w-20 flex-shrink-0">{{ group }}</span>
                    <div class="flex-1 h-2 bg-mp-card-hover rounded-full overflow-hidden">
                      <div class="h-full bg-mp-teal rounded-full"
                        :style="`width: ${demographics.total > 0 ? Math.round(count/demographics.total*100) : 0}%`"></div>
                    </div>
                    <span class="text-xs text-white w-6 text-right">{{ count }}</span>
                  </div>
                </template>
              </div>
            </div>

            <!-- Top companies -->
            <div v-if="Object.keys(demographics.companies ?? {}).length > 0" class="bg-mp-card border border-mp-border rounded-2xl p-5">
              <p class="text-xs text-white uppercase tracking-widest font-semibold mb-4">Top Companies</p>
              <div class="space-y-2">
                <template v-for="(count, company) in demographics.companies" :key="company">
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-white truncate flex-1 mr-2">{{ company }}</span>
                    <span class="text-xs font-semibold text-white flex-shrink-0">{{ count }}</span>
                  </div>
                </template>
              </div>
            </div>

            <!-- Recent respondents -->
            <div v-if="demographics.recent?.length > 0" class="bg-mp-card border border-mp-border rounded-2xl p-5">
              <p class="text-xs text-white uppercase tracking-widest font-semibold mb-4">Recent Respondents</p>
              <div class="space-y-3">
                <div v-for="r in demographics.recent" :key="r.date" class="flex items-start gap-3">
                  <div class="w-8 h-8 bg-mp-gold/40 rounded-full flex items-center justify-center text-white text-sm flex-shrink-0 font-semibold">
                    {{ r.name.charAt(0).toUpperCase() }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm text-white font-medium truncate">{{ r.name }}</p>
                    <p class="text-xs text-white truncate">{{ r.title }}{{ r.title && r.company ? ' · ' : '' }}{{ r.company }}</p>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({
  company: Object,
  survey: Object,
  questions: Array,
  demographics: Object,
})

const showAll = reactive({})

const statusBadge = (s) => ({
  draft: 'bg-mp-page text-white',
  active: 'bg-mp-success/50 text-mp-success border border-mp-success/50',
  closed: 'bg-mp-danger/40 text-mp-danger border border-mp-danger/40',
}[s] ?? 'bg-mp-page text-white')

const typeLabel = (t) => ({ mcq: 'MCQ', mcq_multi: 'MCQ Multi', yes_no: 'Yes/No', rating: 'Rating', short_text: 'Text', number: 'Number', dropdown: 'Dropdown' }[t] ?? t)
const typeColor = (t) => ({
  mcq: 'bg-mp-teal-subtle/40 text-white',
  mcq_multi: 'bg-mp-teal-subtle/40 text-white',
  yes_no: 'bg-mp-success/40 text-mp-success',
  rating: 'bg-mp-gold/40 text-white',
  short_text: 'bg-mp-gold/40 text-white',
  number: 'bg-mp-teal-subtle/40 text-white',
  dropdown: 'bg-mp-danger/40 text-mp-danger',
}[t] ?? 'bg-mp-card-hover text-white')

const genderLabel = (g) => ({ male: 'Male', female: 'Female', prefer_not_to_say: 'Prefer not to say' }[g] ?? g)
const genderColor = (g) => ({ male: 'bg-mp-teal', female: 'bg-mp-gold', prefer_not_to_say: 'bg-mp-border' }[g] ?? 'bg-mp-border')
</script>