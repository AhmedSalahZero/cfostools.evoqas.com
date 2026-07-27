<template>
  <Head :title="`Fixed Assets — ${study.name}`" />
  <AuthenticatedLayout>

    <div class="min-h-screen bg-gray-950 text-white">

      <!-- ── HEADER ── -->
      <div class="bg-gray-900 border-b border-gray-800">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-5">

          <Link :href="`/portfolio-companies/${company.id}/financial-studies`"
            class="flex items-center gap-2 text-sm text-gray-400 hover:text-white transition-colors mb-3 w-fit">
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
                i === 5 ? 'bg-orange-600 text-white' : 'text-gray-600'
              ]">
                <span :class="[
                  'w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0',
                  i === 5 ? 'bg-white/20 text-white' : 'bg-gray-800 text-gray-500'
                ]">{{ i + 1 }}</span>
                {{ step }}
              </div>
              <svg v-if="i < wizardSteps.length - 1" class="w-4 h-4 text-gray-700 mx-1 flex-shrink-0"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </div>

          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white">Step 6 — Fixed Assets & CAPEX Plan</h1>
              <p class="text-gray-400 text-sm mt-0.5">{{ company.name }} · {{ study.name }}</p>
            </div>
            <div class="flex items-center gap-3">
              <!-- Summary pill -->
              <div class="hidden md:flex items-center gap-4 bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-xs">
                <span class="text-gray-400">Assets: <span class="text-white font-semibold">{{ assets.length }}</span></span>
                <span class="text-gray-400">Total CAPEX: <span class="text-orange-400 font-semibold">{{ fmtNumber(totalCapex) }}</span></span>
              </div>
              <!-- Back -->
              <Link :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/expenses`"
                class="flex items-center gap-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                ← Back
              </Link>
              <!-- Write-up -->
              <StudyWriteup
                :company-id="company.id"
                :study-id="study.id"
                :study-name="study.name"
                step-key="fixed_assets"
                step-label="Fixed Assets"
                step-icon="🏗️"
                accent-color="#ea580c"
                :saved-text="props.writeupText"
                :summary-columns="writeupSummaryColumns"
                :summary-rows="writeupSummaryRows"
                :summary-totals="writeupSummaryTotals"
                :category-breakdown="writeupCategoryBreakdown"
              />
              <!-- Save & Exit -->
              <button type="button" @click="submitForm('save')" :disabled="processing"
                class="flex items-center gap-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Save & Exit
              </button>
              <!-- Save & Next -->
              <button type="button" @click="submitForm('next')" :disabled="processing"
                class="flex items-center gap-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors disabled:opacity-50">
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
      <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- Top bar: description + Add button -->
        <div class="flex items-center justify-between">
          <p class="text-gray-500 text-sm">
            Define each fixed asset — depreciation schedule, product allocation, and funding structure.
          </p>
          <button type="button" @click="addAsset"
            class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Fixed Asset
          </button>
        </div>

        <!-- Empty state -->
        <div v-if="assets.length === 0"
          class="bg-gray-900 border border-gray-800 rounded-xl p-16 text-center text-gray-600 text-sm">
          <div class="text-4xl mb-3">🏗️</div>
          No assets yet. Click "Add Fixed Asset" to start planning your CAPEX.
        </div>

        <!-- ── Asset Cards ── -->
        <div v-for="(asset, idx) in assets" :key="asset._id"
          class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">

          <!-- Card header -->
          <div class="px-6 py-3 border-b border-gray-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="text-xs font-semibold text-blue-400 uppercase tracking-widest">Asset #{{ idx + 1 }}</span>
              <span v-if="asset.name" class="text-white text-sm font-medium">— {{ asset.name }}</span>
              <span v-if="asset.total > 0"
                class="bg-orange-900/40 border border-orange-700/50 text-orange-300 text-xs px-2 py-0.5 rounded-full">
                {{ study.study_currency }} {{ fmtNumber(asset.total) }}
              </span>
            </div>
            <button type="button" @click="removeAsset(idx)"
              class="text-gray-600 hover:text-red-400 transition-colors p-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
            </button>
          </div>

          <div class="p-5 space-y-5">

            <!-- ── ROW 1: Core asset fields ── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7 gap-4 items-end">
              <!-- Name -->
              <div class="xl:col-span-2">
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Name</label>
                <input type="text" v-model="asset.name" placeholder="e.g. CNC Machine, Delivery Van..."
                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
              </div>
              <!-- Count -->
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Count</label>
                <input type="number" min="0" step="1" v-model.number="asset.count"
                  @input="calcTotal(asset)"
                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
              </div>
              <!-- Unit Amount -->
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">
                  Unit Amount
                  <span class="text-gray-600 normal-case font-normal ml-1">({{ study.study_currency }})</span>
                </label>
                <input type="number" min="0" step="0.01" v-model.number="asset.amount"
                  @input="calcTotal(asset)"
                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
              </div>
              <!-- Total (auto) -->
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Total</label>
                <div class="bg-gray-800/50 border border-gray-700/50 rounded-lg px-3 py-2 text-orange-400 font-semibold text-sm">
                  {{ fmtNumber(asset.total) }}
                </div>
              </div>
              <!-- Depreciation Duration -->
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Depreciation</label>
                <select v-model="asset.depreciation_duration"
                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500">
                  <option value="0">No Depreciation</option>
                  <option v-for="y in 20" :key="y" :value="y">{{ y }} Year{{ y > 1 ? 's' : '' }}</option>
                </select>
              </div>
              <!-- Start Date -->
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">Start Date</label>
                <input type="month" v-model="asset.start_date"
                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
              </div>
              <!-- End Date -->
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">End Date</label>
                <input type="month" v-model="asset.end_date"
                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
              </div>
            </div>

            <!-- ── ROW 2: Depreciation split + Replacement + Payment ── -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 items-end border-t border-gray-800 pt-4">
              <!-- Admin Dep % -->
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Admin Dep. %</label>
                <div class="flex items-center gap-1">
                  <input type="number" min="0" max="100" step="0.1" v-model.number="asset.admin_dep_pct"
                    @input="syncMfgDep(asset)"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
                  <span class="text-gray-500 text-sm">%</span>
                </div>
              </div>
              <!-- Mfg Dep % (auto) -->
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Mfg. Dep. %</label>
                <div class="bg-gray-800/50 border border-gray-700/50 rounded-lg px-3 py-2 text-blue-400 font-semibold text-sm flex items-center justify-between">
                  {{ asset.mfg_dep_pct.toFixed(1) }}
                  <span class="text-gray-600 text-xs font-normal">%</span>
                </div>
              </div>
              <!-- Products Allocation -->
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">
                  Mfg. Dep. Allocation
                </label>
                <button type="button" @click="openAllocModal(idx)"
                  :class="[
                    'w-full flex items-center gap-2 border rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    asset.product_allocation.length > 0
                      ? 'bg-blue-900/40 border-blue-700/60 text-blue-300'
                      : 'bg-gray-800 border-gray-700 text-gray-300 hover:bg-gray-700'
                  ]">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                  <span class="truncate">
                    {{ asset.product_allocation.length > 0
                      ? (asset.alloc_mode === 'revenue' ? 'By Revenue % ✓' : 'Manual ✓')
                      : 'Allocate' }}
                  </span>
                </button>
              </div>
              <!-- Replacement Cost % -->
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Replacement Cost %</label>
                <div class="flex items-center gap-1">
                  <input type="number" min="0" max="100" step="0.1" v-model.number="asset.replacement_cost_pct"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
                  <span class="text-gray-500 text-sm">%</span>
                </div>
              </div>
              <!-- Replacement Interval -->
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Replacement Interval</label>
                <select v-model="asset.replacement_interval"
                  class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500">
                  <option value="1y">1 Year</option>
                  <option value="2y">2 Years</option>
                  <option value="3y">3 Years</option>
                  <option value="4y">4 Years</option>
                  <option value="5y">5 Years</option>
                </select>
              </div>
              <!-- Payment Term + configure button -->
              <div>
                <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Payment Term</label>
                <div class="flex items-center gap-2">
                  <select v-model="asset.payment_term" @change="onPaymentTermChange(asset, idx)"
                    class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500">
                    <option value="cash">Cash</option>
                    <option value="installment">Installment</option>
                    <option value="customize">Customize</option>
                  </select>
                  <!-- Edit button shown when already configured -->
                  <button v-if="asset.payment_term !== 'cash'"
                    type="button" @click="openPaymentModal(idx)"
                    :class="[
                      'flex-shrink-0 w-9 h-9 flex items-center justify-center border rounded-lg transition-colors',
                      hasPaymentConfig(asset)
                        ? 'bg-violet-900/40 border-violet-600/60 text-violet-300'
                        : 'bg-gray-800 border-gray-700 text-gray-400 hover:bg-gray-700'
                    ]"
                    title="Edit configuration">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- ── ROW 3: Funding Structure ── -->
            <div class="border-t border-gray-800 pt-4">
              <p class="text-xs font-semibold text-blue-400 uppercase tracking-widest mb-3">Funding Structure</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 items-end">
                <!-- Equity % -->
                <div>
                  <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">Equity Funding %</label>
                  <div class="flex items-center gap-1">
                    <input type="number" min="0" max="100" step="0.1" v-model.number="asset.equity_pct"
                      @input="calcDebtPct(asset)"
                      class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
                    <span class="text-gray-500 text-sm">%</span>
                  </div>
                </div>
                <!-- Debt % (auto) -->
                <div>
                  <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">Debt Funding %</label>
                  <div class="bg-gray-800/50 border border-gray-700/50 rounded-lg px-3 py-2 text-blue-400 font-semibold text-sm flex items-center justify-between">
                    {{ asset.debt_pct.toFixed(1) }}
                    <span class="text-gray-600 text-xs font-normal">%</span>
                  </div>
                </div>
                <!-- Interest % -->
                <div>
                  <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">Interest %</label>
                  <div class="flex items-center gap-1">
                    <input type="number" min="0" max="100" step="0.01" v-model.number="asset.interest_pct"
                      :disabled="asset.debt_pct <= 0"
                      :class="asset.debt_pct <= 0 ? 'opacity-30 cursor-not-allowed' : ''"
                      class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
                    <span class="text-gray-500 text-sm">%</span>
                  </div>
                </div>
                <!-- Grace Period -->
                <div>
                  <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">Grace Period (Months)</label>
                  <input type="number" min="0" step="1" v-model.number="asset.grace_months"
                    :disabled="asset.debt_pct <= 0"
                    :class="asset.debt_pct <= 0 ? 'opacity-30 cursor-not-allowed' : ''"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
                </div>
                <!-- Tenor -->
                <div>
                  <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">Tenor (Months)</label>
                  <input type="number" min="0" step="1" v-model.number="asset.tenor_months"
                    :disabled="asset.debt_pct <= 0"
                    :class="asset.debt_pct <= 0 ? 'opacity-30 cursor-not-allowed' : ''"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
                </div>
                <!-- Installment Interval -->
                <div>
                  <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">Installment Interval</label>
                  <select v-model="asset.installment_interval"
                    :disabled="asset.debt_pct <= 0"
                    :class="asset.debt_pct <= 0 ? 'opacity-30 cursor-not-allowed' : ''"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500">
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="semi-annual">Semi-Annual</option>
                    <option value="annual">Annual</option>
                  </select>
                </div>
              </div>
            </div>

          </div><!-- /card body -->
        </div><!-- /v-for asset -->

        <!-- ── Grand Total Summary ── -->
        <div v-if="assets.length > 0" class="bg-gray-900 border border-gray-700 rounded-xl p-5">
          <p class="text-xs font-semibold text-blue-400 uppercase tracking-widest mb-4">CAPEX Summary</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
            <div class="bg-gray-800 rounded-lg p-3">
              <p class="text-xs text-gray-500 mb-1">Total Assets</p>
              <p class="text-white font-semibold text-sm">{{ assets.length }} asset{{ assets.length !== 1 ? 's' : '' }}</p>
            </div>
            <div class="bg-gray-800 rounded-lg p-3">
              <p class="text-xs text-gray-500 mb-1">Total CAPEX</p>
              <p class="text-orange-400 font-semibold text-sm">{{ study.study_currency }} {{ fmtNumber(totalCapex) }}</p>
            </div>
            <div class="bg-gray-800 rounded-lg p-3">
              <p class="text-xs text-gray-500 mb-1">Equity Financed</p>
              <p class="text-green-400 font-semibold text-sm">{{ study.study_currency }} {{ fmtNumber(totalEquity) }}</p>
            </div>
            <div class="bg-gray-800 rounded-lg p-3">
              <p class="text-xs text-gray-500 mb-1">Debt Financed</p>
              <p class="text-blue-400 font-semibold text-sm">{{ study.study_currency }} {{ fmtNumber(totalDebt) }}</p>
            </div>
          </div>
          <div class="pt-4 border-t border-gray-700 flex items-center justify-between">
            <span class="text-gray-400 text-sm font-medium">Total Investment in Fixed Assets</span>
            <span class="text-orange-400 font-bold text-xl">{{ study.study_currency }} {{ fmtNumber(totalCapex) }}</span>
          </div>
        </div>

      </div><!-- /content -->
    </div><!-- /page -->

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  MODAL: Products Allocation (Mfg. Dep. %)                   -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="allocModal.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4"
        @click.self="allocModal.open = false">
        <div class="bg-gray-900 border border-gray-700 rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden">

          <!-- Modal header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
            <div>
              <h3 class="text-white font-semibold text-lg">Allocate</h3>
              <p class="text-gray-500 text-xs mt-0.5">
                Distributes the <span class="text-blue-400 font-semibold">Mfg. Dep. {{ allocModal.mfgPct }}%</span>
                across products
              </p>
            </div>
            <button type="button" @click="allocModal.open = false" class="text-gray-500 hover:text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Mode toggle: Revenue % vs Manual -->
          <div class="px-6 py-3 border-b border-gray-800 flex items-center gap-3">
            <!-- Revenue-based toggle -->
            <button type="button" @click="setAllocMode('revenue')"
              :class="[
                'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border',
                allocModal.mode === 'revenue'
                  ? 'bg-emerald-900/40 border-emerald-600/60 text-emerald-300'
                  : 'bg-gray-800 border-gray-700 text-gray-400 hover:bg-gray-700'
              ]">
              <!-- Green radio dot matching the image -->
              <span :class="[
                'w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0',
                allocModal.mode === 'revenue'
                  ? 'border-emerald-400'
                  : 'border-gray-600'
              ]">
                <span v-if="allocModal.mode === 'revenue'" class="w-2 h-2 rounded-full bg-emerald-400"></span>
              </span>
              Allocate based on Revenues Percentages
            </button>
            <!-- Manual toggle -->
            <button type="button" @click="setAllocMode('manual')"
              :class="[
                'flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors border',
                allocModal.mode === 'manual'
                  ? 'bg-blue-900/40 border-blue-600/60 text-blue-300'
                  : 'bg-gray-800 border-gray-700 text-gray-400 hover:bg-gray-700'
              ]">
              <span :class="[
                'w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0',
                allocModal.mode === 'manual'
                  ? 'border-blue-400'
                  : 'border-gray-600'
              ]">
                <span v-if="allocModal.mode === 'manual'" class="w-2 h-2 rounded-full bg-blue-400"></span>
              </span>
              Manual
            </button>
          </div>

          <!-- No products -->
          <div v-if="props.products.length === 0"
            class="p-10 text-center text-gray-600 text-sm">No products defined in Step 1.</div>

          <!-- Table -->
          <div v-else class="px-6 pt-4 pb-2">
            <!-- Table header -->
            <div class="grid grid-cols-2 gap-4 mb-2 px-1">
              <span class="text-xs font-semibold text-gray-500 uppercase tracking-widest">Product</span>
              <span class="text-xs font-semibold text-gray-500 uppercase tracking-widest text-right">Perc. %</span>
            </div>

            <!-- Product rows -->
            <div class="space-y-2 max-h-64 overflow-y-auto">
              <div v-for="(row, pi) in allocModal.rows" :key="pi"
                class="grid grid-cols-2 gap-4 items-center border-b border-gray-800 pb-2">
                <!-- Product name (read-only, styled like the image) -->
                <div class="bg-blue-900/20 border border-blue-800/40 rounded-lg px-3 py-2 text-blue-200 text-sm font-medium">
                  {{ row.product_name }}
                </div>
                <!-- Percentage input -->
                <div class="flex justify-end">
                  <input
                    type="number" min="0" max="100" step="0.01"
                    v-model.number="row.pct"
                    :readonly="allocModal.mode === 'revenue'"
                    :class="[
                      'w-28 border rounded-lg px-3 py-2 text-sm text-right focus:outline-none focus:ring-1',
                      allocModal.mode === 'revenue'
                        ? 'bg-blue-900/20 border-blue-800/40 text-blue-200 cursor-not-allowed'
                        : 'bg-gray-800 border-gray-700 text-white focus:ring-blue-500'
                    ]"/>
                </div>
              </div>
            </div>

            <!-- Total row -->
            <div class="grid grid-cols-2 gap-4 items-center mt-3 pt-3 border-t border-gray-700">
              <div class="bg-blue-900/20 border border-blue-800/40 rounded-lg px-3 py-2 text-blue-200 text-sm font-bold">
                Total
              </div>
              <div class="flex justify-end">
                <div :class="[
                  'w-28 border rounded-lg px-3 py-2 text-sm text-right font-bold',
                  allocTotal === 100
                    ? 'bg-emerald-900/30 border-emerald-700/50 text-emerald-300'
                    : 'bg-red-900/30 border-red-700/50 text-red-300'
                ]">
                  {{ allocTotal.toFixed(2) }}
                </div>
              </div>
            </div>

            <!-- Validation message -->
            <div v-if="allocTotal !== 100" class="mt-2 flex items-center gap-2">
              <div class="w-2 h-2 rounded-full bg-red-400 flex-shrink-0"></div>
              <span class="text-xs text-red-400">Must equal 100% — currently {{ allocTotal.toFixed(2) }}%</span>
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-700 mt-2">
            <button type="button" @click="allocModal.open = false"
              class="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors">Cancel</button>
            <button type="button" @click="saveAlloc"
              :disabled="props.products.length > 0 && allocTotal !== 100"
              class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40">
              Save
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  MODAL: Custom Payment                                      -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="customPayModal.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4"
        @click.self="customPayModal.open = false">
        <div class="bg-gray-900 border border-gray-700 rounded-2xl w-full max-w-2xl p-6 shadow-2xl">
          <div class="flex items-center justify-between mb-5">
            <div>
              <h3 class="text-white font-semibold text-lg">Custom Payment</h3>
              <p class="text-gray-500 text-xs mt-0.5">Define up to 5 payment tranches — must total 100%</p>
            </div>
            <button type="button" @click="customPayModal.open = false" class="text-gray-500 hover:text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Table header -->
          <div class="grid grid-cols-4 gap-4 text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3 px-3">
            <span>Payment Rate %</span>
            <span>Due In Days</span>
            <span class="text-center">From Total Amount</span>
            <span class="text-center">From Execution Amount</span>
          </div>

          <!-- Tranche rows -->
          <div class="space-y-2 mb-4">
            <div v-for="(tranche, ti) in customPayModal.tranches" :key="ti"
              class="grid grid-cols-4 gap-4 items-center bg-gray-800 border border-gray-700 rounded-lg p-3">
              <input type="number" min="0" max="100" step="0.1" v-model.number="tranche.rate" placeholder="0"
                class="bg-gray-900 border border-gray-600 rounded px-2 py-1.5 text-white text-sm text-center focus:outline-none focus:ring-1 focus:ring-violet-500"/>
              <input type="number" min="0" step="1" v-model.number="tranche.days" placeholder="0"
                class="bg-gray-900 border border-gray-600 rounded px-2 py-1.5 text-white text-sm text-center focus:outline-none focus:ring-1 focus:ring-violet-500"/>
              <div class="flex justify-center">
                <input type="radio" :name="`tbasis-${ti}`" value="total" v-model="tranche.basis"
                  class="w-5 h-5 text-blue-500 border-gray-600 focus:ring-blue-500"/>
              </div>
              <div class="flex justify-center">
                <input type="radio" :name="`tbasis-${ti}`" value="execution" v-model="tranche.basis"
                  class="w-5 h-5 text-pink-500 border-gray-600 focus:ring-pink-500"/>
              </div>
            </div>
          </div>

          <!-- Execution curve shape — only matters for tranches set to
               "From Execution Amount": determines how construction
               progress (and therefore each مستخلص's certified value)
               builds up over the PUP period. -->
          <div v-if="customPayModal.tranches.some(t => t.basis === 'execution')" class="mb-4">
            <label class="block text-xs font-semibold text-pink-400 uppercase tracking-widest mb-1">Execution Curve Shape</label>
            <select v-model="customPayModal.curve_shape"
              class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-pink-500">
              <option value="symmetric">Symmetric S-Curve (peaks mid-project)</option>
              <option value="front_loaded">Front-Loaded (peaks early — mobilization-heavy)</option>
              <option value="back_loaded">Back-Loaded (peaks late — finishing-heavy)</option>
            </select>
            <p class="text-gray-500 text-xs mt-1">Models how fast work — and each execution invoice's certified value — builds up during the project.</p>
          </div>

          <!-- Validation -->
          <div class="flex items-center gap-2 mb-4">
            <div :class="['w-2 h-2 rounded-full', customPayTotal === 100 ? 'bg-emerald-400' : 'bg-red-400']"></div>
            <span :class="['text-xs', customPayTotal === 100 ? 'text-emerald-400' : 'text-red-400']">
              {{ customPayTotal === 100 ? 'Tranches sum to 100% ✓' : `Currently ${customPayTotal.toFixed(1)}% — must equal 100%` }}
            </span>
          </div>

          <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-700">
            <button type="button" @click="customPayModal.open = false"
              class="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors">Cancel</button>
            <button type="button" @click="saveCustomPay" :disabled="customPayTotal !== 100"
              class="px-5 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40">
              Save
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!--  MODAL: Installments                                        -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="installModal.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4"
        @click.self="installModal.open = false">
        <div class="bg-gray-900 border border-gray-700 rounded-2xl w-full max-w-xl p-6 shadow-2xl">
          <div class="flex items-center justify-between mb-5">
            <div>
              <h3 class="text-white font-semibold text-lg">Installments</h3>
              <p class="text-gray-500 text-xs mt-0.5">Configure the payment installment schedule</p>
            </div>
            <button type="button" @click="installModal.open = false" class="text-gray-500 hover:text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
            <div>
              <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Reservation %</label>
              <input type="number" min="0" max="100" step="0.1" v-model.number="installModal.form.reservation_pct"
                @input="calcInstallRemaining"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
            </div>
            <div>
              <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Contractual %</label>
              <input type="number" min="0" max="100" step="0.1" v-model.number="installModal.form.contractual_pct"
                @input="calcInstallRemaining"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
            </div>
            <div>
              <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">After Months</label>
              <input type="number" min="0" step="1" v-model.number="installModal.form.after_months"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
            </div>
            <div>
              <label class="block text-xs font-semibold text-blue-400 uppercase tracking-widest mb-1">Remaining Balance %</label>
              <div class="bg-gray-800/50 border border-gray-700/50 rounded-lg px-3 py-2 text-blue-400 font-semibold text-sm">
                {{ installModal.remaining.toFixed(1) }}%
              </div>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">Grace Period (Months)</label>
              <input type="number" min="0" step="1" v-model.number="installModal.form.grace_period"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">Installment Count</label>
              <input type="number" min="1" step="1" v-model.number="installModal.form.count"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500"/>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-widest mb-1">Installment Interval</label>
              <select v-model="installModal.form.interval"
                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-orange-500">
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="semi-annual">Semi-Annual</option>
                <option value="annual">Annual</option>
              </select>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 mt-5 pt-4 border-t border-gray-700">
            <button type="button" @click="installModal.open = false"
              class="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors">Cancel</button>
            <button type="button" @click="saveInstall"
              class="px-5 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium rounded-lg transition-colors">
              Save
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Saved toast ── -->
    <Teleport to="body">
      <div v-if="savedToast"
        class="fixed bottom-6 right-6 z-50 bg-green-600 text-white text-sm font-medium px-5 py-3 rounded-xl shadow-2xl flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        Saved successfully
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
  company:         { type: Object, required: true },
  study:           { type: Object, required: true },
  products:        { type: Array,  default: () => [] },
  fixedAssetsData: { type: Array,  default: () => [] },
  writeupText:     { type: String, default: '' },
})

// ── Wizard steps (current = index 5) ──────────────────────────────────
const wizardSteps = [
  'Setup', 'Sales Projection', 'COGS', 'Manpower',
  'Expenses', 'Fixed Assets', 'Opening Balance', 'Results',
]

// ── Asset state ────────────────────────────────────────────────────────
let _nextId = 1
function blankAsset(existing = {}) {
  return {
    _id:                   _nextId++,
    name:                  existing.name                  ?? '',
    count:                 existing.count                 ?? 0,
    amount:                existing.amount                ?? 0,
    total:                 existing.total                 ?? 0,
    depreciation_duration: existing.depreciation_duration ?? 0,
    start_date:            existing.start_date            ?? '',
    end_date:              existing.end_date              ?? '',
    admin_dep_pct:         existing.admin_dep_pct         ?? 0,
    mfg_dep_pct:           existing.mfg_dep_pct           ?? 100,
    product_allocation:    existing.product_allocation    ?? [],
    alloc_mode:            existing.alloc_mode            ?? 'revenue',
    replacement_cost_pct:  existing.replacement_cost_pct  ?? 0,
    replacement_interval:  existing.replacement_interval  ?? '1y',
    payment_term:          existing.payment_term          ?? 'cash',
    custom_payment:        existing.custom_payment        ?? null,
    installment_config:    existing.installment_config    ?? null,
    equity_pct:            existing.equity_pct            ?? 0,
    debt_pct:              existing.debt_pct              ?? 100,
    interest_pct:          existing.interest_pct          ?? 0,
    grace_months:          existing.grace_months          ?? 0,
    tenor_months:          existing.tenor_months          ?? 60,
    installment_interval:  existing.installment_interval  ?? 'monthly',
  }
}

const assets = reactive(
  props.fixedAssetsData.length
    ? props.fixedAssetsData.map(a => blankAsset(a))
    : []
)

// ── Computed totals ────────────────────────────────────────────────────
const totalCapex  = computed(() => assets.reduce((s, a) => s + (a.total || 0), 0))
const totalEquity = computed(() => assets.reduce((s, a) => s + (a.total || 0) * ((a.equity_pct || 0) / 100), 0))
const totalDebt   = computed(() => assets.reduce((s, a) => s + (a.total || 0) * ((a.debt_pct  || 0) / 100), 0))

// ── Asset helpers ──────────────────────────────────────────────────────
function addAsset()       { assets.push(blankAsset()) }
function removeAsset(idx) { assets.splice(idx, 1) }

function calcTotal(asset) {
  asset.total = (asset.count || 0) * (asset.amount || 0)
}
function syncMfgDep(asset) {
  asset.mfg_dep_pct = parseFloat((100 - (asset.admin_dep_pct || 0)).toFixed(2))
}
function calcDebtPct(asset) {
  asset.debt_pct = parseFloat((100 - (asset.equity_pct || 0)).toFixed(2))
}
function onPaymentTermChange(asset, idx) {
  if (asset.payment_term === 'cash') {
    asset.custom_payment     = null
    asset.installment_config = null
  } else {
    // Auto-open the correct modal immediately when user selects the term
    setTimeout(() => openPaymentModal(idx), 0)
  }
}
function hasPaymentConfig(asset) {
  return (asset.payment_term === 'customize' && asset.custom_payment !== null) ||
         (asset.payment_term === 'installment' && asset.installment_config !== null)
}
function openPaymentModal(idx) {
  assets[idx].payment_term === 'customize' ? openCustomPayModal(idx) : openInstallModal(idx)
}
function fmtNumber(n) {
  if (!n && n !== 0) return '—'
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

// ── Allocation Modal ───────────────────────────────────────────────────
const allocModal = reactive({
  open:     false,
  assetIdx: null,
  mode:     'revenue',   // 'revenue' | 'manual'
  mfgPct:   100,         // the Mfg. Dep. % of the current asset (display only)
  rows:     [],
})

const allocTotal = computed(() =>
  allocModal.rows.reduce((s, r) => s + (Number(r.pct) || 0), 0)
)

// Revenue-based = equal share across all products (since we don't have
// live revenue totals in this step; equal split is the standard default)
function applyRevenueMode() {
  const n = allocModal.rows.length
  if (n === 0) return
  const base     = parseFloat((100 / n).toFixed(2))
  const remainder = parseFloat((100 - base * (n - 1)).toFixed(2))
  allocModal.rows.forEach((r, i) => {
    r.pct = i === n - 1 ? remainder : base
  })
}

function setAllocMode(mode) {
  allocModal.mode = mode
  if (mode === 'revenue') applyRevenueMode()
}

function openAllocModal(idx) {
  allocModal.assetIdx = idx
  allocModal.mfgPct   = assets[idx].mfg_dep_pct ?? 100
  const existing      = assets[idx].product_allocation || []
  const savedMode     = assets[idx].alloc_mode || 'revenue'

  allocModal.rows = props.products.map(p => {
    const found = existing.find(e => e.product_name === p.name)
    return { product_name: p.name, pct: found ? found.pct : 0 }
  })

  allocModal.mode = savedMode
  // If no existing allocation yet, auto-apply revenue mode as default
  if (existing.length === 0) applyRevenueMode()

  allocModal.open = true
}

function saveAlloc() {
  assets[allocModal.assetIdx].product_allocation = allocModal.rows.map(r => ({ ...r }))
  assets[allocModal.assetIdx].alloc_mode         = allocModal.mode
  allocModal.open = false
}

// ── Custom Payment Modal ───────────────────────────────────────────────
const customPayModal = reactive({ open: false, assetIdx: null, tranches: [], curve_shape: 'symmetric' })
const customPayTotal  = computed(() => customPayModal.tranches.reduce((s, t) => s + (t.rate || 0), 0))
const CURVE_SHAPES = { symmetric: [2, 2], front_loaded: [3, 2], back_loaded: [2, 3] }

function openCustomPayModal(idx) {
  customPayModal.assetIdx = idx
  const existing = assets[idx].custom_payment || {}
  const ex = existing.tranches || []
  customPayModal.tranches = Array.from({ length: 5 }, (_, i) => ({
    rate:  ex[i]?.rate  || 0,
    days:  ex[i]?.days  || 0,
    basis: ex[i]?.basis || 'execution',
  }))
  customPayModal.curve_shape = existing.curve_shape || 'symmetric'
  customPayModal.open = true
}
function saveCustomPay() {
  const [alpha, beta] = CURVE_SHAPES[customPayModal.curve_shape] || CURVE_SHAPES.symmetric
  assets[customPayModal.assetIdx].custom_payment = {
    tranches: customPayModal.tranches.map(t => ({ ...t })),
    curve_shape: customPayModal.curve_shape,
    curve_alpha: alpha,
    curve_beta: beta,
  }
  customPayModal.open = false
}

// ── Installment Modal ──────────────────────────────────────────────────
const installModal = reactive({
  open: false, assetIdx: null, remaining: 100,
  form: { reservation_pct: 0, contractual_pct: 0, after_months: 0, grace_period: 0, count: 1, interval: 'monthly' },
})
function openInstallModal(idx) {
  installModal.assetIdx = idx
  const e = assets[idx].installment_config || {}
  installModal.form = {
    reservation_pct: e.reservation_pct || 0,
    contractual_pct: e.contractual_pct || 0,
    after_months:    e.after_months    || 0,
    grace_period:    e.grace_period    || 0,
    count:           e.count           || 1,
    interval:        e.interval        || 'monthly',
  }
  calcInstallRemaining()
  installModal.open = true
}
function calcInstallRemaining() {
  const used = (installModal.form.reservation_pct || 0) + (installModal.form.contractual_pct || 0)
  installModal.remaining = parseFloat((100 - used).toFixed(2))
}
function saveInstall() {
  assets[installModal.assetIdx].installment_config = { ...installModal.form }
  installModal.open = false
}

// ── apiFetch (same pattern as all other steps) ─────────────────────────
function apiFetch(url, options = {}) {
  const xsrf = document.cookie.split('; ')
    .find(r => r.startsWith('XSRF-TOKEN='))?.split('=')[1]
  return fetch(url, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'Accept':        'application/json',
      'X-XSRF-TOKEN':  xsrf ? decodeURIComponent(xsrf) : '',
      ...options.headers,
    },
    ...options,
  })
}

// ── Submit ─────────────────────────────────────────────────────────────
const processing = ref(false)
const savedToast = ref(false)

async function submitForm(button) {
  processing.value = true
  try {
    const payload = assets.map(({ _id, ...rest }) => rest)
    const res  = await apiFetch(
      `/portfolio-companies/${props.company.id}/financial-studies/${props.study.id}/fixed-assets`,
      { method: 'POST', body: JSON.stringify({ fixed_assets_data: payload, submit_button: button }) }
    )
    const json = await res.json()
    if (json.success) {
      if (json.redirect) {
        router.visit(json.redirect)
      } else {
        savedToast.value = true
        setTimeout(() => { savedToast.value = false }, 2500)
      }
    }
  } catch (e) {
    console.error(e)
  } finally {
    processing.value = false
  }
}

// ── Write-up summary ───────────────────────────────────────────────────
const writeupSummaryColumns = [
  { key: 'name',    label: 'Asset Name',  align: 'left'  },
  { key: 'total',   label: 'Total Cost',  align: 'right', highlight: true, totalColor: '#fb923c' },
  { key: 'funding', label: 'Funding Mix', align: 'right' },
]
const writeupSummaryRows = computed(() =>
  assets.map(a => ({
    name:    a.name || '(unnamed)',
    total:   fmtNumber(a.total),
    funding: `${a.equity_pct}% Eq / ${a.debt_pct}% Debt`,
  }))
)
const writeupSummaryTotals = computed(() => ({
  name: 'TOTAL', total: fmtNumber(totalCapex.value), funding: '',
}))
const writeupCategoryBreakdown = computed(() => {
  const colors = ['#3b82f6', '#ec4899', '#7c3aed', '#f97316', '#10b981']
  return assets.map((a, i) => ({
    label: a.name || `Asset ${i + 1}`,
    value: fmtNumber(a.total) + (totalCapex.value > 0 ? ` (${((a.total / totalCapex.value) * 100).toFixed(1)}%)` : ''),
    color: colors[i % colors.length],
  }))
})
</script>