<template>
  <Head title="User Management" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-bold text-white">User Management</h1>
              <p class="text-white text-sm mt-1">{{ users.length }} user{{ users.length !== 1 ? 's' : '' }} in your organization</p>
            </div>
            <Link :href="route('users.create')"
              class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              New User
            </Link>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Flash -->
        <div v-if="$page.props.flash?.success" class="mb-6 bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <!-- Empty state -->
        <div v-if="users.length === 0" class="bg-mp-card rounded-xl border border-dashed border-mp-border p-16 text-center">
          <div class="w-14 h-14 bg-mp-card-hover rounded-xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <p class="text-white font-medium mb-1">No users yet</p>
          <p class="text-white text-sm mb-5">Create your first user and assign them to portfolio companies</p>
          <Link :href="route('users.create')" class="bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors inline-block">
            + New User
          </Link>
        </div>

        <!-- Table -->
        <div v-else class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-mp-border">
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">User</th>
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">Company Roles</th>
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">Companies</th>
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">Created</th>
                <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
              <tr v-for="user in users" :key="user.id" class="hover:bg-mp-card-hover/50 transition-colors">

                <!-- User info -->
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-mp-teal flex items-center justify-center text-sm font-bold flex-shrink-0">
                      {{ user.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <p class="font-semibold text-white">{{ user.name }}</p>
                      <p class="text-white text-xs">{{ user.email }}</p>
                    </div>
                  </div>
                </td>

                <!-- Company roles -->
                <td class="px-6 py-4">
                  <div v-if="user.company_roles.length === 0" class="text-white text-xs">No assignments</div>
                  <div v-else class="flex flex-wrap gap-1.5">
                    <span v-for="cr in user.company_roles" :key="cr.company_name"
                      class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-full border"
                      :class="roleClass(cr.role)">
                      <span class="font-medium">{{ cr.company_name }}</span>
                      <span class="opacity-60">·</span>
                      <span class="capitalize">{{ cr.role }}</span>
                    </span>
                  </div>
                </td>

                <!-- Companies count -->
                <td class="px-6 py-4 text-white text-sm">
                  {{ user.company_count }} {{ user.company_count === 1 ? 'company' : 'companies' }}
                </td>

                <!-- Created -->
                <td class="px-6 py-4 text-white text-sm">{{ user.created_at }}</td>

                <!-- Actions -->
                <td class="px-6 py-4">
                  <div class="flex items-center justify-center gap-2">
                    <Link :href="route('users.edit', user.id)"
                      class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-teal text-white hover:text-white transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </Link>
                    <button @click="confirmDelete(user)"
                      class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>

              </tr>
            </tbody>
          </table>
        </div>

        <!-- Delete Modal -->
        <div v-if="userToDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" @click.self="userToDelete = null">
          <div class="bg-mp-card border border-mp-border rounded-xl p-6 w-full max-w-sm mx-4 shadow-2xl">
            <div class="w-12 h-12 bg-mp-danger/15 rounded-xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-6 h-6 text-mp-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <h3 class="text-white font-semibold text-center mb-1">Delete User?</h3>
            <p class="text-white text-sm text-center mb-6">
              Are you sure you want to delete <span class="text-white font-medium">{{ userToDelete.name }}</span>?
              All their company assignments and permissions will be removed.
            </p>
            <div class="flex gap-3">
              <button @click="userToDelete = null" class="flex-1 px-4 py-2.5 rounded-lg border border-mp-border text-white hover:text-white text-sm font-medium transition-colors">
                Cancel
              </button>
              <button @click="deleteUser" class="flex-1 px-4 py-2.5 rounded-lg bg-mp-danger hover:bg-mp-danger text-white text-sm font-medium transition-colors">
                Yes, Delete
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

defineProps({
  users: { type: Array, default: () => [] },
})

const userToDelete = ref(null)
const deleteForm   = useForm({})

function confirmDelete(user) { userToDelete.value = user }

function deleteUser() {
  deleteForm.delete(route('users.destroy', userToDelete.value.id), {
    onSuccess: () => { userToDelete.value = null },
  })
}

function roleClass(role) {
  const map = {
    manager: 'bg-mp-teal-subtle text-white border-mp-teal',
    analyst: 'bg-mp-success/15 text-mp-success border-mp-success',
    viewer:  'bg-mp-card-hover text-white border-mp-border',
  }
  return map[role] || 'bg-mp-card-hover text-white border-mp-border'
}
</script>