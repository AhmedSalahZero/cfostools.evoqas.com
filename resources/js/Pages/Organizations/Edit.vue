<template>
  <Head title="Edit Organization" />

  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- PAGE HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <Link
                href="/organizations"
                class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-2 w-fit"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Organizations
              </Link>
              <h1 class="text-2xl font-bold text-white">Edit: {{ organization.name }}</h1>
              <p class="text-white text-sm mt-1">Update this organization's details</p>
            </div>

            <!-- Organization avatar -->
            <div class="flex-shrink-0">
              <img
                v-if="organization.logo"
                :src="`/storage/${organization.logo}`"
                class="w-14 h-14 rounded-xl object-cover"
              />
              <div
                v-else
                class="w-14 h-14 rounded-xl bg-mp-teal flex items-center justify-center text-2xl font-bold"
              >
                {{ organization.name.charAt(0).toUpperCase() }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- FORM -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Flash success -->
        <div v-if="$page.props.flash?.success" class="mb-6 bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.success }}
        </div>

        <form @submit.prevent="submit" class="space-y-8">

          <!-- ── SECTION: ORGANIZATION INFO ── -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-6">Organization Information</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

              <!-- Organization Name -->
              <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Organization Name <span class="text-mp-danger">*</span>
                </label>
                <input
                  v-model="form.name"
                  type="text"
                  placeholder="e.g. Acme Capital Partners"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition"
                />
                <p v-if="form.errors.name" class="text-mp-danger text-xs mt-1">{{ form.errors.name }}</p>
              </div>

              <!-- Legal Structure -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Legal Structure <span class="text-mp-danger">*</span>
                </label>
                <select
                  v-model="form.legal_structure"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition"
                >
                  <option value="" disabled>Select structure</option>
                  <option value="Joint Stock">Joint Stock Company</option>
                  <option value="LLC">Limited Liability Company (LLC)</option>
                  <option value="Partnership">Partnership</option>
                  <option value="SPC">Single Person Company (SPC)</option>
                  <option value="Other">Other</option>
                </select>
                <p v-if="form.errors.legal_structure" class="text-mp-danger text-xs mt-1">{{ form.errors.legal_structure }}</p>
              </div>

              <!-- Base Currency -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Base Currency <span class="text-mp-danger">*</span>
                </label>
                <select
                  v-model="form.base_currency"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition"
                >
                  <option value="EGP">EGP — Egyptian Pound</option>
                  <option value="USD">USD — US Dollar</option>
                  <option value="EUR">EUR — Euro</option>
                  <option value="GBP">GBP — British Pound</option>
                  <option value="SAR">SAR — Saudi Riyal</option>
                  <option value="AED">AED — UAE Dirham</option>
                </select>
                <p v-if="form.errors.base_currency" class="text-mp-danger text-xs mt-1">{{ form.errors.base_currency }}</p>
              </div>

              <!-- Logo Upload -->
              <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Logo <span class="text-white font-normal normal-case tracking-normal">(optional — leave blank to keep current)</span>
                </label>

                <!-- Current logo preview -->
                <div v-if="organization.logo && !form.logo" class="flex items-center gap-4 mb-3 p-3 bg-mp-card-hover rounded-lg border border-mp-border">
                  <img :src="`/storage/${organization.logo}`" class="w-12 h-12 rounded-lg object-cover" />
                  <div>
                    <p class="text-white text-sm font-medium">Current logo</p>
                    <p class="text-white text-xs">Upload a new file below to replace it</p>
                  </div>
                </div>

                <div
                  class="border-2 border-dashed border-mp-border hover:border-mp-teal rounded-xl p-8 text-center transition-colors cursor-pointer"
                  @click="$refs.logoInput.click()"
                >
                  <input
                    ref="logoInput"
                    type="file"
                    class="hidden"
                    accept="image/*"
                    @change="form.logo = $event.target.files[0]"
                  />

                  <!-- New file selected -->
                  <div v-if="form.logo" class="flex items-center justify-center gap-3">
                    <div class="w-10 h-10 bg-mp-teal rounded-lg flex items-center justify-center">
                      <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                    </div>
                    <div class="text-left">
                      <p class="text-white text-sm font-medium">{{ form.logo.name }}</p>
                      <p class="text-white text-xs">Click to change</p>
                    </div>
                  </div>

                  <!-- Default prompt -->
                  <div v-else>
                    <svg class="w-10 h-10 text-white mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-white text-sm font-medium">Click to upload new logo</p>
                    <p class="text-white text-xs mt-1">PNG, JPG up to 2MB</p>
                  </div>
                </div>
                <p v-if="form.errors.logo" class="text-mp-danger text-xs mt-1">{{ form.errors.logo }}</p>
              </div>

            </div>
          </div>

          <!-- ── SECTION: ADMINISTRATOR ACCOUNT ── -->
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-2">Administrator Account</p>
            <p class="text-white text-xs mb-6">Update the admin user details for this organization</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

              <!-- Admin Name -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Full Name <span class="text-mp-danger">*</span>
                </label>
                <input
                  v-model="form.admin_name"
                  type="text"
                  placeholder="e.g. Ahmed Mohamed"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition"
                />
                <p v-if="form.errors.admin_name" class="text-mp-danger text-xs mt-1">{{ form.errors.admin_name }}</p>
              </div>

              <!-- Email -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Email Address <span class="text-mp-danger">*</span>
                </label>
                <input
                  v-model="form.email"
                  type="email"
                  placeholder="admin@acmecapital.com"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition"
                />
                <p v-if="form.errors.email" class="text-mp-danger text-xs mt-1">{{ form.errors.email }}</p>
              </div>

              <!-- New Password -->
              <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  New Password
                  <span class="text-white font-normal normal-case tracking-normal">(leave blank to keep current password)</span>
                </label>
                <div class="relative">
                  <input
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="••••••••"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-mp-teal focus:border-transparent transition pr-12"
                  />
                  <button
                    type="button"
                    @click="showPassword = !showPassword"
                    class="absolute right-3 top-3.5 text-white hover:text-white transition-colors"
                  >
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

          <!-- ── SUBMIT ── -->
          <div class="flex items-center justify-end gap-4 pb-8">
            <Link
              href="/organizations"
              class="px-6 py-3 rounded-lg border border-mp-border text-white hover:text-white hover:border-mp-border text-sm font-medium transition-colors"
            >
              Cancel
            </Link>
            <button
              type="submit"
              :disabled="form.processing"
              class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium px-8 py-3 rounded-lg transition-colors"
            >
              <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ form.processing ? 'Saving...' : 'Save Changes' }}
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
  organization: Object,
  adminUser: Object,
})

const showPassword = ref(false)

// Pre-fill form with existing data
const form = useForm({
  name:            props.organization.name,
  legal_structure: props.organization.legal_structure,
  base_currency:   props.organization.base_currency,
  admin_name:      props.adminUser?.name ?? '',
  email:           props.adminUser?.email ?? '',
  password:        '',   // blank = keep existing password
  logo:            null, // null = keep existing logo
})

function submit() {
  form.post(`/organizations/${props.organization.id}`, {
    forceFormData: true, // required for file uploads
    preserveScroll: true,
  })
}
</script>
