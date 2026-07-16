<template>
  <Head :title="company.name" />

  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ══════════════════════════════════════════
           COMPANY HEADER
      ═══════════════════════════════════════════ -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

          <!-- Top row: back + actions -->
          <div class="flex items-center justify-between mb-4">
            <Link
              href="/portfolio-companies"
              class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              Back to Portfolio
            </Link>

            <div class="flex items-center gap-2">
              <!-- Quick Nav -->
              <div class="hidden md:flex items-center gap-1 bg-mp-card-hover rounded-lg p-1">
                <a v-for="nav in quickNav" :key="nav.label" :href="nav.href"
                  class="flex items-center gap-1.5 px-3 py-1.5 text-xs text-white hover:text-white hover:bg-mp-page rounded-md transition-colors whitespace-nowrap">
                  <span>{{ nav.label }}</span>
                </a>
              </div>
              <Link
                v-if="canEdit"
                :href="`/portfolio-companies/${company.id}/edit`"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
              </Link>
            </div>
          </div>

          <!-- Company identity row -->
          <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-xl bg-mp-teal flex items-center justify-center text-2xl font-bold flex-shrink-0">
              {{ company.name.charAt(0).toUpperCase() }}
            </div>

            <div class="flex-1">
              <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-white">{{ company.name }}</h1>
                <span :class="statusBadgeClass" class="text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wide">
                  {{ statusLabel }}
                </span>
                <span v-if="company.lead_source"
                  class="text-xs font-semibold px-3 py-1 rounded-full bg-mp-card-hover text-white border border-mp-border uppercase tracking-wide">
                  {{ company.lead_source }}
                </span>
              </div>
              <div class="flex flex-wrap gap-4 mt-2 text-sm text-white">
                <span class="flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                  </svg>
                  {{ company.sector }}
                </span>
                <span class="flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                  Invested: {{ formatDate(company.transaction_date) }}
                </span>
                <span v-if="company.organization?.name" class="flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16" />
                  </svg>
                  {{ company.organization.name }}
                </span>
              </div>
            </div>

            <!-- Currency Toggle -->
            <div class="flex items-center bg-mp-card-hover rounded-lg p-1 gap-1 flex-shrink-0">
              <button @click="currency = 'local'"
                :class="currency === 'local' ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
                class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                {{ company.invested_currency || 'EGP' }}
              </button>
              <button @click="currency = 'fx'"
                :class="currency === 'fx' ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
                class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                {{ company.fx_currency || 'USD' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════
           MAIN CONTENT
      ═══════════════════════════════════════════ -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">

        <!-- ────────────────────────────────────────
             SECTION 1: VALUATION SNAPSHOT
        ──────────────────────────────────────── -->
        <div>
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">Valuation Snapshot</p>
            <span v-if="ebitdaValuation" class="text-xs text-mp-success bg-mp-success/30 border border-mp-success px-2 py-1 rounded-md">
              ✓ Auto-calculated from latest EBITDA × {{ company.ebitda_multiplier }}x
            </span>
          </div>

          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- MOIC -->
            <div class="bg-gradient-to-br from-mp-teal-dark to-mp-teal-dark rounded-xl p-5 shadow-lg">
              <p class="text-xs text-white uppercase tracking-wide mb-1">MOIC</p>
              <p class="text-3xl font-bold text-white">{{ company.moic ? Number(company.moic).toFixed(2) + 'x' : '—' }}</p>
              <p class="text-xs text-white mt-1">Multiple on Invested Capital</p>
            </div>

            <!-- IRR -->
            <div class="bg-gradient-to-br from-mp-teal-dark to-mp-teal-dark rounded-xl p-5 shadow-lg">
              <p class="text-xs text-white uppercase tracking-wide mb-1">IRR</p>
              <p class="text-3xl font-bold text-white">{{ company.irr ? Number(company.irr).toFixed(1) + '%' : '—' }}</p>
              <p class="text-xs text-white mt-1">Internal Rate of Return</p>
            </div>

            <!-- Entry Valuation -->
            <div class="bg-mp-card rounded-xl p-5 border border-mp-border">
              <p class="text-xs text-white uppercase tracking-wide mb-1">Entry Valuation</p>
              <p class="text-2xl font-bold text-white">{{ fmt(convertedVal(company.entry_valuation)) }}</p>
              <p class="text-xs text-white mt-1">At transaction date</p>
            </div>

            <!-- Current Valuation -->
            <div class="bg-mp-card rounded-xl p-5 border border-mp-border relative">
              <p class="text-xs text-white uppercase tracking-wide mb-1">Current Valuation</p>
              <p class="text-2xl font-bold text-white">
                {{ company.current_valuation ? fmt(convertedVal(company.current_valuation)) : '—' }}
              </p>
              <p class="text-xs text-white mt-1">
                <span v-if="ebitdaValuation" class="text-mp-success">EBITDA-based estimate</span>
                <span v-else>Latest estimate</span>
              </p>
              <!-- Uplift badge -->
              <div v-if="company.current_valuation && company.entry_valuation" class="absolute top-4 right-4">
                <span :class="upliftClass" class="text-xs font-semibold px-2 py-0.5 rounded-full">
                  {{ upliftPct }}
                </span>
              </div>
            </div>
          </div>

          <!-- Investment details row -->
          <div class="mt-4 bg-mp-card rounded-xl border border-mp-border p-5">
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-6">
              <div>
                <p class="text-xs text-white uppercase tracking-wide mb-1">Invested Amount</p>
                <p class="text-lg font-semibold text-white">{{ fmt(convertedVal(company.invested_amount)) }}</p>
                <p class="text-xs text-white mt-0.5">{{ activeCurrency }}</p>
              </div>
              <div>
                <p class="text-xs text-white uppercase tracking-wide mb-1">Equity Stake</p>
                <p class="text-lg font-semibold text-white">{{ equityPercent }}%</p>
                <p class="text-xs text-white mt-0.5">Ownership share</p>
              </div>
              <div>
                <p class="text-xs text-white uppercase tracking-wide mb-1">EBITDA Multiplier</p>
                <p class="text-lg font-semibold text-white">{{ company.ebitda_multiplier ? company.ebitda_multiplier + 'x' : '—' }}</p>
                <p class="text-xs text-white mt-0.5">Valuation multiple</p>
              </div>
              <div>
                <p class="text-xs text-white uppercase tracking-wide mb-1">FX Rate</p>
                <p class="text-lg font-semibold text-white">{{ company.fx_rate ? Number(company.fx_rate).toLocaleString() : '—' }}</p>
                <p class="text-xs text-white mt-0.5">{{ company.invested_currency }}/{{ company.fx_currency }}</p>
              </div>
              <div>
                <p class="text-xs text-white uppercase tracking-wide mb-1">Last FS Update</p>
                <p class="text-lg font-semibold text-white">{{ company.last_financial_update ? formatDate(company.last_financial_update) : '—' }}</p>
                <p class="text-xs text-white mt-0.5">Financial data</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ────────────────────────────────────────
             SECTION 2: FINANCIAL STATEMENTS (last 3)
        ──────────────────────────────────────── -->
        <div>
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">Financial Statements</p>
            <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
              class="text-xs text-white hover:text-white transition-colors flex items-center gap-1">
              View All →
            </Link>
          </div>

          <!-- Has data -->
          <div v-if="fsData && fsData.length > 0">
            <div class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-white uppercase tracking-wide">Period</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-white uppercase tracking-wide">Revenue</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-white uppercase tracking-wide">Gross Profit</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-white uppercase tracking-wide">EBITDA</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-white uppercase tracking-wide">Net Profit</th>
                    <th class="px-5 py-3 text-xs font-semibold text-white uppercase tracking-wide">Status</th>
                    <th class="px-5 py-3"></th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="(fs, idx) in fsData" :key="fs.id">
                    <tr :class="idx < fsData.length - 1 ? 'border-b border-mp-border/60' : ''"
                      class="hover:bg-mp-card-hover/40 transition-colors">
                      <td class="px-5 py-4">
                        <p class="text-white font-medium">{{ formatPeriod(fs.period_from, fs.period_to) }}</p>
                        <p class="text-xs text-white mt-0.5">{{ fs.currency }}</p>
                      </td>
                      <td class="px-5 py-4 text-right">
                        <span :class="fs.revenue !== null ? 'text-white' : 'text-white'">
                          {{ fs.revenue !== null ? fmt(fs.revenue) : '—' }}
                        </span>
                      </td>
                      <td class="px-5 py-4 text-right">
                        <span :class="fs.gross_profit !== null ? (fs.gross_profit >= 0 ? 'text-mp-success' : 'text-mp-danger') : 'text-white'">
                          {{ fs.gross_profit !== null ? fmt(fs.gross_profit) : '—' }}
                        </span>
                        <p v-if="fs.gross_profit !== null && fs.revenue > 0" class="text-xs text-white mt-0.5">
                          {{ ((fs.gross_profit / fs.revenue) * 100).toFixed(1) }}%
                        </p>
                      </td>
                      <td class="px-5 py-4 text-right">
                        <span :class="fs.ebitda !== null ? (fs.ebitda >= 0 ? 'text-white' : 'text-mp-danger') : 'text-white'">
                          {{ fs.ebitda !== null ? fmt(fs.ebitda) : '—' }}
                        </span>
                        <p v-if="fs.ebitda !== null && fs.revenue > 0" class="text-xs text-white mt-0.5">
                          {{ ((fs.ebitda / fs.revenue) * 100).toFixed(1) }}%
                        </p>
                      </td>
                      <td class="px-5 py-4 text-right">
                        <span :class="fs.net_profit !== null ? (fs.net_profit >= 0 ? 'text-mp-success' : 'text-mp-danger') : 'text-white'">
                          {{ fs.net_profit !== null ? fmt(fs.net_profit) : '—' }}
                        </span>
                      </td>
                      <td class="px-5 py-4">
                        <span :class="fs.status === 'final' ? 'bg-mp-success/15 text-mp-success border-mp-success' : 'bg-mp-warning/15 text-mp-warning border-mp-warning'"
                          class="text-xs font-semibold px-2 py-0.5 rounded-full border capitalize">
                          {{ fs.status }}
                        </span>
                      </td>
                      <td class="px-5 py-4">
                        <Link :href="`/portfolio-companies/${company.id}/financial-statements/${fs.id}`"
                          class="text-xs text-white hover:text-white transition-colors">
                          View →
                        </Link>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Empty state -->
          <div v-else class="bg-mp-card rounded-xl border border-mp-border border-dashed p-10 text-center">
            <div class="w-12 h-12 bg-mp-card-hover rounded-xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <p class="text-white font-medium mb-1">No Financial Statements Yet</p>
            <p class="text-white text-sm mb-5">Add a financial statement to see P&L trends here</p>
            <Link :href="`/portfolio-companies/${company.id}/financial-statements/create`"
              class="inline-block bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
              + Add Financial Statement
            </Link>
          </div>
        </div>

        <!-- ────────────────────────────────────────
             SECTION 3: KPIs
        ──────────────────────────────────────── -->
        <div>
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">KPI Tracking</p>
            <Link :href="`/kpis?company=${company.id}`"
              class="text-xs text-white hover:text-white transition-colors">
              Manage KPIs →
            </Link>
          </div>

          <div v-if="kpiSummary && kpiSummary.length > 0">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
              <template v-for="kpi in kpiSummary" :key="kpi.kpi_name">
                <div class="bg-mp-card rounded-xl border border-mp-border p-4">
                  <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-white uppercase tracking-wide truncate pr-2">{{ kpi.kpi_name }}</p>
                    <span :class="kpiStatusClass(kpi.status)" class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0">
                      {{ kpiStatusLabel(kpi.status) }}
                    </span>
                  </div>
                  <p class="text-2xl font-bold text-white">
                    {{ kpi.actual_value !== null ? Number(kpi.actual_value).toLocaleString() : '—' }}
                    <span class="text-sm text-white font-normal">{{ kpi.unit }}</span>
                  </p>
                  <div v-if="kpi.target_value" class="mt-2">
                    <div class="flex items-center justify-between text-xs text-white mb-1">
                      <span>Target: {{ Number(kpi.target_value).toLocaleString() }}</span>
                      <span v-if="kpi.actual_value && kpi.target_value">
                        {{ ((kpi.actual_value / kpi.target_value) * 100).toFixed(0) }}%
                      </span>
                    </div>
                    <div class="h-1.5 bg-mp-card-hover rounded-full overflow-hidden">
                      <div :style="{ width: kpiProgressWidth(kpi) }"
                        :class="kpiProgressColor(kpi)"
                        class="h-full rounded-full transition-all duration-500">
                      </div>
                    </div>
                  </div>
                  <p class="text-xs text-white mt-2">{{ formatDate(kpi.period_date) }}</p>
                </div>
              </template>
            </div>
          </div>

          <div v-else class="bg-mp-card rounded-xl border border-mp-border border-dashed p-10 text-center">
            <div class="w-12 h-12 bg-mp-card-hover rounded-xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
            <p class="text-white font-medium mb-1">No KPIs Defined Yet</p>
            <p class="text-white text-sm mb-5">Configure KPI targets and track actuals against them</p>
            <Link :href="`/kpis?company=${company.id}`"
              class="inline-block bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
              + Configure KPIs
            </Link>
          </div>
        </div>

        <!-- ────────────────────────────────────────
             SECTION 4: BUDGET & VARIANCE
        ──────────────────────────────────────── -->
        <div>
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">Budget & Variance</p>
            <Link :href="`/portfolio-companies/${company.id}/budgets`"
              class="text-xs text-white hover:text-white transition-colors">
              View All Budgets →
            </Link>
          </div>

          <div v-if="budgetSummary">
            <div class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">

              <!-- Card header -->
              <div class="px-5 py-4 border-b border-mp-border flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div>
                    <p class="text-white font-semibold text-sm">{{ budgetSummary.name }}</p>
                    <p class="text-xs text-white mt-0.5">
                      FY {{ budgetSummary.year }} · {{ budgetSummary.currency }}
                      · YTD through month {{ budgetSummary.ytd_months }}
                    </p>
                  </div>
                  <span :class="budgetSummary.status === 'approved'
                      ? 'bg-mp-success/50 text-mp-success border-mp-success'
                      : 'bg-mp-warning/50 text-mp-warning border-mp-warning'"
                    class="text-xs font-semibold px-2 py-0.5 rounded-full border capitalize">
                    {{ budgetSummary.status }}
                  </span>
                </div>
                <Link :href="`/portfolio-companies/${company.id}/budgets/${budgetSummary.id}`"
                  class="text-xs bg-mp-teal hover:bg-mp-teal-dark text-white px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1">
                  Full Variance Report →
                </Link>
              </div>

              <!-- Column headers -->
              <div class="grid grid-cols-4 px-5 py-2 border-b border-mp-border bg-mp-card-hover/40">
                <span class="text-xs font-semibold text-white uppercase tracking-wider">Metric</span>
                <span class="text-xs font-semibold text-white uppercase tracking-wider text-right">YTD Budget</span>
                <span class="text-xs font-semibold text-mp-success uppercase tracking-wider text-right">YTD Actual</span>
                <span class="text-xs font-semibold text-mp-warning uppercase tracking-wider text-right">Variance</span>
              </div>

              <!-- 7 P&L rows -->
              <div class="divide-y divide-gray-800/60">
                <template v-for="row in budgetSummary.pl_variance" :key="row.key">
                  <div class="grid grid-cols-4 px-5 items-center"
                    :class="['gross_profit','ebitda','ebit','ebt','net_profit'].includes(row.key)
                      ? 'py-3 bg-mp-card-hover/25'
                      : 'py-2.5'">

                    <!-- Label -->
                    <span :class="['gross_profit','ebitda','ebit','ebt','net_profit'].includes(row.key)
                        ? 'text-sm font-bold text-mp-warning'
                        : 'text-sm text-white'">
                      {{ row.label }}
                    </span>

                    <!-- Budget -->
                    <span class="text-right text-sm font-semibold text-white tabular-nums">
                      {{ row.budget !== null ? fmtShort(row.budget) : '—' }}
                    </span>

                    <!-- Actual -->
                    <span class="text-right text-sm font-semibold tabular-nums"
                      :class="row.actual !== null ? 'text-mp-success' : 'text-white'">
                      {{ row.actual !== null ? fmtShort(row.actual) : '—' }}
                    </span>

                    <!-- Variance + % pill -->
                    <div class="flex items-center justify-end gap-2">
                      <span v-if="row.variance !== null"
                        class="text-sm font-bold tabular-nums"
                        :class="varGoodColor(row.variance, row.is_expense)">
                        {{ row.variance > 0 ? '+' : '' }}{{ fmtShort(row.variance) }}
                      </span>
                      <span v-else class="text-sm text-white">—</span>
                      <span v-if="row.var_pct !== null"
                        class="text-xs font-semibold px-1.5 py-0.5 rounded-full tabular-nums"
                        :class="varPillClass(row.variance, row.is_expense)">
                        {{ row.var_pct > 0 ? '+' : '' }}{{ row.var_pct }}%
                      </span>
                    </div>

                  </div>
                </template>
              </div>

            </div>
          </div>

          <div v-else class="bg-mp-card rounded-xl border border-mp-border border-dashed p-10 text-center">
            <div class="w-12 h-12 bg-mp-card-hover rounded-xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <p class="text-white font-medium mb-1">No Budget Created Yet</p>
            <p class="text-white text-sm mb-5">Create an annual budget to track actuals and variances</p>
            <Link :href="`/budgets/create?company=${company.id}`"
              class="inline-block bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
              + Create Budget
            </Link>
          </div>
        </div>

        <!-- ────────────────────────────────────────
             SECTION 5: CASH FORECAST
        ──────────────────────────────────────── -->
        <div>
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">Cash Flow Forecast</p>
            <Link :href="`/portfolio-companies/${company.id}/cash-forecast`"
              class="text-xs text-white hover:text-white transition-colors">
              Full Forecast →
            </Link>
          </div>

          <div v-if="cashForecast && cashForecast.length > 0">
            <div class="bg-mp-card rounded-xl border border-mp-border p-6">
              <!-- Sparkline chart -->
              <div class="mb-4">
                <svg :viewBox="`0 0 ${cashChartWidth} 80`" class="w-full h-20" preserveAspectRatio="none">
                  <!-- Zero line -->
                  <line x1="0" y1="40" :x2="cashChartWidth" y2="40" stroke="#1490a833" stroke-width="1" stroke-dasharray="4,4" />
                  <!-- Area fill -->
                  <path :d="cashAreaPath" fill="rgba(59,130,246,0.1)" />
                  <!-- Line -->
                  <path :d="cashLinePath" fill="none" stroke="#00b4c8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  <!-- Dots -->
                  <template v-for="(pt, i) in cashPoints" :key="i">
                    <circle :cx="pt.x" :cy="pt.y" r="3" :fill="pt.net >= 0 ? '#00b4c8' : '#ef4444'" />
                  </template>
                </svg>
              </div>
              <!-- Monthly values -->
              <div class="grid gap-2" :class="`grid-cols-${Math.min(cashForecast.length, 6)}`">
                <template v-for="cf in cashForecast.slice(0, 6)" :key="cf.month">
                  <div class="text-center">
                    <p class="text-xs text-white">{{ formatMonth(cf.month) }}</p>
                    <p class="text-sm font-semibold mt-0.5"
                      :class="cf.net >= 0 ? 'text-white' : 'text-mp-danger'">
                      {{ cf.net >= 0 ? '+' : '' }}{{ fmtShort(cf.net) }}
                    </p>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <div v-else class="bg-mp-card rounded-xl border border-mp-border border-dashed p-10 text-center">
            <div class="w-12 h-12 bg-mp-card-hover rounded-xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            </div>
            <p class="text-white font-medium mb-1">No Cash Forecast Data</p>
            <p class="text-white text-sm mb-5">Add forecast entries to visualise your cash position</p>
            <Link :href="`/portfolio-companies/${company.id}/cash-forecast`"
              class="inline-block bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors">
              + Add Forecast
            </Link>
          </div>
        </div>

        <!-- ────────────────────────────────────────
             SECTION 6: SALES ANALYSIS
        ──────────────────────────────────────── -->
        <div>
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">Sales Analysis</p>
            <Link :href="`/companies/${company.id}/sales`"
              class="text-xs text-white hover:text-white transition-colors">
              Full Dashboard →
            </Link>
          </div>

          <div v-if="salesKpis">

            <!-- ── Sales Update KPI Cards (exact same design as Dashboard.vue) ── -->
            <div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Sales Update</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                <!-- Last Month -->
                <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
                  <div class="absolute top-0 right-0 w-24 h-24 bg-mp-teal/5 rounded-full -translate-y-6 translate-x-6"></div>
                  <p class="text-xs font-semibold text-white uppercase tracking-widest mb-1">Last Month in Range</p>
                  <p class="text-xs text-white mb-2">{{ salesKpis.current_month_label }}</p>
                  <p class="text-3xl font-bold text-white">{{ fmt(salesKpis.current_month) }}</p>
                  <p class="text-xs text-white mt-1">Net Sales Value</p>
                  <div class="mt-3 flex items-center gap-2">
                    <span :class="salesKpis.current_month_gr >= 0 ? 'text-mp-success bg-mp-success/50' : 'text-mp-danger bg-mp-danger/50'"
                      class="text-xs font-semibold px-2 py-0.5 rounded-full">
                      {{ salesKpis.current_month_gr >= 0 ? '▲' : '▼' }} {{ Math.abs(salesKpis.current_month_gr) }}% vs prior month
                    </span>
                  </div>
                </div>

                <!-- Last 3 Months -->
                <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
                  <div class="absolute top-0 right-0 w-24 h-24 bg-mp-gold-dark/5 rounded-full -translate-y-6 translate-x-6"></div>
                  <p class="text-xs font-semibold text-white uppercase tracking-widest mb-1">Last 3 Months in Range</p>
                  <p class="text-xs text-white mb-2">{{ salesKpis.last_3_label }}</p>
                  <p class="text-3xl font-bold text-white">{{ fmt(salesKpis.last_3_months) }}</p>
                  <p class="text-xs text-white mt-1">Net Sales Value</p>
                  <div class="mt-3 flex items-center gap-2">
                    <span :class="salesKpis.last_3_months_gr >= 0 ? 'text-mp-success bg-mp-success/50' : 'text-mp-danger bg-mp-danger/50'"
                      class="text-xs font-semibold px-2 py-0.5 rounded-full">
                      {{ salesKpis.last_3_months_gr >= 0 ? '▲' : '▼' }} {{ Math.abs(salesKpis.last_3_months_gr) }}% vs prior 3M
                    </span>
                  </div>
                </div>

                <!-- Year to Date -->
                <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
                  <div class="absolute top-0 right-0 w-24 h-24 bg-mp-success/5 rounded-full -translate-y-6 translate-x-6"></div>
                  <p class="text-xs font-semibold text-mp-success uppercase tracking-widest mb-1">Year to Date</p>
                  <p class="text-xs text-white mb-2">{{ salesKpis.ytd_label }}</p>
                  <p class="text-3xl font-bold text-white">{{ fmt(salesKpis.ytd) }}</p>
                  <p class="text-xs text-white mt-1">Net Sales Value</p>
                  <div class="mt-3 flex items-center gap-2">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full text-white bg-mp-teal-subtle/50">
                      {{ salesKpis.avg_monthly > 0 ? fmt(salesKpis.avg_monthly) + ' / mo avg' : '—' }}
                    </span>
                  </div>
                </div>

                <!-- Avg Transaction Value -->
                <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
                  <div class="absolute top-0 right-0 w-24 h-24 bg-mp-gold-dark/5 rounded-full -translate-y-6 translate-x-6"></div>
                  <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Avg Transaction Value</p>
                  <p class="text-3xl font-bold text-white">{{ fmt(salesKpis.avg_transaction) }}</p>
                  <p class="text-xs text-white mt-1">Net Sales ÷ Invoice Count</p>
                  <div class="mt-3">
                    <span class="text-xs text-white">{{ salesKpis.total_invoices?.toLocaleString() }} total invoices</span>
                  </div>
                </div>

                <!-- Active Customers -->
                <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
                  <div class="absolute top-0 right-0 w-24 h-24 bg-mp-teal/5 rounded-full -translate-y-6 translate-x-6"></div>
                  <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Active Customers</p>
                  <p class="text-3xl font-bold text-white">{{ salesKpis.active_customers?.toLocaleString() ?? '—' }}</p>
                  <p class="text-xs text-white mt-1">Unique customers in period</p>
                  <div class="mt-3">
                    <span class="text-xs text-white">{{ salesSummary?.product_count }} products</span>
                  </div>
                </div>

                <!-- Best Month -->
                <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
                  <div class="absolute top-0 right-0 w-24 h-24 bg-mp-danger/5 rounded-full -translate-y-6 translate-x-6"></div>
                  <p class="text-xs font-semibold text-mp-danger uppercase tracking-widest mb-3">Best Month</p>
                  <p class="text-3xl font-bold text-white">{{ salesKpis.best_month_label ?? '—' }}</p>
                  <p class="text-xs text-white mt-1">{{ fmt(salesKpis.best_month_value) }}</p>
                  <div class="mt-3">
                    <span class="text-xs text-white">Worst: {{ salesKpis.worst_month_label ?? '—' }}</span>
                  </div>
                </div>

              </div>
            </div>

            <!-- ── Top Achievers (exact same design as Dashboard.vue) ── -->
            <div v-if="salesTopAchievers && salesTopAchievers.length > 0" class="mt-6">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Top Achievers</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="achiever in salesTopAchievers" :key="achiever.field"
                  class="bg-mp-card border border-mp-border rounded-xl p-5 hover:border-mp-border transition-all">
                  <div class="flex items-start justify-between mb-3">
                    <p class="text-xs font-semibold text-white uppercase tracking-widest">Top {{ achiever.label }}</p>
                    <Link :href="`/companies/${company.id}/sales`"
                      class="flex items-center gap-1 text-xs font-semibold bg-mp-teal/20 hover:bg-mp-teal/40 text-white px-2.5 py-1 rounded-lg transition-colors">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                      Details
                    </Link>
                  </div>
                  <p class="text-lg font-bold text-white truncate">{{ achiever.top_label }}</p>
                  <p class="text-2xl font-bold text-mp-success mt-1">{{ fmt(achiever.top_value) }}</p>
                  <!-- Progress bar -->
                  <div class="mt-3 w-full h-1.5 bg-mp-card-hover rounded-full overflow-hidden">
                    <div class="h-full rounded-full"
                      style="width:100%; background: linear-gradient(90deg, #00b4c8, #10b981)">
                    </div>
                  </div>
                  <p class="text-xs text-white mt-1.5">{{ achiever.total_items }} total {{ achiever.label.toLowerCase() }}s</p>
                </div>
              </div>
            </div>

          </div>

          <div v-else class="bg-mp-card rounded-xl border border-mp-border border-dashed p-8 text-center">
            <p class="text-white font-medium mb-1">No Sales Data Uploaded</p>
            <p class="text-white text-sm mb-4">Upload sales data to see revenue analytics here</p>
            <Link :href="`/companies/${company.id}/sales`"
              class="inline-block bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
              Go to Sales Analysis →
            </Link>
          </div>
        </div>

        <!-- ────────────────────────────────────────
             SECTION 7: EXPENSE ANALYSIS
        ──────────────────────────────────────── -->
        <div>
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">Expense Analysis</p>
            <Link :href="`/companies/${company.id}/expenses`"
              class="text-xs text-white hover:text-white transition-colors">
              Full Dashboard →
            </Link>
          </div>

          <!-- ── Has Data ── -->
          <div v-if="expenseKpis" class="space-y-5">

            <!-- Period label -->
            <p class="text-xs text-white">{{ expenseKpis.period_label }}</p>

            <!-- 6 KPI Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">

              <!-- Total Expenses -->
              <div class="bg-mp-card rounded-xl border border-mp-border p-4">
                <p class="text-xs text-white uppercase tracking-widest mb-1">Total Expenses</p>
                <p class="text-lg font-bold text-white tabular-nums">{{ fmtShort(expenseKpis.total_expense) }}</p>
              </div>

              <!-- Total Revenue -->
              <div class="bg-mp-card rounded-xl border border-mp-border p-4">
                <p class="text-xs text-white uppercase tracking-widest mb-1">Revenue (Period)</p>
                <p class="text-lg font-bold text-white tabular-nums">{{ fmtShort(expenseKpis.total_revenue) }}</p>
              </div>

              <!-- Exp / Revenue % — colour-coded -->
              <div class="bg-mp-card rounded-xl border border-mp-border p-4">
                <p class="text-xs text-white uppercase tracking-widest mb-1">Exp / Revenue</p>
                <p class="text-lg font-bold tabular-nums"
                  :class="expenseKpis.expense_to_rev > 80 ? 'text-mp-danger' : expenseKpis.expense_to_rev > 60 ? 'text-mp-warning' : 'text-mp-success'">
                  {{ expenseKpis.expense_to_rev }}%
                </p>
              </div>

              <!-- Avg Monthly -->
              <div class="bg-mp-card rounded-xl border border-mp-border p-4">
                <p class="text-xs text-white uppercase tracking-widest mb-1">Avg Monthly</p>
                <p class="text-lg font-bold text-white tabular-nums">{{ fmtShort(expenseKpis.avg_monthly) }}</p>
              </div>

              <!-- Categories -->
              <div class="bg-mp-card rounded-xl border border-mp-border p-4">
                <p class="text-xs text-white uppercase tracking-widest mb-1">Categories</p>
                <p class="text-lg font-bold text-white tabular-nums">{{ expenseKpis.category_count }}</p>
              </div>

              <!-- Expense Items -->
              <div class="bg-mp-card rounded-xl border border-mp-border p-4">
                <p class="text-xs text-white uppercase tracking-widest mb-1">Expense Items</p>
                <p class="text-lg font-bold text-white tabular-nums">{{ expenseKpis.item_count }}</p>
              </div>

            </div>

            <!-- Top 10 Expense Items -->
            <div v-if="expenseTopItems && expenseTopItems.length > 0"
              class="bg-mp-card rounded-xl border border-mp-border p-5">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Top Expense Items</p>
              <div class="space-y-2.5">
                <div v-for="(item, i) in expenseTopItems" :key="i" class="flex items-center gap-3">
                  <!-- Label -->
                  <div class="w-40 flex-shrink-0 text-right">
                    <p class="text-xs text-white truncate">{{ item.category }}</p>
                    <p class="text-xs text-white font-medium truncate">{{ item.item }}</p>
                  </div>
                  <!-- Bar -->
                  <div class="flex-1 bg-mp-card-hover rounded-full h-4 relative">
                    <div class="bg-gradient-to-r from-mp-teal to-mp-teal h-4 rounded-full"
                      :style="`width:${Math.max(item.pct, 2)}%`">
                    </div>
                  </div>
                  <!-- Value + % -->
                  <div class="w-28 flex-shrink-0 flex items-center justify-between gap-1">
                    <span class="text-xs text-white tabular-nums">{{ item.pct }}%</span>
                    <span class="text-xs text-white font-semibold tabular-nums">{{ fmtShort(item.total) }}</span>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- ── No Data ── -->
          <div v-else class="bg-mp-card rounded-xl border border-mp-border border-dashed p-8 text-center">
            <p class="text-white font-medium mb-1">No Expense Data Uploaded</p>
            <p class="text-white text-sm mb-4">Upload expense data to see cost analytics here</p>
            <Link :href="`/companies/${company.id}/expenses/upload`"
              class="inline-block bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
              ↑ Upload Expenses
            </Link>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="company.notes">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Notes</p>
          <div class="bg-mp-card rounded-xl border border-mp-border p-6">
            <p class="text-white text-sm leading-relaxed whitespace-pre-line">{{ company.notes }}</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-xs text-white pb-4">
          Last financial update: {{ company.last_financial_update ? formatDate(company.last_financial_update) : 'Not recorded' }}
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:           { type: Object, default: () => ({ id: null, name: '' }) },
  canEdit:           { type: Boolean, default: false },
  fsData:            { type: Array,  default: () => [] },
  ebitdaValuation:   { type: Number, default: null },
  kpiSummary:        { type: Array,  default: () => [] },
  budgetSummary:     { type: Object, default: null },
  cashForecast:      { type: Array,  default: () => [] },
  salesSummary:      { type: Object, default: null },
  salesKpis:         { type: Object, default: null },
  salesTopAchievers: { type: Array,  default: () => [] },
  expenseSummary:    { type: Object, default: null },
  expenseKpis:       { type: Object, default: null },
  expenseTopItems:   { type: Array,  default: () => [] },
  profitSummary:     { type: Object, default: null },
})

// ── Currency toggle ──
const currency = ref('local')
const activeCurrency = computed(() =>
  currency.value === 'local' ? props.company.invested_currency : props.company.fx_currency
)

function convertedVal(val) {
  if (!val) return null
  if (currency.value === 'fx' && props.company.fx_rate) {
    return val / props.company.fx_rate
  }
  return val
}

// ── Quick nav links ──
const quickNav = computed(() => [
  { label: 'Financials',    href: `/portfolio-companies/${props.company.id}/financial-statements` },
  { label: 'Budget',        href: `/portfolio-companies/${props.company.id}/budgets` },
  { label: 'Cash Forecast', href: `/portfolio-companies/${props.company.id}/cash-forecast` },
  { label: 'Sales',         href: `/companies/${props.company.id}/sales` },
  { label: 'Expenses',      href: `/companies/${props.company.id}/expenses` },
  { label: 'Profitability', href: `/companies/${props.company.id}/profitability` },
  { label: 'KPIs',          href: `/portfolio-companies/${props.company.id}/kpi` },
])

// ── Equity as % ──
const equityPercent = computed(() => (props.company.equity_stake * 100).toFixed(2))

// ── Uplift % (current vs entry valuation) ──
const upliftPct = computed(() => {
  const c = props.company.current_valuation
  const e = props.company.entry_valuation
  if (!c || !e || e === 0) return null
  const pct = ((c - e) / e * 100).toFixed(1)
  return (pct >= 0 ? '+' : '') + pct + '%'
})
const upliftClass = computed(() => {
  const c = props.company.current_valuation
  const e = props.company.entry_valuation
  if (!c || !e) return ''
  return c >= e
    ? 'bg-mp-success/15 text-mp-success border border-mp-success'
    : 'bg-mp-danger/15 text-mp-danger border border-mp-danger'
})

// ── Status badge ──
const statusBadgeClass = computed(() => {
  const map = {
    on_track: 'bg-mp-success/15 text-mp-success border border-mp-success',
    at_risk:  'bg-mp-danger/15 text-mp-danger border border-mp-danger',
    watch:    'bg-mp-warning/15 text-mp-warning border border-mp-warning',
  }
  return map[props.company.status] || 'bg-mp-card-hover text-white'
})
const statusLabel = computed(() => {
  const map = { on_track: 'On Track', at_risk: 'At Risk', watch: 'Watch' }
  return map[props.company.status] || props.company.status
})

// ── Budget % ──
const budgetPct = computed(() => {
  if (!props.budgetSummary || !props.budgetSummary.total_budget) return 0
  return Math.round((props.budgetSummary.total_actual / props.budgetSummary.total_budget) * 100)
})

// ── Expense ratio ──
const expenseRatioPct = computed(() => {
  if (!props.expenseSummary || !props.salesSummary || !props.salesSummary.total_revenue) return 0
  return Math.round((props.expenseSummary.total_expenses / props.salesSummary.total_revenue) * 100)
})

// ── KPI helpers ──
function kpiStatusClass(status) {
  const map = {
    on_track: 'bg-mp-success/50 text-mp-success',
    at_risk:  'bg-mp-danger/50 text-mp-danger',
    watch:    'bg-mp-warning/50 text-mp-warning',
  }
  return map[status] || 'bg-mp-card-hover text-white'
}
function kpiStatusLabel(status) {
  const map = { on_track: '✓ On Track', at_risk: '✗ At Risk', watch: '⚠ Watch' }
  return map[status] || status
}
function kpiProgressWidth(kpi) {
  if (!kpi.actual_value || !kpi.target_value) return '0%'
  return Math.min((kpi.actual_value / kpi.target_value) * 100, 100) + '%'
}
function kpiProgressColor(kpi) {
  if (!kpi.actual_value || !kpi.target_value) return 'bg-mp-muted'
  const ratio = kpi.actual_value / kpi.target_value
  if (ratio >= 1) return 'bg-mp-success'
  if (ratio >= 0.75) return 'bg-mp-teal'
  return 'bg-mp-danger'
}

// ── Budget variance color helpers ──
function varGoodColor(variance, isExpense) {
  if (variance === null || variance === 0) return 'text-white'
  const good = isExpense ? variance < 0 : variance > 0
  return good ? 'text-mp-success' : 'text-mp-danger'
}
function varPillClass(variance, isExpense) {
  if (variance === null || variance === 0) return 'bg-mp-card-hover text-white'
  const good = isExpense ? variance < 0 : variance > 0
  return good
    ? 'bg-mp-success/50 text-mp-success border border-mp-success/50'
    : 'bg-mp-danger/50 text-mp-danger border border-mp-danger/50'
}

// ── Cash chart ──
const cashChartWidth = 600
const cashPoints = computed(() => {
  const data = props.cashForecast
  if (!data || data.length === 0) return []
  const vals = data.map(d => d.net)
  const min = Math.min(...vals, 0)
  const max = Math.max(...vals, 0)
  const range = max - min || 1
  const step = cashChartWidth / Math.max(data.length - 1, 1)
  return data.map((d, i) => ({
    x: i * step,
    y: 80 - ((d.net - min) / range) * 70,
    net: d.net,
  }))
})
const cashLinePath = computed(() => {
  const pts = cashPoints.value
  if (!pts.length) return ''
  return pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ')
})
const cashAreaPath = computed(() => {
  const pts = cashPoints.value
  if (!pts.length) return ''
  const vals = props.cashForecast.map(d => d.net)
  const min = Math.min(...vals, 0)
  const max = Math.max(...vals, 0)
  const range = max - min || 1
  const zeroY = 80 - ((0 - min) / range) * 70
  const last = pts[pts.length - 1]
  const first = pts[0]
  return pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x},${p.y}`).join(' ') +
    ` L${last.x},${zeroY} L${first.x},${zeroY} Z`
})

// ── Formatters ──
function fmt(val) {
  if (val === null || val === undefined) return '—'
  return Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}
function fmtShort(val) {
  if (val === null || val === undefined) return '—'
  const abs = Math.abs(val)
  const sign = val < 0 ? '-' : ''
  if (abs >= 1_000_000) return sign + (abs / 1_000_000).toFixed(1) + 'M'
  if (abs >= 1_000) return sign + (abs / 1_000).toFixed(0) + 'K'
  return sign + abs.toLocaleString()
}
function formatDate(dateStr) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
function formatPeriod(from, to) {
  if (!from && !to) return '—'
  const f = from ? new Date(from).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }) : '?'
  const t = to   ? new Date(to).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }) : '?'
  return `${f} — ${t}`
}
function formatMonth(monthStr) {
  if (!monthStr) return ''
  const [y, m] = monthStr.split('-')
  const names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
  return (names[parseInt(m) - 1] || m) + ' ' + y.slice(2)
}
</script>