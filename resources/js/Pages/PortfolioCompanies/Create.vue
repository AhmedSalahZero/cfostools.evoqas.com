<template>
  <Head title="Add New Customer" />

  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- PAGE HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div>
            <Link href="/portfolio-companies"
              class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-2 w-fit">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              Back to Customers
            </Link>
            <h1 class="text-2xl font-bold text-white">Add New Customer</h1>
            <p class="text-white text-sm mt-1">Record a new customer for your organization</p>
          </div>
        </div>
      </div>

      <!-- FORM -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div v-if="$page.props.flash?.success" class="mb-6 bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <form @submit.prevent="submit" class="space-y-8">

          <!-- ── SECTION: ORGANIZATION (super-admin only) ── -->
          <div v-if="isSuperAdmin" class="bg-mp-warning/30 rounded-xl border border-mp-warning/50 p-6">
            <p class="text-xs font-semibold text-mp-warning uppercase tracking-widest mb-2">Organization Assignment</p>
            <p class="text-white text-xs mb-4">As super-admin, select which organization this customer belongs to</p>
            <select v-model="form.organization_id"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-mp-warning focus:border-transparent transition">
              <option value="">— Select Organization —</option>
              <option v-for="org in organizations" :key="org.id" :value="org.id">
                {{ org.name }}
              </option>
            </select>
            <p v-if="form.errors.organization_id" class="text-mp-danger text-xs mt-1">{{ form.errors.organization_id }}</p>
          </div>

          <!-- ── SECTION: CUSTOMER INFO ── -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-6">Customer Information</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

              <!-- Customer Name -->
              <div class="md:col-span-1">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Customer Name <span class="text-mp-danger">*</span></label>
                <input v-model="form.name" type="text" placeholder="e.g. TechCo Egypt"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
                <p v-if="form.errors.name" class="text-mp-danger text-xs mt-1">{{ form.errors.name }}</p>
              </div>

              <!-- Lead Source -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Lead Source <span class="text-mp-danger">*</span></label>
                <LeadSourceSelect v-model="form.lead_source" :options="leadSourceOptions" />
                <p v-if="form.errors.lead_source" class="text-mp-danger text-xs mt-1">{{ form.errors.lead_source }}</p>
              </div>

              <!-- Sector -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Sector <span class="text-mp-danger">*</span></label>
                <input v-model="form.sector" type="text" placeholder="e.g. Technology, Healthcare"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
                <p v-if="form.errors.sector" class="text-mp-danger text-xs mt-1">{{ form.errors.sector }}</p>
              </div>

              <!-- Status -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Status <span class="text-mp-danger">*</span></label>
                <select v-model="form.status"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition">
                  <option value="on_track">✅ On Track</option>
                  <option value="at_risk">🔴 At Risk</option>
                  <option value="watch">🟡 Watch</option>
                </select>
                <p v-if="form.errors.status" class="text-mp-danger text-xs mt-1">{{ form.errors.status }}</p>
              </div>

            </div>
          </div>

          <!-- ── SECTION: NOTES ── -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-6">Notes</p>
            <textarea v-model="form.notes" rows="4"
              placeholder="Any additional notes about this customer..."
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition resize-none"></textarea>
            <p v-if="form.errors.notes" class="text-mp-danger text-xs mt-1">{{ form.errors.notes }}</p>
          </div>

          <!-- ── SUBMIT ── -->
          <div class="flex items-center justify-end gap-4 pb-8">
            <Link href="/portfolio-companies"
              class="px-6 py-3 rounded-lg border border-mp-border text-white hover:text-white hover:border-mp-border text-sm font-medium transition-colors">
              Cancel
            </Link>
            <button type="submit" :disabled="form.processing"
              class="flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-8 py-3 rounded-lg transition-colors bg-mp-teal hover:bg-mp-teal-dark">
              <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ form.processing ? 'Saving...' : 'Save Customer' }}
            </button>
          </div>

        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import LeadSourceSelect from '@/Pages/PortfolioCompanies/Partials/LeadSourceSelect.vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  organizations:     { type: Array, default: () => [] },
  defaultOrgId:      { type: Number, default: null },
  leadSourceOptions: { type: Array, default: () => [] },
})

const page = usePage()
const isSuperAdmin = computed(() => {
  const roles = page.props.auth?.user?.roles ?? []
  if (Array.isArray(roles)) return roles.includes('super-admin')
  return Object.values(roles).includes('super-admin')
})

const form = useForm({
  type:            'investment',
  organization_id: props.defaultOrgId ?? '',
  name:            '',
  lead_source:     '',
  sector:          '',
  status:          'on_track',
  notes:           '',
})

function submit() {
  form.post('/portfolio-companies')
}
</script>
