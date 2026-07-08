<template>
  <Head :title="`Profitability — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-mp-text-secondary">

      <!-- ── HEADER ── -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
          <Link :href="`/portfolio-companies/${company.id}`"
            class="flex items-center gap-2 text-sm text-mp-muted hover:text-mp-text-secondary transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to {{ company.name }}
          </Link>
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-mp-text-secondary">📈 Profitability Dashboard</h1>
              <p class="text-mp-muted text-sm mt-0.5">{{ company.name }} — P&L waterfall, margins &amp; trends</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <!-- Date range picker -->
              <div class="flex items-center gap-2 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2">
                <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <input v-model="dateFrom" type="date" :min="minDate" :max="maxDate"
                  class="bg-transparent text-mp-text-secondary text-sm focus:outline-none w-32"/>
                <span class="text-mp-muted text-xs">→</span>
                <input v-model="dateTo" type="date" :min="minDate" :max="maxDate"
                  class="bg-transparent text-mp-text-secondary text-sm focus:outline-none w-32"/>
              </div>
              <!-- Period selector -->
              <select v-model="periodType"
                class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-mp-text-secondary text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                <option value="month">Monthly</option>
                <option value="quarter">Quarterly</option>
                <option value="semi">Semi-Annual</option>
                <option value="year">Annual</option>
              </select>
              <!-- Apply -->
              <button @click="loadData" :disabled="loading"
                class="bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 text-mp-text-secondary text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                {{ loading ? 'Loading...' : 'Apply' }}
              </button>
              <!-- Mapping link -->
              <Link :href="`/companies/${company.id}/profitability/mapping`"
                class="flex items-center gap-2 bg-mp-gold hover:bg-mp-gold-dark text-mp-text-secondary text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                P&L Mapping
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- No mapping warning -->
      <div v-if="!hasMappings" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        <div class="bg-mp-gold/30 border border-mp-gold rounded-xl p-4 flex items-center gap-3">
          <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          </svg>
          <p class="text-white text-sm">
            P&L mapping not set up yet.
            <Link :href="`/companies/${company.id}/profitability/mapping`" class="underline font-semibold hover:text-white">
              Set it up now →
            </Link>
            All expenses will show under OpEx until mapped.
          </p>
        </div>
      </div>

      <!-- ── LOADING ── -->
      <div v-if="loading" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
        <svg class="animate-spin w-10 h-10 text-white mx-auto mb-4" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        <p class="text-mp-muted text-sm">Calculating P&L...</p>
      </div>

      <!-- ── MAIN CONTENT ── -->
      <div v-else-if="data" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">

        <!-- ════════════════════════════════════════════
             SECTION 1 — KPI CARDS
        ════════════════════════════════════════════ -->
        <div>
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">
            P&L Summary — {{ dateFrom }} → {{ dateTo }}
          </p>
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div v-for="kpi in kpiCards" :key="kpi.key"
              class="bg-mp-card border rounded-xl p-4 relative overflow-hidden transition-all"
              :style="`border-color: ${kpi.color}30`">
              <div class="absolute top-0 right-0 w-20 h-20 rounded-full -translate-y-6 translate-x-6 opacity-10"
                :style="`background:${kpi.color}`"></div>
              <p class="text-xs uppercase tracking-widest mb-1 font-semibold" :style="`color:${kpi.color}`">{{ kpi.label }}</p>
              <p class="text-xl font-bold" :class="kpi.amount >= 0 ? 'text-mp-text-secondary' : 'text-mp-danger'">
                {{ fmt(kpi.amount) }}
              </p>
              <div class="mt-2 flex items-center gap-1.5">
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                  :class="kpi.margin >= 20 ? 'bg-mp-success/60 text-mp-success' :
                         kpi.margin >= 10 ? 'bg-mp-warning/60 text-mp-warning' :
                         kpi.margin >= 0  ? 'bg-mp-teal-subtle/60 text-white' :
                                            'bg-mp-danger/60 text-mp-danger'">
                  {{ kpi.margin }}% margin
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Unmapped warning -->
        <div v-if="data.kpis.unmapped > 0"
          class="bg-mp-card-hover/60 border border-mp-border rounded-xl px-5 py-3 flex items-center gap-3">
          <svg class="w-4 h-4 text-mp-muted flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/>
          </svg>
          <p class="text-mp-muted text-xs">
            <span class="text-mp-text-secondary font-semibold">{{ fmt(data.kpis.unmapped) }}</span> in expenses has no P&L mapping and is excluded from all metrics.
            <Link :href="`/companies/${company.id}/profitability/mapping`" class="text-white underline ml-1">Fix mappings →</Link>
          </p>
        </div>

        <!-- ════════════════════════════════════════════
             SECTION 2 — P&L WATERFALL TABLE
        ════════════════════════════════════════════ -->
        <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
          <div class="px-6 py-4 border-b border-mp-border">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">P&L Waterfall</p>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3 w-56">Line Item</th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3">Amount</th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-6 py-3 w-28">% of Revenue</th>
                  <th class="text-left text-xs font-semibold text-white uppercase px-6 py-3">Visual</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in data.waterfall" :key="row.label"
                  :class="{
                    'bg-mp-teal-subtle/20 border-b-2 border-mp-teal/40': row.type === 'subtotal',
                    'bg-mp-success/30 border-b-2 border-mp-success/50': row.type === 'net',
                    'border-b border-mp-border/60 hover:bg-mp-card-hover/30': row.type === 'deduct' || row.type === 'revenue',
                  }">
                  <td class="px-6 py-3">
                    <div class="flex items-center gap-2">
                      <span v-if="row.type === 'deduct'" class="text-mp-muted text-xs">−</span>
                      <span v-else-if="row.type === 'subtotal'" class="text-white text-xs">▶</span>
                      <span v-else-if="row.type === 'net'" class="text-mp-success text-xs">★</span>
                      <span :class="{
                        'font-bold text-mp-text-secondary': row.type === 'subtotal' || row.type === 'net' || row.type === 'revenue',
                        'text-mp-muted text-xs pl-2': row.type === 'deduct',
                      }">{{ row.label }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-3 text-right font-semibold"
                    :class="{
                      'text-mp-text-secondary': row.type === 'revenue' || row.type === 'subtotal',
                      'text-mp-success': row.type === 'net' && row.value >= 0,
                      'text-mp-danger': row.value < 0,
                      'text-mp-muted text-xs': row.type === 'deduct',
                    }">
                    {{ row.value < 0 ? '(' + fmt(Math.abs(row.value)) + ')' : fmt(row.value) }}
                  </td>
                  <td class="px-6 py-3 text-right text-xs"
                    :class="{
                      'font-semibold': row.type !== 'deduct',
                      'text-mp-success': row.margin >= 20 && (row.type === 'subtotal' || row.type === 'net'),
                      'text-mp-warning': row.margin < 20 && row.margin >= 10 && (row.type === 'subtotal' || row.type === 'net'),
                      'text-mp-danger': row.margin < 0,
                      'text-mp-muted': row.type === 'deduct',
                      'text-white': row.type === 'revenue',
                    }">
                    {{ row.type !== 'deduct' ? row.margin + '%' : '' }}
                  </td>
                  <td class="px-6 py-3">
                    <div v-if="row.type !== 'deduct'" class="flex items-center gap-2">
                      <div class="w-32 bg-mp-card-hover rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full transition-all duration-500"
                          :class="{
                            'bg-mp-teal': row.type === 'revenue',
                            'bg-mp-success': row.type === 'subtotal' && row.margin >= 20,
                            'bg-mp-warning': row.type === 'subtotal' && row.margin < 20 && row.margin >= 0,
                            'bg-mp-success': row.type === 'net' && row.value >= 0,
                            'bg-mp-danger': row.value < 0,
                          }"
                          :style="`width:${Math.min(Math.max(row.margin, 0), 100)}%`">
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ════════════════════════════════════════════
             SECTION 3 — MULTI-LINE TREND CHART
        ════════════════════════════════════════════ -->
        <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
          <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between flex-wrap gap-3">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">Profitability Trend — All Metrics</p>
            <div class="flex items-center bg-mp-card-hover rounded-lg p-0.5">
              <button @click="trendMode = 'amount'; rerenderTrend()"
                :class="trendMode === 'amount' ? 'bg-mp-page text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text'"
                class="text-xs font-medium px-3 py-1.5 rounded-md transition-all">Amount</button>
              <button @click="trendMode = 'margin'; rerenderTrend()"
                :class="trendMode === 'margin' ? 'bg-mp-page text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text'"
                class="text-xs font-medium px-3 py-1.5 rounded-md transition-all">Margin %</button>
            </div>
          </div>
          <div class="p-6">
            <div style="height:380px">
              <canvas ref="trendCanvas"></canvas>
            </div>
          </div>
          <!-- Trend table -->
          <div class="border-t border-mp-border overflow-x-auto">
            <table class="text-sm border-collapse" style="min-width:max-content; width:100%">
              <thead>
                <tr class="bg-mp-teal-subtle/30 border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase px-5 py-3 sticky left-0 bg-mp-teal-subtle/60 min-w-36">Metric</th>
                  <th v-for="p in data.trend.periods" :key="p"
                    class="text-center text-xs font-semibold text-white uppercase px-3 py-3 whitespace-nowrap min-w-28">
                    {{ p }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <template v-for="metricKey in trendMetricKeys" :key="metricKey">
                  <tr class="border-b border-mp-border hover:bg-mp-card-hover/30 transition-colors">
                    <td class="px-5 py-2.5 sticky left-0 bg-mp-card z-10">
                      <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="`background:${metricColors[metricKey]}`"></span>
                        <span class="text-mp-text-secondary text-xs font-semibold">{{ metricLabels[metricKey] }}</span>
                      </div>
                    </td>
                    <td v-for="p in data.trend.periods" :key="p" class="px-3 py-2.5 text-center">
                      <div class="text-mp-text-secondary text-xs font-semibold">
                        {{ trendMode === 'amount'
                            ? fmt(data.trend.metrics[metricKey]?.[p]?.value ?? 0)
                            : (data.trend.metrics[metricKey]?.[p]?.margin ?? 0) + '%' }}
                      </div>
                      <div v-if="data.trend.metrics[metricKey]?.[p]?.gr !== null && data.trend.metrics[metricKey]?.[p]?.gr !== undefined"
                        class="text-xs mt-0.5"
                        :class="(data.trend.metrics[metricKey]?.[p]?.gr ?? 0) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                        [GR {{ (data.trend.metrics[metricKey]?.[p]?.gr ?? 0) >= 0 ? '+' : '' }}{{ data.trend.metrics[metricKey]?.[p]?.gr ?? 0 }}%]
                      </div>
                      <div v-else class="text-xs mt-0.5 text-mp-muted">—</div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ════════════════════════════════════════════
             SECTION 4 — MANUAL INPUTS
        ════════════════════════════════════════════ -->
        <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
          <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest">Manual Inputs — D&amp;A / Interest / Tax</p>
              <p class="text-xs text-mp-muted mt-0.5">Enter amounts that are not in your uploaded expense data</p>
            </div>
            <span class="text-xs text-mp-muted italic">Auto-saved per period</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-mp-teal-subtle/30 border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase px-5 py-3">Period</th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-5 py-3">D&amp;A Amount</th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-5 py-3">Interest Amount</th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-5 py-3">Tax Amount</th>
                  <th class="text-center text-xs font-semibold text-white uppercase px-5 py-3">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <tr v-for="(row, i) in manualInputs" :key="row.period_label"
                  :class="i % 2 === 0 ? 'bg-mp-card' : 'bg-mp-card-hover/30'">
                  <td class="px-5 py-2.5 text-mp-text-secondary font-medium text-xs">{{ row.period_label }}</td>
                  <td class="px-5 py-2">
                    <input type="number" v-model.number="row.da_amount" min="0" step="1"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-1.5 text-mp-text-secondary text-xs text-right focus:outline-none focus:ring-1 focus:ring-mp-gold placeholder-mp-muted"
                      placeholder="0"/>
                  </td>
                  <td class="px-5 py-2">
                    <input type="number" v-model.number="row.interest_amount" min="0" step="1"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-1.5 text-mp-text-secondary text-xs text-right focus:outline-none focus:ring-1 focus:ring-mp-teal placeholder-mp-muted"
                      placeholder="0"/>
                  </td>
                  <td class="px-5 py-2">
                    <input type="number" v-model.number="row.tax_amount" min="0" step="1"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-1.5 text-mp-text-secondary text-xs text-right focus:outline-none focus:ring-1 focus:ring-mp-teal placeholder-mp-muted"
                      placeholder="0"/>
                  </td>
                  <td class="px-5 py-2 text-center">
                    <button @click="saveManual(row)" :disabled="row.saving"
                      class="text-xs px-3 py-1.5 rounded-lg font-medium transition-colors"
                      :class="row.saved ? 'bg-mp-success text-mp-success' : 'bg-mp-teal-dark hover:bg-mp-teal text-mp-text-secondary disabled:opacity-50'">
                      {{ row.saving ? '...' : row.saved ? '✓ Saved' : 'Save' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="px-6 py-4 border-t border-mp-border flex justify-end">
            <button @click="saveAllManuals" :disabled="savingAll"
              class="flex items-center gap-2 bg-mp-success hover:bg-mp-success disabled:opacity-50 text-mp-text-secondary text-sm font-medium px-5 py-2 rounded-lg transition-colors">
              <svg v-if="savingAll" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ savingAll ? 'Saving all...' : 'Save All &amp; Recalculate' }}
            </button>
          </div>
        </div>

        <!-- ════════════════════════════════════════════
             SECTION 5 — AUTO INSIGHTS & ALERTS
        ════════════════════════════════════════════ -->
        <div v-if="insights.length > 0">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Auto Insights &amp; Alerts</p>
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
                <p class="text-xs text-mp-text mt-1 leading-relaxed">{{ insight.body }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════
             SECTION 6 — ANALYST NOTES
        ════════════════════════════════════════════ -->
        <div>
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">
            Analyst Notes
            <span class="text-mp-muted normal-case font-normal ml-2">Saved per date range — {{ dateFrom }} → {{ dateTo }}</span>
          </p>
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
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
                    <button @click="startEdit(n)" class="flex items-center gap-1 text-xs text-white hover:text-white bg-mp-teal-subtle/40 hover:bg-mp-teal-subtle/70 px-3 py-1.5 rounded-lg transition-colors">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                      Edit
                    </button>
                    <button @click="deleteProfNote(n.id)" class="flex items-center gap-1 text-xs text-mp-danger hover:text-mp-danger bg-mp-danger/40 hover:bg-mp-danger/70 px-3 py-1.5 rounded-lg transition-colors">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      Delete
                    </button>
                  </div>
                </div>
                <div class="prose-dark text-sm text-mp-text leading-relaxed" v-html="n.note"></div>
              </div>
            </div>
            <div v-else class="px-6 py-4 text-xs text-mp-muted border-b border-mp-border">
              No notes saved for this date range yet. Write one below.
            </div>
            <!-- Rich Text Editor -->
            <div class="p-5">
              <p class="text-xs font-semibold text-mp-muted uppercase tracking-widest mb-3">
                {{ editingNoteId ? '✏️ Editing Note' : '+ New Note' }}
                <button v-if="editingNoteId" @click="cancelEdit" class="ml-3 text-mp-muted hover:text-mp-muted normal-case font-normal">Cancel</button>
              </p>
              <div class="flex flex-wrap items-center gap-1 bg-mp-card-hover border border-mp-border rounded-t-lg px-3 py-2">
                <div class="flex items-center gap-0.5 pr-2 border-r border-mp-border">
                  <button @click="profEditorCmd('bold')" class="w-7 h-7 rounded flex items-center justify-center text-sm font-bold text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page">B</button>
                  <button @click="profEditorCmd('italic')" class="w-7 h-7 rounded flex items-center justify-center text-sm italic text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page">I</button>
                  <button @click="profEditorCmd('underline')" class="w-7 h-7 rounded flex items-center justify-center text-sm underline text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page">U</button>
                </div>
                <div class="flex items-center gap-0.5 px-2 border-r border-mp-border">
                  <button @click="profEditorCmd('h1')" class="px-2 h-7 rounded text-xs font-bold text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page">H1</button>
                  <button @click="profEditorCmd('h2')" class="px-2 h-7 rounded text-xs font-bold text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page">H2</button>
                </div>
                <div class="flex items-center gap-0.5 px-2 border-r border-mp-border">
                  <button @click="profEditorCmd('insertUnorderedList')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                  </button>
                  <button @click="profEditorCmd('insertOrderedList')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h10M7 16h10M3 8h.01M3 12h.01M3 16h.01"/></svg>
                  </button>
                </div>
                <div class="flex items-center gap-0.5 px-2 border-r border-mp-border">
                  <button @click="profEditorCmd('highlight', '#c9a84c')" class="w-5 h-5 rounded bg-mp-gold border border-mp-border hover:scale-110 transition-transform"></button>
                  <button @click="profEditorCmd('highlight', '#10b981')" class="w-5 h-5 rounded bg-mp-success border border-mp-border hover:scale-110 transition-transform"></button>
                  <button @click="profEditorCmd('highlight', '#ef4444')" class="w-5 h-5 rounded bg-mp-danger border border-mp-border hover:scale-110 transition-transform"></button>
                  <button @click="profEditorCmd('removeHighlight')" class="w-5 h-5 rounded bg-mp-muted border border-mp-border hover:scale-110 transition-transform text-mp-text-secondary text-xs flex items-center justify-center">✕</button>
                </div>
                <div class="flex items-center gap-0.5 pl-2">
                  <button @click="profEditorCmd('undo')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                  </button>
                  <button @click="profEditorCmd('redo')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/></svg>
                  </button>
                </div>
              </div>
              <div ref="profEditorEl" contenteditable="true"
                class="min-h-[180px] bg-mp-card-hover border border-t-0 border-mp-border rounded-b-lg px-5 py-4 text-mp-text-secondary text-sm leading-relaxed focus:outline-none"></div>
              <div class="flex items-center justify-between mt-3">
                <p class="text-xs text-mp-muted">Rich text — supports bold, lists, highlights</p>
                <div class="flex items-center gap-3">
                  <span v-if="noteSaved" class="text-xs text-mp-success font-semibold">✓ Note saved</span>
                  <button @click="saveProfNote" :disabled="savingNote"
                    class="flex items-center gap-2 bg-mp-success hover:bg-mp-success disabled:opacity-50 text-mp-text-secondary text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    <svg v-if="savingNote" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    {{ savingNote ? 'Saving...' : (editingNoteId ? 'Update Note' : 'Save Note') }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ── ERROR STATE ── -->
      <div v-else-if="errorMsg" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="bg-mp-danger/30 border border-mp-danger rounded-xl p-6 text-center">
          <svg class="w-10 h-10 text-mp-danger mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          </svg>
          <p class="text-mp-danger font-semibold mb-1">Could not load data</p>
          <p class="text-mp-danger/70 text-sm">{{ errorMsg }}</p>
          <button @click="loadData" class="mt-4 bg-mp-danger/20 hover:bg-mp-danger text-mp-text-secondary text-sm px-4 py-2 rounded-lg transition-colors">
            Try Again
          </button>
        </div>
      </div>

      <!-- ── EMPTY STATE ── -->
      <div v-else class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
        <svg class="w-12 h-12 text-mp-muted mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-mp-muted text-lg font-semibold mb-2">Select your date range and click Apply</p>
        <p class="text-mp-muted text-sm mb-1">📅 Your expense data: <span class="text-mp-muted">Jan 2024 – Dec 2024</span></p>
        <p class="text-mp-muted text-sm">📊 Your sales data: <span class="text-mp-muted">Sep 2020 – Dec 2025</span></p>
        <p class="text-mp-muted text-xs mt-3">For best results, choose a period where both datasets overlap</p>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'

const props = defineProps({
  company:     Object,
  defaultFrom: String,
  defaultTo:   String,
  minDate:     String,
  maxDate:     String,
  hasMappings: Boolean,
})

// ── State ──
const loading       = ref(false)
const savingAll     = ref(false)
const data          = ref(null)
const errorMsg      = ref(null)
const insights      = ref([])
const notes         = ref([])
const savingNote    = ref(false)
const noteSaved     = ref(false)
const editingNoteId = ref(null)
const profEditorEl  = ref(null)
const dateFrom      = ref(props.defaultFrom)
const dateTo        = ref(props.defaultTo)
const periodType    = ref('month')
const trendMode     = ref('amount')
const manualInputs  = ref([])
const trendCanvas   = ref(null)

let Chart      = null
let trendChart = null

const trendMetricKeys = ['gross_profit', 'ebitda', 'ebit', 'ebt', 'net_profit']
const metricLabels = {
  gross_profit: 'Gross Profit',
  ebitda:       'EBITDA',
  ebit:         'EBIT',
  ebt:          'EBT',
  net_profit:   'Net Profit',
}
const metricColors = {
  gross_profit: '#00b4c8',
  ebitda:       '#c9a84c',
  ebit:         '#10b981',
  ebt:          '#f59e0b',
  net_profit:   '#00b4c8',
}

// ── KPI cards computed ──
const kpiCards = computed(() => {
  if (!data.value) return []
  const k = data.value.kpis
  return [
    { key: 'revenue',      label: 'Revenue',      amount: k.revenue.amount,      margin: k.revenue.margin,      color: '#00b4c8' },
    { key: 'gross_profit', label: 'Gross Profit',  amount: k.gross_profit.amount, margin: k.gross_profit.margin, color: '#00b4c8' },
    { key: 'ebitda',       label: 'EBITDA',        amount: k.ebitda.amount,       margin: k.ebitda.margin,       color: '#c9a84c' },
    { key: 'ebit',         label: 'EBIT',          amount: k.ebit.amount,         margin: k.ebit.margin,         color: '#10b981' },
    { key: 'ebt',          label: 'EBT',           amount: k.ebt.amount,          margin: k.ebt.margin,          color: '#f59e0b' },
    { key: 'net_profit',   label: 'Net Profit',    amount: k.net_profit.amount,   margin: k.net_profit.margin,   color: '#00b4c8' },
  ]
})

// ── Load Chart.js ──
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

// ── Render trend chart ──
function renderTrend() {
  if (!data.value || !trendCanvas.value || !Chart) return
  if (trendChart) { trendChart.destroy(); trendChart = null }

  const ctx     = trendCanvas.value.getContext('2d')
  const periods = data.value.trend.periods
  const metrics = data.value.trend.metrics
  const key     = trendMode.value

  function alpha(hex, a) {
    const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16)
    return `rgba(${r},${g},${b},${a})`
  }

  const datasets = trendMetricKeys.map((mk, i) => ({
    label:                metricLabels[mk],
    data:                 periods.map(p => key === 'amount' ? (metrics[mk]?.[p]?.value ?? 0) : (metrics[mk]?.[p]?.margin ?? 0)),
    borderColor:          metricColors[mk],
    backgroundColor:      alpha(metricColors[mk], 0.08),
    pointBackgroundColor: metricColors[mk],
    pointBorderColor:     '#0c1829',
    pointRadius:          4,
    pointHoverRadius:     6,
    fill:                 i === 0,
    tension:              0.3,
    borderWidth:          2,
  }))

  trendChart = new Chart(ctx, {
    type: 'line',
    data: { labels: periods, datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { color: '#ffffff', font: { size: 11 }, usePointStyle: true } },
        tooltip: {
          callbacks: {
            label: ctx => {
              const raw = ctx.raw
              return ` ${ctx.dataset.label}: ${trendMode.value === 'amount'
                ? Number(raw).toLocaleString('en-US', { maximumFractionDigits: 0 })
                : raw + '%'}`
            }
          }
        }
      },
      scales: {
        x: { ticks: { color: '#64748b', font: { size: 10 } }, grid: { color: '#112240' } },
        y: {
          ticks: {
            color: '#64748b',
            font: { size: 10 },
            callback: v => trendMode.value === 'amount'
              ? Number(v).toLocaleString('en-US', { notation: 'compact' })
              : v + '%'
          },
          grid: { color: '#112240' },
        }
      }
    }
  })
}

function rerenderTrend() {
  nextTick(() => renderTrend())
}

// ── Load dashboard data ──
async function loadData() {
  loading.value  = true
  errorMsg.value = null
  if (trendChart) { trendChart.destroy(); trendChart = null }
  data.value = null
  try {
    const res = await axios.get(`/companies/${props.company.id}/profitability/data`, {
      params: { date_from: dateFrom.value, date_to: dateTo.value, period_type: periodType.value }
    })
    data.value = res.data
    manualInputs.value = (res.data.manual_list || []).map(row => ({
      ...row, saving: false, saved: false,
    }))
    loading.value = false
    await nextTick()
    await loadChartJs()
    await nextTick()
    setTimeout(() => renderTrend(), 100)
    loadInsights()
    loadNotes()
  } catch (e) {
    console.error(e)
    errorMsg.value = e?.response?.data?.message ?? 'Failed to load data. Check date range and try again.'
    loading.value = false
  }
}

// ── Manual inputs ──
async function saveManual(row) {
  row.saving = true; row.saved = false
  try {
    await axios.post(`/companies/${props.company.id}/profitability/manual-input`, {
      period_type: periodType.value, period_label: row.period_label,
      da_amount: row.da_amount || 0, interest_amount: row.interest_amount || 0, tax_amount: row.tax_amount || 0,
    })
    row.saved = true
    setTimeout(() => { row.saved = false }, 2500)
  } catch (e) { console.error(e) } finally { row.saving = false }
}

async function saveAllManuals() {
  savingAll.value = true
  try {
    await Promise.all(manualInputs.value.map(row =>
      axios.post(`/companies/${props.company.id}/profitability/manual-input`, {
        period_type: periodType.value, period_label: row.period_label,
        da_amount: row.da_amount || 0, interest_amount: row.interest_amount || 0, tax_amount: row.tax_amount || 0,
      })
    ))
    await loadData()
  } catch (e) { console.error(e) } finally { savingAll.value = false }
}

// ── Helpers ──
function fmt(val) {
  if (val === null || val === undefined) return '—'
  return Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

// ── Insights ──
async function loadInsights() {
  try {
    const res = await axios.get(`/companies/${props.company.id}/profitability/insights`, {
      params: { date_from: dateFrom.value, date_to: dateTo.value }
    })
    insights.value = res.data.insights || []
  } catch(e) { console.error(e) }
}

// ── Notes ──
async function loadNotes() {
  try {
    const res = await axios.get(`/companies/${props.company.id}/profitability/notes`, {
      params: { date_from: dateFrom.value, date_to: dateTo.value }
    })
    notes.value = res.data.notes || []
  } catch(e) { console.error(e) }
}

function startEdit(n) {
  editingNoteId.value = n.id
  nextTick(() => {
    if (profEditorEl.value) { profEditorEl.value.innerHTML = n.note; profEditorEl.value.focus() }
    setTimeout(() => profEditorEl.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 100)
  })
}

function cancelEdit() {
  editingNoteId.value = null
  if (profEditorEl.value) profEditorEl.value.innerHTML = ''
}

async function deleteProfNote(id) {
  if (!confirm('Delete this note?')) return
  try {
    await axios.delete(`/companies/${props.company.id}/profitability/notes/${id}`)
    await loadNotes()
  } catch(e) { console.error(e) }
}

async function saveProfNote() {
  const html = profEditorEl.value?.innerHTML?.trim()
  if (!html || html === '<br>') return
  savingNote.value = true; noteSaved.value = false
  try {
    if (editingNoteId.value) {
      await axios.put(`/companies/${props.company.id}/profitability/notes/${editingNoteId.value}`, { note: html })
    } else {
      await axios.post(`/companies/${props.company.id}/profitability/notes`, {
        date_from: dateFrom.value, date_to: dateTo.value, note: html
      })
    }
    noteSaved.value = true; editingNoteId.value = null
    if (profEditorEl.value) profEditorEl.value.innerHTML = ''
    await loadNotes()
    setTimeout(() => { noteSaved.value = false }, 3000)
  } catch(e) { console.error(e) } finally { savingNote.value = false }
}

function profEditorCmd(cmd, value = null) {
  const el = profEditorEl.value; if (!el) return; el.focus()
  if (cmd === 'h1') document.execCommand('formatBlock', false, 'h1')
  else if (cmd === 'h2') document.execCommand('formatBlock', false, 'h2')
  else if (cmd === 'highlight') document.execCommand('hiliteColor', false, value)
  else if (cmd === 'removeHighlight') document.execCommand('hiliteColor', false, 'transparent')
  else document.execCommand(cmd, false, value)
}
</script>