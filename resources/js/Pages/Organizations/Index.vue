<template>

	<Head title="Organizations" />
	<AuthenticatedLayout>
		<div class="min-h-screen bg-mp-page text-white">
			<!-- PAGE HEADER -->
			<div class="bg-mp-card border-b border-mp-border">
				<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
					<div class="flex items-center justify-between">
						<div>
							<h1 class="text-2xl font-bold text-white">Organizations</h1>
							<p class="text-white text-sm mt-1">{{ organizations.length }} consulting firm{{ organizations.length
								!== 1 ? 's' : '' }} registered</p>
						</div>
						<Link :href="route('organizations.create')"
							class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
							<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M12 4v16m8-8H4" />
							</svg> New Organization
						</Link>
					</div>
				</div>
			</div>
			<!-- MAIN CONTENT -->
			<div class="mx-auto px-4 sm:px-6 lg:px-8 py-8">
				<!-- Flash message -->
				<div v-if="$page.props.flash?.success"
					class="mb-6 bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
					{{ $page.props.flash.success }}
				</div>
				<!-- Empty state -->
				<div v-if="organizations.length === 0"
					class="bg-mp-card rounded-xl border border-mp-border border-dashed p-16 text-center">
					<div class="w-14 h-14 bg-mp-card-hover rounded-xl flex items-center justify-center mx-auto mb-4">
						<svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
						</svg>
					</div>
					<p class="text-white font-medium mb-1">No organizations yet</p>
					<p class="text-white text-sm mb-5">Create your first consulting firm to get started</p>
					<Link :href="route('organizations.create')"
						class="bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors inline-block">
						+ New Organization </Link>
				</div>
				<!-- TABLE -->
				<div v-else class="bg-mp-card rounded-xl border border-mp-border overflow-visible">
					<table class="w-full text-sm">
						<thead>
							<tr class="border-b border-mp-border">
								<th
									class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">
									Organization</th>
								<th
									class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">
									Legal Structure</th>
								<th
									class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">
									Currency</th>
								<th
									class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">
									Users</th>
								<th
									class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">
									Created</th>
								<th
									class="text-center text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">
									Actions</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-800">
							<tr v-for="org in organizations" :key="org.id"
								class="hover:bg-mp-card-hover/50 transition-colors" style="height: 72px;">
								<!-- Organization Name + Logo -->
								<td class="px-6 py-4">
									<div class="flex items-center gap-3">
										<img v-if="org.logo" :src="`/storage/${org.logo}`"
											class="w-9 h-9 rounded-lg object-cover flex-shrink-0" />
										<div v-else
											class="w-9 h-9 rounded-lg bg-mp-teal flex items-center justify-center text-sm font-bold flex-shrink-0">
											{{ org.name.charAt(0).toUpperCase() }}
										</div>
										<div>
											<Link :href="`/portfolio-companies?organization=${org.id}`"
												class="font-semibold text-white hover:text-white transition-colors">
												{{ org.name }}
											</Link>
										</div>
									</div>
								</td>
								<!-- Legal Structure -->
								<td class="px-6 py-4">
									<span
										class="text-xs font-medium bg-mp-card-hover border border-mp-border text-white px-2.5 py-1 rounded-full">
										{{ org.legal_structure || '—' }}
									</span>
								</td>
								<!-- Currency -->
								<td class="px-6 py-4 text-white font-medium">
									{{ org.base_currency }}
								</td>
								<!-- Users count -->
								<td class="px-6 py-4">
									<span class="flex items-center gap-1.5 text-white text-sm">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
												d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
										</svg>
										{{ org.user_count ?? 0 }} user{{ (org.user_count ?? 0) !== 1 ? 's' : '' }}
									</span>
								</td>
								<!-- Created date -->
								<td class="px-6 py-4 text-white text-sm">
									{{ formatDate(org.created_at) }}
								</td>
								<!-- Actions -->
								<td class="px-6 py-4">
									<div class="flex items-center justify-center gap-2">
										<!-- ── InvestaDocs button ── -->
										<Link :href="`/organizations/${org.id}/investadocs`"
											class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-mp-teal-subtle/50 hover:bg-mp-teal-dark text-white hover:text-white text-xs font-medium transition-colors border border-mp-teal hover:border-mp-teal"
											title="InvestaDocs — Legal Document Workspace"> 📝 InvestaDocs </Link>
										<!-- Edit icon button -->
										<Link :href="`/organizations/${org.id}/edit`"
											class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-teal text-white hover:text-white transition-colors"
											title="Edit organization">
											<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
													d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
											</svg>
										</Link>
										<!-- Delete button -->
										<button @click="confirmDelete(org)"
											class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors"
											title="Delete organization">
											<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
													d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
											</svg>
										</button>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<!-- Delete Confirmation Modal -->
				<div v-if="orgToDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
					@click.self="orgToDelete = null">
					<div class="bg-mp-card border border-mp-border rounded-xl p-6 w-full max-w-sm mx-4 shadow-2xl">
						<div class="w-12 h-12 bg-mp-danger/15 rounded-xl flex items-center justify-center mx-auto mb-4">
							<svg class="w-6 h-6 text-mp-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
									d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
							</svg>
						</div>
						<h3 class="text-white font-semibold text-center mb-1">Delete Organization?</h3>
						<p class="text-white text-sm text-center mb-6"> Are you sure you want to delete <span
								class="text-white font-medium">{{ orgToDelete.name }}</span>? This cannot be undone.
						</p>
						<div class="flex gap-3">
							<button @click="orgToDelete = null"
								class="flex-1 px-4 py-2.5 rounded-lg border border-mp-border text-white hover:text-white text-sm font-medium transition-colors">
								Cancel </button>
							<button @click="deleteOrg"
								class="flex-1 px-4 py-2.5 rounded-lg bg-mp-danger hover:bg-mp-danger text-white text-sm font-medium transition-colors">
								Yes, Delete </button>
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
	organizations: {
		type: Array,
		default: () => [],
	},
})

// ── Delete confirmation ──
const orgToDelete = ref(null)
const deleteForm = useForm({})

function confirmDelete(org) {
	orgToDelete.value = org
}

function deleteOrg() {
	deleteForm.delete(`/organizations/${orgToDelete.value.id}`, {
		onSuccess: () => { orgToDelete.value = null },
	})
}

// ── Format date ──
function formatDate(dateStr) {
	if (!dateStr) return '—'
	return new Date(dateStr).toLocaleDateString('en-US', {
		year: 'numeric', month: 'short', day: 'numeric',
	})
}
</script>
