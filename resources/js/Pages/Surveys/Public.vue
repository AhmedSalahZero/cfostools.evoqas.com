<template>
  <Head :title="survey.title" />
  <div class="min-h-screen bg-mp-page text-white flex flex-col">

    <!-- Top bar -->
    <div class="bg-mp-card border-b border-mp-border px-6 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="text-2xl">📋</span>
        <span class="text-sm text-white font-medium">CFOs Tools Survey</span>
      </div>
      <div class="text-xs text-white">{{ questions.length }} questions</div>
    </div>

    <div class="flex-1 max-w-2xl mx-auto w-full px-4 py-10">

      <!-- Survey header -->
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-white mb-3">{{ survey.title }}</h1>
        <p v-if="survey.prepared_by" class="text-white text-sm mb-3">Prepared by {{ survey.prepared_by }}</p>
        <p v-if="survey.introduction" class="text-white text-sm leading-relaxed bg-mp-card border border-mp-border rounded-xl px-5 py-4">
          {{ survey.introduction }}
        </p>
      </div>

      <!-- Respondent info -->
      <div class="bg-mp-card border border-mp-border rounded-2xl p-6 mb-6">
        <p class="text-xs text-white uppercase tracking-widest font-semibold mb-4">About You <span class="text-white normal-case font-normal">(all optional)</span></p>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs text-white mb-1.5 block">Full Name</label>
            <input v-model="respondent.name" type="text" placeholder="Your name"
              :readonly="nameLocked"
              :class="inputClass(nameLocked)" />
          </div>
          <div>
            <label class="text-xs text-white mb-1.5 block">Job Title</label>
            <input v-model="respondent.title" type="text" placeholder="e.g. CFO"
              :readonly="titleLocked"
              :class="inputClass(titleLocked)" />
          </div>
          <div v-if="companyLocked">
            <label class="text-xs text-white mb-1.5 block">Company</label>
            <input v-model="respondent.company" type="text" placeholder="Company name"
              readonly
              :class="inputClass(true)" />
          </div>
          <div v-if="showAge">
            <label class="text-xs text-white mb-1.5 block">Age</label>
            <input v-model="respondent.age" type="number" placeholder="Your age" min="16" max="100"
              class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-gold placeholder-gray-600" />
          </div>
          <div v-if="showGender" class="col-span-2">
            <label class="text-xs text-white mb-1.5 block">Gender</label>
            <div class="flex gap-3">
              <template v-for="g in genderOptions" :key="g.value">
                <button @click="respondent.gender = g.value"
                  :class="respondent.gender === g.value ? 'bg-mp-gold-dark border-mp-gold text-white' : 'bg-mp-card-hover border-mp-border text-white'"
                  class="px-4 py-2 rounded-lg border text-sm transition-colors">
                  {{ g.label }}
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Questions -->
      <div class="space-y-5 mb-8">
        <template v-for="(q, qi) in questions" :key="q.id">
          <div class="bg-mp-card border border-mp-border rounded-2xl p-6"
            :class="{ 'border-mp-danger/50': errors[q.id] }">

            <!-- Question text -->
            <div class="mb-4">
              <p class="text-white font-medium text-lg leading-snug">
                <span class="text-white text-m font-normal mr-2">{{ qi + 1 }}.</span>
                {{ q.question_text }}
                <span v-if="q.is_required" class="text-mp-danger ml-1 text-sm">*</span>
              </p>
              <p v-if="errors[q.id]" class="text-mp-danger text-xs mt-1">This question is required.</p>
            </div>

            <!-- MCQ -->
            <div v-if="q.question_type === 'mcq'" class="space-y-2">
              <div v-for="opt in q.options" :key="opt.id"
                @click="answers[q.id] = opt.id"
                :class="answers[q.id] === opt.id ? 'bg-mp-gold/30 border-mp-gold/60 text-white' : 'bg-mp-card-hover/50 border-mp-teal text-white hover:border-mp-border'"
                class="flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-all">
                <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                  :class="answers[q.id] === opt.id ? 'border-mp-gold' : 'border-mp-border'">
                  <div v-if="answers[q.id] === opt.id" class="w-2 h-2 rounded-full bg-mp-gold"></div>
                </div>
                <span class="text-m">{{ opt.option_text }}</span>
              </div>
            </div>

            <!-- MCQ multiple selection -->
            <div v-else-if="q.question_type === 'mcq_multi'" class="space-y-2">
              <div v-for="opt in q.options" :key="opt.id"
                @click="toggleMulti(q.id, opt.id)"
                :class="isMultiSelected(q.id, opt.id) ? 'bg-mp-gold/30 border-mp-gold/60 text-white' : 'bg-mp-card-hover/50 border-mp-teal text-white hover:border-mp-border'"
                class="flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-all">
                <div class="w-4 h-4 rounded-sm border-2 flex items-center justify-center flex-shrink-0"
                  :class="isMultiSelected(q.id, opt.id) ? 'border-mp-gold bg-mp-gold' : 'border-mp-border'">
                  <svg v-if="isMultiSelected(q.id, opt.id)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <span class="text-m">{{ opt.option_text }}</span>
              </div>
            </div>

            <!-- Dropdown -->
            <div v-else-if="q.question_type === 'dropdown'">
              <select v-model="answers[q.id]"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-lg focus:outline-none focus:border-mp-teal">
                <option value="">— Select an option —</option>
                <option v-for="opt in q.options" :key="opt.id" :value="opt.id">{{ opt.option_text }}</option>
              </select>
            </div>

            <!-- Yes / No -->
            <div v-else-if="q.question_type === 'yes_no'" class="flex gap-3">
              <div v-for="opt in q.options" :key="opt.id"
                @click="answers[q.id] = opt.id"
                :class="{
                  'bg-mp-success/30 border-mp-success/60 text-mp-success': answers[q.id] === opt.id && opt.option_text === 'Yes',
                  'bg-mp-danger/30 border-mp-danger/60 text-mp-danger': answers[q.id] === opt.id && opt.option_text === 'No',
                  'bg-mp-card-hover/50 border-mp-border text-white hover:border-mp-border': answers[q.id] !== opt.id,
                }"
                class="flex-1 text-center px-4 py-3 rounded-xl border cursor-pointer transition-all font-medium">
                {{ opt.option_text }}
              </div>
            </div>

            <!-- Rating -->
            <div v-else-if="q.question_type === 'rating'" class="flex gap-2 flex-wrap">
              <template v-for="n in q.rating_max" :key="n">
                <button @click="answers[q.id] = String(n)"
                  :class="answers[q.id] === String(n) ? 'bg-mp-gold border-mp-gold text-white font-bold' : 'bg-mp-card-hover border-mp-border text-white text-lg font-normal hover:border-mp-gold/50'"
                  class="w-11 h-11 rounded-xl border text-sm transition-all">
                  {{ n }}
                </button>
              </template>
              <div class="w-full flex justify-between text-xs text-white mt-1">
                <span>Low</span><span>High</span>
              </div>
            </div>

            <!-- Short text -->
            <div v-else-if="q.question_type === 'short_text'">
              <textarea v-model="answers[q.id]" rows="3"
                :placeholder="q.placeholder || 'Type your answer here...'"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-mp-gold placeholder-gray-600 resize-none transition-colors"></textarea>
            </div>

            <!-- Number -->
            <div v-else-if="q.question_type === 'number'">
              <input v-model="answers[q.id]" type="number"
                :placeholder="q.placeholder || 'Enter a number'"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-gold placeholder-gray-600" />
            </div>

          </div>
        </template>
      </div>

      <!-- Submit -->
      <button @click="submitSurvey" :disabled="submitting"
        class="w-full bg-mp-gold-dark hover:bg-mp-gold disabled:opacity-50 text-white font-semibold py-4 rounded-2xl text-base transition-colors">
        <span v-if="submitting">Submitting…</span>
        <span v-else>Submit Response →</span>
      </button>

      <p class="text-center text-white text-xs mt-4">Your response will be recorded anonymously unless you provided your name above.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({
  survey: Object,
  questions: Array,
})

const nameLocked = !!props.survey.default_respondent_name
const titleLocked = !!props.survey.default_respondent_title
const companyLocked = !!props.survey.default_respondent_company
const showAge = !!props.survey.show_respondent_age
const showGender = !!props.survey.show_respondent_gender

const respondent = reactive({
  name: props.survey.default_respondent_name ?? '',
  title: props.survey.default_respondent_title ?? '',
  company: props.survey.default_respondent_company ?? '',
  age: '',
  gender: null,
})
const answers = reactive({})
const errors = reactive({})
const submitting = ref(false)

const inputClass = (readonly) =>
  [
    'w-full border rounded-lg px-4 py-2.5 text-sm placeholder-gray-600',
    readonly
      ? 'bg-mp-page border-mp-border text-white/70 cursor-not-allowed'
      : 'bg-mp-card-hover border-mp-border text-white focus:outline-none focus:border-mp-gold',
  ].join(' ')

const genderOptions = [
  { value: 'male', label: 'Male' },
  { value: 'female', label: 'Female' },
  { value: 'prefer_not_to_say', label: 'Prefer not to say' },
]

const isMultiSelected = (qId, optId) => Array.isArray(answers[qId]) && answers[qId].some(id => String(id) === String(optId))

const toggleMulti = (qId, optId) => {
  const current = Array.isArray(answers[qId]) ? [...answers[qId]] : []
  const idx = current.findIndex(id => String(id) === String(optId))
  if (idx >= 0) current.splice(idx, 1)
  else current.push(optId)
  answers[qId] = current
}

const isAnswered = (q) => {
  const value = answers[q.id]
  if (q.question_type === 'mcq_multi') return Array.isArray(value) && value.length > 0
  return !!value
}

const submitSurvey = async () => {
  // Validate required
  let valid = true
  props.questions.forEach(q => {
    errors[q.id] = false
    if (q.is_required && !isAnswered(q)) {
      errors[q.id] = true
      valid = false
    }
  })
  if (!valid) {
    window.scrollTo({ top: 0, behavior: 'smooth' })
    return
  }

  submitting.value = true
  router.post(`/s/${props.survey.link_token}`, {
    respondent_name:    respondent.name || null,
    respondent_title:   respondent.title || null,
    respondent_company: respondent.company || null,
    respondent_gender:  respondent.gender || null,
    respondent_age:     respondent.age || null,
    answers,
  }, { onFinish: () => { submitting.value = false } })
}
</script>