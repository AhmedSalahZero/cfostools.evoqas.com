<template>

	<Head title="Customers" />
	<AuthenticatedLayout>
		<div class="min-h-screen bg-mp-page text-white">
			<!-- PAGE HEADER -->
			 
			
			
			
			
			
			<div class="bg-mp-card border-b border-mp-border">
				<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
					<div class="flex items-center justify-between">
						<div>
							<h1 class="text-2xl font-bold text-white">Customers</h1>
							<p class="text-white text-sm mt-1">
								{{ customers.length }} customer{{ customers.length !== 1 ? 's' : '' }}
							</p>
						</div>
						<div class="flex items-center gap-2">
							<Link v-if="isAdmin" href="/portfolio-companies/create"
								class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
								<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
										d="M12 4v16m8-8H4" />
								</svg> Add Customer
							</Link>
							<!-- <Link
                href="/investor-decision"
                class="flex items-center gap-2 bg-mp-gold/60 hover:bg-mp-gold/70 border border-mp-gold/60 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <span>⚖️</span>
                Investor Decision Tool
              </Link> -->
							<Link :href="safeOrgId ? `/organizations/${safeOrgId}/statistica` : '#'"
								class="flex items-center gap-2 bg-mp-success/60 hover:bg-mp-success/70 border border-mp-success/60 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
								<span>📊</span> Statistica
							</Link>
						</div>
					</div>
				</div>
			</div>
			<!-- MAIN CONTENT -->
			<div class="w-full px-4 sm:px-6 lg:px-8 py-8">
				<!-- Flash message -->
				<div v-if="$page.props.flash?.success"
					class="mb-6 bg-mp-success/15 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm">
					{{ $page.props.flash.success }}
				</div>
				<div v-if="customers.length === 0"
					class="bg-mp-card rounded-xl border border-mp-border border-dashed p-16 text-center">
					<div
						class="w-14 h-14 bg-mp-teal-subtle/50 rounded-xl flex items-center justify-center mx-auto mb-4">
						<svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
						</svg>
					</div>
					<p class="text-white font-medium mb-1">No customers yet</p>
					<p class="text-white text-sm mb-5">Add your first customer to get started</p>
					<Link href="/portfolio-companies/create"
						class="bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors inline-block">
						+ Add Customer </Link>
				</div>
				<div v-else class="bg-mp-card rounded-xl border border-mp-border overflow-visible">
					<table class="w-full text-sm">
						<thead>
							<tr class="border-b border-mp-border">
								<th
									class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">
									Customer</th>
								<th
									class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">
									Sector</th>
								<th
									class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">
									Status</th>
								<th
									class="text-center text-xs font-semibold text-white uppercase tracking-widest px-6 py-4">
									Actions</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-800">
							<tr v-for="customer in customers" :key="customer.id"
								class="hover:bg-mp-card-hover/50 transition-colors" style="height: 72px;">
								<td class="px-6 py-4">
									<div class="flex items-center gap-3">
										<div
											class="w-9 h-9 rounded-lg bg-mp-teal flex items-center justify-center text-sm font-bold flex-shrink-0">
											{{ customer.name.charAt(0).toUpperCase() }}
										</div>
										<div>
											<Link :href="`/portfolio-companies/${customer.id}`"
												class="font-semibold text-white hover:text-white transition-colors">
												{{ customer.name }}
											</Link>
										</div>
									</div>
								</td>
								<td class="px-6 py-4 text-white">{{ customer.sector }}</td>
								<td class="px-6 py-4">
									<span :class="statusClass(customer.status)"
										class="text-xs font-semibold px-2.5 py-1 rounded-full uppercase tracking-wide">
										{{ statusLabel(customer.status) }}
									</span>
								</td>
								<td class="px-6 py-4">
									<div class="flex items-center justify-center gap-2">
										<!-- Edit -->
										<Link v-if="isAdmin" :href="`/portfolio-companies/${customer.id}/edit`"
											class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-teal text-white hover:text-white transition-colors"
											title="Edit customer">
											<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
													d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
											</svg>
										</Link>
										<!-- Delete -->
										<button v-if="isAdmin" type="button" @click.stop="confirmDelete(customer)"
											class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors"
											title="Delete customer">
											<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
													d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
											</svg>
										</button>
										<!-- Contracts -->
										<Link :href="`/portfolio-companies/${customer.id}/contracts`"
											class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-gold-dark text-white hover:text-white transition-colors"
											title="Contracts">
											<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
													d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
											</svg>
										</Link>
										<!-- Dropdown trigger -->
										<div class="relative">
											<button type="button" :data-dropdown-btn="customer.id"
												@click.stop="toggleDropdown(customer.id)"
												class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-white hover:text-white transition-colors"
												title="More options">
												<svg class="w-4 h-4" fill="none" stroke="currentColor"
													viewBox="0 0 24 24">
													<path stroke-linecap="round" stroke-linejoin="round"
														stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
												</svg>
											</button>
											<!-- Dropdown menu -->
											<Teleport to="body">
												<div v-if="activeDropdown === customer.id"
													:style="getDropdownStyle(customer.id)" data-dropdown-menu="true"
													class="fixed z-50 w-56 bg-mp-card-hover border border-mp-border rounded-xl shadow-2xl overflow-hidden">
													<div class="px-4 py-2.5 border-b border-mp-border">
														<p
															class="text-xs text-white font-medium uppercase tracking-widest">
															{{ customer.name }}
														</p>
													</div>
													<div class="py-1">
														<Link :href="`/companies/${customer.id}/sales`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-teal/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-white" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
																</svg>
															</span> Sales Analysis
														</Link>
														<Link :href="`/companies/${customer.id}/export-sales`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-teal/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-white" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
																</svg>
															</span> Export Sales Analysis
														</Link>
														<Link :href="`/companies/${customer.id}/expenses/upload`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-danger/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-mp-danger" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10" />
																</svg>
															</span> Expense Analysis
														</Link>
														<Link
															:href="`/portfolio-companies/${customer.id}/financial-statements`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-success/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-mp-success" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
																</svg>
															</span> Financial Statement
														</Link>
														<Link :href="`/portfolio-companies/${customer.id}/budgets`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-warning/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-mp-warning" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m6-6v6m0 0V3m0 14h2a2 2 0 002-2v-4a2 2 0 00-2-2h-2" />
																</svg>
															</span> Budget &amp; Variance
														</Link>
														<Link
															:href="`/portfolio-companies/${customer.id}/cash-forecast`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-teal/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-white" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
																</svg>
															</span> Cash Forecast
														</Link>
														<Link :href="`/portfolio-companies/${customer.id}/kpi`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-warning/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-mp-warning" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
																</svg>
															</span> 📊 KPI Dashboard
														</Link>
														<Link
															:href="`/portfolio-companies/${customer.id}/financial-studies`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-gold-dark/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-white" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
																</svg>
															</span> Financial Studies
														</Link>
														<Link :href="`/portfolio-companies/${customer.id}/model-studio`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-success/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-mp-success" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
																</svg>
															</span> Financial Model Studio
														</Link>
														<Link
															:href="`/portfolio-companies/${customer.id}/financial-planning`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-gold-dark/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-white" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
																</svg>
															</span> Upload Financial Planning Models
														</Link>
														<!-- ── Data Room ── -->
														<div class="border-t border-mp-border/50 my-1"></div>
														<Link :href="`/portfolio-companies/${customer.id}/data-room`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-teal/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-white" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
																</svg>
															</span> 📁 Data Room
														</Link>
														<Link :href="`/portfolio-companies/${customer.id}/projects`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-teal/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-white" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
																</svg>
															</span> Projects & Tasks
														</Link>
														<!-- ── Surveys ── -->
														<div class="border-t border-mp-border/50 my-1"></div>
														<Link :href="`/portfolio-companies/${customer.id}/surveys`"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-gold-dark/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-white" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
																</svg>
															</span> 📋 Surveys
														</Link>
														<!-- ── Statistica ── -->
														<Link
															:href="safeOrgId ? `/organizations/${safeOrgId}/statistica` : '#'"
															class="flex items-center gap-3 px-4 py-2.5 text-sm text-white hover:bg-mp-page hover:text-white transition-colors">
															<span
																class="w-7 h-7 bg-mp-success/20 rounded-lg flex items-center justify-center flex-shrink-0">
																<svg class="w-3.5 h-3.5 text-mp-success" fill="none"
																	stroke="currentColor" viewBox="0 0 24 24">
																	<path stroke-linecap="round" stroke-linejoin="round"
																		stroke-width="2"
																		d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
																</svg>
															</span> 📊 Statistica
														</Link>
													</div>
												</div>
											</Teleport>
										</div>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<!-- DELETE CONFIRMATION MODAL -->
		<Teleport to="body">
			<div v-if="deleteModal.show" class="fixed inset-0 z-[100] flex items-center justify-center p-4"
				@click.self="deleteModal.show = false">
				<div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
				<div
					class="relative z-10 w-full max-w-md bg-mp-card border border-mp-danger/50 rounded-2xl shadow-2xl p-6">
					<div
						class="flex items-center justify-center w-14 h-14 rounded-full bg-mp-danger/40 border border-mp-danger/50 mx-auto mb-4">
						<svg class="w-7 h-7 text-mp-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
						</svg>
					</div>
					<h3 class="text-lg font-bold text-white text-center mb-1">Delete Customer</h3>
					<p class="text-white text-sm text-center mb-1"> You are about to permanently delete </p>
					<p class="text-mp-danger font-semibold text-center text-base mb-4">{{ deleteModal.company?.name }}
					</p>
					<div
						class="bg-mp-danger/40 border border-mp-danger/40 rounded-xl p-4 mb-5 text-sm text-mp-danger space-y-1.5">
						<p class="font-semibold text-mp-danger mb-2">⚠️ This will also permanently delete:</p>
						<p>• All Sales data & uploads</p>
						<p>• All Expense data & uploads</p>
						<p>• All Profitability mappings & notes</p>
						<p>• All Financial Statements</p>
						<p>• All Financial Planning Models (files included)</p>
					</div>
					<p class="text-white text-xs mb-2"> Type <span class="text-white font-mono font-bold">DELETE</span>
						to confirm </p>
					<input v-model="deleteModal.confirmation" type="text" placeholder="Type DELETE here..."
						class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-4 py-2.5 text-sm mb-4 focus:outline-none focus:border-mp-danger placeholder-gray-600" />
					<div class="flex gap-3">
						<button @click="deleteModal.show = false; deleteModal.confirmation = ''"
							class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
							Cancel </button>
						<button @click="executeDelete"
							:disabled="deleteModal.confirmation !== 'DELETE' || deleteModal.loading" :class="[
								'flex-1 px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors',
								deleteModal.confirmation === 'DELETE' && !deleteModal.loading
									? 'bg-mp-danger hover:bg-mp-danger text-white'
									: 'bg-mp-page text-white cursor-not-allowed'
							]">
							<span v-if="deleteModal.loading">Deleting…</span>
							<span v-else>Yes, Delete Everything</span>
						</button>
					</div>
				</div>
			</div>
		</Teleport>
	</AuthenticatedLayout>
</template>
<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({ companies: Array, orgId: Number })

const page = usePage()
const safeOrgId = computed(() => props.orgId ?? page.props.auth?.user?.organization_id ?? null)
const isAdmin = computed(() => {
	if (page.props.auth?.user?.can_manage_portfolio_companies) return true
	const roles = page.props.auth?.user?.roles ?? []
	const roleList = Array.isArray(roles) ? roles : Object.values(roles)
	return roleList.includes('super-admin') || roleList.includes('admin')
})

const customers = computed(() =>
	(props.companies ?? []).filter(c => c.type !== 'prospect')
)

// ── DROPDOWN ──
const activeDropdown = ref(null)
const buttonPositions = ref({})

function toggleDropdown(id) {
	if (activeDropdown.value === id) {
		activeDropdown.value = null
		return
	}
	const btn = document.querySelector(`[data-dropdown-btn="${id}"]`)
	if (btn) {
		buttonPositions.value[id] = btn.getBoundingClientRect()
	}
	activeDropdown.value = id
}

const dropdownHeightEstimate = 320

function getDropdownStyle(id) {
	const pos = buttonPositions.value[id]
	if (!pos) return {}

	const spaceBelow = window.innerHeight - pos.bottom
	const spaceAbove = pos.top
	const shouldShowAbove = spaceBelow < dropdownHeightEstimate && spaceAbove > dropdownHeightEstimate

	const topValue = shouldShowAbove
		? pos.top - dropdownHeightEstimate - 8
		: pos.bottom + 8

	return {
		position: 'absolute',
		top: `${topValue}px`,
		right: `${window.innerWidth - pos.right}px`,
		transform: 'translateY(0)',
		maxHeight: '80vh',
		overflowY: 'auto',
	}
}

function handleOutsideClick(event) {
	if (event.target.closest('[data-dropdown-menu]')) return
	if (event.target.closest('[data-dropdown-btn]')) return
	activeDropdown.value = null
}

onMounted(() => window.addEventListener('click', handleOutsideClick))
onUnmounted(() => window.removeEventListener('click', handleOutsideClick))

// ── DELETE MODAL ──
const deleteModal = reactive({
	show: false,
	company: null,
	confirmation: '',
	loading: false,
})

function confirmDelete(company) {
	deleteModal.company = company
	deleteModal.confirmation = ''
	deleteModal.loading = false
	deleteModal.show = true
}

function executeDelete() {
	if (deleteModal.confirmation !== 'DELETE' || deleteModal.loading) return
	deleteModal.loading = true

	router.delete(`/portfolio-companies/${deleteModal.company.id}`, {
		onSuccess: () => {
			deleteModal.show = false
			deleteModal.loading = false
		},
		onError: () => {
			deleteModal.loading = false
			alert('Something went wrong. Please try again.')
		},
	})
}

// ── STATUS HELPERS ──
function statusClass(status) {
	const map = {
		on_track: 'bg-mp-success/15 text-mp-success border border-mp-success',
		at_risk: 'bg-mp-danger/15 text-mp-danger border border-mp-danger',
		watch: 'bg-mp-warning/15 text-mp-warning border border-mp-warning',
	}
	return map[status] || 'bg-mp-card-hover text-white'
}

function statusLabel(status) {
	const map = { on_track: 'On Track', at_risk: 'At Risk', watch: 'Watch' }
	return map[status] || status
}
</script>
