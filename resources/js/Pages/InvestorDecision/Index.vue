<template>

	<Head title="Investor Decision Tool" />
	<AuthenticatedLayout>
		<div class="min-h-screen bg-mp-page text-white">
			<!-- ── Header ─────────────────────────────────────────────────────────── -->
			<div class="border-b border-mp-border bg-mp-card">
				<div class="max-w-7xl mx-auto px-6 py-5 flex items-center justify-between">
					<div>
						<div class="flex items-center gap-3 mb-1">
							<div
								class="w-8 h-8 rounded-lg bg-gradient-to-br from-mp-gold-dark to-mp-teal flex items-center justify-center text-sm">
								⚖️</div>
							<h1 class="text-xl font-bold text-white">Investor Decision Tool</h1>
						</div>
						<p class="text-sm text-white">Evaluate prospects and compare investment opportunities</p>
					</div>
					<a :href="`/dashboard`"
						class="text-xs text-white hover:text-white flex items-center gap-1 transition-colors"> ← Back to
						Dashboard </a>
				</div>
			</div>
			<div class="max-w-7xl mx-auto px-6 py-10">
				<!-- ── Empty state ──────────────────────────────────────────────────── -->
				<div v-if="prospects.length === 0" class="text-center py-24">
					<div class="text-6xl mb-4">🔍</div>
					<h2 class="text-xl font-semibold text-white mb-2">No Prospects Yet</h2>
					<p class="text-white text-sm mb-6">Add prospects under Portfolio Companies to start evaluating them
						here.</p>
					<a href="/portfolio-companies/create"
						class="inline-block px-5 py-2.5 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium rounded-lg transition-colors">
						+ Add Prospect </a>
				</div>
				<template v-else>
					<!-- ── Mode selector ──────────────────────────────────────────────── -->
					<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-12">
						<!-- Evaluate card -->
						<div
							class="bg-mp-card border border-mp-border rounded-2xl p-7 hover:border-mp-gold/60 transition-all group">
							<div class="flex items-start gap-4">
								<div
									class="w-12 h-12 rounded-xl bg-mp-gold/50 border border-mp-gold/40 flex items-center justify-center text-2xl flex-shrink-0 group-hover:bg-mp-gold/60 transition-colors">
									🎯 </div>
								<div class="flex-1">
									<h2 class="text-lg font-bold text-white mb-1">Single Evaluation</h2>
									<p class="text-sm text-white mb-5 leading-relaxed"> Deep-dive into one prospect.
										Analyze financial metrics, sales momentum, KPI health, and score non-financial
										factors like team quality and market position. </p>
									<div class="mb-4">
										<label
											class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Select
											Prospect</label>
										<select v-model="selectedEval"
											class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none">
											<option value="">— Choose a prospect —</option>
											<option v-for="p in prospects" :key="p.id" :value="p.id">
												{{ p.name }} {{ p.sector ? `(${p.sector})` : '' }}
											</option>
										</select>
									</div>
									<a v-if="selectedEval" :href="`/investor-decision/${selectedEval}/evaluate`"
										class="inline-block px-5 py-2.5 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-semibold rounded-lg transition-colors">
										Evaluate → </a>
									<button v-else disabled
										class="px-5 py-2.5 bg-mp-page text-white text-sm font-semibold rounded-lg cursor-not-allowed">
										Evaluate → </button>
								</div>
							</div>
						</div>
						<!-- Compare card -->
						<div
							class="bg-mp-card border border-mp-border rounded-2xl p-7 hover:border-mp-teal/60 transition-all group">
							<div class="flex items-start gap-4">
								<div
									class="w-12 h-12 rounded-xl bg-mp-teal-subtle/50 border border-mp-teal/40 flex items-center justify-center text-2xl flex-shrink-0 group-hover:bg-mp-teal-subtle/60 transition-colors">
									⚡ </div>
								<div class="flex-1">
									<h2 class="text-lg font-bold text-white mb-1">Head-to-Head Comparison</h2>
									<p class="text-sm text-white mb-5 leading-relaxed"> Compare two prospects side by
										side across all financial and operational dimensions to make a clear,
										data-driven investment choice. </p>
									<div class="grid grid-cols-2 gap-3 mb-4">
										<div>
											<label
												class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Prospect
												A</label>
											<select v-model="compareA"
												class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none">
												<option value="">— Select —</option>
												<option v-for="p in prospects" :key="p.id" :value="p.id"
													:disabled="p.id === compareB">
													{{ p.name }}
												</option>
											</select>
										</div>
										<div>
											<label
												class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Prospect
												B</label>
											<select v-model="compareB"
												class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:border-mp-teal focus:outline-none">
												<option value="">— Select —</option>
												<option v-for="p in prospects" :key="p.id" :value="p.id"
													:disabled="p.id === compareA">
													{{ p.name }}
												</option>
											</select>
										</div>
									</div>
									<a v-if="compareA && compareB"
										:href="`/investor-decision/compare?a=${compareA}&b=${compareB}`"
										class="inline-block px-5 py-2.5 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-semibold rounded-lg transition-colors">
										Compare → </a>
									<button v-else disabled
										class="px-5 py-2.5 bg-mp-page text-white text-sm font-semibold rounded-lg cursor-not-allowed">
										Compare → </button>
								</div>
							</div>
						</div>
					</div>
					<!-- ── Prospect overview cards ─────────────────────────────────────── -->
					<div class="mb-4 flex items-center justify-between">
						<p class="text-xs font-semibold text-white uppercase tracking-widest">All Prospects ({{
							prospects.length }})</p>
					</div>
					<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
						<div v-for="p in prospects" :key="p.id"
							class="bg-mp-card border border-mp-border rounded-xl p-5 hover:border-mp-border transition-colors">
							<div class="flex items-start justify-between mb-4">
								<div class="flex items-center gap-3">
									<div
										class="w-9 h-9 rounded-lg bg-mp-card-hover border border-mp-border flex items-center justify-center overflow-hidden flex-shrink-0">
										<img v-if="p.logo" :src="`/storage/${p.logo}`"
											class="w-full h-full object-contain" />
										<span v-else class="text-sm font-bold text-white">{{ p.name.charAt(0) }}</span>
									</div>
									<div>
										<p class="text-sm font-semibold text-white">{{ p.name }}</p>
										<p class="text-xs text-white">{{ p.sector || 'No sector' }}</p>
									</div>
								</div>
								<span :class="statusBadge(p.status)"
									class="text-xs px-2 py-0.5 rounded-full font-medium">{{ p.status }}</span>
							</div>
							<div class="grid grid-cols-2 gap-3 mb-4 text-sm">
								<div>
									<p class="text-xs text-white mb-0.5">Entry Valuation</p>
									<p class="font-semibold text-white">{{ fmtM(p.entry_valuation) }}</p>
								</div>
								<div>
									<p class="text-xs text-white mb-0.5">Invested</p>
									<p class="font-semibold text-white">{{ fmtM(p.invested_amount) }}</p>
								</div>
							</div>
							<div class="flex gap-2">
								<a :href="`/investor-decision/${p.id}/evaluate`"
									class="flex-1 text-center py-1.5 bg-mp-gold/40 hover:bg-mp-gold/50 border border-mp-gold/40 text-white text-xs font-medium rounded-lg transition-colors">
									Evaluate </a>
								<button @click="quickCompare(p.id)"
									class="flex-1 text-center py-1.5 bg-mp-teal-subtle/40 hover:bg-mp-teal-subtle/50 border border-mp-teal/40 text-white text-xs font-medium rounded-lg transition-colors">
									+ Compare </button>
							</div>
						</div>
					</div>
				</template>
			</div>
		</div>
	</AuthenticatedLayout>
</template>
<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
	prospects: { type: Array, default: () => [] },
	evaluations: { type: Array, default: () => [] },
})

const selectedEval = ref('')
const compareA = ref('')
const compareB = ref('')

function quickCompare(id) {
	if (!compareA.value) { compareA.value = id; return }
	if (!compareB.value && compareB.value !== id) { compareB.value = id }
}

function statusBadge(s) {
	const m = {
		on_track: 'bg-mp-success/60 text-mp-success border border-mp-success',
		at_risk: 'bg-mp-danger/60 text-mp-danger border border-mp-danger',
		watch: 'bg-mp-warning/60 text-mp-warning border border-mp-warning'
	}
	return m[s] || m.watch
}

function fmtM(v) {
	if (!v) return '—'
	if (Math.abs(v) >= 1e9) return (v / 1e9).toFixed(1) + 'B'
	if (Math.abs(v) >= 1e6) return (v / 1e6).toFixed(1) + 'M'
	if (Math.abs(v) >= 1e3) return (v / 1e3).toFixed(0) + 'K'
	return Number(v).toLocaleString()
}
</script>
