<template>
  <Head :title="`Manpower — ${study.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ── HEADER ── -->
      <div class="bg-mp-card border-b border-mp-border">
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
              <div :class="[
                'flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                i === 3 ? 'bg-mp-gold-dark text-white' : 'text-white'
              ]">
                <span :class="[
                  'w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0',
                  i === 3 ? 'bg-white/20 text-white' : 'bg-mp-card-hover text-white'
                ]">{{ i + 1 }}</span>
                {{ step }}
              </div>
              <svg v-if="i < wizardSteps.length - 1" class="w-4 h-4 text-white mx-1 flex-shrink-0"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </div>

          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white">Step 4 — Manpower Plan</h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }} · {{ study.name }}</p>
            </div>
            <div class="flex items-center gap-3">
              <!-- Totals summary pill -->
              <div class="hidden md:flex items-center gap-4 bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2 text-xs">
                <span class="text-white">Total Headcount (Y1 avg): <span class="text-white font-semibold">{{ totalHeadcountY1 }}</span></span>
                <span class="text-white">Total Monthly Cost (Y1 avg): <span class="text-mp-success font-semibold">{{ fmtNumber(totalMonthlyCostY1) }}</span></span>
              </div>
             <Link :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/cogs`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                ← Back
              </Link>
              <StudyWriteup
                :company-id="company.id"
                :study-id="study.id"
                :study-name="study.name"
                step-key="manpower"
                step-label="Manpower Plan"
                step-icon="👷"
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
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Save & Exit
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

      <!-- ── CONTENT ── -->
      <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- Department tabs -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1">
          <button v-for="dept in departments" :key="dept.key"
            type="button" @click="activeDept = dept.key"
            :class="[
              'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors flex-shrink-0',
              activeDept === dept.key
                ? dept.activeClass
                : 'bg-mp-card-hover text-white hover:bg-mp-page hover:text-white'
            ]">
            <span>{{ dept.icon }}</span>
            {{ dept.label }}
            <span :class="[
              'ml-1 px-1.5 py-0.5 rounded-full text-xs font-bold',
              activeDept === dept.key ? 'bg-white/20' : 'bg-mp-page text-white'
            ]">
              {{ rowsByDept(dept.key).length }}
            </span>
          </button>
        </div>

        <!-- Department card — only render the active one -->
        <template v-for="dept in departments" :key="dept.key">
        <div v-if="activeDept === dept.key"
          class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">

          <!-- Card header -->
          <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest">{{ dept.label }}</p>
              <p class="text-white text-xs mt-0.5">{{ dept.description }}</p>
            </div>
            <button type="button" @click="addRow(dept.key)"
              class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Add Position
            </button>
          </div>

          <!-- Empty state -->
          <div v-if="rowsByDept(dept.key).length === 0"
            class="p-8 text-center text-white text-sm">
            No positions yet. Click "Add Position" to begin.
          </div>

          <!-- Rows table -->
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b border-mp-border bg-mp-card sticky top-0 z-10">
                <tr>
                  <th class="text-left text-white font-medium py-3 px-4 whitespace-nowrap min-w-[300px]">Job Title</th>
                  <th class="text-center text-white font-medium py-3 px-2 whitespace-nowrap min-w-[110px]">Net Salary<br/><span class="text-white text-xs normal-case">({{ study.study_currency }})</span></th>
                  <th class="text-center text-white font-medium py-3 px-2 whitespace-nowrap min-w-[90px]">Salary<br/>Taxes %</th>
                  <th class="text-center text-white font-medium py-3 px-2 whitespace-nowrap min-w-[90px]">Social Ins.<br/>%</th>
                  <th class="text-center text-white font-medium py-3 px-2 whitespace-nowrap min-w-[90px]">Annual<br/>Increase %</th>
                  <th class="text-center text-white font-medium py-3 px-2 whitespace-nowrap">Gross/month<br/><span class="text-white text-xs normal-case">(auto)</span></th>
                  <!-- Y1 months -->
                  <th v-for="(lbl, mi) in y1Labels" :key="'y1-'+mi"
                    class="text-center text-white/70 font-medium py-3 px-1 whitespace-nowrap min-w-[52px] text-xs">
                    {{ lbl }}
                  </th>
                  <!-- Y2 months -->
                  <th v-for="(lbl, mi) in y2Labels" :key="'y2-'+mi"
                    class="text-center text-white/70 font-medium py-3 px-1 whitespace-nowrap min-w-[52px] text-xs">
                    {{ lbl }}
                  </th>
                  <!-- Y3+ annual -->
                  <th v-for="yr in annualYears" :key="'y-'+yr"
                    class="text-center text-mp-success/70 font-medium py-3 px-1 whitespace-nowrap min-w-[64px] text-xs">
                    {{ yr }}
                  </th>
                  <th class="py-3 px-2 w-8"></th>
                  <th class="py-3 px-2 w-8"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, ri) in rowsByDept(dept.key)" :key="row._id"
                  class="border-b border-mp-border/60 hover:bg-mp-card-hover/20 transition-colors">
                  <!-- Title -->
                  <td class="py-2 px-4">
                    <input type="text" v-model="row.title" placeholder="e.g. Production Worker"
                      class="w-full bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-xs focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                  </td>
                  <!-- Net Salary -->
                  <td class="py-2 px-2">
                    <input type="number" min="0" step="0.01" v-model.number="row.net_salary"
                      class="w-full bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-xs focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                  </td>
                  <!-- Salary Taxes % -->
                  <td class="py-2 px-2">
                    <div class="flex items-center gap-0.5">
                      <input type="number" min="0" max="100" step="0.1" v-model.number="row.salary_taxes_pct"
                        class="w-full bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-xs focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                      <span class="text-white text-xs">%</span>
                    </div>
                  </td>
                  <!-- Social Insurance % -->
                  <td class="py-2 px-2">
                    <div class="flex items-center gap-0.5">
                      <input type="number" min="0" max="100" step="0.1" v-model.number="row.social_insurance_pct"
                        class="w-full bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-xs focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                      <span class="text-white text-xs">%</span>
                    </div>
                  </td>
                  <!-- Annual Increase % -->
                  <td class="py-2 px-2">
                    <div class="flex items-center gap-0.5">
                      <input type="number" min="0" max="100" step="0.1" v-model.number="row.annual_increase_pct"
                        class="w-full bg-mp-card-hover border border-mp-border rounded px-2 py-1.5 text-white text-center text-xs focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                      <span class="text-white text-xs">%</span>
                    </div>
                  </td>
                  <!-- Gross/month auto -->
                  <td class="py-2 px-2 text-center">
                    <span class="text-mp-success font-semibold text-xs">
                      {{ fmtNumber(grossPerPerson(row)) }}
                    </span>
                  </td>
                  <!-- Y1 month headcount -->
                  <td v-for="(lbl, mi) in y1Labels" :key="'y1c-'+mi" class="py-2 px-1">
                    <input type="number" min="0" step="1" v-model.number="row.y1_count[mi]"
                      class="w-full bg-mp-card-hover/80 border border-mp-teal/40 rounded px-1 py-1.5 text-white text-center text-xs focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                    <button v-if="mi < y1Labels.length - 1"
                      type="button" @click="copyY1Right(row, mi)"
                      title="Copy this value to all months →"
                      class="w-full flex items-center justify-center mt-0.5 text-white hover:text-white transition-colors opacity-50 hover:opacity-100">
                      <span class="text-xs leading-none tracking-tighter">···</span>
                    </button>
                    <div v-else class="mt-0.5 h-4"></div>
                  </td>
                  <!-- Y2 month headcount -->
                  <td v-for="(lbl, mi) in y2Labels" :key="'y2c-'+mi" class="py-2 px-1">
                    <input type="number" min="0" step="1" v-model.number="row.y2_count[mi]"
                      class="w-full bg-mp-card-hover/80 border border-mp-gold/40 rounded px-1 py-1.5 text-white text-center text-xs focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                    <button v-if="mi < y2Labels.length - 1"
                      type="button" @click="copyY2Right(row, mi)"
                      title="Copy this value to all months →"
                      class="w-full flex items-center justify-center mt-0.5 text-white hover:text-white transition-colors opacity-50 hover:opacity-100">
                      <span class="text-xs leading-none tracking-tighter">···</span>
                    </button>
                    <div v-else class="mt-0.5 h-4"></div>
                  </td>
                  <!-- Y3+ annual headcount -->
                  <td v-for="(yr, yi) in annualYears" :key="'yc-'+yr" class="py-2 px-1">
                    <input type="number" min="0" step="1" v-model.number="row.annual_count[yi]"
                      class="w-full bg-mp-card-hover/80 border border-mp-success/40 rounded px-1 py-1.5 text-white text-center text-xs focus:outline-none focus:ring-1 focus:ring-mp-success"/>
                    <button v-if="yi < annualYears.length - 1"
                      type="button" @click="copyAnnualRight(row, yi)"
                      title="Copy this value to all years →"
                      class="w-full flex items-center justify-center mt-0.5 text-white hover:text-white transition-colors opacity-50 hover:opacity-100">
                      <span class="text-xs leading-none tracking-tighter">···</span>
                    </button>
                    <div v-else class="mt-0.5 h-4"></div>
                  </td>
                  <!-- Allocate (Direct & Indirect Labor only) -->
                  <td class="py-2 px-1">
                    <button v-if="dept.key === 'direct_labor' || dept.key === 'indirect_labor'"
                      type="button" @click="openAllocModal(row)"
                      :title="row.product_allocation.length > 0 ? 'Edit product allocation' : 'Allocate to products'"
                      :class="[
                        'flex items-center justify-center w-7 h-7 rounded-lg border transition-colors',
                        row.product_allocation.length > 0
                          ? 'bg-mp-teal-subtle/40 border-mp-teal/60 text-white'
                          : 'bg-mp-card-hover border-mp-border text-white hover:text-white hover:border-mp-teal'
                      ]">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                      </svg>
                    </button>
                    <div v-else class="w-7"></div>
                  </td>
                  <!-- Delete -->
                  <td class="py-2 px-2">
                    <button type="button" @click="removeRow(row._id)"
                      class="text-white hover:text-mp-danger transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </td>
                </tr>

                <!-- Department totals row -->
                <tr class="bg-mp-card-hover/40 border-t-2 border-mp-border">
                  <td colspan="5" class="py-2 px-4 text-xs font-semibold text-white uppercase tracking-widest">
                    {{ dept.label }} Totals
                  </td>
                  <td class="py-2 px-2 text-center text-xs font-semibold text-mp-success">
                    {{ fmtNumber(deptTotalCostY1(dept.key)) }}
                  </td>
                  <td v-for="(lbl, mi) in y1Labels" :key="'tot-y1-'+mi"
                    class="py-2 px-1 text-center text-xs font-semibold text-white">
                    {{ deptCountMonth(dept.key, 'y1', mi) }}
                  </td>
                  <td v-for="(lbl, mi) in y2Labels" :key="'tot-y2-'+mi"
                    class="py-2 px-1 text-center text-xs font-semibold text-white">
                    {{ deptCountMonth(dept.key, 'y2', mi) }}
                  </td>
                  <td v-for="(yr, yi) in annualYears" :key="'tot-ya-'+yr"
                    class="py-2 px-1 text-center text-xs font-semibold text-mp-success">
                    {{ deptCountAnnual(dept.key, yi) }}
                  </td>
                  <td></td>
                  <td></td>
                </tr>
              </tbody>
            </table>
          </div>

        </div><!-- end dept card -->
        </template>

        <!-- Grand Total Summary card -->
        <div class="bg-mp-card border border-mp-border rounded-xl p-5">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Grand Total Summary</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div v-for="dept in departments" :key="dept.key"
              class="bg-mp-card-hover rounded-lg p-3">
              <p class="text-xs text-white mb-1">{{ dept.label }}</p>
              <p class="text-white font-semibold text-sm">{{ rowsByDept(dept.key).length }} positions</p>
              <p class="text-mp-success text-xs mt-0.5">{{ fmtNumber(deptTotalCostY1(dept.key)) }}/mo (Y1 avg)</p>
            </div>
          </div>
          <div class="mt-4 pt-4 border-t border-mp-border flex items-center justify-between">
            <span class="text-white text-sm font-medium">Total Annual Manpower Cost (Year 1)</span>
            <span class="text-mp-success font-bold text-lg">{{ fmtNumber(totalMonthlyCostY1 * 12) }}</span>
          </div>
        </div>

      </div><!-- end content -->
    </div>
    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  MODAL: Products Allocation (Direct & Indirect Labor)       -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="allocModal.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4"
        @click.self="allocModal.open = false">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden">

          <!-- Modal header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
            <div>
              <h3 class="text-white font-semibold text-lg">Allocate Labor to Products</h3>
              <p class="text-white text-xs mt-0.5">
                Distribute this position's cost across products (used in COGS calculation)
              </p>
            </div>
            <button type="button" @click="allocModal.open = false" class="text-white hover:text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Mode toggle -->
          <div class="px-6 py-3 border-b border-mp-border flex items-center gap-3">
            <button type="button" @click="setAllocMode('revenue')"
              :class="[
                'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border',
                allocModal.mode === 'revenue'
                  ? 'bg-mp-success/40 border-mp-success/60 text-mp-success'
                  : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page'
              ]">
              <span :class="['w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0',
                allocModal.mode === 'revenue' ? 'border-mp-success' : 'border-mp-border']">
                <span v-if="allocModal.mode === 'revenue'" class="w-2 h-2 rounded-full bg-mp-success"></span>
              </span>
              Equal split (by Revenue %)
            </button>
            <button type="button" @click="setAllocMode('manual')"
              :class="[
                'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border',
                allocModal.mode === 'manual'
                  ? 'bg-mp-teal-subtle/40 border-mp-teal/60 text-white'
                  : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page'
              ]">
              <span :class="['w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0',
                allocModal.mode === 'manual' ? 'border-mp-teal' : 'border-mp-border']">
                <span v-if="allocModal.mode === 'manual'" class="w-2 h-2 rounded-full bg-mp-teal"></span>
              </span>
              Manual
            </button>
          </div>

          <!-- No products warning -->
          <div v-if="props.products.length === 0"
            class="p-10 text-center text-white text-sm">No products defined in Step 1.</div>

          <!-- Table -->
          <div v-else class="px-6 pt-4 pb-2">
            <div class="grid grid-cols-2 gap-4 mb-2 px-1">
              <span class="text-xs font-semibold text-white uppercase tracking-widest">Product</span>
              <span class="text-xs font-semibold text-white uppercase tracking-widest text-right">Perc. %</span>
            </div>
            <div class="space-y-2 max-h-64 overflow-y-auto">
              <div v-for="(row, pi) in allocModal.rows" :key="pi"
                class="grid grid-cols-2 gap-4 items-center border-b border-mp-border pb-2">
                <div class="bg-mp-teal-subtle/20 border border-mp-teal/40 rounded-lg px-3 py-2 text-white text-sm font-medium">
                  {{ row.product_name }}
                </div>
                <div class="flex justify-end">
                  <input type="number" min="0" max="100" step="0.01"
                    v-model.number="row.pct"
                    :readonly="allocModal.mode === 'revenue'"
                    :class="[
                      'w-28 border rounded-lg px-3 py-2 text-sm text-right focus:outline-none focus:ring-1',
                      allocModal.mode === 'revenue'
                        ? 'bg-mp-teal-subtle/20 border-mp-teal/40 text-white cursor-not-allowed'
                        : 'bg-mp-card-hover border-mp-border text-white focus:ring-mp-teal'
                    ]"/>
                </div>
              </div>
            </div>
            <!-- Total -->
            <div class="grid grid-cols-2 gap-4 items-center mt-3 pt-3 border-t border-mp-border">
              <div class="bg-mp-teal-subtle/20 border border-mp-teal/40 rounded-lg px-3 py-2 text-white text-sm font-bold">Total</div>
              <div class="flex justify-end">
                <div :class="[
                  'w-28 border rounded-lg px-3 py-2 text-sm text-right font-bold',
                  allocTotal === 100
                    ? 'bg-mp-success/30 border-mp-success/50 text-mp-success'
                    : 'bg-mp-danger/30 border-mp-danger/50 text-mp-danger'
                ]">{{ allocTotal.toFixed(2) }}</div>
              </div>
            </div>
            <div v-if="allocTotal !== 100" class="mt-2 flex items-center gap-2">
              <div class="w-2 h-2 rounded-full bg-mp-danger flex-shrink-0"></div>
              <span class="text-xs text-mp-danger">Must equal 100% — currently {{ allocTotal.toFixed(2) }}%</span>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-mp-border mt-2">
            <button type="button" @click="allocModal.open = false"
              class="px-4 py-2 text-sm text-white hover:text-white transition-colors">Cancel</button>
            <button type="button" @click="saveAllocModal"
              :disabled="props.products.length > 0 && allocTotal !== 100"
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
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StudyWriteup from '@/Components/StudyWriteup.vue'

// ── Props ──────────────────────────────────────────────────────────────
const props = defineProps({
  company:      { type: Object, required: true },
  study:        { type: Object, required: true },
  products:     { type: Array,  default: () => [] },
  manpowerData: { type: Array,  default: () => [] },
  writeupText: { type: String, default: '' },
})

// ── Wizard steps ───────────────────────────────────────────────────────
const wizardSteps = [
  'Setup', 'Sales Projection', 'COGS', 'Manpower',
  'Expenses', 'Fixed Assets', 'Opening Balance', 'Results'
]

// ── Department config ─────────────────────────────────────────────────
const departments = [
  {
    key:         'direct_labor',
    label:       'Direct Labor',
    icon:        '🏭',
    description: 'Workers directly involved in production / service delivery',
    activeClass: 'bg-mp-teal text-white',
  },
  {
    key:         'indirect_labor',
    label:       'Indirect Labor',
    icon:        '⚙️',
    description: 'Factory / operations support staff not directly producing',
    activeClass: 'bg-mp-warning text-white',
  },
  {
    key:         'admin',
    label:       'Admin & Management',
    icon:        '🏢',
    description: 'Management, HR, finance, administrative roles',
    activeClass: 'bg-mp-gold-dark text-white',
  },
  {
    key:         'sales',
    label:       'Sales & Marketing',
    icon:        '📈',
    description: 'Sales team, marketing, business development',
    activeClass: 'bg-mp-success text-white',
  },
]

const activeDept = ref('direct_labor')

// ── Month/year label helpers ──────────────────────────────────────────
const durationYears = computed(() => Number(props.study.duration_years) || 3)

const y1Labels = computed(() => {
  if (!props.study.start_date) return Array.from({ length: 12 }, (_, i) => `M${i+1}`)
  const [y, m] = props.study.start_date.split('-').map(Number)
  return Array.from({ length: 12 }, (_, i) => {
    const d = new Date(y, m - 1 + i)
    return d.toLocaleDateString('en-US', { month: 'short', year: '2-digit' })
  })
})

const y2Labels = computed(() => {
  if (durationYears.value < 2) return []
  if (!props.study.start_date) return Array.from({ length: 12 }, (_, i) => `M${i+13}`)
  const [y, m] = props.study.start_date.split('-').map(Number)
  return Array.from({ length: 12 }, (_, i) => {
    const d = new Date(y, m - 1 + 12 + i)
    return d.toLocaleDateString('en-US', { month: 'short', year: '2-digit' })
  })
})

const annualYears = computed(() => {
  if (durationYears.value <= 2) return []
  const [y] = (props.study.start_date || '2026-01').split('-').map(Number)
  return Array.from({ length: durationYears.value - 2 }, (_, i) => y + 2 + i)
})

// ── Row state ─────────────────────────────────────────────────────────
let _nextId = 1
function makeRow(dept, existing = null) {
  return {
    _id:                  _nextId++,
    dept,
    title:                existing?.title                ?? '',
    net_salary:           existing?.net_salary           ?? 0,
    salary_taxes_pct:     existing?.salary_taxes_pct     ?? 0,
    social_insurance_pct: existing?.social_insurance_pct ?? 0,
    annual_increase_pct:  existing?.annual_increase_pct  ?? 0,
    y1_count:             existing?.y1_count             ?? Array(12).fill(0),
    y2_count:             existing?.y2_count             ?? Array(12).fill(0),
    annual_count:         existing?.annual_count         ?? Array(Math.max(0, durationYears.value - 2)).fill(0),
    product_allocation:   existing?.product_allocation   ?? [],
    alloc_mode:           existing?.alloc_mode           ?? 'revenue',
  }
}

// Hydrate from saved data
const rows = reactive(
  props.manpowerData.length > 0
    ? props.manpowerData.map(r => makeRow(r.dept, r))
    : []
)

// ── CRUD helpers ──────────────────────────────────────────────────────
function rowsByDept(dept) {
  return rows.filter(r => r.dept === dept)
}

// ── Copy-right helpers ─────────────────────────────────────────────────
function copyY1Right(row, fromIndex) {
  const val = row.y1_count[fromIndex] || 0
  for (let i = fromIndex + 1; i < row.y1_count.length; i++) {
    row.y1_count[i] = val
  }
}
function copyY2Right(row, fromIndex) {
  const val = row.y2_count[fromIndex] || 0
  for (let i = fromIndex + 1; i < row.y2_count.length; i++) {
    row.y2_count[i] = val
  }
}
function copyAnnualRight(row, fromIndex) {
  const val = row.annual_count[fromIndex] || 0
  for (let i = fromIndex + 1; i < row.annual_count.length; i++) {
    row.annual_count[i] = val
  }
}
function addRow(dept) {
  rows.push(makeRow(dept))
}
function removeRow(id) {
  const idx = rows.findIndex(r => r._id === id)
  if (idx !== -1) rows.splice(idx, 1)
}

// ── Calculations ──────────────────────────────────────────────────────
function grossPerPerson(row) {
  const s = Number(row.net_salary) || 0
  const t = Number(row.salary_taxes_pct) || 0
  const si = Number(row.social_insurance_pct) || 0
  return s * (1 + t / 100 + si / 100)
}

function deptTotalCostY1(dept) {
  return rowsByDept(dept).reduce((sum, row) => {
    const avgCount = row.y1_count.reduce((a, b) => a + b, 0) / 12
    return sum + grossPerPerson(row) * avgCount
  }, 0)
}

function deptCountMonth(dept, year, mi) {
  return rowsByDept(dept).reduce((sum, row) => {
    return sum + (year === 'y1' ? (row.y1_count[mi] || 0) : (row.y2_count[mi] || 0))
  }, 0)
}
function deptCountAnnual(dept, yi) {
  return rowsByDept(dept).reduce((sum, row) => sum + (row.annual_count[yi] || 0), 0)
}

const totalHeadcountY1 = computed(() =>
  rows.reduce((sum, row) => sum + (row.y1_count[0] || 0), 0)
)
const totalMonthlyCostY1 = computed(() =>
  departments.reduce((sum, d) => sum + deptTotalCostY1(d.key), 0)
)

function fmtNumber(n) {
  if (!n && n !== 0) return '—'
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

// ── Submit ─────────────────────────────────────────────────────────────
const processing = ref(false)

function apiFetch(url, body) {
  const xsrf = document.cookie.split('; ')
    .find(r => r.startsWith('XSRF-TOKEN='))?.split('=')[1]
  return fetch(url, {
    method:      'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'Accept':        'application/json',
      'X-XSRF-TOKEN':  xsrf ? decodeURIComponent(xsrf) : '',
    },
    body: JSON.stringify(body),
  })
}

async function submitForm(button) {
  processing.value = true
  try {
    const payload = rows.map(({ _id, ...rest }) => rest) // strip _id before save
    const res = await apiFetch(
      `/portfolio-companies/${props.company.id}/financial-studies/${props.study.id}/manpower`,
      { manpower_data: payload, submit_button: button }
    )
    const json = await res.json()
    if (json.success && json.redirect) {
      router.visit(json.redirect)
    }
  } catch (e) {
    console.error(e)
  } finally {
    processing.value = false
  }
}

// ── Products Allocation Modal (Direct & Indirect Labor only) ──────────
const allocModal = reactive({
  open:    false,
  rowId:   null,   // the _id of the manpower row being edited
  mode:    'revenue',
  rows:    [],
})

const allocTotal = computed(() =>
  allocModal.rows.reduce((s, r) => s + (Number(r.pct) || 0), 0)
)

function applyRevenueMode() {
  const n = allocModal.rows.length
  if (n === 0) return
  const base      = parseFloat((100 / n).toFixed(2))
  const remainder = parseFloat((100 - base * (n - 1)).toFixed(2))
  allocModal.rows.forEach((r, i) => { r.pct = i === n - 1 ? remainder : base })
}

function setAllocMode(mode) {
  allocModal.mode = mode
  if (mode === 'revenue') applyRevenueMode()
}

function openAllocModal(row) {
  allocModal.rowId = row._id
  const existing  = row.product_allocation || []
  const savedMode = row.alloc_mode || 'revenue'
  allocModal.rows = props.products.map(p => {
    const found = existing.find(e => e.product_name === p.name)
    return { product_name: p.name, pct: found ? found.pct : 0 }
  })
  allocModal.mode = savedMode
  if (existing.length === 0) applyRevenueMode()
  allocModal.open = true
}

function saveAllocModal() {
  const row = rows.find(r => r._id === allocModal.rowId)
  if (row) {
    row.product_allocation = allocModal.rows.map(r => ({ ...r }))
    row.alloc_mode         = allocModal.mode
  }
  allocModal.open = false
}

// ── Write-up summary data for the panel ───────────────────────────────
const writeupSummaryColumns = [
  { key: 'dept',  label: 'Department',        align: 'left'  },
  { key: 'count', label: 'Positions',          align: 'right' },
  { key: 'cost',  label: 'Monthly Cost (Y1 avg)', align: 'right', highlight: true, totalColor: '#10b981' },
]

const writeupSummaryRows = computed(() =>
  departments.map(d => ({
    dept:  d.label,
    count: rowsByDept(d.key).length,
    cost:  fmtNumber(deptTotalCostY1(d.key)),
  }))
)

const writeupSummaryTotals = computed(() => ({
  dept:  'TOTAL',
  count: rows.length,
  cost:  fmtNumber(totalMonthlyCostY1.value),
}))

const writeupCategoryBreakdown = computed(() =>
  departments.map((d, i) => {
    const colors = ['#00b4c8', '#f97316', '#7c3aed', '#10b981']
    const total  = totalMonthlyCostY1.value
    const cost   = deptTotalCostY1(d.key)
    const pct    = total > 0 ? ((cost / total) * 100).toFixed(1) : '0'
    return { label: d.label, value: pct + '%', color: colors[i] }
  })
)

</script>