<template>
  <Head :title="`Results — ${study.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ── HEADER ── -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
          <Link
            :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/opening-balance`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            ← Back to Opening Balance
          </Link>

          <!-- Wizard bar -->
          <div class="flex items-center gap-0 mb-5 overflow-x-auto pb-1">
            <div v-for="(step, i) in wizardSteps" :key="i" class="flex items-center flex-shrink-0">
              <div :class="[
                'flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium',
                i === 7 ? 'bg-mp-success text-white' :
                i <  7 ? 'bg-mp-card-hover text-white' : 'text-white'
              ]">
                <span :class="[
                  'w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0',
                  i < 7  ? 'bg-mp-success text-white' :
                  i === 7 ? 'bg-white/20 text-white' : 'bg-mp-card-hover text-white'
                ]">
                  <svg v-if="i < 7" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                  </svg>
                  <span v-else>{{ i + 1 }}</span>
                </span>
                {{ step }}
              </div>
              <svg v-if="i < wizardSteps.length - 1" class="w-4 h-4 text-white mx-1 flex-shrink-0"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </div>

          <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                📊 Financial Results
              </h1>
              <p class="text-white text-sm mt-0.5">
                {{ company.name }} · {{ study.name }} · {{ study.duration_years }}-Year Projection
              </p>
            </div>
            <div class="flex items-center gap-3">
              <!-- Recalculate button -->
              <button @click="recalculate" :disabled="calculating"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50">
                <svg :class="['w-4 h-4', calculating && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ calculating ? 'Calculating...' : 'Recalculate' }}
              </button>
              <!-- Excel Export — respects current granularity toggle -->
              <button @click="exportExcel" :disabled="!results || exporting"
                class="flex items-center gap-2 bg-mp-success hover:bg-mp-success disabled:opacity-40 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                </svg>
                {{ exporting ? 'Exporting…' : `Export Excel (${granularity})` }}
              </button>
              <!-- Print / PDF -->
              <button @click="printReport"
                class="flex items-center gap-2 bg-mp-success hover:bg-mp-success text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Export PDF
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ── MAIN CONTENT ── -->
      <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6" id="results-printable">

        <!-- Error state -->
        <div v-if="calcError"
          class="bg-mp-danger/40 border border-mp-danger text-mp-danger px-5 py-4 rounded-xl text-sm">
          ⚠️ {{ calcError }}
        </div>

        <!-- ════════════════════════════════════════════════════════
             MANUAL OVERRIDES PANEL
        ════════════════════════════════════════════════════════ -->
        <div class="bg-mp-card border border-mp-gold/50 rounded-xl overflow-hidden">
          <button @click="overridesOpen = !overridesOpen"
            class="w-full flex items-center justify-between px-5 py-4 hover:bg-mp-card-hover/50 transition-colors">
            <div class="flex items-center gap-3">
              <span class="text-white text-lg">⚙️</span>
              <div class="text-left">
                <p class="text-sm font-semibold text-white">Manual Overrides</p>
                <p class="text-xs text-white">Override calculated values with known figures</p>
              </div>
              <span v-if="hasOverrides" class="text-xs bg-mp-gold/60 text-white border border-mp-gold/50 px-2 py-0.5 rounded-full">Active</span>
            </div>
            <svg :class="['w-4 h-4 text-white transition-transform', overridesOpen && 'rotate-180']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div v-if="overridesOpen" class="border-t border-mp-border px-5 py-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <div v-for="ov in overrideFields" :key="ov.key">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">{{ ov.label }}</label>
                <div class="relative">
                  <input type="number" step="any" v-model.number="overrides[ov.key]"
                    :placeholder="ov.placeholder"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-gold pr-12"/>
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-white">{{ ov.unit }}</span>
                </div>
                <p class="text-xs text-white mt-1">{{ ov.hint }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3 mt-4">
              <button @click="applyOverrides"
                class="bg-mp-gold hover:bg-mp-gold-dark text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                Apply & Recalculate
              </button>
              <button @click="clearOverrides"
                class="bg-mp-card-hover hover:bg-mp-page text-white text-sm px-4 py-2 rounded-lg transition-colors">
                Clear All
              </button>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════
             CALCULATING SPINNER
        ════════════════════════════════════════════════════════ -->
        <div v-if="calculating" class="flex flex-col items-center justify-center py-24 gap-4">
          <svg class="animate-spin w-10 h-10 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          <p class="text-white text-sm">Running financial model…</p>
        </div>

        <!-- ════════════════════════════════════════════════════════
             KPI SUMMARY CARDS
        ════════════════════════════════════════════════════════ -->
        <div v-if="results" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
          <div v-for="kpi in kpiCards" :key="kpi.label"
            :class="['bg-mp-card border rounded-xl p-4 flex flex-col gap-1', kpi.borderColor ?? 'border-mp-border']">
            <p class="text-lg text-white font-normal">{{ kpi.label }}</p>
            <p :class="['text-xl font-normal font-mono', kpi.color ?? 'text-white']">{{ kpi.value }}</p>
            <p v-if="kpi.sub" class="text-m text-white">{{ kpi.sub }}</p>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════
             INVESTMENT SUMMARY
        ════════════════════════════════════════════════════════ -->
        <div v-if="results" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-mp-card border border-mp-border rounded-xl p-5">
            <p class="text-lg font-semibold text-white uppercase tracking-widest mb-3">Required Investment</p>
            <p class="text-2xl font-normal font-mono text-white">{{ fmt(results.kpis.totalInvestment) }}</p>
            <p class="text-m text-white mt-1">{{ results.currency }}</p>
            <div class="mt-4 space-y-2">
              <div class="flex justify-between text-lg">
                <span class="text-white">Fixed Assets (Equity)</span>
                <span class="font-mono text-white">{{ fmt(results.kpis.totalFixedAssetCapex * (results.kpis.totalEquityFunded / Math.max(1, results.kpis.totalInvestment + results.kpis.totalDebt))) }}</span>
              </div>
              <div class="flex justify-between text-lg">
                <span class="text-white">Debt Financing</span>
                <span class="font-mono text-white">{{ fmt(results.kpis.totalDebt) }}</span>
              </div>
              <div class="flex justify-between text-lg">
                <span class="text-white">Working Capital Injection</span>
                <span class="font-mono text-white">{{ fmt(results.kpis.workingCapitalInjection) }}</span>
              </div>
            </div>
          </div>

          <div class="bg-mp-card border border-mp-border rounded-xl p-5">
            <p class="text-lg font-semibold text-white uppercase tracking-widest mb-3">Average Margins</p>
            <div class="space-y-3 mt-2">
              <div>
                <div class="flex justify-between text-m mb-1">
                  <span class="text-white">Gross Margin</span>
                  <span class="font-mono text-mp-success">{{ fmtPct(results.kpis.avgGrossMarginPct) }}</span>
                </div>
                <div class="h-2 bg-mp-card-hover rounded-full overflow-hidden">
                  <div class="h-full bg-mp-success rounded-full" :style="`width:${Math.min(100,results.kpis.avgGrossMarginPct)}%`"></div>
                </div>
              </div>
              <div>
                <div class="flex justify-between text-lg mb-1">
                  <span class="text-white">EBITDA Margin</span>
                  <span class="font-mono text-white">{{ fmtPct(results.kpis.avgEbitdaMarginPct) }}</span>
                </div>
                <div class="h-2 bg-mp-card-hover rounded-full overflow-hidden">
                  <div class="h-full bg-mp-teal rounded-full" :style="`width:${Math.min(100,Math.max(0,results.kpis.avgEbitdaMarginPct))}%`"></div>
                </div>
              </div>
              <div>
                <div class="flex justify-between text-lg mb-1">
                  <span class="text-white">Net Margin</span>
                  <span :class="['font-mono', results.kpis.avgNetMarginPct >= 0 ? 'text-white' : 'text-mp-danger']">{{ fmtPct(results.kpis.avgNetMarginPct) }}</span>
                </div>
                <div class="h-2 bg-mp-card-hover rounded-full overflow-hidden">
                  <div :class="['h-full rounded-full', results.kpis.avgNetMarginPct >= 0 ? 'bg-mp-gold-dark' : 'bg-mp-danger']"
                    :style="`width:${Math.min(100,Math.max(0,Math.abs(results.kpis.avgNetMarginPct)))}%`"></div>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-mp-card border border-mp-border rounded-xl p-5">
            <p class="text-lg font-semibold text-white uppercase tracking-widest mb-3">Study Parameters</p>
            <div class="space-y-2 text-lg">
              <div v-if="study.duration_years > 2" class="flex justify-between">
                <span class="text-white">WACC (Discount Rate)</span>
                <span class="font-mono text-white">{{ fmtPct(results.kpis.wacc) }}</span>
              </div>
              <div v-if="study.duration_years > 2" class="flex justify-between">
                <span class="text-white">Terminal Growth</span>
                <span class="font-mono text-white">{{ fmtPct(results.kpis.perpGrowthRate) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-white">Corporate Tax</span>
                <span class="font-mono text-white">{{ fmtPct(study.corporate_tax_rate) }}</span>
              </div>
              <div v-if="study.duration_years > 2" class="flex justify-between">
                <span class="text-white">Terminal Value</span>
                <span class="font-mono text-mp-success">{{ fmt(results.kpis.terminalValue) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-white">Duration</span>
                <span class="font-mono text-white">{{ study.duration_years }} years</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════
             GRANULARITY TOGGLE
        ════════════════════════════════════════════════════════ -->
        <div v-if="results" class="flex items-center gap-2">
          <span class="text-xs text-white font-medium">View:</span>
          <div class="flex bg-mp-card border border-mp-border rounded-lg p-0.5">
            <button v-for="g in ['Annual','Monthly']" :key="g"
              @click="granularity = g"
              :class="['px-4 py-1.5 text-sm rounded-md transition-colors font-medium',
                granularity === g ? 'bg-mp-teal text-white' : 'text-white hover:text-white']">
              {{ g }}
            </button>
          </div>
          <span v-if="granularity === 'Monthly'" class="text-xs text-white bg-mp-gold/30 px-2 py-0.5 rounded border border-mp-gold/50">
            Showing monthly detail — scroll horizontally
          </span>
        </div>

        <!-- ════════════════════════════════════════════════════════
             P&L STATEMENT
        ════════════════════════════════════════════════════════ -->
        <div v-if="results" class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-mp-border">
            <div>
              <p class="text-m font-bold text-white">Income Statement (P&L)</p>
              <p class="text-m text-white mt-0.5">All figures in {{ results.currency }}</p>
            </div>
<span class="text-xs text-white italic">COGS auto-expanded by product</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm" :style="granularity === 'Monthly' ? 'min-width:1400px' : ''">
              <thead>
                <tr class="bg-mp-card-hover/60 border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white px-5 py-3 w-48 sticky left-0 bg-mp-card-hover/90 z-10">Line Item</th>
                  <th v-for="(col, ci) in displayCols" :key="ci"
                    class="text-right text-lg font-semibold text-white px-4 py-3 whitespace-nowrap min-w-28">
                    {{ col.label }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <template v-for="(row, ri) in plRows" :key="ri">
                  <tr v-if="!row.isUnabsorbed || displayCols.some(c => getVal(c.data, row.key) !== 0)"
                    :class="[
                    'border-b border-mp-border/50 transition-colors',
                    row.type === 'section'    ? 'bg-mp-card-hover/40' : '',
                    row.type === 'prodheader' ? 'bg-mp-card-hover/30' : '',
                    row.type === 'subtotal'   ? 'bg-mp-card-hover/20' : '',
                    row.type === 'total'      ? 'bg-mp-card-hover/60 font-semibold' : '',
                    row.type === 'spacer'     ? 'h-2' : 'hover:bg-mp-card-hover/30',
                  ]">
                    <td v-if="row.type !== 'spacer'" :class="[
                      'px-5 py-2.5 sticky left-0 z-10',
                      row.type === 'section'    ? 'bg-mp-card-hover/90 text-m font-semibold text-white uppercase tracking-widest' :
                      row.type === 'prodheader' ? 'bg-mp-card-hover/50 text-white font-semibold text-sm' :
                      row.type === 'total'      ? 'bg-mp-card-hover/90 text-white font-bold' :
                      row.type === 'subtotal'   ? 'bg-mp-card text-white font-medium' :
                      row.isDep                 ? 'bg-mp-card/90 text-white' :
                      row.isUnabsorbed          ? 'bg-mp-card/90 text-white' :
                      row.isSub                 ? 'bg-mp-card/90 text-white' :
                      'bg-mp-card/90 text-white',
                    ]" :style="row.indent ? `padding-left:${1.25 + row.indent * 1.25}rem` : ''">
                      <span v-if="row.type === 'prodheader'" class="mr-1.5">📦</span>
                      {{ row.label }}
                    </td>
                    <td v-if="row.type === 'spacer'" colspan="999"></td>
                    <td v-else-if="row.type === 'section'" :colspan="displayCols.length"></td>
                    <template v-else>
                      <td v-for="(col, ci) in displayCols" :key="ci"
                        class="px-4 py-2.5 text-right font-mono whitespace-nowrap text-lg">
                        <span v-if="row.type === 'prodheader'"></span>
                        <span v-else-if="row.isPct" class="text-white">{{ fmtPct(getVal(col.data, row.key)) }}</span>
                        <span v-else-if="row.isHighlight" class="text-mp-success">{{ fmtCell(getVal(col.data, row.key)) }}</span>
                        <span v-else-if="row.type === 'total'" class="text-white">{{ fmtCell(getVal(col.data, row.key)) }}</span>
                        <span v-else-if="row.type === 'subtotal'" class="text-white font-semibold">{{ fmtCell(getVal(col.data, row.key)) }}</span>
                        <span v-else-if="row.isDep" class="text-white">{{ fmtCell(getVal(col.data, row.key)) }}</span>
                        <span v-else-if="row.isUnabsorbed" class="text-white">{{ fmtCell(getVal(col.data, row.key)) }}</span>
                        <span v-else-if="row.isSub" class="text-mp-danger/80">{{ fmtCell(getVal(col.data, row.key)) }}</span>
                        <span v-else :class="[row.isNeg ? 'text-mp-danger' : 'text-white', getVal(col.data, row.key) < 0 ? 'text-mp-danger' : '']">
                          {{ fmtCell(getVal(col.data, row.key)) }}
                        </span>
                      </td>
                    </template>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════
             CASH FLOW STATEMENT
        ════════════════════════════════════════════════════════ -->
        <div v-if="results" class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-mp-border">
            <div>
              <p class="text-lg font-bold text-white">Cash Flow Statement</p>
              <p class="text-m text-white mt-0.5">All figures in {{ results.currency }}</p>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm" :style="granularity === 'Monthly' ? 'min-width:1400px' : ''">
              <thead>
                <tr class="bg-mp-card-hover/60 border-b border-mp-border">
                  <th class="text-left text-m font-semibold text-white px-5 py-3 w-48 sticky left-0 bg-mp-card-hover/90 z-10">Line Item</th>
                  <th v-for="(col, ci) in cfDisplayCols" :key="ci"
                    class="text-right text-lg font-semibold text-white px-4 py-3 whitespace-nowrap min-w-28">
                    {{ col.label }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, ri) in cfRows" :key="ri"
                  :class="[
                    'border-b border-mp-border/50 hover:bg-mp-card-hover/20',
                    row.type === 'section'  ? 'bg-mp-card-hover/40' : '',
                    row.type === 'total'    ? 'bg-mp-card-hover/60' : '',
                    row.type === 'spacer'   ? 'h-2' : '',
                  ]">
                  <td v-if="row.type !== 'spacer'" :class="[
                    'px-5 py-2.5 sticky left-0 z-10',
                    row.type === 'section' ? 'bg-mp-card-hover/90 text-m font-semibold text-white uppercase tracking-widest' :
                    row.type === 'total'   ? 'bg-mp-card-hover/90 text-white font-bold' :
                    'bg-mp-card/90 text-white pl-9',
                  ]">{{ row.label }}</td>
                  <td v-if="row.type === 'spacer'" colspan="999"></td>
                  <td v-else-if="row.type === 'section'" :colspan="cfDisplayCols.length"></td>
                  <template v-else>
                    <td v-for="(col, ci) in cfDisplayCols" :key="ci"
                      class="px-4 py-2.5 text-right font-mono whitespace-nowrap text-lg">
                      <span :class="[
                        row.type === 'total' ? 'font-bold text-lg' : '',
                        getVal(col.data, row.key) >= 0 ? (row.positiveGreen ? 'text-mp-success' : 'text-white') : 'text-mp-danger'
                      ]">
                        {{ fmtCell(getVal(col.data, row.key)) }}
                      </span>
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </div>


        <!-- ════════════════════════════════════════════════════════
             BALANCE SHEET
        ════════════════════════════════════════════════════════ -->
        <div v-if="results" class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-mp-border">
            <div>
              <p class="text-lg font-bold text-white">Balance Sheet</p>
              <p class="text-m text-white mt-0.5">{{ granularity === 'Annual' ? 'End-of-year snapshot' : 'Monthly snapshot' }} · {{ results.currency }}</p>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-mp-card-hover/60 border-b border-mp-border">
                  <th class="text-left text-m font-semibold text-white px-5 py-3 w-48 sticky left-0 bg-mp-card-hover/90 z-10">Line Item</th>
                  <th v-for="(col, ci) in bsDisplayCols" :key="ci"
                    class="text-right text-lg font-semibold text-white px-4 py-3 whitespace-nowrap min-w-28">
                    {{ col.label }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, ri) in bsRows" :key="ri"
                  :class="[
                    'border-b border-mp-border/50 hover:bg-mp-card-hover/20',
                    row.type === 'section'  ? 'bg-mp-card-hover/40' : '',
                    row.type === 'total'    ? 'bg-mp-card-hover/60' : '',
                    row.type === 'subtotal' ? 'bg-mp-card-hover/30' : '',
                    row.type === 'spacer'   ? 'h-2' : '',
                  ]">
                  <td v-if="row.type !== 'spacer'" :class="[
                    'px-5 py-2.5 sticky left-0 z-10',
                    row.type === 'section'  ? 'bg-mp-card-hover/90 text-sm font-semibold text-white uppercase tracking-widest' :
                    row.type === 'total'    ? 'bg-mp-card-hover/90 text-white font-bold' :
                    row.type === 'subtotal' ? 'bg-mp-card-hover/70 text-white font-semibold' :
                    'bg-mp-card/90 text-white pl-9',
                  ]">{{ row.label }}</td>
                  <td v-if="row.type === 'spacer'" colspan="999"></td>
                  <td v-else-if="row.type === 'section'" :colspan="bsDisplayCols.length"></td>
                  <template v-else>
                    <td v-for="(col, ci) in bsDisplayCols" :key="ci"
                      class="px-4 py-2.5 text-right font-mono whitespace-nowrap text-lg"
                      :class="[
                        row.type === 'total' || row.type === 'subtotal' ? 'font-bold text-lg' : '',
                        row.checkRow
                          ? (Math.abs(getVal(col.data, 'totalAssets') - getVal(col.data, 'totalLiabEquity')) < 1 ? 'text-mp-success' : 'text-mp-danger')
                          : (getVal(col.data, row.key) >= 0 ? (row.positiveGreen !== false ? 'text-white' : 'text-white') : 'text-mp-danger')
                      ]">
                      <span v-if="row.checkRow">
                        {{ Math.abs(getVal(col.data, 'totalAssets') - getVal(col.data, 'totalLiabEquity')) < 1 ? '✅ Balanced' : '⚠️ ' + fmtCell(Math.abs(getVal(col.data, 'totalAssets') - getVal(col.data, 'totalLiabEquity'))) }}
                      </span>
                      <span v-else>{{ fmtCell(getVal(col.data, row.key)) }}</span>
                    </td>
                  </template>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════
             REVENUE BREAKDOWN BY PRODUCT
        ════════════════════════════════════════════════════════ -->
        <div v-if="results && results.revenueByProductByYear.length > 0"
          class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
          <div class="px-5 py-4 border-b border-mp-border">
            <p class="text-lg font-bold text-white">Revenue Breakdown by Product</p>
            <p class="text-m text-white mt-0.5">Annual revenue per product · {{ results.currency }}</p>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-lg">
              <thead>
                <tr class="bg-mp-card-hover/60 border-b border-mp-border">
                  <th class="text-left text-lg font-semibold text-white px-5 py-3 sticky left-0 bg-mp-card-hover/90 z-10">Product</th>
                  <th v-for="y in results.durationYears" :key="y"
                    class="text-right text-lg font-semibold text-white px-4 py-3 whitespace-nowrap min-w-28">
                    Y{{ y }} ({{ results.startYear + y - 1 }})
                  </th>
                  <th class="text-right text-lg font-semibold text-white px-4 py-3 whitespace-nowrap">Total</th>
                  <th class="text-right text-lg font-semibold text-white px-4 py-3 whitespace-nowrap">% Mix</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(prodRevs, pi) in results.revenueByProductByYear" :key="pi"
                  class="border-b border-mp-border/50 hover:bg-mp-card-hover/20">
                  <td class="px-5 py-2.5 text-white sticky left-0 bg-mp-card/90 z-10 flex items-center gap-2">
                    <span :class="['w-2 h-2 rounded-full flex-shrink-0', productDot(pi)]"></span>
                    {{ results.productNames[pi] || `Product ${pi + 1}` }}
                  </td>
                  <td v-for="y in results.durationYears" :key="y"
                    class="px-4 py-2.5 text-right font-mono text-lg text-white whitespace-nowrap">
                    {{ fmt(prodRevs[y - 1] || 0) }}
                  </td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-white font-semibold whitespace-nowrap">
                    {{ fmt(prodRevs.reduce((s, v) => s + v, 0)) }}
                  </td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-white whitespace-nowrap">
                    {{ fmtPct(totalRevenue > 0 ? prodRevs.reduce((s,v) => s+v, 0) / totalRevenue * 100 : 0) }}
                  </td>
                </tr>
                <!-- Totals row -->
                <tr class="bg-mp-card-hover/40 font-semibold border-t border-mp-border">
                  <td class="px-5 py-2.5 text-white sticky left-0 bg-mp-card-hover/90 z-10">TOTAL</td>
                  <td v-for="y in results.durationYears" :key="y"
                    class="px-4 py-2.5 text-right font-mono text-lg text-mp-success font-bold whitespace-nowrap">
                    {{ fmt(results.plByYear[y-1]?.revenue || 0) }}
                  </td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-mp-success font-bold whitespace-nowrap">
                    {{ fmt(totalRevenue) }}
                  </td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-white">100%</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════════════
             FCFF TABLE — hidden for studies ≤ 2 years
        ════════════════════════════════════════════════════════ -->
        <div v-if="results && study.duration_years > 2" class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
          <div class="px-5 py-4 border-b border-mp-border">
            <p class="text-lg font-bold text-white">Free Cash Flow & Valuation</p>
            <p class="text-m text-white mt-0.5">FCFF used for NPV & IRR · WACC: {{ fmtPct(results.kpis.wacc) }}</p>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-lg">
              <thead>
                <tr class="bg-mp-card-hover/60 border-b border-mp-border">
                  <th class="text-left text-lg font-semibold text-white px-5 py-3 sticky left-0 bg-mp-card-hover/90 z-10 w-48">Item</th>
                  <th class="text-right text-lg font-semibold text-white px-4 py-3 whitespace-nowrap">Y0 (Invest)</th>
                  <th v-for="y in results.durationYears" :key="y"
                    class="text-right text-lg font-semibold text-white px-4 py-3 whitespace-nowrap min-w-28">
                    Y{{ y }} ({{ results.startYear + y - 1 }})
                  </th>
                  <th class="text-right text-lg font-semibold text-white px-4 py-3 whitespace-nowrap">Terminal</th>
                </tr>
              </thead>
              <tbody>
                <tr class="border-b border-mp-border/50 hover:bg-mp-card-hover/20">
                  <td class="px-5 py-2.5 text-white sticky left-0 bg-mp-card/90 z-10">EBIT</td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-white">—</td>
                  <td v-for="y in results.durationYears" :key="y"
                    class="px-4 py-2.5 text-right font-mono text-lg text-white">
                    {{ fmt(results.plByYear[y-1]?.ebit || 0) }}
                  </td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-white">—</td>
                </tr>
                <tr class="border-b border-mp-border/50 hover:bg-mp-card-hover/20">
                  <td class="px-5 py-2.5 text-white sticky left-0 bg-mp-card/90 z-10">+ Depreciation</td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-white">—</td>
                  <td v-for="y in results.durationYears" :key="y"
                    class="px-4 py-2.5 text-right font-mono text-lg text-white">
                    {{ fmt(results.plByYear[y-1]?.totalDep || 0) }}
                  </td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-white">—</td>
                </tr>
                <tr class="border-b border-mp-border/50 hover:bg-mp-card-hover/20">
                  <td class="px-5 py-2.5 text-white sticky left-0 bg-mp-card/90 z-10">− CAPEX</td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-white">—</td>
                  <td v-for="y in results.durationYears" :key="y"
                    class="px-4 py-2.5 text-right font-mono text-lg text-mp-danger">
                    ({{ fmt(results.cfByYear[y-1]?.capexPaid || 0) }})
                  </td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-white">—</td>
                </tr>
                <tr class="bg-mp-card-hover/30 font-semibold border-b border-mp-border">
                  <td class="px-5 py-2.5 text-white sticky left-0 bg-mp-card-hover/90 z-10">FCFF</td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-mp-danger font-bold">({{ fmt(results.kpis.totalInvestment) }})</td>
                  <td v-for="(fcf, yi) in results.kpis.fcff" :key="yi"
                    :class="['px-4 py-2.5 text-right font-mono text-lg font-bold', fcf >= 0 ? 'text-mp-success' : 'text-mp-danger']">
                    {{ fcf >= 0 ? fmt(fcf) : `(${fmt(Math.abs(fcf))})` }}
                  </td>
                  <td class="px-4 py-2.5 text-right font-mono text-lg text-white font-bold">
                    {{ fmt(results.kpis.terminalValue) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <!-- NPV / IRR result box -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 px-5 py-4 border-t border-mp-border bg-mp-card-hover/20">
            <div class="text-center">
              <p class="text-lg text-white mb-1">NPV</p>
              <p :class="['text-lg font-bold font-mono', results.kpis.npv >= 0 ? 'text-mp-success' : 'text-mp-danger']">
                {{ results.kpis.npv >= 0 ? fmt(results.kpis.npv) : `(${fmt(Math.abs(results.kpis.npv))})` }}
              </p>
              <p class="text-m text-white">{{ results.kpis.npv >= 0 ? 'Value Created ✅' : 'Value Destroyed ⚠️' }}</p>
            </div>
            <div class="text-center">
              <p class="text-lg text-white mb-1">IRR</p>
              <p :class="['text-lg font-bold font-mono', results.kpis.irr >= results.kpis.wacc ? 'text-mp-success' : 'text-mp-danger']">
                {{ fmtPct(results.kpis.irr) }}
              </p>
              <p class="text-m text-white">vs WACC {{ fmtPct(results.kpis.wacc) }}</p>
            </div>
            <div class="text-center">
              <p class="text-lg text-white mb-1">MOIC</p>
              <p :class="['text-lg font-bold font-mono', results.kpis.moic >= 1 ? 'text-mp-success' : 'text-mp-danger']">
                {{ results.kpis.moic.toFixed(2) }}x
              </p>
              <p class="text-m text-white">Multiple on invested capital</p>
            </div>
            <div class="text-center">
              <p class="text-lg text-white mb-1">Payback</p>
              <p class="text-lg font-bold font-mono text-white">
                {{ results.kpis.paybackYears !== null ? results.kpis.paybackYears.toFixed(1) + ' yrs' : 'N/A' }}
              </p>
              <p class="text-m text-white">Investment recovery</p>
            </div>
          </div>
        </div>

        <!-- No data state -->
        <div v-if="!results && !calcError && !calculating"
          class="bg-mp-card border border-mp-border rounded-xl p-16 text-center">
          <div class="text-5xl mb-4">📊</div>
          <p class="text-white text-sm">Click <strong class="text-white">Recalculate</strong> to run the financial model</p>
        </div>

      </div><!-- /max-w -->
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { runStudy } from '@/Utils/StudyResultsEngine.js'

// ── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
  company:         { type: Object, required: true },
  study:           { type: Object, required: true },
  products:        { type: Array,  default: () => [] },
  projections:     { type: Object, default: () => ({}) },
  cogsData:        { type: Array,  default: () => [] },
  manpowerData:    { type: Array,  default: () => [] },
  rawMaterials:    { type: Array,  default: () => [] },
  expensesData:    { type: Array,  default: () => [] },
  fixedAssetsData: { type: Array,  default: () => [] },
  openingBalance:  { type: Object, default: null },
})

const wizardSteps = ['Setup','Sales Projection','COGS','Manpower','Expenses','Fixed Assets','Opening Balance','Results']

// ── State ────────────────────────────────────────────────────────────────────
const results     = ref(null)
const calcError   = ref(null)
const calculating = ref(false)
const exporting   = ref(false)
const granularity = ref('Annual')
const plExpanded  = ref(false)
const overridesOpen = ref(false)

// ── Manual Overrides ─────────────────────────────────────────────────────────
const overrides = ref({
  totalInvestment: null,
  corporateTaxRate: null,
  wacc: null,
  perpetualGrowth: null,
})

const overrideFields = computed(() => {
  const showValuation = props.study.duration_years > 2
  return [
    { key: 'totalInvestment', label: 'Total Investment',    unit: props.study.study_currency, placeholder: 'Auto-calculated', hint: 'Override total required investment' },
    { key: 'corporateTaxRate',label: 'Corporate Tax Rate',  unit: '%',                         placeholder: props.study.corporate_tax_rate, hint: 'Override tax rate used' },
    ...(showValuation ? [
      { key: 'wacc',            label: 'Discount Rate (WACC)',unit: '%',                         placeholder: props.study.required_investment_return_pct, hint: 'Override discount rate' },
      { key: 'perpetualGrowth', label: 'Terminal Growth',     unit: '%',                         placeholder: props.study.perpetual_growth_rate_pct, hint: 'Override terminal growth rate' },
    ] : []),
  ]
})

const hasOverrides = computed(() => Object.values(overrides.value).some(v => v !== null && v !== ''))

function applyOverrides() {
  recalculate()
  overridesOpen.value = false
}

function clearOverrides() {
  Object.keys(overrides.value).forEach(k => overrides.value[k] = null)
  recalculate()
}

// ── Core Calculation ─────────────────────────────────────────────────────────
function recalculate() {
  calculating.value = true
  calcError.value   = null
  // NOTE: do NOT null out results here — keeping old results in DOM
  // prevents layout collapse which causes the "invisible until click" bug

  const studyWithOverrides = {
    ...props.study,
    corporate_tax_rate:             overrides.value.corporateTaxRate   ?? props.study.corporate_tax_rate,
    required_investment_return_pct: overrides.value.wacc               ?? props.study.required_investment_return_pct,
    perpetual_growth_rate_pct:      overrides.value.perpetualGrowth    ?? props.study.perpetual_growth_rate_pct,
  }

  try {
    const res = runStudy({
      study:           studyWithOverrides,
      products:        props.products,
      projections:     props.projections,
      cogsData:        props.cogsData,
      manpowerData:    props.manpowerData,
      rawMaterials:    props.rawMaterials ?? [],
      expensesData:    props.expensesData,
      fixedAssetsData: props.fixedAssetsData,
      openingBalance:  props.openingBalance,
      manualOverrides: {
        totalInvestment: overrides.value.totalInvestment ?? null,
      },
    })
    if (res.error) {
      calcError.value = res.error
    } else {
      results.value = res
    }
  } catch (e) {
    calcError.value = 'Calculation error: ' + e.message
    console.error(e)
  } finally {
    calculating.value = false
  }
}

onMounted(() => setTimeout(recalculate, 80))

// ── Date label helper ────────────────────────────────────────────────────────
const MONTH_NAMES = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
function timelineLabel(i) {
  // results.timeline[i] is "YYYY-MM" e.g. "2026-01"
  if (!results.value?.timeline?.[i]) return `M${i + 1}`
  const [yr, mo] = results.value.timeline[i].split('-').map(Number)
  return `${MONTH_NAMES[mo - 1]} ${yr}`
}

// ── Display Columns ───────────────────────────────────────────────────────────
const displayCols = computed(() => {
  if (!results.value) return []
  if (granularity.value === 'Annual') {
    return results.value.plByYear.map((y, i) => ({
      label: `Y${y.year} (${results.value.startYear + i})`,
      data:  y,
    }))
  } else {
    return results.value.pl.map((m, i) => ({
      label: timelineLabel(i),
      data:  m,
    }))
  }
})

const cfDisplayCols = computed(() => {
  if (!results.value) return []
  if (granularity.value === 'Annual') {
    return results.value.cfByYear.map((y, i) => ({
      label: `Y${y.year} (${results.value.startYear + i})`,
      data:  y,
    }))
  } else {
    return results.value.cf.map((m, i) => ({
      label: timelineLabel(i),
      data:  m,
    }))
  }
})

// ── BS Display Columns ───────────────────────────────────────────────────────
const bsDisplayCols = computed(() => {
  if (!results.value) return []
  if (granularity.value === 'Annual') {
    return results.value.bsByYear.map((y, i) => ({
      label: `Y${y.year} (${results.value.startYear + i})`,
      data:  y,
    }))
  } else {
    return results.value.bs.map((m, i) => ({
      label: timelineLabel(i),
      data:  m,
    }))
  }
})

// ── P&L Row Definitions (dynamic — COGS section built from products) ──────────
const plRows = computed(() => {
  const cogsDetail    = results.value?.plByYear?.[0]?.cogsDetail ?? []
  const expMeta       = results.value?.expenseBreakdownMeta?.plItems ?? []

  // Build COGS sub-rows per product
  const cogsSubRows = []
  cogsDetail.forEach((d, di) => {
    if (d.nature === 'manufacturing') {
      cogsSubRows.push({ type: 'prodheader', label: d.name, indent: 1, isNeg: true })
      cogsSubRows.push({ label: 'Raw Material Cost', key: `cd_${di}_rmCogs`,      isNeg: true, indent: 2, isSub: true })
      cogsSubRows.push({ label: 'Labor Cost',        key: `cd_${di}_dlCogs`,      isNeg: true, indent: 2, isSub: true })
      cogsSubRows.push({ label: 'Overheads Cost',    key: `cd_${di}_ohCogs`,      isNeg: true, indent: 2, isSub: true })
      cogsSubRows.push({ label: 'Mfg Depreciation',  key: `cd_${di}_mfgDep`,      isNeg: true, indent: 2, isSub: true, isDep: true })
    } else if (d.nature === 'trading') {
      cogsSubRows.push({ label: d.name + ' — Trading COGS',  key: `cd_${di}_tradingCogs`, isNeg: true, indent: 1, isSub: true })
    } else if (d.nature === 'service') {
      cogsSubRows.push({ label: d.name + ' — Service Cost',  key: `cd_${di}_serviceCogs`, isNeg: true, indent: 1, isSub: true })
    }
  })

  // Build per-expense sub-rows — one row per named expense
  const expenseSubRows = expMeta.length > 0
    ? expMeta.map(e => ({ label: e.name, key: e.plKey, isNeg: true, indent: 1, isSub: true }))
    : [{ label: 'Operating Expenses', key: 'opexCost', isNeg: true, indent: 1 }]

  return [
    { type: 'section',  label: 'Revenue' },
    { label: 'Net Revenue',           key: 'revenue',         isHighlight: true },
    { type: 'spacer' },
    { type: 'section',  label: 'Cost of Goods Sold' },
    ...cogsSubRows,
    { type: 'subtotal', label: 'Total COGS',                  key: 'cogs',            isNeg: true },
    { label: 'Gross Profit',          key: 'grossProfit',     isHighlight: true },
    { label: 'Gross Margin %',        key: 'grossMarginPct',  isPct: true, indent: 1 },
    { type: 'spacer' },
    { type: 'section',  label: 'Operating Expenses' },
    { label: 'Manpower',              key: 'manpowerCost',    isNeg: true, indent: 1 },
    ...expenseSubRows,
    { label: 'Unabsorbed Mfg. Overheads', key: 'ohUnabsorbed', isNeg: true, indent: 1, isUnabsorbed: true },
    { type: 'subtotal', label: 'Total OpEx',                  key: 'totalOpEx',       isNeg: true },
    { label: 'Add Back: Mfg Dep',     key: 'mfgDep',          isNeg: false, indent: 1, isDep: true, isAddBack: true },
    { label: 'EBITDA',                key: 'ebitda',          isHighlight: true },
    { label: 'EBITDA Margin %',       key: 'ebitdaMarginPct', isPct: true,  indent: 1 },
    { type: 'spacer' },
    { type: 'section',  label: 'Depreciation & Amortization' },
    { label: 'Mfg Depreciation',      key: 'mfgDep',          isNeg: true, indent: 1, isDep: true },
    { label: 'Admin Depreciation',    key: 'adminDep',        isNeg: true, indent: 1, isDep: true },
    { label: 'Total Depreciation',    key: 'totalDep',        isNeg: true, indent: 1, isDep: true },
    { label: 'EBIT',                  key: 'ebit' },
    { type: 'spacer' },
    { type: 'section',  label: 'Finance' },
    { label: 'Finance Costs',         key: 'finCost',         isNeg: true, indent: 1 },
    { label: 'EBT (Pre-Tax)',          key: 'ebt' },
    { label: 'Income Tax',            key: 'tax',             isNeg: true, indent: 1 },
    { type: 'total',    label: 'Net Profit',                  key: 'netProfit' },
    { label: 'Net Margin %',          key: 'netMarginPct',    isPct: true, indent: 1 },
  ]
})

// ── CF Row Definitions (computed — dynamic per-expense and per-supplier rows) ──
const cfRows = computed(() => {
  const supplierMeta = results.value?.supplierPaymentBreakdownMeta ?? {}
  const expMeta      = results.value?.expenseBreakdownMeta?.cashItems ?? []

  // Supplier payment rows: per-RM + per-OH breakdown, falling back to single total line
  const supplierRows = []
  const rmItems = supplierMeta.rawMaterials ?? []
  const ohItems = supplierMeta.overheads    ?? []
  if (rmItems.length > 0 || ohItems.length > 0) {
    rmItems.forEach(item => supplierRows.push({
      label: item.name, key: item.key, isNeg: true, indent: 1, isSub: true,
    }))
    ohItems.forEach(item => supplierRows.push({
      label: item.name + ' (Overhead)', key: item.key, isNeg: true, indent: 1, isSub: true,
    }))
    supplierRows.push({ type: 'subtotal', label: 'Total Supplier Payments', key: 'cogsPaid', isNeg: true })
  } else {
    supplierRows.push({ label: 'Supplier Payments (incl. VAT)', key: 'cogsPaid', isNeg: true })
  }

  // Expense payment rows: per-expense breakdown, falling back to single total line
  const expenseRows = []
  if (expMeta.length > 0) {
    expMeta.forEach(item => expenseRows.push({
      label: item.name, key: item.cashKey, isNeg: true, indent: 1, isSub: true,
    }))
    expenseRows.push({ type: 'subtotal', label: 'Total Expenses Paid', key: 'expensesPaid', isNeg: true })
  } else {
    expenseRows.push({ label: 'Expenses Paid', key: 'expensesPaid', isNeg: true })
  }

  return [
    { type: 'section', label: 'Operating Activities' },
    { label: 'Cash Receipts from Customers',     key: 'receipts',       positiveGreen: true },
    ...supplierRows,
    { label: 'Net VAT Paid to Authority',        key: 'vatPaid',        isNeg: true },
    { label: 'Credit WHT Paid (Quarterly)',      key: 'creditWhtPaid',  isNeg: true },
    { label: 'Manpower Paid',                    key: 'manpowerPaid',   isNeg: true },
    ...expenseRows,
    { label: 'Corporate Tax Paid (April)',       key: 'corpTaxPaid',    isNeg: true },
    { label: 'Interest Paid',                    key: 'interestPaid',   isNeg: true },
    { type: 'total', label: 'Net Operating CF',        key: 'operatingCF',   positiveGreen: true },
    { type: 'spacer' },
    { type: 'section', label: 'Investing Activities' },
    { label: 'CAPEX Payments',                   key: 'capexPaid',      isNeg: true },
    { type: 'total', label: 'Net Investing CF',        key: 'investingCF',   positiveGreen: false },
    { type: 'spacer' },
    { type: 'section', label: 'Financing Activities' },
    { label: 'Equity Injection',                 key: 'equityInjection', positiveGreen: true },
    { label: 'Loan Drawdowns',                   key: 'loanDrawdown',   positiveGreen: true },
    { label: 'Loan Repayments',                  key: 'loanRepay',      isNeg: true },
    { type: 'total', label: 'Net Financing CF',        key: 'financingCF',   positiveGreen: false },
    { type: 'spacer' },
    { type: 'total', label: 'Net Change in Cash',      key: 'netCF',         positiveGreen: true },
    { type: 'total', label: 'Cumulative Cash Balance',  key: 'cumulativeCash', positiveGreen: true },
  ]
})

// ── BS Row Definitions ───────────────────────────────────────────────────────
const bsRows = [
  // ── NON-CURRENT ASSETS ──
  { type: 'section',  label: 'Non-Current Assets' },
  { label: 'Gross Fixed Assets',             key: 'grossFA',              indent: 1 },
  { label: 'Accumulated Depreciation',       key: 'accumDep',             indent: 1, isNeg: true },
  { type: 'subtotal', label: 'Net Fixed Assets',                          key: 'netFA' },
  { type: 'spacer' },
  // ── CURRENT ASSETS ──
  { type: 'section',  label: 'Current Assets' },
  { label: 'Cash & Bank',                    key: 'cash',                 indent: 1, positiveGreen: true },
  { label: 'Customers Receivable (AR)',      key: 'ar',                   indent: 1, positiveGreen: true },
  { label: 'Trading Inventory',              key: 'inventory',            indent: 1, positiveGreen: true },
  { label: 'Corporate Tax Prepayment',       key: 'corpTaxPrepayment',    indent: 1, positiveGreen: true },
  { type: 'subtotal', label: 'Total Current Assets',                      key: 'totalCurrentAssets' },
  { type: 'spacer' },
  { type: 'total',    label: 'TOTAL ASSETS',                              key: 'totalAssets' },
  { type: 'spacer' },
  // ── NON-CURRENT LIABILITIES ──
  { type: 'section',  label: 'Non-Current Liabilities' },
  { label: 'Long-term Debt',                 key: 'longTermDebt',         indent: 1 },
  { type: 'spacer' },
  // ── CURRENT LIABILITIES ──
  { type: 'section',  label: 'Current Liabilities' },
  { label: 'Suppliers Payable (AP)',         key: 'ap',                   indent: 1 },
  { label: 'Net VAT Payable',               key: 'vatPayable',           indent: 1 },
  { label: 'Corporate Tax Payable',         key: 'corpTaxPayable',       indent: 1 },
  { label: 'Credit WHT Payable',            key: 'creditWhtPayable',     indent: 1 },
  { type: 'subtotal', label: 'Total Current Liabilities',                 key: 'totalCurrentLiabilities' },
  { type: 'spacer' },
  { type: 'subtotal', label: 'TOTAL LIABILITIES',                        key: 'totalLiabilities' },
  { type: 'spacer' },
  // ── EQUITY ──
  { type: 'section',  label: 'Equity' },
  { label: 'Paid-up Capital',                key: 'equityPaidUp',         indent: 1, positiveGreen: true },
  { label: 'Legal Reserve',                  key: 'equityLegalRes',       indent: 1, positiveGreen: true },
  { label: 'Retained Earnings',              key: 'equityRetained',       indent: 1, positiveGreen: true },
  { label: 'Profit of the Period',           key: 'equityProfit',         indent: 1, positiveGreen: true },
  { type: 'subtotal', label: 'TOTAL EQUITY',                              key: 'totalEquity' },
  { type: 'spacer' },
  { type: 'total',    label: 'TOTAL LIABILITIES & EQUITY',               key: 'totalLiabEquity' },
  { type: 'spacer' },
  { type: 'total',    label: '✓ Balance Check', key: 'totalAssets',       checkRow: true },
]

// ── Helpers ───────────────────────────────────────────────────────────────────
function getVal(data, key) {
  if (!data || !key) return 0
  // Handle cogsDetail keys: cd_{productIndex}_{field}
  if (key.startsWith('cd_')) {
    const parts = key.split('_')  // ['cd', di, fieldName]
    const di    = parseInt(parts[1])
    const field = parts.slice(2).join('_')
    return data.cogsDetail?.[di]?.[field] ?? 0
  }
  return data[key] ?? 0
}

function fmt(n) {
  if (n === null || n === undefined) return '—'
  return Math.abs(n) >= 1000000
    ? (n / 1000000).toFixed(2) + 'M'
    : Math.abs(n) >= 1000
    ? (n / 1000).toFixed(1) + 'K'
    : Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 })
}

function fmtCell(n) {
  if (n === null || n === undefined) return '—'
  const abs = Math.abs(n)
  const str = abs >= 1000000
    ? (abs / 1000000).toFixed(2) + 'M'
    : abs >= 1000
    ? (abs / 1000).toFixed(1) + 'K'
    : abs.toLocaleString('en-US', { maximumFractionDigits: 0 })
  return n < 0 ? `(${str})` : str
}

function fmtPct(n) {
  if (n === null || n === undefined) return '—'
  return Number(n).toFixed(1) + '%'
}

const productColors = ['bg-mp-gold','bg-mp-teal','bg-mp-success','bg-mp-gold','bg-mp-danger','bg-mp-gold']
function productDot(pi) { return productColors[pi % productColors.length] }

const totalRevenue = computed(() => {
  if (!results.value) return 0
  return results.value.plByYear.reduce((s, y) => s + y.revenue, 0)
})

// ── KPI Cards ─────────────────────────────────────────────────────────────────
const kpiCards = computed(() => {
  if (!results.value) return []
  const k = results.value.kpis
  const showValuation = props.study.duration_years > 2
  return [
    showValuation ? {
      label:  'NPV',
      value:  fmt(k.npv),
      sub:    k.npv >= 0 ? 'Positive ✅' : 'Negative ⚠️',
      color:  k.npv >= 0 ? 'text-mp-success' : 'text-mp-danger',
      borderColor: k.npv >= 0 ? 'border-mp-success/50' : 'border-mp-danger/50',
    } : null,
    showValuation ? {
      label:  'IRR',
      value:  fmtPct(k.irr),
      sub:    `WACC: ${fmtPct(k.wacc)}`,
      color:  k.irr >= k.wacc ? 'text-mp-success' : 'text-mp-danger',
      borderColor: k.irr >= k.wacc ? 'border-mp-success/50' : 'border-mp-danger/50',
    } : null,
    {
      label:  'MOIC',
      value:  k.moic.toFixed(2) + 'x',
      sub:    k.moic >= 2 ? 'Strong Return' : k.moic >= 1 ? 'Positive Return' : 'Below Cost',
      color:  k.moic >= 1 ? 'text-mp-success' : 'text-mp-danger',
    },
    {
      label:  'Payback Period',
      value:  k.paybackYears !== null ? k.paybackYears.toFixed(1) + ' yrs' : 'N/A',
      sub:    k.paybackYears !== null ? `Month ${k.paybackMonths}` : 'Beyond horizon',
      color:  'text-white',
    },
    {
      label:  'Total Investment',
      value:  fmt(k.totalInvestment),
      sub:    results.value.currency,
      color:  'text-white',
    },
    {
      label:  'Break-Even',
      value:  k.breakEvenYears !== null ? k.breakEvenYears.toFixed(1) + ' yrs' : 'N/A',
      sub:    'Cumulative profit',
      color:  'text-white',
    },
  ].filter(Boolean)
})

// ── Excel Export — uses current view (Annual or Monthly) ─────────────────────
async function exportExcel() {
  if (!results.value || exporting.value) return
  exporting.value = true
  try {
    const XLSX = await import('https://cdn.sheetjs.com/xlsx-0.20.3/package/xlsx.mjs')
    const r    = results.value

    // Column headers — same as what's shown on screen right now
    const colLabels = displayCols.value.map(c => c.label)

    // ── helper: build a sheet from row-definition array + data columns ────
    function buildSheet(rowDefs, cols) {
      const aoa = []
      // title rows
      aoa.push([`${props.company.name} — ${props.study.name}`, ...cols.map(() => '')])
      aoa.push([`${granularity.value} View  |  Currency: ${r.currency}`, ...cols.map(() => '')])
      aoa.push([...([''].concat(cols.map(c => c.label)))])
      aoa.push([])

      for (const row of rowDefs) {
        if (row.spacer)           { aoa.push([]); continue }
        if (row.section != null)  { aoa.push([row.section.toUpperCase()]); continue }
        if (row.type === 'prodheader') { aoa.push([`  ${row.label}`]); continue }
        if (row.key == null)      { aoa.push([row.label]); continue }

        const vals = cols.map(col => {
          const d = col.data
          let v
          if (row.keyFn) {
            v = row.keyFn(d)
          } else if (row.key.startsWith('cd_')) {
            const parts = row.key.split('_')
            const di    = parseInt(parts[1])
            const field = parts.slice(2).join('_')
            v = d.cogsDetail?.[di]?.[field] ?? 0
          } else {
            v = d[row.key] ?? 0
          }
          if (row.isPct) return v / 100   // store as decimal; format cell as %
          return v
        })
        aoa.push([row.label, ...vals])
      }
      return aoa
    }

    // ════════════════════════════════════════════════════════════════
    // SHEET 1 — INCOME STATEMENT
    // ════════════════════════════════════════════════════════════════
    const cogsDetail = r.plByYear[0]?.cogsDetail ?? []
    const expMeta    = r.expenseBreakdownMeta?.plItems ?? []
    const cogsSubRows = []
    cogsDetail.forEach((d, di) => {
      if (d.nature === 'manufacturing') {
        cogsSubRows.push({ type: 'prodheader', label: d.name })
        cogsSubRows.push({ label: '    Raw Material Cost',  key: `cd_${di}_rmCogs` })
        cogsSubRows.push({ label: '    Labor Cost',         key: `cd_${di}_dlCogs` })
        cogsSubRows.push({ label: '    Overheads Cost',     key: `cd_${di}_ohCogs` })
        cogsSubRows.push({ label: '    Mfg Depreciation',  key: `cd_${di}_mfgDep` })
      } else if (d.nature === 'trading') {
        cogsSubRows.push({ label: `  ${d.name} — Trading COGS`, key: `cd_${di}_tradingCogs` })
      } else if (d.nature === 'service') {
        cogsSubRows.push({ label: `  ${d.name} — Service Cost`,  key: `cd_${di}_serviceCogs` })
      }
    })

    const expenseXlsRows = expMeta.length > 0
      ? [
          ...expMeta.map(e => ({ label: `  ${e.name}`, key: e.plKey })),
          { label: '  Total OpEx', key: 'opexCost' },
        ]
      : [{ label: '  Operating Expenses', key: 'opexCost' }]

    const plRowDefs = [
      { section: 'Revenue' },
      { label: 'Net Revenue',                    key: 'revenue' },
      { spacer: true },
      { section: 'Cost of Goods Sold' },
      ...cogsSubRows,
      { label: 'Total COGS',                     key: 'cogs' },
      { label: 'Gross Profit',                   key: 'grossProfit' },
      { label: '  Gross Margin %',               key: 'grossMarginPct', isPct: true },
      { spacer: true },
      { section: 'Operating Expenses' },
      { label: '  Manpower',                     key: 'manpowerCost' },
      ...expenseXlsRows,
      { label: '  Add Back: Mfg Dep',            key: 'mfgDep' },
      { label: 'EBITDA',                         key: 'ebitda' },
      { label: '  EBITDA Margin %',              key: 'ebitdaMarginPct', isPct: true },
      { spacer: true },
      { section: 'Depreciation & Amortization' },
      { label: '  Mfg Depreciation',             key: 'mfgDep' },
      { label: '  Admin Depreciation',           key: 'adminDep' },
      { label: '  Total Depreciation',           key: 'totalDep' },
      { label: 'EBIT',                           key: 'ebit' },
      { spacer: true },
      { section: 'Finance' },
      { label: '  Finance Costs',                key: 'finCost' },
      { label: 'EBT (Pre-Tax)',                  key: 'ebt' },
      { label: '  Income Tax',                   key: 'tax' },
      { label: 'NET PROFIT',                     key: 'netProfit' },
      { label: '  Net Margin %',                 key: 'netMarginPct', isPct: true },
    ]
    const plAoa = buildSheet(plRowDefs, displayCols.value)

    // ════════════════════════════════════════════════════════════════
    // SHEET 2 — BALANCE SHEET
    // ════════════════════════════════════════════════════════════════
    const bsRowDefs = [
      { section: 'Non-Current Assets' },
      { label: '  Gross Fixed Assets',           key: 'grossFA' },
      { label: '  Accumulated Depreciation',     key: 'accumDep' },
      { label: 'Net Fixed Assets',               key: 'netFA' },
      { spacer: true },
      { section: 'Current Assets' },
      { label: '  Cash & Bank',                  key: 'cash' },
      { label: '  Customers Receivable (AR)',    key: 'ar' },
      { label: '  Trading Inventory',            key: 'inventory' },
      { label: '  Corporate Tax Prepayment',     key: 'corpTaxPrepayment' },
      { label: 'Total Current Assets',           key: 'totalCurrentAssets' },
      { spacer: true },
      { label: 'TOTAL ASSETS',                   key: 'totalAssets' },
      { spacer: true },
      { section: 'Non-Current Liabilities' },
      { label: '  Long-term Debt',               key: 'longTermDebt' },
      { spacer: true },
      { section: 'Current Liabilities' },
      { label: '  Suppliers Payable (AP)',        key: 'ap' },
      { label: '  Net VAT Payable',              key: 'vatPayable' },
      { label: '  Corporate Tax Payable',        key: 'corpTaxPayable' },
      { label: '  Credit WHT Payable',           key: 'creditWhtPayable' },
      { label: 'Total Current Liabilities',      key: 'totalCurrentLiabilities' },
      { spacer: true },
      { label: 'TOTAL LIABILITIES',              key: 'totalLiabilities' },
      { spacer: true },
      { section: 'Equity' },
      { label: '  Paid-up Capital',              key: 'equityPaidUp' },
      { label: '  Legal Reserve',                 key: 'equityLegalRes' },
      { label: '  Retained Earnings',            key: 'equityRetained' },
      { label: '  Profit of the Period',         key: 'equityProfit' },
      { label: 'TOTAL EQUITY',                   key: 'totalEquity' },
      { spacer: true },
      { label: 'TOTAL LIABILITIES & EQUITY',     key: 'totalLiabEquity' },
      { spacer: true },
      { label: '✓ Balance Check (Assets − L&E)', keyFn: d => (d.totalAssets || 0) - (d.totalLiabEquity || 0) },
    ]
    const bsAoa = buildSheet(bsRowDefs, bsDisplayCols.value)

    // ════════════════════════════════════════════════════════════════
    // SHEET 3 — CASH FLOW STATEMENT
    // ════════════════════════════════════════════════════════════════
    const cfSupplierMeta = r.supplierPaymentBreakdownMeta ?? {}
    const cfExpMeta      = r.expenseBreakdownMeta?.cashItems ?? []
    const cfRmItems  = cfSupplierMeta.rawMaterials ?? []
    const cfOhItems  = cfSupplierMeta.overheads    ?? []

    const cfSupplierXlsRows = (cfRmItems.length > 0 || cfOhItems.length > 0)
      ? [
          ...cfRmItems.map(it => ({ label: `  ${it.name}`, key: it.key })),
          ...cfOhItems.map(it => ({ label: `  ${it.name} (Overhead)`, key: it.key })),
          { label: '  Total Supplier Payments', key: 'cogsPaid' },
        ]
      : [{ label: '  Supplier Payments (incl. VAT)', key: 'cogsPaid' }]

    const cfExpenseXlsRows = cfExpMeta.length > 0
      ? [
          ...cfExpMeta.map(it => ({ label: `  ${it.name}`, key: it.cashKey })),
          { label: '  Total Expenses Paid', key: 'expensesPaid' },
        ]
      : [{ label: '  Expenses Paid', key: 'expensesPaid' }]

    const cfRowDefs = [
      { section: 'Operating Activities' },
      { label: '  Cash Receipts from Customers',  key: 'receipts' },
      ...cfSupplierXlsRows,
      { label: '  Net VAT Paid to Authority',     key: 'vatPaid' },
      { label: '  Credit WHT Paid (Quarterly)',   key: 'creditWhtPaid' },
      { label: '  Manpower Paid',                 key: 'manpowerPaid' },
      ...cfExpenseXlsRows,
      { label: '  Corporate Tax Paid (April)',    key: 'corpTaxPaid' },
      { label: '  Interest Paid',                 key: 'interestPaid' },
      { label: 'Net Operating CF',                key: 'operatingCF' },
      { spacer: true },
      { section: 'Investing Activities' },
      { label: '  CAPEX Payments',                key: 'capexPaid' },
      { label: 'Net Investing CF',                key: 'investingCF' },
      { spacer: true },
      { section: 'Financing Activities' },
      { label: '  Equity Injection',              key: 'equityInjection' },
      { label: '  Loan Drawdowns',                key: 'loanDrawdown' },
      { label: '  Loan Repayments',               key: 'loanRepay' },
      { label: 'Net Financing CF',                key: 'financingCF' },
      { spacer: true },
      { label: 'Net Change in Cash',              key: 'netCF' },
      { label: 'Cumulative Cash Balance',         key: 'cumulativeCash' },
    ]
    const cfAoa = buildSheet(cfRowDefs, cfDisplayCols.value)

    // ── Build workbook ───────────────────────────────────────────────────────
    const wb = XLSX.utils.book_new()

    function aoaToStyledSheet(aoa) {
      const ws = XLSX.utils.aoa_to_sheet(aoa)
      const range = XLSX.utils.decode_range(ws['!ref'] || 'A1')
      // Format: row 2 (index 2) has column headers; data starts row 3 (index 3+)
      for (let R = 3; R <= range.e.r; R++) {
        for (let C = 1; C <= range.e.c; C++) {
          const cell = ws[XLSX.utils.encode_cell({ r: R, c: C })]
          if (!cell || typeof cell.v !== 'number') continue
          // Detect percentage cells (stored as 0.xx decimal)
          cell.z = (Math.abs(cell.v) <= 2 && cell.v !== 0 && cell.v !== Math.round(cell.v))
            ? '0.0%'
            : '#,##0'
        }
      }
      // Column widths: first col wide, rest equal
      ws['!cols'] = [{ wch: 40 }, ...Array(range.e.c).fill({ wch: 15 })]
      return ws
    }

    XLSX.utils.book_append_sheet(wb, aoaToStyledSheet(plAoa), 'Income Statement')
    XLSX.utils.book_append_sheet(wb, aoaToStyledSheet(bsAoa), 'Balance Sheet')
    XLSX.utils.book_append_sheet(wb, aoaToStyledSheet(cfAoa), 'Cash Flow')

    const view     = granularity.value === 'Annual' ? 'Annual' : 'Monthly'
    const fileName = `${props.company.name}_${props.study.name}_${view}_Results.xlsx`
      .replace(/[^a-zA-Z0-9_\-.]/g, '_')
    XLSX.writeFile(wb, fileName)

  } catch (e) {
    console.error('Excel export failed:', e)
    alert('Export failed: ' + e.message)
  } finally {
    exporting.value = false
  }
}

// ── Print ─────────────────────────────────────────────────────────────────────
function printReport() {
  window.print()
}
</script>

<style>
@media print {
  header, nav, .no-print { display: none !important; }
  #results-printable { padding: 0; }
  body { background: white; color: black; }
}
</style>