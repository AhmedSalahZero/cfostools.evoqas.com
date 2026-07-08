<template>
  <Head :title="`Sales Projection — ${study.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ── HEADER ── -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
          <Link
            :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/edit`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            ← Back to Setup
          </Link>

          <!-- Wizard bar -->
          <div class="flex items-center gap-0 mb-5 overflow-x-auto pb-1">
            <div v-for="(step, i) in wizardSteps" :key="i" class="flex items-center flex-shrink-0">
              <div :class="[
                'flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium',
                i === 1 ? 'bg-mp-gold-dark text-white' :
                i  <  1 ? 'bg-mp-card-hover text-white' : 'text-white'
              ]">
                <span :class="[
                  'w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0',
                  i < 1  ? 'bg-mp-success text-white' :
                  i === 1 ? 'bg-white/20 text-white' : 'bg-mp-card-hover text-white'
                ]">
                  <svg v-if="i < 1" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
              <h1 class="text-2xl font-bold text-white">Sales Projection</h1>
              <p class="text-white text-sm mt-0.5">
                {{ company.name }} · {{ study.name }} · Step 2 of {{ wizardSteps.length }}
              </p>
            </div>
            <div class="flex items-center gap-3">
              <!-- Saved checkmark -->
              <transition enter-active-class="transition-all duration-300" enter-from-class="opacity-0 scale-75" enter-to-class="opacity-100 scale-100" leave-active-class="transition-all duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="savedOk" class="flex items-center gap-1.5 text-mp-success text-sm font-medium">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                  </svg>
                  Saved!
                </div>
              </transition>
              <StudyWriteup
                :company-id="company.id"
                :study-id="study.id"
                :study-name="study.name"
                step-key="sales"
                step-label="Sales Projection"
                step-icon="📈"
                accent-color="#7c3aed"
                :saved-text="props.writeupText"
                :summary-columns="writeupSummaryColumns"
                :summary-rows="writeupSummaryRows"
                :summary-totals="writeupSummaryTotals"
                :category-breakdown="writeupCategoryBreakdown"
              />
              <button type="button" @click="saveForm('save')" :disabled="saving"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Save & Exit
              </button>
              <button type="button" @click="saveForm('next')" :disabled="saving"
                class="flex items-center gap-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors disabled:opacity-50">
                <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                {{ saving ? 'Saving...' : 'Save & Next →' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ── MAIN CONTENT ── -->
      <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- Flash -->
        <div v-if="flashMsg"
          class="mb-5 bg-mp-success/60 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          {{ flashMsg }}
        </div>

        <!-- No products guard -->
        <div v-if="!salesData.length"
          class="bg-mp-card border border-mp-border rounded-xl p-12 text-center">
          <p class="text-white">No products found.
            <Link :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/edit`"
              class="text-white hover:underline">Go back to Step 1</Link> and add products first.
          </p>
        </div>

        <div v-else class="space-y-4">

          <!-- ── PRODUCT TABS ── -->
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">

            <!-- Tab headers -->
            <div class="flex overflow-x-auto border-b border-mp-border">
              <button
                v-for="(prod, i) in salesData" :key="i"
                type="button"
                @click="activeProduct = i"
                :class="[
                  'flex items-center gap-2 px-5 py-3.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors flex-shrink-0',
                  activeProduct === i
                    ? 'border-mp-gold text-white bg-mp-gold/20'
                    : 'border-transparent text-white hover:text-white hover:bg-mp-card-hover/40'
                ]"
              >
                <span :class="['w-2 h-2 rounded-full flex-shrink-0', natureDot(prod.nature)]"></span>
                {{ prod.name || `Product ${i + 1}` }}
                <span v-if="isProductComplete(prod)"
                  class="w-4 h-4 bg-mp-success rounded-full flex items-center justify-center ml-1 flex-shrink-0">
                  <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                  </svg>
                </span>
              </button>
            </div>

            <!-- ── PER-PRODUCT PANEL ── -->
            <div v-for="(prod, pi) in salesData" :key="pi" v-show="activeProduct === pi" class="p-6 space-y-8">

              <!-- Nature badge + study period info -->
              <div class="flex items-center gap-3 flex-wrap">
                <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full', natureBadge(prod.nature)]">
                  {{ natureIcon(prod.nature) }} {{ prod.nature }}
                </span>
                <span class="text-xs text-white">
                  {{ study.duration_years }}-year projection ·
                  {{ formatYM(study.study_start_date) }} → {{ formatYM(study.study_end_date) }}
                </span>
              </div>

              <!-- ══════════════════════════════
                   A — PRICING & VOLUME
              ═══════════════════════════════ -->
              <section>
                <div class="mb-5">
                  <p class="text-m font-semibold text-white uppercase tracking-widest">A — Pricing & Volume</p>
                  <p class="text-x text-white mt-1">
                    Years 1 & 2: monthly breakdown. Year 3 onwards: annual summary with growth rates.
                  </p>
                </div>

                <!-- ════════════════════════════════════
                     YEAR 1 — Monthly HORIZONTAL table
                ═════════════════════════════════════ -->
                <div class="mb-6">
                  <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-lg bg-mp-gold-dark flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-bold">Y1</span>
                      </div>
                      <div>
                        <p class="text-sm font-bold text-white">Year 1 — {{ yearLabel(0) }}</p>
                        <p class="text-s text-white">Monthly price & volume — hover a cell to copy value across →</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="text-s text-white">Annual Revenue:</span>
                      <span class="bg-mp-success/60 border border-mp-teal text-mp-success text-base font-bold font-mono px-4 py-1.5 rounded-lg">
                        {{ formatNum(y1Total(prod)) }} <span class="text-mp-success text-xs font-normal ml-0.5">{{ study.study_currency }}</span>
                      </span>
                    </div>
                  </div>

                  <div class="overflow-x-auto rounded-xl border border-mp-border">
                    <table class="w-full" style="min-width: 900px">
                      <thead>
                        <tr class="bg-mp-card-hover/80 border-b border-mp-border">
                          <th class="text-left text-xs font-semibold text-white px-4 py-3 w-32 sticky left-0 bg-mp-card-hover/80 z-10">Row</th>
                          <th v-for="(mn, mi) in months" :key="mi"
                            class="text-center text-xs font-semibold text-white px-2 py-3 min-w-[80px]">
                            {{ mn }}
                          </th>
                          <th class="text-center text-xs font-semibold text-mp-success px-3 py-3 w-28">Total</th>
                        </tr>
                      </thead>
                      <tbody>

                        <!-- Price row -->
                        <tr class="border-b border-mp-border group">
                          <td class="px-4 py-2 sticky left-0 bg-mp-card z-10">
                            <span class="text-xs font-semibold text-white">Price</span>
                            <p class="text-xs text-white">{{ study.study_currency }}</p>
                          </td>
                          <td v-for="(m, mi) in prod.year1_months" :key="mi" class="px-1.5 py-2">
                            <input type="number" step="any" min="0"
                              v-model.number="m.price"
                              class="w-full bg-mp-card-hover border border-mp-border hover:border-mp-teal focus:border-mp-teal rounded-lg px-2 py-2.5 text-white text-sm font-mono focus:outline-none focus:ring-1 focus:ring-mp-gold transition-colors text-right" />
                          </td>
                          <td class="px-3 py-2 text-center">
                            <span class="text-sm text-white font-mono">Avg {{ formatNum(y1AvgPrice(prod)) }}</span>
                          </td>
                        </tr>

                        <!-- Fill-price dots row -->
                        <tr class="border-b border-mp-border/40">
                          <td class="px-4 py-0.5 sticky left-0 bg-mp-card z-10"></td>
                          <td v-for="(m, mi) in prod.year1_months" :key="mi" class="px-1.5 py-0.5">
                            <button v-if="mi < 11" type="button"
                              @click="fillPriceRight(prod.year1_months, mi)"
                              title="Copy this price to all months to the right"
                              class="w-full flex items-center justify-center gap-0.5 text-xs text-white hover:text-black hover:bg-mp-teal rounded py-0.5 transition-colors">
                              <span class="font-bold tracking-widest leading-none">···</span>
                              <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                              </svg>
                            </button>
                          </td>
                          <td></td>
                        </tr>

                        <!-- Volume row -->
                        <tr class="border-b border-mp-border group">
                          <td class="px-4 py-2 sticky left-0 bg-mp-card z-10">
                            <span class="text-xs font-semibold text-white">Volume</span>
                            <p class="text-xs text-white">{{ prod.measurement_unit || 'units' }}</p>
                          </td>
                          <td v-for="(m, mi) in prod.year1_months" :key="mi" class="px-1.5 py-2">
                            <input type="number" step="1" min="0"
                              v-model.number="m.volume"
                              class="w-full bg-mp-card-hover border border-mp-border hover:border-mp-teal focus:border-mp-teal rounded-lg px-2 py-2.5 text-white text-sm font-mono focus:outline-none focus:ring-1 focus:ring-mp-gold transition-colors text-right" />
                          </td>
                          <td class="px-3 py-2 text-center">
                            <span class="text-sm text-white font-mono">
                              {{ formatNum(prod.year1_months.reduce((s,m) => s+(m.volume||0), 0)) }}
                            </span>
                          </td>
                        </tr>

                        <!-- Fill-volume dots row -->
                        <tr class="border-b border-mp-border/40">
                          <td class="px-4 py-0.5 sticky left-0 bg-mp-card z-10"></td>
                          <td v-for="(m, mi) in prod.year1_months" :key="mi" class="px-1.5 py-0.5">
                            <button v-if="mi < 11" type="button"
                              @click="fillVolumeRight(prod.year1_months, mi)"
                              title="Copy this volume to all months to the right"
                              class="w-full flex items-center justify-center gap-0.5 text-xs text-white hover:text-black hover:bg-mp-teal rounded py-0.5 transition-colors">
                              <span class="font-bold tracking-widest leading-none">···</span>
                              <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                              </svg>
                            </button>
                          </td>
                          <td></td>
                        </tr>

                        <!-- Revenue row (read-only) -->
                        <tr class="bg-mp-success/10">
                          <td class="px-4 py-2 sticky left-0 bbg-mp-teal z-10">
                            <span class="text-xs font-bold text-balck">Revenue</span>
                            <p class="text-xs text-white">auto</p>
                          </td>
                          <td v-for="(m, mi) in prod.year1_months" :key="mi" class="px-1.5 py-2">
                            <div :class="[
                              'text-center text-sm font-bold font-mono rounded-lg px-2 py-2.5',
                              monthRevenue(m) > 0 ? 'text-black bg-mp-teal' : 'text-white bg-mp-card-hover/30'
                            ]">
                              {{ formatNum(monthRevenue(m)) }}
                            </div>
                          </td>
                          <td class="px-3 py-2 text-center">
                            <span class="text-base font-bold text-mp-success font-mono">{{ formatNum(y1Total(prod)) }}</span>
                          </td>
                        </tr>

                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- ════════════════════════════════════
                     YEAR 2 — Monthly HORIZONTAL table
                ═════════════════════════════════════ -->
                <div v-if="study.duration_years >= 2 && prod.year2_months" class="mb-6">
                  <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-lg bg-mp-teal-dark flex items-center justify-center flex-shrink-0">
                        <span class="text-white text-xs font-bold">Y2</span>
                      </div>
                      <div>
                        <p class="text-sm font-bold text-white">Year 2 — {{ yearLabel(1) }}</p>
                        <p class="text-xs text-white">Monthly price & volume — hover a cell to copy value across →</p>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="text-xs text-white">Annual Revenue:</span>
                      <span class="bg-mp-success/60 border border-mp-success/40 text-mp-success text-base font-bold font-mono px-4 py-1.5 rounded-lg">
                        {{ formatNum(yearRevenue(prod.year2_months)) }} <span class="text-mp-success text-xs font-normal ml-0.5">{{ study.study_currency }}</span>
                      </span>
                    </div>
                  </div>

                  <div class="overflow-x-auto rounded-xl border border-mp-border">
                    <table class="w-full" style="min-width: 900px">
                      <thead>
                        <tr class="bg-mp-card-hover/80 border-b border-mp-border">
                          <th class="text-left text-xs font-semibold text-white px-4 py-3 w-32 sticky left-0 bg-mp-card-hover/80 z-10">Row</th>
                          <th v-for="(mn, mi) in months" :key="mi"
                            class="text-center text-xs font-semibold text-white px-2 py-3 min-w-[80px]">
                            {{ mn }}
                          </th>
                          <th class="text-center text-xs font-semibold text-mp-success px-3 py-3 w-28">Total</th>
                        </tr>
                      </thead>
                      <tbody>

                        <!-- Price row -->
                        <tr class="border-b border-mp-border group">
                          <td class="px-4 py-2 sticky left-0 bg-mp-card z-10">
                            <span class="text-xs font-semibold text-white">Price</span>
                            <p class="text-xs text-white">{{ study.study_currency }}</p>
                          </td>
                          <td v-for="(m, mi) in prod.year2_months" :key="mi" class="px-1.5 py-2">
                            <input type="number" step="any" min="0"
                              v-model.number="m.price"
                              class="w-full bg-mp-card-hover border border-mp-border hover:border-mp-teal/60 focus:border-mp-teal rounded-lg px-2 py-2.5 text-white text-sm font-mono focus:outline-none focus:ring-1 focus:ring-mp-teal transition-colors text-right" />
                          </td>
                          <td class="px-3 py-2 text-center">
                            <span class="text-sm text-white font-mono">
                              Avg {{ formatNum(prod.year2_months.reduce((s,m,_,a) => { const v=a.reduce((t,x)=>t+(x.volume||0),0); return s+(v?(m.price||0)*(m.volume||0)/v:0) }, 0)) }}
                            </span>
                          </td>
                        </tr>

                        <!-- Fill-price dots row -->
                        <tr class="border-b border-mp-border/40">
                          <td class="px-4 py-0.5 sticky left-0 bg-mp-card z-10"></td>
                          <td v-for="(m, mi) in prod.year2_months" :key="mi" class="px-1.5 py-0.5">
                            <button v-if="mi < 11" type="button"
                              @click="fillPriceRight(prod.year2_months, mi)"
                              title="Copy this price to all months to the right"
                              class="w-full flex items-center justify-center gap-0.5 text-xs text-white hover:text-white hover:bg-mp-teal-subtle/30 rounded py-0.5 transition-colors">
                              <span class="font-bold tracking-widest leading-none">···</span>
                              <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                              </svg>
                            </button>
                          </td>
                          <td></td>
                        </tr>

                        <!-- Volume row -->
                        <tr class="border-b border-mp-border group">
                          <td class="px-4 py-2 sticky left-0 bg-mp-card z-10">
                            <span class="text-xs font-semibold text-white">Volume</span>
                            <p class="text-xs text-white">{{ prod.measurement_unit || 'units' }}</p>
                          </td>
                          <td v-for="(m, mi) in prod.year2_months" :key="mi" class="px-1.5 py-2">
                            <input type="number" step="1" min="0"
                              v-model.number="m.volume"
                              class="w-full bg-mp-card-hover border border-mp-border hover:border-mp-teal/60 focus:border-mp-teal rounded-lg px-2 py-2.5 text-white text-sm font-mono focus:outline-none focus:ring-1 focus:ring-mp-teal transition-colors text-right" />
                          </td>
                          <td class="px-3 py-2 text-center">
                            <span class="text-sm text-white font-mono">
                              {{ formatNum(prod.year2_months.reduce((s,m) => s+(m.volume||0), 0)) }}
                            </span>
                          </td>
                        </tr>

                        <!-- Fill-volume dots row -->
                        <tr class="border-b border-mp-border/40">
                          <td class="px-4 py-0.5 sticky left-0 bg-mp-card z-10"></td>
                          <td v-for="(m, mi) in prod.year2_months" :key="mi" class="px-1.5 py-0.5">
                            <button v-if="mi < 11" type="button"
                              @click="fillVolumeRight(prod.year2_months, mi)"
                              title="Copy this volume to all months to the right"
                              class="w-full flex items-center justify-center gap-0.5 text-xs text-white hover:text-white hover:bg-mp-teal-subtle/30 rounded py-0.5 transition-colors">
                              <span class="font-bold tracking-widest leading-none">···</span>
                              <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                              </svg>
                            </button>
                          </td>
                          <td></td>
                        </tr>

                        <!-- Revenue row -->
                        <tr class="bg-mp-success/10">
                          <td class="px-4 py-2 sticky left-0 bg-mp-success/20 z-10">
                            <span class="text-xs font-bold text-white">Revenue</span>
                            <p class="text-xs text-white">auto</p>
                          </td>
                          <td v-for="(m, mi) in prod.year2_months" :key="mi" class="px-1.5 py-2">
                            <div :class="[
                              'text-center text-sm font-bold font-mono rounded-lg px-2 py-2.5',
                              monthRevenue(m) > 0 ? 'text-black bg-mp-teal' : 'text-white bg-mp-card-hover/30'
                            ]">
                              {{ formatNum(monthRevenue(m)) }}
                            </div>
                          </td>
                          <td class="px-3 py-2 text-center">
                            <span class="text-base font-bold text-mp-success font-mono">{{ formatNum(yearRevenue(prod.year2_months)) }}</span>
                          </td>
                        </tr>

                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- ════════════════════════════════════
                     YEARS 3+ — Annual compact table
                     (only shown if duration >= 3)
                ═════════════════════════════════════ -->
                <div v-if="study.duration_years >= 3 && prod.annual_years.length">
                  <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-lg bg-mp-teal-dark flex items-center justify-center flex-shrink-0">
                      <span class="text-white text-xs font-bold">Y3+</span>
                    </div>
                    <div>
                      <p class="text-sm font-bold text-white">Years 3–{{ study.duration_years }} — Annual Summary</p>
                      <p class="text-xs text-white">Enter growth % to auto-fill price/volume, or type directly</p>
                    </div>
                  </div>

                  <div class="rounded-xl border border-mp-border overflow-hidden">
                    <table class="w-full">
                      <thead>
                        <tr class="bg-mp-card-hover/80 border-b border-mp-border">
                          <th class="text-left text-m font-semibold text-white px-4 py-3 w-32">Metric</th>
                          <th v-for="yr in prod.annual_years" :key="yr.year"
                            class="text-center text-xs font-semibold text-white px-4 py-3 min-w-[160px]">
                            <span class="flex items-center justify-center gap-1.5">
                              <span class="w-5 h-5 rounded-full bg-mp-teal-subtle text-white flex items-center justify-center text-xs font-bold">{{ yr.year }}</span>
                              {{ yearLabel(yr.year - 1) }}
                            </span>
                          </th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-800/60">

                        <!-- Price -->
                        <tr class="hover:bg-mp-card-hover/20 transition-colors">
                          <td class="px-4 py-3 text-sm font-medium text-white whitespace-nowrap">
                            Price <span class="text-white text-xs font-normal">({{ study.study_currency }})</span>
                          </td>
                          <td v-for="(yr, yi) in prod.annual_years" :key="yr.year" class="px-4 py-3">
                            <input type="number" step="any" min="0"
                              v-model.number="yr.price"
                              class="w-full bg-mp-card-hover border border-mp-border hover:border-mp-teal/60 focus:border-mp-teal rounded-lg px-3 py-3 text-white text-base font-mono focus:outline-none focus:ring-1 focus:ring-mp-teal transition-colors text-right" />
                          </td>
                        </tr>

                        <!-- Price Growth % -->
                        <tr class="bg-mp-teal-subtle/10 hover:bg-mp-teal-subtle/20 transition-colors">
                          <td class="px-4 py-3">
                            <span class="text-sm font-medium text-white whitespace-nowrap">Price Growth %</span>
                            <p class="text-xs text-white">auto-fills price ↑</p>
                          </td>
                          <td v-for="(yr, yi) in prod.annual_years" :key="yr.year" class="px-4 py-3">
                            <div class="relative">
                              <input type="number" step="any"
                                v-model.number="yr.price_growth_pct"
                                @change="applyPriceGrowthAnnual(prod, yi)"
                                placeholder="0"
                                class="w-full bg-mp-card-hover border border-mp-teal/50 hover:border-mp-teal/70 focus:border-mp-teal rounded-lg px-3 py-3 text-white text-base font-mono focus:outline-none focus:ring-1 focus:ring-mp-teal transition-colors text-right pr-6" />
                              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-white text-sm pointer-events-none">%</span>
                            </div>
                          </td>
                        </tr>

                        <!-- Spacer -->
                        <tr class="border-t-2 border-mp-border/80 bg-transparent">
                          <td colspan="99" class="py-0 h-0"></td>
                        </tr>

                        <!-- Volume -->
                        <tr class="hover:bg-mp-card-hover/20 transition-colors">
                          <td class="px-4 py-3 text-sm font-medium text-white whitespace-nowrap">
                            Volume <span class="text-white text-xs font-normal">({{ prod.measurement_unit || 'units' }})</span>
                          </td>
                          <td v-for="(yr, yi) in prod.annual_years" :key="yr.year" class="px-4 py-3">
                            <input type="number" step="1" min="0"
                              v-model.number="yr.volume"
                              class="w-full bg-mp-card-hover border border-mp-border hover:border-mp-teal/60 focus:border-mp-teal rounded-lg px-3 py-3 text-white text-base font-mono focus:outline-none focus:ring-1 focus:ring-mp-teal transition-colors text-right" />
                          </td>
                        </tr>

                        <!-- Volume Growth % -->
                        <tr class="bg-mp-teal-subtle/10 hover:bg-mp-teal-subtle/20 transition-colors">
                          <td class="px-4 py-3">
                            <span class="text-sm font-medium text-white whitespace-nowrap">Volume Growth %</span>
                            <p class="text-xs text-white">auto-fills volume ↑</p>
                          </td>
                          <td v-for="(yr, yi) in prod.annual_years" :key="yr.year" class="px-4 py-3">
                            <div class="relative">
                              <input type="number" step="any"
                                v-model.number="yr.volume_growth_pct"
                                @change="applyVolumeGrowthAnnual(prod, yi)"
                                placeholder="0"
                                class="w-full bg-mp-card-hover border border-mp-teal/50 hover:border-mp-teal/70 focus:border-mp-teal rounded-lg px-3 py-3 text-white text-base font-mono focus:outline-none focus:ring-1 focus:ring-mp-teal transition-colors text-right pr-6" />
                              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-white text-sm pointer-events-none">%</span>
                            </div>
                          </td>
                        </tr>

                        <!-- Spacer -->
                        <tr class="border-t-2 border-mp-border/80 bg-transparent">
                          <td colspan="99" class="py-0 h-0"></td>
                        </tr>

                        <!-- Capacity % -->
                        <tr class="hover:bg-mp-card-hover/20 transition-colors">
                          <td class="px-4 py-3 text-sm font-medium text-white whitespace-nowrap">
                            Capacity %
                          </td>
                          <td v-for="yr in prod.annual_years" :key="yr.year" class="px-4 py-3">
                            <div class="relative">
                              <input type="number" step="any" min="0" max="100"
                                v-model.number="yr.capacity_pct"
                                class="w-full bg-mp-card-hover border border-mp-border hover:border-mp-teal/60 focus:border-mp-teal rounded-lg px-3 py-3 text-white text-base font-mono focus:outline-none focus:ring-1 focus:ring-mp-teal transition-colors text-right pr-6" />
                              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-white text-sm pointer-events-none">%</span>
                            </div>
                          </td>
                        </tr>

                        <!-- Revenue (read-only) -->
                        <tr class="bg-mp-success/20 border-t-2 border-mp-success/40">
                          <td class="px-4 py-3">
                            <span class="text-sm font-bold text-mp-success">Revenue</span>
                            <p class="text-xs text-white">Price × Volume</p>
                          </td>
                          <td v-for="yr in prod.annual_years" :key="yr.year" class="px-4 py-3">
                            <div :class="[
                              'text-center text-base font-bold font-mono rounded-lg px-3 py-3 border',
                              annualRevenue(yr) > 0
                                ? 'text-mp-success bg-mp-success/30 border-mp-success/40'
                                : 'text-white bg-mp-card-hover/30 border-mp-border/40'
                            ]">
                              {{ formatNum(annualRevenue(yr)) }}
                            </div>
                          </td>
                        </tr>

                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- ════════════════════════════════════
                     REVENUE SUMMARY — all years
                ═════════════════════════════════════ -->
                <div class="mt-5 bg-mp-card-hover/40 border border-mp-border/50 rounded-xl overflow-hidden">
                  <div class="px-4 py-2.5 border-b border-mp-border/50 flex items-center gap-2">
                    <svg class="w-3.5 h-3.5 text-mp-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span class="text-m font-semibold text-mp-success uppercase tracking-widest">Revenue at a Glance</span>
                  </div>
                  <div class="flex overflow-x-auto divide-x divide-gray-700/50">
                    <!-- Y1 -->
                    <div class="flex-1 min-w-[110px] px-4 py-3 text-center flex-shrink-0">
                      <p class="text-m text-white font-semibold mb-1">Y1 {{ yearLabel(0) }}</p>
                      <p :class="['text-base font-bold font-mono', y1Total(prod) > 0 ? 'text-mp-success' : 'text-white']">
                        {{ formatNum(y1Total(prod)) }}
                      </p>
                    </div>
                    <!-- Y2 -->
                    <div v-if="study.duration_years >= 2 && prod.year2_months"
                      class="flex-1 min-w-[110px] px-4 py-3 text-center flex-shrink-0">
                      <p class="text-m text-white font-semibold mb-1">Y2 {{ yearLabel(1) }}</p>
                      <p :class="['text-base font-bold font-mono', yearRevenue(prod.year2_months) > 0 ? 'text-mp-success' : 'text-white']">
                        {{ formatNum(yearRevenue(prod.year2_months)) }}
                      </p>
                      <p v-if="y1Total(prod) > 0" class="text-xs mt-0.5"
                        :class="yearRevenue(prod.year2_months) >= y1Total(prod) ? 'text-white' : 'text-mp-danger'">
                        {{ y1Total(prod) > 0 ? ((yearRevenue(prod.year2_months) - y1Total(prod)) / y1Total(prod) * 100).toFixed(1) : 0 }}%
                      </p>
                    </div>
                    <!-- Y3+ annual -->
                    <div v-for="(yr, yi) in prod.annual_years" :key="yr.year"
                      class="flex-1 min-w-[110px] px-4 py-3 text-center flex-shrink-0">
                      <p class="text-xs text-white font-semibold mb-1">Y{{ yr.year }} {{ yearLabel(yr.year - 1) }}</p>
                      <p :class="['text-base font-bold font-mono', annualRevenue(yr) > 0 ? 'text-mp-success' : 'text-white']">
                        {{ formatNum(annualRevenue(yr)) }}
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Seasonality panel -->
                <div class="mt-5 bg-mp-card-hover/40 border border-mp-border/50 rounded-xl overflow-hidden">
                  <button type="button" @click="prod.showSeasonality = !prod.showSeasonality"
                    class="w-full px-5 py-3.5 flex items-center justify-between hover:bg-mp-card-hover/60 transition-colors">
                    <div class="flex items-center gap-3 flex-wrap">
                      <span class="text-m font-semibold text-white uppercase tracking-widest">Monthly Seasonality</span>
                      <span :class="[
                        'text-xs px-2 py-0.5 rounded-full',
                        seasonalityTotal(prod) === 100
                          ? 'bg-mp-success/60 text-mp-success'
                          : 'bg-mp-danger/60 text-mp-danger'
                      ]">
                        Total: {{ seasonalityTotal(prod).toFixed(2) }}%
                        {{ seasonalityTotal(prod) === 100 ? '✓' : '⚠ must be 100%' }}
                      </span>
                    </div>
                    <div class="flex items-center gap-3">
                      <button type="button" @click.stop="setFlatSeasonality(pi)"
                        class="text-xs text-white hover:text-white bg-mp-page hover:bg-mp-muted px-2.5 py-1 rounded-lg transition-colors">
                        Equal (8.33%)
                      </button>
                      <svg :class="['w-4 h-4 text-white transition-transform', prod.showSeasonality ? 'rotate-180' : '']"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                      </svg>
                    </div>
                  </button>

                  <div v-show="prod.showSeasonality" class="px-5 pb-5">
                    <div class="grid grid-cols-6 sm:grid-cols-12 gap-2 mb-4">
                      <div v-for="(m, mi) in months" :key="mi">
                        <label class="block text-xs text-white mb-1 text-center">{{ m }}</label>
                        <input type="number" step="0.01" min="0" max="100"
                          v-model.number="prod.pricing.seasonality[mi]"
                          class="w-full bg-mp-card border border-mp-border rounded-lg px-1 py-2 text-white text-xs focus:outline-none focus:ring-1 focus:ring-mp-gold text-center" />
                      </div>
                    </div>
                    <!-- Visual bar chart -->
                    <div class="flex items-end gap-1 h-14 bg-mp-card/40 rounded-lg px-2 py-2">
                      <div v-for="(pct, mi) in prod.pricing.seasonality" :key="mi"
                        class="flex-1 bg-mp-gold-dark/70 rounded-t transition-all duration-300 relative group"
                        :style="{ height: `${Math.max(4, (pct / Math.max(...prod.pricing.seasonality, 1)) * 100)}%` }">
                        <div class="absolute -top-6 left-1/2 -translate-x-1/2 bg-mp-card-hover text-white text-xs px-1.5 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                          {{ months[mi] }}: {{ pct }}%
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </section>

              <!-- ══════════════════════════════
                   B — LOCAL / EXPORT SPLIT
              ═══════════════════════════════ -->
              <section>
                <p class="text-m font-semibold text-white uppercase tracking-widest mb-4">B — Market Split</p>
                <div class="flex items-center gap-4 max-w-sm">
                  <div class="flex-1">
                    <label class="block text-xs text-white mb-1.5">Local %</label>
                    <input type="number" step="any" min="0" max="100"
                      v-model.number="prod.market_split.local_pct"
                      @input="prod.market_split.export_pct = Math.max(0, parseFloat((100 - prod.market_split.local_pct).toFixed(2)))"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-gold" />
                  </div>
                  <div class="text-white text-sm font-bold mt-5">+</div>
                  <div class="flex-1">
                    <label class="block text-xs text-white mb-1.5">Export %</label>
                    <input type="number" step="any" min="0" max="100"
                      v-model.number="prod.market_split.export_pct"
                      @input="prod.market_split.local_pct = Math.max(0, parseFloat((100 - prod.market_split.export_pct).toFixed(2)))"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-gold" />
                  </div>
                  <div class="mt-5 text-sm" :class="splitTotal(prod) === 100 ? 'text-mp-success' : 'text-mp-danger'">
                    = {{ splitTotal(prod) }}%
                  </div>
                </div>
                <p v-if="splitTotal(prod) !== 100" class="text-xs text-mp-danger mt-2">
                  ⚠ Local + Export must equal 100%
                </p>
              </section>

              <!-- ══════════════════════════════
                   C — LOCAL ALLOCATION
              ═══════════════════════════════ -->
              <section v-if="prod.market_split.local_pct > 0">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                  <div>
                    <p class="text-m font-semibold text-white uppercase tracking-widest">C — Local Sales Breakdown</p>
                    <p class="text-xs text-white mt-0.5">How do you want to break down local revenue?</p>
                  </div>
                </div>

                <!-- Dimension selector cards -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                  <button v-for="dim in dimensions" :key="dim.value" type="button"
                    @click="setDimension(pi, 'local', dim.value)"
                    :class="[
                      'flex flex-col items-center gap-1.5 p-4 rounded-xl border-2 text-center transition-all',
                      prod.local_allocation.dimension === dim.value
                        ? 'border-mp-gold bg-mp-gold/40 text-white'
                        : 'border-mp-border bg-mp-card-hover/40 text-white hover:border-mp-border'
                    ]"
                  >
                    <span class="text-2xl">{{ dim.icon }}</span>
                    <span class="text-xs font-semibold">{{ dim.label }}</span>
                  </button>
                </div>

                <!-- Allocation rows -->
                <div v-if="prod.local_allocation.dimension && prod.local_allocation.dimension !== 'none'">
                  <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                      <p class="text-xs text-white">Rows total:</p>
                      <span :class="[
                        'text-xs font-semibold px-2 py-0.5 rounded-full',
                        allocationTotal(prod.local_allocation.rows) === 100
                          ? 'bg-mp-success/60 text-mp-success'
                          : 'bg-mp-danger/60 text-mp-danger'
                      ]">
                        {{ allocationTotal(prod.local_allocation.rows).toFixed(1) }}%
                      </span>
                    </div>
                    <div class="flex items-center gap-2">
                      <button type="button"
                        @click="openImport(pi, 'local', prod.local_allocation.dimension)"
                        class="flex items-center gap-1.5 text-xs text-white bg-mp-teal-subtle/40 hover:bg-mp-teal-subtle/70 px-3 py-1.5 rounded-lg border border-mp-teal/40 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Import from Sales
                      </button>
                      <button type="button"
                        @click="equalSplit(prod.local_allocation.rows)"
                        class="text-xs text-white hover:text-white bg-mp-page hover:bg-mp-muted px-2.5 py-1.5 rounded-lg transition-colors">
                        Equal Split
                      </button>
                      <button type="button"
                        @click="prod.local_allocation.rows.push({ name: '', pct: 0, collection_policy: defaultPolicy() })"
                        class="text-xs text-mp-success bg-mp-success/30 hover:bg-mp-success/60 px-2.5 py-1.5 rounded-lg border border-mp-success/40 transition-colors">
                        + Add Row
                      </button>
                    </div>
                  </div>

                  <div class="space-y-2">
                    <div v-for="(row, ri) in prod.local_allocation.rows" :key="ri"
                      class="flex items-center gap-3 bg-mp-card-hover/60 rounded-lg px-4 py-3 flex-wrap">
                      <span class="text-xs text-white w-5 flex-shrink-0">{{ ri + 1 }}</span>
                      <input type="text" v-model="row.name"
                        :placeholder="dimPlaceholder(prod.local_allocation.dimension)"
                        class="flex-1 min-w-[140px] bg-mp-card border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold" />
                      <div class="flex items-center gap-1.5 flex-shrink-0">
                        <input type="number" step="any" min="0" max="100" v-model.number="row.pct"
                          class="w-20 bg-mp-card border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold text-center" />
                        <span class="text-xs text-white">%</span>
                      </div>
                      <!-- Collection Policy per row -->
                      <button type="button" @click.stop="openCollModal(pi, 'row', ri)"
                        class="flex items-center gap-1.5 text-xs px-3 py-2 rounded-lg border transition-colors flex-shrink-0"
                        :class="row.collection_policy?.preset && row.collection_policy.preset !== 'cash'
                          ? 'bg-mp-gold/40 border-mp-gold/60 text-white'
                          : 'bg-mp-page/60 border-mp-border/60 text-white hover:text-white hover:bg-mp-page'">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Collection: {{ policyLabel(row.collection_policy) }}
                      </button>
                      <button v-if="prod.local_allocation.rows.length > 1" type="button"
                        @click="prod.local_allocation.rows.splice(ri, 1)"
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-mp-danger/40 hover:bg-mp-danger/60 text-mp-danger transition-colors flex-shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
              </section>

              <!-- ══════════════════════════════
                   D — COLLECTION POLICY (Local) — when No Breakdown
              ═══════════════════════════════ -->
              <section v-if="prod.market_split.local_pct > 0 && prod.local_allocation.dimension === 'none'">
                <div class="flex items-center justify-between mb-3 flex-wrap gap-3">
                  <div>
                    <p class="text-m font-semibold text-white uppercase tracking-widest">D — Collection Policy (Local)</p>
                    <p class="text-xs text-white mt-0.5">How customers pay for local sales</p>
                  </div>
                </div>
                <div class="bg-mp-card-hover/50 border border-mp-border/50 rounded-xl p-5 flex items-center gap-4 flex-wrap">
                  <div class="flex items-center gap-3">
                    <span class="text-sm text-white">Current policy:</span>
                    <span :class="[
                      'text-sm font-semibold px-3 py-1 rounded-full border',
                      prod.collection_local.preset !== 'cash'
                        ? 'bg-mp-gold/40 border-mp-gold/60 text-white'
                        : 'bg-mp-page/60 border-mp-border/60 text-white'
                    ]">{{ policyLabel(prod.collection_local) }}</span>
                    <span v-if="prod.collection_local.tranches" class="text-xs text-white">
                      ({{ prod.collection_local.tranches.filter(t=>t.pct>0).map(t=>t.pct+'% in '+t.days+'d').join(', ') }})
                    </span>
                  </div>
                  <button type="button" @click.stop="openCollModal(pi, 'local')"
                    class="flex items-center gap-2 text-sm px-4 py-2 bg-mp-gold hover:bg-mp-gold-dark text-white rounded-lg transition-colors font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Set Collection Policy
                  </button>
                </div>
              </section>

              <!-- ══════════════════════════════
                   E — COLLECTION POLICY (Export)
              ═══════════════════════════════ -->
              <section v-if="prod.market_split.export_pct > 0">
                <div class="flex items-center justify-between mb-3 flex-wrap gap-3">
                  <div>
                    <p class="text-m font-semibold text-white uppercase tracking-widest">E — Collection Policy (Export)</p>
                    <p class="text-xs text-white mt-0.5">How customers pay for export sales</p>
                  </div>
                </div>
                <div class="bg-mp-card-hover/50 border border-mp-border/50 rounded-xl p-5 flex items-center gap-4 flex-wrap">
                  <div class="flex items-center gap-3">
                    <span class="text-sm text-white">Current policy:</span>
                    <span :class="[
                      'text-sm font-semibold px-3 py-1 rounded-full border',
                      prod.collection_export.preset !== 'cash'
                        ? 'bg-mp-gold/40 border-mp-gold/60 text-white'
                        : 'bg-mp-page/60 border-mp-border/60 text-white'
                    ]">{{ policyLabel(prod.collection_export) }}</span>
                    <span v-if="prod.collection_export.tranches" class="text-xs text-white">
                      ({{ prod.collection_export.tranches.filter(t=>t.pct>0).map(t=>t.pct+'% in '+t.days+'d').join(', ') }})
                    </span>
                  </div>
                  <button type="button" @click.stop="openCollModal(pi, 'export')"
                    class="flex items-center gap-2 text-sm px-4 py-2 bg-mp-gold hover:bg-mp-gold-dark text-white rounded-lg transition-colors font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Set Collection Policy
                  </button>
                </div>
              </section>

              <!-- ══════════════════════════════
                   F — INVENTORY (Manufacturing only)
              ═══════════════════════════════ -->
              <section v-if="prod.nature === 'manufacturing'">
                <div class="mb-4">
                  <p class="text-m font-semibold text-white uppercase tracking-widest">F — Inventory Inputs</p>
                  <p class="text-xs text-white mt-0.5">Beginning inventory and coverage days for production planning</p>
                </div>

                <div class="bg-mp-card-hover/50 border border-mp-border/50 rounded-xl p-5">
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 items-end">

                    <!-- Inventory Coverage Days -->
                    <div>
                      <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">
                        Inventory Coverage Days
                      </label>
                      <select v-model.number="prod.inventory_coverage_days"
                        class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold">
                        <option v-for="d in coverageDaysOptions" :key="d" :value="d">{{ d }} Days</option>
                      </select>
                    </div>

                    <!-- Beginning Inventory Quantity -->
                    <div>
                      <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">
                        Beginning Inventory Qty
                        <span class="text-white normal-case font-normal ml-1">({{ prod.measurement_unit || 'units' }})</span>
                      </label>
                      <input
                        type="number" min="0" step="1"
                        v-model.number="prod.beg_inv_qty"
                        placeholder="0"
                        class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"
                      />
                    </div>

                    <!-- Beginning Inventory Amount + Breakdown -->
                    <div>
                      <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">
                        Beginning Inventory Amount
                        <span class="text-white normal-case font-normal ml-1">({{ study.study_currency }})</span>
                      </label>
                      <div class="flex items-center gap-2">
                        <input
                          type="number" min="0" step="0.01"
                          v-model.number="prod.beg_inv_amount"
                          placeholder="0"
                          class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"
                        />
                        <!-- Breakdown button -->
                        <button
                          type="button"
                          @click="openBreakdownModal(pi)"
                          :title="'View cost breakdown'"
                          :class="[
                            'flex-shrink-0 flex items-center gap-1.5 px-3 py-2.5 rounded-lg border text-xs font-medium transition-colors',
                            prod.beg_inv_breakdown
                              ? 'bg-mp-teal-subtle/40 border-mp-teal/60 text-white hover:bg-mp-teal-subtle/60'
                              : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page'
                          ]">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                          </svg>
                          Breakdown
                        </button>
                      </div>
                      <!-- Mini summary pill -->
                      <div v-if="prod.beg_inv_amount > 0 && prod.beg_inv_breakdown"
                        class="mt-2 flex items-center gap-2 text-xs text-white">
                        <span class="text-white">RM {{ prod.beg_inv_breakdown.raw_material_pct }}%</span>
                        <span>·</span>
                        <span class="text-white">DL {{ prod.beg_inv_breakdown.direct_labor_pct }}%</span>
                        <span>·</span>
                        <span class="text-mp-warning">OH {{ prod.beg_inv_breakdown.overheads_pct }}%</span>
                      </div>
                    </div>

                  </div>
                </div>
              </section>

            </div><!-- end product panel -->
          </div><!-- end tabs card -->

          <!-- Bottom action bar -->
          <div class="flex items-center justify-between bg-mp-card border border-mp-border rounded-xl px-6 py-4">
            <Link :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/edit`"
              class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
              ← Step 1: Setup
            </Link>
            <div class="flex items-center gap-3">
              <button type="button" @click="saveForm('save')" :disabled="saving"
                class="bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50">
                Save & Exit
              </button>
              <button type="button" @click="saveForm('next')" :disabled="saving"
                class="bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors disabled:opacity-50">
                {{ saving ? 'Saving...' : 'Save & Next → COGS' }}
              </button>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════
         IMPORT MODAL (channels / sectors / customers)
    ═══════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="importModal.show"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        @click.self="importModal.show = false">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-lg bg-mp-card border border-mp-border rounded-2xl shadow-2xl overflow-hidden">

          <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between">
            <div>
              <h3 class="text-base font-bold text-white">Import from Sales Data</h3>
              <p class="text-xs text-white mt-0.5">
                {{ dimLabel(importModal.dimension) }} — last 12 months · {{ importModal.items.length }} records
              </p>
            </div>
            <button type="button" @click="importModal.show = false"
              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-white hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div v-if="importModal.loading" class="flex items-center justify-center py-14">
            <svg class="animate-spin w-7 h-7 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
          </div>

          <div v-else-if="!importModal.items.length"
            class="flex flex-col items-center justify-center py-12 px-6 text-center">
            <p class="text-white font-medium mb-1">No data found</p>
            <p class="text-white text-sm">No {{ dimLabel(importModal.dimension) }} records in the last 12 months.</p>
          </div>

          <div v-else class="p-4 max-h-[55vh] overflow-y-auto space-y-1.5">
            <div class="flex justify-between mb-2">
              <p class="text-xs text-white">{{ importModal.selected.length }} of {{ importModal.items.length }} selected</p>
              <button type="button" @click="toggleAll"
                class="text-xs text-white hover:text-white transition-colors">
                {{ importModal.selected.length === importModal.items.length ? 'Deselect All' : 'Select All' }}
              </button>
            </div>
            <label v-for="item in importModal.items" :key="item"
              class="flex items-center gap-3 p-3 rounded-lg bg-mp-card-hover hover:bg-mp-page/70 cursor-pointer transition-colors">
              <input type="checkbox" v-model="importModal.selected" :value="item"
                class="w-4 h-4 text-white bg-mp-page border-mp-border rounded" />
              <span class="text-sm text-white">{{ item }}</span>
            </label>
          </div>

          <div v-if="!importModal.loading" class="px-5 py-4 border-t border-mp-border flex items-center justify-between">
            <p class="text-xs text-white">{{ importModal.selected.length }} selected</p>
            <div class="flex gap-3">
              <button type="button" @click="importModal.show = false"
                class="px-4 py-2 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
                Cancel
              </button>
              <button type="button" @click="applyImport" :disabled="!importModal.selected.length"
                class="px-4 py-2 rounded-lg bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium transition-colors disabled:opacity-40">
                Import {{ importModal.selected.length || '' }} Selected
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  <!-- ══════════════════════════════════════════
       COLLECTION POLICY MODAL
  ═══════════════════════════════════════════ -->
  <Teleport to="body">
    <div v-if="collModal.open"
      class="fixed inset-0 z-[110] flex items-center justify-center bg-black/70 backdrop-blur-sm px-4"
      @click.self="collModal.open = false">
      <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-lg p-6 shadow-2xl">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h3 class="text-white font-semibold text-lg">Collection Policy</h3>
            <p class="text-white text-xs mt-0.5">
              {{ collModal.target?.side === 'export' ? 'Export sales' : 'Local sales' }}
            </p>
          </div>
          <button type="button" @click="collModal.open = false" class="text-white hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Preset buttons -->
        <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Select Preset</p>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-5">
          <button v-for="preset in collectionPresets" :key="preset.key"
            type="button" @click="selectCollPreset(preset.key)"
            :class="[
              'px-3 py-2 rounded-lg text-sm font-medium border transition-colors text-center',
              collModal.draft.preset === preset.key
                ? 'bg-mp-gold-dark border-mp-gold text-white'
                : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page'
            ]">
            {{ preset.label }}
          </button>
        </div>

        <!-- Custom tranches -->
        <div v-if="collModal.draft.preset === 'custom'">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Custom Collection Tranches</p>
          <p class="text-white text-xs mb-3">3 tranches must sum to 100%. Days = days after invoice date.</p>
          <div class="space-y-3">
            <div v-for="(tranche, ti) in collModal.draft.tranches" :key="ti"
              class="flex items-center gap-3 bg-mp-card-hover border border-mp-border rounded-lg p-3">
              <span class="text-white text-xs font-semibold w-16 flex-shrink-0">Tranche {{ ti + 1 }}</span>
              <div class="flex-1">
                <label class="block text-xs text-white mb-1">Percentage %</label>
                <div class="flex items-center gap-1">
                  <input type="number" min="0" max="100" step="1" v-model.number="tranche.pct"
                    class="w-full bg-mp-card border border-mp-border rounded px-2 py-1.5 text-white text-sm text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                  <span class="text-white text-xs">%</span>
                </div>
              </div>
              <div class="flex-1">
                <label class="block text-xs text-white mb-1">Due in (days)</label>
                <input type="number" min="0" step="1" v-model.number="tranche.days"
                  class="w-full bg-mp-card border border-mp-border rounded px-2 py-1.5 text-white text-sm text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
              </div>
            </div>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <div :class="['w-2 h-2 rounded-full flex-shrink-0', collTrancheSumOk ? 'bg-mp-success' : 'bg-mp-danger']"></div>
            <span :class="['text-xs', collTrancheSumOk ? 'text-mp-success' : 'text-mp-danger']">
              {{ collTrancheSumOk ? 'Tranches sum to 100% ✓' : `Currently ${collTrancheSum}% — must equal 100%` }}
            </span>
          </div>
        </div>

        <!-- Preview for non-custom -->
        <div v-else class="bg-mp-card-hover/60 rounded-lg p-3 text-xs text-white">
          <span class="text-white font-medium">Summary: </span>
          {{ collModal.draft.tranches.filter(t=>t.pct>0).map(t => t.pct+'% collected after '+t.days+' days').join(' · ') }}
        </div>

        <!-- Save -->
        <div class="flex items-center justify-end gap-3 mt-5 pt-4 border-t border-mp-border">
          <button type="button" @click="collModal.open = false"
            class="px-4 py-2 text-sm text-white hover:text-white transition-colors">Cancel</button>
          <button type="button" @click="saveCollPolicy"
            :disabled="collModal.draft.preset === 'custom' && !collTrancheSumOk"
            class="px-5 py-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40">
            Apply Policy
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ══════════════════════════════════════════
       BREAKDOWN MODAL (Beginning Inventory)
  ═══════════════════════════════════════════ -->
  <Teleport to="body">
    <div v-if="breakdownModal.open"
      class="fixed inset-0 z-[110] flex items-center justify-center bg-black/70 backdrop-blur-sm px-4"
      @click.self="breakdownModal.open = false">
      <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
          <div>
            <h3 class="text-white font-semibold text-lg">Breakdown</h3>
            <p class="text-white text-xs mt-0.5">
              Cost composition of beginning inventory —
              <span class="text-mp-warning font-medium">{{ formatNum(salesData[breakdownModal.productIndex]?.beg_inv_amount ?? 0) }} {{ study.study_currency }}</span>
            </p>
          </div>
          <button type="button" @click="breakdownModal.open = false" class="text-white hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Rows -->
        <div class="px-6 pt-5 pb-3 space-y-4">

          <!-- Header row -->
          <div class="grid grid-cols-3 gap-4 px-1">
            <span class="text-xs font-semibold text-white uppercase tracking-widest col-span-1">Item</span>
            <span class="text-xs font-semibold text-white uppercase tracking-widest text-center">Percentage %</span>
            <span class="text-xs font-semibold text-white uppercase tracking-widest text-right">Value</span>
          </div>

          <!-- Raw Material -->
          <div class="grid grid-cols-3 gap-4 items-center border-b border-mp-border pb-4">
            <div class="bg-mp-teal-subtle/20 border border-mp-teal/40 rounded-lg px-3 py-2.5 text-white text-sm font-medium">
              Raw Material Value
            </div>
            <input type="number" min="0" max="100" step="0.01"
              v-model.number="breakdownModal.form.raw_material_pct"
              @input="autoFillBreakdownTotal"
              class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
            <div class="bg-mp-card-hover/60 border border-mp-border/50 rounded-lg px-3 py-2.5 text-white text-sm text-right font-mono">
              {{ formatNum((salesData[breakdownModal.productIndex]?.beg_inv_amount || 0) * (breakdownModal.form.raw_material_pct || 0) / 100) }}
            </div>
          </div>

          <!-- Direct Labor -->
          <div class="grid grid-cols-3 gap-4 items-center border-b border-mp-border pb-4">
            <div class="bg-mp-teal-subtle/20 border border-mp-teal/40 rounded-lg px-3 py-2.5 text-white text-sm font-medium">
              Direct Labor Value
            </div>
            <input type="number" min="0" max="100" step="0.01"
              v-model.number="breakdownModal.form.direct_labor_pct"
              @input="autoFillBreakdownTotal"
              class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
            <div class="bg-mp-card-hover/60 border border-mp-border/50 rounded-lg px-3 py-2.5 text-white text-sm text-right font-mono">
              {{ formatNum((salesData[breakdownModal.productIndex]?.beg_inv_amount || 0) * (breakdownModal.form.direct_labor_pct || 0) / 100) }}
            </div>
          </div>

          <!-- Manufacturing Overheads -->
          <div class="grid grid-cols-3 gap-4 items-center pb-2">
            <div class="bg-mp-teal-subtle/20 border border-mp-teal/40 rounded-lg px-3 py-2.5 text-white text-sm font-medium">
              Manufacturing Overheads Value
            </div>
            <div :class="[
              'border rounded-lg px-3 py-2.5 text-sm text-center font-mono',
              breakdownTotal === 100
                ? 'bg-mp-success/20 border-mp-success/40 text-mp-success'
                : 'bg-mp-card-hover border-mp-border text-white'
            ]">
              {{ breakdownModal.form.overheads_pct.toFixed(2) }}
            </div>
            <div class="bg-mp-card-hover/60 border border-mp-border/50 rounded-lg px-3 py-2.5 text-white text-sm text-right font-mono">
              {{ formatNum((salesData[breakdownModal.productIndex]?.beg_inv_amount || 0) * (breakdownModal.form.overheads_pct || 0) / 100) }}
            </div>
          </div>

          <!-- Validation -->
          <div v-if="breakdownTotal !== 100" class="flex items-center gap-2 pt-1">
            <div class="w-2 h-2 rounded-full bg-mp-danger flex-shrink-0"></div>
            <span class="text-xs text-mp-danger">
              Must equal 100% — currently {{ breakdownTotal.toFixed(2) }}%
              (Overheads auto-fills to balance)
            </span>
          </div>
          <div v-else class="flex items-center gap-2 pt-1">
            <div class="w-2 h-2 rounded-full bg-mp-success flex-shrink-0"></div>
            <span class="text-xs text-mp-success">Breakdown sums to 100% ✓</span>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-mp-border">
          <button type="button" @click="breakdownModal.open = false"
            class="px-4 py-2 text-sm text-white hover:text-white transition-colors">Cancel</button>
          <button type="button" @click="saveBreakdown"
            :disabled="breakdownTotal !== 100"
            class="px-5 py-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40">
            Save
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StudyWriteup from '@/Components/StudyWriteup.vue'

// ─────────────────────────────────────────────────────
//  Props
// ─────────────────────────────────────────────────────
const props = defineProps({
  company:       { type: Object, required: true },
  study:         { type: Object, required: true },
  products:      { type: Array,  default: () => [] },
  existingSales: { type: Object, default: null },
  writeupText:   { type: String, default: '' },
})

const page     = usePage()
const flashMsg = ref(page.props.flash?.success ?? null)

// ─────────────────────────────────────────────────────
//  Constants
// ─────────────────────────────────────────────────────
const wizardSteps = ['Setup & Products','Sales Projection','COGS','Expenses','Manpower','Fixed Assets','Opening Balance','Results']
const months      = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

const dimensions = [
  { value: 'none',            icon: '➖', label: 'No Breakdown'    },
  { value: 'sales_channel',   icon: '📦', label: 'Sales Channel'   },
  { value: 'business_sector', icon: '🏢', label: 'Business Sector' },
  { value: 'customer_name',   icon: '👤', label: 'By Customer'     },
]

const dimColumnMap = {
  sales_channel:   'sales_channel',
  business_sector: 'business_sector',
  customer_name:   'customer_name',
}

// ─────────────────────────────────────────────────────
//  Data model helpers
//
//  Year 1 → monthly (12 months): { price, volume }[]  length=12
//  Year 2 → monthly (12 months): { price, volume }[]  length=12
//  Year 3+ → annual: { price, volume, price_growth_pct, volume_growth_pct, capacity_pct }
// ─────────────────────────────────────────────────────
function buildMonths() {
  return Array.from({ length: 12 }, () => ({ price: 0, volume: 0 }))
}

function buildAnnualYears(n) {
  // n = number of annual years (duration_years - 2), minimum 0
  return Array.from({ length: Math.max(0, n) }, (_, i) => ({
    year: i + 3,
    price: 0,
    volume: 0,
    price_growth_pct: 0,
    volume_growth_pct: 0,
    capacity_pct: 80,
  }))
}

// ─────────────────────────────────────────────────────
//  Collection Policy (modal-based, same as ExpensesPlanStep)
// ─────────────────────────────────────────────────────
const collectionPresets = [
  { key: 'cash',        label: 'Cash'        },
  { key: 'quarterly',   label: 'Quarterly'   },
  { key: 'semi_annual', label: 'Semi-Annual' },
  { key: 'annual',      label: 'Annual'      },
  { key: 'custom',      label: 'Custom'      },
]

function defaultCollectionTranches(preset) {
  if (preset === 'cash')        return [{ pct: 100, days: 0   }, { pct: 0, days: 0 }, { pct: 0, days: 0 }]
  if (preset === 'quarterly')   return [{ pct: 100, days: 90  }, { pct: 0, days: 0 }, { pct: 0, days: 0 }]
  if (preset === 'semi_annual') return [{ pct: 100, days: 180 }, { pct: 0, days: 0 }, { pct: 0, days: 0 }]
  if (preset === 'annual')      return [{ pct: 100, days: 365 }, { pct: 0, days: 0 }, { pct: 0, days: 0 }]
  return [{ pct: 50, days: 30 }, { pct: 30, days: 60 }, { pct: 20, days: 90 }]
}

function defaultPolicy() {
  return { preset: 'cash', tranches: defaultCollectionTranches('cash') }
}

// Migrate old format { cash_pct, credit:[...] } to new format
function migratePolicy(old) {
  if (!old) return defaultPolicy()
  if (old.preset) return old  // already new format
  const cash = old.cash_pct ?? 100
  const t1   = old.credit?.[0] ?? { pct: 0, days: 30 }
  const t2   = old.credit?.[1] ?? { pct: 0, days: 60 }
  let preset = 'custom'
  if (cash === 100 && (!t1.pct) && (!t2.pct)) preset = 'cash'
  return {
    preset,
    tranches: [
      { pct: cash,   days: 0       },
      { pct: t1.pct, days: t1.days },
      { pct: t2.pct, days: t2.days },
    ],
  }
}

function policyLabel(policy) {
  if (!policy) return 'Cash'
  const map = { cash:'Cash', quarterly:'Quarterly', semi_annual:'Semi-Annual', annual:'Annual', custom:'Custom' }
  return map[policy.preset] ?? 'Cash'
}

// Collection modal state
const collModal = reactive({
  open:   false,
  target: null,
  draft:  { preset: 'cash', tranches: defaultCollectionTranches('cash') },
})
const collTrancheSum = computed(() =>
  collModal.draft.tranches.reduce((s, t) => s + (parseFloat(t.pct) || 0), 0)
)
const collTrancheSumOk = computed(() => Math.abs(collTrancheSum.value - 100) < 0.01)

function openCollModal(pi, side, ri) {
  const prod = salesData[pi]
  let policy
  if (side === 'local')        policy = prod.collection_local
  else if (side === 'export')  policy = prod.collection_export
  else                         policy = prod.local_allocation.rows[ri].collection_policy
  collModal.target = { pi, side, ri: ri ?? null }
  collModal.draft  = {
    preset:   policy.preset ?? 'cash',
    tranches: JSON.parse(JSON.stringify(policy.tranches ?? defaultCollectionTranches('cash'))),
  }
  collModal.open = true
}

function selectCollPreset(key) {
  collModal.draft.preset   = key
  collModal.draft.tranches = defaultCollectionTranches(key)
}

function saveCollPolicy() {
  const { pi, side, ri } = collModal.target
  const prod = salesData[pi]
  const newPolicy = { preset: collModal.draft.preset, tranches: JSON.parse(JSON.stringify(collModal.draft.tranches)) }
  if (side === 'local')        prod.collection_local = newPolicy
  else if (side === 'export')  prod.collection_export = newPolicy
  else                         prod.local_allocation.rows[ri].collection_policy = newPolicy
  collModal.open = false
}

function buildProduct(p, i) {
  const saved = props.existingSales?.products?.[i] ?? null
  const n     = props.study.duration_years ?? 1

  const flat = Array(12).fill(parseFloat((100 / 12).toFixed(4)))
  flat[11]   = parseFloat((100 - flat.slice(0, 11).reduce((a, b) => a + b, 0)).toFixed(4))

  return {
    product_index:    i,
    name:             p.name,
    nature:           p.nature,
    measurement_unit: p.measurement_unit ?? 'unit',
    showSeasonality:  false,

    // Year 1 monthly (12 rows)
    year1_months: saved?.year1_months ?? buildMonths(),
    // Year 2 monthly (12 rows) — only if duration >= 2
    // If saved data exists but duration grew, add year2_months; if shrunk, remove it
    year2_months: n >= 2
      ? (saved?.year2_months ?? buildMonths())
      : null,
    // Year 3+ annual rows — reconcile saved count against current duration
    // so that adding years to an existing study fills in the missing rows
    annual_years: (() => {
      const targetCount = Math.max(0, n - 2)
      const base        = saved?.annual_years ?? []
      if (base.length === targetCount) return base          // exact match — use as-is
      if (base.length > targetCount)   return base.slice(0, targetCount) // duration shrunk — trim
      // duration grew — keep saved rows, append the missing ones
      const extra = Array.from({ length: targetCount - base.length }, (_, i) => ({
        year:              base.length + i + 3,
        price:             0,
        volume:            0,
        price_growth_pct:  0,
        volume_growth_pct: 0,
        capacity_pct:      80,
      }))
      return [...base, ...extra]
    })(),

    pricing: {
      // keep seasonality for backward compat
      seasonality: saved?.pricing?.seasonality ?? flat,
    },

    market_split:      saved?.market_split ?? { local_pct: 100, export_pct: 0 },
    local_allocation:  (() => {
      const la = saved?.local_allocation ?? { dimension: 'none', rows: [{ name: '', pct: 100 }] }
      // Ensure each row has a collection_policy
      la.rows = (la.rows ?? [{ name: '', pct: 100 }]).map(r => ({
        ...r,
        collection_policy: migratePolicy(r.collection_policy),
      }))
      return la
    })(),
    collection_local:  migratePolicy(saved?.collection_local),
    collection_export: migratePolicy(saved?.collection_export),

    // ── Inventory fields (manufacturing only) ──
    inventory_coverage_days: saved?.inventory_coverage_days ?? 30,
    beg_inv_qty:             saved?.beg_inv_qty             ?? 0,
    beg_inv_amount:          saved?.beg_inv_amount          ?? 0,
    beg_inv_breakdown:       saved?.beg_inv_breakdown       ?? { raw_material_pct: 84, direct_labor_pct: 3, overheads_pct: 13 },
  }
}

const salesData     = reactive(props.products.map((p, i) => buildProduct(p, i)))
const activeProduct = ref(0)
const saving        = ref(false)
const savedOk       = ref(false)

// ─────────────────────────────────────────────────────
//  Inventory Coverage Days options
// ─────────────────────────────────────────────────────
const coverageDaysOptions = [0,7,15, 30, 45, 60, 75, 90, 120, 150, 180]

// ─────────────────────────────────────────────────────
//  Beg. Inventory Breakdown Modal
// ─────────────────────────────────────────────────────
const breakdownModal = reactive({
  open:         false,
  productIndex: null,
  form: {
    raw_material_pct: 84,
    direct_labor_pct: 3,
    overheads_pct:    13,
  },
})

const breakdownTotal = computed(() =>
  (breakdownModal.form.raw_material_pct || 0) +
  (breakdownModal.form.direct_labor_pct || 0) +
  (breakdownModal.form.overheads_pct    || 0)
)

function breakdownRawValue(pi) {
  const prod = salesData[pi]
  return ((prod.beg_inv_amount || 0) * (prod.beg_inv_breakdown?.raw_material_pct || 0) / 100)
}
function breakdownLaborValue(pi) {
  const prod = salesData[pi]
  return ((prod.beg_inv_amount || 0) * (prod.beg_inv_breakdown?.direct_labor_pct || 0) / 100)
}
function breakdownOverheadsValue(pi) {
  const prod = salesData[pi]
  return ((prod.beg_inv_amount || 0) * (prod.beg_inv_breakdown?.overheads_pct || 0) / 100)
}

function openBreakdownModal(pi) {
  const prod = salesData[pi]
  breakdownModal.productIndex = pi
  breakdownModal.form = {
    raw_material_pct: prod.beg_inv_breakdown?.raw_material_pct ?? 84,
    direct_labor_pct: prod.beg_inv_breakdown?.direct_labor_pct ?? 3,
    overheads_pct:    prod.beg_inv_breakdown?.overheads_pct    ?? 13,
  }
  breakdownModal.open = true
}

function saveBreakdown() {
  const prod = salesData[breakdownModal.productIndex]
  prod.beg_inv_breakdown = { ...breakdownModal.form }
  breakdownModal.open = false
}

function autoFillBreakdownTotal() {
  const used = (breakdownModal.form.raw_material_pct || 0) + (breakdownModal.form.direct_labor_pct || 0)
  breakdownModal.form.overheads_pct = parseFloat(Math.max(0, 100 - used).toFixed(2))
}

// ─────────────────────────────────────────────────────
//  Computed helpers per product
// ─────────────────────────────────────────────────────
function monthRevenue(m) { return (m.price || 0) * (m.volume || 0) }
function yearRevenue(months) { return months.reduce((s, m) => s + monthRevenue(m), 0) }
function annualRevenue(yr) { return (yr.price || 0) * (yr.volume || 0) }

// Year 1 totals
function y1Total(prod)    { return yearRevenue(prod.year1_months) }
function y1AvgPrice(prod) {
  const totalVol = prod.year1_months.reduce((s, m) => s + (m.volume || 0), 0)
  if (!totalVol) return 0
  return prod.year1_months.reduce((s, m) => s + (m.price || 0) * (m.volume || 0), 0) / totalVol
}

// ─────────────────────────────────────────────────────
//  Fill-forward helpers (the 3-dots button)
// ─────────────────────────────────────────────────────
function fillPriceRight(months, fromIndex) {
  const val = months[fromIndex].price
  for (let i = fromIndex + 1; i < months.length; i++) months[i].price = val
}
function fillVolumeRight(months, fromIndex) {
  const val = months[fromIndex].volume
  for (let i = fromIndex + 1; i < months.length; i++) months[i].volume = val
}

// Annual year growth appliers — base is always the PREVIOUS year
// Y3 (idx=0): base = Y2 (year2_months). If no Y2, fall back to Y1.
// Y4+ (idx≥1): base = annual_years[idx-1]
function applyPriceGrowthAnnual(prod, idx) {
  const pct = prod.annual_years[idx].price_growth_pct || 0
  let prev
  if (idx === 0) {
    // Y3 base = Y2 avg price
    if (prod.year2_months) {
      const vol2 = prod.year2_months.reduce((s, m) => s + (m.volume || 0), 0)
      prev = vol2
        ? prod.year2_months.reduce((s, m) => s + (m.price || 0) * (m.volume || 0), 0) / vol2
        : y1AvgPrice(prod)
    } else {
      prev = y1AvgPrice(prod)
    }
  } else {
    prev = prod.annual_years[idx - 1].price || 0
  }
  prod.annual_years[idx].price = parseFloat((prev * (1 + pct / 100)).toFixed(2))
}

function applyVolumeGrowthAnnual(prod, idx) {
  const pct = prod.annual_years[idx].volume_growth_pct || 0
  let prev
  if (idx === 0) {
    // Y3 base = Y2 total volume
    prev = prod.year2_months
      ? prod.year2_months.reduce((s, m) => s + (m.volume || 0), 0)
      : prod.year1_months.reduce((s, m) => s + (m.volume || 0), 0)
  } else {
    prev = prod.annual_years[idx - 1].volume || 0
  }
  prod.annual_years[idx].volume = Math.round(prev * (1 + pct / 100))
}

// ─────────────────────────────────────────────────────
//  Write-up summary data
// ─────────────────────────────────────────────────────
const writeupSummaryColumns = computed(() => {
  const cols = [
    { key: 'product', label: 'Product', align: 'left' },
    { key: 'nature',  label: 'Type',    align: 'left' },
    { key: 'y1',      label: 'Y1 Revenue', align: 'right', highlight: true, totalColor: '#7c3aed' },
  ]
  if (props.study.duration_years >= 2)
    cols.push({ key: 'y2', label: 'Y2 Revenue', align: 'right' })
  for (let y = 3; y <= props.study.duration_years; y++)
    cols.push({ key: `y${y}`, label: `Y${y} Revenue`, align: 'right' })
  cols.push({ key: 'split', label: 'Local/Export', align: 'right' })
  return cols
})

const writeupSummaryRows = computed(() =>
  salesData.map(prod => {
    const row = {
      product: prod.name || `Product ${prod.product_index + 1}`,
      nature:  prod.nature,
      y1:      formatNum(y1Total(prod)),
      split:   `${prod.market_split.local_pct ?? 0}% / ${prod.market_split.export_pct ?? 0}%`,
    }
    if (props.study.duration_years >= 2 && prod.year2_months)
      row.y2 = formatNum(yearRevenue(prod.year2_months))
    ;(prod.annual_years ?? []).forEach(yr => {
      row[`y${yr.year}`] = formatNum(annualRevenue(yr))
    })
    return row
  })
)

const writeupSummaryTotals = computed(() => {
  const total = { product: 'TOTAL', nature: '', split: '' }
  total.y1 = formatNum(salesData.reduce((s, p) => s + y1Total(p), 0))
  if (props.study.duration_years >= 2)
    total.y2 = formatNum(salesData.reduce((s, p) => s + (p.year2_months ? yearRevenue(p.year2_months) : 0), 0))
  for (let yi = 0; yi < (salesData[0]?.annual_years?.length ?? 0); yi++) {
    const yrNum = salesData[0].annual_years[yi].year
    total[`y${yrNum}`] = formatNum(salesData.reduce((s, p) => s + annualRevenue(p.annual_years[yi] ?? {}), 0))
  }
  return total
})

const writeupCategoryBreakdown = computed(() => {
  const totalY1 = salesData.reduce((s, p) => s + y1Total(p), 0)
  const colors  = ['#7c3aed', '#00b4c8', '#10b981', '#f59e0b', '#ef4444', '#ec4899']
  return salesData.map((prod, i) => {
    const rev = y1Total(prod)
    const pct = totalY1 > 0 ? ((rev / totalY1) * 100).toFixed(1) : '0'
    return {
      label: prod.name || `Product ${i + 1}`,
      value: `${pct}% — ${formatNum(rev)} (Y1)`,
      color: colors[i % colors.length],
    }
  })
})

// ─────────────────────────────────────────────────────
//  General helpers
// ─────────────────────────────────────────────────────
function yearLabel(offset) {
  if (!props.study.study_start_date) return `Y${offset + 1}`
  return String(new Date(props.study.study_start_date).getFullYear() + offset)
}
function formatYM(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short' })
}
function formatNum(n) {
  return n ? Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 }) : '0'
}
function splitTotal(prod)      { return (prod.market_split.local_pct || 0) + (prod.market_split.export_pct || 0) }
function seasonalityTotal(prod){ return prod.pricing.seasonality.reduce((a, b) => a + (parseFloat(b) || 0), 0) }
function allocationTotal(rows) { return rows.reduce((a, r) => a + (parseFloat(r.pct) || 0), 0) }

function isProductComplete(prod) {
  return y1Total(prod) > 0 && splitTotal(prod) === 100
}
function natureDot(n)   { return { manufacturing:'bg-mp-teal', trading:'bg-mp-gold', service:'bg-mp-teal' }[n] ?? 'bg-mp-border' }
function natureBadge(n) { return { manufacturing:'bg-mp-teal-subtle/60 text-white border border-mp-teal/50', trading:'bg-mp-gold/60 text-white border border-mp-gold/50', service:'bg-mp-teal-subtle/60 text-white border border-mp-teal/50' }[n] ?? 'bg-mp-card-hover text-white' }
function natureIcon(n)  { return { manufacturing:'🏭', trading:'🛒', service:'⚙️' }[n] ?? '' }
function dimLabel(d)    { return dimensions.find(x => x.value === d)?.label ?? d }
function dimPlaceholder(d) {
  return { sales_channel:'e.g. Wholesale, Retail...', business_sector:'e.g. Hospitality, Construction...', customer_name:'e.g. Al-Nasser Group...' }[d] ?? 'Enter name...'
}

// ─────────────────────────────────────────────────────
//  Seasonality
// ─────────────────────────────────────────────────────
function setFlatSeasonality(pi) {
  const flat = Array(12).fill(parseFloat((100 / 12).toFixed(4)))
  flat[11]   = parseFloat((100 - flat.slice(0, 11).reduce((a, b) => a + b, 0)).toFixed(4))
  salesData[pi].pricing.seasonality = flat
}

// ─────────────────────────────────────────────────────
//  Allocation
// ─────────────────────────────────────────────────────
function setDimension(pi, side, value) {
  salesData[pi][`${side}_allocation`].dimension = value
  if (value !== 'none') salesData[pi][`${side}_allocation`].rows = [{ name: '', pct: 100, collection_policy: defaultPolicy() }]
}
function equalSplit(rows) {
  if (!rows.length) return
  const each = parseFloat((100 / rows.length).toFixed(2))
  rows.forEach((r, i) => {
    r.pct = i < rows.length - 1 ? each : parseFloat((100 - each * (rows.length - 1)).toFixed(2))
  })
}

// ─────────────────────────────────────────────────────
//  Fetch helper (replaces axios — uses XSRF cookie)
// ─────────────────────────────────────────────────────
async function apiFetch(url, opts = {}) {
  const xsrf = document.cookie.split('; ')
    .find(r => r.startsWith('XSRF-TOKEN='))?.split('=')[1]
  const { headers: extraHeaders, ...restOpts } = opts
  return fetch(url, {
    credentials: 'include',
    headers: {
      'Accept':         'application/json',
      'X-XSRF-TOKEN':   xsrf ? decodeURIComponent(xsrf) : '',
      ...(extraHeaders ?? {}),
    },
    ...restOpts,
  })
}

// ─────────────────────────────────────────────────────
//  Import modal (allocation dimension items)
// ─────────────────────────────────────────────────────
const importModal = reactive({
  show: false, loading: false,
  dimension: '', productIndex: 0, side: 'local',
  items: [], selected: [],
})

async function openImport(pi, side, dimension) {
  importModal.productIndex = pi
  importModal.side         = side
  importModal.dimension    = dimension
  importModal.show         = true
  importModal.loading      = true
  importModal.selected     = []
  importModal.items        = []

  try {
    const col = dimColumnMap[dimension]
    const res = await apiFetch(
      `/portfolio-companies/${props.company.id}/financial-studies/api/sales-dimension?column=${col}`
    )
    const data = await res.json()
    importModal.items = data.items ?? []
  } catch (e) {
    importModal.items = []
  } finally {
    importModal.loading = false
  }
}

function toggleAll() {
  importModal.selected = importModal.selected.length === importModal.items.length
    ? [] : [...importModal.items]
}

function applyImport() {
  const prod = salesData[importModal.productIndex]
  const key  = `${importModal.side}_allocation`
  const existingNames = prod[key].rows.filter(r => r.name.trim()).map(r => r.name.toLowerCase())
  importModal.selected.forEach(name => {
    if (!existingNames.includes(name.toLowerCase())) prod[key].rows.push({ name, pct: 0, collection_policy: defaultPolicy() })
  })
  if (prod[key].rows.some(r => r.name.trim())) prod[key].rows = prod[key].rows.filter(r => r.name.trim())
  equalSplit(prod[key].rows)
  importModal.show = false
}

// ─────────────────────────────────────────────────────
//  Save
// ─────────────────────────────────────────────────────
async function saveForm(action) {
  saving.value = true
  try {
    const res = await apiFetch(
      `/portfolio-companies/${props.company.id}/financial-studies/${props.study.id}/sales`,
      {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          sales_data:    { products: salesData },
          submit_button: action,
        }),
      }
    )

    // If Laravel returned an error status, read the body for debugging
    if (!res.ok) {
      const text = await res.text()
      console.error('Save error response:', res.status, text)
      alert(`Save failed (HTTP ${res.status}). Check browser console for details.`)
      return
    }

    const contentType = res.headers.get('content-type') ?? ''
    if (!contentType.includes('application/json')) {
      // Unexpected HTML response (e.g. Laravel redirect or error page)
      const text = await res.text()
      console.error('Non-JSON response from server:', text.substring(0, 500))
      alert('Save failed: unexpected server response. Check browser console.')
      return
    }

    const data = await res.json()
    savedOk.value = true
    setTimeout(() => {
      if (data.redirect) {
        router.visit(data.redirect)
      } else {
        router.visit(`/portfolio-companies/${props.company.id}/financial-studies`)
      }
    }, 800)
  } catch (e) {
    console.error('Save exception:', e)
    alert('Save failed: ' + e.message)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
input[type="month"]::-webkit-calendar-picker-indicator { filter: invert(0.7); }
input[type="number"]::-webkit-inner-spin-button { opacity: 0.3; }
</style>