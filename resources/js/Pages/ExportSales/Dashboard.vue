<template>
  <Head :title="`Export Sales Dashboard — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-mp-text-secondary">

      <!-- ── HEADER ── -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-1 sm:px-6 lg:px-2 py-3">
          <Link :href="`/portfolio-companies/${company.id}`"
            class="flex items-center gap-2 text-sm text-mp-muted hover:text-mp-text-secondary transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to {{ company.name }}
          </Link>
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-mp-text-secondary">Export Sales Dashboard</h1>
                <span class="text-xs font-bold bg-mp-success text-mp-text-secondary px-2.5 py-1 rounded-full uppercase tracking-widest">Export</span>
              </div>
              <p class="text-mp-muted text-sm mt-0.5">{{ company.name }} — Export trade intelligence</p>
              <!-- Date Range -->
              <div class="flex items-center gap-2 mt-2 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2">
                <svg class="w-4 h-4 text-mp-success flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <input v-model="dateFrom" type="date" @change="loadDashboard"
                  class="bg-transparent text-mp-text-secondary text-sm focus:outline-none w-32" />
                <span class="text-mp-muted text-xs">→</span>
                <input v-model="dateTo" type="date" @change="loadDashboard"
                  class="bg-transparent text-mp-text-secondary text-sm focus:outline-none w-32" />
              </div>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-3 flex-wrap">
              <Link :href="`/companies/${company.id}/export-sales/reports`"
                class="flex items-center gap-2 bg-mp-success hover:bg-mp-success text-mp-text-secondary text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Full Reports
              </Link>
              <Link :href="`/companies/${company.id}/sales`"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-mp-text-secondary text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Total Sales Dashboard
              </Link>
              <Link :href="`/companies/${company.id}/expenses`"
                class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm bg-mp-gold hover:bg-mp-gold-dark text-mp-text-secondary font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                Expense
              </Link>
              <Link :href="`/companies/${company.id}/export-sales/upload`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-mp-text text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                Upload Data
              </Link>
              <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-mp-text-secondary text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Financial Statements
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading overlay -->
      <div v-if="loading" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <svg class="animate-spin w-10 h-10 text-mp-success mx-auto mb-4" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        <p class="text-mp-muted text-sm">Loading dashboard data...</p>
      </div>

      <div v-else class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">

        <!-- ══════════════════════════════════════════
             SECTION 1 — EXPORT KPI CARDS
        ═══════════════════════════════════════════ -->
        <div>
          <p class="text-xs font-semibold text-mp-success uppercase tracking-widest mb-4">Export Performance Update</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <!-- Card: Current Month -->
            <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
              <div class="absolute top-0 right-0 w-24 h-24 bg-mp-success/5 rounded-full -translate-y-6 translate-x-6"></div>
              <p class="text-xs font-semibold text-mp-success uppercase tracking-widest mb-1">Last Month in Range</p>
              <p class="text-xs text-mp-muted mb-2">{{ kpis.current_month_label }}</p>
              <p class="text-3xl font-bold text-mp-text-secondary">{{ fmt(kpis.current_month) }}</p>
              <p class="text-xs text-mp-muted mt-1">PO Net Value</p>
              <div class="mt-3 flex items-center gap-2">
                <span :class="kpis.current_month_gr >= 0 ? 'text-mp-success bg-mp-success/50' : 'text-mp-danger bg-mp-danger/50'"
                  class="text-xs font-semibold px-2 py-0.5 rounded-full">
                  {{ kpis.current_month_gr >= 0 ? '▲' : '▼' }} {{ Math.abs(kpis.current_month_gr) }}% vs prior month
                </span>
              </div>
            </div>

            <!-- Card: Last 3 Months -->
            <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
              <div class="absolute top-0 right-0 w-24 h-24 bg-mp-teal-subtle/5 rounded-full -translate-y-6 translate-x-6"></div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-1">Last 3 Months in Range</p>
              <p class="text-xs text-mp-muted mb-2">{{ kpis.last_3_label }}</p>
              <p class="text-3xl font-bold text-mp-text-secondary">{{ fmt(kpis.last_3_months) }}</p>
              <p class="text-xs text-mp-muted mt-1">PO Net Value</p>
              <div class="mt-3 flex items-center gap-2">
                <span :class="kpis.last_3_months_gr >= 0 ? 'text-mp-success bg-mp-success/50' : 'text-mp-danger bg-mp-danger/50'"
                  class="text-xs font-semibold px-2 py-0.5 rounded-full">
                  {{ kpis.last_3_months_gr >= 0 ? '▲' : '▼' }} {{ Math.abs(kpis.last_3_months_gr) }}% vs prior 3M
                </span>
              </div>
            </div>

            <!-- Card: Year to Date -->
            <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
              <div class="absolute top-0 right-0 w-24 h-24 bg-mp-teal/5 rounded-full -translate-y-6 translate-x-6"></div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-1">Year to Date</p>
              <p class="text-xs text-mp-muted mb-2">{{ kpis.ytd_label }}</p>
              <p class="text-3xl font-bold text-mp-text-secondary">{{ fmt(kpis.ytd) }}</p>
              <p class="text-xs text-mp-muted mt-1">PO Net Value</p>
              <div class="mt-3 flex items-center gap-2">
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full text-white bg-mp-success/50">
                  {{ kpis.avg_monthly > 0 ? fmt(kpis.avg_monthly) + ' / mo avg' : '—' }}
                </span>
              </div>
            </div>

            <!-- Card: Avg Order Value -->
            <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
              <div class="absolute top-0 right-0 w-24 h-24 bg-mp-gold-dark/5 rounded-full -translate-y-6 translate-x-6"></div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Avg Order Value</p>
              <p class="text-3xl font-bold text-mp-text-secondary">{{ fmt(kpis.avg_order_value) }}</p>
              <p class="text-xs text-mp-muted mt-1">PO Net Value ÷ PO Count</p>
              <div class="mt-3">
                <span class="text-xs text-mp-muted">{{ kpis.total_orders?.toLocaleString() }} total orders</span>
              </div>
            </div>

            <!-- Card: Destination Countries -->
            <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
              <div class="absolute top-0 right-0 w-24 h-24 bg-mp-teal/5 rounded-full -translate-y-6 translate-x-6"></div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Destination Markets</p>
              <p class="text-3xl font-bold text-mp-text-secondary">{{ kpis.active_destinations?.toLocaleString() ?? '—' }}</p>
              <p class="text-xs text-mp-muted mt-1">Active countries in period</p>
              <div class="mt-3">
                <span class="text-xs text-mp-muted">{{ kpis.total_destinations?.toLocaleString() }} markets ever reached</span>
              </div>
            </div>

            <!-- Card: Best Month -->
            <div class="bg-mp-card border border-mp-border rounded-xl p-5 relative overflow-hidden">
              <div class="absolute top-0 right-0 w-24 h-24 bg-mp-danger/5 rounded-full -translate-y-6 translate-x-6"></div>
              <p class="text-xs font-semibold text-mp-danger uppercase tracking-widest mb-3">Best Shipment Month</p>
              <p class="text-3xl font-bold text-mp-text-secondary">{{ kpis.best_month_label ?? '—' }}</p>
              <p class="text-xs text-mp-muted mt-1">{{ fmt(kpis.best_month_value) }}</p>
              <div class="mt-3">
                <span class="text-xs text-mp-muted">Worst: {{ kpis.worst_month_label ?? '—' }}</span>
                <span v-if="kpis.total_fcl" class="ml-3 text-xs text-mp-success font-semibold">
                  {{ kpis.total_fcl?.toLocaleString() }} FCL total
                </span>
              </div>
            </div>

          </div>

          <!-- Monthly Trend Line Chart -->
          <div class="mt-4 bg-mp-card border border-mp-border rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
              <p class="text-xs font-semibold text-mp-success uppercase tracking-widest">Monthly Export Value Trend</p>
              <div class="flex items-center gap-4 text-xs text-mp-muted">
                <span class="flex items-center gap-1.5"><span class="w-3 h-0.5 bg-mp-success inline-block rounded"></span> PO Net Value</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-0.5 bg-mp-teal inline-block rounded"></span> MoM Growth %</span>
              </div>
            </div>
            <div style="height:280px">
              <canvas ref="trendChartCanvas"></canvas>
            </div>
          </div>
        </div>

        <!-- ══════════════════════════════════════════
             SECTION 2 — PO STATUS SUMMARY
        ═══════════════════════════════════════════ -->
        <div v-if="poStatus.length > 0">
          <p class="text-xs font-semibold text-mp-success uppercase tracking-widest mb-4">PO Status Summary</p>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div v-for="(po, i) in poStatus" :key="i"
              class="bg-mp-card border border-mp-border rounded-xl p-4 hover:border-mp-success/60 transition-colors">
              <p class="text-xs font-semibold uppercase tracking-widest mb-2" :class="poStatusColor(po.status)">
                {{ po.status }}
              </p>
              <p class="text-2xl font-bold text-mp-text-secondary">{{ po.order_count }}</p>
              <p class="text-xs text-mp-muted mt-1">orders</p>
              <p class="text-sm font-semibold text-mp-success mt-2">{{ fmt(po.total_value) }}</p>
              <p class="text-xs text-mp-muted mt-0.5">{{ po.pct }}% of total</p>
            </div>
          </div>
        </div>

        <!-- ══════════════════════════════════════════
             SECTION 3 — SALES BREAKDOWN ANALYSIS
        ═══════════════════════════════════════════ -->
        <div v-if="breakdowns.length > 0">
          <p class="text-xs font-semibold text-mp-success uppercase tracking-widest mb-4">Export Breakdown Analysis</p>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div v-for="(bd, idx) in breakdowns" :key="bd.field"
              class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
              <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between">
                <p class="text-sm font-semibold text-mp-text-secondary">{{ bd.label }} Breakdown</p>
                <div class="flex items-center gap-2">
                  <!-- Export button -->
                  <button @click="exportBreakdown(bd)"
                    class="flex items-center gap-1.5 text-xs font-medium bg-mp-success/30 hover:bg-mp-success/60 text-mp-success px-3 py-1.5 rounded-lg transition-colors border border-mp-success/50">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export
                  </button>
                  <!-- Tab switcher -->
                  <div class="flex items-center bg-mp-card-hover rounded-lg p-0.5">
                    <button @click="onTabChange(bd, idx)"
                      :class="bd.tab === 'chart' ? 'bg-mp-page text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text'"
                      class="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-md transition-all">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                      </svg>
                      Chart
                    </button>
                    <button @click="bd.tab = 'table'"
                      :class="bd.tab === 'table' ? 'bg-mp-page text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text'"
                      class="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-md transition-all">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 4v16M6 4h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2z" />
                      </svg>
                      Table
                    </button>
                  </div>
                </div>
              </div>

              <!-- Chart view -->
              <div v-if="bd.tab === 'chart'" class="p-6">
                <DonutChart3D :data="bd.rows" label-key="label" value-key="value" :height="300" />
              </div>

              <!-- Table view -->
              <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b border-mp-border">
                      <th class="text-left text-xs font-semibold text-mp-success uppercase px-5 py-3">{{ bd.label }}</th>
                      <th class="text-right text-xs font-semibold text-mp-success uppercase px-5 py-3">PO Net Value</th>
                      <th class="text-right text-xs font-semibold text-mp-success uppercase px-5 py-3">% Share</th>
                      <th class="text-right text-xs font-semibold text-mp-success uppercase px-5 py-3">Accum %</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-800">
                    <tr v-for="(row, ri) in bd.rows" :key="ri" class="hover:bg-mp-card-hover/50 transition-colors">
                      <td class="px-5 py-2.5 text-mp-text-secondary text-sm">{{ row.label }}</td>
                      <td class="px-5 py-2.5 text-right text-mp-success font-semibold text-sm">{{ fmt(row.value) }}</td>
                      <td class="px-5 py-2.5 text-right text-mp-muted text-xs">{{ row.pct }}%</td>
                      <td class="px-5 py-2.5 text-right text-xs">
                        <span class="text-mp-success font-semibold">{{ row.accum }}%</span>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr class="border-t-2 border-mp-border bg-mp-card-hover/50">
                      <td class="px-5 py-2.5 text-mp-text-secondary font-bold text-sm">Total</td>
                      <td class="px-5 py-2.5 text-right text-mp-text-secondary font-bold text-sm">{{ fmt(bd.rows.reduce((s,r) => s + r.value, 0)) }}</td>
                      <td class="px-5 py-2.5 text-right text-mp-muted text-xs">100%</td>
                      <td class="px-5 py-2.5 text-right text-mp-success font-bold text-xs">100%</td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- ══════════════════════════════════════════
             SECTION 4 — TOP ACHIEVERS
        ═══════════════════════════════════════════ -->
        <div v-if="topAchievers.length > 0">
          <p class="text-xs font-semibold text-mp-success uppercase tracking-widest mb-4">Top Export Performers</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="achiever in topAchievers" :key="achiever.field"
              class="bg-mp-card border border-mp-border rounded-xl p-5 hover:border-mp-success/50 transition-all">
              <div class="flex items-start justify-between mb-3">
                <p class="text-xs font-semibold text-mp-muted uppercase tracking-widest">Top {{ achiever.label }}</p>
                <button @click="openTakeaway(achiever)"
                  class="flex items-center gap-1 text-xs font-semibold bg-mp-success/20 hover:bg-mp-success/40 text-mp-success px-2.5 py-1 rounded-lg transition-colors">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Takeaways
                </button>
              </div>
              <p class="text-lg font-bold text-mp-text-secondary truncate">{{ achiever.top_label }}</p>
              <p class="text-2xl font-bold text-mp-success mt-1">{{ fmt(achiever.top_value) }}</p>
              <div class="mt-3 w-full h-1.5 bg-mp-card-hover rounded-full overflow-hidden">
                <div class="h-full rounded-full"
                  style="width:100%; background: linear-gradient(90deg, #00b4c8, #10b981)">
                </div>
              </div>
              <p class="text-xs text-mp-muted mt-1.5">{{ achiever.total_items }} total {{ achiever.label.toLowerCase() }}s</p>
            </div>
          </div>
        </div>

        <!-- ══════════════════════════════════════════
             SECTION 5 — AUTO INSIGHTS & ALERTS
        ═══════════════════════════════════════════ -->
        <div v-if="insights.length > 0">
          <p class="text-xs font-semibold text-mp-success uppercase tracking-widest mb-4">
            Auto Insights & Alerts
          </p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div v-for="(insight, i) in insights" :key="i"
              :class="{
                'bg-mp-success/30 border-mp-success/60':   insight.type === 'positive',
                'bg-mp-warning/30 border-mp-warning/60': insight.type === 'warning',
                'bg-mp-danger/30 border-mp-danger/60':       insight.type === 'danger',
              }"
              class="rounded-xl border p-4 flex gap-3">
              <span class="text-2xl flex-shrink-0 mt-0.5">{{ insight.icon }}</span>
              <div>
                <p class="text-sm font-semibold text-mp-text-secondary">{{ insight.title }}</p>
                <p class="text-xs text-mp-text-secondary mt-1 leading-relaxed">{{ insight.body }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ══════════════════════════════════════════
             SECTION 6 — ANALYST NOTES (Rich Text)
        ═══════════════════════════════════════════ -->
        <div>
          <p class="text-xs font-semibold text-mp-success uppercase tracking-widest mb-4">
            Analyst Notes
            <span class="text-mp-muted normal-case font-normal ml-2">
              Saved per date range — {{ dateFrom }} → {{ dateTo }}
            </span>
          </p>
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">

            <!-- Saved Notes List -->
            <div v-if="notes.length > 0" class="divide-y divide-gray-800">
              <div v-for="(n, i) in notes" :key="n.id ?? i" class="p-5">
                <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-mp-success flex items-center justify-center text-xs font-bold text-mp-text-secondary flex-shrink-0">
                      {{ (n.author ?? 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <p class="text-sm font-semibold text-mp-text-secondary">{{ n.author }}</p>
                      <p class="text-xs text-mp-muted">{{ n.updated_at ? new Date(n.updated_at).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '' }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <button @click="startEdit(n)"
                      class="flex items-center gap-1 text-xs text-mp-success hover:text-mp-success bg-mp-success/40 hover:bg-mp-success/70 px-3 py-1.5 rounded-lg transition-colors">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                      Edit
                    </button>
                    <button @click="deleteNote(n.id)"
                      class="flex items-center gap-1 text-xs text-mp-danger hover:text-mp-danger bg-mp-danger/40 hover:bg-mp-danger/70 px-3 py-1.5 rounded-lg transition-colors">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                      Delete
                    </button>
                  </div>
                </div>
                <div class="prose-dark text-sm text-mp-text leading-relaxed" v-html="n.note"></div>
              </div>
            </div>
            <div v-else class="px-6 py-4 text-xs text-mp-text-secondary border-b border-mp-border">
              No notes saved for this date range yet. Write one below.
            </div>

            <!-- Rich Text Editor -->
            <div class="p-5">
              <p class="text-xs font-semibold text-mp-muted uppercase tracking-widest mb-3">
                {{ editingNoteId ? '✏️ Editing Note' : '+ New Note' }}
                <button v-if="editingNoteId" @click="cancelEdit"
                  class="ml-3 text-mp-muted hover:text-mp-muted normal-case font-normal">Cancel</button>
              </p>

              <!-- Toolbar -->
              <div class="flex flex-wrap items-center gap-1 bg-mp-card-hover border border-mp-border rounded-t-lg px-3 py-2">
                <div class="flex items-center gap-0.5 pr-2 border-r border-mp-border">
                  <button @click="editorCmd('bold')" :class="isActive('bold') ? 'bg-mp-success text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page'"
                    class="w-7 h-7 rounded flex items-center justify-center text-sm font-bold transition-colors">B</button>
                  <button @click="editorCmd('italic')" :class="isActive('italic') ? 'bg-mp-success text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page'"
                    class="w-7 h-7 rounded flex items-center justify-center text-sm italic transition-colors">I</button>
                  <button @click="editorCmd('underline')" :class="isActive('underline') ? 'bg-mp-success text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page'"
                    class="w-7 h-7 rounded flex items-center justify-center text-sm underline transition-colors">U</button>
                  <button @click="editorCmd('strikethrough')" :class="isActive('strikethrough') ? 'bg-mp-success text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page'"
                    class="w-7 h-7 rounded flex items-center justify-center text-sm line-through transition-colors">S</button>
                </div>
                <div class="flex items-center gap-0.5 px-2 border-r border-mp-border">
                  <button @click="editorCmd('h1')" :class="isActive('h1') ? 'bg-mp-success text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page'"
                    class="px-2 h-7 rounded text-xs font-bold transition-colors">H1</button>
                  <button @click="editorCmd('h2')" :class="isActive('h2') ? 'bg-mp-success text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page'"
                    class="px-2 h-7 rounded text-xs font-bold transition-colors">H2</button>
                  <button @click="editorCmd('h3')" :class="isActive('h3') ? 'bg-mp-success text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page'"
                    class="px-2 h-7 rounded text-xs font-bold transition-colors">H3</button>
                </div>
                <div class="flex items-center gap-0.5 px-2 border-r border-mp-border">
                  <button @click="editorCmd('bullet')" :class="isActive('bullet') ? 'bg-mp-success text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page'"
                    class="w-7 h-7 rounded flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                  </button>
                  <button @click="editorCmd('ordered')" :class="isActive('ordered') ? 'bg-mp-success text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page'"
                    class="w-7 h-7 rounded flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h10M7 16h10M3 8h.01M3 12h.01M3 16h.01"/></svg>
                  </button>
                  <button @click="editorCmd('blockquote')" :class="isActive('blockquote') ? 'bg-mp-success text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page'"
                    class="w-7 h-7 rounded flex items-center justify-center text-lg font-serif transition-colors">"</button>
                </div>
                <div class="flex items-center gap-0.5 px-2 border-r border-mp-border">
                  <button @click="editorCmd('alignLeft')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M3 5h18v2H3zm0 4h12v2H3zm0 4h18v2H3zm0 4h12v2H3z"/></svg>
                  </button>
                  <button @click="editorCmd('alignCenter')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M3 5h18v2H3zm3 4h12v2H6zm-3 4h18v2H3zm3 4h12v2H6z"/></svg>
                  </button>
                  <button @click="editorCmd('alignRight')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M3 5h18v2H3zm6 4h12v2H9zm-6 4h18v2H3zm6 4h12v2H9z"/></svg>
                  </button>
                </div>
                <div class="flex items-center gap-0.5 px-2 border-r border-mp-border">
                  <button @click="editorCmd('insertTable')" class="flex items-center gap-1 text-xs text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page px-2 h-7 rounded transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 3v18M6 3h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                    Table
                  </button>
                  <button @click="editorCmd('addColBefore')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center text-xs transition-colors">C+</button>
                  <button @click="editorCmd('addRowAfter')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center text-xs transition-colors">R+</button>
                  <button @click="editorCmd('deleteTable')" class="w-7 h-7 rounded text-mp-danger hover:text-mp-danger hover:bg-mp-page flex items-center justify-center text-xs transition-colors">✕T</button>
                </div>
                <div class="flex items-center gap-0.5 px-2 border-r border-mp-border">
                  <button @click="editorCmd('highlight', '#c9a84c')" class="w-5 h-5 rounded bg-mp-gold border border-mp-border hover:scale-110 transition-transform"></button>
                  <button @click="editorCmd('highlight', '#10b981')" class="w-5 h-5 rounded bg-mp-success border border-mp-border hover:scale-110 transition-transform"></button>
                  <button @click="editorCmd('highlight', '#ef4444')" class="w-5 h-5 rounded bg-mp-danger border border-mp-border hover:scale-110 transition-transform"></button>
                  <button @click="editorCmd('highlight', '#00b4c8')" class="w-5 h-5 rounded bg-mp-teal border border-mp-border hover:scale-110 transition-transform"></button>
                  <button @click="editorCmd('removeHighlight')" class="w-5 h-5 rounded bg-mp-muted border border-mp-border hover:scale-110 transition-transform text-mp-text-secondary text-xs flex items-center justify-center">✕</button>
                </div>
                <div class="flex items-center gap-0.5 pl-2">
                  <button @click="editorCmd('undo')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                  </button>
                  <button @click="editorCmd('redo')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/></svg>
                  </button>
                </div>
              </div>

              <!-- Editor content area -->
              <div
                id="rich-editor"
                ref="editorEl"
                contenteditable="true"
                @input="onEditorInput"
                @keydown="onEditorKeydown"
                class="min-h-[180px] bg-mp-card-hover border border-t-0 border-mp-border rounded-b-lg px-5 py-4 text-mp-text-secondary text-sm leading-relaxed focus:outline-none editor-area"
                :data-placeholder="'Write your export analysis, trade observations or action items for this period...'"
              ></div>

              <div class="flex items-center justify-between mt-3">
                <p class="text-xs text-mp-muted">Rich text — supports bold, tables, lists, highlights</p>
                <div class="flex items-center gap-3">
                  <span v-if="noteSaved" class="text-xs text-mp-success font-semibold">✓ Note saved</span>
                  <button @click="saveNote"
                    :disabled="savingNote"
                    class="flex items-center gap-2 bg-mp-success hover:bg-mp-success disabled:opacity-50 text-mp-text-secondary text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    <svg v-if="savingNote" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    {{ savingNote ? 'Saving...' : (editingNoteId ? 'Update Note' : 'Save Note') }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ── TAKEAWAY POPUP ── -->
    <Teleport to="body">
      <div v-if="takeawayPopup" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4" @click.self="takeawayPopup = null">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-2xl shadow-2xl max-h-[85vh] flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border flex-shrink-0">
            <div>
              <p class="text-mp-text-secondary font-bold text-lg">
                {{ takeawaySelected ? takeawaySelected : 'Top ' + takeawayPopup.label }}
              </p>
              <p class="text-mp-success text-sm mt-0.5">
                {{ takeawaySelected ? takeawayPopup.label + ' drill-down analysis' : takeawayPopup.top_label + ' — ' + fmt(takeawayPopup.top_value) }}
              </p>
            </div>
            <button @click="takeawayPopup = null"
              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-mp-muted transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="p-6 overflow-y-auto flex-1">
            <div v-if="takeawayLoading" class="text-center py-8">
              <svg class="animate-spin w-8 h-8 text-mp-success mx-auto" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
            </div>
            <div v-else-if="takeawayData">
              <div v-if="takeawaySelected" class="flex items-center gap-2 mb-4">
                <button @click="resetTakeaway"
                  class="flex items-center gap-1.5 text-xs text-mp-success hover:text-mp-success transition-colors">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                  </svg>
                  All {{ takeawayPopup.label }}s
                </button>
                <span class="text-mp-muted text-xs">›</span>
                <span class="text-mp-text-secondary text-xs font-semibold">{{ takeawaySelected }}</span>
              </div>

              <p class="text-xs font-semibold text-mp-success uppercase tracking-widest mb-3">
                {{ takeawaySelected ? takeawaySelected + ' — Key Stats' : 'Top ' + takeawayPopup.label + ' — Key Stats' }}
              </p>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
                <div v-for="stat in takeawayData.stats" :key="stat.label" class="bg-mp-card-hover rounded-xl p-4">
                  <p class="text-xs text-mp-muted uppercase tracking-widest">{{ stat.label }}</p>
                  <p class="text-lg font-bold text-mp-text-secondary mt-1">{{ stat.value }}</p>
                </div>
              </div>

              <p class="text-xs font-semibold text-mp-success uppercase tracking-widest mb-3">
                {{ takeawaySelected ? 'Back to full ranking ↓' : 'Click any row to drill down ↓' }}
              </p>
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-mp-muted uppercase px-3 py-2">#</th>
                    <th class="text-left text-xs font-semibold text-mp-muted uppercase px-3 py-2">{{ takeawayPopup.label }}</th>
                    <th class="text-right text-xs font-semibold text-mp-muted uppercase px-3 py-2">PO Net Value</th>
                    <th class="text-right text-xs font-semibold text-mp-muted uppercase px-3 py-2">% Share</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(row, i) in takeawayData.ranking" :key="i"
                    @click="drillDown(row.label)"
                    :class="[
                      takeawaySelected === row.label ? 'bg-mp-success/40 border-l-2 border-mp-success' : '',
                      i === 0 && !takeawaySelected ? 'bg-mp-success/30' : '',
                      'hover:bg-mp-card-hover/70 cursor-pointer transition-colors'
                    ]">
                    <td class="px-3 py-2.5 text-mp-muted text-xs">{{ i + 1 }}</td>
                    <td class="px-3 py-2.5 text-mp-text-secondary font-medium">
                      <span v-if="i === 0 && !takeawaySelected" class="text-mp-warning mr-1">★</span>
                      {{ row.label }}
                      <span class="text-mp-muted text-xs ml-1">→</span>
                    </td>
                    <td class="px-3 py-2.5 text-right text-mp-success font-semibold">{{ fmt(row.value) }}</td>
                    <td class="px-3 py-2.5 text-right text-mp-muted text-xs">{{ row.pct }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DonutChart3D from '@/Components/DonutChart3D.vue'
import axios from 'axios'

const props = defineProps({
  company:         Object,
  defaultDateFrom: String,
  defaultDateTo:   String,
  dimensionFields: { type: Object, default: () => ({}) },
})

// ── State ──
const loading         = ref(true)
const dateFrom        = ref(props.defaultDateFrom)
const dateTo          = ref(props.defaultDateTo)
const kpis            = ref({})
const breakdowns      = ref([])
const topAchievers    = ref([])
const poStatus        = ref([])
const insights        = ref([])
const notes           = ref([])
const savingNote      = ref(false)
const noteSaved       = ref(false)
const takeawayPopup   = ref(null)
const takeawayData    = ref(null)
const takeawayLoading = ref(false)
const takeawaySelected = ref(null)
const trendChartCanvas = ref(null)

let Chart = null
let trendChart = null

// ── Chart.js ──
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

// ── SheetJS ──
async function loadSheetJs() {
  if (window.XLSX) return
  await new Promise((resolve, reject) => {
    const s = document.createElement('script')
    s.src = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js'
    s.onload = resolve; s.onerror = reject
    document.head.appendChild(s)
  })
}

// ── Export breakdown ──
async function exportBreakdown(bd) {
  await loadSheetJs()
  const XLSX = window.XLSX
  const dataRows = [
    [bd.label, 'PO Net Value', '% Share', 'Accumulated %'],
    ...bd.rows.map(r => [r.label, r.value, r.pct + '%', r.accum + '%']),
    ['Total', bd.rows.reduce((s, r) => s + r.value, 0), '100%', '100%'],
  ]
  const ws = XLSX.utils.aoa_to_sheet(dataRows)
  ws['!cols'] = [{ wch: 35 }, { wch: 20 }, { wch: 12 }, { wch: 16 }]
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, bd.label.slice(0, 31))
  XLSX.writeFile(wb, `${props.company.name.replace(/\s+/g,'_')}_${bd.label.replace(/\s+/g,'_')}_${dateFrom.value}_${dateTo.value}.xlsx`)
}

function alpha(hex, a) {
  const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16)
  return `rgba(${r},${g},${b},${a})`
}
function compactNum(n) {
  if (n >= 1e9) return (n/1e9).toFixed(1) + 'B'
  if (n >= 1e6) return (n/1e6).toFixed(1) + 'M'
  if (n >= 1e3) return (n/1e3).toFixed(0) + 'K'
  return n.toFixed(0)
}

// ── PO Status color ──
function poStatusColor(status) {
  const s = (status || '').toLowerCase()
  if (['open','pending'].includes(s))   return 'text-white'
  if (['shipped','delivered','closed','completed'].some(v => s.includes(v))) return 'text-mp-success'
  if (['cancelled','canceled','rejected'].some(v => s.includes(v))) return 'text-mp-danger'
  return 'text-white'
}

// ── Load dashboard ──
async function loadDashboard() {
  loading.value = true
  destroyAllCharts()
  try {
    const { data } = await axios.get(
      route('export-sales.dashboard-data', props.company.id),
      { params: { date_from: dateFrom.value, date_to: dateTo.value } }
    )
    kpis.value         = data.kpis
    breakdowns.value   = (data.breakdowns || []).map(bd => ({ ...bd, tab: 'chart' }))
    topAchievers.value = data.top_achievers || []
    poStatus.value     = data.po_status || []
    insights.value     = data.insights || []

    loadNotes()

    loading.value = false
    await nextTick()
    await loadChartJs()
    await nextTick()

    setTimeout(() => {
      renderTrendChart(data.monthly_trend || [])
    }, 100)

  } catch(e) {
    console.error(e)
    loading.value = false
  }
}

function destroyAllCharts() {
  if (trendChart) { trendChart.destroy(); trendChart = null }
}

function onTabChange(bd, idx) {
  bd.tab = 'chart'
}

// ── Trend Chart ──
function renderTrendChart(rows) {
  if (trendChart) { trendChart.destroy(); trendChart = null }
  const canvas = trendChartCanvas.value
  if (!canvas || !rows.length) return
  const ctx = canvas.getContext('2d')

  const labels = rows.map(r => r.period)
  const values = rows.map(r => r.value)
  const growthRates = rows.map((r, i) => {
    if (i === 0) return null
    const prev = rows[i-1].value
    return prev > 0 ? parseFloat(((r.value - prev)/prev*100).toFixed(1)) : null
  })

  trendChart = new Chart(ctx, {
    data: {
      labels,
      datasets: [
        {
          type: 'line', label: 'PO Net Value', data: values,
          borderColor: '#10b981', backgroundColor: alpha('#10b981', 0.08),
          pointBackgroundColor: '#10b981', pointBorderColor: '#0f7a90',
          pointRadius: 4, pointHoverRadius: 6, fill: true, tension: 0.4, yAxisID: 'y',
        },
        {
          type: 'line', label: 'MoM Growth %', data: growthRates,
          borderColor: '#00b4c8', backgroundColor: 'transparent',
          pointBackgroundColor: '#00b4c8', pointBorderColor: '#0f7a90',
          pointRadius: 4, pointHoverRadius: 6, fill: false, tension: 0.4,
          borderDash: [5, 3], yAxisID: 'y2', spanGaps: true,
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { color: '#64748b', font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: ctx => {
              if (ctx.datasetIndex === 0) return ' PO Net Value: ' + Number(ctx.raw).toLocaleString('en-US', { maximumFractionDigits: 0 })
              return ctx.raw !== null ? ` MoM Growth: ${ctx.raw}%` : ''
            }
          }
        }
      },
      scales: {
        x:  { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: '#112240' } },
        y:  { position: 'left',  ticks: { color: '#64748b', font: { size: 10 }, callback: v => Number(v).toLocaleString('en-US', { notation: 'compact' }) }, grid: { color: '#112240' } },
        y2: { position: 'right', ticks: { color: '#00b4c8', font: { size: 10 }, callback: v => v + '%' }, grid: { drawOnChartArea: false } },
      }
    }
  })
}

// ── Takeaway ──
async function openTakeaway(achiever) {
  takeawayPopup.value = achiever; takeawaySelected.value = null
  takeawayData.value = null; takeawayLoading.value = true
  await fetchTakeaway(achiever.field, null)
}
async function drillDown(itemLabel) {
  takeawaySelected.value = itemLabel; takeawayLoading.value = true
  await fetchTakeaway(takeawayPopup.value.field, itemLabel)
}
async function resetTakeaway() {
  takeawaySelected.value = null; takeawayLoading.value = true
  await fetchTakeaway(takeawayPopup.value.field, null)
}
async function fetchTakeaway(field, selectedValue) {
  try {
    const { data } = await axios.get(
      route('export-sales.takeaway', props.company.id),
      { params: { field, selected_value: selectedValue, date_from: dateFrom.value, date_to: dateTo.value } }
    )
    takeawayData.value = data
  } catch(e) { console.error(e) }
  finally { takeawayLoading.value = false }
}

// ── Helpers ──
function fmt(val) {
  const n = parseFloat(val) || 0
  return n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

// ── Notes ──
const editorEl      = ref(null)
const editingNoteId = ref(null)

async function loadNotes() {
  try {
    const { data } = await axios.get(
      route('export-sales.get-notes', props.company.id),
      { params: { date_from: dateFrom.value, date_to: dateTo.value } }
    )
    notes.value = data.notes || []
  } catch(e) { console.error(e) }
}
function startEdit(n) {
  editingNoteId.value = n.id
  nextTick(() => { if (editorEl.value) { editorEl.value.innerHTML = n.note; editorEl.value.focus() } })
  setTimeout(() => editorEl.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 100)
}
function cancelEdit() {
  editingNoteId.value = null
  if (editorEl.value) editorEl.value.innerHTML = ''
}
async function deleteNote(id) {
  if (!confirm('Are you sure you want to delete this note?')) return
  try {
    await axios.delete(route('export-sales.delete-note', { company: props.company.id, note: id }))
    await loadNotes()
  } catch(e) { console.error(e) }
}
async function saveNote() {
  const html = editorEl.value?.innerHTML?.trim()
  if (!html || html === '<br>') return
  savingNote.value = true; noteSaved.value = false
  try {
    if (editingNoteId.value) {
      await axios.put(route('export-sales.update-note', { company: props.company.id, note: editingNoteId.value }), { note: html })
    } else {
      await axios.post(route('export-sales.save-note', props.company.id), { date_from: dateFrom.value, date_to: dateTo.value, note: html })
    }
    noteSaved.value = true; editingNoteId.value = null
    if (editorEl.value) editorEl.value.innerHTML = ''
    await loadNotes()
    setTimeout(() => noteSaved.value = false, 3000)
  } catch(e) { console.error(e) }
  finally { savingNote.value = false }
}

// ── Rich Text Editor ──
function editorCmd(cmd, value = null) {
  const el = editorEl.value; if (!el) return; el.focus()
  switch(cmd) {
    case 'bold':           document.execCommand('bold'); break
    case 'italic':         document.execCommand('italic'); break
    case 'underline':      document.execCommand('underline'); break
    case 'strikethrough':  document.execCommand('strikeThrough'); break
    case 'h1': document.execCommand('formatBlock', false, isActive('h1') ? 'p' : 'h1'); break
    case 'h2': document.execCommand('formatBlock', false, isActive('h2') ? 'p' : 'h2'); break
    case 'h3': document.execCommand('formatBlock', false, isActive('h3') ? 'p' : 'h3'); break
    case 'bullet':         document.execCommand('insertUnorderedList'); break
    case 'ordered':        document.execCommand('insertOrderedList'); break
    case 'blockquote':     document.execCommand('formatBlock', false, isActive('blockquote') ? 'p' : 'blockquote'); break
    case 'alignLeft':      document.execCommand('justifyLeft'); break
    case 'alignCenter':    document.execCommand('justifyCenter'); break
    case 'alignRight':     document.execCommand('justifyRight'); break
    case 'undo':           document.execCommand('undo'); break
    case 'redo':           document.execCommand('redo'); break
    case 'highlight':      document.execCommand('hiliteColor', false, value); break
    case 'removeHighlight':document.execCommand('hiliteColor', false, 'transparent'); break
    case 'insertTable':    insertTable(); break
    case 'addColBefore':   tableCmd('addCol'); break
    case 'addRowAfter':    tableCmd('addRow'); break
    case 'deleteTable':    tableCmd('deleteTable'); break
  }
}
function isActive(format) {
  try {
    switch(format) {
      case 'bold':         return document.queryCommandState('bold')
      case 'italic':       return document.queryCommandState('italic')
      case 'underline':    return document.queryCommandState('underline')
      case 'strikethrough':return document.queryCommandState('strikeThrough')
      case 'bullet':       return document.queryCommandState('insertUnorderedList')
      case 'ordered':      return document.queryCommandState('insertOrderedList')
      case 'blockquote':   return !!getSelectionNode('blockquote')
      case 'h1':           return document.queryCommandValue('formatBlock') === 'h1'
      case 'h2':           return document.queryCommandValue('formatBlock') === 'h2'
      case 'h3':           return document.queryCommandValue('formatBlock') === 'h3'
    }
  } catch(e) {}
  return false
}
function getSelectionNode(tag) {
  let node = window.getSelection()?.anchorNode
  while (node && node !== editorEl.value) { if (node.nodeName?.toLowerCase() === tag) return node; node = node.parentNode }
  return null
}
function onEditorInput() {}
function onEditorKeydown(e) {
  if (e.key === 'Tab') { e.preventDefault(); document.execCommand('insertHTML', false, '&nbsp;&nbsp;&nbsp;&nbsp;') }
}
function insertTable() {
  const rows = 3, cols = 3
  let html = '<table style="border-collapse:collapse;width:100%;margin:8px 0"><tbody>'
  for (let r = 0; r < rows; r++) {
    html += '<tr>'
    for (let c = 0; c < cols; c++) {
      const tag = r === 0 ? 'th' : 'td'
      const style = r === 0
        ? 'border:1px solid #1490a8;padding:8px 12px;background:#0f7a90;color:#10b981;font-weight:600;text-align:left'
        : 'border:1px solid #1490a833;padding:8px 12px;background:#112240;color:#e2e8f0'
      html += `<${tag} style="${style}">&nbsp;</${tag}>`
    }
    html += '</tr>'
  }
  html += '</tbody></table><p><br></p>'
  document.execCommand('insertHTML', false, html)
}
function tableCmd(action) {
  const sel = window.getSelection(); if (!sel?.anchorNode) return
  let cell = sel.anchorNode
  while (cell && !['TD','TH'].includes(cell.nodeName)) cell = cell.parentNode
  if (!cell) return
  const row = cell.parentNode; const table = row?.parentNode?.parentNode
  if (action === 'deleteTable' && table) { table.remove(); return }
  if (action === 'addRow' && row) {
    const newRow = document.createElement('tr')
    for (let i = 0; i < row.cells.length; i++) {
      const td = document.createElement('td')
      td.style.cssText = 'border:1px solid #1490a833;padding:8px 12px;background:#112240;color:#e2e8f0'
      td.innerHTML = '&nbsp;'; newRow.appendChild(td)
    }
    row.parentNode.insertBefore(newRow, row.nextSibling)
  }
  if (action === 'addCol' && table) {
    Array.from(table.rows).forEach((r, ri) => {
      const cellIdx = Array.from(row.cells).indexOf(cell)
      const newCell = document.createElement(ri === 0 ? 'th' : 'td')
      newCell.style.cssText = ri === 0
        ? 'border:1px solid #1490a8;padding:8px 12px;background:#0f7a90;color:#10b981;font-weight:600'
        : 'border:1px solid #1490a833;padding:8px 12px;background:#112240;color:#e2e8f0'
      newCell.innerHTML = '&nbsp;'; r.insertBefore(newCell, r.cells[cellIdx])
    })
  }
}

onMounted(() => loadDashboard())
</script>

<style>
.editor-area:empty:before {
  content: attr(data-placeholder);
  color: #1490a8;
  pointer-events: none;
}
.prose-dark h1, .editor-area h1 { font-size:1.4em; font-weight:700; color:#fff; margin:12px 0 6px; }
.prose-dark h2, .editor-area h2 { font-size:1.2em; font-weight:700; color:#e2e8f0; margin:10px 0 4px; }
.prose-dark h3, .editor-area h3 { font-size:1.05em; font-weight:600; color:#e2e8f0; margin:8px 0 4px; }
.prose-dark p,  .editor-area p  { margin: 4px 0; }
.prose-dark ul, .editor-area ul { list-style:disc; padding-left:1.5rem; margin:6px 0; color:#e2e8f0; }
.prose-dark ol, .editor-area ol { list-style:decimal; padding-left:1.5rem; margin:6px 0; color:#e2e8f0; }
.prose-dark blockquote, .editor-area blockquote { border-left:3px solid #10b981; padding-left:12px; color:#64748b; font-style:italic; margin:8px 0; }
.prose-dark table, .editor-area table { border-collapse:collapse; width:100%; margin:8px 0; }
.prose-dark td, .prose-dark th, .editor-area td, .editor-area th { border:1px solid #1490a833; padding:8px 12px; }
.prose-dark th, .editor-area th { background:#0f7a90; color:#10b981; font-weight:600; }
.prose-dark td, .editor-area td { background:#112240; color:#e2e8f0; }
.editor-area:focus { outline: none; }
</style>