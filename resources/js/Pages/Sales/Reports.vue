<template>
  <Head :title="`Sales Reports — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-mp-text-secondary">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <Link :href="`/portfolio-companies/${company.id}`"
            class="flex items-center gap-2 text-sm text-mp-muted hover:text-mp-text-secondary transition-colors mb-2 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to {{ company.name }}
          </Link>
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-bold text-mp-text-secondary">Sales Reports</h1>
              <p class="text-mp-muted text-sm mt-1">Build and run analytical reports on your sales data</p>
            </div>
            <div class="flex items-center gap-3">
              <Link :href="`/companies/${company.id}/sales`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page text-mp-text text-sm font-medium px-4 py-2 rounded-lg border border-mp-border transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Sales Dashboard
              </Link>
              <Link :href="route('comparison-dashboard.index', company.id)"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page text-mp-text text-sm font-medium px-4 py-2 rounded-lg border border-mp-border transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Comparison Dashboard
              </Link>
              <Link :href="`/companies/${company.id}/sales/upload`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page text-mp-text text-sm font-medium px-4 py-2 rounded-lg border border-mp-border transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                Upload Data
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- No data -->
      <div v-if="!hasData" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-mp-warning/30 border border-mp-warning/50 rounded-xl p-6 text-center">
          <svg class="w-10 h-10 text-mp-warning mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <p class="text-mp-warning font-medium mb-1">No Sales Data Yet</p>
          <p class="text-mp-warning text-sm mb-4">Upload your sales data first before running reports</p>
          <Link :href="`/companies/${company.id}/sales/upload`"
            class="bg-mp-teal hover:bg-mp-teal-dark text-mp-text-secondary text-sm font-medium px-6 py-2.5 rounded-lg transition-colors inline-block">
            Upload Data Now
          </Link>
        </div>
      </div>

      <div v-else class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- REPORT BUILDER -->
        <div class="bg-mp-card rounded-xl border border-mp-border p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-6">Report Builder</p>

          <div class="space-y-5">

            <!-- Report Type -->
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-3">Report Type</label>
              <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2">
                <button v-for="rt in reportTypes" :key="rt.key"
                  type="button" @click="params.report_type = rt.key"
                  :class="params.report_type === rt.key
                    ? 'border-mp-teal bg-mp-teal/20 text-mp-text-secondary'
                    : 'border-mp-border bg-mp-card-hover text-mp-muted hover:border-mp-border'"
                  class="flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 transition-all cursor-pointer text-center">
                  <span class="text-xs font-semibold leading-tight">{{ rt.label }}</span>
                </button>
              </div>
            </div>

            <!-- Dates + Period -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div v-if="params.report_type !== 'period_comparison'">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Date From</label>
                <input v-model="params.date_from" type="date"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
              </div>
              <div v-if="params.report_type !== 'period_comparison'">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Date To</label>
                <input v-model="params.date_to" type="date"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
              </div>
              <div v-if="showPeriodSelector">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period Group</label>
                <select v-model="params.period"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly (Q1-Q4)</option>
                  <option value="semi_annually">Semi-Annually (H1-H2)</option>
                  <option value="annually">Annually</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Metric</label>
                <select v-model="params.metric"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                  <option v-for="(label, key) in metricFields" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
            </div>

            <!-- Dimension selectors (context-sensitive) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4" v-if="showDimension1">
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  {{ dimension1Label }}
                </label>
                <select v-model="params.dimension1"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                  <template v-if="params.report_type === 'ranking'">
                    <option v-for="(label, key) in rankByFields" :key="key" :value="key">{{ label }}</option>
                  </template>
                  <template v-else>
                    <option v-for="(label, key) in dimensionFields" :key="key" :value="key">{{ label }}</option>
                  </template>
                </select>
              </div>
              <div v-if="showDimension2">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  {{ dimension2Label }}
                </label>
                <select v-model="params.dimension2"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                  <option v-for="(label, key) in dimensionFields" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
            </div>

            <!-- Two Factors Trend / Matrix: item-level multi-selectors for each axis -->
            <div v-if="['two_factors_trend', 'matrix'].includes(params.report_type)" class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <DimensionMultiSelect
                v-if="params.dimension1"
                :company-id="company.id"
                :dimension="params.dimension1"
                :date-from="params.date_from"
                :date-to="params.date_to"
                :metric="params.metric"
                v-model="params.dim1_items"
                :label="`${dimensionFields[params.dimension1] || (params.report_type === 'matrix' ? 'Row' : 'Factor 1')} items`" />
              <DimensionMultiSelect
                v-if="params.dimension2"
                :company-id="company.id"
                :dimension="params.dimension2"
                :date-from="params.date_from"
                :date-to="params.date_to"
                :metric="params.metric"
                v-model="params.dim2_items"
                :label="`${dimensionFields[params.dimension2] || (params.report_type === 'matrix' ? 'Column' : 'Factor 2')} items`" />
            </div>

            <!-- Period Comparison: how many periods + their date ranges -->
            <div v-if="params.report_type === 'period_comparison'" class="space-y-4">
              <div class="max-w-xs">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Number of Periods to Compare</label>
                <select v-model.number="periodsCount"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                  <option :value="2">2 Periods</option>
                  <option :value="3">3 Periods</option>
                  <option :value="4">4 Periods</option>
                  <option :value="5">5 Periods</option>
                </select>
              </div>

              <div class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period 1 From</label>
                    <input v-model="params.date_from" type="date"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period 1 To</label>
                    <input v-model="params.date_to" type="date"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
                  </div>
                </div>

                <div v-for="(p, i) in extraPeriods" :key="i" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period {{ i + 2 }} From</label>
                    <input v-model="p.from" type="date"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period {{ i + 2 }} To</label>
                    <input v-model="p.to" type="date"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
                  </div>
                </div>
              </div>

              <!-- Item-level multi-selector for the chosen dimension -->
              <DimensionMultiSelect
                v-if="params.dimension1"
                :company-id="company.id"
                :dimension="params.dimension1"
                :date-from="latestPeriod.from"
                :date-to="latestPeriod.to"
                :metric="params.metric"
                v-model="params.selected_items"
                :label="`${dimensionFields[params.dimension1] || 'Items'} — pick specific ones, or leave empty for Top 300 + Others`" />
            </div>

            <!-- Invoice Analysis: view toggle + view-specific controls -->
            <div v-if="params.report_type === 'invoice_analysis'" class="space-y-4">
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-3">View</label>
                <div class="grid grid-cols-3 gap-2 max-w-xl">
                  <button v-for="v in [{key:'snapshot',label:'Snapshot'},{key:'by_dimension',label:'By Dimension'},{key:'large_invoices',label:'Large Invoices'}]"
                    :key="v.key" type="button" @click="params.invoice_view = v.key"
                    :class="params.invoice_view === v.key
                      ? 'border-mp-teal bg-mp-teal/20 text-mp-text-secondary'
                      : 'border-mp-border bg-mp-card-hover text-mp-muted hover:border-mp-border'"
                    class="p-2.5 rounded-lg border-2 transition-all cursor-pointer text-center text-xs font-semibold">
                    {{ v.label }}
                  </button>
                </div>
              </div>

              <div v-if="params.invoice_view === 'by_dimension'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Dimension</label>
                  <select v-model="params.dimension1"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                    <option v-for="(label, key) in dimensionFields" :key="key" :value="key">{{ label }}</option>
                  </select>
                </div>
                <DimensionMultiSelect
                  v-if="params.dimension1"
                  :company-id="company.id"
                  :dimension="params.dimension1"
                  :date-from="params.date_from"
                  :date-to="params.date_to"
                  :metric="params.metric"
                  v-model="params.dim1_items"
                  :label="`${dimensionFields[params.dimension1] || 'Items'} — pick specific ones, or leave empty for Top 300 + Others`" />
              </div>

              <div v-if="params.invoice_view === 'large_invoices'" class="max-w-xs">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Show Invoices Above</label>
                <input v-model.number="params.invoice_threshold" type="number" min="0" step="10000"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
              </div>
            </div>

            <!-- Run button -->
            <div class="flex items-center gap-3 pt-2">
              <button @click="runReport"
                :disabled="running || !params.report_type || !params.date_from || !params.date_to"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 disabled:cursor-not-allowed text-mp-text-secondary text-sm font-medium px-8 py-3 rounded-lg transition-colors">
                <svg v-if="running" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ running ? 'Running...' : 'Run Report' }}
              </button>
              <button v-if="result" @click="result = null" class="text-sm text-mp-muted hover:text-mp-text transition-colors">
                Clear Results
              </button>
            </div>

          </div>
        </div>

        <!-- ── RESULTS ── -->
        <div v-if="result" class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">

          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
            <div>
              <p class="text-mp-text-secondary font-semibold">{{ resultTitle }}</p>
              <p class="text-mp-muted text-xs mt-0.5">{{ params.date_from }} → {{ params.date_to }}</p>
            </div>
            <!-- Export to Excel button -->
            <button @click="exportToExcel"
              :disabled="exporting"
              class="flex items-center gap-2 bg-mp-success hover:bg-mp-success disabled:opacity-50 disabled:cursor-not-allowed text-mp-text-secondary text-sm font-medium px-4 py-2 rounded-lg transition-colors">
              <svg v-if="exporting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              {{ exporting ? 'Exporting...' : 'Export to Excel' }}
            </button>
          </div>

          <!-- Single Dimension -->
          <div v-if="result.type === 'single_dimension'">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">#</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">{{ fields[result.dimension] || result.dimension }}</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">{{ metricFields[result.metric] || result.metric }}</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Transactions</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">% Share</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Accumulated % Share</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(row, i) in result.rows" :key="i" class="hover:bg-mp-card-hover/50 transition-colors">
                    <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                    <td class="px-6 py-3 text-mp-text-secondary font-medium">
                      {{ row.label }}<span v-if="row.is_other" class="text-mp-muted font-normal"> ({{ row.other_count }} items)</span>
                    </td>
                    <td class="px-6 py-3 text-right text-mp-success font-semibold">{{ fmt(row.value) }}</td>
                    <td class="px-6 py-3 text-right text-mp-muted">{{ row.transactions }}</td>
                    <td class="px-6 py-3 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <div class="w-16 h-1.5 bg-mp-card-hover rounded-full overflow-hidden">
                          <div class="h-full bg-mp-teal rounded-full" :style="`width:${getShare(row.value, result.rows)}%`"></div>
                        </div>
                        <span class="text-mp-muted text-xs w-10 text-right">{{ getShare(row.value, result.rows).toFixed(1) }}%</span>
                      </div>
                    </td>
                    <td class="px-6 py-3 text-right text-mp-muted text-xs">{{ accumulatedShares[i]?.toFixed(1) }}%</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="border-t-2 border-mp-border bg-mp-card-hover/50">
                    <td colspan="2" class="px-6 py-3 text-mp-text-secondary font-bold">Total</td>
                    <td class="px-6 py-3 text-right text-mp-text-secondary font-bold">{{ fmt(aggregate(result.rows.map(r => r.value), result.metric)) }}</td>
                    <td class="px-6 py-3 text-right text-mp-muted font-bold">{{ result.rows.reduce((s,r) => s + parseInt(r.transactions||0), 0) }}</td>
                    <td class="px-6 py-3 text-right text-mp-muted">100%</td>
                    <td class="px-6 py-3 text-right text-mp-muted">100%</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <!-- Chart -->
            <div class="px-6 py-6 border-t border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Chart View</p>
              <div class="bg-mp-page rounded-xl p-4" style="height:400px">
                <canvas ref="chartCanvas"></canvas>
              </div>
            </div>
          </div>

          <!-- Matrix -->
          <div v-else-if="result.type === 'matrix'">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase px-4 py-3">#</th>
                    <th class="text-left text-xs font-semibold text-white uppercase px-4 py-3 sticky left-0 bg-mp-card min-w-32">{{ dimensionFields[result.dim1] || result.dim1 }}</th>
                    <th v-for="col in result.columns" :key="col" class="text-right text-xs font-semibold text-white uppercase px-4 py-3 whitespace-nowrap">{{ col }}</th>
                    <th class="text-right text-xs font-semibold text-white uppercase px-4 py-3">Total</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(row, i) in result.rows" :key="row.label" class="hover:bg-mp-card-hover/50">
                    <td class="px-4 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                    <td class="px-4 py-3 text-mp-text-secondary font-medium sticky left-0 bg-mp-card">{{ row.label }}</td>
                    <td v-for="col in result.columns" :key="col" class="px-4 py-3 text-right"
                      :class="row[col] > 0 ? 'text-mp-success' : 'text-mp-muted'">
                      {{ row[col] > 0 ? fmt(row[col]) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right text-mp-text-secondary font-bold">
                      {{ fmt(result.columns.reduce((s,c) => s + (row[c] || 0), 0)) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="border-t-2 border-mp-border bg-mp-card-hover/50">
                    <td class="px-4 py-3"></td>
                    <td class="px-4 py-3 text-mp-text-secondary font-bold sticky left-0 bg-mp-card-hover/50">Total</td>
                    <td v-for="col in result.columns" :key="col" class="px-4 py-3 text-right text-mp-text-secondary font-bold">
                      {{ fmt(aggregate(result.rows.map(r => r[col] || 0), result.metric)) }}
                    </td>
                    <td class="px-4 py-3 text-right text-mp-text-secondary font-bold">
                      {{ fmt(aggregate(result.rows.flatMap(r => result.columns.map(c => r[c] || 0)), result.metric)) }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <!-- Chart -->
            <div class="px-6 py-6 border-t border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Chart View — Stacked Bar</p>
              <div class="bg-mp-page rounded-xl p-4" style="height:420px">
                <canvas ref="chartCanvas"></canvas>
              </div>
            </div>
          </div>

          <!-- Branch Product Ranking -->
          <div v-else-if="result.type === 'ranking'" class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase px-4 py-3">#</th>
                  <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3 sticky left-0 bg-mp-card">Branch</th>
                  <th v-for="r in result.num_ranks" :key="r"
                    class="text-center text-xs font-semibold text-white uppercase px-4 py-3 whitespace-nowrap">
                    Rank {{ r }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <tr v-for="(row, i) in result.rows" :key="row.branch" class="hover:bg-mp-card-hover/50">
                  <td class="px-4 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                  <td class="px-6 py-3 text-mp-text-secondary font-medium sticky left-0 bg-mp-card">{{ row.branch }}</td>
                  <td v-for="r in result.num_ranks" :key="r" class="px-4 py-3 text-center">
                    <button v-if="row.ranks[r] && row.ranks[r].count > 0"
                      @click="openRankPopup(row.branch, r, row.ranks[r])"
                      class="bg-mp-teal hover:bg-mp-teal text-mp-text-secondary text-xs font-bold px-3 py-1.5 rounded-lg transition-colors">
                      {{ row.ranks[r].count }}
                    </button>
                    <span v-else class="text-mp-muted">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Customer Nature -->
          <div v-else-if="result.type === 'customer_nature'">
            <div class="p-6">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="(cat, key) in result.categories" :key="key"
                  :class="natureBg(cat.label)"
                  class="rounded-xl border p-4 cursor-pointer hover:opacity-90 transition-opacity"
                  @click="openNaturePopup(cat)">
                  <p class="text-xs font-semibold uppercase tracking-widest mb-1" :class="natureText(cat.label)">
                    {{ natureLabel(cat.label) }}
                  </p>
                  <p class="text-4xl font-bold text-mp-text-secondary">{{ cat.count }}</p>
                  <p class="text-xs mt-2" :class="natureText(cat.label)">
                    {{ fmt(cat.total_sales) }}
                  </p>
                  <p class="text-xs text-mp-muted mt-1">Click to view details</p>
                </div>
              </div>
              <div class="mt-4 flex items-center justify-between bg-mp-card-hover/50 border-t-2 border-mp-border rounded-lg px-4 py-3">
                <span class="text-mp-text-secondary font-bold text-sm">Total</span>
                <span class="text-mp-text-secondary font-bold text-sm">{{ fmt(result.grand_total) }}</span>
              </div>
            </div>
            <!-- Donut Chart -->
            <div class="px-6 py-6 border-t border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Customer Distribution Chart</p>
              <div class="bg-mp-page rounded-xl p-4 flex items-center justify-center" style="height:380px">
                <canvas ref="chartCanvas"></canvas>
              </div>
            </div>
          </div>

          <!-- Period Comparison -->
          <div v-else-if="result.type === 'period_comparison'">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">#</th>
                    <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">Label</th>
                    <template v-for="(p, pi) in result.periods" :key="pi">
                      <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">
                        Period {{ pi + 1 }}<br><span class="font-normal text-mp-muted">{{ p.from }} → {{ p.to }}</span>
                      </th>
                      <th v-if="pi > 0" class="text-right text-xs font-semibold text-white uppercase px-6 py-3">Change %</th>
                      <th class="text-center text-xs font-semibold text-white uppercase px-3 py-3">Rank</th>
                    </template>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(row, i) in result.rows" :key="row.label"
                    class="hover:bg-mp-card-hover/50"
                    :class="row.is_other ? 'italic' : ''">
                    <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                    <td class="px-6 py-3 text-mp-text-secondary font-medium">
                      {{ row.label }}<span v-if="row.is_other" class="text-mp-muted font-normal"> ({{ row.other_count }} items)</span>
                    </td>
                    <template v-for="(p, pi) in result.periods" :key="pi">
                      <td class="px-6 py-3 text-right text-mp-text">{{ fmt(row.values[pi]) }}</td>
                      <td v-if="pi > 0" class="px-6 py-3 text-right">
                        <span v-if="row.changes[pi] !== null" :class="row.changes[pi] >= 0 ? 'text-mp-success' : 'text-mp-danger'" class="font-semibold">
                          {{ row.changes[pi] >= 0 ? '+' : '' }}{{ row.changes[pi] }}%
                        </span>
                        <span v-else class="text-mp-muted">N/A</span>
                      </td>
                      <td class="px-3 py-3 text-center">
                        <template v-if="row.ranks && row.ranks[pi] && row.ranks[pi].rank !== null">
                          <span class="inline-flex items-center gap-1 bg-mp-card-hover border border-mp-border rounded-full px-2 py-0.5 text-xs text-mp-text-secondary font-semibold">
                            #{{ row.ranks[pi].rank }}
                            <span v-if="row.ranks[pi].rank_change" :class="row.ranks[pi].rank_change > 0 ? 'text-mp-success' : 'text-mp-danger'">
                              {{ row.ranks[pi].rank_change > 0 ? '▲' : '▼' }}{{ Math.abs(row.ranks[pi].rank_change) }}
                            </span>
                          </span>
                        </template>
                        <span v-else class="text-mp-muted text-xs">—</span>
                      </td>
                    </template>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="border-t-2 border-mp-border bg-mp-card-hover/50">
                    <td class="px-6 py-3"></td>
                    <td class="px-6 py-3 text-mp-text-secondary font-bold">Total</td>
                    <template v-for="(p, pi) in result.periods" :key="pi">
                      <td class="px-6 py-3 text-right text-mp-text-secondary font-bold">
                        {{ fmt(aggregate(result.rows.map(r => r.values[pi]), result.metric)) }}
                      </td>
                      <td v-if="pi > 0" class="px-6 py-3 text-right font-bold">
                        <span v-if="periodComparisonTotalChange(pi) !== null"
                          :class="periodComparisonTotalChange(pi) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                          {{ periodComparisonTotalChange(pi) >= 0 ? '+' : '' }}{{ periodComparisonTotalChange(pi) }}%
                        </span>
                        <span v-else class="text-mp-muted">N/A</span>
                      </td>
                      <td class="px-3 py-3 text-center text-mp-muted text-xs">—</td>
                    </template>
                  </tr>
                </tfoot>
              </table>
            </div>
            <!-- Chart -->
            <div class="px-6 py-6 border-t border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Period Comparison Chart</p>
              <div class="bg-mp-page rounded-xl p-4" style="height:420px">
                <canvas ref="chartCanvas"></canvas>
              </div>
            </div>
          </div>

          <!-- Trend -->
          <div v-else-if="result.type === 'trend'">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">#</th>
                    <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">Period</th>
                    <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">{{ metricFields[result.metric] || result.metric }}</th>
                    <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">vs Previous</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(row, i) in result.rows" :key="i" class="hover:bg-mp-card-hover/50">
                    <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                    <td class="px-6 py-3 text-mp-text-secondary font-medium">{{ row.period }}</td>
                    <td class="px-6 py-3 text-right text-mp-success font-semibold">{{ fmt(row.value) }}</td>
                    <td class="px-6 py-3 text-right">
                      <span v-if="i > 0 && getTrendChange(result.rows, i) !== null"
                        :class="getTrendChange(result.rows, i) >= 0 ? 'text-mp-success' : 'text-mp-danger'" class="font-semibold">
                        {{ getTrendChange(result.rows, i) >= 0 ? '+' : '' }}{{ getTrendChange(result.rows, i) }}%
                      </span>
                      <span v-else class="text-mp-muted">—</span>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="border-t-2 border-mp-border bg-mp-card-hover/50">
                    <td class="px-6 py-3"></td>
                    <td class="px-6 py-3 text-mp-text-secondary font-bold">Total</td>
                    <td class="px-6 py-3 text-right text-mp-text-secondary font-bold">
                      {{ fmt(aggregate(result.rows.map(r => r.value), result.metric)) }}
                    </td>
                    <td class="px-6 py-3 text-right text-mp-muted">—</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <!-- Line Chart -->
            <div class="px-6 py-6 border-t border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Trend Chart</p>
              <div class="bg-mp-page rounded-xl p-4" style="height:380px">
                <canvas ref="chartCanvas"></canvas>
              </div>
            </div>
          </div>

          <!-- Two Factors Trend -->
          <div v-else-if="result.type === 'two_factors_trend'">
            <div class="overflow-x-auto">
              <table class="w-full text-sm border-collapse">
                <thead>
                  <tr class="border-b border-mp-border bg-mp-card-hover/50">
                    <th class="text-left text-xs font-semibold text-white uppercase px-4 py-3 sticky left-0 bg-mp-card-hover min-w-48">
                      {{ dimensionFields[result.dim1] }} / {{ dimensionFields[result.dim2] }}
                    </th>
                    <th v-for="p in result.periods" :key="p"
                      class="text-center text-xs font-semibold text-white uppercase px-3 py-3 whitespace-nowrap min-w-28">
                      {{ p }}
                    </th>
                    <th class="text-right text-xs font-semibold text-white uppercase px-4 py-3">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="(row, i) in result.rows" :key="row.label">
                    <!-- Parent row (collapsible) -->
                    <tr class="border-b border-mp-border bg-mp-teal-subtle/30 cursor-pointer hover:bg-mp-teal-subtle/50 transition-colors"
                      @click="toggleExpand(row.label)">
                      <td class="px-4 py-2.5 sticky left-0 bg-mp-teal-subtle/30">
                        <div class="flex items-center gap-2">
                          <span class="text-mp-muted text-xs w-5 flex-shrink-0">{{ i + 1 }}.</span>
                          <svg class="w-3 h-3 text-white transition-transform flex-shrink-0"
                            :class="expanded.has(row.label) ? 'rotate-90' : ''"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                          </svg>
                          <span class="text-mp-text-secondary font-semibold text-xs" :class="row.is_other ? 'italic' : ''">
                            {{ row.label }}<span v-if="row.is_other" class="text-mp-muted font-normal"> ({{ row.other_count }} items)</span>
                          </span>
                        </div>
                      </td>
                      <td v-for="p in result.periods" :key="p" class="px-3 py-2.5 text-center">
                        <div class="text-mp-text-secondary text-xs font-semibold">{{ fmt(row.cells[p]?.value || 0) }}</div>
                        <div class="text-xs mt-0.5" :class="(row.cells[p]?.gr || 0) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                          [GR {{ (row.cells[p]?.gr || 0) >= 0 ? '+' : '' }}{{ row.cells[p]?.gr || 0 }}%]
                        </div>
                        <div v-if="row.cells[p]?.rank != null" class="mt-1">
                          <span class="inline-flex items-center gap-1 bg-mp-card-hover border border-mp-border rounded-full px-2 py-0.5 text-xs text-mp-text-secondary font-semibold">
                            #{{ row.cells[p].rank }}
                            <span v-if="row.cells[p].rank_change" :class="row.cells[p].rank_change > 0 ? 'text-mp-success' : 'text-mp-danger'">
                              {{ row.cells[p].rank_change > 0 ? '▲' : '▼' }}{{ Math.abs(row.cells[p].rank_change) }}
                            </span>
                          </span>
                        </div>
                      </td>
                      <td class="px-4 py-2.5 text-right text-mp-text-secondary font-bold text-xs">{{ fmt(row.total) }}</td>
                    </tr>

                    <!-- Child rows (expanded) -->
                    <template v-if="expanded.has(row.label)">
                      <tr v-for="(child, ci) in row.children" :key="child.label"
                        class="border-b border-mp-border hover:bg-mp-card-hover/30 transition-colors">
                        <td class="px-4 py-2 pl-10 sticky left-0 bg-mp-card text-mp-text text-xs" :class="child.is_other ? 'italic' : ''">
                          <span class="text-mp-muted">{{ ci + 1 }}.</span>
                          {{ child.label }}<span v-if="child.is_other" class="text-mp-muted"> ({{ child.other_count }} items)</span>
                        </td>
                        <td v-for="p in result.periods" :key="p" class="px-3 py-2 text-center">
                          <div class="text-mp-text text-xs">{{ child.cells[p]?.value > 0 ? fmt(child.cells[p].value) : '—' }}</div>
                          <div v-if="child.cells[p]?.value > 0" class="text-xs mt-0.5"
                            :class="(child.cells[p]?.gr || 0) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                            [{{ (child.cells[p]?.gr || 0) >= 0 ? '+' : '' }}{{ child.cells[p]?.gr || 0 }}%]
                          </div>
                        </td>
                        <td class="px-4 py-2 text-right text-mp-text text-xs font-semibold">{{ fmt(child.total) }}</td>
                      </tr>
                    </template>
                  </template>
                </tbody>
                <tfoot>
                  <tr class="border-t-2 border-mp-border bg-mp-card-hover/50">
                    <td class="px-4 py-2.5 sticky left-0 bg-mp-card-hover/50 text-mp-text-secondary font-bold text-xs">Total</td>
                    <td v-for="p in result.periods" :key="p" class="px-3 py-2.5 text-center text-mp-text-secondary font-bold text-xs">
                      {{ fmt(aggregate(result.rows.map(r => r.cells[p]?.value || 0), result.metric)) }}
                    </td>
                    <td class="px-4 py-2.5 text-right text-mp-text-secondary font-bold text-xs">
                      {{ fmt(aggregate(result.rows.map(r => r.total || 0), result.metric)) }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <!-- Line chart per parent -->
            <div class="px-6 py-6 border-t border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Trend Chart — Parent Rows</p>
              <div class="bg-mp-page rounded-xl p-4" style="height:420px">
                <canvas ref="chartCanvas"></canvas>
              </div>
            </div>
          </div>

          <!-- Invoice Analysis — Snapshot -->
          <div v-else-if="result.type === 'invoice_analysis' && result.view === 'snapshot'">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 p-6">
              <div class="bg-mp-card-hover rounded-xl border border-mp-border p-4">
                <p class="text-xs text-mp-muted uppercase tracking-widest mb-1">Total Invoices</p>
                <p class="text-2xl font-bold text-mp-text-secondary">{{ result.summary.invoice_count.toLocaleString() }}</p>
              </div>
              <div class="bg-mp-card-hover rounded-xl border border-mp-border p-4">
                <p class="text-xs text-mp-muted uppercase tracking-widest mb-1">Total Value</p>
                <p class="text-2xl font-bold text-mp-success">{{ fmt(result.summary.total_value) }}</p>
              </div>
              <div class="bg-mp-card-hover rounded-xl border border-mp-border p-4">
                <p class="text-xs text-mp-muted uppercase tracking-widest mb-1">Avg Invoice Value</p>
                <p class="text-2xl font-bold text-mp-text-secondary">{{ fmt(result.summary.avg_invoice_value) }}</p>
              </div>
              <div class="bg-mp-card-hover rounded-xl border border-mp-border p-4">
                <p class="text-xs text-mp-muted uppercase tracking-widest mb-1">Median Invoice Value</p>
                <p class="text-2xl font-bold text-mp-text-secondary">{{ fmt(result.summary.median_invoice_value) }}</p>
              </div>
              <div class="bg-mp-card-hover rounded-xl border border-mp-border p-4">
                <p class="text-xs text-mp-muted uppercase tracking-widest mb-1">Largest Invoice</p>
                <p class="text-2xl font-bold text-mp-gold">{{ fmt(result.summary.max_invoice_value) }}</p>
              </div>
              <div class="bg-mp-card-hover rounded-xl border border-mp-border p-4">
                <p class="text-xs text-mp-muted uppercase tracking-widest mb-1">Avg Line Items / Invoice</p>
                <p class="text-2xl font-bold text-mp-text-secondary">{{ result.summary.avg_line_items.toFixed(1) }}</p>
              </div>
            </div>
            <div class="overflow-x-auto px-6 pb-6">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Value Range</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Invoice Count</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Total Value</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">% of Revenue</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(b, i) in result.distribution" :key="i" class="hover:bg-mp-card-hover/50">
                    <td class="px-6 py-3 text-mp-text-secondary font-medium">{{ b.label }}</td>
                    <td class="px-6 py-3 text-right text-mp-muted">{{ b.count.toLocaleString() }}</td>
                    <td class="px-6 py-3 text-right text-mp-success font-semibold">{{ fmt(b.total_value) }}</td>
                    <td class="px-6 py-3 text-right">
                      <div class="flex items-center justify-end gap-2">
                        <div class="w-16 h-1.5 bg-mp-card-hover rounded-full overflow-hidden">
                          <div class="h-full bg-mp-teal rounded-full" :style="`width:${b.pct_of_revenue}%`"></div>
                        </div>
                        <span class="text-mp-muted text-xs w-10 text-right">{{ b.pct_of_revenue }}%</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="px-6 py-6 border-t border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Invoice Value Distribution</p>
              <div class="bg-mp-page rounded-xl p-4" style="height:320px">
                <canvas ref="chartCanvas"></canvas>
              </div>
            </div>
          </div>

          <!-- Invoice Analysis — By Dimension -->
          <div v-else-if="result.type === 'invoice_analysis' && result.view === 'by_dimension'">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">#</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">{{ fields[result.dimension] || result.dimension }}</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Invoices</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Avg Invoice Value</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Median</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Max</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Avg Line Items</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Total Value</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(row, i) in result.rows" :key="i" class="hover:bg-mp-card-hover/50" :class="row.is_other ? 'italic' : ''">
                    <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                    <td class="px-6 py-3 text-mp-text-secondary font-medium">
                      {{ row.label }}<span v-if="row.is_other" class="text-mp-muted font-normal"> ({{ row.other_count }} items)</span>
                    </td>
                    <td class="px-6 py-3 text-right text-mp-muted">{{ row.invoice_count.toLocaleString() }}</td>
                    <td class="px-6 py-3 text-right text-mp-text">{{ fmt(row.avg_invoice_value) }}</td>
                    <td class="px-6 py-3 text-right text-mp-text">{{ fmt(row.median_invoice_value) }}</td>
                    <td class="px-6 py-3 text-right text-mp-gold">{{ fmt(row.max_invoice_value) }}</td>
                    <td class="px-6 py-3 text-right text-mp-muted">{{ row.avg_line_items.toFixed(1) }}</td>
                    <td class="px-6 py-3 text-right text-mp-success font-semibold">{{ fmt(row.total_value) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="border-t-2 border-mp-border bg-mp-card-hover/50">
                    <td colspan="2" class="px-6 py-3 text-mp-text-secondary font-bold">Total</td>
                    <td class="px-6 py-3 text-right text-mp-muted font-bold">{{ result.rows.reduce((s,r) => s + r.invoice_count, 0).toLocaleString() }}</td>
                    <td class="px-6 py-3 text-right text-mp-text-secondary font-bold">
                      {{ fmt(result.rows.reduce((s,r) => s + r.total_value, 0) / (result.rows.reduce((s,r) => s + r.invoice_count, 0) || 1)) }}
                    </td>
                    <td class="px-6 py-3 text-right text-mp-muted">—</td>
                    <td class="px-6 py-3 text-right text-mp-muted">—</td>
                    <td class="px-6 py-3 text-right text-mp-muted font-bold">
                      {{ (result.rows.reduce((s,r) => s + r.avg_line_items * r.invoice_count, 0) / (result.rows.reduce((s,r) => s + r.invoice_count, 0) || 1)).toFixed(1) }}
                    </td>
                    <td class="px-6 py-3 text-right text-mp-text-secondary font-bold">{{ fmt(result.rows.reduce((s,r) => s + r.total_value, 0)) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div class="px-6 py-6 border-t border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Total Value by {{ fields[result.dimension] || result.dimension }}</p>
              <div class="bg-mp-page rounded-xl p-4" style="height:400px">
                <canvas ref="chartCanvas"></canvas>
              </div>
            </div>
          </div>

          <!-- Invoice Analysis — Large Invoices -->
          <div v-else-if="result.type === 'invoice_analysis' && result.view === 'large_invoices'">
            <div v-if="result.truncated" class="px-6 py-2.5 bg-mp-warning/20 border-b border-mp-warning/40 text-xs text-mp-warning">
              Showing the top 500 invoices above this threshold — raise the threshold to narrow the list further.
            </div>
            <div v-if="result.rows.length === 0" class="px-6 py-10 text-center text-mp-muted text-sm">
              No invoices found above {{ fmt(result.threshold) }} in this period.
            </div>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-mp-border">
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">#</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Document #</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Customer</th>
                    <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Date</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Value</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Line Items</th>
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">% of Total Sales</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(row, i) in result.rows" :key="i" class="hover:bg-mp-card-hover/50">
                    <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                    <td class="px-6 py-3 text-mp-muted text-xs">{{ row.document_number }}</td>
                    <td class="px-6 py-3 text-mp-text-secondary">{{ row.customer_name }}</td>
                    <td class="px-6 py-3 text-mp-muted text-xs">{{ row.date }}</td>
                    <td class="px-6 py-3 text-right text-mp-success font-semibold">{{ fmt(row.value) }}</td>
                    <td class="px-6 py-3 text-right text-mp-muted">{{ row.line_items }}</td>
                    <td class="px-6 py-3 text-right text-mp-muted">
                      {{ result.period_total > 0 ? (row.net_sales / result.period_total * 100).toFixed(2) : '0.00' }}%
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="border-t-2 border-mp-border bg-mp-card-hover/50">
                    <td colspan="4" class="px-6 py-3 text-mp-text-secondary font-bold">Total</td>
                    <td class="px-6 py-3 text-right text-mp-text-secondary font-bold">{{ fmt(result.rows.reduce((s,r) => s + r.value, 0)) }}</td>
                    <td class="px-6 py-3 text-right text-mp-muted font-bold">{{ result.rows.reduce((s,r) => s + r.line_items, 0) }}</td>
                    <td class="px-6 py-3 text-right text-mp-teal font-bold">
                      {{ result.period_total > 0 ? (result.rows.reduce((s,r) => s + r.net_sales, 0) / result.period_total * 100).toFixed(1) : '0.0' }}%
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

        </div>

      </div>
    </div>

    <!-- ── Ranking Popup Modal ── -->
    <Teleport to="body">
      <div v-if="rankPopup" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4" @click.self="rankPopup = null">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-lg shadow-2xl">
          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
            <div>
              <p class="text-mp-text-secondary font-semibold">{{ rankPopup.branch }}</p>
              <p class="text-white text-xs">Rank {{ rankPopup.rank }} — {{ rankPopup.count }} product(s)</p>
            </div>
            <button @click="rankPopup = null" class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-mp-muted transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="overflow-y-auto max-h-96">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">#</th>
                  <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">Product</th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">Sales Value</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <tr v-for="(item, i) in rankPopup.products" :key="i" class="hover:bg-mp-card-hover/50">
                  <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                  <td class="px-6 py-3 text-mp-text-secondary">{{ item.product }}</td>
                  <td class="px-6 py-3 text-right text-mp-success font-semibold">{{ fmt(item.value) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-mp-border bg-mp-card-hover/50">
                  <td class="px-6 py-3 text-mp-text-secondary font-bold">Total</td>
                  <td class="px-6 py-3 text-right text-mp-text-secondary font-bold">{{ fmt(rankPopup.total) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Customer Nature Popup Modal ── -->
    <Teleport to="body">
      <div v-if="naturePopup" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4" @click.self="naturePopup = null">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[80vh]">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border flex-shrink-0">
            <div>
              <p class="text-mp-text-secondary font-semibold text-lg">{{ natureLabel(naturePopup.label) }}</p>
              <p class="text-xs mt-0.5" :class="natureText(naturePopup.label)">
                {{ naturePopup.count }} customer(s) — Total: {{ fmt(naturePopup.total_sales) }}
              </p>
            </div>
            <button @click="naturePopup = null"
              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-mp-muted transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div v-if="naturePopup.is_past_period" class="px-6 py-2.5 bg-mp-warning/20 border-b border-mp-warning/40 text-xs text-mp-warning">
            These customers had no sales in the selected period — figures shown are from {{ naturePopup.sales_period_year }}, the last year they were active, so you can see the revenue being lost.
          </div>
          <!-- Table -->
          <div class="overflow-y-auto flex-1">
            <table class="w-full text-sm">
              <thead class="sticky top-0 bg-mp-card">
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">#</th>
                  <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">Customer Name</th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">
                    {{ naturePopup.is_past_period ? `Sales in ${naturePopup.sales_period_year}` : 'Net Sales Value' }}
                  </th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">% of Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <tr v-for="(customer, i) in naturePopup.customers" :key="customer.name" class="hover:bg-mp-card-hover/50">
                  <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                  <td class="px-6 py-3 text-mp-text-secondary">{{ customer.name }}</td>
                  <td class="px-6 py-3 text-right text-mp-success font-semibold">{{ fmt(customer.sales) }}</td>
                  <td class="px-6 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <div class="w-12 h-1.5 bg-mp-card-hover rounded-full overflow-hidden">
                        <div class="h-full bg-mp-teal rounded-full" :style="`width:${customer.percentage}%`"></div>
                      </div>
                      <span class="text-mp-muted text-xs w-12 text-right">{{ customer.percentage }}%</span>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-mp-border bg-mp-card-hover/60">
                  <td colspan="2" class="px-6 py-3 text-mp-text-secondary font-bold">Total</td>
                  <td class="px-6 py-3 text-right text-mp-text-secondary font-bold">{{ fmt(naturePopup.total_sales) }}</td>
                  <td class="px-6 py-3 text-right text-mp-muted font-semibold">
                    {{ naturePopup.customers.reduce((s, c) => s + c.percentage, 0).toFixed(2) }}%
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DimensionMultiSelect from '@/Components/DimensionMultiSelect.vue'
import { generateDistinctColors, shadeColor } from '@/Utils/chartColors'
import axios from 'axios'

const props = defineProps({
  company:         Object,
  hasData:         Boolean,
  fields:          { type: Object, default: () => ({}) },
  dimensionFields: { type: Object, default: () => ({}) },
  metricFields:    { type: Object, default: () => ({}) },
  reportTypes:     { type: Array,  default: () => [] },
})

const running     = ref(false)
const exporting   = ref(false)
const result      = ref(null)
const rankPopup   = ref(null)
const naturePopup = ref(null)
const expanded    = ref(new Set())
const chartCanvas = ref(null)
let chartInstance = null

// Chart.js loaded from CDN
let Chart = null
async function loadChart() {
  if (Chart) return Chart
  await new Promise((resolve, reject) => {
    if (window.Chart) { Chart = window.Chart; resolve(); return }
    const s = document.createElement('script')
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
    s.onload = () => { Chart = window.Chart; resolve() }
    s.onerror = reject
    document.head.appendChild(s)
  })
  return Chart
}

// ── Palette ──
const COLORS = [
  '#00b4c8','#10b981','#f59e0b','#ef4444','#c9a84c',
  '#00b4c8','#c9a84c','#10b981','#f59e0b','#00b4c8',
  '#00b4c8','#c9a84c','#f59e0b','#00b4c8','#c9a84c',
]
function alpha(hex, a) {
  const r = parseInt(hex.slice(1,3),16)
  const g = parseInt(hex.slice(3,5),16)
  const b = parseInt(hex.slice(5,7),16)
  return `rgba(${r},${g},${b},${a})`
}

// ── Render chart after result loads ──
watch(result, async (val) => {
  if (!val) { destroyChart(); return }
  await nextTick()
  await loadChart()
  await nextTick()
  renderChart(val)
})

function destroyChart() {
  if (chartInstance) { chartInstance.destroy(); chartInstance = null }
}

function chartDefaults() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { labels: { color: '#64748b', font: { size: 11 } } },
      tooltip: {
        callbacks: {
          label: ctx => ' ' + Number(ctx.raw).toLocaleString('en-US', { maximumFractionDigits: 2 })
        }
      }
    },
    scales: {
      x: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: '#112240' } },
      y: {
        ticks: {
          color: '#64748b', font: { size: 10 },
          callback: v => Number(v).toLocaleString('en-US', { maximumFractionDigits: 0 })
        },
        grid: { color: '#112240' }
      }
    }
  }
}

function renderChart(data) {
  destroyChart()
  if (!chartCanvas.value) return
  const ctx = chartCanvas.value.getContext('2d')

  if (data.type === 'single_dimension') {
    // Top 20 horizontal bar
    const rows = [...data.rows].slice(0, 20)
    chartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: rows.map(r => r.label),
        datasets: [{
          label: props.metricFields[data.metric] || data.metric,
          data: rows.map(r => parseFloat(r.value) || 0),
          backgroundColor: rows.map((_, i) => alpha(COLORS[i % COLORS.length], 0.85)),
          borderColor: rows.map((_, i) => COLORS[i % COLORS.length]),
          borderWidth: 1,
          borderRadius: 4,
        }]
      },
      options: {
        ...chartDefaults(),
        indexAxis: 'y',
        plugins: {
          ...chartDefaults().plugins,
          legend: { display: false },
        }
      }
    })

  } else if (data.type === 'matrix') {
    // Stacked bar: rows = dim1, stacks = dim2 columns
    const labels = data.rows.map(r => r.label)
    const datasets = (data.columns || []).map((col, i) => ({
      label: col,
      data: data.rows.map(r => r[col] || 0),
      backgroundColor: alpha(COLORS[i % COLORS.length], 0.8),
      borderColor: COLORS[i % COLORS.length],
      borderWidth: 1,
      borderRadius: 2,
    }))
    chartInstance = new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets },
      options: {
        ...chartDefaults(),
        scales: {
          x: { stacked: true, ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: '#112240' } },
          y: { stacked: true, ticks: { color: '#64748b', font: { size: 10 }, callback: v => Number(v).toLocaleString('en-US') }, grid: { color: '#112240' } }
        }
      }
    })

  } else if (data.type === 'trend') {
    const labels = data.rows.map(r => r.period)
    const values = data.rows.map(r => parseFloat(r.value) || 0)
    chartInstance = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: props.metricFields[data.metric] || data.metric,
          data: values,
          borderColor: '#00b4c8',
          backgroundColor: alpha('#00b4c8', 0.1),
          pointBackgroundColor: '#00b4c8',
          pointBorderColor: '#fff',
          pointRadius: 5,
          pointHoverRadius: 7,
          fill: true,
          tension: 0.3,
        }]
      },
      options: chartDefaults()
    })

  } else if (data.type === 'two_factors_trend') {
    // One line per parent row
    const labels = data.periods || []
    const datasets = (data.rows || []).map((row, i) => ({
      label: row.label,
      data: labels.map(p => row.cells[p]?.value || 0),
      borderColor: COLORS[i % COLORS.length],
      backgroundColor: alpha(COLORS[i % COLORS.length], 0.08),
      pointBackgroundColor: COLORS[i % COLORS.length],
      pointBorderColor: '#fff',
      pointRadius: 4,
      fill: false,
      tension: 0.3,
    }))
    chartInstance = new Chart(ctx, {
      type: 'line',
      data: { labels, datasets },
      options: chartDefaults()
    })

  } else if (data.type === 'period_comparison') {
    const rows = data.rows || []
    const periods = data.periods || []
    const labels = rows.map(r => r.label)
    const datasets = periods.map((p, pi) => ({
      label: `Period ${pi + 1} (${p.from} → ${p.to})`,
      data: rows.map(r => parseFloat(r.values?.[pi]) || 0),
      backgroundColor: alpha(COLORS[pi % COLORS.length], 0.8),
      borderColor: COLORS[pi % COLORS.length],
      borderWidth: 1,
      borderRadius: 3,
    }))
    chartInstance = new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets },
      options: chartDefaults()
    })

  } else if (data.type === 'customer_nature') {
    const cats  = Object.values(data.categories || {})
    const colors = generateDistinctColors(cats.length)
    const total  = cats.reduce((s, c) => s + (c.count || 0), 0)

    const gradientDonut = {
      id: 'gradientDonutCustomerNature',
      beforeDraw(chart) {
        const meta = chart.getDatasetMeta(0)
        meta.data.forEach((arc, i) => {
          const { x: cx, y: cy, outerRadius: outer, innerRadius: inner } = arc
          const base = colors[i]
          const grad = chart.ctx.createRadialGradient(cx, cy, inner, cx, cy, outer)
          grad.addColorStop(0, shadeColor(base, 35))
          grad.addColorStop(0.55, base)
          grad.addColorStop(1, shadeColor(base, -30))
          arc.options.backgroundColor = grad
        })
      },
      beforeDatasetsDraw(chart) {
        chart.ctx.save()
        chart.ctx.shadowColor = 'rgba(0,0,0,0.35)'
        chart.ctx.shadowBlur = 10
        chart.ctx.shadowOffsetY = 5
      },
      afterDatasetsDraw(chart) {
        chart.ctx.restore()
      },
    }

    const centerTotal = {
      id: 'centerTotalCustomerNature',
      afterDraw(chart) {
        const { ctx: c, chartArea } = chart
        const cx = (chartArea.left + chartArea.right) / 2
        const cy = (chartArea.top + chartArea.bottom) / 2
        c.save()
        c.textAlign = 'center'
        c.textBaseline = 'middle'
        c.fillStyle = '#8a94a6'
        c.font = '10px sans-serif'
        c.fillText('TOTAL', cx, cy - 10)
        c.fillStyle = '#e2e8f0'
        c.font = 'bold 18px sans-serif'
        c.fillText(total.toLocaleString('en-US'), cx, cy + 10)
        c.restore()
      },
    }

    chartInstance = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: cats.map(c => natureLabel(c.label)),
        datasets: [{
          data: cats.map(c => c.count),
          backgroundColor: colors,
          borderColor: '#111a2e',
          borderWidth: 2,
          hoverOffset: 10,
          cutout: '58%',
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right', labels: { color: '#64748b', font: { size: 11 }, padding: 16 } },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.label}: ${ctx.raw} (${(ctx.raw / total * 100).toFixed(1)}%)`
            }
          }
        },
        animation: { animateRotate: true, animateScale: true }
      },
      plugins: [gradientDonut, centerTotal]
    })

  } else if (data.type === 'invoice_analysis' && data.view === 'snapshot') {
    const buckets = data.distribution || []
    chartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: buckets.map(b => b.label),
        datasets: [{
          label: 'Invoice Count',
          data: buckets.map(b => b.count),
          backgroundColor: buckets.map((_, i) => alpha(COLORS[i % COLORS.length], 0.85)),
          borderColor: buckets.map((_, i) => COLORS[i % COLORS.length]),
          borderWidth: 1,
          borderRadius: 4,
        }]
      },
      options: {
        ...chartDefaults(),
        plugins: { ...chartDefaults().plugins, legend: { display: false } }
      }
    })

  } else if (data.type === 'invoice_analysis' && data.view === 'by_dimension') {
    const rows = [...data.rows].slice(0, 20)
    chartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: rows.map(r => r.label),
        datasets: [{
          label: 'Total Value',
          data: rows.map(r => parseFloat(r.total_value) || 0),
          backgroundColor: rows.map((_, i) => alpha(COLORS[i % COLORS.length], 0.85)),
          borderColor: rows.map((_, i) => COLORS[i % COLORS.length]),
          borderWidth: 1,
          borderRadius: 4,
        }]
      },
      options: {
        ...chartDefaults(),
        indexAxis: 'y',
        plugins: { ...chartDefaults().plugins, legend: { display: false } }
      }
    })
  }
}

// ── For ranking: exclude branch and non-product fields from "Rank By" selector
const RANK_EXCLUDED = ['branch', 'date', 'document_number', 'measurement_unit', 'country', 'sales_channel', 'document_type']
const rankByFields = computed(() =>
  Object.fromEntries(Object.entries(props.dimensionFields).filter(([k]) => !RANK_EXCLUDED.includes(k)))
)

const defaultDim1    = Object.keys(props.dimensionFields)[0] ?? 'branch'
const defaultRankDim = computed(() => Object.keys(rankByFields.value)[0] ?? 'product_category')

const params = ref({
  report_type:    '',
  date_from:      '',
  date_to:        '',
  period:         'monthly',
  metric:         Object.keys(props.metricFields)[0] ?? 'net_sales_value',
  dimension1:     defaultDim1,
  dimension2:     Object.keys(props.dimensionFields)[1] ?? 'product_category',
  selected_items: [],  // Period Comparison: specific items for dimension1, or [] = Top 300 + Others
  dim1_items:     [],  // Two Factors Trend / Matrix / Invoice Analysis: specific items, or [] = Top 300 + Others
  dim2_items:     [],  // Two Factors Trend / Matrix: specific Factor 2 / column items, or [] = Top N + Others
  invoice_view:      'snapshot', // Invoice Analysis: 'snapshot' | 'by_dimension' | 'large_invoices'
  invoice_threshold: 1000000,    // Invoice Analysis: large-invoice threshold
})

// ── Period Comparison: 2-5 periods ──
const periodsCount  = ref(2)
const extraPeriods  = ref([{ from: '', to: '' }]) // periodsCount - 1 entries

watch(periodsCount, (n) => {
  const needed = n - 1
  while (extraPeriods.value.length < needed) extraPeriods.value.push({ from: '', to: '' })
  while (extraPeriods.value.length > needed) extraPeriods.value.pop()
})

const allPeriods = computed(() => [
  { from: params.value.date_from, to: params.value.date_to },
  ...extraPeriods.value,
])
// The multi-selector sorts/filters based on the LATEST period the user picked.
const latestPeriod = computed(() => allPeriods.value[allPeriods.value.length - 1] || { from: '', to: '' })

// Auto-switch dimension1 when user selects ranking report type
watch(() => params.value.report_type, (newType) => {
  if (newType === 'ranking') {
    params.value.dimension1 = defaultRankDim.value
  } else if (params.value.dimension1 === defaultRankDim.value) {
    params.value.dimension1 = defaultDim1
  }
})

// ── Context-sensitive UI ──
const showPeriodSelector = computed(() =>
  ['trend', 'two_factors_trend'].includes(params.value.report_type)
)
const showDimension1 = computed(() =>
  ['single_dimension', 'matrix', 'ranking', 'period_comparison', 'two_factors_trend'].includes(params.value.report_type)
)
const showDimension2 = computed(() =>
  ['matrix', 'two_factors_trend'].includes(params.value.report_type)
)
const dimension1Label = computed(() => {
  const map = {
    matrix:            'Dimension 1 (Rows)',
    two_factors_trend: 'Factor 1 (Parent Rows)',
    ranking:           'Rank By (Product/Category/Item)',
  }
  return map[params.value.report_type] ?? 'Dimension'
})
const dimension2Label = computed(() => {
  const map = {
    matrix:            'Dimension 2 (Columns)',
    two_factors_trend: 'Factor 2 (Sub Rows)',
  }
  return map[params.value.report_type] ?? 'Dimension 2'
})

const resultTitle = computed(() => {
  const rt = props.reportTypes.find(r => r.key === params.value.report_type)
  return rt ? rt.label : 'Report Results'
})

// ── Build the payload actually sent to the backend ──
function buildPayload() {
  const payload = { ...params.value }
  if (params.value.report_type === 'period_comparison') {
    payload.periods = allPeriods.value
  }
  return payload
}

// ── Run report ──
async function runReport() {
  running.value = true
  result.value  = null
  expanded.value = new Set()
  try {
    const { data } = await axios.post(
      route('sales.run-report', props.company.id),
      buildPayload()
    )
    result.value = data
  } catch (e) {
    console.error(e)
  } finally {
    running.value = false
  }
}

// ── Export to Excel ──
async function exportToExcel() {
  exporting.value = true
  try {
    const response = await axios.post(
      route('sales.export-report', props.company.id),
      buildPayload(),
      { responseType: 'blob' }
    )
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    })
    const url  = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href  = url
    const rt   = props.reportTypes.find(r => r.key === params.value.report_type)
    link.download = `${props.company.name}_${rt?.label ?? 'report'}_${params.value.date_from}_${params.value.date_to}.xlsx`
    link.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    console.error(e)
  } finally {
    exporting.value = false
  }
}

// ── Customer Nature popup ──
function openNaturePopup(cat) {
  naturePopup.value = cat
}

// ── Ranking popup ──
function openRankPopup(branch, rank, rankData) {
  rankPopup.value = {
    branch,
    rank,
    count:    rankData.count,
    products: rankData.products,
    total:    rankData.total,
  }
}

// ── Two factors expand/collapse ──
function toggleExpand(label) {
  const s = new Set(expanded.value)
  s.has(label) ? s.delete(label) : s.add(label)
  expanded.value = s
}

// ── Helpers ──
function fmt(val) {
  const n = parseFloat(val) || 0
  return n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function getShare(value, rows) {
  const total = rows.reduce((s, r) => s + parseFloat(r.value || 0), 0)
  return total > 0 ? (parseFloat(value) / total) * 100 : 0
}

// Running total of % Share down the (already largest→smallest sorted)
// rows — row i shows what % of the total the top (i+1) rows make up.
const accumulatedShares = computed(() => {
  if (!result.value || result.value.type !== 'single_dimension') return []
  const rows = result.value.rows || []
  let running = 0
  return rows.map(r => {
    running += getShare(r.value, rows)
    return running
  })
})

// Price Per Unit / Service Provider Birth Year aren't meaningfully
// additive — Total rows average these instead of summing them, matching
// the backend's aggregation choice.
const NON_ADDITIVE_METRICS = ['price_per_unit', 'service_provider_birth_year']
function aggregate(values, metric) {
  const nums = values.map(v => parseFloat(v) || 0)
  if (nums.length === 0) return 0
  const sum = nums.reduce((s, v) => s + v, 0)
  return NON_ADDITIVE_METRICS.includes(metric) ? sum / nums.length : sum
}

function getTrendChange(rows, i) {
  const prev = parseFloat(rows[i - 1]?.value || 0)
  const curr = parseFloat(rows[i]?.value || 0)
  if (prev === 0) return null
  return parseFloat(((curr - prev) / prev * 100).toFixed(2))
}

function periodComparisonTotalChange(pi) {
  if (!result.value || result.value.type !== 'period_comparison') return null
  const rows = result.value.rows || []
  const prevTotal = aggregate(rows.map(r => r.values[pi - 1]), result.value.metric)
  const currTotal = aggregate(rows.map(r => r.values[pi]), result.value.metric)
  if (prevTotal === 0) return null
  return parseFloat(((currTotal - prevTotal) / prevTotal * 100).toFixed(2))
}

function natureBg(label) {
  const map = { new: 'bg-mp-teal-subtle/30 border-mp-teal', repeating: 'bg-mp-success/30 border-mp-success', active: 'bg-mp-success/30 border-mp-success', stop: 'bg-mp-warning/30 border-mp-warning', dead: 'bg-mp-danger/30 border-mp-danger', stop_reactivated: 'bg-mp-gold/30 border-mp-gold', dead_reactivated: 'bg-purple-500/20 border-purple-500' }
  return map[label] || 'bg-mp-card-hover border-mp-border'
}
function natureText(label) {
  const map = { new: 'text-white', repeating: 'text-mp-success', active: 'text-mp-success', stop: 'text-mp-warning', dead: 'text-mp-danger', stop_reactivated: 'text-white', dead_reactivated: 'text-purple-400' }
  return map[label] || 'text-mp-muted'
}
function natureLabel(label) {
  const map = { new: 'New Customers', repeating: 'Repeating', active: 'Active (3+ yrs)', stop: 'Stop', dead: 'Dead', stop_reactivated: 'Stop Reactivated', dead_reactivated: 'Dead Reactivated' }
  return map[label] || label
}
</script>