<template>

	<Head :title="`Edit Contract — ${contract.name}`" />
	<AuthenticatedLayout>
		<div class="min-h-screen bg-mp-page text-white">
			<div class="bg-mp-card border-b border-mp-border">
				<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
					<Link :href="`/portfolio-companies/${customer.id}/contracts`"
						class="flex items-center gap-2 text-sm text-white/60 hover:text-white transition-colors mb-2 w-fit">
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
						</svg>
						{{ customer.name }} — Contracts
					</Link>
					<h1 class="text-2xl font-bold text-white">Edit: {{ contract.name }}</h1>
					<p v-if="contract.code" class="text-white/50 text-sm mt-1 font-mono">{{ contract.code }}</p>
					<p class="text-white/60 text-sm mt-1">Update contract details and services</p>
				</div>
			</div>
			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
				<form @submit.prevent="submit" class="space-y-8">
					<ContractForm v-model="form" :errors="form.errors" />
					<ServicesRepeater v-model="form.services" :currency="form.currency" :errors="form.errors" :existing-services="existingServices" />
					<div class="bg-mp-card rounded-xl border border-mp-teal/40 p-4 flex items-center justify-between">
						<span class="text-xs font-semibold text-white/60 uppercase tracking-widest">Total Contract Value</span>
						<span class="text-xl font-bold text-mp-teal">
							{{ fmtAmt(totalAmount) }} <span class="text-sm text-white/50 ml-1">{{ form.currency }}</span>
						</span>
					</div>
					<div class="flex items-center justify-end gap-4 pb-8">
						<Link :href="`/portfolio-companies/${customer.id}/contracts`"
							class="px-6 py-3 rounded-lg border border-mp-border text-white text-sm font-medium transition-colors">
							Cancel </Link>
						<button type="submit" :disabled="form.processing"
							class="flex items-center gap-2 disabled:opacity-50 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-8 py-3 rounded-lg transition-colors">
							<svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
								<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
								<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
							</svg>
							{{ form.processing ? 'Saving…' : 'Save Changes' }}
						</button>
					</div>
				</form>
			</div>
		</div>
	</AuthenticatedLayout>
</template>
<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ContractForm from './Partials/ContractForm.vue'
import ServicesRepeater from './Partials/ServicesRepeater.vue'

const props = defineProps({
  customer: Object,
  contract: Object,
  existingServices: { type: Array, default: () => [] },
})

const form = useForm({
	name: props.contract.name,
	code: props.contract.code ?? '',
	start_date: props.contract.start_date ?? '',
	end_date: props.contract.end_date ?? '',
	currency: props.contract.currency,
	notes: props.contract.notes ?? '',
	services: props.contract.services.length
		? props.contract.services.map(s => ({
			id: s.id,
			name: s.name,
			description: s.description ?? '',
			amount: String(s.amount),
			start_date: s.start_date ?? '',
			end_date: s.end_date ?? '',
			milestones: s.milestones ?? [],
		}))
		: [{ name: '', description: '', amount: '', start_date: '', end_date: '', milestones: [] }],
})

const totalAmount = computed(() =>
	form.services.reduce((sum, s) => sum + (parseFloat(s.amount) || 0), 0)
)

function fmtAmt(v) {
	return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

function submit() {
	form.put(`/portfolio-companies/${props.customer.id}/contracts/${props.contract.id}`)
}
</script>
