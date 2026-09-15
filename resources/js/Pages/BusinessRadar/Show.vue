<template>
	<Head :title="`Business Radar — ${board.name}`" />
	<AuthenticatedLayout>
		<div class="min-h-screen bg-mp-page text-white">

			<!-- ── HEADER ── -->
			<div class="bg-mp-card border-b border-mp-border">
				<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
					<Link :href="`/portfolio-companies/${company.id}/business-radar`"
						class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-4 w-fit">
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
						</svg>
						Back to Business Radar boards
					</Link>
					<div class="flex items-center justify-between flex-wrap gap-3">
						<div>
							<p class="text-xs text-white uppercase tracking-widest font-semibold">{{ company.name }}</p>
							<h1 class="text-2xl font-bold text-white leading-tight">{{ board.name }}</h1>
						</div>
						<div class="flex items-center gap-3">
							<label class="flex items-center gap-2 text-xs text-white/70">
								<input type="checkbox" v-model="showResolved" class="rounded border-mp-border" />
								Show resolved / captured
							</label>
							<select v-model="areaFilter"
								class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-xs text-white focus:outline-none">
								<option value="">All business areas</option>
								<option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
							</select>
						</div>
					</div>
				</div>
			</div>

			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

				<div v-if="$page.props.flash?.success"
					class="bg-mp-success/20 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
					{{ $page.props.flash.success }}
				</div>

				<!-- ── PRIORITY RANKING ── -->
				<div class="bg-mp-card border border-mp-border rounded-xl p-5">
					<h2 class="text-sm font-semibold text-white uppercase tracking-widest mb-1">Priority ranking</h2>
					<p class="text-xs text-white/50 mb-4">
						Sorted by priority — impact × speed, plus a bonus when linked items add value (shown as own → combined)
					</p>
					<div class="space-y-1.5">
						<div v-for="(item, idx) in priorityRanking" :key="item.id"
							class="flex items-center gap-3 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2">
							<span class="w-6 text-xs text-white/40 flex-shrink-0">#{{ idx + 1 }}</span>
							<span class="w-2 h-2 rounded-full flex-shrink-0"
								:class="item.type === 'challenge' ? 'bg-mp-danger' : 'bg-mp-teal'"></span>
							<span class="flex-1 text-sm text-white">{{ item.title }}</span>
							<span class="text-xs text-white/50 hidden sm:inline">{{ item.duration_label }}</span>
							<span class="text-xs text-white/50 hidden sm:inline">Impact {{ item.impact_score }}</span>
							<span class="text-xs font-semibold text-mp-teal text-right" :class="hasBonus(item) ? 'w-24' : 'w-10'">
								<template v-if="hasBonus(item)">{{ item.priority_score }} → {{ item.combined_priority_score }}</template>
								<template v-else>{{ item.priority_score }}</template>
							</span>
						</div>
						<p v-if="priorityRanking.length === 0" class="text-xs text-white/30 italic">Nothing here yet</p>
					</div>
				</div>

				<!-- ── QUADRANT CHART ── -->
				<div class="bg-mp-card border border-mp-border rounded-xl p-5">
					<div class="flex items-center justify-between mb-1">
						<h2 class="text-sm font-semibold text-white uppercase tracking-widest">Impact vs. time to resolve</h2>
						<div class="flex items-center gap-4 text-xs text-white/70">
							<span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-mp-danger"></span> Challenges</span>
							<span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-mp-teal"></span> Potentials</span>
						</div>
					</div>
					<p class="text-[11px] text-white/40 mb-3">Position = own impact &amp; time · dot size = combined priority (bigger = more valuable once linked items count)</p>
					<div style="height: 340px">
						<canvas ref="chartCanvas"></canvas>
					</div>
				</div>

				<!-- ── PHASED ROADMAP ── -->
				<div>
					<h2 class="text-sm font-semibold text-white uppercase tracking-widest mb-3">Phasing / roadmap</h2>
					<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
						<div v-for="phase in phaseColumns" :key="phase.key"
							class="bg-mp-card border border-mp-border rounded-xl p-4">
							<div class="flex items-center gap-2 mb-3">
								<span class="w-2.5 h-2.5 rounded-full" :class="phase.dot"></span>
								<p class="text-xs font-semibold text-white uppercase tracking-widest">{{ phase.label }}</p>
								<span class="text-xs text-white/50 ml-auto">{{ phase.items.length }}</span>
							</div>
							<p class="text-xs text-white/50 mb-3">{{ phase.hint }}</p>
							<div class="space-y-2">
								<div v-for="item in phase.items" :key="item.id"
									class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-xs">
									<span class="inline-block w-1.5 h-1.5 rounded-full mr-1.5"
										:class="item.type === 'challenge' ? 'bg-mp-danger' : 'bg-mp-teal'"></span>
									{{ item.title }}
								</div>
								<p v-if="phase.items.length === 0" class="text-xs text-white/30 italic">Nothing here</p>
							</div>
						</div>
					</div>
				</div>

				<!-- ── CHALLENGES / POTENTIALS COLUMNS ── -->
				<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
					<ItemColumn type="challenge" title="Challenges" accent="mp-danger"
						:items="filteredChallenges" :areas="areas" :opposite-items="filteredPotentials"
						@add="openAddForm" @edit="openEditForm" @delete="deleteItem" @link="openLinkModal" />
					<ItemColumn type="potential" title="Potentials" accent="mp-teal"
						:items="filteredPotentials" :areas="areas" :opposite-items="filteredChallenges"
						@add="openAddForm" @edit="openEditForm" @delete="deleteItem" @link="openLinkModal" />
				</div>

				<!-- ── DISCUSSED DIRECTION ── -->
				<div id="direction" class="bg-mp-card border border-mp-border rounded-xl p-5">
					<div class="flex items-center justify-between mb-1">
						<h2 class="text-sm font-semibold text-white uppercase tracking-widest flex items-center gap-2">
							<span class="inline-block w-2.5 h-2.5 rounded-full bg-mp-gold"></span>
							{{ directionLabelDisplay }} ({{ filteredDirections.length }})
							<button @click="renameDirectionSection"
								class="text-white/30 hover:text-white text-xs font-normal normal-case tracking-normal">✎ rename</button>
						</h2>
						<button @click="openAddForm('direction')"
							class="text-xs px-3 py-1.5 rounded-lg bg-mp-card-hover border border-mp-border text-white hover:border-mp-gold">
							+ Add
						</button>
					</div>
					<p class="text-[11px] text-white/40 mb-4">
						What you've decided to do about the Challenges &amp; Potentials above — each line can link back to
						them and carries its own start / finish dates.
					</p>
					<div class="space-y-2">
						<div v-for="item in sortedDirections" :key="item.id"
							class="bg-mp-card-hover border border-mp-border rounded-lg p-3">
							<div class="flex items-start justify-between gap-2 mb-1.5">
								<p class="text-sm text-white font-medium">{{ item.title }}</p>
								<div class="flex items-center gap-1 flex-shrink-0">
									<button class="text-white/40 hover:text-white text-xs" @click="openEditForm(item)">✎</button>
									<button class="text-white/40 hover:text-mp-danger text-xs" @click="deleteItem(item)">✕</button>
								</div>
							</div>
							<p v-if="item.description" class="text-xs text-white/60 mb-2">{{ item.description }}</p>
							<div class="flex flex-wrap items-center gap-1.5 text-[11px] mb-1.5">
								<span v-for="a in item.areas" :key="a.id" class="px-2 py-0.5 rounded-full bg-white/10 text-white/70">
									{{ a.name }} · {{ a.impact_score }}
								</span>
							</div>
							<div class="flex flex-wrap items-center gap-1.5 text-[11px]">
								<span class="px-2 py-0.5 rounded-full bg-white/10 text-white/70">Impact {{ item.impact_score }}</span>
								<span class="px-2 py-0.5 rounded-full bg-white/10 text-white/70">{{ item.duration_label }}</span>
								<span v-if="item.es_date_label" class="px-2 py-0.5 rounded-full bg-mp-gold/10 text-mp-gold">
									ES {{ item.es_date_label }}
								</span>
								<span v-if="item.ef_date_label" class="px-2 py-0.5 rounded-full bg-mp-gold/10 text-mp-gold">
									EF {{ item.ef_date_label }}
								</span>
								<span class="px-2 py-0.5 rounded-full bg-white/5 text-white/50 capitalize">{{ item.status.replace('_', ' ') }}</span>
							</div>
							<p v-if="item.linked_items?.length" class="text-[11px] text-white/40 mt-2">
								Addresses: {{ item.linked_items.map(l => l.title).join(', ') }}
							</p>
						</div>
						<p v-if="sortedDirections.length === 0" class="text-xs text-white/30 italic">Nothing here yet</p>
					</div>
				</div>

				<!-- ── DISCUSSED DIRECTION TIMELINE ── -->
				<div class="bg-mp-card border border-mp-border rounded-xl p-5">
					<h2 class="text-sm font-semibold text-white uppercase tracking-widest mb-1">
						{{ directionLabelDisplay }} timeline
					</h2>
					<p class="text-[11px] text-white/40 mb-3">Each bar runs from its ES (start) to EF (finish) date · color = status</p>
					<div v-if="sortedDirections.length" :style="{ height: Math.max(160, sortedDirections.length * 42) + 'px' }">
						<canvas ref="directionChartCanvas"></canvas>
					</div>
					<p v-else class="text-xs text-white/30 italic">Add a {{ directionLabelDisplay.toLowerCase() }} item with ES/EF dates to see it plotted here</p>
				</div>
			</div>
		</div>

		<!-- ── ADD / EDIT ITEM MODAL ── -->
		<div v-if="itemModal.show" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
			@click.self="itemModal.show = false">
			<div class="bg-mp-card border border-mp-border rounded-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
				<h3 class="text-lg font-semibold text-white mb-4">
					{{ itemModal.editing ? 'Edit' : 'New' }} {{ itemModalTypeLabel }}
				</h3>
				<form @submit.prevent="submitItem">
					<label class="block text-xs text-white/70 uppercase tracking-widest mb-1.5">Title</label>
					<input v-model="itemForm.title" type="text" required
						class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white mb-4 focus:outline-none focus:border-mp-teal" />

					<label class="block text-xs text-white/70 uppercase tracking-widest mb-1.5">
						Business areas this affects
					</label>
					<p class="text-[11px] text-white/40 mb-2">
						Add every area this touches — e.g. a single-supplier issue can hit both Cash Flow and Cost.
						Each area gets its own impact weight; the item is prioritized by its highest one.
					</p>
					<div class="space-y-2 mb-2">
						<div v-for="(row, idx) in itemForm.areas" :key="idx"
							class="flex items-center gap-2 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2">
							<select v-model="row.business_radar_area_id"
								class="flex-1 bg-mp-card border border-mp-border rounded px-2 py-1.5 text-xs text-white focus:outline-none">
								<option :value="null" disabled>Choose area…</option>
								<option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
							</select>
							<input type="range" min="1" max="5" v-model.number="row.impact_score" class="w-24" />
							<span class="text-[11px] text-white/60 w-20 flex-shrink-0">{{ impactLabels[row.impact_score] }}</span>
							<button type="button" @click="removeAreaRow(idx)"
								class="text-white/40 hover:text-mp-danger flex-shrink-0">✕</button>
						</div>
					</div>
					<div class="flex gap-2 mb-4">
						<button type="button" @click="addAreaRow"
							class="px-3 py-1.5 rounded-lg border border-mp-border text-xs text-white hover:border-mp-teal">
							+ Add another area
						</button>
						<button type="button" @click="addCustomArea"
							class="px-3 py-1.5 rounded-lg border border-mp-border text-xs text-white hover:border-mp-teal">
							+ New custom area
						</button>
					</div>

					<label class="block text-xs text-white/70 uppercase tracking-widest mb-1.5">Description</label>
					<textarea v-model="itemForm.description" rows="2"
						class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white mb-4 focus:outline-none focus:border-mp-teal"></textarea>

					<div v-if="itemModal.type !== 'direction'" class="mb-4">
						<label class="block text-xs text-white/70 uppercase tracking-widest mb-1.5">
							{{ itemModal.type === 'challenge' ? 'Time to resolve' : 'Time to activate' }}
							({{ durationLabel(itemForm.duration_months) }})
						</label>
						<input type="range" min="1" :max="durationOptions.length"
							:value="durationRankFor(itemForm.duration_months)"
							@input="itemForm.duration_months = durationOptions[$event.target.value - 1].months"
							class="w-full" />
					</div>

					<template v-if="itemModal.type === 'direction'">
						<div class="grid grid-cols-2 gap-3 mb-4">
							<div>
								<label class="block text-xs text-white/70 uppercase tracking-widest mb-1.5">ES (start)</label>
								<input v-model="itemForm.es_date" type="date"
									class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-mp-teal" />
							</div>
							<div>
								<label class="block text-xs text-white/70 uppercase tracking-widest mb-1.5">EF (finish)</label>
								<input v-model="itemForm.ef_date" type="date" :min="itemForm.es_date || undefined"
									class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-mp-teal" />
							</div>
						</div>
						<p class="text-[11px] text-white/40 -mt-3 mb-4">
							Time-weighting for ordering is calculated automatically from these dates — no separate duration to set.
						</p>

						<label class="block text-xs text-white/70 uppercase tracking-widest mb-1.5">
							Addresses these Challenges / Potentials
						</label>
						<div class="max-h-40 overflow-y-auto space-y-1.5 mb-4 bg-mp-card-hover border border-mp-border rounded-lg p-2.5">
							<label v-for="c in linkableItems" :key="c.id"
								class="flex items-center gap-2 text-xs text-white/80 px-1 py-1">
								<input type="checkbox" :value="c.id" v-model="itemForm.linked_item_ids" class="rounded border-mp-border" />
								<span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="c.type === 'challenge' ? 'bg-mp-danger' : 'bg-mp-teal'"></span>
								{{ c.title }}
							</label>
							<p v-if="linkableItems.length === 0" class="text-xs text-white/30 italic px-1">
								No challenges or potentials on this board yet
							</p>
						</div>
					</template>

					<label class="block text-xs text-white/70 uppercase tracking-widest mb-1.5">Status</label>
					<select v-model="itemForm.status"
						class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white mb-4 focus:outline-none">
						<option value="open">Open</option>
						<option value="in_progress">In progress</option>
						<option :value="resolvedStatusValue">{{ resolvedStatusLabel }}</option>
					</select>

					<label class="block text-xs text-white/70 uppercase tracking-widest mb-1.5">Notes</label>
					<textarea v-model="itemForm.notes" rows="2"
						class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-sm text-white mb-5 focus:outline-none focus:border-mp-teal"></textarea>

					<div class="flex justify-end gap-3">
						<button type="button" @click="itemModal.show = false"
							class="px-4 py-2 rounded-lg border border-mp-border text-white text-sm">Cancel</button>
						<button type="submit"
							class="px-4 py-2 rounded-lg bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium">
							{{ itemModal.editing ? 'Save changes' : 'Add' }}
						</button>
					</div>
				</form>
			</div>
		</div>

		<!-- ── LINK MODAL ── -->
		<div v-if="linkModal.show" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4"
			@click.self="linkModal.show = false">
			<div class="bg-mp-card border border-mp-border rounded-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
				<h3 class="text-lg font-semibold text-white mb-1">Link "{{ linkModal.item?.title }}"</h3>
				<p class="text-xs text-white/50 mb-4">
					Connect to {{ linkModal.item?.type === 'challenge' ? 'potentials this unlocks' : 'challenges this depends on' }}
				</p>

				<div v-if="linkModal.item?.links?.length" class="mb-4 space-y-2">
					<p class="text-xs text-white/70 uppercase tracking-widest mb-1.5">Currently linked</p>
					<div v-for="l in linkModal.item.links" :key="l.link_id"
						class="flex items-center justify-between bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-xs">
						<span>{{ l.title }} <span class="text-white/40">— {{ strengthLabels[l.strength] }}</span></span>
						<button @click="removeLink(l.link_id)" class="text-white/40 hover:text-mp-danger">✕</button>
					</div>
				</div>

				<div class="space-y-2 mb-5">
					<p class="text-xs text-white/70 uppercase tracking-widest mb-1.5">Add a link</p>
					<div v-for="opp in linkModal.candidates" :key="opp.id"
						class="flex items-center gap-2 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2">
						<span class="flex-1 text-xs">{{ opp.title }}</span>
						<select v-model.number="linkStrengths[opp.id]"
							class="bg-mp-card border border-mp-border rounded px-2 py-1 text-xs text-white">
							<option :value="1">Weak</option>
							<option :value="2">Medium</option>
							<option :value="3">Strong</option>
						</select>
						<button @click="addLink(opp)"
							class="px-2 py-1 rounded bg-mp-teal/20 text-mp-teal text-xs hover:bg-mp-teal/30">Link</button>
					</div>
					<p v-if="linkModal.candidates.length === 0" class="text-xs text-white/30 italic">
						No unlinked {{ linkModal.item?.type === 'challenge' ? 'potentials' : 'challenges' }} available
					</p>
				</div>

				<div class="flex justify-end">
					<button @click="linkModal.show = false"
						class="px-4 py-2 rounded-lg border border-mp-border text-white text-sm">Close</button>
				</div>
			</div>
		</div>
	</AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, reactive, onMounted, watch, nextTick, h, defineComponent } from 'vue'

const props = defineProps({
	company: Object,
	board:   Object,
	items:   { type: Array, default: () => [] },
	areas:   { type: Array, default: () => [] },
	durationOptions: { type: Array, default: () => [] },
})

const directionLabelDisplay = computed(() => props.board.direction_label || 'Discussed Direction')

function renameDirectionSection() {
	const name = prompt('Rename this section:', directionLabelDisplay.value)
	if (!name) return
	router.put(baseUrl, {
		name: props.board.name,
		description: props.board.description,
		direction_label: name,
	}, { preserveScroll: true })
}

const baseUrl = `/portfolio-companies/${props.company.id}/business-radar/${props.board.id}`

const showResolved = ref(false)
const areaFilter    = ref('')

const impactLabels = { 1: 'Minor', 2: 'Noticeable', 3: 'Moderate', 4: 'Significant', 5: 'Critical' }
const strengthLabels = { 1: 'Weak', 2: 'Medium', 3: 'Strong' }

function durationLabel(months) {
	return props.durationOptions.find(o => o.months === months)?.label ?? `${months} months`
}
function durationRankFor(months) {
	const idx = props.durationOptions.findIndex(o => o.months === months)
	return idx === -1 ? 1 : idx + 1
}
function hasBonus(item) {
	return item.combined_priority_score > item.priority_score
}

const resolvedStatuses = ['resolved', 'captured', 'done']

function itemHasArea(item, areaId) {
	return (item.areas || []).some(a => a.id === areaId)
}

function passesFilters(item) {
	if (!showResolved.value && resolvedStatuses.includes(item.status)) return false
	if (areaFilter.value && !itemHasArea(item, areaFilter.value)) return false
	return true
}

const filteredChallenges = computed(() => props.items.filter(i => i.type === 'challenge' && passesFilters(i)))
const filteredPotentials = computed(() => props.items.filter(i => i.type === 'potential' && passesFilters(i)))
const filteredDirections = computed(() => props.items.filter(i => i.type === 'direction' && passesFilters(i)))

// Same "order by weight/time" logic as Challenges & Potentials (priority = impact x speed)
const sortedDirections = computed(() =>
	[...filteredDirections.value].sort((a, b) => b.priority_score - a.priority_score)
)

// Challenges/Potentials this board has, for the "addresses" checklist —
// unaffected by the resolved/area filters so a direction can still point
// at something already marked resolved/captured.
const linkableItems = computed(() =>
	props.items
		.filter(i => i.type === 'challenge' || i.type === 'potential')
		.slice()
		.sort((a, b) => a.title.localeCompare(b.title))
)

// ── Priority ranking: impact x speed, plus linked-item bonus, best quick wins first ──
const priorityRanking = computed(() =>
	[...filteredChallenges.value, ...filteredPotentials.value]
		.slice()
		.sort((a, b) => b.combined_priority_score - a.combined_priority_score)
)

// ── Phased roadmap (active items only, regardless of area filter's resolved toggle) ──
const phaseMeta = {
	quick_win:  { label: 'Quick wins',            hint: 'High impact, fast to do — best quick wins', dot: 'bg-mp-teal' },
	short_term: { label: 'Short-term',            hint: 'Lower impact but fast — fill-in work',       dot: 'bg-mp-gold' },
	long_term:  { label: 'Long-term / structural', hint: 'High impact but slow — plan for these', dot: 'bg-mp-danger' },
	monitor:    { label: 'Monitor',               hint: 'Lower impact and slow — watch, don\'t prioritize', dot: 'bg-mp-border' },
}

const phaseColumns = computed(() => {
	const active = props.items.filter(i => i.type !== 'direction' && !resolvedStatuses.includes(i.status) && (!areaFilter.value || itemHasArea(i, areaFilter.value)))
	return Object.entries(phaseMeta).map(([key, meta]) => ({
		key,
		...meta,
		items: active.filter(i => i.phase === key),
	}))
})

// ── Chart ──
const chartCanvas = ref(null)
let chartInstance = null
const directionChartCanvas = ref(null)
let directionChartInstance = null

onMounted(async () => {
	await nextTick()
	buildChart()
	buildDirectionChart()

	if (new URLSearchParams(window.location.search).get('openDirection') === '1') {
		document.getElementById('direction')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
		openAddForm('direction')
	}
})
watch([filteredChallenges, filteredPotentials], async () => { await nextTick(); buildChart() })
watch(sortedDirections, async () => { await nextTick(); buildDirectionChart() })

function ensureChartJs(callback) {
	if (typeof Chart !== 'undefined') { callback(); return }
	const existing = document.getElementById('mp-chartjs-cdn')
	if (existing) { existing.addEventListener('load', callback, { once: true }); return }
	const script = document.createElement('script')
	script.id = 'mp-chartjs-cdn'
	script.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js'
	script.onload = callback
	document.head.appendChild(script)
}

function buildChart() {
	if (!chartCanvas.value) return
	if (chartInstance) { chartInstance.destroy(); chartInstance = null }
	ensureChartJs(() => renderChart(chartCanvas.value.getContext('2d')))
}

function renderChart(ctx) {
	const toPoint = (i) => ({
		x: i.duration_rank,
		y: i.impact_score,
		title: i.title,
		durationLabel: i.duration_label,
		combined: i.combined_priority_score,
		own: i.priority_score,
	})
	const tickLabel = (rank) => props.durationOptions[rank - 1]?.label.replace(' months', 'mo').replace(' month', 'mo') ?? rank

	// Dot size reflects the Combined priority score (own + linked bonus),
	// so a well-connected item stands out even when its own impact/time
	// position looks unremarkable. Position itself always stays honest —
	// only the size changes.
	const radiusFor = (combined) => Math.max(6, Math.min(20, 5 + combined / 3))

	chartInstance = new Chart(ctx, {
		type: 'scatter',
		data: {
			datasets: [
				{
					label: 'Challenges',
					data: filteredChallenges.value.map(toPoint),
					backgroundColor: '#e2534a',
					pointRadius: (c) => radiusFor(c.raw?.combined ?? 1),
					pointHoverRadius: (c) => radiusFor(c.raw?.combined ?? 1) + 2,
				},
				{
					label: 'Potentials',
					data: filteredPotentials.value.map(toPoint),
					backgroundColor: '#00b4c8',
					pointRadius: (c) => radiusFor(c.raw?.combined ?? 1),
					pointHoverRadius: (c) => radiusFor(c.raw?.combined ?? 1) + 2,
				},
			],
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			scales: {
				x: {
					title: { display: true, text: 'Time to resolve / activate (fast → slow)', color: '#ffffff99' },
					min: 0.5, max: props.durationOptions.length + 0.5,
					ticks: { stepSize: 1, color: '#ffffff99', callback: (v) => tickLabel(v) },
					grid: { color: '#ffffff1a' },
				},
				y: {
					title: { display: true, text: 'Impact (1 = minor → 5 = critical)', color: '#ffffff99' },
					min: 0.5, max: 5.5,
					ticks: { stepSize: 1, color: '#ffffff99' },
					grid: { color: '#ffffff1a' },
				},
			},
			plugins: {
				legend: { display: false },
				tooltip: {
					callbacks: {
						label: (ctx) => ctx.raw.combined > ctx.raw.own
							? `${ctx.raw.title} — impact ${ctx.raw.y}, ${ctx.raw.durationLabel}, priority ${ctx.raw.own} → ${ctx.raw.combined} (dot size)`
							: `${ctx.raw.title} — impact ${ctx.raw.y}, ${ctx.raw.durationLabel}, priority ${ctx.raw.own}`,
					},
				},
			},
		},
	})
}

// ── Discussed Direction timeline (Gantt-style floating bars, ES → EF) ──
const statusColors = {
	open:        '#c9a84c', // mp-gold
	in_progress: '#00b4c8', // mp-teal
	done:        '#ffffff4d',
}

function buildDirectionChart() {
	if (!directionChartCanvas.value) return
	if (directionChartInstance) { directionChartInstance.destroy(); directionChartInstance = null }
	if (sortedDirections.value.length === 0) return
	ensureChartJs(() => renderDirectionChart(directionChartCanvas.value.getContext('2d')))
}

function renderDirectionChart(ctx) {
	// Chart displayed top-to-bottom in the same order as the list above.
	const rows = [...sortedDirections.value].reverse()

	const dayMs = 24 * 60 * 60 * 1000
	const toDay = (dateStr) => Math.floor(new Date(dateStr).getTime() / dayMs)
	const todayDay = toDay(new Date().toISOString().slice(0, 10))

	const bars = rows.map(item => {
		const hasEs = !!item.es_date
		const hasEf = !!item.ef_date
		const start = hasEs ? toDay(item.es_date) : (hasEf ? toDay(item.ef_date) - 30 : todayDay)
		const end   = hasEf ? toDay(item.ef_date) : start + Math.max(30, item.duration_months * 30)
		return { range: [start, Math.max(end, start + 1)], item }
	})

	const allDays = bars.flatMap(b => b.range)
	const minDay = Math.min(...allDays, todayDay) - 3
	const maxDay = Math.max(...allDays, todayDay) + 3

	const dayToLabel = (day) => new Date(day * dayMs).toLocaleDateString(undefined, { day: 'numeric', month: 'short' })

	directionChartInstance = new Chart(ctx, {
		type: 'bar',
		data: {
			labels: rows.map(item => item.title),
			datasets: [{
				data: bars.map(b => b.range),
				backgroundColor: bars.map(b => statusColors[b.item.status] || statusColors.open),
				borderRadius: 4,
				barPercentage: 0.6,
			}],
		},
		options: {
			indexAxis: 'y',
			responsive: true,
			maintainAspectRatio: false,
			scales: {
				x: {
					min: minDay, max: maxDay,
					ticks: { color: '#ffffff99', callback: (v) => dayToLabel(v) },
					grid: { color: '#ffffff1a' },
				},
				y: {
					ticks: { color: '#ffffffcc', autoSkip: false },
					grid: { display: false },
				},
			},
			plugins: {
				legend: { display: false },
				tooltip: {
					callbacks: {
						title: (items) => rows[items[0].dataIndex].title,
						label: (c) => {
							const item = rows[c.dataIndex]
							const range = item.es_date_label && item.ef_date_label
								? `${item.es_date_label} → ${item.ef_date_label}`
								: 'Dates not fully set — estimated bar'
							return `${range} · ${item.status.replace('_', ' ')} · priority ${item.priority_score}`
						},
					},
				},
			},
		},
	})
}

// ── Add / Edit item ──
const itemModal = reactive({ show: false, editing: null, type: 'challenge' })
const itemForm = reactive({
	title: '',
	description: '',
	duration_months: 1,
	es_date: '',
	ef_date: '',
	status: 'open',
	notes: '',
	areas: [{ business_radar_area_id: null, impact_score: 3 }],
	linked_item_ids: [],
})

const itemModalTypeLabel = computed(() => ({ challenge: 'challenge', potential: 'potential', direction: directionLabelDisplay.value.toLowerCase() }[itemModal.type]))
const resolvedStatusValue = computed(() => ({ challenge: 'resolved', potential: 'captured', direction: 'done' }[itemModal.type]))
const resolvedStatusLabel = computed(() => ({ challenge: 'Resolved', potential: 'Captured', direction: 'Done' }[itemModal.type]))

function resetItemForm() {
	Object.assign(itemForm, {
		title: '',
		description: '',
		duration_months: 1,
		es_date: '',
		ef_date: '',
		status: 'open',
		notes: '',
		areas: [{ business_radar_area_id: null, impact_score: 3 }],
		linked_item_ids: [],
	})
}

function addAreaRow() {
	itemForm.areas.push({ business_radar_area_id: null, impact_score: 3 })
}

function removeAreaRow(idx) {
	if (itemForm.areas.length <= 1) return
	itemForm.areas.splice(idx, 1)
}

function openAddForm(type) {
	resetItemForm()
	itemModal.editing = null
	itemModal.type = type
	itemModal.show = true
}

function openEditForm(item) {
	itemModal.editing = item
	itemModal.type = item.type
	Object.assign(itemForm, {
		title: item.title,
		description: item.description ?? '',
		duration_months: item.duration_months,
		es_date: item.es_date ?? '',
		ef_date: item.ef_date ?? '',
		status: item.status,
		notes: item.notes ?? '',
		areas: item.areas?.length
			? item.areas.map(a => ({ business_radar_area_id: a.id, impact_score: a.impact_score }))
			: [{ business_radar_area_id: null, impact_score: 3 }],
		linked_item_ids: item.linked_items?.map(l => l.item_id) ?? [],
	})
	itemModal.show = true
}

function submitItem() {
	const cleanAreas = itemForm.areas.filter(a => a.business_radar_area_id)
	if (cleanAreas.length === 0) {
		alert('Add at least one business area.')
		return
	}
	const payload = { ...itemForm, areas: cleanAreas }

	if (itemModal.editing) {
		router.put(`${baseUrl}/items/${itemModal.editing.id}`, payload, {
			onSuccess: () => { itemModal.show = false },
		})
	} else {
		router.post(`${baseUrl}/items`, { ...payload, type: itemModal.type }, {
			onSuccess: () => { itemModal.show = false },
		})
	}
}

function deleteItem(item) {
	if (!confirm(`Delete "${item.title}"?`)) return
	router.delete(`${baseUrl}/items/${item.id}`)
}

function addCustomArea() {
	const name = prompt('New business area name:')
	if (!name) return
	router.post(`/portfolio-companies/${props.company.id}/business-radar/areas`, { name }, { preserveScroll: true })
}

// ── Links ──
const linkModal = reactive({ show: false, item: null, candidates: [] })
const linkStrengths = reactive({})

function openLinkModal(item) {
	linkModal.item = item
	const linkedIds = new Set((item.links || []).map(l => l.item_id))
	const pool = item.type === 'challenge' ? filteredPotentials.value : filteredChallenges.value
	linkModal.candidates = pool.filter(i => !linkedIds.has(i.id))
	linkModal.candidates.forEach(c => { linkStrengths[c.id] = linkStrengths[c.id] || 2 })
	linkModal.show = true
}

function addLink(candidate) {
	const item = linkModal.item
	const challenge_item_id = item.type === 'challenge' ? item.id : candidate.id
	const potential_item_id = item.type === 'challenge' ? candidate.id : item.id
	router.post(`${baseUrl}/links`, {
		challenge_item_id,
		potential_item_id,
		strength: linkStrengths[candidate.id] || 2,
	}, {
		preserveScroll: true,
		onSuccess: () => { linkModal.show = false },
	})
}

function removeLink(linkId) {
	router.delete(`${baseUrl}/links/${linkId}`, {
		preserveScroll: true,
		onSuccess: () => { linkModal.show = false },
	})
}

// ── Item column sub-component (kept local to this file) ──
const ItemColumn = defineComponent({
	props: {
		type: String,
		title: String,
		accent: String,
		items: Array,
		areas: Array,
		oppositeItems: Array,
	},
	emits: ['add', 'edit', 'delete', 'link'],
	setup(p, { emit }) {
		const phaseBadge = {
			quick_win:  { label: 'Quick win',   cls: 'bg-mp-teal/20 text-mp-teal' },
			short_term: { label: 'Short-term',  cls: 'bg-mp-gold/20 text-mp-gold' },
			long_term:  { label: 'Long-term',   cls: 'bg-mp-danger/20 text-mp-danger' },
			monitor:    { label: 'Monitor',     cls: 'bg-white/10 text-white/60' },
		}
		return () => h('div', { class: 'bg-mp-card border border-mp-border rounded-xl p-5' }, [
			h('div', { class: 'flex items-center justify-between mb-4' }, [
				h('h2', { class: 'text-sm font-semibold text-white uppercase tracking-widest' }, [
					h('span', { class: `inline-block w-2.5 h-2.5 rounded-full bg-${p.accent} mr-2` }),
					p.title,
					` (${p.items.length})`,
				]),
				h('button', {
					class: 'text-xs px-3 py-1.5 rounded-lg bg-mp-card-hover border border-mp-border text-white hover:border-mp-teal',
					onClick: () => emit('add', p.type),
				}, '+ Add'),
			]),
			h('div', { class: 'space-y-3' }, p.items.length
				? p.items.map(item => h('div', {
					key: item.id,
					class: 'bg-mp-card-hover border border-mp-border rounded-lg p-3',
				}, [
					h('div', { class: 'flex items-start justify-between gap-2 mb-1.5' }, [
						h('p', { class: 'text-sm text-white font-medium' }, item.title),
						h('div', { class: 'flex items-center gap-1 flex-shrink-0' }, [
							h('button', { class: 'text-white/40 hover:text-white text-xs', onClick: () => emit('link', item) }, '🔗'),
							h('button', { class: 'text-white/40 hover:text-white text-xs', onClick: () => emit('edit', item) }, '✎'),
							h('button', { class: 'text-white/40 hover:text-mp-danger text-xs', onClick: () => emit('delete', item) }, '✕'),
						]),
					]),
					item.description ? h('p', { class: 'text-xs text-white/60 mb-2' }, item.description) : null,
					h('div', { class: 'flex flex-wrap items-center gap-1.5 text-[11px] mb-1.5' },
						(item.areas || []).map(a => h('span', {
							key: a.id,
							class: 'px-2 py-0.5 rounded-full bg-white/10 text-white/70',
						}, `${a.name} · ${a.impact_score}`))),
					h('div', { class: 'flex flex-wrap items-center gap-1.5 text-[11px]' }, [
						h('span', { class: 'px-2 py-0.5 rounded-full bg-white/10 text-white/70' }, `Highest impact ${item.impact_score}`),
						h('span', { class: 'px-2 py-0.5 rounded-full bg-white/10 text-white/70' }, item.duration_label),
						h('span', { class: 'px-2 py-0.5 rounded-full bg-mp-teal/10 text-mp-teal' },
							item.combined_priority_score > item.priority_score
								? `Priority ${item.priority_score} → ${item.combined_priority_score}`
								: `Priority ${item.priority_score}`),
						h('span', { class: `px-2 py-0.5 rounded-full ${phaseBadge[item.phase].cls}` }, phaseBadge[item.phase].label),
						h('span', { class: 'px-2 py-0.5 rounded-full bg-white/5 text-white/50 capitalize' }, item.status.replace('_', ' ')),
					]),
					item.links?.length ? h('p', { class: 'text-[11px] text-white/40 mt-2' },
						`Linked to: ${item.links.map(l => l.title).join(', ')}`) : null,
				]))
				: [h('p', { class: 'text-xs text-white/30 italic' }, 'Nothing here yet')]),
		])
	},
})
</script>
