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

      <!-- Resumed draft notice -->
      <div v-if="resumed" class="mb-6 flex items-start gap-3 bg-mp-success/15 border border-mp-success/50 rounded-2xl px-5 py-4">
        <svg class="w-5 h-5 text-mp-success flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div>
          <p class="text-mp-success text-sm font-semibold">Welcome back — your answers were restored.</p>
          <p class="text-white/70 text-xs mt-0.5">
            {{ answeredCount }} of {{ questions.length }} question{{ questions.length !== 1 ? 's' : '' }} answered so far. Pick up where you left off.
          </p>
        </div>
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
          <!-- Section header -->
          <div v-if="shouldShowSection(q, qi)" class="pt-2">
            <h3 class="text-lg font-bold text-mp-gold border-b border-mp-gold/30 pb-2">
              {{ sectionTitle(q.survey_section_id) }}
            </h3>
          </div>

          <div :id="`question-${q.id}`"
            class="bg-mp-card border border-mp-border rounded-2xl p-6"
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

            <!-- Matrix -->
            <div v-else-if="q.question_type === 'matrix'" class="overflow-x-auto">
              <table class="w-full min-w-[480px] text-sm">
                <thead>
                  <tr>
                    <th class="text-left text-white/70 font-normal pb-3 pr-4"></th>
                    <th v-for="opt in q.options" :key="opt.id"
                      class="text-center text-white/80 font-medium pb-3 px-2 min-w-[80px]">
                      {{ opt.option_text }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in q.matrix_rows" :key="row.id" class="border-t border-mp-border/50">
                    <td class="py-3 pr-4 text-white align-middle">{{ row.row_text }}</td>
                    <td v-for="opt in q.options" :key="opt.id" class="text-center py-3 px-2">
                      <button type="button"
                        @click="setMatrixAnswer(q.id, row.id, opt.id)"
                        :class="matrixSelected(q.id, row.id, opt.id) ? 'bg-mp-gold border-mp-gold' : 'bg-mp-card-hover border-mp-border hover:border-mp-gold/50'"
                        class="w-5 h-5 rounded-full border mx-auto transition-colors">
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
              <p v-if="errors[q.id]" class="text-mp-danger text-xs mt-2">Please answer all required rows.</p>
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

      <!-- Save & continue later -->
      <button @click="saveDraft" :disabled="savingDraft || submitting" type="button"
        class="w-full mt-3 bg-mp-card hover:bg-mp-card-hover border border-mp-border disabled:opacity-50 text-white font-medium py-3.5 rounded-2xl text-sm transition-colors flex items-center justify-center gap-2">
        <svg v-if="savingDraft" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-4-4v8m0 0l3-3m-3 3l-3-3" />
        </svg>
        {{ savingDraft ? 'Saving…' : 'Save & Continue Later' }}
      </button>

      <p v-if="draftError" class="text-center text-mp-danger text-xs mt-3">{{ draftError }}</p>

      <p class="text-center text-white text-xs mt-4">Your response will be recorded anonymously unless you provided your name above.</p>
    </div>

    <!-- Progress saved -->
    <div v-if="showSavedModal"
      class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-mp-card border border-mp-border rounded-2xl shadow-2xl w-full max-w-md">

        <div class="p-6 text-center">
          <div class="w-14 h-14 bg-mp-success/25 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-mp-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h3 class="text-white font-bold text-lg mb-2">Progress saved</h3>
          <p class="text-white/80 text-sm mb-3">
            {{ answeredCount }} of {{ questions.length }} question{{ questions.length !== 1 ? 's' : '' }} answered.
          </p>
          <p class="text-white/70 text-sm">
            You can close this page and come back whenever you like — just open
            <span class="text-white font-medium">the same survey link</span> in this browser
            and your answers will be waiting.
          </p>
        </div>

        <div class="px-6 pb-6">
          <button @click="showSavedModal = false" type="button"
            class="w-full px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
            Keep Answering
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'

const props = defineProps({
  survey: Object,
  questions: Array,
  sections: { type: Object, default: () => ({}) },
  draft: { type: Object, default: null },
})

const nameLocked = !!props.survey.default_respondent_name
const titleLocked = !!props.survey.default_respondent_title
const companyLocked = !!props.survey.default_respondent_company
const showAge = !!props.survey.show_respondent_age
const showGender = !!props.survey.show_respondent_gender

const draftRespondent = props.draft?.respondent ?? {}

const respondent = reactive({
  // A locked default set by the survey owner always wins over the draft
  name: props.survey.default_respondent_name ?? draftRespondent.name ?? '',
  title: props.survey.default_respondent_title ?? draftRespondent.title ?? '',
  company: props.survey.default_respondent_company ?? draftRespondent.company ?? '',
  age: draftRespondent.age ?? '',
  gender: draftRespondent.gender ?? null,
})
const answers = reactive({ ...(props.draft?.answers ?? {}) })
const errors = reactive({})
const submitting = ref(false)

// ── Save & continue later ────────────────────────────────────────────────────
// The draft is tied to this browser by a cookie the server sets, so the
// respondent reopens the exact same survey link to carry on.
const savingDraft = ref(false)
const draftError = ref('')
const showSavedModal = ref(false)
const resumed = ref(!!props.draft)

const respondentPayload = () => ({
  name: respondent.name || null,
  title: respondent.title || null,
  company: respondent.company || null,
  age: respondent.age || null,
  gender: respondent.gender || null,
})

const saveDraft = async () => {
  if (savingDraft.value) return

  savingDraft.value = true
  draftError.value = ''

  try {
    await window.axios.post(
      `/s/${props.survey.link_token}/draft`,
      { respondent: respondentPayload(), answers },
      { withXSRFToken: true },
    )
    showSavedModal.value = true
  } catch (error) {
    draftError.value = error?.response?.data?.message || 'Could not save your progress. Please try again.'
  } finally {
    savingDraft.value = false
  }
}

// Long surveys: jumping to the very top hides which question actually failed
const scrollToFirstError = () => {
  const firstInvalid = props.questions.find(q => errors[q.id])
  const el = firstInvalid ? document.getElementById(`question-${firstInvalid.id}`) : null

  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
  else window.scrollTo({ top: 0, behavior: 'smooth' })
}

const answeredCount = computed(
  () => props.questions.filter(q => isAnswered(q)).length
)

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

const sectionTitle = (sectionId) => {
  if (!sectionId) return ''
  const section = props.sections?.[sectionId] ?? props.sections?.[String(sectionId)]
  return section?.title ?? ''
}

const shouldShowSection = (q, qi) => {
  if (!q.survey_section_id) return false
  const prev = props.questions[qi - 1]
  return !prev || prev.survey_section_id !== q.survey_section_id
}

const ensureMatrixAnswers = (qId) => {
  if (!answers[qId] || typeof answers[qId] !== 'object' || Array.isArray(answers[qId])) {
    answers[qId] = {}
  }
  return answers[qId]
}

const setMatrixAnswer = (qId, rowId, optId) => {
  ensureMatrixAnswers(qId)[rowId] = optId
}

const matrixSelected = (qId, rowId, optId) => {
  const rowAnswers = answers[qId]
  return rowAnswers && String(rowAnswers[rowId]) === String(optId)
}

const isAnswered = (q) => {
  const value = answers[q.id]
  if (q.question_type === 'mcq_multi') return Array.isArray(value) && value.length > 0
  if (q.question_type === 'matrix') {
    const rows = q.matrix_rows ?? []
    if (!rows.length) return true
    const rowAnswers = value && typeof value === 'object' ? value : {}
    return rows.every(row => !!rowAnswers[row.id])
  }
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
    scrollToFirstError()
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
  }, {
    onFinish: () => { submitting.value = false },
  })
}
</script>