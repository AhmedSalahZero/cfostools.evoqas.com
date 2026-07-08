<template>
  <Head :title="`New Document — ${org.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
          <Link :href="`/organizations/${org.id}/investadocs`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to InvestaDocs
          </Link>
          <div class="flex items-center gap-3">
            <span class="text-3xl">{{ selectedTemplate?.icon || '📝' }}</span>
            <div>
              <h1 class="text-xl font-bold text-white">
                {{ selectedTemplate ? 'Create ' + selectedTemplate.name : 'Choose a Template' }}
              </h1>
              <p class="text-white text-sm mt-0.5">{{ org.name }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- TEMPLATE CHOOSER -->
      <div v-if="!selectedTemplate" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <template v-for="(catInfo, catKey) in categoryMeta" :key="catKey">
          <p class="text-xs font-semibold uppercase tracking-wider mb-3 mt-6 first:mt-0" :class="catInfo.color">
            {{ catInfo.icon }} {{ catInfo.label }}
          </p>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
            <template v-for="tpl in templates" :key="tpl.id">
              <div v-if="tpl.category === catKey"
                @click="chooseTemplate(tpl)"
                class="cursor-pointer bg-mp-card hover:bg-mp-card-hover border border-mp-border hover:border-mp-teal rounded-xl p-5 transition-all">
                <div class="text-3xl mb-3">{{ tpl.icon }}</div>
                <p class="font-semibold text-white text-sm">{{ tpl.name }}</p>
                <p class="text-xs text-white mt-1 leading-relaxed">{{ tpl.description }}</p>
              </div>
            </template>
          </div>
        </template>
      </div>

      <!-- DOCUMENT FORM -->
      <div v-else class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form @submit.prevent="submitForm">

          <!-- Document meta -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-5 mb-5">
            <p class="text-xs font-semibold text-white uppercase tracking-wider mb-4">Document Details</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

              <div class="md:col-span-2">
                <label class="block text-xs text-white mb-1">Document Title *</label>
                <input v-model="form.title" type="text" required
                  :placeholder="`e.g. ${selectedTemplate.name} with ABC Company — March 2026`"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none" />
              </div>

              <!-- Target company — free text for prospects not yet in system -->
              <div>
                <label class="block text-xs text-white mb-1">Target Company Name</label>
                <input v-model="form.target_company_name" type="text"
                  placeholder="e.g. TechVenture S.A.E. (if not in system)"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none" />
                <p class="text-xs text-white mt-1">Use this for prospects not yet added as portfolio companies.</p>
              </div>

              <!-- Link to existing portfolio company -->
              <div>
                <label class="block text-xs text-white mb-1">Link to Portfolio Company (optional)</label>
                <select v-model="form.portfolio_company_id"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none">
                  <option :value="null">— None (prospect / external) —</option>
                  <option v-for="pc in portfolioCompanies" :key="pc.id" :value="pc.id">{{ pc.name }}</option>
                </select>
                <p class="text-xs text-white mt-1">Link once the prospect is onboarded into CFOs Tools.</p>
              </div>

              <div class="md:col-span-2">
                <label class="block text-xs text-white mb-1">Internal Notes (optional)</label>
                <textarea v-model="form.notes" rows="2"
                  placeholder="Any internal notes about this document..."
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none resize-none" />
              </div>
            </div>
          </div>

          <!-- Template variable fields -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-5 mb-5">
            <p class="text-xs font-semibold text-white uppercase tracking-wider mb-4">
              {{ selectedTemplate.name }} — Fill in the Details
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <template v-for="field in parsedVariables" :key="field.key">
                <div :class="field.type === 'textarea' ? 'md:col-span-2' : ''">
                  <label class="block text-xs mb-1"
                    :class="field.required ? 'text-white' : 'text-white'">
                    {{ field.label }}
                    <span v-if="field.required" class="text-mp-danger ml-0.5">*</span>
                    <span v-else class="text-white ml-1">(optional)</span>
                  </label>

                  <input v-if="field.type === 'text'" v-model="form.variables_data[field.key]"
                    type="text" :placeholder="field.placeholder || ''" :required="field.required"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none" />

                  <input v-else-if="field.type === 'number'" v-model="form.variables_data[field.key]"
                    type="number" :placeholder="field.placeholder || ''" :required="field.required"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none" />

                  <input v-else-if="field.type === 'date'" v-model="form.variables_data[field.key]"
                    type="date" :required="field.required"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none" />

                  <select v-else-if="field.type === 'select'" v-model="form.variables_data[field.key]"
                    :required="field.required"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none">
                    <option value="" disabled>— Select —</option>
                    <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                  </select>

                  <textarea v-else-if="field.type === 'textarea'" v-model="form.variables_data[field.key]"
                    rows="3" :placeholder="field.placeholder || ''" :required="field.required"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none resize-none" />
                </div>
              </template>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-between">
            <button type="button" @click="selectedTemplate = null"
              class="px-4 py-2.5 bg-mp-page hover:bg-mp-muted rounded-lg text-sm transition-colors">
              ← Change Template
            </button>
            <button type="submit" :disabled="form.processing"
              class="flex items-center gap-2 px-6 py-2.5 bg-mp-teal hover:bg-mp-teal disabled:opacity-50 rounded-lg text-sm font-medium transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              {{ form.processing ? 'Generating...' : 'Generate Document' }}
            </button>
          </div>

        </form>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  org:                 Object,
  templates:           Array,
  template:            Object,
  portfolioCompanies:  Array,
  prefill:             Object,
})

const categoryMeta = {
  pre_loi:       { label: 'Pre-LOI',       icon: '🔍', color: 'text-white' },
  due_diligence: { label: 'Due Diligence', icon: '📋', color: 'text-white' },
  closing:       { label: 'Closing',       icon: '✅', color: 'text-mp-success' },
}

const selectedTemplate = ref(props.template || null)

const parsedVariables = computed(() => {
  if (!selectedTemplate.value) return []
  const raw = selectedTemplate.value.variables
  return typeof raw === 'string' ? JSON.parse(raw) : (raw || [])
})

const form = useForm({
  doc_template_id:      selectedTemplate.value?.id || null,
  title:                '',
  target_company_name:  '',
  portfolio_company_id: null,
  notes:                '',
  variables_data:       {},
})

watch(selectedTemplate, (tpl) => {
  if (!tpl) return
  form.doc_template_id = tpl.id
  const vars = typeof tpl.variables === 'string' ? JSON.parse(tpl.variables) : (tpl.variables || [])
  const data = {}
  vars.forEach(f => {
    data[f.key] = props.prefill?.[f.key] || ''
  })
  form.variables_data = data
}, { immediate: true })

function chooseTemplate(tpl) {
  selectedTemplate.value = tpl
}

function submitForm() {
  form.post(`/organizations/${props.org.id}/investadocs`)
}
</script>
