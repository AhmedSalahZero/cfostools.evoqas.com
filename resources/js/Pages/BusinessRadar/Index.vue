<template>
	<Head :title="`Business Radar — ${company.name}`" />
	<AuthenticatedLayout>
		<div class="min-h-screen bg-mp-page text-white">

			<!-- ── PAGE HEADER ── -->
			<div class="bg-mp-card border-b border-mp-border">
				<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
					<Link :href="`/portfolio-companies`"
						class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-4 w-fit">
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
						</svg>
						Back to Portfolio Companies
					</Link>
					<div class="flex items-center justify-between">
						<div>
							<div class="flex items-center gap-3 mb-1">
								<span class="w-9 h-9 rounded-lg bg-mp-teal flex items-center justify-center text-sm font-bold flex-shrink-0">
									{{ company.name.charAt(0).toUpperCase() }}
								</span>
								<div>
									<p class="text-xs text-white uppercase tracking-widest font-semibold">{{ company.name }}</p>
									<h1 class="text-2xl font-bold text-white leading-tight">Business Radar</h1>
								</div>
							</div>
							<p class="text-white text-sm mt-1 ml-12">
								Challenges and potentials, scored, linked and phased into an action roadmap
							</p>
						</div>
						<button @click="showCreate = true"
							class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
							<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
							</svg>
							New Board
						</button>
					</div>
				</div>
			</div>

			<!-- ── MAIN CONTENT ── -->
			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

				<div v-if="$page.props.flash?.success"
					class="mb-6 bg-mp-success/20 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
					{{ $page.props.flash.success }}
				</div>

				<!-- Empty state -->
				<div v-if="boards.length === 0"
					class="bg-mp-card rounded-xl border border-mp-border border-dashed p-16 text-center">
					<div class="w-16 h-16 bg-mp-teal/20 rounded-2xl flex items-center justify-center mx-auto mb-5">
						<svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
								d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
						</svg>
					</div>
					<p class="text-white font-semibold text-lg mb-1">No boards yet</p>
					<p class="text-white text-sm mb-6 max-w-sm mx-auto">
						Create your first Business Radar board for {{ company.name }} to map challenges, potentials, and a phased action plan.
					</p>
					<button @click="showCreate = true"
						class="inline-flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
						Create First Board
					</button>
				</div>

				<!-- Boards grid -->
				<div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
					<div v-for="board in boards" :key="board.id"
						class="bg-mp-card border border-mp-border rounded-xl overflow-hidden hover:border-mp-teal/50 transition-colors group">
						<Link :href="`/portfolio-companies/${company.id}/business-radar/${board.id}`" class="block p-5">
							<div class="flex items-start justify-between mb-3">
								<h3 class="font-semibold text-white text-base">{{ board.name }}</h3>
								<button @click.prevent="destroyBoard(board)"
									class="text-white/40 hover:text-mp-danger transition-colors">
									<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
										<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
											d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
									</svg>
								</button>
							</div>
							<p v-if="board.description" class="text-white/70 text-sm mb-4 line-clamp-2">{{ board.description }}</p>
							<div class="flex items-center gap-4 text-xs">
								<span class="flex items-center gap-1.5 text-white/70">
									<span class="w-2 h-2 rounded-full bg-mp-danger"></span>
									{{ board.challenges_count }} challenges
								</span>
								<span class="flex items-center gap-1.5 text-white/70">
									<span class="w-2 h-2 rounded-full bg-mp-teal"></span>
									{{ board.potentials_count }} potentials
								</span>
							</div>
						</Link>
					</div>
				</div>
			</div>
		</div>

		<!-- ── CREATE MODAL ── -->
		<div v-if="showCreate" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
			@click.self="showCreate = false">
			<div class="bg-mp-card border border-mp-border rounded-xl w-full max-w-md p-6">
				<h3 class="text-lg font-semibold text-white mb-4">New Business Radar board</h3>
				<form @submit.prevent="submitCreate">
					<label class="block text-xs text-white/70 uppercase tracking-widest mb-1.5">Board name</label>
					<input v-model="form.name" type="text" required placeholder="e.g. Q3 2026 Diagnostic"
						class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white mb-4 focus:outline-none focus:border-mp-teal" />
					<label class="block text-xs text-white/70 uppercase tracking-widest mb-1.5">Description (optional)</label>
					<textarea v-model="form.description" rows="3"
						class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white mb-5 focus:outline-none focus:border-mp-teal"></textarea>
					<div class="flex justify-end gap-3">
						<button type="button" @click="showCreate = false"
							class="px-4 py-2 rounded-lg border border-mp-border text-white text-sm hover:border-mp-border">Cancel</button>
						<button type="submit" :disabled="form.processing"
							class="px-4 py-2 rounded-lg bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium disabled:opacity-50">
							{{ form.processing ? 'Creating…' : 'Create board' }}
						</button>
					</div>
				</form>
			</div>
		</div>
	</AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
	company: Object,
	boards:  { type: Array, default: () => [] },
})

const showCreate = ref(false)

const form = useForm({
	name: '',
	description: '',
})

function submitCreate() {
	form.post(`/portfolio-companies/${props.company.id}/business-radar`, {
		onSuccess: () => { showCreate.value = false; form.reset() },
	})
}

function destroyBoard(board) {
	if (!confirm(`Delete "${board.name}"? This removes all its challenges, potentials and links.`)) return
	router.delete(`/portfolio-companies/${props.company.id}/business-radar/${board.id}`)
}
</script>
