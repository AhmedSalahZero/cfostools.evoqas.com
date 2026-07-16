<template>
  <Head :title="`Breakeven Analysis — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ── HEADER ── -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <Link :href="`/portfolio-companies/${company.id}`"
                class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-2 w-fit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to {{ company.name }}
              </Link>
              <h1 class="text-2xl font-bold text-white">📊 Breakeven Analysis</h1>
              <p class="text-white text-sm mt-1">{{ company.name }} — Fixed vs Variable cost classification using correlation with revenue</p>
            </div>
            <div class="flex gap-2">
              <a :href="`/companies/${company.id}/expenses`"
                class="px-4 py-2 rounded-lg text-sm text-white hover:text-white hover:bg-mp-card-hover transition-colors">Dashboard</a>
              <a :href="`/companies/${company.id}/expenses/reports`"
                class="px-4 py-2 rounded-lg text-sm text-white hover:text-white hover:bg-mp-card-hover transition-colors">Reports</a>
              <span class="px-4 py-2 rounded-lg text-sm bg-mp-gold text-white font-medium">Breakeven</span>
            </div>
          </div>

          <!-- Date Range + Run -->
          <div class="flex items-center gap-4 mt-5 flex-wrap">
            <div class="flex items-center gap-3 bg-mp-card-hover border border-mp-border rounded-xl px-4 py-2.5">
              <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              <input type="date" v-model="dateFrom" :min="minDate" :max="maxDate"
                class="bg-transparent text-white text-sm focus:outline-none"/>
              <span class="text-white">→</span>
              <input type="date" v-model="dateTo" :min="minDate" :max="maxDate"
                class="bg-transparent text-white text-sm focus:outline-none"/>
            </div>
            <button @click="calculate" :disabled="loading"
              class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 text-white text-sm font-medium px-5 py-2.5 rounded-xl transition-colors">
              <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
              {{ loading ? 'Calculating...' : 'Calculate Breakeven' }}
            </button>
          </div>
        </div>
      </div>

      <!-- ── NO SALES WARNING ── -->
      <div v-if="!hasSales" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="bg-mp-warning/30 border border-mp-warning rounded-xl p-5 flex items-start gap-3">
          <svg class="w-5 h-5 text-mp-warning flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          </svg>
          <div>
            <p class="text-mp-warning font-semibold text-sm">Sales data required for full breakeven calculation</p>
            <p class="text-mp-warning/70 text-xs mt-1">To classify expenses as Fixed vs Variable, the system computes the Pearson correlation between each expense item and monthly revenue. Without sales data, the correlation cannot be calculated and all expenses will be shown as Fixed. Please upload sales data first for accurate results.</p>
          </div>
        </div>
      </div>

      <!-- ── HOW IT WORKS — INFO BOX ── -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="bg-mp-teal-subtle/30 border border-mp-teal/50 rounded-xl p-5">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-2">How Classification Works</p>
          <p class="text-white text-sm leading-relaxed">
            The system computes the <strong class="text-white">Pearson correlation coefficient (r)</strong> between each expense item's monthly spending and monthly revenue.
            If <strong class="text-mp-success">r ≥ 0.65</strong>, the expense moves with revenue → classified as
            <strong class="text-mp-warning">Variable</strong>.
            If <strong class="text-mp-danger">r &lt; 0.65</strong>, the expense is independent → classified as
            <strong class="text-white">Fixed</strong>.
            Breakeven Revenue = Fixed Costs ÷ Contribution Margin Ratio, where CM Ratio = (Revenue − Variable Costs) ÷ Revenue.
          </p>
        </div>
      </div>

      <!-- ── MAIN CONTENT ── -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 space-y-8">

        <!-- Placeholder before calculation -->
        <div v-if="!result" class="bg-mp-card border border-mp-border border-dashed rounded-xl p-16 text-center">
          <svg class="w-12 h-12 text-white mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
          <p class="text-white text-sm">Select a date range and click <strong class="text-white">Calculate Breakeven</strong></p>
          <p class="text-white text-xs mt-1">The system will automatically classify each expense as Fixed or Variable using statistical correlation with revenue.</p>
        </div>

        <!-- ── RESULTS ── -->
        <template v-else>

          <!-- ══════════════════════════════════════════════════
               SECTION 1: BREAKEVEN SUMMARY CARDS
          ═══════════════════════════════════════════════════ -->
          <div>
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Breakeven Summary</p>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">

              <!-- Total Revenue -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-4 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-mp-teal/5 rounded-full -translate-y-4 translate-x-4"></div>
                <p class="text-xs text-white uppercase tracking-widest mb-1">Total Revenue</p>
                <p class="text-xl font-bold text-white">{{ fmt(result.total_revenue) }}</p>
              </div>

              <!-- Fixed Costs -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-4 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-mp-teal/5 rounded-full -translate-y-4 translate-x-4"></div>
                <p class="text-xs text-white uppercase tracking-widest mb-1">Fixed Costs</p>
                <p class="text-xl font-bold text-white">{{ fmt(result.fixed_total) }}</p>
                <p class="text-xs text-white mt-0.5">{{ fixedPct }}% of expenses</p>
              </div>

              <!-- Variable Costs -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-4 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-mp-warning/5 rounded-full -translate-y-4 translate-x-4"></div>
                <p class="text-xs text-white uppercase tracking-widest mb-1">Variable Costs</p>
                <p class="text-xl font-bold text-mp-warning">{{ fmt(result.variable_total) }}</p>
                <p class="text-xs text-white mt-0.5">{{ variablePct }}% of expenses</p>
              </div>

              <!-- CM Ratio -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-4 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-mp-gold-dark/5 rounded-full -translate-y-4 translate-x-4"></div>
                <p class="text-xs text-white uppercase tracking-widest mb-1">CM Ratio</p>
                <p class="text-xl font-bold text-white">
                  {{ result.cm_ratio !== null ? result.cm_ratio + '%' : 'N/A' }}
                </p>
                <p class="text-xs text-white mt-0.5">Contribution Margin</p>
              </div>

              <!-- Breakeven Revenue -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-4 relative overflow-hidden"
                :class="breakEvenStatus === 'healthy' ? 'border-mp-success/60' : breakEvenStatus === 'caution' ? 'border-mp-warning/60' : 'border-mp-danger/60'">
                <div class="absolute top-0 right-0 w-16 h-16 rounded-full -translate-y-4 translate-x-4"
                  :class="breakEvenStatus === 'healthy' ? 'bg-mp-success/5' : breakEvenStatus === 'caution' ? 'bg-mp-warning/5' : 'bg-mp-danger/5'"></div>
                <p class="text-xs text-white uppercase tracking-widest mb-1">Breakeven Revenue</p>
                <p class="text-xl font-bold"
                  :class="breakEvenStatus === 'healthy' ? 'text-mp-success' : breakEvenStatus === 'caution' ? 'text-mp-warning' : 'text-mp-danger'">
                  {{ result.breakeven_revenue !== null ? fmt(result.breakeven_revenue) : 'N/A' }}
                </p>
                <p class="text-xs text-white mt-0.5">
                  {{ result.breakeven_pct !== null ? result.breakeven_pct + '% of revenue' : '' }}
                </p>
              </div>

              <!-- Safety Margin -->
              <div class="bg-mp-card border border-mp-border rounded-xl p-4 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-mp-success/5 rounded-full -translate-y-4 translate-x-4"></div>
                <p class="text-xs text-white uppercase tracking-widest mb-1">Safety Margin</p>
                <p class="text-xl font-bold"
                  :class="(result.safety_margin ?? 0) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                  {{ result.safety_margin !== null ? fmt(result.safety_margin) : 'N/A' }}
                </p>
                <p class="text-xs text-white mt-0.5">
                  {{ result.safety_margin_pct !== null ? Math.abs(result.safety_margin_pct) + '%' + ((result.safety_margin_pct ?? 0) >= 0 ? ' above breakeven' : ' below breakeven') : '' }}
                </p>
              </div>

            </div>
          </div>

          <!-- ══════════════════════════════════════════════════
               SECTION 2: VISUAL BREAKEVEN CHART (Horizontal Bar)
          ═══════════════════════════════════════════════════ -->
          <div v-if="result.breakeven_revenue !== null" class="bg-mp-card border border-mp-border rounded-xl p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-6">Revenue vs Breakeven Point</p>

            <!-- Bar visualization -->
            <div class="space-y-5">
              <!-- Fixed Costs bar -->
              <div>
                <div class="flex justify-between items-center mb-1.5">
                  <span class="text-xs text-white font-medium">Fixed Costs</span>
                  <span class="text-xs text-white font-semibold">{{ fmt(result.fixed_total) }}</span>
                </div>
                <div class="w-full bg-mp-card-hover rounded-full h-5 relative overflow-hidden">
                  <div class="h-5 bg-mp-teal rounded-full transition-all duration-700"
                    :style="`width: ${Math.min(fixedPctOfRev, 100)}%`"></div>
                  <span class="absolute right-2 top-0 h-5 flex items-center text-xs text-white">{{ fixedPctOfRev }}% of revenue</span>
                </div>
              </div>

              <!-- Variable Costs bar -->
              <div>
                <div class="flex justify-between items-center mb-1.5">
                  <span class="text-xs text-white font-medium">Variable Costs</span>
                  <span class="text-xs text-white font-semibold">{{ fmt(result.variable_total) }}</span>
                </div>
                <div class="w-full bg-mp-card-hover rounded-full h-5 relative overflow-hidden">
                  <div class="h-5 bg-mp-warning rounded-full transition-all duration-700"
                    :style="`width: ${Math.min(variablePctOfRev, 100)}%`"></div>
                  <span class="absolute right-2 top-0 h-5 flex items-center text-xs text-white">{{ variablePctOfRev }}% of revenue</span>
                </div>
              </div>

              <!-- Breakeven line -->
              <div>
                <div class="flex justify-between items-center mb-1.5">
                  <span class="text-xs font-semibold text-mp-warning">Breakeven Revenue</span>
                  <span class="text-xs text-mp-warning font-semibold">{{ fmt(result.breakeven_revenue) }}</span>
                </div>
                <div class="w-full bg-mp-card-hover rounded-full h-5 relative overflow-hidden">
                  <div class="h-5 rounded-full transition-all duration-700"
                    :class="breakEvenStatus === 'healthy' ? 'bg-mp-warning/60' : 'bg-mp-danger/60'"
                    :style="`width: ${Math.min(result.breakeven_pct, 100)}%`"></div>
                  <!-- Actual revenue line marker -->
                  <div class="absolute top-0 right-0 h-5 w-px bg-white/60"></div>
                  <span class="absolute right-2 top-0 h-5 flex items-center text-xs text-mp-warning font-semibold">
                    {{ result.breakeven_pct }}% of revenue
                  </span>
                </div>
              </div>

              <!-- Actual Revenue bar (always 100%) -->
              <div>
                <div class="flex justify-between items-center mb-1.5">
                  <span class="text-xs text-white font-medium">Actual Revenue</span>
                  <span class="text-xs text-white font-semibold">{{ fmt(result.total_revenue) }}</span>
                </div>
                <div class="w-full bg-mp-card-hover rounded-full h-5 overflow-hidden">
                  <div class="h-5 bg-mp-teal/40 border border-mp-teal rounded-full w-full flex items-center px-3">
                    <span class="text-xs text-white font-medium">100%</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Status badge -->
            <div class="mt-6 flex items-center gap-3">
              <span class="text-xs font-semibold px-3 py-1.5 rounded-full"
                :class="{
                  'bg-mp-success/60 text-mp-success border border-mp-success': breakEvenStatus === 'healthy',
                  'bg-mp-warning/60 text-mp-warning border border-mp-warning': breakEvenStatus === 'caution',
                  'bg-mp-danger/60 text-mp-danger border border-mp-danger': breakEvenStatus === 'at_risk',
                }">
                {{ breakEvenStatus === 'healthy' ? '✅ Healthy — Revenue well above breakeven' : breakEvenStatus === 'caution' ? '⚠️ Caution — Close to breakeven point' : '🚨 At Risk — Revenue below breakeven' }}
              </span>
              <span v-if="result.safety_margin_pct !== null" class="text-xs text-white">
                Safety margin: {{ result.safety_margin_pct }}%
              </span>
            </div>
          </div>

          <!-- No breakeven possible -->
          <div v-else class="bg-mp-warning/20 border border-mp-warning rounded-xl p-6 text-center">
            <p class="text-mp-warning font-semibold">Cannot Calculate Breakeven</p>
            <p class="text-mp-warning/70 text-sm mt-1">
              The Contribution Margin Ratio is zero or negative — variable costs equal or exceed revenue.
              Check if sales data exists for this period.
            </p>
          </div>

          <!-- ══════════════════════════════════════════════════
               SECTION 3: EXPENSE ITEM CLASSIFICATION TABLE
          ═══════════════════════════════════════════════════ -->
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">

            <!-- Table Header with filters -->
            <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between flex-wrap gap-3">
              <p class="text-xs font-semibold text-white uppercase tracking-widest">Expense Item Classification</p>
              <div class="flex items-center gap-3">
                <!-- Filter buttons -->
                <div class="flex items-center bg-mp-card-hover rounded-lg p-0.5">
                  <button @click="filterNature = 'all'"
                    :class="filterNature === 'all' ? 'bg-mp-page text-white' : 'text-white hover:text-white'"
                    class="text-xs font-medium px-3 py-1.5 rounded-md transition-all">
                    All ({{ result.items.length }})
                  </button>
                  <button @click="filterNature = 'fixed'"
                    :class="filterNature === 'fixed' ? 'bg-mp-page text-white' : 'text-white hover:text-white'"
                    class="text-xs font-medium px-3 py-1.5 rounded-md transition-all">
                    Fixed ({{ fixedCount }})
                  </button>
                  <button @click="filterNature = 'variable'"
                    :class="filterNature === 'variable' ? 'bg-mp-page text-white' : 'text-white hover:text-white'"
                    class="text-xs font-medium px-3 py-1.5 rounded-md transition-all">
                    Variable ({{ variableCount }})
                  </button>
                </div>
                <!-- Legend -->
                <div class="flex items-center gap-3 text-xs text-white">
                  <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-mp-teal inline-block"></span> Fixed
                  </span>
                  <span class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-mp-warning inline-block"></span> Variable
                  </span>
                </div>
              </div>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-mp-teal-subtle/40 border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Category</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Expense Item</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Total Amount</th>
                    <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Correlation (r)</th>
                    <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Classification</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">% of Total Expense</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(item, i) in filteredItems" :key="i"
                    :class="i % 2 === 0 ? 'bg-mp-card' : 'bg-mp-card-hover/40'"
                    class="hover:bg-mp-teal-subtle/20 transition-colors">
                    <td class="px-5 py-3 text-white text-xs">{{ item.category }}</td>
                    <td class="px-5 py-3 text-white font-medium">{{ item.item }}</td>
                    <td class="px-5 py-3 text-right text-white font-semibold">{{ fmt(item.total) }}</td>
                    <td class="px-5 py-3 text-center">
                      <span v-if="item.correlation !== null" class="font-mono text-xs"
                        :class="{
                          'text-mp-warning': item.correlation >= 0.65,
                          'text-white': item.correlation >= 0.3 && item.correlation < 0.65,
                          'text-white': item.correlation < 0.3
                        }">
                        {{ item.correlation >= 0 ? '+' : '' }}{{ item.correlation }}
                      </span>
                      <span v-else class="text-white text-xs italic">No sales data</span>
                    </td>
                    <td class="px-5 py-3 text-center">
                      <span class="text-xs font-semibold px-3 py-1 rounded-full"
                        :class="item.nature === 'variable'
                          ? 'bg-mp-warning/60 text-mp-warning border border-mp-warning'
                          : 'bg-mp-teal-subtle/60 text-white border border-mp-teal'">
                        {{ item.nature === 'variable' ? '📈 Variable' : '📌 Fixed' }}
                      </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <div class="w-16 bg-mp-page rounded-full h-1.5">
                          <div class="h-1.5 rounded-full"
                            :class="item.nature === 'variable' ? 'bg-mp-warning' : 'bg-mp-teal'"
                            :style="`width:${Math.min(result.total_expense > 0 ? (item.total/result.total_expense*100) : 0, 100)}%`">
                          </div>
                        </div>
                        <span class="text-white text-xs w-10 text-right">
                          {{ result.total_expense > 0 ? (item.total/result.total_expense*100).toFixed(1) : 0 }}%
                        </span>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="border-t-2 border-mp-border bg-mp-card-hover/60">
                    <td colspan="2" class="px-5 py-3 text-white font-bold">Total</td>
                    <td class="px-5 py-3 text-right text-white font-bold">{{ fmt(result.total_expense) }}</td>
                    <td colspan="3" class="px-5 py-3"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- ══════════════════════════════════════════════════
               SECTION 4: CATEGORY SUMMARY
          ═══════════════════════════════════════════════════ -->
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest">Fixed vs Variable by Category</p>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-mp-teal-subtle/40 border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Category</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Fixed</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Variable</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Total</th>
                    <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Fixed %</th>
                    <th class="text-center text-xs font-semibold text-white uppercase tracking-widest px-5 py-3">Variable %</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(cat, i) in categorySummary" :key="cat.category"
                    :class="i % 2 === 0 ? 'bg-mp-card' : 'bg-mp-card-hover/40'"
                    class="hover:bg-mp-teal-subtle/20 transition-colors">
                    <td class="px-5 py-3 text-white font-medium">{{ cat.category }}</td>
                    <td class="px-5 py-3 text-right text-white">{{ fmt(cat.fixed) }}</td>
                    <td class="px-5 py-3 text-right text-mp-warning">{{ fmt(cat.variable) }}</td>
                    <td class="px-5 py-3 text-right text-white font-semibold">{{ fmt(cat.total) }}</td>
                    <td class="px-5 py-3 text-center">
                      <div class="flex items-center justify-center gap-2">
                        <div class="w-20 bg-mp-page rounded-full h-2 overflow-hidden">
                          <div class="h-2 bg-mp-teal rounded-full"
                            :style="`width:${cat.total > 0 ? (cat.fixed/cat.total*100).toFixed(0) : 0}%`"></div>
                        </div>
                        <span class="text-white text-xs w-10 text-left">{{ cat.total > 0 ? (cat.fixed/cat.total*100).toFixed(0) : 0 }}%</span>
                      </div>
                    </td>
                    <td class="px-5 py-3 text-center">
                      <div class="flex items-center justify-center gap-2">
                        <div class="w-20 bg-mp-page rounded-full h-2 overflow-hidden">
                          <div class="h-2 bg-mp-warning rounded-full"
                            :style="`width:${cat.total > 0 ? (cat.variable/cat.total*100).toFixed(0) : 0}%`"></div>
                        </div>
                        <span class="text-mp-warning text-xs w-10 text-left">{{ cat.total > 0 ? (cat.variable/cat.total*100).toFixed(0) : 0 }}%</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ══════════════════════════════════════════════════
               SECTION 5: BREAKEVEN FORMULA EXPLAINER
          ═══════════════════════════════════════════════════ -->
          <div class="bg-mp-card border border-mp-border rounded-xl p-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-5">Breakeven Formula Breakdown</p>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-center">

              <div class="bg-mp-card-hover/60 rounded-xl p-4">
                <p class="text-xs text-white mb-2 uppercase tracking-widest">Step 1</p>
                <p class="text-xs text-white">Contribution Margin</p>
                <p class="text-sm text-white font-mono mt-1">Revenue − Variable</p>
                <p class="text-lg font-bold text-white mt-2">{{ fmt(result.total_revenue - result.variable_total) }}</p>
              </div>

              <div class="bg-mp-card-hover/60 rounded-xl p-4">
                <p class="text-xs text-white mb-2 uppercase tracking-widest">Step 2</p>
                <p class="text-xs text-white">CM Ratio</p>
                <p class="text-sm text-white font-mono mt-1">CM ÷ Revenue</p>
                <p class="text-lg font-bold text-white mt-2">{{ result.cm_ratio !== null ? result.cm_ratio + '%' : 'N/A' }}</p>
              </div>

              <div class="bg-mp-card-hover/60 rounded-xl p-4">
                <p class="text-xs text-white mb-2 uppercase tracking-widest">Step 3</p>
                <p class="text-xs text-white">Breakeven Revenue</p>
                <p class="text-sm text-white font-mono mt-1">Fixed ÷ CM Ratio</p>
                <p class="text-lg font-bold text-mp-warning mt-2">{{ result.breakeven_revenue !== null ? fmt(result.breakeven_revenue) : 'N/A' }}</p>
              </div>

              <div class="bg-mp-card-hover/60 rounded-xl p-4">
                <p class="text-xs text-white mb-2 uppercase tracking-widest">Result</p>
                <p class="text-xs text-white">Safety Margin</p>
                <p class="text-sm text-white font-mono mt-1">Revenue − Breakeven</p>
                <p class="text-lg font-bold mt-2"
                  :class="(result.safety_margin ?? 0) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                  {{ result.safety_margin !== null ? fmt(result.safety_margin) : 'N/A' }}
                </p>
              </div>

            </div>
          </div>

        </template>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'

const props = defineProps({
  company:     Object,
  defaultFrom: String,
  defaultTo:   String,
  minDate:     String,
  maxDate:     String,
  hasSales:    Boolean,
})

const loading     = ref(false)
const result      = ref(null)
const filterNature = ref('all')

const dateFrom = ref(props.defaultFrom ?? props.minDate ?? '')
const dateTo   = ref(props.defaultTo   ?? props.maxDate ?? '')

// ── Computed helpers ──

const breakEvenStatus = computed(() => {
  if (!result.value || result.value.breakeven_pct === null) return 'healthy'
  const pct = result.value.breakeven_pct
  if (pct <= 80) return 'healthy'
  if (pct <= 100) return 'caution'
  return 'at_risk'
})

const fixedPct = computed(() => {
  if (!result.value || result.value.total_expense === 0) return 0
  return Math.round(result.value.fixed_total / result.value.total_expense * 100)
})

const variablePct = computed(() => {
  if (!result.value || result.value.total_expense === 0) return 0
  return Math.round(result.value.variable_total / result.value.total_expense * 100)
})

const fixedPctOfRev = computed(() => {
  if (!result.value || result.value.total_revenue === 0) return 0
  return Math.round(result.value.fixed_total / result.value.total_revenue * 100)
})

const variablePctOfRev = computed(() => {
  if (!result.value || result.value.total_revenue === 0) return 0
  return Math.round(result.value.variable_total / result.value.total_revenue * 100)
})

const fixedCount = computed(() =>
  result.value ? result.value.items.filter(i => i.nature === 'fixed').length : 0
)
const variableCount = computed(() =>
  result.value ? result.value.items.filter(i => i.nature === 'variable').length : 0
)

const filteredItems = computed(() => {
  if (!result.value) return []
  if (filterNature.value === 'all') return result.value.items
  return result.value.items.filter(i => i.nature === filterNature.value)
})

// Build per-category summary from items list
const categorySummary = computed(() => {
  if (!result.value) return []
  const map = {}
  for (const item of result.value.items) {
    if (!map[item.category]) {
      map[item.category] = { category: item.category, fixed: 0, variable: 0, total: 0 }
    }
    map[item.category].total += item.total
    if (item.nature === 'fixed') map[item.category].fixed += item.total
    else map[item.category].variable += item.total
  }
  return Object.values(map).sort((a, b) => b.total - a.total)
})

// ── Calculate ──
async function calculate() {
  loading.value = true
  result.value  = null
  filterNature.value = 'all'
  try {
    const { data } = await axios.post(
      `/companies/${props.company.id}/expenses/breakeven/calculate`,
      { date_from: dateFrom.value, date_to: dateTo.value }
    )
    result.value = data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

// ── Format number ──
function fmt(val) {
  if (val === null || val === undefined) return '—'
  return Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}
</script>