<template>
  <Head title="Create User" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <Link href="/users" class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-2 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Users
          </Link>
          <h1 class="text-2xl font-bold text-white">Create New User</h1>
          <p class="text-white text-sm mt-1">Add a user, then assign them to companies with a role and permissions per company</p>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form @submit.prevent="submit" class="space-y-8">

          <!-- ── SECTION 1: USER INFO ── -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-6">User Information</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

              <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Full Name <span class="text-mp-danger">*</span></label>
                <input v-model="form.name" type="text" placeholder="e.g. Ahmed Mohamed"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
                <p v-if="form.errors.name" class="text-mp-danger text-xs mt-1">{{ form.errors.name }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Email Address <span class="text-mp-danger">*</span></label>
                <input v-model="form.email" type="email" placeholder="user@company.com"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition" />
                <p v-if="form.errors.email" class="text-mp-danger text-xs mt-1">{{ form.errors.email }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Password <span class="text-mp-danger">*</span></label>
                <div class="relative">
                  <input v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="Min. 8 characters"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition pr-12" />
                  <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-3.5 text-white hover:text-white">
                    <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                  </button>
                </div>
                <p v-if="form.errors.password" class="text-mp-danger text-xs mt-1">{{ form.errors.password }}</p>
              </div>

            </div>
          </div>

          <!-- ── SECTION 2: COMPANY ASSIGNMENTS ── -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-2">Company Access</p>
            <p class="text-white text-xs mb-6">
              Select companies this user can access. For each company, assign their role and what tasks they can perform.
            </p>

            <div v-if="companies.length === 0" class="text-center py-8 text-white text-sm">
              No portfolio companies found. Add companies first.
            </div>

            <div v-else class="space-y-4">
              <div v-for="company in companies" :key="company.id"
                class="rounded-xl border transition-all"
                :class="isAssigned(company.id) ? 'border-mp-teal bg-mp-teal-subtle/20' : 'border-mp-border bg-mp-card-hover/20'">

                <!-- Company header — click to assign/unassign -->
                <div class="flex items-center gap-4 p-4 cursor-pointer" @click="toggleCompany(company.id)">
                  <div :class="isAssigned(company.id) ? 'bg-mp-teal border-mp-teal' : 'bg-transparent border-mp-border'"
                    class="w-5 h-5 rounded border-2 flex items-center justify-center flex-shrink-0 transition-colors">
                    <svg v-if="isAssigned(company.id)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                  </div>
                  <div class="flex items-center gap-3 flex-1">
                    <div class="w-8 h-8 rounded-lg bg-mp-teal flex items-center justify-center text-xs font-bold flex-shrink-0">
                      {{ company.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <p class="text-white font-medium text-sm">{{ company.name }}</p>
                      <p class="text-white text-xs">{{ company.sector }}</p>
                    </div>
                  </div>
                  <span v-if="isAssigned(company.id)" class="text-xs text-white">Click to remove</span>
                  <span v-else class="text-xs text-white">Click to assign</span>
                </div>

                <!-- Role + Permissions — only when assigned -->
                <div v-if="isAssigned(company.id)" class="px-4 pb-5 border-t border-mp-border/50 pt-4 space-y-4">

                  <!-- Role selector -->
                  <div>
                    <p class="text-xs text-white uppercase tracking-widest mb-3">Role in this company <span class="text-mp-danger">*</span></p>
                    <div class="grid grid-cols-3 gap-2">
                      <button v-for="role in ['manager', 'analyst', 'viewer']" :key="role"
                        type="button"
                        @click="setRole(company.id, role)"
                        :class="getRole(company.id) === role
                          ? roleBtnActive(role)
                          : 'border-mp-border bg-mp-card-hover text-white hover:border-mp-border'"
                        class="flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 transition-all cursor-pointer">
                        <!-- Icon -->
                        <div :class="getRole(company.id) === role ? roleBtnIconBg(role) : 'bg-mp-page'"
                          class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                          <svg v-if="role === 'manager'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                          </svg>
                          <svg v-else-if="role === 'analyst'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                          </svg>
                          <svg v-else class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                          </svg>
                        </div>
                        <span class="text-xs font-semibold capitalize">{{ role }}</span>
                        <span class="text-xs text-center leading-tight"
                          :class="getRole(company.id) === role ? 'text-white' : 'text-white'">
                          {{ roleDescription(role) }}
                        </span>
                      </button>
                    </div>
                  </div>

                  <!-- Permissions -->
                  <div>
                    <p class="text-xs text-white uppercase tracking-widest mb-3">Task Permissions</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                      <button v-for="(label, key) in permissions" :key="key"
                        type="button"
                        @click="togglePermission(company.id, key)"
                        :class="hasPermission(company.id, key)
                          ? 'bg-mp-teal/20 border-mp-teal text-white'
                          : 'bg-mp-card-hover border-mp-border text-white hover:border-mp-border'"
                        class="flex items-center gap-2 px-3 py-2 rounded-lg border text-xs font-medium transition-all text-left">
                        <div :class="hasPermission(company.id, key) ? 'bg-mp-teal' : 'bg-mp-page'"
                          class="w-4 h-4 rounded flex items-center justify-center flex-shrink-0 transition-colors">
                          <svg v-if="hasPermission(company.id, key)" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                          </svg>
                        </div>
                        {{ label }}
                      </button>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>

          <!-- SUBMIT -->
          <div class="flex items-center justify-end gap-4 pb-8">
            <Link href="/users" class="px-6 py-3 rounded-lg border border-mp-border text-white hover:text-white hover:border-mp-border text-sm font-medium transition-colors">
              Cancel
            </Link>
            <button type="submit" :disabled="form.processing"
              class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 text-white text-sm font-medium px-8 py-3 rounded-lg transition-colors">
              <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ form.processing ? 'Creating...' : 'Create User' }}
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
import { ref } from 'vue'

const props = defineProps({
  companies:   { type: Array,  default: () => [] },
  permissions: { type: Object, default: () => ({}) },
})

const showPassword = ref(false)

const form = useForm({
  name:               '',
  email:              '',
  password:           '',
  assigned_companies: [], // [{ id, role, permissions: [] }]
})

// ── Helpers ──
function getAssignment(companyId) {
  return form.assigned_companies.find(a => a.id === companyId)
}

function isAssigned(companyId) {
  return !!getAssignment(companyId)
}

function toggleCompany(companyId) {
  if (isAssigned(companyId)) {
    form.assigned_companies = form.assigned_companies.filter(a => a.id !== companyId)
  } else {
    form.assigned_companies.push({ id: companyId, role: 'viewer', permissions: [] })
  }
}

function getRole(companyId) {
  return getAssignment(companyId)?.role ?? 'viewer'
}

function setRole(companyId, role) {
  const a = getAssignment(companyId)
  if (a) a.role = role
}

function hasPermission(companyId, perm) {
  return getAssignment(companyId)?.permissions.includes(perm) ?? false
}

function togglePermission(companyId, perm) {
  const a = getAssignment(companyId)
  if (!a) return
  if (hasPermission(companyId, perm)) {
    a.permissions = a.permissions.filter(p => p !== perm)
  } else {
    a.permissions.push(perm)
  }
}

// ── Role styling ──
function roleBtnActive(role) {
  const map = {
    manager: 'border-mp-teal bg-mp-teal/20 text-white',
    analyst: 'border-mp-success bg-mp-success/20 text-white',
    viewer:  'border-mp-border bg-mp-muted/20 text-white',
  }
  return map[role] || 'border-mp-teal bg-mp-teal/20 text-white'
}

function roleBtnIconBg(role) {
  const map = {
    manager: 'bg-mp-teal',
    analyst: 'bg-mp-success',
    viewer:  'bg-mp-muted',
  }
  return map[role] || 'bg-mp-teal'
}

function roleDescription(role) {
  const map = {
    manager: 'Full access',
    analyst: 'Assigned tasks',
    viewer:  'View only',
  }
  return map[role] || ''
}

function submit() {
  form.post(route('users.store'))
}
</script>
