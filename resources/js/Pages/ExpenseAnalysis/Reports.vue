<template>
  <Head title="Expense Reports" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-mp-text-secondary">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <Link :href="`/portfolio-companies/${company.id}`"
                class="flex items-center gap-2 text-sm text-mp-muted hover:text-mp-text-secondary transition-colors mb-2 w-fit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to {{ company.name }}
              </Link>
              <h1 class="text-2xl font-bold text-mp-text-secondary">Expense Reports</h1>
              <p class="text-mp-muted text-sm mt-1">{{ company.name }}</p>
            </div>
            <div class="flex gap-2">
              <a :href="`/companies/${company.id}/expenses`"
                class="px-4 py-2 rounded-lg text-sm text-mp-muted hover:text-mp-text-secondary hover:bg-mp-card-hover transition-colors">Dashboard</a>
              <a :href="`/companies/${company.id}/expenses/upload`"
                class="px-4 py-2 rounded-lg text-sm text-mp-muted hover:text-mp-text-secondary hover:bg-mp-card-hover transition-colors">Upload</a>
              <span class="px-4 py-2 rounded-lg text-sm bg-mp-teal text-mp-text-secondary font-medium">Reports</span>
              <a :href="`/companies/${company.id}/expenses/breakeven`"
                class="px-4 py-2 rounded-lg text-sm bg-mp-gold hover:bg-mp-gold-dark text-mp-text-secondary font-medium transition-colors">
                📊 Breakeven
              </a>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

          <!-- ── LEFT SIDEBAR: CONTROLS ── -->
          <div class="lg:col-span-1 space-y-4">

            <!-- Report Type -->
            <div class="bg-mp-card rounded-xl border border-mp-border p-5">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Report Type</p>
              <div class="space-y-2">
                <button v-for="rt in reportTypes" :key="rt.value"
                  @click="selectReportType(rt.value)"
                  :class="[
                    'w-full text-left px-4 py-3 rounded-lg text-sm transition-colors',
                    form.report_type === rt.value
                      ? 'bg-mp-teal text-mp-text-secondary font-medium'
                      : 'bg-mp-card-hover text-mp-text hover:bg-mp-page'
                  ]">
                  {{ rt.label }}
                </button>
              </div>
            </div>

            <!-- Date Range (all report types except Period Comparison) -->
            <div v-if="form.report_type !== 'period_comparison'" class="bg-mp-card rounded-xl border border-mp-border p-5">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Date Range</p>
              <div class="space-y-3">
                <div>
                  <label class="block text-xs text-mp-muted mb-1">From</label>
                  <input type="date" v-model="form.date_from" :min="minDate" :max="maxDate"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
                </div>
                <div>
                  <label class="block text-xs text-mp-muted mb-1">To</label>
                  <input type="date" v-model="form.date_to" :min="minDate" :max="maxDate"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
                </div>
              </div>
            </div>

            <!-- Number of Periods (Period Comparison only) -->
            <div v-if="form.report_type === 'period_comparison'" class="bg-mp-card rounded-xl border border-mp-border p-5">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Number of Periods</p>
              <div class="grid grid-cols-4 gap-2">
                <button v-for="n in [2, 3, 4, 5]" :key="n" @click="setPeriodCount(n)"
                  :class="[
                    'py-2 rounded-lg text-sm font-medium transition-colors',
                    periods.length === n
                      ? 'bg-mp-teal text-mp-text-secondary'
                      : 'bg-mp-card-hover text-mp-text hover:bg-mp-page'
                  ]">
                  {{ n }}
                </button>
              </div>
            </div>

            <!-- Periods (Period Comparison only) -->
            <div v-if="form.report_type === 'period_comparison'" class="space-y-4">
              <div v-for="(p, idx) in periods" :key="idx" class="bg-mp-card rounded-xl border border-mp-border p-5">
                <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Period {{ idx + 1 }}</p>
                <div class="space-y-3">
                  <div>
                    <label class="block text-xs text-mp-muted mb-1">From</label>
                    <input type="date" v-model="p.from" :min="minDate" :max="maxDate"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
                  </div>
                  <div>
                    <label class="block text-xs text-mp-muted mb-1">To</label>
                    <input type="date" v-model="p.to" :min="minDate" :max="maxDate"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
                  </div>
                </div>
              </div>
            </div>

            <!-- Compare By (Period Comparison) -->
            <div v-if="form.report_type === 'period_comparison'" class="bg-mp-card rounded-xl border border-mp-border p-5">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Compare By</p>
              <select v-model="form.compare_by"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                <option value="category">Expense Category</option>
                <option value="sub_category">Sub Category</option>
                <option value="item">Expense Item</option>
              </select>
            </div>

            <!-- Period Group (Trend only) -->
            <div v-if="form.report_type === 'trend'" class="bg-mp-card rounded-xl border border-mp-border p-5">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Period Group</p>
              <select v-model="form.period"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly (Q1-Q4)</option>
                <option value="semi_annually">Semi-Annually (H1-H2)</option>
                <option value="annually">Annually</option>
              </select>
            </div>

            <!-- Category Filter (item_breakdown, trend, min_avg_max) -->
            <div v-if="showCategoryFilter" class="bg-mp-card rounded-xl border border-mp-border p-5">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Filter by Category</p>
              <select v-model="form.category"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                <option value="">All Categories</option>
                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
              </select>
            </div>

            <!-- Run Button -->
            <button @click="runReport" :disabled="loading"
              class="w-full bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 text-mp-text-secondary text-sm font-medium py-3 rounded-lg transition-colors flex items-center justify-center gap-2">
              <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ loading ? 'Running...' : 'Run Report' }}
            </button>

          </div>

          <!-- ── RIGHT: RESULTS ── -->
          <div class="lg:col-span-3">

            <div v-if="!result" class="bg-mp-card rounded-xl border border-mp-border border-dashed p-16 text-center">
              <svg class="w-10 h-10 text-mp-muted mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              <p class="text-mp-muted">Select a report type and click Run Report</p>
            </div>

            <div v-else>

              <!-- Result Header -->
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h2 class="text-lg font-bold text-mp-text-secondary">{{ currentReportLabel }}</h2>
                  <p class="text-xs text-mp-muted mt-0.5">
                    {{ form.date_from }} → {{ form.date_to }} ·
                    Total Expense: <span class="text-mp-text-secondary font-medium">{{ fmt(totalExpense) }}</span>
                    <template v-if="totalRevenue > 0"> · Revenue: <span class="text-mp-text-secondary font-medium">{{ fmt(totalRevenue) }}</span></template>
                  </p>
                </div>
                <button @click="exportReport"
                  class="flex items-center gap-2 bg-mp-success hover:bg-mp-success text-mp-text-secondary text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                  Export Excel
                </button>
              </div>

              <!-- ── Category Breakdown ── -->
              <div v-if="form.report_type === 'category_breakdown'" class="space-y-4">
                <div class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="bg-mp-teal-subtle/40 border-b border-mp-border">
                        <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Category</th>
                        <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Total Amount</th>
                        <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">% of Expense</th>
                        <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">% of Revenue</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                      <tr v-for="(row, i) in result" :key="i"
                        :class="i % 2 === 0 ? 'bg-mp-card' : 'bg-mp-card-hover/40'"
                        class="hover:bg-mp-teal-subtle/20 transition-colors">
                        <td class="px-5 py-3 font-medium text-mp-text-secondary">{{ row.category }}</td>
                        <td class="px-5 py-3 text-right text-mp-text">{{ fmt(row.total) }}</td>
                        <td class="px-5 py-3 text-right">
                          <div class="flex items-center justify-end gap-2">
                            <div class="w-16 bg-mp-page rounded-full h-1.5">
                              <div class="bg-mp-teal h-1.5 rounded-full" :style="`width:${Math.min(row.pct_of_expense,100)}%`"></div>
                            </div>
                            <span class="text-mp-text w-12 text-right">{{ row.pct_of_expense }}%</span>
                          </div>
                        </td>
                        <td class="px-5 py-3 text-right text-mp-text">{{ row.pct_of_revenue }}%</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <!-- Bar chart -->
                <div class="bg-mp-card rounded-xl border border-mp-border p-5">
                  <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Category Breakdown Chart</p>
                  <div class="space-y-3">
                    <div v-for="row in result" :key="row.category" class="flex items-center gap-3">
                      <div class="w-36 text-xs text-mp-muted truncate text-right">{{ row.category }}</div>
                      <div class="flex-1 bg-mp-card-hover rounded-full h-5 relative">
                        <div class="bg-mp-teal h-5 rounded-full flex items-center pl-2"
                          :style="`width:${Math.max(row.pct_of_expense, 2)}%`">
                          <span class="text-xs text-mp-text-secondary font-medium whitespace-nowrap">{{ fmt(row.total) }}</span>
                        </div>
                      </div>
                      <div class="w-12 text-xs text-mp-muted text-right">{{ row.pct_of_expense }}%</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ── Sub-Category Breakdown ── -->
              <div v-if="form.report_type === 'subcategory_breakdown'" class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="bg-mp-teal-subtle/40 border-b border-mp-border">
                      <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Category</th>
                      <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Sub Category</th>
                      <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Total</th>
                      <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">% Expense</th>
                      <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">% Revenue</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-800">
                    <tr v-for="(row, i) in result" :key="i"
                      :class="i % 2 === 0 ? 'bg-mp-card' : 'bg-mp-card-hover/40'"
                      class="hover:bg-mp-teal-subtle/20 transition-colors">
                      <td class="px-5 py-3 text-mp-text">{{ row.category }}</td>
                      <td class="px-5 py-3 font-medium text-mp-text-secondary">{{ row.sub_category }}</td>
                      <td class="px-5 py-3 text-right text-mp-text">{{ fmt(row.total) }}</td>
                      <td class="px-5 py-3 text-right text-mp-text">{{ row.pct_of_expense }}%</td>
                      <td class="px-5 py-3 text-right text-mp-text">{{ row.pct_of_revenue }}%</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- ── Item Breakdown ── -->
              <div v-if="form.report_type === 'item_breakdown'" class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="bg-mp-teal-subtle/40 border-b border-mp-border">
                      <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Category</th>
                      <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Expense Item</th>
                      <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Total</th>
                      <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">% Expense</th>
                      <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">% Revenue</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-800">
                    <tr v-for="(row, i) in result" :key="i"
                      :class="i % 2 === 0 ? 'bg-mp-card' : 'bg-mp-card-hover/40'"
                      class="hover:bg-mp-teal-subtle/20 transition-colors">
                      <td class="px-5 py-3 text-mp-text text-xs">{{ row.category }}</td>
                      <td class="px-5 py-3 font-medium text-mp-text-secondary">{{ row.item }}</td>
                      <td class="px-5 py-3 text-right text-mp-text">{{ fmt(row.total) }}</td>
                      <td class="px-5 py-3 text-right text-mp-text">{{ row.pct_of_expense }}%</td>
                      <td class="px-5 py-3 text-right text-mp-text">{{ row.pct_of_revenue }}%</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- ══════════════════════════════════════════════════════════
                   TREND REPORT — Fixed: Total at END, GR% per period
                   Same structure as Sales two_factors_trend
              ═══════════════════════════════════════════════════════════ -->
              <div v-if="form.report_type === 'trend'" class="bg-mp-card rounded-xl border border-mp-border overflow-x-auto">
                <table class="text-sm border-collapse" style="min-width: max-content; width: 100%;">
                  <thead>
                    <tr class="bg-mp-teal-subtle/40 border-b border-mp-border">
                      <!-- Col 1: Category / Item — sticky -->
                      <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3 sticky left-0 bg-mp-teal-subtle/60 z-10 min-w-[220px]">
                        Category / Item
                      </th>
                      <!-- Period columns — value + GR% sub-columns -->
                      <th v-for="p in result.periods" :key="p"
                        class="text-center text-xs font-semibold text-white uppercase tracking-widest px-3 py-3 whitespace-nowrap min-w-[130px]">
                        {{ formatPeriod(p) }}
                      </th>
                      <!-- Total at END — same as Sales two_factors_trend -->
                      <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-4 py-3 min-w-[100px]">
                        Total
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="cat in result.rows" :key="cat.label">

                      <!-- ── CATEGORY ROW (collapsible) ── -->
                      <tr class="border-b border-mp-border bg-mp-teal-subtle/30 cursor-pointer hover:bg-mp-teal-subtle/50 transition-colors"
                        @click="toggleCat(cat.label)">
                        <td class="px-5 py-2.5 sticky left-0 bg-mp-teal-subtle/30 z-10">
                          <div class="flex items-center gap-2">
                            <svg class="w-3 h-3 text-white transition-transform flex-shrink-0"
                              :class="expandedCats.has(cat.label) ? 'rotate-90' : ''"
                              fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <span class="text-mp-text-secondary font-semibold text-xs">{{ cat.label }}</span>
                          </div>
                        </td>
                        <!-- Period cells for category totals -->
                        <td v-for="(p, pi) in result.periods" :key="p" class="px-3 py-2.5 text-center">
                          <div class="text-mp-text-secondary text-xs font-semibold">{{ fmt(cat.cells[p]?.value || 0) }}</div>
                          <div class="text-xs mt-0.5"
                            :class="(cat.cells[p]?.gr ?? 0) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                            <template v-if="pi > 0">
                              [GR {{ cat.cells[p]?.gr >= 0 ? '+' : '' }}{{ cat.cells[p]?.gr ?? 0 }}%]
                            </template>
                            <template v-else><span class="text-mp-muted">—</span></template>
                          </div>
                        </td>
                        <!-- Total at end -->
                        <td class="px-4 py-2.5 text-right text-mp-text-secondary font-bold text-xs">{{ fmt(cat.total) }}</td>
                      </tr>

                      <!-- ── ITEM ROWS (expanded) ── -->
                      <template v-if="expandedCats.has(cat.label)">
                        <tr v-for="item in cat.children" :key="item.label"
                          class="border-b border-mp-border hover:bg-mp-card-hover/30 transition-colors">
                          <td class="px-5 py-2 pl-10 sticky left-0 bg-mp-card z-10 text-mp-text text-xs">
                            {{ item.label }}
                          </td>
                          <!-- Period cells for item -->
                          <td v-for="p in result.periods" :key="p" class="px-3 py-2 text-center">
                            <div class="text-mp-text text-xs">{{ item.cells[p]?.value > 0 ? fmt(item.cells[p].value) : '—' }}</div>
                            <div v-if="item.cells[p]?.value > 0 && item.cells[p]?.gr !== 0" class="text-xs mt-0.5"
                              :class="item.cells[p].gr >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                              [{{ item.cells[p].gr >= 0 ? '+' : '' }}{{ item.cells[p].gr }}%]
                            </div>
                          </td>
                          <!-- Total at end -->
                          <td class="px-4 py-2 text-right text-mp-text text-xs font-semibold">{{ fmt(item.total) }}</td>
                        </tr>
                      </template>

                    </template>
                  </tbody>
                </table>
              </div>

              <!-- ══════════════════════════════════════════════════════════
                   PERIOD COMPARISON — Same as Sales reports period_comparison
              ═══════════════════════════════════════════════════════════ -->
              <div v-if="form.report_type === 'period_comparison'">
                <div class="bg-mp-card rounded-xl border border-mp-border overflow-x-auto">
                  <table class="w-full text-sm" style="min-width: max-content">
                    <thead>
                      <tr class="bg-mp-teal-subtle/40 border-b border-mp-border">
                        <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3 sticky left-0 bg-mp-teal-subtle/60 z-10">
                          {{ compareByLabel }}
                        </th>
                        <th v-for="(p, pi) in result.periods" :key="pi"
                          class="text-right text-xs font-semibold text-white uppercase px-6 py-3 min-w-[150px]">
                          Period {{ pi + 1 }}<br>
                          <span class="font-normal text-mp-muted text-xs">{{ p.from }} → {{ p.to }}</span>
                          <template v-if="pi > 0"><br><span class="font-normal text-mp-muted text-xs">vs Period {{ pi }}</span></template>
                        </th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                      <tr v-for="(row, i) in result.rows" :key="row.label"
                        :class="i % 2 === 0 ? 'bg-mp-card' : 'bg-mp-card-hover/40'"
                        class="hover:bg-mp-teal-subtle/20 transition-colors">
                        <td class="px-6 py-3 text-mp-text-secondary font-medium sticky left-0" :class="i % 2 === 0 ? 'bg-mp-card' : 'bg-mp-card-hover'">{{ row.label }}</td>
                        <td v-for="(v, pi) in row.values" :key="pi" class="px-6 py-3 text-right text-mp-text">
                          {{ fmt(v) }}
                          <div v-if="pi > 0" class="text-xs mt-0.5">
                            <span v-if="row.changes[pi - 1] !== null"
                              :class="row.changes[pi - 1] >= 0 ? 'text-mp-danger' : 'text-mp-success'"
                              class="font-semibold">
                              {{ row.changes[pi - 1] >= 0 ? '+' : '' }}{{ row.changes[pi - 1] }}%
                            </span>
                            <span v-else class="text-mp-muted">N/A</span>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="border-t-2 border-mp-border bg-mp-card-hover/60">
                        <td class="px-6 py-3 text-mp-text-secondary font-bold sticky left-0 bg-mp-card-hover/60">Total</td>
                        <td v-for="(p, pi) in result.periods" :key="pi" class="px-6 py-3 text-right text-mp-text-secondary font-bold">
                          {{ fmt(periodColumnTotal(pi)) }}
                          <div v-if="pi > 0" class="text-xs mt-0.5 font-bold">
                            <span v-if="periodColumnChange(pi) !== null"
                              :class="periodColumnChange(pi) >= 0 ? 'text-mp-danger' : 'text-mp-success'">
                              {{ periodColumnChange(pi) >= 0 ? '+' : '' }}{{ periodColumnChange(pi) }}%
                            </span>
                            <span v-else class="text-mp-muted font-normal">N/A</span>
                          </div>
                        </td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
                <!-- Grouped Bar Chart -->
                <div class="mt-4 bg-mp-card rounded-xl border border-mp-border p-5">
                  <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Period Comparison Chart</p>
                  <div style="height:360px">
                    <canvas ref="chartCanvas"></canvas>
                  </div>
                </div>
              </div>

              <!-- ── Min / Avg / Max ── -->
              <div v-if="form.report_type === 'min_avg_max'" class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="bg-mp-teal-subtle/40 border-b border-mp-border">
                      <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Category</th>
                      <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Expense Item</th>
                      <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Min (Monthly)</th>
                      <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Average</th>
                      <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Max (Monthly)</th>
                      <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Outliers</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-800">
                    <template v-for="(row, i) in result" :key="i">
                      <tr :class="i % 2 === 0 ? 'bg-mp-card' : 'bg-mp-card-hover/40'"
                        class="hover:bg-mp-teal-subtle/20 transition-colors cursor-pointer"
                        @click="toggleOutlier(i)">
                        <td class="px-5 py-3 text-mp-text text-xs">{{ row.category }}</td>
                        <td class="px-5 py-3 font-medium text-mp-text-secondary">{{ row.item }}</td>
                        <td class="px-5 py-3 text-right text-mp-success">{{ fmt(row.min) }}</td>
                        <td class="px-5 py-3 text-right text-white font-semibold">{{ fmt(row.avg) }}</td>
                        <td class="px-5 py-3 text-right text-mp-danger">{{ fmt(row.max) }}</td>
                        <td class="px-5 py-3 text-center w-28">
                          <span v-if="row.outlier_count > 0"
                            class="inline-block whitespace-nowrap text-xs bg-mp-warning/15 text-mp-warning border border-mp-warning/50 px-2.5 py-1 rounded-full font-semibold">
                            {{ row.outlier_count }} outlier{{ row.outlier_count > 1 ? 's' : '' }}
                          </span>
                          <span v-else class="text-xs text-mp-muted">—</span>
                        </td>
                      </tr>
                      <tr v-if="expandedOutliers.has(i) && row.outlier_count > 0"
                        :class="i % 2 === 0 ? 'bg-mp-card' : 'bg-mp-card-hover/40'">
                        <td colspan="6" class="px-10 pb-3">
                          <div class="flex flex-wrap gap-2">
                            <span v-for="o in row.outlier_months" :key="o.month"
                              class="text-xs bg-mp-warning/15 border border-mp-warning/50 text-mp-warning px-3 py-1 rounded-full">
                              {{ formatMonth(o.month) }}: {{ fmt(o.value) }}
                            </span>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'

const props = defineProps({
  company:    Object,
  minDate:    String,
  maxDate:    String,
  categories: Array,
})

const reportTypes = [
  { value: 'category_breakdown',    label: 'Category Breakdown' },
  { value: 'subcategory_breakdown', label: 'Sub-Category Breakdown' },
  { value: 'item_breakdown',        label: 'Item Breakdown' },
  { value: 'trend',                 label: 'Trend Analysis' },
  { value: 'period_comparison',     label: 'Period Comparison' },
  { value: 'min_avg_max',           label: 'Min / Avg / Max & Outliers' },
]

const form = ref({
  report_type:  'category_breakdown',
  date_from:    props.minDate ?? '',
  date_to:      props.maxDate ?? '',
  category:     '',
  compare_by:   'category',
  period:       'monthly',
})

// Period Comparison — 2 to 5 independent {from, to} periods.
const periods = ref([
  { from: props.minDate ?? '', to: props.maxDate ?? '' },
  { from: '', to: '' },
])

function setPeriodCount(n) {
  if (periods.value.length < n) {
    while (periods.value.length < n) periods.value.push({ from: '', to: '' })
  } else {
    periods.value = periods.value.slice(0, n)
  }
}

const loading          = ref(false)
const result           = ref(null)

// Switching report type must clear any previously-run result — otherwise
// the results panel tries to render the new report type's layout using
// leftover data from the old report type, which crashes (e.g. Period
// Comparison expects result.periods/rows, but a Category Breakdown
// result has neither).
function selectReportType(value) {
  form.value.report_type = value
  result.value = null
  if (value === 'period_comparison' && !periods.value[0].from && !periods.value[0].to) {
    periods.value[0] = { from: form.value.date_from, to: form.value.date_to }
  }
}

const totalExpense     = ref(0)
const totalRevenue     = ref(0)
const expandedCats     = ref(new Set())
const expandedOutliers = ref(new Set())
const chartCanvas      = ref(null)
let Chart              = null
let chartInst          = null

const showCategoryFilter = computed(() =>
  ['item_breakdown', 'trend', 'min_avg_max'].includes(form.value.report_type)
)

const currentReportLabel = computed(() =>
  reportTypes.find(r => r.value === form.value.report_type)?.label ?? ''
)

const compareByLabel = computed(() => ({
  category: 'Expense Category',
  sub_category: 'Sub Category',
  item: 'Expense Item',
})[form.value.compare_by] ?? 'Label')

// Column total for period index `pi`, summed across every row.
function periodColumnTotal(pi) {
  if (!result.value?.rows) return 0
  return result.value.rows.reduce((s, r) => s + (r.values[pi] ?? 0), 0)
}

// % change of period `pi`'s total vs the immediately preceding period's total.
function periodColumnChange(pi) {
  if (pi === 0) return null
  const prev = periodColumnTotal(pi - 1)
  const curr = periodColumnTotal(pi)
  return prev > 0 ? Math.round((curr - prev) / prev * 1000) / 10 : null
}

async function loadChartJs() {
  if (Chart) return
  await new Promise((resolve, reject) => {
    if (window.Chart) { Chart = window.Chart; resolve(); return }
    const s = document.createElement('script')
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
    s.onload = () => { Chart = window.Chart; resolve() }
    s.onerror = reject
    document.head.appendChild(s)
  })
}

// Render Period Comparison grouped bar chart — same style as Sales reports
watch(result, async (val) => {
  if (!val) { if (chartInst) { chartInst.destroy(); chartInst = null }; return }
  if (form.value.report_type !== 'period_comparison') return
  await nextTick()
  await loadChartJs()
  await nextTick()
  renderComparisonChart(val)
})

function renderComparisonChart(data) {
  if (chartInst) { chartInst.destroy(); chartInst = null }
  if (!chartCanvas.value) return
  const ctx  = chartCanvas.value.getContext('2d')
  const rows = data.rows.slice(0, 20)

  const COLORS = ['#00b4c8','#10b981','#f59e0b','#ef4444','#c9a84c','#00b4c8']
  function alpha(hex, a) {
    const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16)
    return `rgba(${r},${g},${b},${a})`
  }

  chartInst = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: rows.map(r => r.label),
      datasets: data.periods.map((p, pi) => ({
        label: `Period ${pi + 1} (${p.from} → ${p.to})`,
        data: rows.map(r => r.values[pi]),
        backgroundColor: alpha(COLORS[pi % COLORS.length], 0.75),
        borderColor: COLORS[pi % COLORS.length],
        borderWidth: 1,
        borderRadius: 3,
      })),
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { color: '#64748b', font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: ctx => ' ' + ctx.dataset.label.split('(')[0].trim() + ': ' + Number(ctx.raw).toLocaleString('en-US', { maximumFractionDigits: 0 })
          }
        }
      },
      scales: {
        x: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: '#112240' } },
        y: { ticks: { color: '#64748b', font: { size: 10 }, callback: v => Number(v).toLocaleString('en-US', { notation: 'compact' }) }, grid: { color: '#112240' } }
      }
    }
  })
}

// Period Comparison uses the `periods` array instead of a single
// date_from/date_to, but we still send date_from/date_to (mirroring
// Period 1) since the backend uses those for the header's total.
function buildPayload() {
  if (form.value.report_type === 'period_comparison') {
    return {
      ...form.value,
      date_from: periods.value[0]?.from || '',
      date_to:   periods.value[0]?.to || '',
      periods:   periods.value,
    }
  }
  return { ...form.value }
}

async function runReport() {
  loading.value = true
  expandedCats.value = new Set()
  expandedOutliers.value = new Set()
  result.value = null
  try {
    const res = await axios.post(`/companies/${props.company.id}/expenses/reports/run`, buildPayload())
    result.value       = res.data.result
    totalExpense.value = res.data.total_expense
    totalRevenue.value = res.data.total_revenue
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function toggleCat(cat) {
  const s = new Set(expandedCats.value)
  s.has(cat) ? s.delete(cat) : s.add(cat)
  expandedCats.value = s
}

function toggleOutlier(i) {
  const s = new Set(expandedOutliers.value)
  s.has(i) ? s.delete(i) : s.add(i)
  expandedOutliers.value = s
}

async function exportReport() {
  const payload = buildPayload()
  // URLSearchParams can't serialize a nested array as-is — encode it as JSON,
  // matching what the backend already expects for the `periods` field.
  if (payload.periods) payload.periods = JSON.stringify(payload.periods)
  const params = new URLSearchParams(payload)
  window.location.href = `/companies/${props.company.id}/expenses/export-report?${params}`
}

function fmt(val) {
  if (val === null || val === undefined) return '—'
  return Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function formatMonth(m) {
  if (!m) return ''
  const [y, mo] = m.split('-')
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
  return months[parseInt(mo) - 1] + ' ' + y
}

// For trend: handle both YYYY-MM (monthly) and YYYY-Q1 (quarterly) etc.
function formatPeriod(p) {
  if (!p) return ''
  // Monthly: 2024-01 → Jan 2024
  if (/^\d{4}-\d{2}$/.test(p)) return formatMonth(p)
  // Already labelled (Q, H, year) → return as-is
  return p
}
</script>