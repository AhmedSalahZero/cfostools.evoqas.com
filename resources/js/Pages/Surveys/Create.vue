<template>
  <Head :title="isEditing ? 'Edit Survey' : 'New Survey'" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border sticky top-0 z-30">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <Link :href="`/portfolio-companies/${company.id}/surveys`" class="text-white hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
              </Link>
              <h1 class="text-lg font-bold text-white">{{ isEditing ? 'Edit Survey' : 'New Survey' }}</h1>
            </div>
            <div class="flex items-center gap-3">
              <button @click="showBankPanel = !showBankPanel"
                :class="showBankPanel ? 'bg-mp-gold-dark text-white' : 'bg-mp-card-hover text-white'"
                class="flex items-center gap-2 text-sm font-medium px-3 py-2 rounded-lg transition-colors border border-mp-border">
                📚 Question Bank
              </button>
              <button @click="saveSurvey" :disabled="saving"
                class="flex items-center gap-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                <span v-if="saving">Saving…</span>
                <span v-else>{{ isEditing ? 'Update Survey' : 'Save Survey' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex gap-6">

        <!-- MAIN BUILDER -->
        <div class="flex-1 min-w-0">

          <!-- Survey metadata -->
          <div class="bg-mp-card border border-mp-border rounded-2xl p-6 mb-6">
            <p class="text-xs text-white uppercase tracking-widest font-semibold mb-4">Survey Details</p>
            <div class="space-y-4">
              <div>
                <label class="text-xs text-white font-medium mb-1.5 block">Survey Title *</label>
                <input v-model="form.title" type="text" placeholder="e.g. Q1 2026 Investor Satisfaction Survey"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-gold placeholder-gray-600" />
              </div>
              <div>
                <label class="text-xs text-white font-medium mb-1.5 block">Introduction <span class="text-white">(optional)</span></label>
                <textarea v-model="form.introduction" rows="3" placeholder="Brief introduction shown to respondents before they start..."
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-gold placeholder-gray-600 resize-none"></textarea>
              </div>
              <div class="flex gap-4">
                <div class="flex-1">
                  <label class="text-xs text-white font-medium mb-1.5 block">Prepared By <span class="text-white">(optional)</span></label>
                  <input v-model="form.prepared_by" type="text" placeholder="Your name or team"
                    class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-gold placeholder-gray-600" />
                </div>
                <div class="flex items-end pb-0.5">
                  <label class="flex items-center gap-2.5 cursor-pointer">
                    <div @click="form.is_template = !form.is_template"
                      :class="form.is_template ? 'bg-mp-gold' : 'bg-mp-page'"
                      class="w-11 h-6 rounded-full transition-colors relative flex-shrink-0">
                      <div :class="form.is_template ? 'translate-x-5' : 'translate-x-1'"
                        class="absolute top-1 w-4 h-4 bg-white rounded-full transition-transform"></div>
                    </div>
                    <span class="text-sm text-white">Mark as Template</span>
                  </label>
                </div>
              </div>

              <div class="pt-4 mt-2 border-t border-mp-border">
                <p class="text-xs text-white uppercase tracking-widest font-semibold mb-1">About the respondent</p>
                <p class="text-xs text-white/60 mb-4">Optional defaults appear read-only on the public form. Age and Gender stay hidden unless you choose Yes.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label class="text-xs text-white font-medium mb-1.5 block">Full Name</label>
                    <input v-model="form.default_respondent_name" type="text" placeholder="Leave blank for the respondent to type"
                      class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-gold placeholder-gray-600" />
                  </div>
                  <div>
                    <label class="text-xs text-white font-medium mb-1.5 block">Job Title</label>
                    <input v-model="form.default_respondent_title" type="text" placeholder="Leave blank for the respondent to type"
                      class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-gold placeholder-gray-600" />
                  </div>
                  <div class="sm:col-span-2">
                    <label class="text-xs text-white font-medium mb-1.5 block">Company</label>
                    <input v-model="form.default_respondent_company" type="text" placeholder="Leave blank to hide Company on the public form"
                      class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-gold placeholder-gray-600" />
                  </div>
                  <div>
                    <p class="text-xs text-white font-medium mb-2">Show Age</p>
                    <div class="flex gap-3">
                      <label class="flex items-center gap-2 cursor-pointer text-sm text-white">
                        <input type="radio" class="accent-mp-gold" :value="true" v-model="form.show_respondent_age" />
                        Yes
                      </label>
                      <label class="flex items-center gap-2 cursor-pointer text-sm text-white">
                        <input type="radio" class="accent-mp-gold" :value="false" v-model="form.show_respondent_age" />
                        No
                      </label>
                    </div>
                    <p class="text-xs text-white/50 mt-1.5">Optional when shown.</p>
                  </div>
                  <div>
                    <p class="text-xs text-white font-medium mb-2">Show Gender</p>
                    <div class="flex gap-3">
                      <label class="flex items-center gap-2 cursor-pointer text-sm text-white">
                        <input type="radio" class="accent-mp-gold" :value="true" v-model="form.show_respondent_gender" />
                        Yes
                      </label>
                      <label class="flex items-center gap-2 cursor-pointer text-sm text-white">
                        <input type="radio" class="accent-mp-gold" :value="false" v-model="form.show_respondent_gender" />
                        No
                      </label>
                    </div>
                    <p class="text-xs text-white/50 mt-1.5">Optional when shown.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Questions -->
          <div class="space-y-4 mb-6">
            <template v-for="(q, qi) in form.questions" :key="q._id">
              <div class="bg-mp-card border rounded-2xl overflow-hidden transition-all"
                :class="activeQuestion === qi ? 'border-mp-gold/60' : 'border-mp-border'">

                <!-- Question header bar -->
                <div class="flex items-center gap-3 px-5 py-3 bg-mp-card-hover/50 border-b border-mp-border">
                  <span class="text-xs text-white font-mono w-5">{{ qi + 1 }}</span>
                  <span :class="typeColor(q.question_type)" class="text-xs font-semibold px-2 py-0.5 rounded-md">
                    {{ typeLabel(q.question_type) }}
                  </span>
                  <div class="flex-1"></div>
                  <label class="flex items-center gap-1.5 text-xs text-white cursor-pointer">
                    <input type="checkbox" v-model="q.is_required" class="accent-purple-500" />
                    Required
                  </label>
                  <!-- Move up/down -->
                  <button @click="moveQuestion(qi, -1)" :disabled="qi === 0" class="text-white hover:text-white disabled:opacity-30 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                  </button>
                  <button @click="moveQuestion(qi, 1)" :disabled="qi === form.questions.length - 1" class="text-white hover:text-white disabled:opacity-30 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                  </button>
                  <button @click="removeQuestion(qi)" class="text-white hover:text-mp-danger transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>

                <div class="p-5" @click="activeQuestion = qi">
                  <!-- Question text -->
                  <input v-model="q.question_text" type="text"
                    :placeholder="typePlaceholder(q.question_type)"
                    class="w-full bg-transparent border-0 border-b border-mp-border text-white text-base font-medium pb-2 mb-4 focus:outline-none focus:border-mp-gold placeholder-gray-600 transition-colors" />

                  <!-- Type-specific inputs -->

                  <!-- MCQ / Dropdown options -->
                  <div v-if="['mcq', 'mcq_multi', 'dropdown'].includes(q.question_type)">
                    <div v-for="(opt, oi) in q.options" :key="oi" class="flex items-center gap-2 mb-2">
                      <div
                        :class="q.question_type === 'mcq_multi' ? 'rounded-sm' : 'rounded-full'"
                        class="w-4 h-4 border border-mp-border flex-shrink-0 flex items-center justify-center">
                        <div v-if="q.question_type === 'mcq'" class="w-2 h-2 rounded-full bg-mp-muted"></div>
                        <div v-else-if="q.question_type === 'mcq_multi'" class="w-2 h-2 rounded-sm bg-mp-muted"></div>
                        <div v-else class="w-2 h-0.5 bg-mp-muted"></div>
                      </div>
                      <input v-model="q.options[oi]" type="text" :placeholder="`Option ${oi + 1}`"
                        class="flex-1 bg-transparent border-b border-mp-border hover:border-mp-border text-white text-sm py-1 focus:outline-none focus:border-mp-gold placeholder-gray-700 transition-colors" />
                      <button @click="q.options.splice(oi, 1)" class="text-white hover:text-mp-danger transition-colors text-xs">✕</button>
                    </div>
                    <button @click="q.options.push('')" class="text-white hover:text-white text-xs font-medium mt-1 transition-colors flex items-center gap-1">
                      <span>+</span> Add Option
                    </button>
                  </div>

                  <!-- Yes / No preview -->
                  <div v-if="q.question_type === 'yes_no'" class="flex gap-3">
                    <div class="flex items-center gap-2 bg-mp-success/20 border border-mp-success/40 text-mp-success text-sm px-4 py-2 rounded-lg">✓ Yes</div>
                    <div class="flex items-center gap-2 bg-mp-danger/20 border border-mp-danger/40 text-mp-danger text-sm px-4 py-2 rounded-lg">✕ No</div>
                  </div>

                  <!-- Rating -->
                  <div v-if="q.question_type === 'rating'" class="flex items-center gap-4">
                    <div class="flex gap-1">
                      <template v-for="n in q.rating_max" :key="n">
                        <span class="w-8 h-8 bg-mp-card-hover border border-mp-border rounded-lg flex items-center justify-center text-white text-sm">{{ n }}</span>
                      </template>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="text-xs text-white">Max:</span>
                      <select v-model="q.rating_max" class="bg-mp-card-hover border border-mp-border text-white text-xs rounded px-2 py-1 focus:outline-none">
                        <option v-for="n in [3,4,5,7,10]" :key="n" :value="n">{{ n }}</option>
                      </select>
                    </div>
                  </div>

                  <!-- Short text / Number preview -->
                  <div v-if="['short_text', 'number'].includes(q.question_type)">
                    <input type="text" v-model="q.placeholder" :placeholder="q.question_type === 'number' ? 'Hint text (e.g. Enter amount in USD)' : 'Hint text for respondents...'"
                      class="w-full bg-mp-card-hover/50 border border-dashed border-mp-border text-white text-sm rounded-lg px-4 py-2.5 focus:outline-none placeholder-gray-600" />
                  </div>
                </div>

                <!-- Save to bank option -->
                <div class="px-5 pb-3 flex items-center gap-2">
                  <input type="checkbox" :id="`bank-${qi}`" v-model="q._saveToBank" class="accent-purple-500" />
                  <label :for="`bank-${qi}`" class="text-xs text-white cursor-pointer">Save to Question Bank</label>
                  <select v-if="q._saveToBank" v-model="q._bankSection" class="ml-2 bg-mp-card-hover border border-mp-border text-xs text-white rounded px-2 py-1 focus:outline-none">
                    <option :value="null">— No section —</option>
                    <option v-for="s in bankSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
              </div>
            </template>
          </div>

          <!-- Add question buttons -->
          <div class="bg-mp-card border border-dashed border-mp-border rounded-2xl p-5">
            <p class="text-xs text-white uppercase tracking-widest font-semibold mb-4 text-center">Add Question</p>
            <div class="flex flex-wrap gap-2 justify-center">
              <template v-for="type in questionTypes" :key="type.value">
                <button @click="addQuestion(type.value)"
                  :class="type.color"
                  class="flex items-center gap-1.5 text-xs font-medium px-3 py-2 rounded-lg transition-colors border">
                  {{ type.icon }} {{ type.label }}
                </button>
              </template>
            </div>
          </div>
        </div>

        <!-- QUESTION BANK PANEL -->
        <div v-if="showBankPanel" class="w-72 flex-shrink-0">
          <div class="bg-mp-card border border-mp-border rounded-2xl sticky top-24 max-h-[calc(100vh-8rem)] overflow-y-auto">
            <div class="px-4 py-4 border-b border-mp-border">
              <p class="text-xs text-white uppercase tracking-widest font-semibold">Question Bank</p>
              <p class="text-white text-xs mt-1">Click a question to add it</p>
            </div>

            <!-- Search -->
            <div class="px-4 py-3 border-b border-mp-border">
              <input v-model="bankSearch" type="text" placeholder="Search questions..."
                class="w-full bg-mp-card-hover border border-mp-border text-white text-xs rounded-lg px-3 py-2 focus:outline-none focus:border-mp-gold placeholder-gray-600" />
            </div>

            <!-- Grouped by section -->
            <div class="p-3">
              <div v-for="section in groupedBankItems" :key="section.id ?? 'uncategorized'" class="mb-4">
                <div v-if="section.name" class="flex items-center gap-2 mb-2">
                  <span class="text-xs font-semibold px-2 py-0.5 rounded-full border"
                    :style="{ color: sectionColorHex(section.color), borderColor: sectionColorHex(section.color) }">{{ section.name }}</span>
                </div>
                <div v-for="item in section.items" :key="item.id"
                  @click="addFromBank(item)"
                  class="bg-mp-card-hover/50 hover:bg-mp-card-hover border border-transparent hover:border-mp-gold/40 rounded-xl p-3 mb-2 cursor-pointer transition-all group">
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-xs text-white leading-snug flex-1">{{ item.question_text }}</p>
                    <span class="text-white opacity-0 group-hover:opacity-100 transition-opacity text-xs flex-shrink-0">+ Add</span>
                  </div>
                  <span :class="typeColor(item.question_type)" class="text-xs font-medium px-1.5 py-0.5 rounded mt-1.5 inline-block">
                    {{ typeLabel(item.question_type) }}
                  </span>
                </div>
              </div>
              <div v-if="groupedBankItems.length === 0 || groupedBankItems.every(s => s.items.length === 0)" class="text-center py-8 text-white text-xs">
                No questions in bank yet.<br>Check the ✓ "Save to Bank" option on questions.
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company: Object,
  survey: Object,
  bankSections: { type: Array, default: () => [] },
  bankItems:    { type: Array, default: () => [] },
})

const isEditing = computed(() => !!props.survey?.id)

const DEFAULT_SECTION_COLOR = '#14b8a6'
const NAMED_SECTION_COLORS = {
  blue: '#3b82f6', purple: '#a855f7', green: '#22c55e', amber: '#f59e0b',
  red: '#ef4444', cyan: '#06b6d4', rose: '#f43f5e', indigo: '#6366f1',
  teal: '#14b8a6', orange: '#f97316',
}
const sectionColorHex = (color) => {
  if (!color) return DEFAULT_SECTION_COLOR
  const value = String(color).trim()
  if (/^#[0-9A-Fa-f]{6}$/.test(value)) return value
  return NAMED_SECTION_COLORS[value] ?? DEFAULT_SECTION_COLOR
}

let _idCounter = 0
const makeId = () => ++_idCounter

const questionTypes = [
  { value: 'mcq',        label: 'Multiple Choice', icon: '◉', color: 'bg-mp-teal-subtle/40 text-white border-mp-teal/50 hover:bg-mp-teal-subtle/60' },
  { value: 'mcq_multi',  label: 'Multiple Choice multiple selection', icon: '☑', color: 'bg-mp-teal-subtle/40 text-white border-mp-teal/50 hover:bg-mp-teal-subtle/60' },
  { value: 'yes_no',     label: 'Yes / No',        icon: '✓✕', color: 'bg-mp-success/40 text-mp-success border-mp-success/50 hover:bg-mp-success/60' },
  { value: 'rating',     label: 'Rating Scale',    icon: '★',  color: 'bg-mp-gold/40 text-white border-mp-gold/50 hover:bg-mp-gold/60' },
  { value: 'short_text', label: 'Short Text',      icon: '¶',  color: 'bg-mp-gold/40 text-white border-mp-gold/50 hover:bg-mp-gold/60' },
  { value: 'number',     label: 'Number / Amount', icon: '#',  color: 'bg-mp-teal-subtle/40 text-white border-mp-teal/50 hover:bg-mp-teal-subtle/60' },
  { value: 'dropdown',   label: 'Dropdown',        icon: '▼',  color: 'bg-mp-danger/40 text-mp-danger border-mp-danger/50 hover:bg-mp-danger/60' },
]

const defaultOptions = (type) => {
  if (type === 'yes_no') return ['Yes', 'No']
  if (type === 'mcq' || type === 'mcq_multi' || type === 'dropdown') return ['', '', '']
  return []
}

const buildQuestion = (type, overrides = {}) => ({
  _id: makeId(),
  question_text: '',
  question_type: type,
  is_required: false,
  rating_max: 5,
  placeholder: '',
  options: defaultOptions(type),
  _saveToBank: false,
  _bankSection: null,
  ...overrides,
})

// Hydrate from existing survey
const hydrateQuestions = () => {
  if (!props.survey?.questions) return []
  return props.survey.questions.map(q => buildQuestion(q.question_type, {
    ...q,
    options: q.options?.length ? q.options : defaultOptions(q.question_type),
  }))
}

const form = reactive({
  title:        props.survey?.title ?? '',
  introduction: props.survey?.introduction ?? '',
  prepared_by:  props.survey?.prepared_by ?? '',
  default_respondent_name:    props.survey?.default_respondent_name ?? '',
  default_respondent_title:   props.survey?.default_respondent_title ?? '',
  default_respondent_company: props.survey?.default_respondent_company ?? '',
  show_respondent_age:        !!props.survey?.show_respondent_age,
  show_respondent_gender:     !!props.survey?.show_respondent_gender,
  is_template:  props.survey?.id ? !!props.survey?.is_template : false,
  questions:    hydrateQuestions(),
})

const saving = ref(false)
const showBankPanel = ref(false)
const activeQuestion = ref(null)
const bankSearch = ref('')

const addQuestion = (type) => {
  form.questions.push(buildQuestion(type))
  activeQuestion.value = form.questions.length - 1
}

const removeQuestion = (i) => { form.questions.splice(i, 1) }

const moveQuestion = (i, dir) => {
  const j = i + dir
  if (j < 0 || j >= form.questions.length) return
  const tmp = form.questions[i]
  form.questions[i] = form.questions[j]
  form.questions[j] = tmp
}

const addFromBank = (item) => {
  form.questions.push(buildQuestion(item.question_type, {
    question_text: item.question_text,
    is_required:   item.is_required,
    rating_max:    item.rating_max,
    placeholder:   item.placeholder,
    options: item.options?.length ? [...item.options] : defaultOptions(item.question_type),
  }))
  activeQuestion.value = form.questions.length - 1
}

const groupedBankItems = computed(() => {
  const search = bankSearch.value.toLowerCase()
  const filtered = (props.bankItems ?? []).filter(i => !search || i.question_text.toLowerCase().includes(search))

  const map = new Map()
  map.set(null, { id: null, name: null, color: 'gray', items: [] })
  ;(props.bankSections ?? []).forEach(s => map.set(s.id, { ...s, items: [] }))

  filtered.forEach(item => {
    const sid = item.question_bank_section_id
    const bucket = map.get(sid) ?? map.get(null)
    bucket.items.push(item)
  })
  return [...map.values()].filter(s => s.items.length > 0)
})

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

const typePlaceholder = (t) => ({
  mcq:        'Type your multiple choice question here...',
  mcq_multi:  'Type your multiple-select question here...',
  yes_no:     'Type your yes/no question here...',
  rating:     'Type your rating question here...',
  short_text: 'Type your open-ended question here...',
  number:     'Type your numeric question here...',
  dropdown:   'Type your dropdown question here...',
}[t] ?? '')

const saveSurvey = async () => {
  if (!form.title.trim()) { alert('Please enter a survey title.'); return }
  saving.value = true

  const payload = {
    title:        form.title,
    introduction: form.introduction,
    prepared_by:  form.prepared_by,
    default_respondent_name:    form.default_respondent_name,
    default_respondent_title:   form.default_respondent_title,
    default_respondent_company: form.default_respondent_company,
    show_respondent_age:        form.show_respondent_age,
    show_respondent_gender:     form.show_respondent_gender,
    is_template:  form.is_template,
    questions: form.questions.map(q => ({
      question_text: q.question_text,
      question_type: q.question_type,
      is_required:   q.is_required,
      rating_max:    q.rating_max,
      placeholder:   q.placeholder,
      options:       ['mcq', 'mcq_multi', 'dropdown', 'yes_no'].includes(q.question_type) ? q.options.filter(o => o.trim()) : [],
      save_to_bank:  !!q._saveToBank,
      bank_section_id: q._bankSection || null,
    })),
  }

  if (isEditing.value) {
    router.put(`/portfolio-companies/${props.company.id}/surveys/${props.survey.id}`, payload, {
      onFinish: () => { saving.value = false }
    })
  } else {
    router.post(`/portfolio-companies/${props.company.id}/surveys`, payload, {
      onFinish: () => { saving.value = false }
    })
  }
}
</script>