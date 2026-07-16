<template>
  <Head :title="`InvestaDocs — ${org.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ═══ HEADER ═══ -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
          <Link href="/organizations"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Organizations
          </Link>
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white">📝 InvestaDocs</h1>
              <p class="text-white text-sm mt-0.5">{{ org.name }} · Legal Document Workspace</p>
            </div>
            <button @click="showTemplateModal = true"
              class="flex items-center gap-2 px-5 py-2.5 bg-mp-teal hover:bg-mp-teal rounded-lg text-sm font-medium transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              New Document
            </button>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- ═══ STATS ═══ -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-white mb-1">Total</p>
            <p class="text-2xl font-bold text-white">{{ stats.total }}</p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-white mb-1">Draft</p>
            <p class="text-2xl font-bold text-white">{{ stats.draft }}</p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-white mb-1">Sent</p>
            <p class="text-2xl font-bold text-white">{{ stats.sent }}</p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-white mb-1">Signed</p>
            <p class="text-2xl font-bold text-mp-success">{{ stats.signed }}</p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-white mb-1">Archived</p>
            <p class="text-2xl font-bold text-white">{{ stats.archived }}</p>
          </div>
        </div>

        <!-- ═══ FILTER BAR ═══ -->
        <div class="flex items-center gap-3 flex-wrap">
          <span class="text-xs text-white">Filter by company:</span>
          <button @click="filterCompany = null"
            class="px-3 py-1 rounded-full text-xs transition-colors"
            :class="!filterCompany ? 'bg-mp-teal text-white' : 'bg-mp-card-hover text-white hover:text-white'">
            All
          </button>
          <template v-for="pc in portfolioCompanies" :key="pc.id">
            <button @click="filterCompany = pc.id"
              class="px-3 py-1 rounded-full text-xs transition-colors"
              :class="filterCompany === pc.id ? 'bg-mp-teal text-white' : 'bg-mp-card-hover text-white hover:text-white'">
              {{ pc.name }}
            </button>
          </template>
          <button @click="filterCompany = 'none'"
            class="px-3 py-1 rounded-full text-xs transition-colors"
            :class="filterCompany === 'none' ? 'bg-mp-gold-dark text-white' : 'bg-mp-card-hover text-white hover:text-white'">
            Prospects / Unlinked
          </button>
        </div>

        <!-- ═══ DOCUMENTS BY DEAL STAGE ═══ -->
        <template v-for="(catData, catKey) in categorized" :key="catKey">
          <div v-if="filteredDocs(catData.docs).length > 0"
            class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
            <div class="px-5 py-3 bg-mp-card-hover border-b border-mp-border flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider"
                :class="catKey === 'pre_loi' ? 'text-white' : catKey === 'due_diligence' ? 'text-white' : 'text-mp-success'">
                {{ catData.icon }} {{ catData.label }}
              </span>
              <span class="text-xs text-white">{{ filteredDocs(catData.docs).length }} doc{{ filteredDocs(catData.docs).length !== 1 ? 's' : '' }}</span>
            </div>

            <div class="divide-y divide-gray-800">
              <template v-for="doc in filteredDocs(catData.docs)" :key="doc.id">
                <div class="px-5 py-4 flex items-center justify-between hover:bg-mp-card-hover/50 transition-colors gap-4">
                  <!-- Left: icon + info -->
                  <div class="flex items-center gap-4 min-w-0">
                    <div class="text-2xl flex-shrink-0">{{ doc.icon }}</div>
                    <div class="min-w-0">
                      <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-medium text-white">{{ doc.title }}</p>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium flex-shrink-0"
                          :class="statusClass(doc.status)">
                          {{ doc.status.charAt(0).toUpperCase() + doc.status.slice(1) }}
                        </span>
                      </div>
                      <p class="text-xs text-white mt-0.5">
                        {{ doc.short_name }}
                        <span v-if="doc.portfolio_company_name" class="text-white"> · 🏢 {{ doc.portfolio_company_name }}</span>
                        <span v-else-if="doc.target_company_name" class="text-white"> · 🎯 {{ doc.target_company_name }}</span>
                        <span class="text-white"> · {{ doc.created_by_name }} · {{ formatDate(doc.created_at) }}</span>
                        <span v-if="doc.signed_at" class="text-mp-success"> · Signed {{ formatDate(doc.signed_at) }}</span>
                      </p>
                    </div>
                  </div>

                  <!-- Right: actions -->
                  <div class="flex items-center gap-2 flex-shrink-0">
                    <Link :href="`/organizations/${org.id}/investadocs/${doc.id}`"
                      class="px-3 py-1.5 text-xs bg-mp-page hover:bg-mp-muted rounded-lg transition-colors">
                      View
                    </Link>
                    <a :href="`/organizations/${org.id}/investadocs/${doc.id}/download`"
                      class="px-3 py-1.5 text-xs bg-mp-page hover:bg-mp-muted rounded-lg transition-colors">
                      ↓ Download
                    </a>
                    <button @click="confirmDelete(doc)"
                      class="px-3 py-1.5 text-xs bg-mp-danger/40 hover:bg-mp-danger/70 text-mp-danger rounded-lg transition-colors">
                      Delete
                    </button>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </template>

        <!-- Empty state -->
        <div v-if="stats.total === 0" class="bg-mp-card rounded-xl border border-mp-border p-16 text-center">
          <div class="text-5xl mb-4">📝</div>
          <p class="text-white text-lg font-medium mb-2">No documents yet</p>
          <p class="text-white text-sm mb-6">Create your first legal document using a professional template — NDA, LOI, Term Sheet, and more.</p>
          <button @click="showTemplateModal = true"
            class="px-6 py-2.5 bg-mp-teal hover:bg-mp-teal rounded-lg text-sm font-medium transition-colors">
            Browse Templates
          </button>
        </div>

      </div>
    </div>

    <!-- ═══ TEMPLATE PICKER MODAL ═══ -->
    <div v-if="showTemplateModal"
      class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4"
      @click.self="showTemplateModal = false">
      <div class="bg-mp-card rounded-2xl border border-mp-border w-full max-w-5xl max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between sticky top-0 bg-mp-card z-10">
          <h2 class="text-lg text-white font-semibold">Choose a Template</h2>
          <button @click="showTemplateModal = false" class="text-white hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6 space-y-6">
          <template v-for="(catInfo, catKey) in categoryMeta" :key="catKey">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider mb-3" :class="catInfo.color">
                {{ catInfo.icon }} {{ catInfo.label }}
              </p>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <template v-for="tpl in templates" :key="tpl.id">
                  <div v-if="tpl.category === catKey"
                    @click="selectTemplate(tpl)"
                    class="cursor-pointer bg-mp-card-hover border border-mp-border hover:border-mp-teal rounded-xl p-4 transition-all hover:bg-mp-page">
                    <div class="text-3xl mb-2">{{ tpl.icon }}</div>
                    <p class="font-medium text-m text-white">{{ tpl.name }}</p>
                    <p class="text-m text-white mt-1 leading-relaxed">{{ tpl.description }}</p>
                  </div>
                </template>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- ═══ DELETE CONFIRM MODAL ═══ -->
    <div v-if="docToDelete" class="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4">
      <div class="bg-mp-card rounded-2xl border border-mp-border w-full max-w-md p-6">
        <h2 class="text-lg font-semibold mb-2">Delete Document?</h2>
        <p class="text-white text-sm mb-6">
          This will permanently delete <strong class="text-white">{{ docToDelete.title }}</strong>. This cannot be undone.
        </p>
        <div class="flex gap-3 justify-end">
          <button @click="docToDelete = null"
            class="px-4 py-2 bg-mp-page hover:bg-mp-muted rounded-lg text-sm transition-colors">Cancel</button>
          <button @click="submitDelete"
            class="px-4 py-2 bg-mp-danger hover:bg-mp-danger rounded-lg text-sm transition-colors">Delete</button>
        </div>
      </div>
    </div>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  org:                 Object,
  templates:           Array,
  documents:           Array,
  categorized:         Object,
  portfolioCompanies:  Array,
  stats:               Object,
})

const showTemplateModal = ref(false)
const docToDelete       = ref(null)
const filterCompany     = ref(null)

const categoryMeta = {
  pre_loi:       { label: 'Pre-LOI',       icon: '🔍', color: 'text-white' },
  due_diligence: { label: 'Due Diligence', icon: '📋', color: 'text-white' },
  valuation: { label: 'Valuation', icon: '📋', color: 'text-white' },
  closing:       { label: 'Closing',       icon: '✅', color: 'text-mp-success' },
  post_investment: { label: 'Post Investment', icon: '📋', color: 'text-white' },
}

function filteredDocs(docs) {
  if (!filterCompany.value) return docs
  if (filterCompany.value === 'none') return docs.filter(d => !d.portfolio_company_id)
  return docs.filter(d => d.portfolio_company_id === filterCompany.value)
}

function selectTemplate(tpl) {
  showTemplateModal.value = false
  router.get(`/organizations/${props.org.id}/investadocs/create?template=${tpl.slug}`)
}

function confirmDelete(doc) { docToDelete.value = doc }

function submitDelete() {
  router.delete(`/organizations/${props.org.id}/investadocs/${docToDelete.value.id}`, {
    onSuccess: () => { docToDelete.value = null },
  })
}

function statusClass(s) {
  return {
    draft:    'bg-mp-gold/50 text-white',
    sent:     'bg-mp-teal-subtle/50 text-white',
    signed:   'bg-mp-success/50 text-mp-success',
    archived: 'bg-mp-page text-white',
  }[s] || 'bg-mp-page text-white'
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>