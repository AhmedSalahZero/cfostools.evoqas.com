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
                  @click="form.report_type = rt.value"
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

            <!-- Date Range -->
            <div class="bg-mp-card rounded-xl border border-mp-border p-5">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">
                {{ form.report_type === 'period_comparison' ? 'Period 1' : 'Date Range' }}
              </p>
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

            <!-- Period 2 (only for Period Comparison) -->
            <div v-if="form.report_type === 'period_comparison'" class="bg-mp-card rounded-xl border border-mp-border p-5">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Period 2</p>
              <div class="space-y-3">
                <div>
                  <label class="block text-xs text-mp-muted mb-1">Compare From</label>
                  <input type="date" v-model="form.compare_from" :min="minDate" :max="maxDate"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
                </div>
                <div>
                  <label class="block text-xs text-mp-muted mb-1">Compare To</label>
                  <input type="date" v-model="form.compare_to" :min="minDate" :max="maxDate"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
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
                      <th v-for="m in result.months" :key="m"
                        class="text-center text-xs font-semibold text-white uppercase tracking-widest px-3 py-3 whitespace-nowrap min-w-[130px]">
                        {{ formatPeriod(m) }}
                      </th>
                      <!-- Total at END — same as Sales two_factors_trend -->
                      <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-4 py-3 min-w-[100px]">
                        Total
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="cat in result.rows" :key="cat.category">

                      <!-- ── CATEGORY ROW (collapsible) ── -->
                      <tr class="border-b border-mp-border bg-mp-teal-subtle/30 cursor-pointer hover:bg-mp-teal-subtle/50 transition-colors"
                        @click="toggleCat(cat.category)">
                        <td class="px-5 py-2.5 sticky left-0 bg-mp-teal-subtle/30 z-10">
                          <div class="flex items-center gap-2">
                            <svg class="w-3 h-3 text-white transition-transform flex-shrink-0"
                              :class="expandedCats.has(cat.category) ? 'rotate-90' : ''"
                              fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <span class="text-mp-text-secondary font-semibold text-xs">{{ cat.category }}</span>
                          </div>
                        </td>
                        <!-- Period cells for category totals -->
                        <td v-for="(m, mi) in result.months" :key="m" class="px-3 py-2.5 text-center">
                          <div class="text-mp-text-secondary text-xs font-semibold">{{ fmt(cat.months[m] || 0) }}</div>
                          <div class="text-xs mt-0.5"
                            :class="getCatGR(cat, result.months, mi) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                            <template v-if="mi > 0">
                              [GR {{ getCatGR(cat, result.months, mi) >= 0 ? '+' : '' }}{{ getCatGR(cat, result.months, mi) }}%]
                            </template>
                            <template v-else><span class="text-mp-muted">—</span></template>
                          </div>
                        </td>
                        <!-- Total at end -->
                        <td class="px-4 py-2.5 text-right text-mp-text-secondary font-bold text-xs">{{ fmt(cat.total) }}</td>
                      </tr>

                      <!-- ── ITEM ROWS (expanded) ── -->
                      <template v-if="expandedCats.has(cat.category)">
                        <tr v-for="item in cat.items" :key="item.item"
                          class="border-b border-mp-border hover:bg-mp-card-hover/30 transition-colors">
                          <td class="px-5 py-2 pl-10 sticky left-0 bg-mp-card z-10 text-mp-text text-xs">
                            {{ item.item }}
                          </td>
                          <!-- Period cells for item -->
                          <td v-for="(m, mi) in result.months" :key="m" class="px-3 py-2 text-center">
                            <div class="text-mp-text text-xs">{{ item.months[m] > 0 ? fmt(item.months[m]) : '—' }}</div>
                            <div v-if="item.months[m] > 0 && mi > 0" class="text-xs mt-0.5"
                              :class="getItemGR(item, result.months, mi) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                              [{{ getItemGR(item, result.months, mi) >= 0 ? '+' : '' }}{{ getItemGR(item, result.months, mi) }}%]
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
                <div class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="bg-mp-teal-subtle/40 border-b border-mp-border">
                        <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">
                          {{ compareByLabel }}
                        </th>
                        <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">
                          Period 1<br>
                          <span class="font-normal text-mp-muted text-xs">{{ result.period1.from }} → {{ result.period1.to }}</span>
                        </th>
                        <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">
                          Period 2<br>
                          <span class="font-normal text-mp-muted text-xs">{{ result.period2.from }} → {{ result.period2.to }}</span>
                        </th>
                        <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">Change %</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                      <tr v-for="(row, i) in result.rows" :key="row.label"
                        :class="i % 2 === 0 ? 'bg-mp-card' : 'bg-mp-card-hover/40'"
                        class="hover:bg-mp-teal-subtle/20 transition-colors">
                        <td class="px-6 py-3 text-mp-text-secondary font-medium">{{ row.label }}</td>
                        <td class="px-6 py-3 text-right text-mp-text">{{ fmt(row.period1) }}</td>
                        <td class="px-6 py-3 text-right text-mp-text">{{ fmt(row.period2) }}</td>
                        <td class="px-6 py-3 text-right">
                          <span v-if="row.change !== null"
                            :class="row.change >= 0 ? 'text-mp-danger' : 'text-mp-success'"
                            class="font-semibold">
                            {{ row.change >= 0 ? '+' : '' }}{{ row.change }}%
                          </span>
                          <span v-else class="text-mp-muted">N/A</span>
                        </td>
                      </tr>
                    </tbody>
                    <tfoot>
                      <tr class="border-t-2 border-mp-border bg-mp-card-hover/60">
                        <td class="px-6 py-3 text-mp-text-secondary font-bold">Total</td>
                        <td class="px-6 py-3 text-right text-mp-text-secondary font-bold">{{ fmt(result.rows.reduce((s,r) => s + r.period1, 0)) }}</td>
                        <td class="px-6 py-3 text-right text-mp-text-secondary font-bold">{{ fmt(result.rows.reduce((s,r) => s + r.period2, 0)) }}</td>
                        <td class="px-6 py-3 text-right">
                          <span :class="periodTotalChange >= 0 ? 'text-mp-danger' : 'text-mp-success'" class="font-bold">
                            {{ periodTotalChange >= 0 ? '+' : '' }}{{ periodTotalChange }}%
                          </span>
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
                            class="inline-block whitespace-nowrap text-xs bg-mp-warning text-mp-warning border border-mp-warning px-2.5 py-1 rounded-full font-semibold">
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
                              class="text-xs bg-mp-warning border border-mp-warning text-mp-warning px-3 py-1 rounded-full">
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
  compare_from: '',
  compare_to:   '',
  compare_by:   'category',
  period:       'monthly',
})

const loading          = ref(false)
const result           = ref(null)
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

const periodTotalChange = computed(() => {
  if (!result.value?.rows) return 0
  const t1 = result.value.rows.reduce((s, r) => s + (r.period1 ?? 0), 0)
  const t2 = result.value.rows.reduce((s, r) => s + (r.period2 ?? 0), 0)
  return t1 > 0 ? Math.round((t2 - t1) / t1 * 1000) / 10 : 0
})

// ─── GR% helpers for Trend table (calculated in Vue, not backend) ───
function getCatGR(cat, months, mi) {
  if (mi === 0) return 0
  const prev = cat.months[months[mi - 1]] || 0
  const curr = cat.months[months[mi]] || 0
  if (prev === 0) return 0
  return Math.round((curr - prev) / prev * 1000) / 10
}

function getItemGR(item, months, mi) {
  if (mi === 0) return 0
  const prev = item.months[months[mi - 1]] || 0
  const curr = item.months[months[mi]] || 0
  if (prev === 0) return 0
  return Math.round((curr - prev) / prev * 1000) / 10
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
      datasets: [
        {
          label: `Period 1 (${data.period1.from} → ${data.period1.to})`,
          data: rows.map(r => r.period1),
          backgroundColor: alpha('#00b4c8', 0.75),
          borderColor: '#00b4c8',
          borderWidth: 1,
          borderRadius: 3,
        },
        {
          label: `Period 2 (${data.period2.from} → ${data.period2.to})`,
          data: rows.map(r => r.period2),
          backgroundColor: alpha('#10b981', 0.75),
          borderColor: '#10b981',
          borderWidth: 1,
          borderRadius: 3,
        }
      ]
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

async function runReport() {
  loading.value = true
  expandedCats.value = new Set()
  expandedOutliers.value = new Set()
  result.value = null
  try {
    const res = await axios.post(`/companies/${props.company.id}/expenses/reports/run`, form.value)
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
  const params = new URLSearchParams({ ...form.value })
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