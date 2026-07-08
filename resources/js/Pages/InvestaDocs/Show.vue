<template>
  <Head :title="`${doc.title} — InvestaDocs`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
          <Link :href="`/organizations/${org.id}/investadocs`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to InvestaDocs
          </Link>
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-3">
              <span class="text-3xl">{{ doc.icon }}</span>
              <div>
                <div class="flex items-center gap-2 flex-wrap">
                  <h1 class="text-xl font-bold text-white">{{ doc.title }}</h1>
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="statusClass(doc.status)">
                    {{ doc.status.charAt(0).toUpperCase() + doc.status.slice(1) }}
                  </span>
                </div>
                <p class="text-white text-sm mt-0.5">
                  {{ doc.template_name }} · {{ org.name }} · Created by {{ doc.created_by_name }}
                  <span v-if="doc.portfolio_company_name" class="text-white"> · 🏢 {{ doc.portfolio_company_name }}</span>
                  <span v-else-if="doc.target_company_name" class="text-white"> · 🎯 {{ doc.target_company_name }}</span>
                </p>
              </div>
            </div>
            <!-- Action buttons -->
            <div class="flex items-center gap-2 flex-wrap">
              <a :href="`/organizations/${org.id}/investadocs/${doc.id}/download`" target="_blank"
                class="px-3 py-2 text-xs bg-mp-page hover:bg-mp-muted rounded-lg transition-colors">
                ↓ Download HTML
              </a>
              <select v-model="newStatus" @change="updateStatus"
                class="bg-mp-teal-dark hover:bg-mp-teal border-0 rounded-lg px-3 py-2 text-xs text-white focus:outline-none cursor-pointer">
                <option value="" disabled>Change Status…</option>
                <option v-if="doc.status !== 'draft'"    value="draft">→ Draft</option>
                <option v-if="doc.status !== 'sent'"     value="sent">→ Mark Sent</option>
                <option v-if="doc.status !== 'signed'"   value="signed">→ Mark Signed ✓</option>
                <option v-if="doc.status !== 'archived'" value="archived">→ Archive</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

        <!-- STATUS TIMELINE -->
        <div class="bg-mp-card rounded-xl border border-mp-border p-5">
          <p class="text-xs font-semibold text-white uppercase tracking-wider mb-4">Deal Progress</p>
          <div class="flex items-center gap-3 flex-wrap">
            <template v-for="(step, idx) in statusSteps" :key="step.key">
              <div class="flex items-center gap-2">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                    :class="stepDone(step.key) ? 'bg-mp-success text-white' : 'bg-mp-page text-white'">
                    {{ stepDone(step.key) ? '✓' : idx + 1 }}
                  </div>
                  <div>
                    <p class="text-xs font-medium" :class="stepDone(step.key) ? 'text-white' : 'text-white'">
                      {{ step.label }}
                    </p>
                    <p class="text-xs text-white" v-if="step.key === 'sent' && doc.sent_at">{{ formatDate(doc.sent_at) }}</p>
                    <p class="text-xs text-white" v-if="step.key === 'signed' && doc.signed_at">{{ formatDate(doc.signed_at) }}</p>
                  </div>
                </div>
                <div v-if="idx < statusSteps.length - 1" class="w-8 h-px bg-mp-page flex-shrink-0"></div>
              </div>
            </template>
          </div>
        </div>

        <!-- LINK TO PORTFOLIO COMPANY -->
        <div class="bg-mp-card rounded-xl border border-mp-border p-5">
          <p class="text-xs font-semibold text-white uppercase tracking-wider mb-3">Company Link</p>
          <div class="flex items-end gap-4 flex-wrap">
            <div class="flex-1 min-w-48">
              <label class="block text-xs text-white mb-1">
                Link to Portfolio Company
                <span class="text-white ml-1">— update once the prospect is onboarded</span>
              </label>
              <select v-model="linkForm.portfolio_company_id"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none">
                <option :value="null">— None (prospect / external) —</option>
                <option v-for="pc in portfolioCompanies" :key="pc.id" :value="pc.id">{{ pc.name }}</option>
              </select>
            </div>
            <button @click="saveLink"
              class="px-4 py-2.5 bg-mp-teal hover:bg-mp-teal rounded-lg text-sm transition-colors flex-shrink-0">
              Save Link
            </button>
          </div>
          <p v-if="linkSaved" class="text-xs text-mp-success mt-2">✓ Saved</p>
        </div>

        <!-- DOCUMENT VARIABLE SUMMARY -->
        <div class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
          <div class="px-5 py-3 bg-mp-card-hover border-b border-mp-border flex items-center justify-between">
            <span class="text-xs font-semibold text-white uppercase tracking-wider">Document Summary</span>
            <a :href="`/organizations/${org.id}/investadocs/${doc.id}/download`" target="_blank"
              class="text-xs text-white hover:text-white transition-colors">
              Open Full Document →
            </a>
          </div>
          <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <template v-for="field in doc.variables" :key="field.key">
                <div v-if="doc.variables_data[field.key]"
                  class="bg-mp-card-hover rounded-lg px-3 py-2.5"
                  :class="field.type === 'textarea' ? 'md:col-span-2' : ''">
                  <p class="text-xs text-white mb-0.5">{{ field.label }}</p>
                  <p class="text-sm text-white font-medium break-words whitespace-pre-line">{{ doc.variables_data[field.key] }}</p>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- NOTES -->
        <div v-if="doc.notes" class="bg-mp-card rounded-xl border border-mp-border p-5">
          <p class="text-xs font-semibold text-white uppercase tracking-wider mb-2">Internal Notes</p>
          <p class="text-sm text-white whitespace-pre-line">{{ doc.notes }}</p>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  org:                Object,
  doc:                Object,
  portfolioCompanies: Array,
})

const newStatus = ref('')
const linkSaved = ref(false)

const linkForm = reactive({
  portfolio_company_id: props.doc.portfolio_company_id || null,
})

const statusSteps = [
  { key: 'draft',  label: 'Created' },
  { key: 'sent',   label: 'Sent' },
  { key: 'signed', label: 'Signed' },
]
const statusOrder = { draft: 0, sent: 1, signed: 2, archived: 3 }

function stepDone(key) {
  return statusOrder[props.doc.status] >= statusOrder[key]
}

function statusClass(s) {
  return {
    draft:    'bg-mp-gold/50 text-white',
    sent:     'bg-mp-teal-subtle/50 text-white',
    signed:   'bg-mp-success/50 text-mp-success',
    archived: 'bg-mp-page text-white',
  }[s] || 'bg-mp-page text-white'
}

function updateStatus() {
  if (!newStatus.value) return
  router.patch(`/organizations/${props.org.id}/investadocs/${props.doc.id}/status`, {
    status: newStatus.value,
  }, { onSuccess: () => { newStatus.value = '' } })
}

function saveLink() {
  router.patch(`/organizations/${props.org.id}/investadocs/${props.doc.id}/link-company`, {
    portfolio_company_id: linkForm.portfolio_company_id,
  }, {
    onSuccess: () => {
      linkSaved.value = true
      setTimeout(() => { linkSaved.value = false }, 2500)
    },
  })
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>