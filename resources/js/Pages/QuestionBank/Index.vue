<template>
  <Head title="Question Bank" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <span class="w-9 h-9 bg-mp-teal/20 rounded-xl flex items-center justify-center text-lg">📚</span>
                Question Bank
              </h1>
              <p class="text-white text-sm mt-1">{{ localItems.length }} questions across {{ localSections.length }} sections</p>
            </div>
            <div class="flex items-center gap-3">
              <button @click="sectionModal.show = true"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors border border-mp-border">
                + New Section
              </button>
              <button @click="openAddItem(null)"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                + Add Question
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex gap-6">

        <!-- LEFT: Section list sidebar -->
        <div class="w-52 flex-shrink-0">
          <div class="space-y-1 sticky top-6">
            <button @click="filterSection = null"
              :class="filterSection === null ? 'bg-mp-teal text-white' : 'bg-mp-card border border-mp-border text-white hover:text-white hover:bg-mp-card-hover'"
              class="w-full text-left px-3 py-2.5 rounded-xl text-sm font-medium transition-colors">
              📋 All Questions
              <span class="float-right text-xs opacity-60">{{ localItems.length }}</span>
            </button>
            <button @click="filterSection = -1"
              :class="filterSection === -1 ? 'bg-mp-teal text-white' : 'bg-mp-card border border-mp-border text-white hover:text-white hover:bg-mp-card-hover'"
              class="w-full text-left px-3 py-2.5 rounded-xl text-sm font-medium transition-colors">
              📁 Uncategorized
              <span class="float-right text-xs opacity-60">{{ localItems.filter(i => !i.question_bank_section_id).length }}</span>
            </button>
            <div class="border-t border-mp-border my-2"></div>
            <template v-for="s in localSections" :key="s.id">
              <div class="group relative">
                <button @click="filterSection = s.id"
                  :class="filterSection === s.id ? 'bg-mp-teal text-white' : 'bg-mp-card border border-mp-border text-white hover:text-white hover:bg-mp-card-hover'"
                  class="w-full text-left px-3 py-2.5 rounded-xl text-sm font-medium transition-colors pr-14">
                  <span :class="`text-${s.color}-400`">●</span>
                  {{ s.name }}
                  <span class="float-right text-xs opacity-60">{{ localItems.filter(i => i.question_bank_section_id === s.id).length }}</span>
                </button>
                <div class="absolute right-1 top-1.5 flex gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button @click.stop="openEditSection(s)" class="p-1 text-white hover:text-white transition-colors text-xs">✏️</button>
                  <button @click.stop="deleteSection(s)" class="p-1 text-white hover:text-mp-danger transition-colors text-xs">🗑</button>
                </div>
              </div>
            </template>
          </div>
        </div>

        <!-- RIGHT: Questions -->
        <div class="flex-1 min-w-0">

          <!-- Search + filter -->
          <div class="flex items-center gap-3 mb-5">
            <input v-model="search" type="text" placeholder="Search questions..."
              class="flex-1 bg-mp-card border border-mp-border text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-mp-teal placeholder-gray-600" />
            <select v-model="typeFilter"
              class="bg-mp-card border border-mp-border text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none">
              <option value="">All Types</option>
              <option v-for="t in questionTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>

          <!-- Empty -->
          <div v-if="filteredItems.length === 0" class="bg-mp-card border border-dashed border-mp-border rounded-2xl p-16 text-center">
            <div class="text-4xl mb-4">📭</div>
            <p class="text-white font-medium mb-1">No questions found</p>
            <p class="text-white text-sm">Add questions or save them from surveys using the ✓ Save to Bank option.</p>
          </div>

          <!-- Question table -->
          <div v-else class="bg-mp-card border border-mp-border rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs text-white uppercase tracking-widest px-5 py-3 font-semibold">Question</th>
                  <th class="text-left text-xs text-white uppercase tracking-widest px-4 py-3 font-semibold">Type</th>
                  <th class="text-left text-xs text-white uppercase tracking-widest px-4 py-3 font-semibold">Section</th>
                  <th class="text-right text-xs text-white uppercase tracking-widest px-4 py-3 font-semibold">Used</th>
                  <th class="text-center text-xs text-white uppercase tracking-widest px-4 py-3 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <template v-for="item in filteredItems" :key="item.id">
                  <tr class="hover:bg-mp-card-hover/40 transition-colors">
                    <td class="px-5 py-4">
                      <p class="text-white font-medium leading-snug">{{ item.question_text }}</p>
                      <p v-if="item.options?.length" class="text-white text-xs mt-0.5">
                        Options: {{ item.options.slice(0, 3).join(' · ') }}{{ item.options.length > 3 ? '…' : '' }}
                      </p>
                    </td>
                    <td class="px-4 py-4">
                      <span :class="typeColor(item.question_type)" class="text-xs font-medium px-2 py-0.5 rounded">
                        {{ typeLabel(item.question_type) }}
                      </span>
                    </td>
                    <td class="px-4 py-4">
                      <span v-if="item.section_name"
                        :class="`bg-${item.section_color ?? 'blue'}-900/30 text-${item.section_color ?? 'blue'}-400 border border-${item.section_color ?? 'blue'}-700/40`"
                        class="text-xs px-2 py-0.5 rounded-full">
                        {{ item.section_name }}
                      </span>
                      <span v-else class="text-white text-xs italic">Uncategorized</span>
                    </td>
                    <td class="px-4 py-4 text-right text-white text-xs">{{ item.usage_count }}×</td>
                    <td class="px-4 py-4">
                      <div class="flex items-center justify-center gap-2">
                        <button @click="openEditItem(item)"
                          class="w-7 h-7 bg-mp-card-hover hover:bg-mp-teal text-white hover:text-white rounded-lg flex items-center justify-center transition-colors text-xs">✏️</button>
                        <select @change="e => handleMove(item, e)"
                          class="bg-mp-card-hover border border-mp-border text-white text-xs rounded-lg px-2 py-1 focus:outline-none cursor-pointer">
                          <option value="">Move to…</option>
                          <option value="null">Uncategorized</option>
                          <option v-for="s in localSections" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <button @click="deleteItem(item)"
                          class="w-7 h-7 bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white rounded-lg flex items-center justify-center transition-colors text-xs">🗑</button>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- SECTION MODAL -->
    <Teleport to="body">
      <div v-if="sectionModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="sectionModal.show = false">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-sm bg-mp-card border border-mp-border rounded-2xl shadow-2xl p-6">
          <h3 class="text-lg font-bold text-white mb-5">{{ sectionModal.id ? 'Edit Section' : 'New Section' }}</h3>
          <div class="mb-4">
            <label class="text-xs text-white mb-1.5 block">Section Name</label>
            <input v-model="sectionModal.name" type="text" placeholder="e.g. Finance, Legal, Marketing..."
              class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-teal placeholder-gray-600" />
          </div>
          <div class="mb-5">
            <label class="text-xs text-white mb-2 block">Color</label>
            <div class="flex gap-2 flex-wrap">
              <template v-for="color in colorOptions" :key="color">
                <button @click="sectionModal.color = color"
                  :class="[`bg-${color}-500`, sectionModal.color === color ? 'ring-2 ring-white ring-offset-2 ring-offset-gray-900' : '']"
                  class="w-7 h-7 rounded-full transition-all"></button>
              </template>
            </div>
          </div>
          <div class="flex gap-3">
            <button @click="sectionModal.show = false" class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm transition-colors">Cancel</button>
            <button @click="saveSection" class="flex-1 px-4 py-2.5 rounded-lg bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-semibold transition-colors">Save</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ITEM MODAL -->
    <Teleport to="body">
      <div v-if="itemModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" @click.self="itemModal.show = false">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-lg bg-mp-card border border-mp-border rounded-2xl shadow-2xl p-6 my-8">
          <h3 class="text-lg font-bold text-white mb-5">{{ itemModal.id ? 'Edit Question' : 'Add Question to Bank' }}</h3>

          <div class="space-y-4">
            <div>
              <label class="text-xs text-white mb-1.5 block">Question Text *</label>
              <textarea v-model="itemModal.question_text" rows="2" placeholder="Enter the question..."
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-teal placeholder-gray-600 resize-none"></textarea>
            </div>
            <div>
              <label class="text-xs text-white mb-1.5 block">Question Type</label>
              <select v-model="itemModal.question_type" @change="onTypeChange"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-teal">
                <option v-for="t in questionTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
            </div>
            <div>
              <label class="text-xs text-white mb-1.5 block">Section</label>
              <select v-model="itemModal.section_id"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-teal">
                <option :value="null">— Uncategorized —</option>
                <option v-for="s in localSections" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>

            <!-- Options -->
            <div v-if="['mcq', 'dropdown'].includes(itemModal.question_type)">
              <label class="text-xs text-white mb-2 block">Answer Options</label>
              <div v-for="(opt, oi) in itemModal.options" :key="oi" class="flex items-center gap-2 mb-2">
                <input v-model="itemModal.options[oi]" type="text" :placeholder="`Option ${oi + 1}`"
                  class="flex-1 bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal placeholder-gray-600" />
                <button @click="itemModal.options.splice(oi, 1)" class="text-white hover:text-mp-danger transition-colors">✕</button>
              </div>
              <button @click="itemModal.options.push('')" class="text-white hover:text-white text-xs font-medium transition-colors">+ Add Option</button>
            </div>

            <div v-if="itemModal.question_type === 'rating'" class="flex items-center gap-3">
              <label class="text-xs text-white">Max Rating:</label>
              <select v-model="itemModal.rating_max" class="bg-mp-card-hover border border-mp-border text-white text-sm rounded px-3 py-1.5 focus:outline-none">
                <option v-for="n in [3,4,5,7,10]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>

            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="itemModal.is_required" class="accent-blue-500" />
              <span class="text-sm text-white">Required question</span>
            </label>
          </div>

          <div class="flex gap-3 mt-6">
            <button @click="itemModal.show = false" class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm transition-colors">Cancel</button>
            <button @click="saveItem" class="flex-1 px-4 py-2.5 rounded-lg bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-semibold transition-colors">Save Question</button>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  sections: Array,
  items:    Array,
})

const localSections = ref([...props.sections])
const localItems    = ref([...props.items])
const search        = ref('')
const typeFilter    = ref('')
const filterSection = ref(null)

const colorOptions = ['blue', 'purple', 'green', 'amber', 'red', 'cyan', 'rose', 'indigo', 'teal', 'orange']
const questionTypes = [
  { value: 'mcq',        label: 'Multiple Choice' },
  { value: 'yes_no',     label: 'Yes / No' },
  { value: 'rating',     label: 'Rating Scale' },
  { value: 'short_text', label: 'Short Text' },
  { value: 'number',     label: 'Number / Amount' },
  { value: 'dropdown',   label: 'Dropdown' },
]

const sectionModal = reactive({ show: false, id: null, name: '', color: 'blue' })
const itemModal = reactive({
  show: false, id: null, question_text: '', question_type: 'mcq',
  section_id: null, is_required: false, rating_max: 5, placeholder: '', options: ['', '', ''],
})

const filteredItems = computed(() => {
  let items = localItems.value
  if (filterSection !== null) {
    if (filterSection.value === -1) items = items.filter(i => !i.question_bank_section_id)
    else if (filterSection.value !== null) items = items.filter(i => i.question_bank_section_id === filterSection.value)
  }
  if (search.value) items = items.filter(i => i.question_text.toLowerCase().includes(search.value.toLowerCase()))
  if (typeFilter.value) items = items.filter(i => i.question_type === typeFilter.value)
  return items
})

const typeLabel = (t) => ({ mcq: 'MCQ', yes_no: 'Yes/No', rating: 'Rating', short_text: 'Text', number: 'Number', dropdown: 'Dropdown' }[t] ?? t)
const typeColor = (t) => ({
  mcq: 'bg-mp-teal-subtle/40 text-white', yes_no: 'bg-mp-success/40 text-mp-success',
  rating: 'bg-mp-gold/40 text-white', short_text: 'bg-mp-gold/40 text-white',
  number: 'bg-mp-teal-subtle/40 text-white', dropdown: 'bg-mp-danger/40 text-mp-danger',
}[t] ?? 'bg-mp-card-hover text-white')

const getCsrf = () => decodeURIComponent(document.cookie.split(';').find(c => c.trim().startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '')

// Section CRUD
const openEditSection = (s) => { Object.assign(sectionModal, { show: true, id: s.id, name: s.name, color: s.color }) }
const saveSection = async () => {
  const body = { name: sectionModal.name, color: sectionModal.color }
  const url = sectionModal.id ? `/question-bank/sections/${sectionModal.id}` : '/question-bank/sections'
  const method = sectionModal.id ? 'PUT' : 'POST'
  const res = await fetch(url, { method, headers: { 'X-XSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'include', body: JSON.stringify(body) })
  const data = await res.json()
  if (sectionModal.id) {
    const idx = localSections.value.findIndex(s => s.id === sectionModal.id)
    if (idx > -1) localSections.value[idx] = { ...localSections.value[idx], ...body }
  } else {
    localSections.value.push({ id: data.id, ...body, sort_order: localSections.value.length })
  }
  sectionModal.show = false
}
const deleteSection = async (s) => {
  if (!confirm(`Delete section "${s.name}"? Questions will become uncategorized.`)) return
  await fetch(`/question-bank/sections/${s.id}`, { method: 'DELETE', headers: { 'X-XSRF-TOKEN': getCsrf(), 'Accept': 'application/json' }, credentials: 'include' })
  localSections.value = localSections.value.filter(x => x.id !== s.id)
  localItems.value.forEach(i => { if (i.question_bank_section_id === s.id) i.question_bank_section_id = null })
}

// Item CRUD
const openAddItem = (sectionId) => {
  Object.assign(itemModal, { show: true, id: null, question_text: '', question_type: 'mcq', section_id: sectionId, is_required: false, rating_max: 5, placeholder: '', options: ['', '', ''] })
}
const openEditItem = (item) => {
  Object.assign(itemModal, { show: true, id: item.id, question_text: item.question_text, question_type: item.question_type, section_id: item.question_bank_section_id, is_required: item.is_required, rating_max: item.rating_max, placeholder: item.placeholder, options: item.options?.length ? [...item.options] : ['', '', ''] })
}
const onTypeChange = () => {
  if (['mcq', 'dropdown'].includes(itemModal.question_type)) itemModal.options = ['', '', '']
  else if (itemModal.question_type === 'yes_no') itemModal.options = ['Yes', 'No']
  else itemModal.options = []
}
const saveItem = async () => {
  const body = { question_text: itemModal.question_text, question_type: itemModal.question_type, section_id: itemModal.section_id, is_required: itemModal.is_required, rating_max: itemModal.rating_max, placeholder: itemModal.placeholder, options: itemModal.options.filter(o => o.trim()) }
  const url = itemModal.id ? `/question-bank/items/${itemModal.id}` : '/question-bank/items'
  const method = itemModal.id ? 'PUT' : 'POST'
  const res = await fetch(url, { method, headers: { 'X-XSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'include', body: JSON.stringify(body) })
  const data = await res.json()
  const sectionObj = localSections.value.find(s => s.id === body.section_id)
  if (itemModal.id) {
    const idx = localItems.value.findIndex(i => i.id === itemModal.id)
    if (idx > -1) localItems.value[idx] = { ...localItems.value[idx], ...body, options: body.options, section_name: sectionObj?.name, section_color: sectionObj?.color }
  } else {
    localItems.value.unshift({ id: data.id, ...body, usage_count: 0, question_bank_section_id: body.section_id, section_name: sectionObj?.name, section_color: sectionObj?.color })
  }
  itemModal.show = false
}
const deleteItem = async (item) => {
  if (!confirm('Delete this question from the bank?')) return
  await fetch(`/question-bank/items/${item.id}`, { method: 'DELETE', headers: { 'X-XSRF-TOKEN': getCsrf(), 'Accept': 'application/json' }, credentials: 'include' })
  localItems.value = localItems.value.filter(i => i.id !== item.id)
}
const handleMove = (item, e) => {
  const val = e.target.value
  if (!val) return
  e.target.value = ''
  moveItem(item, val)
}

const moveItem = async (item, sectionId) => {
  const sid = sectionId === 'null' ? null : parseInt(sectionId)
  await fetch(`/question-bank/items/${item.id}/move`, { method: 'PUT', headers: { 'X-XSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' }, credentials: 'include', body: JSON.stringify({ section_id: sid }) })
  const sectionObj = localSections.value.find(s => s.id === sid)
  const idx = localItems.value.findIndex(i => i.id === item.id)
  if (idx > -1) { localItems.value[idx].question_bank_section_id = sid; localItems.value[idx].section_name = sectionObj?.name; localItems.value[idx].section_color = sectionObj?.color }
}
</script>