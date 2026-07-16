<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ══ HEADER ══ -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <p class="text-xs font-semibold text-white/50 uppercase tracking-widest mb-1">{{ org.name }}</p>
              <h1 class="text-2xl font-bold text-white">Consulting Dashboard</h1>
              <p class="text-white/50 text-sm mt-1">
                {{ summary.total_customers }} customers · {{ summary.total_contracts }} contracts · as of {{ today }}
              </p>
              <!-- Org switcher -->
              <div v-if="allOrgs.length > 1" class="flex items-center gap-2 mt-3">
                <span class="text-xs text-white/40">Viewing:</span>
                <select :value="currentOrgId"
                  @change="e => router.get('/dashboard', { org_id: e.target.value })"
                  class="bg-mp-card-hover border border-mp-border text-white text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:border-mp-teal cursor-pointer">
                  <option v-for="o in allOrgs" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
              </div>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
              <Link href="/portfolio-companies/create"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Customer
              </Link>
              <Link href="/portfolio-companies"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                All Customers
              </Link>
            </div>
          </div>

          <!-- Tabs -->
          <div class="flex gap-1 mt-5 border-b border-mp-border -mb-[1px]">
            <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
              :class="['px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors border-b-2',
                activeTab === tab.key
                  ? 'text-white border-mp-teal bg-mp-card-hover/60'
                  : 'text-white/50 border-transparent hover:text-white hover:bg-mp-card-hover/30']">
              {{ tab.label }}
            </button>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- ════════════════════ TAB: OVERVIEW ════════════════════ -->
        <div v-if="activeTab === 'overview'">

          <!-- KPI Cards -->
          <div class="mb-8">
            <p class="text-xs font-semibold text-white/50 uppercase tracking-widest mb-4">Portfolio Snapshot</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">

              <!-- Total Customers -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-5">
                <p class="text-xs font-semibold text-white/60 uppercase tracking-widest mb-2">Total Customers</p>
                <p class="text-2xl font-bold text-white">{{ summary.total_customers }}</p>
                <p class="text-xs text-white/40 mt-1">In portfolio</p>
              </div>

              <!-- Active Customers — clickable → modal -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-5 cursor-pointer hover:border-mp-teal/60 transition-colors"
                @click="activeCustomersModal = true">
                <p class="text-xs font-semibold text-mp-teal uppercase tracking-widest mb-2">Active Customers</p>
                <p class="text-2xl font-bold text-mp-teal">{{ summary.active_customers }}</p>
                <p class="text-xs text-mp-teal/60 mt-1">Active contract ↗</p>
              </div>

              <!-- At Risk -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-5">
                <p class="text-xs font-semibold text-mp-danger uppercase tracking-widest mb-2">At Risk</p>
                <p class="text-2xl font-bold" :class="summary.customers_at_risk > 0 ? 'text-mp-danger' : 'text-mp-success'">
                  {{ summary.customers_at_risk }}
                </p>
                <p class="text-xs text-white/40 mt-1">Customer status</p>
              </div>

              <!-- Total Contracts -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-5">
                <p class="text-xs font-semibold text-white/60 uppercase tracking-widest mb-2">Total Contracts</p>
                <p class="text-2xl font-bold text-white">{{ summary.total_contracts }}</p>
                <div class="flex gap-1 mt-2 flex-wrap">
                  <span class="text-xs px-1.5 py-0.5 rounded-full bg-mp-teal/15 text-mp-teal border border-mp-teal/40">{{ summary.active_contracts }} active</span>
                  <span class="text-xs px-1.5 py-0.5 rounded-full bg-mp-card-hover text-white/50">{{ summary.finished_contracts }} done</span>
                </div>
              </div>

              <!-- Active Contracts — clickable → modal -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-5 cursor-pointer hover:border-mp-gold/60 transition-colors"
                @click="activeContractsModal = true">
                <p class="text-xs font-semibold text-mp-gold uppercase tracking-widest mb-2">Active Contracts</p>
                <p class="text-2xl font-bold text-mp-gold">{{ summary.active_contracts }}</p>
                <p class="text-xs text-mp-gold/60 mt-1">Click for details ↗</p>
              </div>

              <!-- Expired Contracts -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-5 cursor-pointer hover:border-mp-danger/60 transition-colors"
                @click="expiredContractsModal = true">
                <p class="text-xs font-semibold text-mp-danger uppercase tracking-widest mb-2">Expired Contracts</p>
                <p class="text-2xl font-bold" :class="summary.expired_contracts > 0 ? 'text-mp-danger' : 'text-white'">
                  {{ summary.expired_contracts ?? 0 }}
                </p>
                <p class="text-xs text-mp-danger/60 mt-1">Past end date, not finished ↗</p>
              </div>

              <!-- Active Contract Value -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-5">
                <p class="text-xs font-semibold text-mp-success uppercase tracking-widest mb-2">Active Value</p>
                <p class="text-2xl font-bold text-mp-success">{{ fmtM(summary.active_contract_value) }}</p>
                <p class="text-xs text-white/40 mt-1">Active contracts</p>
              </div>

            </div>
          </div>

          <!-- Row: Mix + Sector -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            <!-- Contracts by Status -->
            <div class="bg-mp-card border border-mp-border rounded-xl p-6">
              <p class="text-xs font-semibold text-white/50 uppercase tracking-widest mb-5">Contracts by Status</p>
              <div class="space-y-3">
                <div v-for="(val, label) in contractStatusBars" :key="label">
                  <div class="flex items-center justify-between text-sm mb-1">
                    <span class="capitalize text-white/80">{{ label }}</span>
                    <span class="font-semibold text-white">{{ val }}</span>
                  </div>
                  <div class="w-full bg-mp-page rounded-full h-2">
                    <div :class="statusBarClass(label)" class="h-2 rounded-full transition-all"
                      :style="{ width: summary.total_contracts > 0 ? (val / summary.total_contracts * 100) + '%' : '0%' }"></div>
                  </div>
                </div>
                <div v-if="summary.total_contracts === 0" class="text-center py-4 text-white/30 text-sm">No contracts yet</div>
              </div>
            </div>

            <!-- Sector Breakdown -->
            <div class="bg-mp-card border border-mp-border rounded-xl p-6">
              <p class="text-xs font-semibold text-white/50 uppercase tracking-widest mb-5">Customers by Sector</p>
              <div class="space-y-3">
                <div v-for="item in sectorBreakdown.slice(0, 6)" :key="item.sector">
                  <div class="flex items-center justify-between text-sm mb-1">
                    <span class="text-white/80 truncate">{{ item.sector }}</span>
                    <span class="font-semibold text-white ml-2">{{ item.count }}</span>
                  </div>
                  <div class="w-full bg-mp-page rounded-full h-2">
                    <div class="bg-mp-teal h-2 rounded-full transition-all"
                      :style="{ width: (item.count / summary.total_customers * 100) + '%' }"></div>
                  </div>
                </div>
                <div v-if="sectorBreakdown.length === 0" class="text-center py-4 text-white/30 text-sm">No customers yet</div>
              </div>
            </div>
          </div>

          <!-- Recent Contracts -->
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between">
              <p class="text-xs font-semibold text-white/50 uppercase tracking-widest">Recent Contracts</p>
              <button @click="activeTab = 'contracts'" class="text-xs text-mp-teal hover:underline">View all →</button>
            </div>
            <div v-if="recentContracts.length === 0" class="px-6 py-8 text-center text-white/30 text-sm">
              No contracts yet. <Link href="/portfolio-companies" class="text-mp-teal hover:underline">Add one from a customer's page.</Link>
            </div>
            <table v-else class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white/40 uppercase tracking-widest px-6 py-3">Contract</th>
                  <th class="text-left text-xs font-semibold text-white/40 uppercase tracking-widest px-4 py-3">Customer</th>
                  <th class="text-right text-xs font-semibold text-white/40 uppercase tracking-widest px-4 py-3">Value</th>
                  <th class="text-center text-xs font-semibold text-white/40 uppercase tracking-widest px-4 py-3">Status</th>
                  <th class="text-center text-xs font-semibold text-white/40 uppercase tracking-widest px-4 py-3">Services</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-mp-border/50">
                <tr v-for="c in recentContracts.slice(0, 5)" :key="c.id" class="hover:bg-mp-card-hover/30 transition-colors">
                  <td class="px-6 py-3">
                    <p class="font-medium text-white">{{ c.name }}</p>
                    <p v-if="c.code" class="text-xs text-white/40">{{ c.code }}</p>
                  </td>
                  <td class="px-4 py-3">
                    <Link :href="`/portfolio-companies/${c.customer_id}/contracts`"
                      class="text-white/70 hover:text-white transition-colors text-sm">
                      {{ c.customer_name }}
                    </Link>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <span class="font-semibold text-white">{{ fmtAmt(c.amount) }}</span>
                    <span class="text-xs text-white/40 ml-1">{{ c.currency }}</span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <span :class="contractStatusBadge(c.status)">{{ contractStatusLabel(c.status) }}</span>
                  </td>
                  <td class="px-4 py-3 text-center text-white/60">{{ c.services_count }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ════════════════════ TAB: CONTRACTS ════════════════════ -->
        <div v-if="activeTab === 'contracts'">
          <div class="flex items-center justify-between mb-5">
            <p class="text-xs font-semibold text-white/50 uppercase tracking-widest">All Contracts</p>
            <div class="flex gap-2">
              <button v-for="f in ['all', 'running', 'finished', 'draft']" :key="f"
                @click="contractFilter = f"
                :class="['text-xs px-3 py-1.5 rounded-lg font-medium transition-colors',
                  contractFilter === f
                    ? 'bg-mp-teal text-white'
                    : 'bg-mp-card-hover text-white/50 hover:text-white']">
                {{ f.charAt(0).toUpperCase() + f.slice(1) }}
              </button>
            </div>
          </div>

          <div v-if="filteredContracts.length === 0" class="bg-mp-card border border-mp-border border-dashed rounded-xl p-16 text-center text-white/30">
            No contracts found.
          </div>

          <div v-else class="space-y-3">
            <div v-for="contract in filteredContracts" :key="contract.id"
              class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">

              <div class="flex items-center gap-4 px-6 py-4 cursor-pointer hover:bg-mp-card-hover/20 transition-colors"
                @click="toggleContractExpand(contract.id)">
                <svg :class="['w-4 h-4 text-white/30 transition-transform flex-shrink-0', contractExpanded.has(contract.id) ? 'rotate-90' : '']"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-white text-sm">{{ contract.name }}</p>
                  <p class="text-xs text-white/40 mt-0.5">
                    <Link :href="`/portfolio-companies/${contract.customer_id}/contracts`"
                      @click.stop class="hover:text-mp-teal transition-colors">
                      {{ contract.customer_name }}
                    </Link>
                    <span v-if="contract.code"> · {{ contract.code }}</span>
                  </p>
                </div>
                <div class="hidden sm:block text-xs text-white/40">
                  {{ fmtDate(contract.start_date) }} → {{ fmtDate(contract.end_date) }}
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="font-bold text-white text-sm">{{ fmtAmt(contract.amount) }}</p>
                  <p class="text-xs text-white/40">{{ contract.currency }}</p>
                </div>
                <span :class="contractStatusBadge(contract.status)" class="flex-shrink-0">{{ contractStatusLabel(contract.status) }}</span>
                <span class="text-xs text-white/40 flex-shrink-0">{{ contract.services_count }} svc</span>
              </div>

              <!-- Expanded services -->
              <div v-if="contractExpanded.has(contract.id)" class="border-t border-mp-border bg-mp-page/40">
                <div v-if="!contract.services.length" class="px-8 py-3 text-xs text-white/30">No services.</div>
                <table v-else class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-mp-border/40">
                      <th class="text-left text-xs font-semibold text-white/30 uppercase px-8 py-2">#</th>
                      <th class="text-left text-xs font-semibold text-white/30 uppercase px-4 py-2">Service</th>
                      <th class="text-right text-xs font-semibold text-white/30 uppercase px-8 py-2">Amount</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-mp-border/20">
                    <tr v-for="(svc, i) in contract.services" :key="i" class="hover:bg-mp-card-hover/10">
                      <td class="px-8 py-2.5 text-white/30 text-xs">{{ i + 1 }}</td>
                      <td class="px-4 py-2.5 text-white/80">{{ svc.name }}</td>
                      <td class="px-8 py-2.5 text-right font-semibold text-white">
                        {{ fmtAmt(svc.amount) }}
                        <span class="text-xs text-white/40 ml-1">{{ contract.currency }}</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- ════════════════════ TAB: CUSTOMERS ════════════════════ -->
        <div v-if="activeTab === 'customers'">
          <div class="flex items-center justify-between mb-5">
            <p class="text-xs font-semibold text-white/50 uppercase tracking-widest">All Customers</p>
            <div class="flex gap-2">
              <button v-for="f in ['all', 'active', 'at_risk']" :key="f"
                @click="customerFilter = f"
                :class="['text-xs px-3 py-1.5 rounded-lg font-medium transition-colors capitalize',
                  customerFilter === f
                    ? 'bg-mp-teal text-white'
                    : 'bg-mp-card-hover text-white/50 hover:text-white']">
                {{ f === 'at_risk' ? 'At Risk' : f.charAt(0).toUpperCase() + f.slice(1) }}
              </button>
            </div>
          </div>

          <div v-if="filteredCustomers.length === 0" class="bg-mp-card border border-mp-border border-dashed rounded-xl p-16 text-center text-white/30">
            No customers found.
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <div v-for="c in filteredCustomers" :key="c.id"
              class="bg-mp-card border border-mp-border rounded-xl p-5 hover:border-mp-teal/50 transition-colors">
              <div class="flex items-start justify-between gap-3 mb-4">
                <div class="flex items-center gap-3 min-w-0">
                  <div class="w-10 h-10 rounded-lg bg-mp-teal flex items-center justify-center text-sm font-bold flex-shrink-0">
                    {{ c.name.charAt(0).toUpperCase() }}
                  </div>
                  <div class="min-w-0">
                    <p class="font-semibold text-white text-sm truncate">{{ c.name }}</p>
                    <p class="text-xs text-white/40 mt-0.5">{{ c.sector }}</p>
                  </div>
                </div>
                <span :class="customerStatusBadge(c.status)" class="text-xs font-semibold px-2 py-0.5 rounded-full uppercase tracking-wide flex-shrink-0">
                  {{ c.status.replace('_', ' ') }}
                </span>
              </div>

              <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-mp-page rounded-lg p-2.5 text-center">
                  <p class="text-lg font-bold text-white">{{ c.total_contracts }}</p>
                  <p class="text-xs text-white/40">Contracts</p>
                </div>
                <div class="bg-mp-teal/10 rounded-lg p-2.5 text-center">
                  <p class="text-lg font-bold text-mp-teal">{{ c.running_count }}</p>
                  <p class="text-xs text-mp-teal/60">Running</p>
                </div>
                <div class="bg-mp-page rounded-lg p-2.5 text-center">
                  <p class="text-sm font-bold text-white">{{ fmtM(c.total_value) }}</p>
                  <p class="text-xs text-white/40">Value</p>
                </div>
              </div>

              <Link :href="`/portfolio-companies/${c.id}/contracts`"
                class="flex items-center justify-center gap-2 w-full bg-mp-card-hover hover:bg-mp-teal text-white text-xs font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                View Contracts
              </Link>
            </div>
          </div>
        </div>

        <!-- ════════════════════ TAB: ACTIVITY ════════════════════ -->
        <div v-if="activeTab === 'activity'">
          <p class="text-xs font-semibold text-white/50 uppercase tracking-widest mb-5">Recent Activity</p>
          <div v-if="recentActivity.length === 0" class="bg-mp-card border border-mp-border border-dashed rounded-xl p-16 text-center text-white/30 text-sm">
            No activity yet.
          </div>
          <div v-else class="bg-mp-card border border-mp-border rounded-xl divide-y divide-mp-border/50">
            <div v-for="(act, idx) in recentActivity" :key="idx" class="px-6 py-4 flex items-start gap-4 hover:bg-mp-card-hover/20 transition-colors">
              <div class="w-8 h-8 rounded-lg bg-mp-gold/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4 text-mp-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm text-white font-medium">{{ act.label }}</p>
                <p class="text-xs text-white/40 mt-0.5">{{ act.sub }}</p>
              </div>
              <p class="text-xs text-white/30 flex-shrink-0 mt-0.5">{{ act.date }}</p>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ══════════════════ ACTIVE CUSTOMERS MODAL ══════════════════ -->
    <Teleport to="body">
      <div v-if="activeCustomersModal"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        @click.self="activeCustomersModal = false">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-2xl bg-mp-card border border-mp-border rounded-2xl shadow-2xl flex flex-col max-h-[80vh]">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border flex-shrink-0">
            <div>
              <h3 class="text-lg font-bold text-white">Active Customers</h3>
              <p class="text-xs text-white/40 mt-0.5">Customers with at least one running contract</p>
            </div>
            <button @click="activeCustomersModal = false"
              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-white/60 hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <!-- Body -->
          <div class="overflow-y-auto flex-1">
            <div v-if="activeCustomers.length === 0" class="px-6 py-12 text-center text-white/30 text-sm">No active customers.</div>
            <table v-else class="w-full text-sm">
              <thead class="sticky top-0 bg-mp-card z-10">
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white/40 uppercase tracking-widest px-6 py-3">Customer</th>
                  <th class="text-left text-xs font-semibold text-white/40 uppercase tracking-widest px-4 py-3">Sector</th>
                  <th class="text-center text-xs font-semibold text-white/40 uppercase tracking-widest px-4 py-3">Running</th>
                  <th class="text-right text-xs font-semibold text-white/40 uppercase tracking-widest px-6 py-3">Value</th>
                  <th class="px-4 py-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-mp-border/40">
                <tr v-for="c in activeCustomers" :key="c.id" class="hover:bg-mp-card-hover/20">
                  <td class="px-6 py-3">
                    <div class="flex items-center gap-2">
                      <div class="w-7 h-7 rounded-lg bg-mp-teal flex items-center justify-center text-xs font-bold flex-shrink-0">
                        {{ c.name.charAt(0).toUpperCase() }}
                      </div>
                      <span class="font-medium text-white">{{ c.name }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-white/60 text-xs">{{ c.sector }}</td>
                  <td class="px-4 py-3 text-center">
                    <span class="bg-mp-teal/15 text-mp-teal border border-mp-teal/40 text-xs font-semibold px-2 py-0.5 rounded-full">
                      {{ c.running_count }}
                    </span>
                  </td>
                  <td class="px-6 py-3 text-right font-bold text-white">
                    {{ fmtAmt(c.running_value) }}
                    <span class="text-xs text-white/40 ml-1">{{ c.running_currency }}</span>
                  </td>
                  <td class="px-4 py-3">
                    <Link :href="`/portfolio-companies/${c.id}/contracts`"
                      @click="activeCustomersModal = false"
                      class="text-xs text-mp-teal hover:underline whitespace-nowrap">
                      View →
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══════════════════ ACTIVE CONTRACTS MODAL ══════════════════ -->
    <Teleport to="body">
      <div v-if="activeContractsModal"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        @click.self="activeContractsModal = false">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-3xl bg-mp-card border border-mp-border rounded-2xl shadow-2xl flex flex-col max-h-[85vh]">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border flex-shrink-0">
            <div>
              <h3 class="text-lg font-bold text-white">Active Contracts</h3>
              <p class="text-xs text-white/40 mt-0.5">All contracts with status: active</p>
            </div>
            <button @click="activeContractsModal = false"
              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-white/60 hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <!-- Body -->
          <div class="overflow-y-auto flex-1 space-y-3 p-4">
            <div v-if="activeContracts.length === 0" class="py-12 text-center text-white/30 text-sm">No active contracts.</div>

            <div v-for="contract in activeContracts" :key="contract.id"
              class="bg-mp-page/60 border border-mp-border/60 rounded-xl overflow-hidden">
              <!-- Contract summary row -->
              <div class="flex items-center gap-4 px-5 py-3 cursor-pointer hover:bg-mp-card-hover/20"
                @click="toggleModalExpand(contract.id)">
                <svg :class="['w-4 h-4 text-white/30 transition-transform', modalExpanded.has(contract.id) ? 'rotate-90' : '']"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-white text-sm">{{ contract.name }}</p>
                  <p class="text-xs text-white/40 mt-0.5">
                    {{ contract.customer_name }}
                    <span v-if="contract.code"> · {{ contract.code }}</span>
                  </p>
                </div>
                <div class="text-xs text-white/40 hidden sm:block">
                  {{ fmtDate(contract.start_date) }} → {{ fmtDate(contract.end_date) }}
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="font-bold text-mp-gold text-sm">{{ fmtAmt(contract.amount) }}</p>
                  <p class="text-xs text-white/40">{{ contract.currency }}</p>
                </div>
                <span class="text-xs text-white/40 flex-shrink-0">{{ contract.services.length }} svc</span>
              </div>

              <!-- Services -->
              <div v-if="modalExpanded.has(contract.id)" class="border-t border-mp-border/40 bg-mp-page/30">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-mp-border/30">
                      <th class="text-left text-xs font-semibold text-white/30 uppercase px-7 py-2">#</th>
                      <th class="text-left text-xs font-semibold text-white/30 uppercase px-4 py-2">Service</th>
                      <th class="text-right text-xs font-semibold text-white/30 uppercase px-4 py-2">Execution</th>
                      <th class="text-right text-xs font-semibold text-white/30 uppercase px-7 py-2">Amount</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-mp-border/20">
                    <tr v-for="(svc, i) in contract.services" :key="i" class="hover:bg-mp-card-hover/10">
                      <td class="px-7 py-2 text-white/30 text-xs">{{ i + 1 }}</td>
                      <td class="px-4 py-2">
                        <p class="text-white/80">{{ svc.name }}</p>
                        <p v-if="svc.start_date || svc.end_date" class="text-xs text-white/30 mt-0.5">
                          {{ fmtDate(svc.start_date) }} → {{ fmtDate(svc.end_date) }}
                        </p>
                        <p v-if="svc.execution_total_pct > 0" class="text-xs text-mp-teal mt-0.5">{{ svc.execution_total_pct }}% executed</p>
                      </td>
                      <td class="px-4 py-2 text-right text-xs text-white/50">
                        <span v-if="svc.execution_total_pct > 0">{{ svc.execution_total_pct }}%</span>
                        <span v-else>—</span>
                      </td>
                      <td class="px-7 py-2 text-right font-semibold text-white">
                        {{ fmtAmt(svc.amount) }}
                        <span class="text-xs text-white/40 ml-1">{{ contract.currency }}</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
                <!-- Edit link -->
                <div class="px-5 py-2.5 border-t border-mp-border/30 flex justify-end">
                  <Link :href="`/portfolio-companies/${contract.customer_id}/contracts/${contract.id}/edit`"
                    @click="activeContractsModal = false"
                    class="text-xs text-mp-teal hover:underline">
                    Edit contract →
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══════════════════ EXPIRED CONTRACTS MODAL ══════════════════ -->
    <Teleport to="body">
      <div v-if="expiredContractsModal"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        @click.self="expiredContractsModal = false">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-2xl bg-mp-card border border-mp-danger/40 rounded-2xl shadow-2xl flex flex-col max-h-[85vh]">
          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border flex-shrink-0">
            <div>
              <h3 class="text-lg font-bold text-white">Expired Contracts</h3>
              <p class="text-xs text-white/40 mt-0.5">End date passed — mark as finished when complete</p>
            </div>
            <button @click="expiredContractsModal = false"
              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-white/60 hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="overflow-y-auto flex-1 p-4 space-y-2">
            <div v-if="!expiredContracts?.length" class="py-12 text-center text-white/30 text-sm">No expired contracts.</div>
            <div v-for="c in expiredContracts" :key="c.id"
              class="flex items-center gap-4 px-4 py-3 bg-mp-page/60 border border-mp-border/60 rounded-xl">
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-white text-sm">{{ c.name }}</p>
                <p class="text-xs text-white/40">{{ c.customer_name }} · ended {{ fmtDate(c.end_date) }}</p>
              </div>
              <span class="text-xs text-mp-danger uppercase">{{ c.status === 'running' ? 'Active' : 'Draft' }}</span>
              <Link :href="`/portfolio-companies/${c.customer_id}/contracts`"
                @click="expiredContractsModal = false"
                class="text-xs text-mp-teal hover:underline flex-shrink-0">
                View →
              </Link>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  summary:         Object,
  activeCustomers: Array,
  activeContracts: Array,
  expiredContracts: { type: Array, default: () => [] },
  recentContracts: Array,
  customerList:    Array,
  allContracts:    Array,
  sectorBreakdown: Array,
  recentActivity:  Array,
  org:             Object,
  allOrgs:         Array,
  currentOrgId:    Number,
})

const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })

const tabs = [
  { key: 'overview',   label: '📊 Overview' },
  { key: 'contracts',  label: '📄 Contracts' },
  { key: 'customers',  label: '👥 Customers' },
  { key: 'activity',   label: '📋 Activity' },
]
const activeTab = ref('overview')

// Modals
const activeCustomersModal = ref(false)
const activeContractsModal = ref(false)
const expiredContractsModal = ref(false)

// Expand state for contracts tab
const contractExpanded = ref(new Set())
const modalExpanded    = ref(new Set())

function toggleContractExpand(id) {
  const s = new Set(contractExpanded.value)
  s.has(id) ? s.delete(id) : s.add(id)
  contractExpanded.value = s
}

function toggleModalExpand(id) {
  const s = new Set(modalExpanded.value)
  s.has(id) ? s.delete(id) : s.add(id)
  modalExpanded.value = s
}

// Filters
const contractFilter = ref('all')
const customerFilter = ref('all')

const filteredContracts = computed(() => {
  if (contractFilter.value === 'all') return props.allContracts
  return props.allContracts.filter(c => c.status === contractFilter.value)
})

const filteredCustomers = computed(() => {
  const activeIds = new Set(props.activeContracts.map(c => c.customer_id))
  if (customerFilter.value === 'active')   return props.customerList.filter(c => activeIds.has(c.id))
  if (customerFilter.value === 'at_risk')  return props.customerList.filter(c => c.status === 'at_risk')
  return props.customerList
})

// Status bar data for overview
const contractStatusBars = computed(() => ({
  running:  props.summary.active_contracts,
  finished: props.summary.finished_contracts,
  draft:    props.summary.draft_contracts,
}))

function statusBarClass(label) {
  const map = { running: 'bg-mp-teal', finished: 'bg-mp-success', draft: 'bg-mp-gold' }
  return map[label] || 'bg-mp-card-hover'
}

function contractStatusBadge(status) {
  const map = {
    running:  'bg-mp-teal/15 text-mp-teal border border-mp-teal/40 text-xs font-semibold px-2.5 py-0.5 rounded-full uppercase',
    finished: 'bg-mp-success/15 text-mp-success border border-mp-success/40 text-xs font-semibold px-2.5 py-0.5 rounded-full uppercase',
    draft:    'bg-mp-gold/15 text-mp-gold border border-mp-gold/40 text-xs font-semibold px-2.5 py-0.5 rounded-full uppercase',
  }
  return map[status] || 'bg-mp-card-hover text-white text-xs font-semibold px-2.5 py-0.5 rounded-full uppercase'
}

function contractStatusLabel(status) {
  const map = { running: 'Active', finished: 'Finished', draft: 'Draft' }
  return map[status] || status
}

function customerStatusBadge(status) {
  const map = {
    on_track: 'bg-mp-success/15 text-mp-success border border-mp-success',
    at_risk:  'bg-mp-danger/15 text-mp-danger border border-mp-danger',
    watch:    'bg-mp-warning/15 text-mp-warning border border-mp-warning',
  }
  return map[status] || 'bg-mp-card-hover text-white'
}

// Format helpers
function fmtM(v) {
  if (!v) return '—'
  const n = Number(v)
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M'
  if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K'
  return n.toFixed(0)
}

function fmtAmt(v) {
  if (v === null || v === undefined) return '—'
  return Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
