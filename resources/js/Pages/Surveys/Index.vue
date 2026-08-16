<template>
  <Head title="Surveys" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3 mb-1">
                <Link :href="`/portfolio-companies/${company.id}`" class="text-white hover:text-white text-sm transition-colors">
                  ← {{ company.name }}
                </Link>
              </div>
              <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <span class="w-9 h-9 bg-mp-gold-dark/20 rounded-xl flex items-center justify-center text-lg">📋</span>
                Surveys
              </h1>
              <p class="text-white text-sm mt-1">{{ localSurveys.length }} survey{{ localSurveys.length !== 1 ? 's' : '' }}</p>
            </div>
            <div class="flex items-center gap-3">
              <Link :href="company.organization_id ? `/question-bank?organization_id=${company.organization_id}` : '/question-bank'"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page text-white hover:text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors border border-mp-border">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                Question Bank
              </Link>
              <Link :href="`/portfolio-companies/${company.id}/surveys/create`"
                class="flex items-center gap-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                New Survey
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- CONTENT -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success" class="mb-6 bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <!-- Copied toast -->
        <Teleport to="body">
          <div v-if="toastVisible"
            class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-mp-card-hover border border-mp-success text-mp-success text-sm font-medium px-5 py-3 rounded-xl shadow-2xl flex items-center gap-2 transition-all">
            ✅ Survey link copied to clipboard!
          </div>
        </Teleport>

        <!-- Empty state -->
        <div v-if="localSurveys.length === 0" class="bg-mp-card rounded-2xl border border-dashed border-mp-border p-16 text-center">
          <div class="w-16 h-16 bg-mp-gold/50 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl">📋</div>
          <p class="text-white font-semibold text-lg mb-1">No surveys yet</p>
          <p class="text-white text-sm mb-6">Create your first survey to collect structured feedback from stakeholders</p>
          <Link :href="`/portfolio-companies/${company.id}/surveys/create`"
            class="inline-flex items-center gap-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
            + Create First Survey
          </Link>
        </div>

        <!-- Survey grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
          <template v-for="survey in localSurveys" :key="survey.id">
            <div class="bg-mp-card border border-mp-border rounded-2xl overflow-hidden hover:border-mp-border transition-all">

              <!-- Color band by status -->
              <div :class="statusBand(survey.status)" class="h-1.5 w-full"></div>

              <div class="p-5">
                <!-- Status + Template badge -->
                <div class="flex items-start justify-between mb-3">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span :class="statusBadge(survey.status)" class="text-xs font-semibold px-2.5 py-0.5 rounded-full uppercase tracking-wide">
                      {{ statusLabel(survey.status) }}
                    </span>
                    <span v-if="survey.is_template" class="text-xs bg-mp-gold/40 text-white border border-mp-gold/50 px-2 py-0.5 rounded-full font-medium">
                      📌 Template
                    </span>
                  </div>
                  <span class="text-xs text-white">{{ formatDate(survey.created_at) }}</span>
                </div>

                <!-- Title -->
                <h3 class="text-white font-semibold text-base mb-1 leading-snug">{{ survey.title }}</h3>
                <p v-if="survey.prepared_by" class="text-white text-xs mb-3">By {{ survey.prepared_by }}</p>

                <!-- Stats -->
                <div class="flex items-center gap-4 text-xs text-white mb-4">
                  <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ survey.question_count }} questions
                  </span>
                  <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-mp-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {{ survey.response_count }} responses
                  </span>
                </div>

                <!-- Link bar (if published) -->
                <div v-if="survey.link_token" class="flex items-center gap-2 bg-mp-card-hover/60 rounded-lg px-3 py-2 mb-4">
                  <span class="text-white text-xs truncate flex-1 font-mono">{{ surveyUrl(survey.link_token) }}</span>
                  <button
                    @click="copyLink(survey.link_token)"
                    class="flex-shrink-0 flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-lg transition-all"
                    :class="copiedToken === survey.link_token
                      ? 'bg-mp-success text-mp-success'
                      : 'bg-mp-teal hover:bg-mp-teal-dark text-white'">
                    <svg v-if="copiedToken !== survey.link_token" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    {{ copiedToken === survey.link_token ? '✓ Copied!' : 'Copy' }}
                  </button>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 flex-wrap">
                  <!-- Publish / Toggle -->
                  <button v-if="!survey.link_token" @click="publishSurvey(survey)"
                    class="flex items-center gap-1.5 bg-mp-gold-dark hover:bg-mp-gold text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                    🚀 Publish &amp; Get Link
                  </button>
                  <button v-else @click="toggleStatus(survey)"
                    :class="survey.status === 'active'
                      ? 'bg-mp-danger/40 hover:bg-mp-danger/60 text-mp-danger border border-mp-danger/50'
                      : 'bg-mp-success/40 hover:bg-mp-success/60 text-mp-success border border-mp-success/50'"
                    class="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                    {{ survey.status === 'active' ? '⏸ Close Survey' : '▶ Reopen' }}
                  </button>

                  <!-- Results -->
                  <Link v-if="survey.response_count > 0"
                    :href="`/portfolio-companies/${company.id}/surveys/${survey.id}/results`"
                    class="flex items-center gap-1.5 bg-mp-teal-subtle/40 hover:bg-mp-teal-subtle/60 text-white border border-mp-teal/50 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                    📊 Results
                  </Link>

                  <!-- Edit -->
                  <Link :href="`/portfolio-companies/${company.id}/surveys/${survey.id}/edit`"
                    class="flex items-center gap-1.5 bg-mp-card-hover hover:bg-mp-page text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                    ✏️ Edit
                  </Link>

                  <!-- Duplicate -->
                  <button @click="openCopy(survey)"
                    class="flex items-center gap-1.5 bg-mp-card-hover hover:bg-mp-page text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                    📋 Copy
                  </button>

                  <!-- Delete -->
                  <button @click="confirmDelete(survey)"
                    class="flex items-center gap-1.5 bg-mp-card-hover hover:bg-mp-danger/40 text-white hover:text-mp-danger text-xs font-medium px-2 py-1.5 rounded-lg transition-colors">
                    🗑
                  </button>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- COPY MODAL -->
    <Teleport to="body">
      <div v-if="copyModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="copyModal.show = false">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-sm bg-mp-card border border-mp-border rounded-2xl shadow-2xl p-6">
          <h3 class="text-lg font-bold text-white text-center mb-2">Copy Survey</h3>
          <p class="text-white text-sm text-center mb-4">Enter a name for the new survey</p>
          <input v-model="copyModal.title" type="text" placeholder="Survey title"
            @keyup.enter="executeCopy"
            class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-mp-gold placeholder-gray-600 mb-5" />
          <div class="flex gap-3">
            <button @click="copyModal.show = false"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
              Cancel
            </button>
            <button @click="executeCopy" :disabled="copyModal.saving || !copyModal.title.trim()"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-gold-dark hover:bg-mp-gold disabled:opacity-50 text-white text-sm font-semibold transition-colors">
              {{ copyModal.saving ? 'Copying…' : 'Copy Survey' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- DELETE MODAL -->
    <Teleport to="body">
      <div v-if="deleteModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="deleteModal.show = false">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-sm bg-mp-card border border-mp-danger/50 rounded-2xl shadow-2xl p-6">
          <h3 class="text-lg font-bold text-white text-center mb-2">Delete Survey</h3>
          <p class="text-white text-sm text-center mb-1">This will permanently delete</p>
          <p class="text-mp-danger font-semibold text-center mb-4">{{ deleteModal.survey?.title }}</p>
          <p class="text-white text-xs text-center mb-5">All {{ deleteModal.survey?.response_count }} responses will also be deleted.</p>
          <div class="flex gap-3">
            <button @click="deleteModal.show = false"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
              Cancel
            </button>
            <button @click="executeDelete"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-danger hover:bg-mp-danger text-white text-sm font-semibold transition-colors">
              Delete
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company: Object,
  surveys: Array,
})

// Use a local reactive copy so we can mutate status/token without prop restrictions
const localSurveys = ref(props.surveys.map(s => ({ ...s })))

watch(() => props.surveys, (surveys) => {
  localSurveys.value = (surveys ?? []).map(s => ({ ...s }))
}, { deep: true })

const copiedToken  = ref(null)
const toastVisible = ref(false)
const deleteModal  = reactive({ show: false, survey: null })
const copyModal    = reactive({ show: false, survey: null, title: '', saving: false })

const surveyUrl = (token) => `${window.location.origin}/s/${token}`

// ── Copy with HTTP fallback (works on investawatch.test without HTTPS) ────────
const copyLink = (token) => {
  const url = surveyUrl(token)

  // Try modern Clipboard API first (works on HTTPS / localhost)
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(() => showCopied(token))
  } else {
    // Fallback: create a temporary textarea and execCommand (works on HTTP)
    const el = document.createElement('textarea')
    el.value = url
    el.style.position = 'fixed'
    el.style.opacity  = '0'
    document.body.appendChild(el)
    el.focus()
    el.select()
    try {
      document.execCommand('copy')
      showCopied(token)
    } catch (e) {
      // Last resort: prompt the user to copy manually
      window.prompt('Copy this link manually (Ctrl+C):', url)
    }
    document.body.removeChild(el)
  }
}

const showCopied = (token) => {
  copiedToken.value  = token
  toastVisible.value = true
  setTimeout(() => {
    copiedToken.value  = null
    toastVisible.value = false
  }, 2500)
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const formatDate = (d) => {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const statusLabel = (s) => ({ draft: 'Draft', active: 'Active', closed: 'Closed' }[s] ?? s)
const statusBadge = (s) => ({
  draft:  'bg-mp-page text-white',
  active: 'bg-mp-success/50 text-mp-success border border-mp-success/50',
  closed: 'bg-mp-danger/40 text-mp-danger border border-mp-danger/40',
}[s])
const statusBand = (s) => ({
  draft:  'bg-mp-page',
  active: 'bg-gradient-to-r from-mp-success to-mp-success',
  closed: 'bg-gradient-to-r from-mp-danger to-mp-danger',
}[s])

const getCsrf = () => decodeURIComponent(
  document.cookie.split(';').find(c => c.trim().startsWith('XSRF-TOKEN='))?.split('=')[1] ?? ''
)

// ── Publish ───────────────────────────────────────────────────────────────────
const publishSurvey = async (survey) => {
  const res  = await fetch(`/portfolio-companies/${props.company.id}/surveys/${survey.id}/publish`, {
    method: 'POST',
    headers: { 'X-XSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
    credentials: 'include',
  })
  const data = await res.json()
  if (data.success) {
    // Mutate the local reactive copy
    const idx = localSurveys.value.findIndex(s => s.id === survey.id)
    if (idx > -1) {
      localSurveys.value[idx].link_token = data.token
      localSurveys.value[idx].status     = 'active'
    }
    copyLink(data.token)
  }
}

// ── Toggle active / closed ────────────────────────────────────────────────────
const toggleStatus = async (survey) => {
  const res  = await fetch(`/portfolio-companies/${props.company.id}/surveys/${survey.id}/toggle-status`, {
    method: 'POST',
    headers: { 'X-XSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
    credentials: 'include',
  })
  const data = await res.json()
  if (data.success) {
    const idx = localSurveys.value.findIndex(s => s.id === survey.id)
    if (idx > -1) localSurveys.value[idx].status = data.status
  }
}

// ── Copy / Delete ─────────────────────────────────────────────────────────────
const openCopy = (survey) => {
  copyModal.survey = survey
  copyModal.title = `Copy of ${survey.title}`
  copyModal.saving = false
  copyModal.show = true
}

const executeCopy = () => {
  const title = copyModal.title.trim()
  if (!title || !copyModal.survey) return
  copyModal.saving = true
  router.post(`/portfolio-companies/${props.company.id}/surveys/${copyModal.survey.id}/copy`, { title }, {
    onSuccess: () => { copyModal.show = false },
    onFinish: () => { copyModal.saving = false },
  })
}

const confirmDelete = (survey) => { deleteModal.survey = survey; deleteModal.show = true }
const executeDelete = () => {
  const id = deleteModal.survey?.id
  deleteModal.show = false
  router.delete(`/portfolio-companies/${props.company.id}/surveys/${id}`, {
    onSuccess: () => {
      localSurveys.value = localSurveys.value.filter(s => s.id !== id)
    },
  })
}
</script>