<template>
  <Head title="Evaluate Prospect" />
  <AuthenticatedLayout>
  <div class="min-h-screen bg-mp-page text-white">

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="border-b border-mp-border bg-mp-card sticky top-0 z-30">
      <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-4">
          <a href="/investor-decision" class="text-white hover:text-white text-sm transition-colors">← Tool Home</a>
          <span class="text-white">|</span>
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-mp-card-hover border border-mp-border flex items-center justify-center overflow-hidden">
              <img v-if="company.logo" :src="`/storage/${company.logo}`" class="w-full h-full object-contain" />
              <span v-else class="text-xs font-bold text-white">{{ company.name.charAt(0) }}</span>
            </div>
            <span class="text-white font-semibold">{{ company.name }}</span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-mp-teal-subtle text-white border border-mp-teal">Prospect</span>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <!-- Switch prospect -->
          <select v-model="switchTo" @change="goToProspect"
            class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-1.5 text-xs text-white focus:outline-none focus:border-mp-teal">
            <option value="">Switch prospect…</option>
            <option v-for="p in prospects" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <!-- Save error -->
          <span v-if="saveError" class="text-xs text-mp-danger bg-mp-danger/30 border border-mp-danger/50 px-3 py-1.5 rounded-lg">
            ⚠️ {{ saveError }}
          </span>
          <!-- Last saved indicator -->
          <span v-if="props.savedEvaluation && !saved" class="text-xs text-white">
            ✓ Previously saved
          </span>
          <!-- Verdict badge -->
          <div v-if="verdict" :class="verdictStyle(verdict).badge" class="px-3 py-1 rounded-full text-xs font-bold border">
            {{ verdictStyle(verdict).label }}
          </div>
          <!-- Save button -->
          <button @click="saveEvaluation"
            :disabled="saving"
            :class="saving ? 'bg-mp-muted cursor-not-allowed' : saved ? 'bg-mp-success' : 'bg-mp-gold-dark hover:bg-mp-gold'"
            class="px-4 py-1.5 text-white text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5">
            <span v-if="saving">Saving…</span>
            <span v-else-if="saved">✓ Saved</span>
            <span v-else>💾 Save Evaluation</span>
          </button>
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-8">

      <!-- ── TOP ROW: Score card + Verdict + KPI summary ───────────────────── -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

        <!-- Overall Score Gauge -->
        <div class="bg-mp-card border border-mp-border rounded-2xl p-6 flex flex-col items-center">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-5">Overall Score</p>
          <!-- SVG Gauge -->
          <div class="relative w-44 h-24 mb-4">
            <svg viewBox="0 0 180 95" class="w-full h-full">
              <!-- Track -->
              <path d="M 10 90 A 80 80 0 0 1 170 90" fill="none" stroke="#112240" stroke-width="14" stroke-linecap="round"/>
              <!-- Fill -->
              <path :d="gaugePath(overallScore)" fill="none" :stroke="gaugeColor(overallScore)" stroke-width="14" stroke-linecap="round"
                style="transition: stroke-dasharray 0.8s ease"/>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-end pb-1">
              <span class="text-4xl font-bold" :style="{color: gaugeColor(overallScore)}">{{ Math.round(overallScore) }}</span>
              <span class="text-xs text-white">out of 100</span>
            </div>
          </div>
          <!-- Score breakdown pills -->
          <div class="w-full grid grid-cols-2 gap-2 mt-2">
            <div v-for="dim in scoreDimensions" :key="dim.key"
              class="bg-mp-card-hover/60 rounded-lg px-3 py-2 flex items-center justify-between">
              <span class="text-xs text-white truncate mr-1">{{ dim.label }}</span>
              <span class="text-xs font-bold" :style="{color: gaugeColor(dim.score * 10)}">{{ dim.score }}/10</span>
            </div>
          </div>
        </div>

        <!-- Verdict Selector -->
        <div class="bg-mp-card border border-mp-border rounded-2xl p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Investment Verdict</p>
          <div class="space-y-2">
            <button v-for="v in verdictOptions" :key="v.value"
              @click="verdict = v.value"
              :class="[
                'w-full flex items-center gap-3 px-4 py-3 rounded-xl border transition-all text-left',
                verdict === v.value ? v.activeClass : 'border-mp-border bg-mp-card-hover/40 hover:border-mp-border'
              ]">
              <span class="text-lg">{{ v.icon }}</span>
              <div class="flex-1">
                <p class="text-sm font-semibold" :class="verdict === v.value ? 'text-white' : 'text-white'">{{ v.label }}</p>
                <p class="text-xs" :class="verdict === v.value ? 'text-white' : 'text-white'">{{ v.desc }}</p>
              </div>
              <div v-if="verdict === v.value" class="w-2 h-2 rounded-full bg-white"></div>
            </button>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="bg-mp-card border border-mp-border rounded-2xl p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Quick Stats</p>
          <div class="space-y-3">
            <template v-if="financials.has_data">
              <div class="flex items-center justify-between py-2 border-b border-mp-border">
                <span class="text-xs text-white">Revenue</span>
                <span class="text-sm font-semibold text-white">{{ fmtM(financials.revenue) }}</span>
              </div>
              <div class="flex items-center justify-between py-2 border-b border-mp-border">
                <span class="text-xs text-white">EBITDA Margin</span>
                <span class="text-sm font-semibold" :class="financials.ebitda_margin >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                  {{ financials.ebitda_margin !== null ? financials.ebitda_margin + '%' : '—' }}
                </span>
              </div>
              <div class="flex items-center justify-between py-2 border-b border-mp-border">
                <span class="text-xs text-white">Net Margin</span>
                <span class="text-sm font-semibold" :class="financials.net_margin >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                  {{ financials.net_margin !== null ? financials.net_margin + '%' : '—' }}
                </span>
              </div>
            </template>
            <div class="flex items-center justify-between py-2 border-b border-mp-border">
              <span class="text-xs text-white">Entry Valuation</span>
              <span class="text-sm font-semibold text-white">{{ fmtM(company.entry_valuation) }}</span>
            </div>
            <div class="flex items-center justify-between py-2 border-b border-mp-border">
              <span class="text-xs text-white">Deal Size</span>
              <span class="text-sm font-semibold text-white">{{ fmtM(company.invested_amount) }} <span class="text-xs text-white">{{ company.invested_currency }}</span></span>
            </div>
            <div class="flex items-center justify-between py-2">
              <span class="text-xs text-white">Equity Stake</span>
              <span class="text-sm font-semibold text-white">{{ company.equity_stake ? company.equity_stake + '%' : '—' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ── SCORING TABLE ────────────────────────────────────────────────── -->
      <div class="bg-mp-card border border-mp-border rounded-2xl p-6 mb-8">
        <div class="flex items-center justify-between mb-6">
          <div>
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-1">Evaluation Scorecard</p>
            <p class="text-xs text-white">Rate each dimension 1–10. Set your own scoring thresholds, then click Auto-score.</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="showThresholds = !showThresholds"
              :class="showThresholds ? 'bg-mp-gold/50 border-mp-gold/50 text-white' : 'bg-mp-card-hover border-mp-border text-white hover:text-white'"
              class="px-3 py-1.5 border text-xs rounded-lg transition-colors">
              ⚙️ {{ showThresholds ? 'Hide' : 'Set' }} Scoring Criteria
            </button>
            <button @click="autoScoreFromData"
              class="px-3 py-1.5 bg-mp-teal-subtle/50 border border-mp-teal/40 text-white text-xs rounded-lg hover:bg-mp-teal-subtle/50 transition-colors">
              ✨ Auto-score from data
            </button>
          </div>
        </div>

        <!-- ── EDITABLE THRESHOLDS PANEL ─────────────────────────────────── -->
        <div v-if="showThresholds" class="mb-6 border border-mp-gold/30 bg-mp-card-hover/40 rounded-xl p-5">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-1">Scoring Criteria</p>
          <p class="text-xs text-white mb-5">
            Define your own thresholds for each financial dimension. Auto-score will use these values.
            Each row maps a minimum value to a score — the system picks the highest score whose threshold is met.
          </p>

          <div class="space-y-6">
            <template v-for="dim in financialDimensions" :key="dim.key">
              <div class="bg-mp-card rounded-xl p-4 border border-mp-border/50">

                <!-- Dim header -->
                <div class="flex items-center justify-between mb-3">
                  <div>
                    <span class="text-sm font-semibold text-white">{{ dim.label }}</span>
                    <span class="text-xs text-white ml-2">
                      {{ dim.higherIsBetter ? '↑ Higher is better' : '↓ Lower is better' }}
                      · unit: <span class="text-white">{{ dim.unit }}</span>
                    </span>
                  </div>
                  <div class="text-xs text-white">
                    Current: <span class="text-white font-semibold">{{ dim.currentValue }}</span>
                  </div>
                </div>

                <!-- Threshold grid — one column per score tier -->
                <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(110px, 1fr))">
                  <div v-for="tier in thresholds[dim.key]" :key="tier.score"
                    class="rounded-lg border p-2.5"
                    :class="tier.score >= 9 ? 'border-mp-success/50 bg-mp-success/30'
                          : tier.score >= 7 ? 'border-mp-teal/50 bg-mp-teal-subtle/30'
                          : tier.score >= 5 ? 'border-mp-warning/50 bg-mp-warning/30'
                          : tier.score >= 3 ? 'border-mp-warning/50 bg-mp-warning/30'
                          :                   'border-mp-danger/50 bg-mp-danger/30'">
                    <p class="text-xs font-bold mb-1.5"
                      :class="tier.score >= 9 ? 'text-mp-success'
                            : tier.score >= 7 ? 'text-white'
                            : tier.score >= 5 ? 'text-mp-warning'
                            : tier.score >= 3 ? 'text-mp-warning'
                            :                   'text-mp-danger'">
                      Score {{ tier.score }}
                    </p>
                    <div class="flex items-center gap-1">
                      <span class="text-white text-xs">{{ dim.higherIsBetter ? '≥' : '≤' }}</span>
                      <input
                        type="number"
                        v-model.number="tier.threshold"
                        class="w-full bg-mp-card-hover border border-mp-border rounded px-1.5 py-1 text-xs text-white text-center focus:border-mp-teal focus:outline-none"
                      />
                    </div>
                    <p class="text-white text-xs mt-1 text-center">{{ dim.unit }}</p>
                  </div>
                </div>

                <!-- Floor note -->
                <p class="text-xs text-white mt-2">
                  Below all thresholds → Score 1 (floor)
                </p>
              </div>
            </template>
          </div>

          <div class="flex items-center justify-between mt-5 pt-4 border-t border-mp-border">
            <button @click="resetThresholds"
              class="text-xs text-white hover:text-white transition-colors">
              ↺ Reset to defaults
            </button>
            <button @click="showThresholds = false; autoScoreFromData()"
              class="px-4 py-1.5 bg-mp-teal hover:bg-mp-teal-dark text-white text-xs font-semibold rounded-lg transition-colors">
              ✓ Save & Auto-score
            </button>
          </div>
        </div>

        <!-- Tab bar: Financial / Non-Financial -->
        <div class="flex gap-1 mb-5 bg-mp-card-hover rounded-lg p-1 w-fit">
          <button v-for="tab in ['financial', 'non_financial']" :key="tab"
            @click="scoreTab = tab"
            :class="scoreTab === tab ? 'bg-mp-page text-white' : 'text-white hover:text-white'"
            class="px-4 py-1.5 rounded-md text-xs font-semibold transition-colors capitalize">
            {{ tab === 'financial' ? '📊 Financial' : '🌐 Non-Financial' }}
          </button>
        </div>

        <!-- Score rows -->
        <div class="space-y-3">
          <template v-for="dim in visibleDimensions" :key="dim.key">
            <div class="bg-mp-card-hover/50 rounded-xl px-5 py-4">
              <div class="flex items-start gap-4">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 mb-0.5">
                    <span class="text-sm font-semibold text-white">{{ dim.label }}</span>
                    <span class="text-xs text-white bg-mp-page px-2 py-0.5 rounded-full">{{ dim.weight }}% weight</span>
                  </div>
                  <p class="text-xs text-white">{{ dim.desc }}</p>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                  <input type="range" min="1" max="10" step="1"
                    v-model="scores[dim.key]"
                    class="w-32 accent-violet-500"
                  />
                  <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-lg border"
                    :class="scoreChipClass(scores[dim.key])">
                    {{ scores[dim.key] }}
                  </div>
                </div>
              </div>
              <div class="mt-3 h-1.5 bg-mp-page rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-300"
                  :class="scoreBarClass(scores[dim.key])"
                  :style="{width: scores[dim.key] * 10 + '%'}"></div>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- ── FINANCIAL ANALYSIS ──────────────────────────────────────────── -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

        <!-- P&L Metrics -->
        <div class="bg-mp-card border border-mp-border rounded-2xl p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">
            Financial Statements
            <span v-if="financials.has_data" class="ml-2 text-white font-normal normal-case">{{ financials.period_to }}</span>
          </p>

          <div v-if="!financials.has_data" class="text-center py-10 text-white text-sm">
            No financial statements uploaded yet.
          </div>
          <template v-else>
            <!-- Margin bars -->
            <div class="space-y-4 mb-6">
              <div v-for="row in plRows" :key="row.label">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-xs text-white">{{ row.label }}</span>
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-white">{{ fmtM(row.value) }}</span>
                    <span v-if="row.margin !== null" class="text-xs px-1.5 py-0.5 rounded"
                      :class="row.margin >= 0 ? 'bg-mp-success/50 text-mp-success' : 'bg-mp-danger/50 text-mp-danger'">
                      {{ row.margin }}%
                    </span>
                  </div>
                </div>
                <div class="h-2 bg-mp-card-hover rounded-full overflow-hidden">
                  <div class="h-full rounded-full transition-all"
                    :class="row.color"
                    :style="{width: Math.min(100, Math.abs(row.pct || 0)) + '%'}"></div>
                </div>
              </div>
            </div>
            <!-- Ratios grid -->
            <div class="grid grid-cols-3 gap-3">
              <div v-for="r in ratioCards" :key="r.label" class="bg-mp-card-hover rounded-xl p-3 text-center">
                <p class="text-2xl font-bold mb-1" :class="r.color">{{ r.value !== null ? r.value + (r.unit || '') : '—' }}</p>
                <p class="text-xs text-white">{{ r.label }}</p>
              </div>
            </div>
          </template>
        </div>

        <!-- Revenue Trend Chart -->
        <div class="bg-mp-card border border-mp-border rounded-2xl p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Revenue & Profit Trend</p>
          <div v-if="!financials.has_data || !financials.trend?.length" class="text-center py-10 text-white text-sm">
            No trend data available.
          </div>
          <template v-else>
            <!-- SVG bar chart -->
            <div class="relative">
              <svg :viewBox="`0 0 ${trendWidth} 180`" class="w-full">
                <!-- Grid lines -->
                <line v-for="y in [30,90,150]" :key="y" :x1="40" :y1="y" :x2="trendWidth - 10" :y2="y"
                  stroke="#112240" stroke-width="1"/>
                <!-- Bars -->
                <template v-for="(pt, i) in financials.trend" :key="i">
                  <rect :x="trendBarX(i)" :y="trendBarY(pt.revenue)" :width="trendBarW"
                    :height="trendBarH(pt.revenue)" rx="3"
                    fill="#00b4c8" opacity="0.8"/>
                  <rect :x="trendBarX(i) + trendBarW + 2" :y="trendBarY(pt.net_profit)"
                    :width="trendBarW * 0.7" :height="Math.max(2, trendBarH(pt.net_profit))" rx="2"
                    :fill="pt.net_profit >= 0 ? '#10b981' : '#ef4444'" opacity="0.9"/>
                  <!-- X label -->
                  <text :x="trendBarX(i) + trendBarW" :y="175" fill="#64748b" font-size="10" text-anchor="middle">
                    {{ pt.period }}
                  </text>
                </template>
              </svg>
              <!-- Legend -->
              <div class="flex gap-4 mt-2 justify-center">
                <div class="flex items-center gap-1.5 text-xs text-white">
                  <div class="w-3 h-3 rounded-sm bg-mp-teal opacity-80"></div>Revenue
                </div>
                <div class="flex items-center gap-1.5 text-xs text-white">
                  <div class="w-3 h-3 rounded-sm bg-mp-success opacity-90"></div>Net Profit
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- ── SALES + KPI + STUDY ROW ──────────────────────────────────────── -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

        <!-- Sales Momentum -->
        <div class="bg-mp-card border border-mp-border rounded-2xl p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Sales Momentum</p>
          <div v-if="!salesData.has_data" class="text-center py-8 text-white text-sm">No sales data uploaded.</div>
          <template v-else>
            <div class="grid grid-cols-2 gap-3 mb-4">
              <div class="bg-mp-card-hover rounded-xl p-3">
                <p class="text-xs text-white mb-1">Total Revenue</p>
                <p class="text-lg font-bold text-white">{{ fmtM(salesData.total_revenue) }}</p>
              </div>
              <div class="bg-mp-card-hover rounded-xl p-3">
                <p class="text-xs text-white mb-1">Customers</p>
                <p class="text-lg font-bold text-white">{{ salesData.customer_count?.toLocaleString() }}</p>
              </div>
              <div class="bg-mp-card-hover rounded-xl p-3">
                <p class="text-xs text-white mb-1">Products</p>
                <p class="text-lg font-bold text-white">{{ salesData.product_count }}</p>
              </div>
              <div class="bg-mp-card-hover rounded-xl p-3">
                <p class="text-xs text-white mb-1">Rev Growth
                  <span v-if="salesData.growth_basis" class="text-white ml-1">({{ salesData.growth_basis }})</span>
                </p>
                <p class="text-lg font-bold" :class="salesData.revenue_growth > 0 ? 'text-mp-success' : salesData.revenue_growth < 0 ? 'text-mp-danger' : 'text-white'">
                  {{ salesData.revenue_growth !== null ? (salesData.revenue_growth > 0 ? '+' : '') + salesData.revenue_growth + '%' : '—' }}
                </p>
              </div>
            </div>
            <!-- Tiny sparkline -->
            <div v-if="salesData.monthly_trend?.length > 1">
              <p class="text-xs text-white mb-2">Monthly Revenue Trend</p>
              <svg viewBox="0 0 200 50" class="w-full">
                <polyline :points="sparklinePoints(salesData.monthly_trend)"
                  fill="none" stroke="#00b4c8" stroke-width="2" stroke-linecap="round"/>
                <polyline :points="sparklinePoints(salesData.monthly_trend)"
                  fill="url(#sparkGrad)" stroke="none" opacity="0.2"/>
                <defs>
                  <linearGradient id="sparkGrad" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="#00b4c8"/>
                    <stop offset="100%" stop-color="#00b4c800"/>
                  </linearGradient>
                </defs>
              </svg>
            </div>
          </template>
        </div>

        <!-- KPI Health -->
        <div class="bg-mp-card border border-mp-border rounded-2xl p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">KPI Health</p>
          <div v-if="!kpiData.has_data" class="text-center py-8 text-white text-sm">No KPIs configured.</div>
          <template v-else>
            <!-- Health score ring (SVG) -->
            <div class="flex items-center gap-4 mb-4">
              <svg viewBox="0 0 60 60" class="w-16 h-16 flex-shrink-0">
                <circle cx="30" cy="30" r="24" fill="none" stroke="#112240" stroke-width="6"/>
                <circle cx="30" cy="30" r="24" fill="none" :stroke="kpiData.health_score >= 80 ? '#10b981' : kpiData.health_score >= 50 ? '#f59e0b' : '#ef4444'"
                  stroke-width="6" stroke-linecap="round"
                  :stroke-dasharray="`${(kpiData.health_score / 100) * 150.8} 150.8`"
                  stroke-dashoffset="37.7" transform="rotate(-90 30 30)"/>
                <text x="30" y="35" text-anchor="middle" fill="white" font-size="14" font-weight="bold">{{ kpiData.health_score }}%</text>
              </svg>
              <div>
                <p class="text-sm font-semibold text-white">{{ kpiData.health_score >= 80 ? 'Healthy' : kpiData.health_score >= 50 ? 'Watch' : 'At Risk' }}</p>
                <p class="text-xs text-white">{{ kpiData.on_track }} on track / {{ kpiData.at_risk }} at risk</p>
              </div>
            </div>
            <!-- Status pills -->
            <div class="flex gap-2 mb-4">
              <div class="flex-1 bg-mp-success/30 border border-mp-success/40 rounded-lg p-2 text-center">
                <p class="text-lg font-bold text-mp-success">{{ kpiData.on_track }}</p>
                <p class="text-xs text-white">On Track</p>
              </div>
              <div class="flex-1 bg-mp-warning/30 border border-mp-warning/40 rounded-lg p-2 text-center">
                <p class="text-lg font-bold text-mp-warning">{{ kpiData.watch }}</p>
                <p class="text-xs text-white">Watch</p>
              </div>
              <div class="flex-1 bg-mp-danger/30 border border-mp-danger/40 rounded-lg p-2 text-center">
                <p class="text-lg font-bold text-mp-danger">{{ kpiData.at_risk }}</p>
                <p class="text-xs text-white">At Risk</p>
              </div>
            </div>
            <!-- Top KPIs list -->
            <div class="space-y-2">
              <div v-for="k in kpiData.items" :key="k.name"
                class="flex items-center justify-between text-xs">
                <span class="text-white truncate mr-2">{{ k.name }}</span>
                <span :class="k.status === 'on_track' ? 'text-mp-success' : k.status === 'at_risk' ? 'text-mp-danger' : 'text-mp-warning'"
                  class="font-semibold flex-shrink-0">
                  {{ k.status === 'on_track' ? '✓' : k.status === 'at_risk' ? '✗' : '~' }}
                  {{ k.actual }}/{{ k.target }}
                </span>
              </div>
            </div>
          </template>
        </div>

        <!-- Financial Study / Budget -->
        <div class="bg-mp-card border border-mp-border rounded-2xl p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Financial Study</p>
          <div v-if="!studyData.has_data" class="text-center py-6 text-white text-sm">
            No financial study built yet.
            <a :href="`/portfolio-companies/${company.id}/financial-studies/create`" class="block mt-2 text-white hover:text-white text-xs">+ Build Study →</a>
          </div>
          <template v-else>
            <p class="text-sm font-semibold text-white mb-4">{{ studyData.study_name }}</p>
            <div class="space-y-3">
              <div class="bg-mp-card-hover rounded-xl p-3 flex items-center justify-between">
                <span class="text-xs text-white">NPV</span>
                <span class="text-sm font-bold" :class="(studyData.npv || 0) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                  {{ studyData.npv ? fmtM(studyData.npv) : '—' }}
                </span>
              </div>
              <div class="bg-mp-card-hover rounded-xl p-3 flex items-center justify-between">
                <span class="text-xs text-white">IRR</span>
                <span class="text-sm font-bold text-white">{{ studyData.irr ? studyData.irr + '%' : '—' }}</span>
              </div>
              <div class="bg-mp-card-hover rounded-xl p-3 flex items-center justify-between">
                <span class="text-xs text-white">MOIC</span>
                <span class="text-sm font-bold text-white">{{ studyData.moic ? studyData.moic + 'x' : '—' }}</span>
              </div>
              <div class="bg-mp-card-hover rounded-xl p-3 flex items-center justify-between">
                <span class="text-xs text-white">Years Projected</span>
                <span class="text-sm font-bold text-white">{{ studyData.years }}</span>
              </div>
            </div>
          </template>

          <!-- Budget variance if available -->
          <template v-if="budgetData.has_data">
            <div class="mt-4 pt-4 border-t border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Budget vs Actual {{ budgetData.year }}</p>
              <div class="grid grid-cols-2 gap-2">
                <div class="bg-mp-card-hover rounded-lg p-2.5">
                  <p class="text-xs text-white mb-0.5">Budget YTD</p>
                  <p class="text-sm font-bold text-white">{{ fmtM(budgetData.budget_revenue) }}</p>
                </div>
                <div class="bg-mp-card-hover rounded-lg p-2.5">
                  <p class="text-xs text-white mb-0.5">Actual YTD</p>
                  <p class="text-sm font-bold text-white">{{ fmtM(budgetData.actual_revenue) }}</p>
                </div>
              </div>
              <div class="mt-2 px-3 py-2 rounded-lg text-center text-xs font-semibold"
                :class="(budgetData.variance_pct || 0) >= 0 ? 'bg-mp-success/30 text-mp-success border border-mp-success/40' : 'bg-mp-danger/30 text-mp-danger border border-mp-danger/40'">
                {{ (budgetData.variance_pct || 0) >= 0 ? '▲' : '▼' }}
                {{ Math.abs(budgetData.variance_pct || 0) }}% vs budget
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- ── ANALYST NOTES ────────────────────────────────────────────────── -->
      <div class="bg-mp-card border border-mp-border rounded-2xl p-6 mb-8">
        <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Analyst Notes</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-xs text-white mb-2">Investment Thesis</label>
            <textarea v-model="notes.thesis" rows="4" placeholder="Why should we invest? What's the core value creation thesis?"
              class="w-full bg-mp-card-hover border border-mp-border rounded-xl px-4 py-3 text-sm text-white placeholder-gray-600 focus:border-mp-teal focus:outline-none resize-none"></textarea>
          </div>
          <div>
            <label class="block text-xs text-white mb-2">Key Risks & Mitigants</label>
            <textarea v-model="notes.risks" rows="4" placeholder="What are the main risks? How can they be mitigated?"
              class="w-full bg-mp-card-hover border border-mp-border rounded-xl px-4 py-3 text-sm text-white placeholder-gray-600 focus:border-mp-teal focus:outline-none resize-none"></textarea>
          </div>
          <div>
            <label class="block text-xs text-white mb-2">Key Conditions / DD Checklist</label>
            <textarea v-model="notes.conditions" rows="3" placeholder="What conditions must be met before investment?"
              class="w-full bg-mp-card-hover border border-mp-border rounded-xl px-4 py-3 text-sm text-white placeholder-gray-600 focus:border-mp-teal focus:outline-none resize-none"></textarea>
          </div>
          <div>
            <label class="block text-xs text-white mb-2">Exit Strategy</label>
            <textarea v-model="notes.exit" rows="3" placeholder="What's the expected exit route and timeline?"
              class="w-full bg-mp-card-hover border border-mp-border rounded-xl px-4 py-3 text-sm text-white placeholder-gray-600 focus:border-mp-teal focus:outline-none resize-none"></textarea>
          </div>
        </div>
      </div>

    </div>
  </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:         { type: Object, required: true },
  financials:      { type: Object, default: () => ({ has_data: false }) },
  salesData:       { type: Object, default: () => ({ has_data: false }) },
  budgetData:      { type: Object, default: () => ({ has_data: false }) },
  kpiData:         { type: Object, default: () => ({ has_data: false }) },
  studyData:       { type: Object, default: () => ({ has_data: false }) },
  prospects:       { type: Array,  default: () => [] },
  savedEvaluation: { type: Object, default: null },
})

// ── State ──────────────────────────────────────────────────────────────────
const switchTo      = ref('')
const verdict       = ref('')
const saving        = ref(false)
const saved         = ref(false)
const saveError     = ref('')
const scoreTab      = ref('financial')
const showThresholds = ref(false)

const notes = ref({ thesis: '', risks: '', conditions: '', exit: '' })

const scores = ref({
  revenue_growth: 5, profitability: 5, financial_health: 5, sales_momentum: 5,
  kpi_performance: 5,
  team_quality: 5, market_size: 5, competitive_moat: 5, esg_governance: 5, exit_potential: 5,
})

// ── Default thresholds — user can change every single value ────────────────
// Each tier: { score, threshold }
// For higher-is-better: value >= threshold → earns that score
// For lower-is-better:  value <= threshold → earns that score
// System picks the HIGHEST score whose threshold is met. Floor = 1.
function defaultThresholds() {
  return {
    revenue_growth:   [ { score: 9, threshold: 30 }, { score: 7, threshold: 15 }, { score: 5, threshold: 5  }, { score: 3, threshold: 0  } ],
    profitability:    [ { score: 9, threshold: 20 }, { score: 7, threshold: 10 }, { score: 6, threshold: 5  }, { score: 4, threshold: 0  } ],
    financial_health: [ { score: 9, threshold: 0.3}, { score: 7, threshold: 0.8}, { score: 5, threshold: 1.5}, { score: 3, threshold: 2.5} ],
    sales_momentum:   [ { score: 9, threshold: 20 }, { score: 7, threshold: 10 }, { score: 5, threshold: 0  }, { score: 3, threshold: -10} ],
    kpi_performance:  [ { score: 9, threshold: 80 }, { score: 7, threshold: 60 }, { score: 5, threshold: 40 }, { score: 3, threshold: 20 } ],
  }
}

const thresholds = ref(defaultThresholds())

function resetThresholds() {
  thresholds.value = defaultThresholds()
}

// ── Scoring helper — applies user-defined thresholds ──────────────────────
function applyThreshold(key, value, higherIsBetter = true) {
  const tiers = [...thresholds.value[key]]
  // Sort so highest score is checked first
  tiers.sort((a, b) => b.score - a.score)
  for (const tier of tiers) {
    if (higherIsBetter ? value >= tier.threshold : value <= tier.threshold) {
      return tier.score
    }
  }
  return 1 // floor
}

// ── Financial dimensions config for the thresholds panel ──────────────────
const financialDimensions = computed(() => [
  {
    key: 'revenue_growth',
    label: 'Revenue Growth',
    unit: '%',
    higherIsBetter: true,
    currentValue: props.salesData?.revenue_growth !== null && props.salesData?.revenue_growth !== undefined
      ? props.salesData.revenue_growth + '%'
      : (props.financials?.has_data ? 'from FS' : 'no data'),
  },
  {
    key: 'profitability',
    label: 'Profitability (Net Margin)',
    unit: '%',
    higherIsBetter: true,
    currentValue: props.financials?.has_data ? (props.financials.net_margin ?? 0) + '%' : 'no data',
  },
  {
    key: 'financial_health',
    label: 'Financial Health (Debt / Equity)',
    unit: 'x ratio',
    higherIsBetter: false,
    currentValue: props.financials?.has_data ? (props.financials.debt_to_equity ?? 0) + 'x' : 'no data',
  },
  {
    key: 'sales_momentum',
    label: 'Sales Momentum (YoY Growth)',
    unit: '%',
    higherIsBetter: true,
    currentValue: props.salesData?.revenue_growth !== null && props.salesData?.revenue_growth !== undefined
      ? props.salesData.revenue_growth + '%'
      : 'no data',
  },
  {
    key: 'kpi_performance',
    label: 'KPI Performance (% On Track)',
    unit: '%',
    higherIsBetter: true,
    currentValue: props.kpiData?.has_data ? (props.kpiData.health_score ?? 0) + '%' : 'no data',
  },
])

// ── Restore saved state on load ────────────────────────────────────────────
if (props.savedEvaluation) {
  if (props.savedEvaluation.scores && Object.keys(props.savedEvaluation.scores).length) {
    Object.assign(scores.value, props.savedEvaluation.scores)
  }
  if (props.savedEvaluation.verdict) {
    verdict.value = props.savedEvaluation.verdict
  }
  if (props.savedEvaluation.thresholds) {
    Object.assign(thresholds.value, props.savedEvaluation.thresholds)
  }
  if (props.savedEvaluation.notes) {
    try {
      const parsed = JSON.parse(props.savedEvaluation.notes)
      if (parsed && typeof parsed === 'object') {
        Object.assign(notes.value, parsed)
      }
    } catch (e) {
      notes.value.thesis = props.savedEvaluation.notes
    }
  }
}

// ── Dimensions config ──────────────────────────────────────────────────────
const allDimensions = [
  { key: 'revenue_growth',   label: 'Revenue Growth',    weight: 15, tab: 'financial',     desc: 'YoY revenue growth from Sales Analysis. Falls back to Financial Statements trend.' },
  { key: 'profitability',    label: 'Profitability',      weight: 15, tab: 'financial',     desc: 'Net profit margin % from your latest Financial Statement.' },
  { key: 'financial_health', label: 'Financial Health',   weight: 10, tab: 'financial',     desc: 'Debt-to-Equity ratio from your latest Balance Sheet.' },
  { key: 'sales_momentum',   label: 'Sales Momentum',     weight: 10, tab: 'financial',     desc: 'YoY revenue growth rate. Uses your custom threshold for scoring.' },
  { key: 'kpi_performance',  label: 'KPI Performance',    weight: 10, tab: 'financial',     desc: '% of KPIs on track from your KPI module.' },
  { key: 'team_quality',     label: 'Management Team',    weight: 10, tab: 'non_financial', desc: 'Manual score. Rate the quality and track record of the management team.' },
  { key: 'market_size',      label: 'Market Opportunity', weight: 10, tab: 'non_financial', desc: 'Manual score. Rate the total addressable market size and growth rate.' },
  { key: 'competitive_moat', label: 'Competitive Moat',   weight: 10, tab: 'non_financial', desc: 'Manual score. Rate defensibility: IP, network effects, switching costs, brand.' },
  { key: 'esg_governance',   label: 'ESG & Governance',   weight: 5,  tab: 'non_financial', desc: 'Manual score. Rate governance quality, compliance and ESG practices.' },
  { key: 'exit_potential',   label: 'Exit Potential',     weight: 5,  tab: 'non_financial', desc: 'Manual score. Rate clarity and attractiveness of exit routes.' },
]

const visibleDimensions = computed(() => allDimensions.filter(d => d.tab === scoreTab.value))

const scoreDimensions = computed(() =>
  allDimensions.map(d => ({ key: d.key, label: d.label.split(' ')[0], score: scores.value[d.key] }))
)

const overallScore = computed(() => {
  let total = 0
  allDimensions.forEach(d => { total += (scores.value[d.key] || 5) * d.weight })
  return total / 10
})

// ── Auto-score using user-defined thresholds ───────────────────────────────
function autoScoreFromData() {
  const f = props.financials
  const s = props.salesData
  const k = props.kpiData

  // Revenue growth — prefer sales data, fall back to FS trend
  if (s.has_data && s.revenue_growth !== null) {
    scores.value.revenue_growth = applyThreshold('revenue_growth', s.revenue_growth, true)
  } else if (f.has_data && f.trend?.length >= 2) {
    const t    = f.trend
    const last = t[t.length - 1].revenue
    const prev = t[t.length - 2].revenue
    const g    = prev > 0 ? ((last - prev) / prev) * 100 : 0
    scores.value.revenue_growth = applyThreshold('revenue_growth', g, true)
  }

  // Profitability — net margin from FS
  if (f.has_data) {
    scores.value.profitability = applyThreshold('profitability', f.net_margin || 0, true)
  }

  // Financial health — debt/equity (lower = better)
  if (f.has_data) {
    scores.value.financial_health = applyThreshold('financial_health', f.debt_to_equity || 0, false)
  }

  // Sales momentum — same YoY growth
  if (s.has_data && s.revenue_growth !== null) {
    scores.value.sales_momentum = applyThreshold('sales_momentum', s.revenue_growth, true)
  }

  // KPI performance — % on track
  if (k.has_data) {
    scores.value.kpi_performance = applyThreshold('kpi_performance', k.health_score || 0, true)
  }
}

// ── Verdict ────────────────────────────────────────────────────────────────
const verdictOptions = [
  { value: 'strong_buy',  label: 'Strong Buy',   icon: '🚀', desc: 'High conviction — move fast', activeClass: 'border-mp-success bg-mp-success/30' },
  { value: 'buy',         label: 'Buy',          icon: '✅', desc: 'Good opportunity with manageable risk', activeClass: 'border-mp-success bg-mp-success/30' },
  { value: 'hold',        label: 'Watch & Wait', icon: '👀', desc: 'Interesting but needs more data', activeClass: 'border-mp-warning bg-mp-warning/30' },
  { value: 'pass',        label: 'Pass',         icon: '⏸️', desc: 'Not the right fit at this time', activeClass: 'border-mp-warning bg-mp-warning/30' },
  { value: 'strong_pass', label: 'Hard Pass',    icon: '❌', desc: 'Fundamental issues — decline', activeClass: 'border-mp-danger bg-mp-danger/30' },
]

function verdictStyle(v) {
  const m = {
    strong_buy:  { badge: 'bg-mp-success/60 text-mp-success border-mp-success', label: '🚀 Strong Buy' },
    buy:         { badge: 'bg-mp-success/60 text-mp-success border-mp-success', label: '✅ Buy' },
    hold:        { badge: 'bg-mp-warning/60 text-mp-warning border-mp-warning', label: '👀 Watch & Wait' },
    pass:        { badge: 'bg-mp-warning/60 text-mp-warning border-mp-warning', label: '⏸️ Pass' },
    strong_pass: { badge: 'bg-mp-danger/60 text-mp-danger border-mp-danger', label: '❌ Hard Pass' },
  }
  return m[v] || { badge: '', label: '' }
}

// ── Charts helpers ─────────────────────────────────────────────────────────
const f = props.financials

const plRows = computed(() => {
  if (!f.has_data) return []
  const rev = f.revenue || 1
  return [
    { label: 'Revenue',     value: f.revenue,    margin: null,            pct: 100,                          color: 'bg-mp-teal' },
    { label: 'Gross Profit',value: f.gross_profit,margin: f.gross_margin, pct: Math.abs(f.gross_margin)||0,  color: f.gross_margin >= 0 ? 'bg-mp-success' : 'bg-mp-danger' },
    { label: 'EBITDA',      value: f.ebitda,      margin: f.ebitda_margin, pct: Math.abs(f.ebitda_margin)||0, color: f.ebitda_margin >= 0 ? 'bg-mp-teal' : 'bg-mp-danger' },
    { label: 'Net Profit',  value: f.net_profit,  margin: f.net_margin,    pct: Math.abs(f.net_margin)||0,    color: f.net_margin >= 0 ? 'bg-mp-gold' : 'bg-mp-danger' },
  ]
})

const ratioCards = computed(() => {
  if (!f.has_data) return []
  return [
    { label: 'Debt/Equity', value: f.debt_to_equity, unit: 'x', color: (f.debt_to_equity||0) <= 1 ? 'text-mp-success' : 'text-mp-danger' },
    { label: 'ROE', value: f.roe, unit: '%', color: (f.roe||0) >= 15 ? 'text-mp-success' : (f.roe||0) >= 5 ? 'text-mp-warning' : 'text-mp-danger' },
    { label: 'ROA', value: f.roa, unit: '%', color: (f.roa||0) >= 10 ? 'text-mp-success' : (f.roa||0) >= 5 ? 'text-mp-warning' : 'text-mp-danger' },
  ]
})

// Trend chart
const trendWidth = 400
const trendBarW  = 20
function trendBarX(i) {
  const n = props.financials.trend?.length || 1
  const step = (trendWidth - 60) / n
  return 45 + i * step
}
function trendMaxVal() {
  return Math.max(...(props.financials.trend || []).map(t => t.revenue), 1)
}
function trendBarY(v) {
  return 20 + (1 - Math.max(0, v) / trendMaxVal()) * 130
}
function trendBarH(v) {
  return Math.max(2, (Math.max(0, v) / trendMaxVal()) * 130)
}

// Sparkline
function sparklinePoints(monthly) {
  if (!monthly?.length) return ''
  const vals = monthly.map(m => m.rev)
  const max = Math.max(...vals, 1)
  const w = 200 / (vals.length - 1 || 1)
  return vals.map((v, i) => `${i * w},${48 - (v / max) * 44}`).join(' ')
}

// ── Score styling ──────────────────────────────────────────────────────────
function gaugeColor(score) {
  if (score >= 80) return '#10b981'
  if (score >= 60) return '#f59e0b'
  if (score >= 40) return '#f97316'
  return '#ef4444'
}

function gaugePath(score) {
  const pct = Math.min(100, Math.max(0, score)) / 100
  const angle = pct * Math.PI
  const x = 90 - 80 * Math.cos(angle)
  const y = 90 - 80 * Math.sin(angle)
  return `M 10 90 A 80 80 0 ${pct > 0.5 ? 1 : 0} 1 ${x} ${y}`
}

function scoreChipClass(s) {
  if (s >= 8) return 'bg-mp-success/50 border-mp-success text-mp-success'
  if (s >= 6) return 'bg-mp-teal-subtle/50 border-mp-teal text-white'
  if (s >= 4) return 'bg-mp-warning/50 border-mp-warning text-mp-warning'
  return 'bg-mp-danger/50 border-mp-danger text-mp-danger'
}
function scoreBarClass(s) {
  if (s >= 8) return 'bg-mp-success'
  if (s >= 6) return 'bg-mp-teal'
  if (s >= 4) return 'bg-mp-warning'
  return 'bg-mp-danger'
}

// ── Save ───────────────────────────────────────────────────────────────────
function saveEvaluation() {
  saving.value = true
  saved.value  = false
  saveError.value = ''

  router.post(
    `/investor-decision/${props.company.id}/save`,
    {
      scores:     scores.value,
      notes:      JSON.stringify(notes.value),
      verdict:    verdict.value,
      thresholds: thresholds.value,
    },
    {
      preserveScroll: true,
      preserveState:  true,
      onSuccess: () => {
        saved.value  = true
        saving.value = false
        setTimeout(() => { saved.value = false }, 3000)
      },
      onError: (errors) => {
        saving.value = false
        saveError.value = Object.values(errors)[0] || 'Save failed. Please try again.'
        setTimeout(() => { saveError.value = '' }, 5000)
      },
      onFinish: () => {
        saving.value = false
      },
    }
  )
}

function goToProspect() {
  if (switchTo.value) {
    router.visit(`/investor-decision/${switchTo.value}/evaluate`)
  }
}

function fmtM(v) {
  if (v === null || v === undefined) return '—'
  if (Math.abs(v) >= 1e9) return (v / 1e9).toFixed(1) + 'B'
  if (Math.abs(v) >= 1e6) return (v / 1e6).toFixed(1) + 'M'
  if (Math.abs(v) >= 1e3) return (v / 1e3).toFixed(0) + 'K'
  return Number(v).toLocaleString()
}
</script>