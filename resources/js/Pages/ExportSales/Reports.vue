<template>
  <Head :title="`Export Sales Reports — ${company.name}`" />
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
              <div class="flex items-center gap-3 mb-1">
                <span class="text-xs font-semibold text-mp-success uppercase tracking-widest bg-mp-success/40 border border-mp-success/50 px-2.5 py-1 rounded-full">
                  Export Sales
                </span>
              </div>
              <h1 class="text-2xl font-bold text-mp-text-secondary">Export Sales Reports</h1>
              <p class="text-mp-muted text-sm mt-1">Build and run analytical reports on your export & trade data</p>
            </div>
            <Link :href="`/companies/${company.id}/export-sales/upload`"
              class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page text-mp-text text-sm font-medium px-4 py-2 rounded-lg border border-mp-border transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
              Upload Data
            </Link>
          </div>
        </div>
      </div>

      <!-- No data -->
      <div v-if="!hasData" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-mp-warning/30 border border-mp-warning/50 rounded-xl p-6 text-center">
          <svg class="w-10 h-10 text-mp-warning mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <p class="text-mp-warning font-medium mb-1">No Export Sales Data Yet</p>
          <p class="text-mp-warning text-sm mb-4">Upload your export data first before running reports</p>
          <Link :href="`/companies/${company.id}/export-sales/upload`"
            class="bg-mp-success hover:bg-mp-success text-mp-text-secondary text-sm font-medium px-6 py-2.5 rounded-lg transition-colors inline-block">
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
                    ? 'border-mp-success bg-mp-success/20 text-mp-text-secondary'
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
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success" />
              </div>
              <div v-if="params.report_type !== 'period_comparison'">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Date To</label>
                <input v-model="params.date_to" type="date"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success" />
              </div>
              <div v-if="showPeriodSelector">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period Group</label>
                <select v-model="params.period"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success">
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly (Q1–Q4)</option>
                  <option value="semi_annually">Semi-Annually (H1–H2)</option>
                  <option value="annually">Annually</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Metric</label>
                <select v-model="params.metric"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success">
                  <option v-for="(label, key) in metricFields" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
            </div>

            <!-- Dimension selectors -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4" v-if="showDimension1">
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  {{ dimension1Label }}
                </label>
                <select v-model="params.dimension1"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success">
                  <option v-for="(label, key) in dimensionFields" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
              <div v-if="showDimension2">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  {{ dimension2Label }}
                </label>
                <select v-model="params.dimension2"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success">
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
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success">
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
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period 1 To</label>
                    <input v-model="params.date_to" type="date"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success" />
                  </div>
                </div>
                <div v-for="(p, i) in extraPeriods" :key="i" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period {{ i + 2 }} From</label>
                    <input v-model="p.from" type="date"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Period {{ i + 2 }} To</label>
                    <input v-model="p.to" type="date"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Top N (single dimension only) -->
            <div v-if="params.report_type === 'single_dimension'" class="max-w-xs">
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">Top N Results</label>
              <input v-model.number="params.top_n" type="number" min="1" max="500"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-success" />
            </div>

            <!-- Run button -->
            <div class="flex items-center gap-3 pt-2">
              <button @click="runReport"
                :disabled="running || !params.report_type || !params.date_from || !params.date_to"
                class="flex items-center gap-2 bg-mp-success hover:bg-mp-success disabled:opacity-50 disabled:cursor-not-allowed text-mp-text-secondary text-sm font-medium px-8 py-3 rounded-lg transition-colors">
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
            <button @click="exportToExcel" :disabled="exporting"
              class="flex items-center gap-2 bg-mp-success hover:bg-mp-success disabled:opacity-50 text-mp-text-secondary text-sm font-medium px-4 py-2 rounded-lg transition-colors">
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

          <!-- Chart canvas -->
          <div v-if="showChart" class="px-6 pt-6 pb-2">
            <div class="bg-mp-card-hover/40 rounded-xl p-4" style="height:280px;">
              <canvas ref="chartCanvas"></canvas>
            </div>
          </div>

          <!-- Single Dimension -->
          <div v-if="result.type === 'single_dimension'" class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">#</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">{{ fields[result.dimension] || result.dimension }}</th>
                  <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">{{ metricFields[result.metric] || result.metric }}</th>
                  <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Transactions</th>
                  <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">% Share</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Bar</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in result.rows" :key="i" class="border-b border-mp-border/50 hover:bg-mp-card-hover/30 transition-colors">
                  <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                  <td class="px-6 py-3 text-mp-text-secondary font-medium">
                    {{ row.label }}<span v-if="row.is_other" class="text-mp-muted font-normal"> ({{ row.other_count }} items)</span>
                  </td>
                  <td class="px-6 py-3 text-right text-mp-success font-mono text-xs">{{ fmt(row.value) }}</td>
                  <td class="px-6 py-3 text-right text-mp-muted text-xs">{{ Number(row.transactions).toLocaleString() }}</td>
                  <td class="px-6 py-3 text-right text-mp-muted text-xs">{{ getShare(row.value, result.rows).toFixed(1) }}%</td>
                  <td class="px-6 py-3 w-32">
                    <div class="bg-mp-card-hover rounded-full h-2">
                      <div class="bg-mp-success h-2 rounded-full" :style="`width:${getShare(row.value, result.rows)}%`"></div>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="bg-mp-card-hover/60">
                  <td colspan="2" class="px-6 py-3 text-mp-text-secondary font-bold text-xs uppercase tracking-widest">Total</td>
                  <td class="px-6 py-3 text-right text-mp-success font-bold font-mono text-xs">{{ fmt(result.rows.reduce((s,r)=>s+parseFloat(r.value||0),0)) }}</td>
                  <td class="px-6 py-3 text-right text-mp-text font-bold text-xs">{{ result.rows.reduce((s,r)=>s+parseInt(r.transactions||0),0).toLocaleString() }}</td>
                  <td colspan="2" class="px-6 py-3 text-right text-mp-muted text-xs">100%</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- Trend -->
          <div v-else-if="result.type === 'trend'" class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">#</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Period</th>
                  <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">{{ metricFields[result.metric] || result.metric }}</th>
                  <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">vs Previous</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(row, i) in result.rows" :key="i">
                  <tr class="border-b border-mp-border/50 hover:bg-mp-card-hover/30 transition-colors">
                    <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                    <td class="px-6 py-3 text-mp-text-secondary font-mono text-xs">{{ row.period }}</td>
                    <td class="px-6 py-3 text-right text-mp-success font-mono text-xs">{{ fmt(row.value) }}</td>
                    <td class="px-6 py-3 text-right text-xs">
                      <span v-if="i > 0" :class="getTrendChange(result.rows, i) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                        {{ getTrendChange(result.rows, i) >= 0 ? '▲' : '▼' }} {{ Math.abs(getTrendChange(result.rows, i)) }}%
                      </span>
                      <span v-else class="text-mp-muted">—</span>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <!-- Matrix -->
          <div v-else-if="result.type === 'matrix'" class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-3">#</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-3 sticky left-0 bg-mp-card">
                    {{ fields[result.dim1] || result.dim1 }}
                  </th>
                  <th v-for="col in result.columns" :key="col" class="text-right text-xs font-semibold text-white uppercase tracking-widest px-4 py-3 whitespace-nowrap">
                    {{ col }}
                  </th>
                  <th class="text-right text-xs font-semibold text-mp-success uppercase tracking-widest px-4 py-3">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in result.rows" :key="i" class="border-b border-mp-border/50 hover:bg-mp-card-hover/30 transition-colors">
                  <td class="px-4 py-2.5 text-mp-muted text-xs">{{ i + 1 }}</td>
                  <td class="px-4 py-2.5 text-mp-text-secondary font-medium text-xs sticky left-0 bg-mp-card">{{ row.label }}</td>
                  <td v-for="col in result.columns" :key="col" class="px-4 py-2.5 text-right text-mp-text font-mono text-xs">
                    {{ fmt(row[col] || 0) }}
                  </td>
                  <td class="px-4 py-2.5 text-right text-mp-success font-bold font-mono text-xs">
                    {{ fmt(result.columns.reduce((s,c)=>s+(row[c]||0), 0)) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Period Comparison -->
          <div v-else-if="result.type === 'period_comparison'" class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">#</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Label</th>
                  <template v-for="(p, pi) in result.periods" :key="pi">
                    <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">
                      Period {{ pi + 1 }}<br><span class="font-normal text-mp-muted">{{ p.from }} → {{ p.to }}</span>
                    </th>
                    <th v-if="pi > 0" class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Change %</th>
                  </template>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in result.rows" :key="i"
                  class="border-b border-mp-border/50 hover:bg-mp-card-hover/30 transition-colors"
                  :class="row.is_other ? 'italic' : ''">
                  <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                  <td class="px-6 py-3 text-mp-text-secondary font-medium">
                    {{ row.label }}<span v-if="row.is_other" class="text-mp-muted font-normal"> ({{ row.other_count }} items)</span>
                  </td>
                  <template v-for="(p, pi) in result.periods" :key="pi">
                    <td class="px-6 py-3 text-right text-mp-text font-mono text-xs">{{ fmt(row.values[pi]) }}</td>
                    <td v-if="pi > 0" class="px-6 py-3 text-right text-xs">
                      <span v-if="row.changes[pi] !== null" :class="row.changes[pi] >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                        {{ row.changes[pi] >= 0 ? '▲' : '▼' }} {{ Math.abs(row.changes[pi]) }}%
                      </span>
                      <span v-else class="text-mp-muted">N/A</span>
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Two Factors Trend -->
          <div v-else-if="result.type === 'two_factors_trend'" class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-3 sticky left-0 bg-mp-card">
                    {{ fields[result.dim1] || result.dim1 }} / {{ fields[result.dim2] || result.dim2 }}
                  </th>
                  <th v-for="p in result.periods" :key="p" class="text-right text-xs font-semibold text-white uppercase tracking-widest px-3 py-3 whitespace-nowrap">
                    {{ p }}
                  </th>
                  <th class="text-right text-xs font-semibold text-mp-success uppercase tracking-widest px-4 py-3">Total</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(parent, pi) in result.rows" :key="pi">
                  <!-- Parent row -->
                  <tr class="border-b border-mp-border bg-mp-card-hover/50 cursor-pointer hover:bg-mp-page/50 transition-colors"
                    @click="toggleExpand(parent.label)">
                    <td class="px-4 py-2.5 text-mp-text-secondary font-bold text-xs sticky left-0 bg-mp-card-hover">
                      <span class="text-mp-muted mr-1">{{ pi + 1 }}.</span>
                      <span class="mr-1">{{ expanded.has(parent.label) ? '▾' : '▸' }}</span>
                      <span :class="parent.is_other ? 'italic' : ''">{{ parent.label }}<span v-if="parent.is_other" class="text-mp-muted font-normal"> ({{ parent.other_count }} items)</span></span>
                    </td>
                    <td v-for="p in result.periods" :key="p" class="px-3 py-2.5 text-right font-bold font-mono text-xs">
                      <span class="text-mp-text-secondary">{{ fmt(parent.cells[p]?.value || 0) }}</span>
                      <span v-if="parent.cells[p]?.gr" :class="parent.cells[p].gr >= 0 ? 'text-mp-success' : 'text-mp-danger'" class="block text-xs">
                        {{ parent.cells[p].gr >= 0 ? '▲' : '▼' }}{{ Math.abs(parent.cells[p].gr) }}%
                      </span>
                    </td>
                    <td class="px-4 py-2.5 text-right text-mp-success font-bold font-mono text-xs">{{ fmt(parent.total) }}</td>
                  </tr>
                  <!-- Child rows -->
                  <template v-if="expanded.has(parent.label)">
                    <tr v-for="(child, ci) in parent.children" :key="ci"
                      class="border-b border-mp-border/50 hover:bg-mp-card-hover/20 transition-colors">
                      <td class="px-4 py-2 pl-8 text-mp-text text-xs sticky left-0 bg-mp-card" :class="child.is_other ? 'italic' : ''">
                        <span class="text-mp-muted">{{ ci + 1 }}.</span>
                        {{ child.label }}<span v-if="child.is_other" class="text-mp-muted"> ({{ child.other_count }} items)</span>
                      </td>
                      <td v-for="p in result.periods" :key="p" class="px-3 py-2 text-right text-mp-text font-mono text-xs">
                        {{ fmt(child.cells[p]?.value || 0) }}
                      </td>
                      <td class="px-4 py-2 text-right text-mp-text font-mono text-xs">{{ fmt(child.total) }}</td>
                    </tr>
                  </template>
                </template>
              </tbody>
            </table>
          </div>

          <!-- PO Status Summary -->
          <div v-else-if="result.type === 'po_status'" class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">#</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">PO Status</th>
                  <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Count</th>
                  <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Total Value</th>
                  <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">% Share</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Bar</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in result.rows" :key="i" class="border-b border-mp-border/50 hover:bg-mp-card-hover/30 transition-colors">
                  <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                  <td class="px-6 py-3 text-mp-text-secondary font-medium">{{ row.status }}</td>
                  <td class="px-6 py-3 text-right text-mp-text font-mono text-xs">{{ Number(row.count).toLocaleString() }}</td>
                  <td class="px-6 py-3 text-right text-mp-success font-mono text-xs">{{ fmt(row.value) }}</td>
                  <td class="px-6 py-3 text-right text-mp-muted text-xs">{{ getShare(row.value, result.rows).toFixed(1) }}%</td>
                  <td class="px-6 py-3 w-32">
                    <div class="bg-mp-card-hover rounded-full h-2">
                      <div class="bg-mp-success h-2 rounded-full" :style="`width:${getShare(row.value, result.rows)}%`"></div>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="bg-mp-card-hover/60">
                  <td class="px-6 py-3"></td>
                  <td class="px-6 py-3 text-mp-text-secondary font-bold text-xs uppercase tracking-widest">Total</td>
                  <td class="px-6 py-3 text-right text-mp-text font-bold font-mono text-xs">{{ result.rows.reduce((s,r)=>s+parseInt(r.count||0),0).toLocaleString() }}</td>
                  <td class="px-6 py-3 text-right text-mp-success font-bold font-mono text-xs">{{ fmt(result.rows.reduce((s,r)=>s+parseFloat(r.value||0),0)) }}</td>
                  <td colspan="2" class="px-6 py-3 text-right text-mp-muted text-xs">100%</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- Ranking -->
          <div v-else-if="result.type === 'ranking'" class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">#</th>
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Destination</th>
                  <th v-for="r in result.num_ranks" :key="r" class="text-center text-xs font-semibold text-white uppercase tracking-widest px-4 py-3">
                    Rank {{ r }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in result.rows" :key="i" class="border-b border-mp-border/50 hover:bg-mp-card-hover/30 transition-colors">
                  <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                  <td class="px-6 py-3 text-mp-text-secondary font-medium">{{ row.branch }}</td>
                  <td v-for="r in result.num_ranks" :key="r" class="px-4 py-3 text-center">
                    <div v-if="row.ranks[r]?.count > 0">
                      <button v-for="prod in row.ranks[r].products" :key="prod.product"
                        @click="rankPopup = { country: row.branch, rank: r, products: row.ranks[r].products, total: row.ranks[r].total }"
                        class="text-xs text-white hover:text-mp-text-secondary underline underline-offset-2 transition-colors block w-full text-center">
                        {{ prod.product }}
                      </button>
                    </div>
                    <span v-else class="text-mp-muted text-xs">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Customer Nature -->
          <div v-else-if="result.type === 'customer_nature'" class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div v-for="(cat, key) in result.categories" :key="key"
                :class="natureBg(cat.label)"
                class="rounded-xl border p-4 cursor-pointer hover:opacity-90 transition-opacity"
                @click="naturePopup = cat">
                <p class="text-xs font-semibold uppercase tracking-widest mb-1" :class="natureText(cat.label)">
                  {{ natureLabel(cat.label) }}
                </p>
                <p class="text-4xl font-bold text-mp-text-secondary">{{ cat.count }}</p>
                <p class="text-xs mt-2" :class="natureText(cat.label)">{{ fmt(cat.total_sales) }}</p>
                <p class="text-xs text-mp-muted mt-1">Click to view details</p>
              </div>
            </div>
            <div class="mt-4 flex items-center justify-between bg-mp-card-hover/50 border-t-2 border-mp-border rounded-lg px-4 py-3">
              <span class="text-mp-text-secondary font-bold text-sm">Total</span>
              <span class="text-mp-text-secondary font-bold text-sm">{{ fmt(result.grand_total) }}</span>
            </div>
          </div>

        </div>

      </div>
    </div>

    <!-- Rank popup -->
    <Teleport to="body">
      <div v-if="rankPopup" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4" @click.self="rankPopup = null">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-md shadow-2xl p-6">
          <div class="flex items-center justify-between mb-4">
            <div>
              <p class="text-mp-text-secondary font-bold">{{ rankPopup.country }}</p>
              <p class="text-mp-muted text-sm">Rank {{ rankPopup.rank }}</p>
            </div>
            <button @click="rankPopup = null" class="text-mp-muted hover:text-mp-text-secondary">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="space-y-2">
            <div v-for="(prod, i) in rankPopup.products" :key="prod.product" class="flex items-center justify-between bg-mp-card-hover rounded-lg px-4 py-2.5">
              <span class="text-mp-muted text-xs w-6">{{ i + 1 }}.</span>
              <span class="text-mp-text-secondary text-sm flex-1">{{ prod.product }}</span>
              <span class="text-mp-success font-mono text-xs">{{ fmt(prod.value) }}</span>
            </div>
          </div>
          <div class="mt-3 pt-3 border-t border-mp-border flex justify-between">
            <span class="text-mp-muted text-sm font-semibold">Total</span>
            <span class="text-mp-success font-bold font-mono text-sm">{{ fmt(rankPopup.total) }}</span>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Customer Nature popup -->
    <Teleport to="body">
      <div v-if="naturePopup" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4" @click.self="naturePopup = null">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[80vh]">
          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border flex-shrink-0">
            <div>
              <p class="text-mp-text-secondary font-semibold text-lg">{{ natureLabel(naturePopup.label) }}</p>
              <p class="text-xs mt-0.5" :class="natureText(naturePopup.label)">
                {{ naturePopup.count }} customer(s) — Total: {{ fmt(naturePopup.total_sales) }}
              </p>
            </div>
            <button @click="naturePopup = null" class="text-mp-muted hover:text-mp-text-secondary">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div v-if="naturePopup.is_past_period" class="px-6 py-2.5 bg-mp-warning/20 border-b border-mp-warning/40 text-xs text-mp-warning">
            These customers had no sales in the selected period — figures shown are from {{ naturePopup.sales_period_year }}, the last year they were active, so you can see the revenue being lost.
          </div>
          <div class="overflow-y-auto flex-1">
            <table class="w-full text-sm">
              <thead class="sticky top-0 bg-mp-card">
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">#</th>
                  <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">Customer Name</th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">
                    {{ naturePopup.is_past_period ? `Value in ${naturePopup.sales_period_year}` : 'Value' }}
                  </th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">% of Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(customer, i) in naturePopup.customers" :key="customer.name" class="border-b border-mp-border/50 hover:bg-mp-card-hover/30">
                  <td class="px-6 py-3 text-mp-muted text-xs">{{ i + 1 }}</td>
                  <td class="px-6 py-3 text-mp-text-secondary">{{ customer.name }}</td>
                  <td class="px-6 py-3 text-right text-mp-success font-mono text-xs">{{ fmt(customer.sales) }}</td>
                  <td class="px-6 py-3 text-right text-mp-muted text-xs">{{ customer.percentage }}%</td>
                </tr>
              </tbody>
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

const running    = ref(false)
const exporting  = ref(false)
const result     = ref(null)
const rankPopup  = ref(null)
const naturePopup= ref(null)
const expanded   = ref(new Set())
const chartCanvas= ref(null)
let chartInstance = null

let Chart = null
async function loadChart() {
  if (Chart) return
  await new Promise((res, rej) => {
    if (window.Chart) { Chart = window.Chart; res(); return }
    const s = document.createElement('script')
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
    s.onload = () => { Chart = window.Chart; res() }
    s.onerror = rej
    document.head.appendChild(s)
  })
}

const COLORS = ['#10b981','#00b4c8','#f59e0b','#ef4444','#c9a84c','#00b4c8','#c9a84c','#10b981','#f59e0b','#00b4c8','#00b4c8','#c9a84c','#f59e0b','#00b4c8','#c9a84c']
function alpha(hex, a) {
  const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16)
  return `rgba(${r},${g},${b},${a})`
}

const showChart = computed(() => result.value && ['single_dimension','trend','matrix','period_comparison','two_factors_trend','po_status','customer_nature'].includes(result.value?.type))

watch(result, async (val) => {
  if (!val) { destroyChart(); return }
  await nextTick()
  await loadChart()
  await nextTick()
  renderChart(val)
})

function destroyChart() { if (chartInstance) { chartInstance.destroy(); chartInstance = null } }

function chartDefaults() {
  return {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { labels: { color: '#64748b', font: { size: 11 } } },
      tooltip: { callbacks: { label: ctx => ' ' + Number(ctx.raw).toLocaleString('en-US', { maximumFractionDigits: 2 }) } }
    },
    scales: {
      x: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: '#112240' } },
      y: { ticks: { color: '#64748b', font: { size: 10 }, callback: v => Number(v).toLocaleString('en-US', { maximumFractionDigits: 0 }) }, grid: { color: '#112240' } }
    }
  }
}

function renderChart(data) {
  destroyChart()
  if (!chartCanvas.value) return
  const ctx = chartCanvas.value.getContext('2d')

  if (data.type === 'single_dimension' || data.type === 'po_status') {
    const rows = data.type === 'po_status' ? data.rows.map(r => ({ label: r.status, value: r.value })) : [...data.rows].slice(0, 20)
    chartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: rows.map(r => r.label),
        datasets: [{ label: 'Value', data: rows.map(r => parseFloat(r.value) || 0), backgroundColor: rows.map((_,i) => alpha(COLORS[i%COLORS.length], 0.85)), borderColor: rows.map((_,i) => COLORS[i%COLORS.length]), borderWidth:1, borderRadius:4 }]
      },
      options: { ...chartDefaults(), indexAxis:'y', plugins:{ ...chartDefaults().plugins, legend:{display:false} } }
    })
  } else if (data.type === 'trend') {
    chartInstance = new Chart(ctx, {
      type: 'line',
      data: { labels: data.rows.map(r => r.period), datasets: [{ label: props.metricFields[data.metric]||data.metric, data: data.rows.map(r => parseFloat(r.value)||0), borderColor:'#10b981', backgroundColor:alpha('#10b981',0.1), pointBackgroundColor:'#10b981', pointBorderColor:'#fff', pointRadius:5, fill:true, tension:0.3 }] },
      options: chartDefaults()
    })
  } else if (data.type === 'matrix') {
    chartInstance = new Chart(ctx, {
      type: 'bar',
      data: { labels: data.rows.map(r=>r.label), datasets: (data.columns||[]).map((col,i) => ({ label:col, data:data.rows.map(r=>r[col]||0), backgroundColor:alpha(COLORS[i%COLORS.length],0.8), borderColor:COLORS[i%COLORS.length], borderWidth:1, borderRadius:2 })) },
      options: { ...chartDefaults(), scales:{ x:{stacked:true,ticks:{color:'#64748b',font:{size:10}},grid:{color:'#112240'}}, y:{stacked:true,ticks:{color:'#64748b',font:{size:10},callback:v=>Number(v).toLocaleString()},grid:{color:'#112240'}} } }
    })
  } else if (data.type === 'two_factors_trend') {
    chartInstance = new Chart(ctx, {
      type: 'line',
      data: { labels: data.periods||[], datasets: (data.rows||[]).map((row,i) => ({ label:row.label, data:(data.periods||[]).map(p=>row.cells[p]?.value||0), borderColor:COLORS[i%COLORS.length], backgroundColor:alpha(COLORS[i%COLORS.length],0.08), pointBackgroundColor:COLORS[i%COLORS.length], pointBorderColor:'#fff', pointRadius:4, fill:false, tension:0.3 })) },
      options: chartDefaults()
    })
  } else if (data.type === 'period_comparison') {
    const rows = data.rows || []
    const periods = data.periods || []
    chartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: rows.map(r => r.label),
        datasets: periods.map((p, pi) => ({
          label: `Period ${pi + 1} (${p.from} → ${p.to})`,
          data: rows.map(r => parseFloat(r.values?.[pi]) || 0),
          backgroundColor: alpha(COLORS[pi % COLORS.length], 0.8),
          borderColor: COLORS[pi % COLORS.length],
          borderWidth: 1,
          borderRadius: 3,
        }))
      },
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
  }
}

const defaultDim = Object.keys(props.dimensionFields)[0] ?? 'destination_country'

const params = ref({
  report_type:  '',
  date_from:    '',
  date_to:      '',
  period:       'monthly',
  metric:       Object.keys(props.metricFields)[0] ?? 'purchase_order_net_value',
  dimension1:   defaultDim,
  dimension2:   Object.keys(props.dimensionFields)[1] ?? 'product_category',
  top_n:        300,
  dim1_items:   [], // Matrix / Two Factors Trend: specific row/Factor 1 items, or [] = automatic Top N + Others
  dim2_items:   [], // Matrix / Two Factors Trend: specific column/Factor 2 items, or [] = automatic Top N + Others
})

// ── Period Comparison: 2-5 periods ──
const periodsCount = ref(2)
const extraPeriods = ref([{ from: '', to: '' }]) // periodsCount - 1 entries

watch(periodsCount, (n) => {
  const needed = n - 1
  while (extraPeriods.value.length < needed) extraPeriods.value.push({ from: '', to: '' })
  while (extraPeriods.value.length > needed) extraPeriods.value.pop()
})

const allPeriods = computed(() => [
  { from: params.value.date_from, to: params.value.date_to },
  ...extraPeriods.value,
])

const showPeriodSelector = computed(() => ['trend','two_factors_trend'].includes(params.value.report_type))
const showDimension1     = computed(() => ['single_dimension','matrix','ranking','period_comparison','two_factors_trend'].includes(params.value.report_type))
const showDimension2     = computed(() => ['matrix','two_factors_trend'].includes(params.value.report_type))
const dimension1Label    = computed(() => ({ matrix:'Dimension 1 (Rows)', two_factors_trend:'Factor 1 (Parent Rows)', ranking:'Rank By (Product/Category)' }[params.value.report_type] ?? 'Dimension'))
const dimension2Label    = computed(() => ({ matrix:'Dimension 2 (Columns)', two_factors_trend:'Factor 2 (Sub Rows)' }[params.value.report_type] ?? 'Dimension 2'))
const resultTitle        = computed(() => props.reportTypes.find(r => r.key === params.value.report_type)?.label ?? 'Report Results')

// ── Build the payload actually sent to the backend ──
function buildPayload() {
  const payload = { ...params.value }
  if (params.value.report_type === 'period_comparison') {
    payload.periods = allPeriods.value
  }
  return payload
}

async function runReport() {
  running.value = true; result.value = null; expanded.value = new Set()
  try {
    const { data } = await axios.post(route('export-sales.run-report', props.company.id), buildPayload())
    result.value = data
  } catch(e) { console.error(e) } finally { running.value = false }
}

async function exportToExcel() {
  exporting.value = true
  try {
    const response = await axios.post(route('export-sales.export-report', props.company.id), buildPayload(), { responseType:'blob' })
    const blob = new Blob([response.data], { type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url  = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    const rt = props.reportTypes.find(r => r.key === params.value.report_type)
    link.download = `${props.company.name}_ExportSales_${rt?.label??'report'}_${params.value.date_from}_${params.value.date_to}.xlsx`
    link.click()
    URL.revokeObjectURL(url)
  } catch(e) { console.error(e) } finally { exporting.value = false }
}

function fmt(val) { return (parseFloat(val)||0).toLocaleString('en-US', { minimumFractionDigits:0, maximumFractionDigits:0 }) }
function getShare(value, rows) { const t = rows.reduce((s,r)=>s+parseFloat(r.value||0),0); return t>0?(parseFloat(value)/t)*100:0 }
function getTrendChange(rows, i) { const p=parseFloat(rows[i-1]?.value||0), c=parseFloat(rows[i]?.value||0); if(p===0) return null; return parseFloat(((c-p)/p*100).toFixed(2)) }
function toggleExpand(label) { const s=new Set(expanded.value); s.has(label)?s.delete(label):s.add(label); expanded.value=s }

function natureBg(label) {
  const map = { new: 'bg-mp-success/20 border-mp-success', repeating: 'bg-mp-success/20 border-mp-success', active: 'bg-mp-success/20 border-mp-success', stop: 'bg-mp-warning/30 border-mp-warning', dead: 'bg-mp-danger/30 border-mp-danger', stop_reactivated: 'bg-mp-gold/30 border-mp-gold', dead_reactivated: 'bg-purple-500/20 border-purple-500' }
  return map[label] || 'bg-mp-card-hover border-mp-border'
}
function natureText(label) {
  const map = { new: 'text-mp-success', repeating: 'text-mp-success', active: 'text-mp-success', stop: 'text-mp-warning', dead: 'text-mp-danger', stop_reactivated: 'text-white', dead_reactivated: 'text-purple-400' }
  return map[label] || 'text-mp-muted'
}
function natureLabel(label) {
  const map = { new: 'New Customers', repeating: 'Repeating', active: 'Active (3+ yrs)', stop: 'Stop', dead: 'Dead', stop_reactivated: 'Stop Reactivated', dead_reactivated: 'Dead Reactivated' }
  return map[label] || label
}
</script>