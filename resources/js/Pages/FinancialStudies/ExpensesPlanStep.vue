<template>
  <Head :title="`Expenses Plan — ${study.name}`" />
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
                i === 4 ? 'bg-mp-warning text-white' : 'text-white'
              ]">
                <span :class="[
                  'w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0',
                  i === 4 ? 'bg-white/20 text-white' : 'bg-mp-card-hover text-white'
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
              <h1 class="text-2xl font-bold text-white">Step 5 — Operating Expenses Plan</h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }} · {{ study.name }}</p>
            </div>
            <div class="flex items-center gap-3">
              <!-- Summary pill -->
              <div class="hidden md:flex items-center gap-4 bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2 text-xs">
                <span class="text-white">Total Items: <span class="text-white font-semibold">{{ allRows.length }}</span></span>
                <span class="text-white">Est. Annual (Y1): <span class="text-mp-warning font-semibold">{{ fmtNumber(totalAnnualY1) }}</span></span>
              </div>
              <!-- Back -->
              <Link :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/manpower`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                ← Back
              </Link>
              <StudyWriteup
                :company-id="company.id"
                :study-id="study.id"
                :study-name="study.name"
                step-key="expenses"
                step-label="Expenses Plan"
                step-icon="💸"
                accent-color="#ea580c"
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
                class="flex items-center gap-2 bg-mp-warning hover:bg-mp-warning text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors disabled:opacity-50">
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

        <!-- Category tabs -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1">
          <button v-for="cat in categories" :key="cat.key"
            type="button" @click.stop="activeCat = cat.key"
            :class="[
              'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors flex-shrink-0',
              activeCat === cat.key
                ? cat.activeClass
                : 'bg-mp-card-hover text-white hover:bg-mp-page hover:text-white'
            ]">
            <span>{{ cat.icon }}</span>
            {{ cat.label }}
            <span :class="[
              'ml-1 px-1.5 py-0.5 rounded-full text-xs font-bold',
              activeCat === cat.key ? 'bg-white/20' : 'bg-mp-page text-white'
            ]">{{ rowsByCat(cat.key).length }}</span>
          </button>
        </div>

        <!-- Category card -->
        <template v-for="cat in categories" :key="cat.key">
        <div v-if="activeCat === cat.key"
          class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">

          <!-- Card header -->
          <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between flex-wrap gap-3">
            <div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest">{{ cat.label }}</p>
              <p class="text-white text-xs mt-0.5">{{ cat.description }}</p>
            </div>
            <div class="flex items-center gap-2">
              <!-- Import from expense_data -->
              <button type="button" @click="openImport(cat.key)"
                class="flex items-center gap-2 bg-mp-teal-subtle/40 hover:bg-mp-teal-subtle/60 border border-mp-teal/50 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Import from Data
              </button>
              <button type="button" @click="addRow(cat.key)"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Expense
              </button>
            </div>
          </div>

          <!-- Empty state -->
          <div v-if="rowsByCat(cat.key).length === 0"
            class="p-10 text-center text-white text-sm">
            No expenses yet. Click "Add Expense" or import from your historical data.
          </div>

          <!-- Expense rows -->
          <div v-else class="divide-y divide-gray-800/60">
            <div v-for="row in rowsByCat(cat.key)" :key="row._id"
              class="p-4 hover:bg-mp-card-hover/20 transition-colors">

              <!-- Row top: main fields in a responsive grid -->
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3 items-end">

                <!-- Expense Name -->
                <div class="lg:col-span-1">
                  <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1">Expense Name</label>
                  <input type="text" v-model="row.name" placeholder="e.g. Rent, Electricity..."
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-warning"/>
                </div>

                <!-- Type selector -->
                <div>
                  <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1">Type</label>
                  <select v-model="row.expense_type"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-warning">
                    <option value="pct_revenue">% of Revenue</option>
                    <option value="fixed_recurring">Fixed Recurring</option>
                    <option value="one_time">One-Time (Amortized)</option>
                  </select>
                </div>

                <!-- Applied-to products (only for % of Revenue) -->
                <div v-if="row.expense_type === 'pct_revenue'" class="lg:col-span-2">
                  <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1">
                    Applied to Products
                    <span class="text-white normal-case font-normal ml-1">(leave empty = all products)</span>
                  </label>
                  <div class="relative product-dropdown-container">
                    <div class="min-h-[38px] bg-mp-card-hover border border-mp-border rounded-lg px-3 py-1.5 flex flex-wrap gap-1.5 cursor-pointer"
                      @click.stop="toggleProductDropdown(row._id)">
                      <span v-if="row.applied_to_products.length === 0"
                        class="text-white text-sm leading-6">All products</span>
                      <span v-for="pname in row.applied_to_products" :key="pname"
                        class="inline-flex items-center gap-1 bg-mp-warning/50 border border-mp-warning/60 text-mp-warning text-xs px-2 py-0.5 rounded-full">
                        {{ pname }}
                        <button type="button" @click.stop="removeProduct(row, pname)" class="text-mp-warning hover:text-white">×</button>
                      </span>
                      <svg class="w-4 h-4 text-white ml-auto self-center flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                      </svg>
                    </div>
                    <!-- Dropdown list -->
                    <div v-if="openProductDropdown === row._id"
                      class="absolute z-30 top-full left-0 right-0 mt-1 bg-mp-card-hover border border-mp-border rounded-lg shadow-xl overflow-hidden" @click.stop>
                      <div v-if="props.products.length === 0" class="px-3 py-2 text-white text-xs">No products defined in Step 1.</div>
                      <label v-for="prod in props.products" :key="prod.name"
                        class="flex items-center gap-3 px-3 py-2 hover:bg-mp-page cursor-pointer">
                        <input type="checkbox" :value="prod.name" v-model="row.applied_to_products"
                          class="rounded border-mp-border text-mp-warning focus:ring-mp-warning"/>
                        <span class="text-sm text-white">{{ prod.name }}</span>
                        <span class="ml-auto text-xs text-white capitalize">{{ prod.nature }}</span>
                      </label>
                    </div>
                  </div>
                </div>

                <!-- Amount / Percentage -->
                <div>
                  <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1">
                    {{ row.expense_type === 'pct_revenue' ? 'Percentage %' : 'Amount' }}
                    <span v-if="row.expense_type !== 'pct_revenue'" class="text-white normal-case font-normal">({{ study.study_currency }})</span>
                  </label>
                  <div class="flex items-center gap-1">
                    <input type="number" min="0" step="0.01" v-model.number="row.amount"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-warning"/>
                    <span v-if="row.expense_type === 'pct_revenue'" class="text-white text-sm">%</span>
                  </div>
                </div>

                <!-- Annual Increase % -->
                <div>
                  <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1">Annual Increase %</label>
                  <div class="flex items-center gap-1">
                    <input type="number" min="0" max="100" step="0.1" v-model.number="row.annual_increase_pct"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-warning"/>
                    <span class="text-white text-sm">%</span>
                  </div>
                </div>

                <!-- Delete button -->
                <div class="flex items-end justify-end">
                  <button type="button" @click="removeRow(row._id)"
                    class="text-white hover:text-mp-danger transition-colors p-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Row bottom: secondary fields -->
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3 mt-3 items-end">

                <!-- Start Date -->
                <div>
                  <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1">Start Date</label>
                  <input type="month" v-model="row.start_date"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-warning"/>
                </div>

                <!-- End Date -->
                <div>
                  <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1">End Date</label>
                  <input type="month" v-model="row.end_date"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-warning"/>
                </div>

                <!-- Amortization months (only for one_time) -->
                <div v-if="row.expense_type === 'one_time'">
                  <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1">Amortize Over (months)</label>
                  <input type="number" min="1" step="1" v-model.number="row.amortization_months"
                    class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-1 focus:ring-mp-warning"/>
                </div>

                <!-- Payment Policy button -->
                <div :class="row.expense_type === 'pct_revenue' ? '' : ''">
                  <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1">Payment Policy</label>
                  <button type="button" @click="openPaymentModal(row)"
                    :class="[
                      'flex items-center gap-2 w-full border rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                      row.payment_policy?.preset === 'custom'
                        ? 'bg-mp-gold/40 border-mp-gold/60 text-white'
                        : 'bg-mp-card-hover border-mp-border text-white hover:bg-mp-page'
                    ]">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <span class="truncate">{{ policyLabel(row.payment_policy) }}</span>
                    <svg class="w-3 h-3 ml-auto flex-shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                  </button>
                </div>

                <!-- Estimated Annual Cost (auto, read-only) -->
                <div>
                  <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1">Est. Annual (Y1)</label>
                  <div class="bg-mp-card-hover/50 border border-mp-border/50 rounded-lg px-3 py-2 text-mp-warning font-semibold text-sm">
                    {{ row.expense_type === 'pct_revenue' ? row.amount + '%' : fmtNumber(annualCostY1(row)) }}
                  </div>
                </div>

              </div>
            </div>
          </div>

          <!-- Category subtotal -->
          <div v-if="rowsByCat(cat.key).length > 0"
            class="px-6 py-3 bg-mp-card-hover/40 border-t border-mp-border flex items-center justify-between">
            <span class="text-xs text-white uppercase tracking-widest font-semibold">{{ cat.label }} Subtotal (Fixed items, Y1)</span>
            <span class="text-mp-warning font-bold text-sm">{{ fmtNumber(catTotalY1(cat.key)) }}</span>
          </div>
        </div>
        </template>

        <!-- Grand Total Summary -->
        <div class="bg-mp-card border border-mp-border rounded-xl p-5">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Grand Total Summary — Year 1</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
            <div v-for="cat in categories" :key="cat.key" class="bg-mp-card-hover rounded-lg p-3">
              <p class="text-xs text-white mb-1">{{ cat.label }}</p>
              <p class="text-white font-semibold text-sm">{{ rowsByCat(cat.key).length }} items</p>
              <p class="text-mp-warning text-xs mt-0.5">{{ fmtNumber(catTotalY1(cat.key)) }} / yr</p>
            </div>
          </div>
          <div class="pt-4 border-t border-mp-border flex items-center justify-between">
            <span class="text-white text-sm font-medium">Total Fixed Operating Expenses (Year 1)</span>
            <span class="text-mp-warning font-bold text-xl">{{ fmtNumber(totalAnnualY1) }}</span>
          </div>
        </div>

      </div><!-- end content -->
    </div>

    <!-- ────────────────────────────────────────────────── -->
    <!--  IMPORT MODAL                                      -->
    <!-- ────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="importModal.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4"
        @click.self="importModal.open = false">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-md p-6 shadow-2xl">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-white font-semibold text-lg">Import Expense Names</h3>
            <button type="button" @click="importModal.open = false" class="text-white hover:text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <p class="text-white text-sm mb-4">Select expense names from your historical data to add to <strong class="text-white">{{ importModal.catLabel }}</strong>.</p>

          <div v-if="importModal.loading" class="text-center py-6 text-white">Loading...</div>
          <div v-else-if="importModal.names.length === 0" class="text-center py-6 text-white text-sm">
            No expense data found for this company yet.
          </div>
          <div v-else class="space-y-1 max-h-64 overflow-y-auto mb-4">
            <label v-for="name in importModal.names" :key="name"
              class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-mp-card-hover cursor-pointer">
              <input type="checkbox" :value="name" v-model="importModal.selected"
                class="rounded border-mp-border text-mp-warning focus:ring-mp-warning"/>
              <span class="text-sm text-white">{{ name }}</span>
            </label>
          </div>

          <div class="flex items-center justify-end gap-3 pt-2 border-t border-mp-border">
            <button type="button" @click="importModal.open = false"
              class="px-4 py-2 text-sm text-white hover:text-white transition-colors">Cancel</button>
            <button type="button" @click="confirmImport"
              :disabled="importModal.selected.length === 0"
              class="px-5 py-2 bg-mp-warning hover:bg-mp-warning text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40">
              Add {{ importModal.selected.length }} Expense{{ importModal.selected.length !== 1 ? 's' : '' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ────────────────────────────────────────────────── -->
    <!--  PAYMENT POLICY MODAL                              -->
    <!-- ────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="paymentModal.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4"
        @click.self="paymentModal.open = false">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-lg p-6 shadow-2xl">
          <div class="flex items-center justify-between mb-5">
            <div>
              <h3 class="text-white font-semibold text-lg">Payment Policy</h3>
              <p class="text-white text-xs mt-0.5">{{ paymentModal.expenseName || 'Expense' }}</p>
            </div>
            <button type="button" @click="paymentModal.open = false" class="text-white hover:text-white">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Preset buttons -->
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">Select Preset</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
            <button v-for="preset in presets" :key="preset.key"
              type="button" @click="selectPreset(preset.key)"
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
                  <label class="block text-xs text-white mb-1">Due in (days)</label>
                  <input type="number" min="0" step="1" v-model.number="tranche.days"
                    class="w-full bg-mp-card border border-mp-border rounded px-2 py-1.5 text-white text-sm text-center focus:outline-none focus:ring-1 focus:ring-mp-gold"/>
                </div>
              </div>
            </div>

            <!-- Validation -->
            <div class="mt-3 flex items-center gap-2">
              <div :class="[
                'w-2 h-2 rounded-full flex-shrink-0',
                trancheSumOk ? 'bg-mp-success' : 'bg-mp-danger'
              ]"></div>
              <span :class="['text-xs', trancheSumOk ? 'text-mp-success' : 'text-mp-danger']">
                {{ trancheSumOk ? 'Tranches sum to 100% ✓' : `Currently ${trancheSum}% — must equal 100%` }}
              </span>
            </div>
          </div>

          <!-- Save -->
          <div class="flex items-center justify-end gap-3 mt-5 pt-4 border-t border-mp-border">
            <button type="button" @click="paymentModal.open = false"
              class="px-4 py-2 text-sm text-white hover:text-white transition-colors">Cancel</button>
            <button type="button" @click="savePaymentPolicy"
              :disabled="paymentModal.draft.preset === 'custom' && !trancheSumOk"
              class="px-5 py-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-40">
              Apply Policy
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StudyWriteup from '@/Components/StudyWriteup.vue'

// ── Props ──────────────────────────────────────────────────────────────
const props = defineProps({
  company:      { type: Object, required: true },
  study:        { type: Object, required: true },
  expensesData: { type: Array,  default: () => [] },
  products:     { type: Array,  default: () => [] },
  writeupText:  { type: String, default: '' },
})

// ── Wizard steps ───────────────────────────────────────────────────────
const wizardSteps = [
  'Setup', 'Sales Projection', 'COGS', 'Manpower',
  'Expenses', 'Fixed Assets', 'Opening Balance', 'Results'
]

// ── Category config ────────────────────────────────────────────────────
const categories = [
  {
    key:         'sales',
    label:       'Sales Expenses',
    icon:        '🤝',
    description: 'Commissions, sales staff expenses, customer entertainment, travel',
    activeClass: 'bg-mp-teal text-white',
  },
  {
    key:         'marketing',
    label:       'Marketing',
    icon:        '📣',
    description: 'Advertising, digital marketing, branding, promotions, events',
    activeClass: 'bg-mp-gold text-white',
  },
  {
    key:         'general',
    label:       'General & Admin',
    icon:        '🏢',
    description: 'Rent, utilities, insurance, legal, office supplies, IT',
    activeClass: 'bg-mp-gold-dark text-white',
  },
  {
    key:         'finance',
    label:       'Finance Expenses',
    icon:        '🏦',
    description: 'Bank charges, loan interest, financial advisory, audit fees',
    activeClass: 'bg-mp-warning text-white',
  },
]

const activeCat = ref('sales')

// ── Presets ────────────────────────────────────────────────────────────
const presets = [
  { key: 'cash',        label: 'Cash' },
  { key: 'quarterly',   label: 'Quarterly' },
  { key: 'semi_annual', label: 'Semi-Annual' },
  { key: 'annual',      label: 'Annual' },
  { key: 'custom',      label: 'Custom' },
]

function defaultTranches(preset) {
  if (preset === 'cash')        return [{ pct: 100, days: 0 }, { pct: 0, days: 0 }, { pct: 0, days: 0 }]
  if (preset === 'quarterly')   return [{ pct: 100, days: 90 }, { pct: 0, days: 0 }, { pct: 0, days: 0 }]
  if (preset === 'semi_annual') return [{ pct: 100, days: 180 }, { pct: 0, days: 0 }, { pct: 0, days: 0 }]
  if (preset === 'annual')      return [{ pct: 100, days: 365 }, { pct: 0, days: 0 }, { pct: 0, days: 0 }]
  return [{ pct: 50, days: 30 }, { pct: 30, days: 60 }, { pct: 20, days: 90 }]
}

function defaultPolicy() {
  return { preset: 'cash', tranches: defaultTranches('cash') }
}

function policyLabel(policy) {
  if (!policy) return 'Cash'
  const map = { cash: 'Cash', quarterly: 'Quarterly', semi_annual: 'Semi-Annual', annual: 'Annual', custom: 'Custom' }
  return map[policy.preset] ?? 'Cash'
}

// ── Row state ──────────────────────────────────────────────────────────
let _nextId = 1
function makeRow(cat, existing = null) {
  return {
    _id:                _nextId++,
    category:           cat,
    name:               existing?.name               ?? '',
    expense_type:       existing?.expense_type       ?? 'fixed_recurring',
    amount:             existing?.amount             ?? 0,
    annual_increase_pct: existing?.annual_increase_pct ?? 0,
    start_date:         existing?.start_date         ?? '',
    end_date:           existing?.end_date           ?? '',
    amortization_months: existing?.amortization_months ?? 12,
    payment_policy:     existing?.payment_policy     ?? defaultPolicy(),
    applied_to_products: existing?.applied_to_products  ?? [],
  }
}

const allRows = reactive(
  props.expensesData.length > 0
    ? props.expensesData.map(r => makeRow(r.category, r))
    : []
)

// ── Product dropdown state ────────────────────────────────────────────
const openProductDropdown = ref(null)

function toggleProductDropdown(rowId) {
  openProductDropdown.value = openProductDropdown.value === rowId ? null : rowId
}

function removeProduct(row, pname) {
  row.applied_to_products = row.applied_to_products.filter(p => p !== pname)
}

// Close dropdown when clicking outside
function handleOutsideClick(e) {
  try {
    if (!e.target?.closest?.('.product-dropdown-container')) {
      openProductDropdown.value = null
    }
  } catch (_) {
    openProductDropdown.value = null
  }
}
onMounted(() => document.addEventListener('click', handleOutsideClick))
onUnmounted(() => document.removeEventListener('click', handleOutsideClick))

function rowsByCat(cat) { return allRows.filter(r => r.category === cat) }
function addRow(cat)     { allRows.push(makeRow(cat)) }
function removeRow(id)   {
  const idx = allRows.findIndex(r => r._id === id)
  if (idx !== -1) allRows.splice(idx, 1)
}

// ── Cost calculations ──────────────────────────────────────────────────
function annualCostY1(row) {
  if (row.expense_type === 'pct_revenue') return 0 // %-based, can't compute without revenue
  if (row.expense_type === 'one_time')    return Number(row.amount) || 0
  return (Number(row.amount) || 0) * 12
}

function catTotalY1(cat) {
  return rowsByCat(cat).reduce((sum, r) => sum + annualCostY1(r), 0)
}

const totalAnnualY1 = computed(() =>
  categories.reduce((sum, c) => sum + catTotalY1(c.key), 0)
)

function fmtNumber(n) {
  if (!n && n !== 0) return '—'
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

// ── Import Modal ───────────────────────────────────────────────────────
const importModal = reactive({
  open:     false,
  catKey:   '',
  catLabel: '',
  loading:  false,
  names:    [],
  selected: [],
})

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

async function openImport(catKey) {
  const cat = categories.find(c => c.key === catKey)
  importModal.catKey   = catKey
  importModal.catLabel = cat?.label ?? catKey
  importModal.selected = []
  importModal.names    = []
  importModal.open     = true
  importModal.loading  = true
  try {
    const res  = await apiFetch(`/portfolio-companies/${props.company.id}/financial-studies/api/expense-names`)
    const json = await res.json()
    importModal.names = json.names ?? []
  } catch (e) {
    console.error(e)
  } finally {
    importModal.loading = false
  }
}

function confirmImport() {
  for (const name of importModal.selected) {
    allRows.push(makeRow(importModal.catKey, { name }))
  }
  importModal.open = false
}

// ── Payment Policy Modal ───────────────────────────────────────────────
const paymentModal = reactive({
  open:        false,
  rowId:       null,
  expenseName: '',
  draft:       { preset: 'cash', tranches: defaultTranches('cash') },
})

const trancheSum = computed(() =>
  paymentModal.draft.tranches.reduce((s, t) => s + (Number(t.pct) || 0), 0)
)
const trancheSumOk = computed(() => trancheSum.value === 100)

function openPaymentModal(row) {
  paymentModal.rowId       = row._id
  paymentModal.expenseName = row.name
  paymentModal.draft       = JSON.parse(JSON.stringify(row.payment_policy ?? defaultPolicy()))
  paymentModal.open        = true
}

function selectPreset(preset) {
  paymentModal.draft.preset   = preset
  paymentModal.draft.tranches = defaultTranches(preset)
}

function savePaymentPolicy() {
  const row = allRows.find(r => r._id === paymentModal.rowId)
  if (row) row.payment_policy = JSON.parse(JSON.stringify(paymentModal.draft))
  paymentModal.open = false
}

// ── Submit ─────────────────────────────────────────────────────────────
const processing = ref(false)

async function submitForm(button) {
  processing.value = true
  try {
    const payload = allRows.map(({ _id, ...rest }) => rest)
    const res  = await apiFetch(
      `/portfolio-companies/${props.company.id}/financial-studies/${props.study.id}/expenses`,
      { method: 'POST', body: JSON.stringify({ expenses_data: payload, submit_button: button }) }
    )
    const json = await res.json()
    if (json.success) {
      if (json.redirect) {
        router.visit(json.redirect)
      } else {
        // Show a quick inline toast
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

const savedToast = ref(false)

// ── Write-up summary data for the panel ───────────────────────────────
const writeupSummaryColumns = [
  { key: 'category', label: 'Category',       align: 'left'  },
  { key: 'count',    label: 'Items',           align: 'right' },
  { key: 'annual',   label: 'Annual Cost (Y1)',align: 'right', highlight: true, totalColor: '#fb923c' },
]

const writeupSummaryRows = computed(() =>
  categories.map(c => ({
    category: c.label,
    count:    rowsByCat(c.key).length,
    annual:   fmtNumber(catTotalY1(c.key)),
  }))
)

const writeupSummaryTotals = computed(() => ({
  category: 'TOTAL',
  count:    allRows.length,
  annual:   fmtNumber(totalAnnualY1.value),
}))

const writeupCategoryBreakdown = computed(() => {
  const colors = ['#00b4c8', '#ec4899', '#7c3aed', '#f97316']
  const total  = totalAnnualY1.value
  return categories.map((c, i) => {
    const amt = catTotalY1(c.key)
    const pct = total > 0 ? ((amt / total) * 100).toFixed(1) : '0'
    return { label: c.label, value: pct + '% — ' + fmtNumber(amt), color: colors[i] }
  })
})

</script>