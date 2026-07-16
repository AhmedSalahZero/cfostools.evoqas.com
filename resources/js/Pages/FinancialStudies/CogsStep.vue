<template>
  <Head :title="`COGS — ${study.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ══════════════════════════ TOP HEADER ══════════════════════════ -->
      <div class="bg-mp-card border-b border-mp-border sticky top-0 z-30">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5">

          <Link :href="`/portfolio-companies/${company.id}/financial-studies`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Studies
          </Link>

          <!-- Wizard bar -->
          <div class="flex items-center gap-0 mb-5 overflow-x-auto pb-1">
            <div v-for="(step, i) in wizardSteps" :key="i" class="flex items-center flex-shrink-0">
              <div :class="['flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors', i === 2 ? 'bg-mp-gold-dark text-white' : 'text-white']">
                <span :class="['w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0', i === 2 ? 'bg-white/20 text-white' : 'bg-mp-card-hover text-white']">{{ i + 1 }}</span>
                {{ step }}
              </div>
              <svg v-if="i < wizardSteps.length - 1" class="w-4 h-4 text-white mx-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </div>

          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white">Step 3 — Cost of Goods Sold (COGS)</h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }} · {{ study.name }}</p>
            </div>
            <div class="flex items-center gap-3">
            <Link :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/sales`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                ← Back
              </Link>
              <StudyWriteup
                :company-id="company.id"
                :study-id="study.id"
                :study-name="study.name"
                step-key="cogs"
                step-label="Expense Plan"
                step-icon=""
                accent-color="#7c3aed"
                :saved-text="props.writeupText"
                :summary-columns="writeupSummaryColumns"
                :summary-rows="writeupSummaryRows"
                :summary-totals="writeupSummaryTotals"
                :category-breakdown="writeupCategoryBreakdown"
              />
             
             
              <button type="button" @click="submitForm('save')" :disabled="processing"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Save &amp; Exit
              </button>
              <button type="button" @click="submitForm('next')" :disabled="processing"
                class="flex items-center gap-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors disabled:opacity-50">
                <svg v-if="processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                {{ processing ? 'Saving...' : 'Save & Next →' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════ CONTENT ══════════════════════════ -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">

        <!-- No products warning -->
        <div v-if="products.length === 0" class="bg-mp-warning/40 border border-mp-warning/50 rounded-xl p-6 text-center">
          <p class="text-mp-warning font-semibold">No products found.</p>
          <p class="text-white text-sm mt-1">Please go back to Step 1 and add products first.</p>
          <Link :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/edit`"
            class="mt-4 inline-flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            ← Back to Step 1
          </Link>
        </div>

        <!-- ═══════════════════ ONE CARD PER PRODUCT ═══════════════════ -->
        <div v-for="(product, pi) in products" :key="pi" class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">

          <!-- Product header -->
          <div class="px-6 py-4 border-b border-mp-border flex items-center gap-3">
            <span :class="[natureBadge(product.nature), 'px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wide']">{{ natureLabel(product.nature) }}</span>
            <h2 class="text-white font-semibold text-lg">{{ product.name }}</h2>
          </div>

          <!-- ══════════ MANUFACTURING ══════════ -->
          <div v-if="product.nature === 'manufacturing'" class="p-6 space-y-10">

            <!-- A) Raw Materials -->
            <div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">A — Raw Materials</p>

              <!-- Costing method toggle -->
              <div class="flex flex-wrap items-center gap-6 mb-5 bg-mp-card-hover/50 border border-mp-border rounded-lg px-4 py-3">
                <span class="text-xs text-white font-semibold uppercase tracking-widest flex-shrink-0">Costing Method:</span>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" value="bom" v-model="cogsForm[pi].rm_method" class="w-4 h-4 text-white bg-mp-card-hover border-mp-border"/>
                  <span class="text-sm text-white">Bill of Materials</span>
                  <span class="text-white text-xs">(cost/unit × qty per finished unit)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" value="pct_selling" v-model="cogsForm[pi].rm_method" class="w-4 h-4 text-white bg-mp-card-hover border-mp-border"/>
                  <span class="text-sm text-white">% of Selling Price</span>
                  <span class="text-white text-xs">(% allocated per material)</span>
                </label>
              </div>

              <div v-if="!study.raw_materials || study.raw_materials.length === 0"
                class="text-white text-sm italic bg-mp-card-hover/40 border border-mp-border rounded-lg p-4">
                No raw materials defined in Step 1.
              </div>

              <!-- BOM mode -->
              <div v-else-if="cogsForm[pi].rm_method === 'bom'" class="space-y-3">
                <!-- Header row -->
                <div class="overflow-x-auto">
                  <table class="w-full text-xs">
                    <thead>
                      <tr class="border-b border-mp-border">
                        <th class="text-left text-white font-semibold py-2.5 pr-3 whitespace-nowrap min-w-[130px]">Material</th>
                        <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Unit</th>
                        <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Cost/Unit<br/><span class="text-white font-normal">({{ study.study_currency }})</span></th>
                        <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Qty per<br/>Finished Unit</th>
                        <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Annual Cost<br/>Increase %</th>
                        <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Beg. Inv.<br/>Qty</th>
                        <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Beg. Inv.<br/>Value ({{ study.study_currency }})</th>
                        <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Cost per<br/>Finished Unit</th>
                        <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Payment<br/>Policy</th>
                      </tr>
                    </thead>
                    <tbody>
                      <template v-for="(rm, rmi) in study.raw_materials" :key="rmi">
                        <!-- Data row -->
                        <tr class="border-b border-mp-border hover:bg-mp-card-hover/20">
                          <td class="py-2.5 pr-3 text-white font-medium">{{ rm.name }}</td>
                          <td class="py-2.5 px-2 text-center text-white">{{ rm.unit || '—' }}</td>
                          <td class="py-2.5 px-2">
                            <input type="number" min="0" step="0.01" v-model.number="cogsForm[pi].raw_materials[rmi].cost_per_unit"
                              class="w-24 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                          </td>
                          <td class="py-2.5 px-2">
                            <input type="number" min="0" step="0.001" v-model.number="cogsForm[pi].raw_materials[rmi].qty_per_unit"
                              class="w-20 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                          </td>
                          <td class="py-2.5 px-2">
                            <div class="flex items-center gap-0.5 justify-center">
                              <input type="number" min="0" max="100" step="0.1" v-model.number="cogsForm[pi].raw_materials[rmi].annual_increase_pct"
                                class="w-16 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                              <span class="text-white">%</span>
                            </div>
                          </td>
                          <td class="py-2.5 px-2">
                            <input type="number" min="0" step="1" v-model.number="cogsForm[pi].raw_materials[rmi].beg_inventory_qty"
                              class="w-20 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                          </td>
                          <td class="py-2.5 px-2">
                            <input type="number" min="0" step="0.01" v-model.number="cogsForm[pi].raw_materials[rmi].beg_inventory_value"
                              :placeholder="autoBegVal(cogsForm[pi].raw_materials[rmi])"
                              class="w-24 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center focus:outline-none focus:ring-1 focus:ring-mp-gold placeholder-gray-600"/>
                          </td>
                          <td class="py-2.5 px-2 text-center text-white font-semibold">
                            {{ fmtNum((cogsForm[pi].raw_materials[rmi].cost_per_unit||0)*(cogsForm[pi].raw_materials[rmi].qty_per_unit||0)) }}
                          </td>
                          <!-- Toggle button -->
                          <td class="py-2.5 px-2 text-center">
                            <button type="button"
                              @click="cogsForm[pi].raw_materials[rmi]._showPayment = !cogsForm[pi].raw_materials[rmi]._showPayment"
                              :class="['text-xs px-2.5 py-1 rounded-md font-medium transition-colors whitespace-nowrap', cogsForm[pi].raw_materials[rmi]._showPayment ? 'bg-mp-gold-dark text-white' : 'bg-mp-page text-white hover:bg-mp-muted']">
                              {{ cogsForm[pi].raw_materials[rmi]._showPayment ? '▲ Hide' : '▼ Set' }}
                            </button>
                          </td>
                        </tr>
                        <!-- Inline payment policy expansion -->
                        <tr v-if="cogsForm[pi].raw_materials[rmi]._showPayment" class="bg-mp-gold/20">
                          <td colspan="9" class="px-4 py-4 border-b border-mp-border">
                            <p class="text-m font-semibold text-white uppercase tracking-widest mb-3">{{ rm.name }} — Payment Policy</p>
                            <!-- Preset selector -->
                            <div class="flex items-center gap-3 mb-4">
                              <span class="text-xs text-white font-semibold uppercase tracking-widest whitespace-nowrap">Preset:</span>
                              <select v-model="cogsForm[pi].raw_materials[rmi].payment_policy.preset"
                                @change="applyPreset(cogsForm[pi].raw_materials[rmi].payment_policy)"
                                class="bg-mp-page border border-mp-border rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold">
                                <option v-for="p in paymentPresets" :key="p.value" :value="p.value">{{ p.label }}</option>
                              </select>
                            </div>
                            <!-- 3 tranches -->
                            <div class="grid grid-cols-3 gap-4">
                              <div v-for="(tranche, ti) in cogsForm[pi].raw_materials[rmi].payment_policy.tranches" :key="ti"
                                class="bg-mp-card/80 border border-mp-border rounded-xl px-4 py-3">
                                <p class="text-xs text-white font-semibold uppercase tracking-widest mb-3">Tranche {{ ti + 1 }}</p>
                                <div class="space-y-3">
                                  <div>
                                    <label class="block text-xs text-white mb-1">% of Invoice</label>
                                    <div class="flex items-center gap-1">
                                      <input type="number" min="0" max="100" step="0.1"
                                        v-model.number="tranche.pct"
                                        :disabled="cogsForm[pi].raw_materials[rmi].payment_policy.preset !== 'custom'"
                                        class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                                      <span class="text-white text-xs">%</span>
                                    </div>
                                  </div>
                                  <div>
                                    <label class="block text-xs text-white mb-1">Due (days from invoice)</label>
                                    <div class="flex items-center gap-1">
                                      <input type="number" min="0" step="1"
                                        v-model.number="tranche.days"
                                        :disabled="cogsForm[pi].raw_materials[rmi].payment_policy.preset !== 'custom'"
                                        class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                                      <span class="text-white text-xs whitespace-nowrap">days</span>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <!-- Total check -->
                            <div class="mt-3 flex items-center justify-end gap-2">
                              <span class="text-xs text-white">Total:</span>
                              <span :class="['text-xs font-bold', policyTotal(cogsForm[pi].raw_materials[rmi].payment_policy) === 100 ? 'text-mp-success' : 'text-mp-danger']">
                                {{ policyTotal(cogsForm[pi].raw_materials[rmi].payment_policy) }}%
                                <span v-if="policyTotal(cogsForm[pi].raw_materials[rmi].payment_policy) !== 100" class="font-normal ml-1">(must equal 100%)</span>
                              </span>
                            </div>
                          </td>
                        </tr>
                      </template>

                      <!-- BOM totals row -->
                      <tr class="bg-mp-card-hover/50 border-t-2 border-mp-border">
                        <td colspan="7" class="py-2.5 px-3 text-xs font-semibold text-white uppercase tracking-widest">Total Raw Material Cost per Finished Unit</td>
                        <td class="py-2.5 px-2 text-center text-white font-bold">{{ fmtNum(bomTotalPerUnit(pi)) }}</td>
                        <td></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- % of Selling Price mode -->
              <div v-else class="overflow-x-auto">
                <table class="w-full text-xs">
                  <thead>
                    <tr class="border-b border-mp-border">
                      <th class="text-left text-white font-semibold py-2.5 pr-3 whitespace-nowrap min-w-[130px]">Material</th>
                      <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">% of Selling Price<br/>(Yr 1)</th>
                      <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Annual<br/>Change %</th>
                      <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Beg. Inv.<br/>Qty</th>
                      <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Beg. Inv.<br/>Value ({{ study.study_currency }})</th>
                      <th class="text-center text-white font-semibold py-2.5 px-2 whitespace-nowrap">Payment<br/>Policy</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="(rm, rmi) in study.raw_materials" :key="rmi">
                      <tr class="border-b border-mp-border hover:bg-mp-card-hover/20">
                        <td class="py-2.5 pr-3 text-white font-medium">{{ rm.name }}</td>
                        <td class="py-2.5 px-2">
                          <div class="flex items-center gap-0.5 justify-center">
                            <input type="number" min="0" max="100" step="0.1" v-model.number="cogsForm[pi].raw_materials[rmi].pct_selling"
                              class="w-20 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                            <span class="text-white text-xs">%</span>
                          </div>
                        </td>
                        <td class="py-2.5 px-2">
                          <div class="flex items-center gap-0.5 justify-center">
                            <input type="number" min="-100" max="100" step="0.1" v-model.number="cogsForm[pi].raw_materials[rmi].annual_change_pct"
                              class="w-20 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                            <span class="text-white text-xs">%</span>
                          </div>
                        </td>
                        <td class="py-2.5 px-2">
                          <input type="number" min="0" step="1" v-model.number="cogsForm[pi].raw_materials[rmi].beg_inventory_qty"
                            class="w-20 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                        </td>
                        <td class="py-2.5 px-2">
                          <input type="number" min="0" step="0.01" v-model.number="cogsForm[pi].raw_materials[rmi].beg_inventory_value"
                            :placeholder="autoBegVal(cogsForm[pi].raw_materials[rmi])"
                            class="w-24 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center focus:outline-none focus:ring-1 focus:ring-mp-gold placeholder-gray-600"/>
                        </td>
                        <!-- Toggle button -->
                        <td class="py-2.5 px-2 text-center">
                          <button type="button"
                            @click="cogsForm[pi].raw_materials[rmi]._showPayment = !cogsForm[pi].raw_materials[rmi]._showPayment"
                            :class="['text-xs px-2.5 py-1 rounded-md font-medium transition-colors whitespace-nowrap', cogsForm[pi].raw_materials[rmi]._showPayment ? 'bg-mp-gold-dark text-white' : 'bg-mp-page text-white hover:bg-mp-muted']">
                            {{ cogsForm[pi].raw_materials[rmi]._showPayment ? '▲ Hide' : '▼ Set' }}
                          </button>
                        </td>
                      </tr>
                      <!-- Inline payment policy expansion -->
                      <tr v-if="cogsForm[pi].raw_materials[rmi]._showPayment" class="bg-mp-gold/20">
                        <td colspan="6" class="px-4 py-4 border-b border-mp-border">
                          <p class="text-m font-semibold text-white uppercase tracking-widest mb-3">{{ rm.name }} — Payment Policy</p>
                          <div class="flex items-center gap-3 mb-4">
                            <span class="text-xs text-white font-semibold uppercase tracking-widest whitespace-nowrap">Preset:</span>
                            <select v-model="cogsForm[pi].raw_materials[rmi].payment_policy.preset"
                              @change="applyPreset(cogsForm[pi].raw_materials[rmi].payment_policy)"
                              class="bg-mp-page border border-mp-border rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold">
                              <option v-for="p in paymentPresets" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </select>
                          </div>
                          <div class="grid grid-cols-3 gap-4">
                            <div v-for="(tranche, ti) in cogsForm[pi].raw_materials[rmi].payment_policy.tranches" :key="ti"
                              class="bg-mp-card/80 border border-mp-border rounded-xl px-4 py-3">
                              <p class="text-xs text-white font-semibold uppercase tracking-widest mb-3">Tranche {{ ti + 1 }}</p>
                              <div class="space-y-3">
                                <div>
                                  <label class="block text-xs text-white mb-1">% of Invoice</label>
                                  <div class="flex items-center gap-1">
                                    <input type="number" min="0" max="100" step="0.1"
                                      v-model.number="tranche.pct"
                                      :disabled="cogsForm[pi].raw_materials[rmi].payment_policy.preset !== 'custom'"
                                      class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                                    <span class="text-white text-xs">%</span>
                                  </div>
                                </div>
                                <div>
                                  <label class="block text-xs text-white mb-1">Due (days from invoice)</label>
                                  <div class="flex items-center gap-1">
                                    <input type="number" min="0" step="1"
                                      v-model.number="tranche.days"
                                      :disabled="cogsForm[pi].raw_materials[rmi].payment_policy.preset !== 'custom'"
                                      class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                                    <span class="text-white text-xs whitespace-nowrap">days</span>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                          <div class="mt-3 flex items-center justify-end gap-2">
                            <span class="text-xs text-white">Total:</span>
                            <span :class="['text-xs font-bold', policyTotal(cogsForm[pi].raw_materials[rmi].payment_policy) === 100 ? 'text-mp-success' : 'text-mp-danger']">
                              {{ policyTotal(cogsForm[pi].raw_materials[rmi].payment_policy) }}%
                              <span v-if="policyTotal(cogsForm[pi].raw_materials[rmi].payment_policy) !== 100" class="font-normal ml-1">(must equal 100%)</span>
                            </span>
                          </div>
                        </td>
                      </tr>
                    </template>
                    <tr class="bg-mp-card-hover/50 border-t-2 border-mp-border">
                      <td class="py-2.5 px-3 text-xs font-semibold text-white uppercase tracking-widest">Total % of Selling Price</td>
                      <td class="py-2.5 px-2 text-center text-white font-bold">{{ fmtNum(pctTotalSelling(pi)) }}%</td>
                      <td colspan="4"></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- B) Manufacturing Overheads → see shared card below product list -->
            <div class="bg-mp-teal-subtle/20 border border-mp-teal/30 rounded-lg px-4 py-3 flex items-center gap-3">
              <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <p class="text-white text-sm">Manufacturing Overheads (Factory Rent, Electricity, etc.) are entered in the <span class="font-semibold">shared section below</span> — they apply across all manufacturing products.</p>
            </div>

            <!-- Info note -->
            <div class="bg-mp-teal-subtle/30 border border-mp-teal/40 rounded-xl p-4 flex items-start gap-3">
              <svg class="w-5 h-5 text-white flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <div>
                <p class="text-white text-sm font-semibold">Direct Labor &amp; Indirect Labor → Step 4</p>
                <p class="text-white/70 text-xs mt-1">Enter all labor in Step 4 — Manpower Plan. The model pulls them automatically into COGS.</p>
              </div>
            </div>

          </div><!-- end manufacturing -->

          <!-- ══════════ TRADING ══════════ -->
          <div v-else-if="product.nature === 'trading'" class="p-6 space-y-8">

            <div class="bg-mp-card-hover/30 border border-mp-border rounded-lg px-4 py-3 text-xs text-white">
              <span class="font-semibold text-white">Formula: </span>COGS = Beginning Inventory + Purchases − Ending Inventory
            </div>

            <!-- A) Purchase Cost -->
            <div class="space-y-5">
              <p class="text-m font-semibold text-white uppercase tracking-widest">A — Purchase Cost</p>
              <div class="grid grid-cols-1 sm:grid-cols-4 gap-5">
                <div>
                  <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Unit Purchase Cost ({{ study.study_currency }})</label>
                  <input type="number" min="0" step="0.01" v-model.number="cogsForm[pi].unit_purchase_cost"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-gold"/>
                </div>
                <div>
                  <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Annual Cost Increase %</label>
                  <div class="flex items-center gap-2">
                    <input type="number" min="0" max="100" step="0.1" v-model.number="cogsForm[pi].annual_cost_increase_pct"
                      class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-gold"/>
                    <span class="text-white">%</span>
                  </div>
                </div>
                <div>
                  <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Target Inventory Days</label>
                  <div class="flex items-center gap-2">
                    <input type="number" min="0" step="1" v-model.number="cogsForm[pi].inventory_days"
                      class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-gold"/>
                    <span class="text-white text-sm">days</span>
                  </div>
                </div>
                 <!-- Trading Payment Policy — modal button -->
              <div>
                <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Purchase Payment Policy</label>
                <button type="button" @click="openPaymentModal(pi, 'purchase_payment_policy', product.name + ' — Purchase')"
                  :class="[
                    'flex items-center gap-2 border rounded-lg px-4 py-2.5 text-sm font-medium transition-colors w-full m:w-auto',
                    cogsForm[pi].purchase_payment_policy?.preset === 'custom'
                      ? 'bg-mp-gold/40 border-mp-gold/60 text-white'
                      : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page'
                  ]">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                  </svg>
                  <span>{{ policyLabel(cogsForm[pi].purchase_payment_policy) }}</span>
                  <svg class="w-3 h-3 ml-auto flex-shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                  </svg>
                </button>
              </div>
              </div>

            </div>

            <!-- B) Beginning Inventory -->
            <div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">B — Beginning Inventory (Month 1 Opening)</p>
              <div class="grid grid-cols-1 sm:grid-cols-4 gap-5">
                <div>
                  <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Beginning Inventory — Units (Qty)</label>
                  <input type="number" min="0" step="1" v-model.number="cogsForm[pi].beginning_inventory_units"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-gold"/>
                </div>
                <div>
                  <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Beginning Inventory — Value ({{ study.study_currency }})</label>
                  <input type="number" min="0" step="0.01" v-model.number="cogsForm[pi].beginning_inventory_value"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-gold"/>
                  <p class="text-white text-xs mt-1.5">If 0, auto-calculated as: Units × Unit Purchase Cost</p>
                </div>
              </div>
            </div>

          </div><!-- end trading -->

          <!-- ══════════ SERVICE ══════════ -->
          <div v-else-if="product.nature === 'service'" class="p-6 space-y-6">

            <p class="text-xs font-semibold text-white uppercase tracking-widest">Direct Cost of Service</p>

            <!-- Method toggle -->
            <div class="flex flex-wrap items-center gap-6 bg-mp-card-hover/50 border border-mp-border rounded-lg px-4 py-3">
              <span class="text-xs text-white font-semibold uppercase tracking-widest flex-shrink-0">Method:</span>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" value="pct_revenue" v-model="cogsForm[pi].service_method" class="w-4 h-4 text-white bg-mp-card-hover border-mp-border"/>
                <span class="text-sm text-white">% of Revenue</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" value="fixed_monthly" v-model="cogsForm[pi].service_method" class="w-4 h-4 text-white bg-mp-card-hover border-mp-border"/>
                <span class="text-sm text-white">Fixed Monthly Amount</span>
              </label>
            </div>

            <!-- % of Revenue -->
            <div v-if="cogsForm[pi].service_method === 'pct_revenue'" class="space-y-5">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                  <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">% of Revenue (Year 1)</label>
                  <div class="flex items-center gap-2">
                    <input type="number" min="0" max="100" step="0.1" v-model.number="cogsForm[pi].service_pct"
                      class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
                    <span class="text-white">%</span>
                  </div>
                </div>
                <div>
                  <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Annual Change %</label>
                  <div class="flex items-center gap-2">
                    <input type="number" min="-100" max="100" step="0.1" v-model.number="cogsForm[pi].service_annual_change"
                      class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
                    <span class="text-white">%</span>
                  </div>
                </div>
              </div>
              <!-- Service payment policy -->
              <div class="bg-mp-card-hover/60 border border-mp-border rounded-xl p-4">
                <div class="flex items-center gap-3 mb-4">
                  <p class="text-xs font-semibold text-white uppercase tracking-widest flex-shrink-0">Payment Policy</p>
                  <select v-model="cogsForm[pi].service_payment_policy.preset" @change="applyPreset(cogsForm[pi].service_payment_policy)"
                    class="bg-mp-page border border-mp-border rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold">
                    <option v-for="p in paymentPresets" :key="p.value" :value="p.value">{{ p.label }}</option>
                  </select>
                </div>
                <div class="grid grid-cols-3 gap-4">
                  <div v-for="(tranche, ti) in cogsForm[pi].service_payment_policy.tranches" :key="ti" class="bg-mp-card/60 border border-mp-border rounded-xl px-4 py-3">
                    <p class="text-xs text-white font-semibold uppercase tracking-widest mb-3">Tranche {{ ti + 1 }}</p>
                    <div class="space-y-3">
                      <div>
                        <label class="block text-xs text-white mb-1">% of Invoice</label>
                        <div class="flex items-center gap-1">
                          <input type="number" min="0" max="100" step="0.1" v-model.number="tranche.pct"
                            :disabled="cogsForm[pi].service_payment_policy.preset !== 'custom'"
                            class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                          <span class="text-white text-xs">%</span>
                        </div>
                      </div>
                      <div>
                        <label class="block text-xs text-white mb-1">Due (days)</label>
                        <div class="flex items-center gap-1">
                          <input type="number" min="0" step="1" v-model.number="tranche.days"
                            :disabled="cogsForm[pi].service_payment_policy.preset !== 'custom'"
                            class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                          <span class="text-white text-xs">days</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="mt-3 flex items-center justify-end gap-2">
                  <span class="text-xs text-white">Total:</span>
                  <span :class="['text-xs font-bold', policyTotal(cogsForm[pi].service_payment_policy) === 100 ? 'text-mp-success' : 'text-mp-danger']">
                    {{ policyTotal(cogsForm[pi].service_payment_policy) }}%
                    <span v-if="policyTotal(cogsForm[pi].service_payment_policy) !== 100" class="font-normal ml-1">(must equal 100%)</span>
                  </span>
                </div>
              </div>
            </div>

            <!-- Fixed Monthly -->
            <div v-else class="space-y-5">
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-5">
                <div>
                  <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Start Date</label>
                  <input type="month" v-model="cogsForm[pi].service_start_date" class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
                </div>
                <div>
                  <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">End Date</label>
                  <input type="month" v-model="cogsForm[pi].service_end_date" class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
                  <p class="text-white text-xs mt-1">Leave blank = full period</p>
                </div>
                <div>
                  <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Monthly Amount ({{ study.study_currency }})</label>
                  <input type="number" min="0" step="0.01" v-model.number="cogsForm[pi].service_amount" class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
                </div>
                <div>
                  <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Annual Increase %</label>
                  <div class="flex items-center gap-2">
                    <input type="number" min="0" max="100" step="0.1" v-model.number="cogsForm[pi].service_annual_increase" class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
                    <span class="text-white">%</span>
                  </div>
                </div>
              </div>
              <!-- Service payment policy -->
              <div class="bg-mp-card-hover/60 border border-mp-border rounded-xl p-4">
                <div class="flex items-center gap-3 mb-4">
                  <p class="text-xs font-semibold text-white uppercase tracking-widest flex-shrink-0">Payment Policy</p>
                  <select v-model="cogsForm[pi].service_payment_policy.preset" @change="applyPreset(cogsForm[pi].service_payment_policy)"
                    class="bg-mp-page border border-mp-border rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold">
                    <option v-for="p in paymentPresets" :key="p.value" :value="p.value">{{ p.label }}</option>
                  </select>
                </div>
                <div class="grid grid-cols-3 gap-4">
                  <div v-for="(tranche, ti) in cogsForm[pi].service_payment_policy.tranches" :key="ti" class="bg-mp-card/60 border border-mp-border rounded-xl px-4 py-3">
                    <p class="text-xs text-white font-semibold uppercase tracking-widest mb-3">Tranche {{ ti + 1 }}</p>
                    <div class="space-y-3">
                      <div>
                        <label class="block text-xs text-white mb-1">% of Invoice</label>
                        <div class="flex items-center gap-1">
                          <input type="number" min="0" max="100" step="0.1" v-model.number="tranche.pct"
                            :disabled="cogsForm[pi].service_payment_policy.preset !== 'custom'"
                            class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                          <span class="text-white text-xs">%</span>
                        </div>
                      </div>
                      <div>
                        <label class="block text-xs text-white mb-1">Due (days)</label>
                        <div class="flex items-center gap-1">
                          <input type="number" min="0" step="1" v-model.number="tranche.days"
                            :disabled="cogsForm[pi].service_payment_policy.preset !== 'custom'"
                            class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                          <span class="text-white text-xs">days</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="mt-3 flex items-center justify-end gap-2">
                  <span class="text-xs text-white">Total:</span>
                  <span :class="['text-xs font-bold', policyTotal(cogsForm[pi].service_payment_policy) === 100 ? 'text-mp-success' : 'text-mp-danger']">
                    {{ policyTotal(cogsForm[pi].service_payment_policy) }}%
                    <span v-if="policyTotal(cogsForm[pi].service_payment_policy) !== 100" class="font-normal ml-1">(must equal 100%)</span>
                  </span>
                </div>
              </div>
            </div>

          </div><!-- end service -->

        </div><!-- end product card -->

        <!-- ═══════════════ SHARED MANUFACTURING OVERHEADS CARD ═══════════════ -->
        <div v-if="mfgProducts.length > 0" class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">

          <!-- Card header -->
          <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between">
            <div>
              <div class="flex items-center gap-3">
                <span class="bg-mp-teal-subtle/60 text-white border border-mp-teal/50 px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wide">Manufacturing</span>
                <h2 class="text-white font-semibold text-lg">B — Manufacturing Overheads</h2>
              </div>
              <p class="text-white text-xs mt-1">Shared across all manufacturing products — Factory Rent, Electricity, Water, Maintenance, etc.</p>
            </div>
            <button type="button" @click="addOverhead()"
              class="flex items-center gap-1.5 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Add Overhead Item
            </button>
          </div>

          <div class="p-6 space-y-4">
            <div v-if="sharedOverheads.length === 0"
              class="text-white text-sm italic p-4 bg-mp-card-hover/30 border border-mp-border rounded-lg">
              No overhead items added yet. Examples: Factory Rent, Electricity, Water, Maintenance...
            </div>

            <div v-for="(oh, ohi) in sharedOverheads" :key="ohi"
              class="bg-mp-card-hover/40 border border-mp-border rounded-xl overflow-hidden">

              <!-- Overhead header row -->
              <div class="flex items-center gap-3 px-4 py-3 border-b border-mp-border bg-mp-card-hover/60">
                <input type="text" v-model="oh.name" placeholder="Overhead name (e.g. Factory Rent)"
                  class="flex-1 bg-mp-page border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold placeholder-gray-500"/>
                <select v-model="oh.method"
                  class="bg-mp-page border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold min-w-[210px]">
                  <option value="fixed_monthly">Fixed Monthly Amount</option>
                  <option value="pct_revenue">% of Revenue</option>
                  <option value="cost_per_unit">Cost per Unit Produced</option>
                </select>
                <button type="button" @click="removeOverhead(ohi)" class="text-white hover:text-mp-danger transition-colors p-1.5">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>

              <!-- ── Fixed Monthly body ── -->
              <div v-if="oh.method === 'fixed_monthly'" class="px-4 py-4 space-y-5">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Start Date</label>
                    <input type="month" v-model="oh.start_date" class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                  </div>
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">End Date</label>
                    <input type="month" v-model="oh.end_date" class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                    <p class="text-white text-xs mt-1">Leave blank = full period</p>
                  </div>
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Monthly Amount ({{ study.study_currency }})</label>
                    <input type="number" min="0" step="0.01" v-model.number="oh.amount" class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                  </div>
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Annual Increase %</label>
                    <div class="flex items-center gap-1">
                      <input type="number" min="0" max="100" step="0.1" v-model.number="oh.annual_increase_pct" class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                      <span class="text-white text-sm">%</span>
                    </div>
                  </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                  <button type="button" @click="openOhAllocModal(ohi)"
                    :class="['flex items-center gap-2 border rounded-lg px-4 py-2 text-sm font-medium transition-colors', oh.product_allocation && oh.product_allocation.length > 0 ? 'bg-mp-success/40 border-mp-success/60 text-mp-success' : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page']">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    {{ oh.product_allocation && oh.product_allocation.length > 0 ? 'Allocation Set ✓' : 'Set Allocation' }}
                  </button>
                  <button type="button" @click="oh._showPayment = !oh._showPayment"
                    :class="['flex items-center gap-2 border rounded-lg px-4 py-2 text-sm font-medium transition-colors', oh._showPayment ? 'bg-mp-gold-dark border-mp-gold text-white' : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page']">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <span>Payment: <span class="font-semibold">{{ policyLabel2(oh.payment_policy) }}</span></span>
                    <span class="text-xs opacity-70">{{ oh._showPayment ? '▲ Hide' : '▼ Set' }}</span>
                  </button>
                </div>
                <div v-if="oh._showPayment" class="bg-mp-gold/20 border border-mp-gold/40 rounded-xl p-4">
                  <div class="flex items-center gap-3 mb-4">
                    <p class="text-xs font-semibold text-white uppercase tracking-widest flex-shrink-0">Payment Policy</p>
                    <select v-model="oh.payment_policy.preset" @change="applyPreset(oh.payment_policy)"
                      class="bg-mp-page border border-mp-border rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold">
                      <option v-for="p in paymentPresets" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                  </div>
                  <div class="grid grid-cols-3 gap-4">
                    <div v-for="(tranche, ti) in oh.payment_policy.tranches" :key="ti" class="bg-mp-card/80 border border-mp-border rounded-xl px-4 py-3">
                      <p class="text-xs text-white font-semibold uppercase tracking-widest mb-3">Tranche {{ ti + 1 }}</p>
                      <div class="space-y-3">
                        <div>
                          <label class="block text-xs text-white mb-1">% of Invoice</label>
                          <div class="flex items-center gap-1">
                            <input type="number" min="0" max="100" step="0.1" v-model.number="tranche.pct"
                              :disabled="oh.payment_policy.preset !== 'custom'"
                              class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                            <span class="text-white text-xs">%</span>
                          </div>
                        </div>
                        <div>
                          <label class="block text-xs text-white mb-1">Due (days)</label>
                          <div class="flex items-center gap-1">
                            <input type="number" min="0" step="1" v-model.number="tranche.days"
                              :disabled="oh.payment_policy.preset !== 'custom'"
                              class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                            <span class="text-white text-xs">days</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="mt-3 flex items-center justify-end gap-2">
                    <span class="text-xs text-white">Total:</span>
                    <span :class="['text-xs font-bold', policyTotal(oh.payment_policy) === 100 ? 'text-mp-success' : 'text-mp-danger']">
                      {{ policyTotal(oh.payment_policy) }}%
                      <span v-if="policyTotal(oh.payment_policy) !== 100" class="font-normal ml-1">(must equal 100%)</span>
                    </span>
                  </div>
                </div>
              </div>

              <!-- ── % of Revenue body ── -->
              <div v-else-if="oh.method === 'pct_revenue'" class="px-4 py-4 space-y-5">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">% of Revenue (Year 1)</label>
                    <div class="flex items-center gap-1">
                      <input type="number" min="0" max="100" step="0.1" v-model.number="oh.pct_revenue" class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                      <span class="text-white text-sm">%</span>
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Annual Change %</label>
                    <div class="flex items-center gap-1">
                      <input type="number" min="-100" max="100" step="0.1" v-model.number="oh.annual_change_pct" class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                      <span class="text-white text-sm">%</span>
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Apply to Products</label>
                    <div class="relative">
                      <button type="button" @click="oh._showProductsDropdown = !oh._showProductsDropdown"
                        class="w-full flex items-center justify-between bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-mp-gold">
                        <span class="truncate">{{ ohSelectedProductsLabel(oh) }}</span>
                        <svg class="w-4 h-4 text-white flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                      </button>
                      <div v-if="oh._showProductsDropdown"
                        class="absolute z-20 mt-1 w-full bg-mp-card-hover border border-mp-border rounded-lg shadow-xl py-1 max-h-48 overflow-y-auto">
                        <label v-for="mp in mfgProducts" :key="mp.name"
                          class="flex items-center gap-3 px-3 py-2 hover:bg-mp-page cursor-pointer">
                          <input type="checkbox" :value="mp.name" v-model="oh.apply_to_products"
                            class="w-4 h-4 rounded text-white bg-mp-page border-mp-border"/>
                          <span class="text-sm text-white">{{ mp.name }}</span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <button type="button" @click="oh._showPayment = !oh._showPayment"
                    :class="['flex items-center gap-2 border rounded-lg px-4 py-2 text-sm font-medium transition-colors', oh._showPayment ? 'bg-mp-gold-dark border-mp-gold text-white' : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page']">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <span>Payment: <span class="font-semibold">{{ policyLabel2(oh.payment_policy) }}</span></span>
                    <span class="text-xs opacity-70">{{ oh._showPayment ? '▲ Hide' : '▼ Set' }}</span>
                  </button>
                </div>
                <div v-if="oh._showPayment" class="bg-mp-gold/20 border border-mp-gold/40 rounded-xl p-4">
                  <div class="flex items-center gap-3 mb-4">
                    <p class="text-xs font-semibold text-white uppercase tracking-widest flex-shrink-0">Payment Policy</p>
                    <select v-model="oh.payment_policy.preset" @change="applyPreset(oh.payment_policy)"
                      class="bg-mp-page border border-mp-border rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold">
                      <option v-for="p in paymentPresets" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                  </div>
                  <div class="grid grid-cols-3 gap-4">
                    <div v-for="(tranche, ti) in oh.payment_policy.tranches" :key="ti" class="bg-mp-card/80 border border-mp-border rounded-xl px-4 py-3">
                      <p class="text-xs text-white font-semibold uppercase tracking-widest mb-3">Tranche {{ ti + 1 }}</p>
                      <div class="space-y-3">
                        <div>
                          <label class="block text-xs text-white mb-1">% of Invoice</label>
                          <div class="flex items-center gap-1">
                            <input type="number" min="0" max="100" step="0.1" v-model.number="tranche.pct"
                              :disabled="oh.payment_policy.preset !== 'custom'"
                              class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                            <span class="text-white text-xs">%</span>
                          </div>
                        </div>
                        <div>
                          <label class="block text-xs text-white mb-1">Due (days)</label>
                          <div class="flex items-center gap-1">
                            <input type="number" min="0" step="1" v-model.number="tranche.days"
                              :disabled="oh.payment_policy.preset !== 'custom'"
                              class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                            <span class="text-white text-xs">days</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="mt-3 flex items-center justify-end gap-2">
                    <span class="text-xs text-white">Total:</span>
                    <span :class="['text-xs font-bold', policyTotal(oh.payment_policy) === 100 ? 'text-mp-success' : 'text-mp-danger']">{{ policyTotal(oh.payment_policy) }}%</span>
                  </div>
                </div>
              </div>

              <!-- ── Cost per Unit body ── -->
              <div v-else-if="oh.method === 'cost_per_unit'" class="px-4 py-4 space-y-5">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Start Date</label>
                    <input type="month" v-model="oh.start_date" class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                  </div>
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">End Date</label>
                    <input type="month" v-model="oh.end_date" class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                  </div>
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Cost per Unit ({{ study.study_currency }})</label>
                    <input type="number" min="0" step="0.001" v-model.number="oh.amount" class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                  </div>
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Annual Increase %</label>
                    <div class="flex items-center gap-1">
                      <input type="number" min="0" max="100" step="0.1" v-model.number="oh.annual_increase_pct" class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                      <span class="text-white text-sm">%</span>
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs text-white uppercase tracking-widest font-semibold mb-2">Apply to Products</label>
                    <div class="relative">
                      <button type="button" @click="oh._showProductsDropdown = !oh._showProductsDropdown"
                        class="w-full flex items-center justify-between bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-mp-gold">
                        <span class="truncate">{{ ohSelectedProductsLabel(oh) }}</span>
                        <svg class="w-4 h-4 text-white flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                      </button>
                      <div v-if="oh._showProductsDropdown"
                        class="absolute z-20 mt-1 w-full bg-mp-card-hover border border-mp-border rounded-lg shadow-xl py-1 max-h-48 overflow-y-auto">
                        <label v-for="mp in mfgProducts" :key="mp.name"
                          class="flex items-center gap-3 px-3 py-2 hover:bg-mp-page cursor-pointer">
                          <input type="checkbox" :value="mp.name" v-model="oh.apply_to_products"
                            class="w-4 h-4 rounded text-white bg-mp-page border-mp-border"/>
                          <span class="text-sm text-white">{{ mp.name }}</span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <button type="button" @click="oh._showPayment = !oh._showPayment"
                    :class="['flex items-center gap-2 border rounded-lg px-4 py-2 text-sm font-medium transition-colors', oh._showPayment ? 'bg-mp-gold-dark border-mp-gold text-white' : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page']">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <span>Payment: <span class="font-semibold">{{ policyLabel2(oh.payment_policy) }}</span></span>
                    <span class="text-xs opacity-70">{{ oh._showPayment ? '▲ Hide' : '▼ Set' }}</span>
                  </button>
                </div>
                <div v-if="oh._showPayment" class="bg-mp-gold/20 border border-mp-gold/40 rounded-xl p-4">
                  <div class="flex items-center gap-3 mb-4">
                    <p class="text-xs font-semibold text-white uppercase tracking-widest flex-shrink-0">Payment Policy</p>
                    <select v-model="oh.payment_policy.preset" @change="applyPreset(oh.payment_policy)"
                      class="bg-mp-page border border-mp-border rounded-lg px-3 py-1.5 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold">
                      <option v-for="p in paymentPresets" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                  </div>
                  <div class="grid grid-cols-3 gap-4">
                    <div v-for="(tranche, ti) in oh.payment_policy.tranches" :key="ti" class="bg-mp-card/80 border border-mp-border rounded-xl px-4 py-3">
                      <p class="text-xs text-white font-semibold uppercase tracking-widest mb-3">Tranche {{ ti + 1 }}</p>
                      <div class="space-y-3">
                        <div>
                          <label class="block text-xs text-white mb-1">% of Invoice</label>
                          <div class="flex items-center gap-1">
                            <input type="number" min="0" max="100" step="0.1" v-model.number="tranche.pct"
                              :disabled="oh.payment_policy.preset !== 'custom'"
                              class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                            <span class="text-white text-xs">%</span>
                          </div>
                        </div>
                        <div>
                          <label class="block text-xs text-white mb-1">Due (days)</label>
                          <div class="flex items-center gap-1">
                            <input type="number" min="0" step="1" v-model.number="tranche.days"
                              :disabled="oh.payment_policy.preset !== 'custom'"
                              class="flex-1 bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-sm focus:outline-none focus:ring-1 focus:ring-mp-gold disabled:opacity-40"/>
                            <span class="text-white text-xs">days</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="mt-3 flex items-center justify-end gap-2">
                    <span class="text-xs text-white">Total:</span>
                    <span :class="['text-xs font-bold', policyTotal(oh.payment_policy) === 100 ? 'text-mp-success' : 'text-mp-danger']">{{ policyTotal(oh.payment_policy) }}%</span>
                  </div>
                </div>
              </div>

            </div><!-- end overhead item -->
          </div><!-- end overheads card body -->
        </div><!-- end shared overheads card -->
        <div class="bg-mp-card border border-mp-border rounded-xl px-6 py-4 flex flex-col sm:flex-row items-center justify-between gap-4">
          <Link :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/sales`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            ← Back to Step 2: Sales Projection
          </Link>
          <div class="flex items-center gap-3">
            <button type="button" @click="submitForm('save')" :disabled="processing"
              class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
              </svg>
              Save &amp; Exit
            </button>
            <button type="button" @click="submitForm('next')" :disabled="processing"
              class="flex items-center gap-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors disabled:opacity-50">
              <svg v-if="processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ processing ? 'Saving...' : 'Save & Next: Manpower →' }}
            </button>
          </div>
        </div>

      </div><!-- end content -->
    </div>

    <!-- ── PAYMENT POLICY MODAL ────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="paymentModal.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4"
        @click.self="paymentModal.open = false">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-lg p-6 shadow-2xl">
          <div class="flex items-center justify-between mb-5">
            <div>
              <h3 class="text-white font-semibold text-lg">Payment Policy</h3>
              <p class="text-white text-xs mt-0.5">{{ paymentModal.label }}</p>
            </div>
            <button type="button" @click="paymentModal.open = false" class="text-white hover:text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Preset buttons -->
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Select Preset</p>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-5">
            <button v-for="preset in paymentPresetList" :key="preset.key"
              type="button" @click="selectModalPreset(preset.key)"
              :class="[
                'px-3 py-2 rounded-lg text-sm font-medium border transition-colors text-center',
                paymentModal.draft.preset === preset.key
                  ? 'bg-mp-gold-dark border-mp-gold text-white'
                  : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page'
              ]">
              {{ preset.label }}
            </button>
          </div>

          <!-- Custom tranches -->
          <div v-if="paymentModal.draft.preset === 'custom'">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Custom Payment Tranches</p>
            <p class="text-white text-xs mb-3">3 tranches must sum to 100%. Days = days after invoice date.</p>
            <div class="space-y-3">
              <div v-for="(tranche, ti) in paymentModal.draft.tranches" :key="ti"
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
                  <label class="block text-xs text-white mb-1">Days after invoice</label>
                  <div class="flex items-center gap-1">
                    <input type="number" min="0" step="1" v-model.number="tranche.days"
                      class="w-full bg-mp-card border border-mp-border rounded px-2 py-1.5 text-white text-sm text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                    <span class="text-white text-xs">d</span>
                  </div>
                </div>
              </div>
            </div>
            <!-- Sum check -->
            <div class="mt-3 flex items-center justify-end gap-2">
              <span class="text-xs text-white">Total:</span>
              <span :class="['text-xs font-bold', modalTrancheSum === 100 ? 'text-mp-success' : 'text-mp-danger']">
                {{ modalTrancheSum }}%
                <span v-if="modalTrancheSum !== 100" class="font-normal ml-1">(must equal 100%)</span>
              </span>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 mt-5 pt-4 border-t border-mp-border">
            <button type="button" @click="paymentModal.open = false"
              class="px-4 py-2 text-sm text-white hover:text-white transition-colors">Cancel</button>
            <button type="button" @click="savePaymentModal"
              :disabled="paymentModal.draft.preset === 'custom' && modalTrancheSum !== 100"
              class="px-5 py-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40">
              Apply Policy
            </button>
          </div>
        </div>
      </div>
    </Teleport>
    <!-- ── OVERHEAD ALLOCATION MODAL ──────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="ohAllocModal.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4"
        @click.self="ohAllocModal.open = false">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden">

          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
            <div>
              <h3 class="text-white font-semibold text-lg">Product Allocation</h3>
              <p class="text-white text-xs mt-0.5">Distribute this overhead cost across manufacturing products</p>
            </div>
            <button type="button" @click="ohAllocModal.open = false" class="text-white hover:text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Mode toggle -->
          <div class="px-6 py-3 border-b border-mp-border flex items-center gap-3">
            <button type="button" @click="setOhAllocMode('revenue')"
              :class="['flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border', ohAllocModal.mode === 'revenue' ? 'bg-mp-success/40 border-mp-success/60 text-mp-success' : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page']">
              <span :class="['w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0', ohAllocModal.mode === 'revenue' ? 'border-mp-success' : 'border-mp-border']">
                <span v-if="ohAllocModal.mode === 'revenue'" class="w-2 h-2 rounded-full bg-mp-success"></span>
              </span>
              Equal Split (Revenue %)
            </button>
            <button type="button" @click="setOhAllocMode('manual')"
              :class="['flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border', ohAllocModal.mode === 'manual' ? 'bg-mp-teal-subtle/40 border-mp-teal/60 text-white' : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page']">
              <span :class="['w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0', ohAllocModal.mode === 'manual' ? 'border-mp-teal' : 'border-mp-border']">
                <span v-if="ohAllocModal.mode === 'manual'" class="w-2 h-2 rounded-full bg-mp-teal"></span>
              </span>
              Manual
            </button>
          </div>

          <div v-if="mfgProducts.length === 0" class="p-10 text-center text-white text-sm">No manufacturing products defined.</div>
          <div v-else class="px-6 pt-4 pb-2">
            <div class="grid grid-cols-2 gap-4 mb-2 px-1">
              <span class="text-xs font-semibold text-white uppercase tracking-widest">Product</span>
              <span class="text-xs font-semibold text-white uppercase tracking-widest text-right">Allocation %</span>
            </div>
            <div class="space-y-2 max-h-64 overflow-y-auto">
              <div v-for="(row, ri) in ohAllocModal.rows" :key="ri"
                class="grid grid-cols-2 gap-4 items-center border-b border-mp-border pb-2">
                <div class="bg-mp-teal-subtle/20 border border-mp-teal/40 rounded-lg px-3 py-2 text-white text-sm font-medium">
                  {{ row.product_name }}
                </div>
                <div class="flex justify-end">
                  <div class="flex items-center gap-1">
                    <input type="number" min="0" max="100" step="0.01"
                      v-model.number="row.pct"
                      :readonly="ohAllocModal.mode === 'revenue'"
                      :class="['w-24 rounded-lg px-3 py-1.5 text-sm text-right border focus:outline-none focus:ring-1 focus:ring-mp-teal', ohAllocModal.mode === 'revenue' ? 'bg-mp-card-hover border-mp-border text-white cursor-not-allowed' : 'bg-mp-card-hover border-mp-border text-white']"/>
                    <span class="text-white text-xs">%</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-3 flex items-center justify-between border-t border-mp-border pt-3 pb-2">
              <span class="text-xs text-white">Total:</span>
              <span :class="['text-sm font-bold', Math.abs(ohAllocTotal - 100) < 0.1 ? 'text-mp-success' : 'text-mp-danger']">
                {{ ohAllocTotal.toFixed(1) }}%
                <span v-if="Math.abs(ohAllocTotal - 100) >= 0.1" class="text-xs font-normal ml-1">(must equal 100%)</span>
              </span>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-mp-border">
            <button type="button" @click="ohAllocModal.open = false"
              class="px-4 py-2 text-sm text-white hover:text-white transition-colors">Cancel</button>
            <button type="button" @click="saveOhAlloc"
              :disabled="mfgProducts.length > 0 && Math.abs(ohAllocTotal - 100) >= 0.1"
              class="px-5 py-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40">
              Save Allocation
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StudyWriteup from '@/Components/StudyWriteup.vue'

// ── Props ─────────────────────────────────────────────────────
const props = defineProps({
  company:  { type: Object, required: true },
  study:    { type: Object, required: true },
  products: { type: Array,  default: () => [] },
  cogsData: { type: Array,  default: () => [] },
  writeupText: { type: String, default: '' },
})

const wizardSteps = ['Setup', 'Sales Projection', 'COGS', 'Manpower', 'Expenses', 'Fixed Assets', 'Opening Balance', 'Results']

// ── Payment policy presets ────────────────────────────────────
const paymentPresets = [
  { value: 'cash',        label: 'Cash (immediate — 0 days)' },
  { value: 'quarterly',   label: 'Quarterly (90 days)' },
  { value: 'semi_annual', label: 'Semi-Annual (180 days)' },
  { value: 'annual',      label: 'Annual (360 days)' },
  { value: 'custom',      label: 'Custom (set manually)' },
]

function defaultPolicy(preset = 'cash') {
  const map = { cash: 0, quarterly: 90, semi_annual: 180, annual: 360 }
  const days = map[preset] ?? 0
  return {
    preset,
    tranches: [
      { pct: preset !== 'custom' ? 100 : 0, days },
      { pct: 0, days: 0 },
      { pct: 0, days: 0 },
    ],
  }
}

function hydratePolicy(saved) {
  if (!saved) return defaultPolicy('cash')
  return {
    preset:   saved.preset ?? 'cash',
    tranches: saved.tranches ?? [{ pct: 100, days: 0 }, { pct: 0, days: 0 }, { pct: 0, days: 0 }],
  }
}

// Mutates the policy object to apply a preset
function applyPreset(policy) {
  const map = { cash: 0, quarterly: 90, semi_annual: 180, annual: 360 }
  if (policy.preset !== 'custom') {
    const days = map[policy.preset] ?? 0
    policy.tranches[0].pct  = 100
    policy.tranches[0].days = days
    policy.tranches[1].pct  = 0
    policy.tranches[1].days = 0
    policy.tranches[2].pct  = 0
    policy.tranches[2].days = 0
  }
}

function policyTotal(policy) {
  return (policy?.tranches ?? []).reduce((s, t) => s + (Number(t.pct) || 0), 0)
}

// ── Default overhead ──────────────────────────────────────────
function defaultOverhead() {
  return {
    name: '', method: 'fixed_monthly',
    start_date: props.study.start_date || '', end_date: '',
    amount: 0, annual_increase_pct: 0,
    pct_revenue: 0, annual_change_pct: 0,
    payment_policy: defaultPolicy('cash'),
    product_allocation: [],
    alloc_mode: 'revenue',
    apply_to_products: [],
    _showPayment: false,
    _showProductsDropdown: false,
  }
}

// ── Manufacturing products list (used by overhead dropdowns & alloc modal) ──
const mfgProducts = computed(() =>
  props.products.filter(p => p.nature === 'manufacturing')
)

// Label for the "Apply to Products" multi-select dropdown
function ohSelectedProductsLabel(oh) {
  const selected = oh.apply_to_products ?? []
  if (selected.length === 0) return 'All Manufacturing Products'
  if (selected.length === mfgProducts.value.length) return 'All Manufacturing Products'
  return selected.join(', ')
}

// Readable policy label (without modal — used on the Set button)
function policyLabel2(policy) {
  if (!policy) return 'Cash'
  const map = { cash: 'Cash', quarterly: 'Quarterly', semi_annual: 'Semi-Annual', annual: 'Annual', custom: 'Custom' }
  return map[policy.preset] ?? 'Cash'
}

// ── Overhead Allocation Modal ─────────────────────────────────
const ohAllocModal = reactive({
  open:    false,
  ohi:     null,
  mode:    'revenue',
  rows:    [],
})

const ohAllocTotal = computed(() =>
  ohAllocModal.rows.reduce((s, r) => s + (Number(r.pct) || 0), 0)
)

function applyOhRevenueMode() {
  const n = ohAllocModal.rows.length
  if (n === 0) return
  const base      = parseFloat((100 / n).toFixed(2))
  const remainder = parseFloat((100 - base * (n - 1)).toFixed(2))
  ohAllocModal.rows.forEach((r, i) => { r.pct = i === n - 1 ? remainder : base })
}

function setOhAllocMode(mode) {
  ohAllocModal.mode = mode
  if (mode === 'revenue') applyOhRevenueMode()
}

function openOhAllocModal(ohi) {
  ohAllocModal.ohi  = ohi
  const oh          = sharedOverheads[ohi]
  const existing    = oh.product_allocation || []
  ohAllocModal.mode = oh.alloc_mode || 'revenue'
  ohAllocModal.rows = mfgProducts.value.map(p => {
    const found = existing.find(e => e.product_name === p.name)
    return { product_name: p.name, pct: found ? found.pct : 0 }
  })
  if (existing.length === 0) applyOhRevenueMode()
  ohAllocModal.open = true
}

function saveOhAlloc() {
  const oh = sharedOverheads[ohAllocModal.ohi]
  oh.product_allocation = ohAllocModal.rows.map(r => ({ ...r }))
  oh.alloc_mode         = ohAllocModal.mode
  ohAllocModal.open     = false
}

// ── Build form per product ────────────────────────────────────
function defaultForProduct(product, pi) {
  const ex  = props.cogsData[pi] || null
  const rms = props.study.raw_materials || []

  if (product.nature === 'manufacturing') {
    return {
      product_name: product.name, nature: product.nature,
      rm_method: ex?.rm_method ?? 'bom',
      raw_materials: rms.map((rm, rmi) => ({
        name: rm.name, unit: rm.unit || '',
        cost_per_unit:       ex?.raw_materials?.[rmi]?.cost_per_unit      ?? 0,
        qty_per_unit:        ex?.raw_materials?.[rmi]?.qty_per_unit       ?? 1,
        annual_increase_pct: ex?.raw_materials?.[rmi]?.annual_increase_pct ?? 0,
        beg_inventory_qty:   ex?.raw_materials?.[rmi]?.beg_inventory_qty   ?? 0,
        beg_inventory_value: ex?.raw_materials?.[rmi]?.beg_inventory_value ?? 0,
        pct_selling:         ex?.raw_materials?.[rmi]?.pct_selling         ?? 0,
        annual_change_pct:   ex?.raw_materials?.[rmi]?.annual_change_pct   ?? 0,
        payment_policy:      hydratePolicy(ex?.raw_materials?.[rmi]?.payment_policy),
        _showPayment:        false,
      })),
      overheads: [],  // kept for backward-compat shape; shared overheads are in sharedOverheads
    }
  }

  if (product.nature === 'trading') {
    return {
      product_name: product.name, nature: product.nature,
      unit_purchase_cost:        ex?.unit_purchase_cost        ?? 0,
      annual_cost_increase_pct:  ex?.annual_cost_increase_pct  ?? 0,
      inventory_days:            ex?.inventory_days            ?? 30,
      beginning_inventory_units: ex?.beginning_inventory_units ?? 0,
      beginning_inventory_value: ex?.beginning_inventory_value ?? 0,
      purchase_payment_policy:   hydratePolicy(ex?.purchase_payment_policy),
    }
  }

  if (product.nature === 'service') {
    return {
      product_name: product.name, nature: product.nature,
      service_method:          ex?.service_method          ?? 'pct_revenue',
      service_pct:             ex?.service_pct             ?? 0,
      service_annual_change:   ex?.service_annual_change   ?? 0,
      service_start_date:      ex?.service_start_date      ?? (props.study.start_date || ''),
      service_end_date:        ex?.service_end_date        ?? '',
      service_amount:          ex?.service_amount          ?? 0,
      service_annual_increase: ex?.service_annual_increase ?? 0,
      service_payment_policy:  hydratePolicy(ex?.service_payment_policy),
    }
  }

  return { product_name: product.name, nature: product.nature }
}

const cogsForm = reactive(props.products.map((p, i) => defaultForProduct(p, i)))

// ── Shared overheads (ONE list for ALL manufacturing products) ────────
// Load from the first manufacturing product's saved overheads (backward-compat)
const firstMfgData = props.cogsData.find(d => d?.nature === 'manufacturing') || null
const sharedOverheads = reactive(
  (firstMfgData?.overheads ?? []).map(oh => ({
    ...defaultOverhead(), ...oh,
    payment_policy: hydratePolicy(oh?.payment_policy),
    product_allocation: oh?.product_allocation ?? [],
    alloc_mode: oh?.alloc_mode ?? 'revenue',
    apply_to_products: oh?.apply_to_products ?? [],
    _showPayment: false,
    _showProductsDropdown: false,
  }))
)

// ── Overhead helpers ──────────────────────────────────────────
function addOverhead()      { sharedOverheads.push(defaultOverhead()) }
function removeOverhead(ohi) { sharedOverheads.splice(ohi, 1) }

// ── Computed helpers ──────────────────────────────────────────
function autoBegVal(rm) {
  const v = (Number(rm.beg_inventory_qty) || 0) * (Number(rm.cost_per_unit) || 0)
  return v > 0 ? `Auto: ${fmtNum(v)}` : ''
}
function bomTotalPerUnit(pi) {
  return (cogsForm[pi]?.raw_materials || []).reduce(
    (s, rm) => s + (Number(rm.cost_per_unit) || 0) * (Number(rm.qty_per_unit) || 0), 0)
}
function pctTotalSelling(pi) {
  return (cogsForm[pi]?.raw_materials || []).reduce((s, rm) => s + (Number(rm.pct_selling) || 0), 0)
}
function fmtNum(n) {
  return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function natureBadge(nature) {
  return { manufacturing: 'bg-mp-teal-subtle/60 text-white border border-mp-teal/50', trading: 'bg-mp-gold/60 text-white border border-mp-gold/50', service: 'bg-mp-teal-subtle/60 text-white border border-mp-teal/50' }[nature] || 'bg-mp-card-hover text-white'
}
function natureLabel(nature) {
  return { manufacturing: 'Manufacturing', trading: 'Trading', service: 'Service' }[nature] || nature
}

// ── Submit ────────────────────────────────────────────────────
const processing = ref(false)

async function submitForm(button) {
  processing.value = true
  try {
    // Strip UI-only fields from sharedOverheads, then inject into every manufacturing product
    const cleanOverheads = sharedOverheads.map(({ _showPayment, _showProductsDropdown, ...oh }) => oh)
    const payload = cogsForm.map(p => {
      if (p.nature !== 'manufacturing') return p
      return {
        ...p,
        raw_materials: p.raw_materials.map(({ _showPayment, ...rm }) => rm),
        overheads: cleanOverheads,
      }
    })
    const xsrf = document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN='))?.split('=')[1]
    const res = await fetch(
      `/portfolio-companies/${props.company.id}/financial-studies/${props.study.id}/cogs`,
      {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '' },
        body: JSON.stringify({ cogs_data: payload, submit_button: button }),
      }
    )
    const json = await res.json()
    if (json.success && json.redirect) router.visit(json.redirect)
  } catch (e) {
    console.error(e)
  } finally {
    processing.value = false
  }
}

// ── Payment Policy Modal (for Trading purchase policy) ────────
const paymentPresetList = [
  { key: 'cash',        label: 'Cash' },
  { key: 'quarterly',   label: 'Quarterly' },
  { key: 'semi_annual', label: 'Semi-Annual' },
  { key: 'annual',      label: 'Annual' },
  { key: 'custom',      label: 'Custom' },
]

const paymentModal = reactive({
  open:      false,
  label:     '',
  pi:        null,
  field:     '',
  draft:     { preset: 'cash', tranches: [{ pct: 100, days: 0 }, { pct: 0, days: 0 }, { pct: 0, days: 0 }] },
})

const modalTrancheSum = computed(() =>
  paymentModal.draft.tranches.reduce((s, t) => s + (Number(t.pct) || 0), 0)
)

function openPaymentModal(pi, field, label) {
  paymentModal.pi    = pi
  paymentModal.field = field
  paymentModal.label = label
  paymentModal.draft = JSON.parse(JSON.stringify(cogsForm[pi][field] ?? defaultPolicy('cash')))
  paymentModal.open  = true
}

function selectModalPreset(preset) {
  paymentModal.draft.preset   = preset
  paymentModal.draft.tranches = (() => {
    const map = { cash: 0, quarterly: 90, semi_annual: 180, annual: 360 }
    if (preset === 'custom') return [{ pct: 50, days: 30 }, { pct: 30, days: 60 }, { pct: 20, days: 90 }]
    const days = map[preset] ?? 0
    return [{ pct: 100, days }, { pct: 0, days: 0 }, { pct: 0, days: 0 }]
  })()
}

function savePaymentModal() {
  cogsForm[paymentModal.pi][paymentModal.field] = JSON.parse(JSON.stringify(paymentModal.draft))
  paymentModal.open = false
}

function policyLabel(policy) {
  if (!policy) return 'Cash'
  const map = { cash: 'Cash', quarterly: 'Quarterly', semi_annual: 'Semi-Annual', annual: 'Annual', custom: 'Custom' }
  return map[policy.preset] ?? 'Cash'
}

// ── Write-up summary data ─────────────────────────────────────

const writeupSummaryColumns = [
  { key: 'product', label: 'Product',   align: 'left' },
  { key: 'nature',  label: 'Nature',    align: 'left' },
  { key: 'method',  label: 'RM Method / Cost Method', align: 'left' },
]

const writeupSummaryRows = computed(() =>
  props.products.map((p, pi) => {
    const f = cogsForm[pi]
    let method = '—'
    if (p.nature === 'manufacturing') method = f.rm_method === 'bom' ? 'Bill of Materials' : '% of Selling Price'
    if (p.nature === 'trading')       method = `Unit Cost: ${fmtNum(f.unit_purchase_cost ?? 0)}`
    if (p.nature === 'service')       method = f.service_method === 'pct_revenue' ? `% Revenue: ${f.service_pct ?? 0}%` : `Fixed Monthly: ${fmtNum(f.service_amount ?? 0)}`
    return { product: p.name, nature: natureLabel(p.nature), method }
  })
)

const writeupSummaryTotals = computed(() => ({
  product: `${props.products.length} products`,
  nature:  '',
  method:  '',
}))

const writeupCategoryBreakdown = computed(() => {
  const colors = ['#00b4c8', '#f59e0b', '#14b8a6', '#c9a84c']
  const counts = { manufacturing: 0, trading: 0, service: 0 }
  props.products.forEach(p => { if (counts[p.nature] !== undefined) counts[p.nature]++ })
  const total = props.products.length || 1
  return [
    { label: 'Manufacturing', value: `${counts.manufacturing} (${((counts.manufacturing/total)*100).toFixed(0)}%)`, color: colors[0] },
    { label: 'Trading',       value: `${counts.trading} (${((counts.trading/total)*100).toFixed(0)}%)`,       color: colors[1] },
    { label: 'Service',       value: `${counts.service} (${((counts.service/total)*100).toFixed(0)}%)`,       color: colors[2] },
  ]
})

</script>