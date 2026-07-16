<template>
  <Head :title="`${isEditing ? 'Edit' : 'New'} Financial Statement — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Statements
          </Link>
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-bold text-white">
                {{ isEditing ? '✏️ Edit Financial Statement' : '📄 New Financial Statement' }}
              </h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }} · {{ company.currency }}</p>
            </div>
            <!-- Progress indicator -->
            <div class="hidden md:flex items-center gap-2 text-xs">
              <span v-for="(tab, i) in tabs" :key="tab.key"
                :class="[
                  'flex items-center gap-1.5 px-3 py-1.5 rounded-full font-medium transition-colors',
                  activeTab === tab.key
                    ? 'bg-mp-teal text-white'
                    : completedTabs.includes(tab.key)
                      ? 'bg-mp-success/15 text-mp-success'
                      : 'bg-mp-card-hover text-white'
                ]">
                <span v-if="completedTabs.includes(tab.key) && activeTab !== tab.key">✓</span>
                {{ tab.label }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- PERIOD & STATUS row -->
        <div class="bg-mp-card rounded-xl border border-mp-border p-6 mb-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Statement Period</p>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-xs text-white mb-1.5">From *</label>
              <input v-model="form.period_from" type="date"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"
                :class="{'border-mp-danger': errors.period_from}"/>
              <p v-if="errors.period_from" class="text-mp-danger text-xs mt-1">{{ errors.period_from }}</p>
            </div>
            <div>
              <label class="block text-xs text-white mb-1.5">To *</label>
              <input v-model="form.period_to" type="date"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"
                :class="{'border-mp-danger': errors.period_to}"/>
              <p v-if="errors.period_to" class="text-mp-danger text-xs mt-1">{{ errors.period_to }}</p>
            </div>
            <div>
              <label class="block text-xs text-white mb-1.5">Status</label>
              <select v-model="form.status"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                <option value="draft">Draft</option>
                <option value="final">Final</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-white mb-1.5">Notes (optional)</label>
              <input v-model="form.notes" type="text" placeholder="e.g. Audited, Unaudited..."
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
            </div>
          </div>
        </div>

        <!-- TABS -->
        <div class="flex gap-1 bg-mp-card border border-mp-border rounded-xl p-1 mb-6">
          <button v-for="tab in tabs" :key="tab.key"
            @click="activeTab = tab.key"
            :class="[
              'flex-1 flex items-center justify-center gap-2 py-2.5 text-sm font-medium rounded-lg transition-colors',
              activeTab === tab.key
                ? 'bg-mp-teal text-white shadow'
                : 'text-white hover:text-white hover:bg-mp-card-hover'
            ]">
            <span>{{ tab.icon }}</span>
            <span>{{ tab.label }}</span>
            <span v-if="completedTabs.includes(tab.key) && activeTab !== tab.key"
              class="w-4 h-4 bg-mp-success rounded-full flex items-center justify-center text-xs text-white">✓</span>
          </button>
        </div>

        <!-- ── INCOME STATEMENT TAB ── -->
        <div v-show="activeTab === 'income'">
          <div class="space-y-4">
            <div v-for="section in incomeSections" :key="section.key"
              :class="[
                'rounded-xl border overflow-hidden',
                section.computed
                  ? 'border-mp-teal/50 bg-mp-teal-subtle/20'
                  : 'border-mp-border bg-mp-card'
              ]">
              <!-- Section Header -->
              <div :class="[
                'flex items-center justify-between px-5 py-3',
                section.computed ? 'bg-mp-teal-subtle/30' : 'bg-mp-card-hover/50'
              ]">
                <div class="flex items-center gap-3">
                  <span v-if="section.computed" class="text-xs bg-mp-teal-dark text-white px-2 py-0.5 rounded font-semibold uppercase tracking-wide">Auto</span>
                  <h3 class="font-semibold text-sm text-white">
                    {{ section.label }}
                  </h3>
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-sm font-bold" :class="section.computed ? 'text-white' : 'text-mp-success'">
                    {{ formatNum(getSectionTotal('income', section.key)) }} {{ company.currency }}
                  </span>
                  <button v-if="!section.computed"
                    @click="addLineItem('income', section.key)"
                    class="text-xs bg-mp-page hover:bg-mp-teal text-white hover:text-white px-2.5 py-1 rounded-lg transition-colors">
                    + Add Row
                  </button>
                </div>
              </div>

              <!-- Computed row explanation -->
              <div v-if="section.computed" class="px-5 py-3 text-xs text-white/70 italic">
                Auto-calculated: {{ computedFormula(section) }}
              </div>

              <!-- Line items for non-computed sections -->
              <div v-if="!section.computed" class="px-5 py-3">
                <div v-if="!getSectionItems('income', section.key).length"
                  class="text-center py-4 text-white text-sm">
                  No rows yet — click "+ Add Row" to add sub-items
                </div>
                <div v-for="(item, idx) in getSectionItems('income', section.key)" :key="idx"
                  class="flex items-center gap-3 mb-2">
                  <input v-model="item.label" type="text"
                    placeholder="Description (e.g. Product A Revenue)"
                    class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white text-xs">{{ company.currency }}</span>
                    <input v-model.number="item.amount" type="number" min="0" step="0.01"
                      placeholder="0.00"
                      class="bg-mp-card-hover border border-mp-border rounded-lg pl-10 pr-3 py-2 text-sm text-white text-right w-44 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  </div>
                  <button @click="removeLineItem('income', section.key, idx)"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-end mt-6">
            <button @click="activeTab = 'balance_sheet'"
              class="bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors flex items-center gap-2">
              Next: Balance Sheet
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- ── BALANCE SHEET TAB ── -->
        <div v-show="activeTab === 'balance_sheet'">

          <!-- Balance check alert -->
          <div v-if="bsCheckDiff !== null" :class="[
            'flex items-center gap-3 px-4 py-3 rounded-lg mb-4 text-sm',
            bsCheckDiff < 1 ? 'bg-mp-success/40 border border-mp-success text-white' : 'bg-mp-danger/40 border border-mp-danger text-mp-danger'
          ]">
            <span>{{ bsCheckDiff < 1 ? '✅' : '⚠️' }}</span>
            <span v-if="bsCheckDiff < 1">Balance sheet is balanced — Total Assets = Total Liabilities & Equity</span>
            <span v-else>Balance sheet is out of balance by <strong>{{ formatNum(bsCheckDiff) }} {{ company.currency }}</strong>. Total Assets must equal Total Liabilities + Equity.</span>
          </div>

          <!-- CF category hint -->
          <div class="flex items-start gap-3 px-4 py-3 rounded-lg mb-4 text-sm bg-mp-teal-subtle/20 border border-mp-teal/40 text-white/80">
            <span>💡</span>
            <span>
              Tag each balance sheet row with <strong>O</strong> (Operating), <strong>I</strong> (Investing), or <strong>F</strong> (Financing).
              Cash flow will be auto-calculated from period-over-period changes when you proceed to the Cash Flow tab.
            </span>
          </div>

          <div class="space-y-4">
            <div v-for="section in balanceSections" :key="section.key"
              :class="[
                'rounded-xl border overflow-hidden',
                section.computed ? 'border-mp-teal/50 bg-mp-teal-subtle/20' : 'border-mp-border bg-mp-card'
              ]">
              <div :class="[
                'flex items-center justify-between px-5 py-3',
                section.computed ? 'bg-mp-teal-subtle/30' : 'bg-mp-card-hover/50'
              ]">
                <div class="flex items-center gap-3">
                  <span v-if="section.computed" class="text-xs bg-mp-teal-dark text-white px-2 py-0.5 rounded font-semibold uppercase tracking-wide">Auto</span>
                  <h3 class="font-semibold text-sm text-white">
                    {{ section.label }}
                  </h3>
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-sm font-bold" :class="section.computed ? 'text-white' : 'text-mp-success'">
                    {{ formatNum(getSectionTotal('balance_sheet', section.key)) }} {{ company.currency }}
                  </span>
                  <button v-if="!section.computed"
                    @click="addLineItem('balance_sheet', section.key)"
                    class="text-xs bg-mp-page hover:bg-mp-teal text-white hover:text-white px-2.5 py-1 rounded-lg transition-colors">
                    + Add Row
                  </button>
                </div>
              </div>
              <div v-if="section.computed" class="px-5 py-3 text-xs text-white/70 italic">
                Auto-calculated: {{ computedFormula(section) }}
              </div>
              <div v-if="!section.computed" class="px-5 py-3">
                <div v-if="!getSectionItems('balance_sheet', section.key).length"
                  class="text-center py-4 text-white text-sm">
                  No rows yet — click "+ Add Row"
                </div>
                <div v-for="(item, idx) in getSectionItems('balance_sheet', section.key)" :key="idx"
                  class="flex items-center gap-3 mb-2 flex-wrap">
                  <input v-model="item.label" type="text"
                    placeholder="Description (e.g. Cash & Bank Balances)"
                    class="flex-1 min-w-[200px] bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  <!-- CF category toggles (O / I / F) -->
                  <div v-if="!isCashLine(item.label)" class="flex gap-1 flex-shrink-0" title="Cash flow category">
                    <button v-for="cat in cfCategories" :key="cat.key" type="button"
                      @click="item.cf_category = item.cf_category === cat.key ? null : cat.key"
                      :class="[
                        'w-7 h-7 rounded-lg text-xs font-bold transition-colors border',
                        item.cf_category === cat.key
                          ? cat.activeClass
                          : 'bg-mp-page border-mp-border text-white/40 hover:text-white hover:border-mp-teal/50'
                      ]"
                      :title="cat.label">
                      {{ cat.short }}
                    </button>
                  </div>
                  <span v-else class="text-xs text-white/40 italic flex-shrink-0">Cash — excluded</span>
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white text-xs">{{ company.currency }}</span>
                    <input v-model.number="item.amount" type="number" min="0" step="0.01"
                      placeholder="0.00"
                      class="bg-mp-card-hover border border-mp-border rounded-lg pl-10 pr-3 py-2 text-sm text-white text-right w-44 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  </div>
                  <!-- Settlement Schedule button — only for settlement-eligible sections and when editing -->
                  <button
                    v-if="isEditing && settlementSections.includes(section.key) && item.id"
                    @click="openSettlement(item, section.key)"
                    :class="[
                      'w-8 h-8 flex items-center justify-center rounded-lg transition-colors flex-shrink-0 text-xs font-bold',
                      item._hasSchedule
                        ? 'bg-mp-teal-dark hover:bg-mp-teal text-white'
                        : 'bg-mp-page hover:bg-mp-teal-dark text-white hover:text-white'
                    ]"
                    title="Set monthly settlement schedule for cash forecast">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                  </button>
                  <!-- Info badge when not editing yet -->
                  <span v-else-if="settlementSections.includes(section.key) && !isEditing"
                    class="text-xs text-white italic whitespace-nowrap">Save first to add schedule</span>
                  <button @click="removeLineItem('balance_sheet', section.key, idx)"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-between mt-6">
            <button @click="activeTab = 'income'"
              class="bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
              Back
            </button>
            <button @click="goToCashFlow"
              class="bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors flex items-center gap-2">
              Next: Cash Flow
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- ── CASH FLOW TAB ── -->
        <div v-show="activeTab === 'cashflow'">
          <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
            <p class="text-sm text-white/70">
              {{ cfAutoMessage }}
            </p>
            <button type="button" @click="autoCalculateCashFlow"
              class="text-xs bg-mp-teal/20 hover:bg-mp-teal text-mp-teal hover:text-white px-3 py-2 rounded-lg font-medium transition-colors">
              ↻ Recalculate from Balance Sheet
            </button>
          </div>
          <div class="space-y-4">
            <div v-for="section in cashflowSections" :key="section.key"
              :class="[
                'rounded-xl border overflow-hidden',
                section.computed ? 'border-mp-teal/50 bg-mp-teal-subtle/20' : 'border-mp-border bg-mp-card'
              ]">
              <div :class="[
                'flex items-center justify-between px-5 py-3',
                section.computed ? 'bg-mp-teal-subtle/30' : 'bg-mp-card-hover/50'
              ]">
                <div class="flex items-center gap-3">
                  <span v-if="section.computed" class="text-xs bg-mp-teal-dark text-white px-2 py-0.5 rounded font-semibold uppercase tracking-wide">Auto</span>
                  <h3 class="font-semibold text-sm text-white">{{ section.label }}</h3>
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-sm font-bold" :class="section.computed ? 'text-white' : 'text-mp-success'">
                    {{ formatNum(getSectionTotal('cashflow', section.key)) }} {{ company.currency }}
                  </span>
                  <button v-if="!section.computed"
                    @click="addLineItem('cashflow', section.key)"
                    class="text-xs bg-mp-page hover:bg-mp-teal text-white hover:text-white px-2.5 py-1 rounded-lg transition-colors">
                    + Add Row
                  </button>
                </div>
              </div>
              <div v-if="section.computed" class="px-5 py-3 text-xs text-white/70 italic">
                Auto-calculated: {{ computedFormula(section) }}
              </div>
              <div v-if="!section.computed" class="px-5 py-3">
                <div v-if="!getSectionItems('cashflow', section.key).length"
                  class="text-center py-4 text-white text-sm">
                  No rows yet — click "+ Add Row"
                </div>
                <div v-for="(item, idx) in getSectionItems('cashflow', section.key)" :key="idx"
                  class="flex items-center gap-3 mb-2">
                  <span v-if="item._auto" class="text-xs bg-mp-teal/20 text-mp-teal px-2 py-0.5 rounded font-semibold flex-shrink-0">Auto</span>
                  <input v-model="item.label" type="text"
                    :readonly="item._auto"
                    placeholder="Description (e.g. Collections from Customers)"
                    :class="[
                      'flex-1 border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-mp-teal',
                      item._auto ? 'bg-mp-page/50 cursor-default' : 'bg-mp-card-hover'
                    ]"/>
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white text-xs">{{ company.currency }}</span>
                    <input v-model.number="item.amount" type="number" step="0.01"
                      placeholder="0.00"
                      class="bg-mp-card-hover border border-mp-border rounded-lg pl-10 pr-3 py-2 text-sm text-white text-right w-44 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  </div>
                  <button @click="removeLineItem('cashflow', section.key, idx)"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-between mt-6">
            <button @click="activeTab = 'balance_sheet'"
              class="bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium px-6 py-2.5 rounded-lg transition-colors flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
              Back
            </button>
            <button @click="submitForm" :disabled="saving"
              class="bg-mp-success hover:bg-mp-success disabled:opacity-50 text-white text-sm font-semibold px-8 py-2.5 rounded-lg transition-colors flex items-center gap-2">
              <svg v-if="!saving" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ saving ? 'Saving...' : (isEditing ? 'Update Statement' : 'Save Statement') }}
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         SETTLEMENT SCHEDULE MODAL
         ═══════════════════════════════════════════════════════ -->
    <div v-if="settlementModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
      <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">

        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border flex-shrink-0">
          <div>
            <h3 class="text-white font-bold text-base">📅 Settlement Schedule</h3>
            <p class="text-white text-xs mt-0.5">
              <span class="text-white font-medium">{{ settlementModal.label }}</span>
              · Total:
              <span class="text-white font-semibold">{{ formatNum(settlementModal.totalAmount) }} {{ company.currency }}</span>
              · Scheduled:
              <span :class="settlementScheduledTotal > settlementModal.totalAmount ? 'text-mp-danger' : 'text-mp-success'" class="font-semibold">
                {{ formatNum(settlementScheduledTotal) }} {{ company.currency }}
              </span>
            </p>
          </div>
          <button @click="closeSettlement"
            class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-white hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Remaining warning -->
        <div v-if="settlementRemaining !== 0"
          :class="['mx-6 mt-4 px-4 py-2.5 rounded-lg text-xs flex items-center gap-2',
            settlementRemaining > 0 ? 'bg-mp-warning/40 border border-mp-warning text-mp-warning'
                                    : 'bg-mp-danger/40 border border-mp-danger text-mp-danger']">
          <span>{{ settlementRemaining > 0 ? '⚠️' : '🔴' }}</span>
          <span v-if="settlementRemaining > 0">
            Unscheduled: <strong>{{ formatNum(settlementRemaining) }} {{ company.currency }}</strong> remaining to assign across months.
          </span>
          <span v-else>
            Over-scheduled by <strong>{{ formatNum(Math.abs(settlementRemaining)) }} {{ company.currency }}</strong> — reduce some months.
          </span>
        </div>
        <div v-else-if="settlementModal.rows.length > 0"
          class="mx-6 mt-4 px-4 py-2.5 rounded-lg text-xs bg-mp-success/40 border border-mp-success text-white flex items-center gap-2">
          ✅ Fully scheduled — amounts match perfectly.
        </div>

        <!-- Month rows -->
        <div class="overflow-y-auto flex-1 px-6 py-4">
          <div v-if="settlementModal.loading" class="text-center py-8 text-white text-sm">Loading...</div>
          <div v-else>
            <div class="grid grid-cols-12 gap-2 text-xs font-semibold text-white uppercase tracking-widest mb-3 px-1">
              <div class="col-span-3">Month</div>
              <div class="col-span-4 text-right">Amount ({{ company.currency }})</div>
              <div class="col-span-5">Notes</div>
            </div>
            <div v-for="(row, idx) in settlementModal.rows" :key="idx"
              class="grid grid-cols-12 gap-2 mb-2 items-center">
              <!-- Month label (read-only — fixed 12 months) -->
              <div class="col-span-3">
                <div class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white">
                  {{ row.label }}
                </div>
              </div>
              <!-- Amount -->
              <div class="col-span-4">
                <input v-model.number="row.amount" type="number" min="0" step="0.01"
                  placeholder="0.00"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white text-right focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
              </div>
              <!-- Notes -->
              <div class="col-span-5">
                <input v-model="row.notes" type="text" placeholder="Optional note..."
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-600 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-mp-border flex-shrink-0">
          <div v-if="settlementModal.saveError"
            class="mb-3 px-3 py-2 bg-mp-danger/50 border border-mp-danger rounded-lg text-mp-danger text-xs">
            ❌ {{ settlementModal.saveError }}
          </div>
          <div class="flex items-center justify-between">
          <button @click="clearSettlement"
            class="text-xs text-white hover:text-mp-danger transition-colors">
            Clear all
          </button>
          <div class="flex gap-3">
            <button @click="closeSettlement"
              class="px-4 py-2 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
              Cancel
            </button>
            <button @click="saveSettlement" :disabled="settlementModal.saving"
              class="px-5 py-2 rounded-lg bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 text-white text-sm font-semibold transition-colors flex items-center gap-2">
              <svg v-if="settlementModal.saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ settlementModal.saving ? 'Saving...' : 'Save Schedule' }}
            </button>
          </div>
          </div>
        </div>

      </div>
    </div>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:           { type: Object, default: () => ({ id: null, name: '', currency: 'USD' }) },
  statement:         { type: Object, default: null },
  existingSections:  { type: Array, default: () => [] },
  incomeSections:    { type: Array, default: () => [] },
  balanceSections:   { type: Array, default: () => [] },
  cashflowSections:  { type: Array, default: () => [] },
  // Line item IDs with existing schedules (passed from controller when editing)
  lineItemsWithSchedules: { type: Array, default: () => [] },
})

const isEditing = computed(() => !!props.statement)
const saving    = ref(false)
const errors    = ref({})
const activeTab = ref('income')
const priorBalanceItems = ref({})
const cfAutoMessage     = ref('Tag balance sheet rows with O/I/F, then auto-calculate cash flow from period changes.')

const cfCategories = [
  { key: 'operating',  short: 'O', label: 'Operating',  activeClass: 'bg-mp-teal/30 border-mp-teal text-mp-teal' },
  { key: 'investing',  short: 'I', label: 'Investing',  activeClass: 'bg-mp-gold/30 border-mp-gold text-mp-gold' },
  { key: 'financing',  short: 'F', label: 'Financing',  activeClass: 'bg-mp-success/30 border-mp-success text-mp-success' },
]

const tabs = [
  { key: 'income',        label: 'Income Statement', icon: '📊' },
  { key: 'balance_sheet', label: 'Balance Sheet',    icon: '⚖️' },
  { key: 'cashflow',      label: 'Cash Flow',        icon: '💧' },
]

// ── FORM STATE ──
const form = ref({
  period_from: props.statement?.period_from ?? '',
  period_to:   props.statement?.period_to   ?? '',
  status:      props.statement?.status      ?? 'draft',
  notes:       props.statement?.notes       ?? '',
})

// ── LINE ITEMS DATA STORE ──
// Structure: { income: { sales_revenue: [{label, amount}, ...], ... }, balance_sheet: {...}, cashflow: {...} }
const lineItems = ref({ income: {}, balance_sheet: {}, cashflow: {} })

// Initialise empty arrays for each non-computed section
function initSections() {
  props.incomeSections.forEach(s => {
    if (!s.computed) lineItems.value.income[s.key] = []
  })
  props.balanceSections.forEach(s => {
    if (!s.computed) lineItems.value.balance_sheet[s.key] = []
  })
  props.cashflowSections.forEach(s => {
    if (!s.computed) lineItems.value.cashflow[s.key] = []
  })
}

// If editing, populate from existingSections
function populateFromExisting() {
  if (!props.existingSections) return
  // Convert to Set of numbers for reliable comparison
  const scheduledIds = new Set((props.lineItemsWithSchedules ?? []).map(id => Number(id)))
  props.existingSections.forEach(sec => {
    const type = sec.statement_type
    const key  = sec.section_key
    if (!sec.is_computed && sec.line_items?.length) {
      lineItems.value[type][key] = sec.line_items.map(li => ({
        id:           li.id,
        label:        li.label,
        amount:       li.amount,
        cf_category:  li.cf_category ?? defaultCfCategory(type === 'balance_sheet' ? key : null),
        _hasSchedule: scheduledIds.has(Number(li.id)),
        _auto:        false,
      }))
    }
  })
}

initSections()
populateFromExisting()

// Fetch prior-period balance sheet when period_from changes
watch(() => form.value.period_from, async (date) => {
  if (!date) {
    priorBalanceItems.value = {}
    return
  }
  try {
    const params = new URLSearchParams({ before: date })
    if (props.statement?.id) params.set('exclude_id', props.statement.id)
    const res = await fetch(`/portfolio-companies/${props.company.id}/financial-statements/prior-balance?${params}`)
    const data = await res.json()
    priorBalanceItems.value = data.items ?? {}
  } catch {
    priorBalanceItems.value = {}
  }
}, { immediate: true })

// ── HELPERS ──
function defaultCfCategory(sectionKey) {
  if (!sectionKey) return null
  if (['current_assets', 'current_liabilities'].includes(sectionKey)) return 'operating'
  if (sectionKey === 'non_current_assets') return 'investing'
  if (['non_current_liabilities', 'equity'].includes(sectionKey)) return 'financing'
  return null
}

function isCashLine(label) {
  return /\b(cash|bank)\b/i.test(label || '')
}

function isAssetSection(key) {
  return ['current_assets', 'non_current_assets'].includes(key)
}

function autoCalculateCashFlow() {
  const prior = priorBalanceItems.value ?? {}
  const generated = { cfo: [], cfi: [], cff: [] }
  let taggedCount = 0

  props.balanceSections.filter(s => !s.computed).forEach(section => {
    getSectionItems('balance_sheet', section.key).forEach(item => {
      if (!item.cf_category || !item.label?.trim() || isCashLine(item.label)) return
      taggedCount++
      const priorKey = `${section.key}::${item.label}`
      const priorAmt = prior[priorKey] ?? 0
      const current  = parseFloat(item.amount) || 0
      const delta    = current - priorAmt
      if (Math.abs(delta) < 0.01) return

      const cfImpact = isAssetSection(section.key) ? -delta : delta
      const targetKey = item.cf_category === 'operating' ? 'cfo'
        : item.cf_category === 'investing' ? 'cfi' : 'cff'

      generated[targetKey].push({
        label: `Δ ${item.label}`,
        amount: Math.round(cfImpact * 100) / 100,
        cf_category: null,
        _auto: true,
      })
    })
  })

  const netProfit = allTotals.value['net_profit']
  if (netProfit && Math.abs(netProfit) >= 0.01) {
    generated.cfo.unshift({
      label: 'Net Profit (Income Statement)',
      amount: netProfit,
      cf_category: null,
      _auto: true,
    })
  }

  ;['cfo', 'cfi', 'cff'].forEach(key => {
    const manual = getSectionItems('cashflow', key).filter(i => !i._auto)
    lineItems.value.cashflow[key] = [...generated[key], ...manual]
  })

  if (!form.value.period_from) {
    cfAutoMessage.value = 'Set a statement period first to compare against the prior period.'
  } else if (Object.keys(prior).length === 0) {
    cfAutoMessage.value = 'No prior balance sheet found — cash flow rows generated from net profit only (if available).'
  } else if (taggedCount === 0) {
    cfAutoMessage.value = 'Tag balance sheet rows with O/I/F to auto-calculate cash flow from changes.'
  } else {
    cfAutoMessage.value = `Auto-calculated from ${taggedCount} tagged balance sheet row(s) vs prior period.`
  }
}

function goToCashFlow() {
  autoCalculateCashFlow()
  activeTab.value = 'cashflow'
}
function getSectionItems(type, key) {
  return lineItems.value[type]?.[key] ?? []
}

function addLineItem(type, key) {
  if (!lineItems.value[type][key]) lineItems.value[type][key] = []
  lineItems.value[type][key].push({
    label: '',
    amount: 0,
    cf_category: type === 'balance_sheet' ? defaultCfCategory(key) : null,
    _auto: false,
  })
}

function removeLineItem(type, key, idx) {
  lineItems.value[type][key].splice(idx, 1)
}

// ── TOTALS (live computed) ──
// Build a flat map of { section_key: total } for all 3 statement types combined
const allTotals = computed(() => {
  const totals = {}

  const allSections = [
    ...props.incomeSections.map(s => ({ ...s, type: 'income' })),
    ...props.balanceSections.map(s => ({ ...s, type: 'balance_sheet' })),
    ...props.cashflowSections.map(s => ({ ...s, type: 'cashflow' })),
  ]

  // First pass: sum line items for non-computed
  allSections.forEach(s => {
    if (!s.computed) {
      const items = lineItems.value[s.type]?.[s.key] ?? []
      totals[s.key] = items.reduce((sum, li) => sum + (parseFloat(li.amount) || 0), 0)
    }
  })

  // Multiple passes for computed sections (handles nested computed like gross_profit → ebitda → ebt)
  for (let pass = 0; pass < 5; pass++) {
    allSections.forEach(s => {
      if (s.computed && s.from) {
        let result = 0
        let allResolved = true
        s.from.forEach(part => {
          if (totals[part.key] === undefined) { allResolved = false; return }
          result += totals[part.key] * part.sign
        })
        if (allResolved) totals[s.key] = result
      }
    })
  }

  return totals
})

function getSectionTotal(type, key) {
  return allTotals.value[key] ?? 0
}

// ── BALANCE SHEET CHECK ──
const bsCheckDiff = computed(() => {
  const ta  = allTotals.value['total_assets']
  const tle = allTotals.value['total_liabilities_equity']
  if (ta === undefined || tle === undefined) return null
  if (ta === 0 && tle === 0) return null
  return Math.abs(ta - tle)
})

// ── COMPLETED TABS (green checkmarks) ──
const completedTabs = computed(() => {
  const done = []
  if (props.incomeSections.some(s => !s.computed && (lineItems.value.income[s.key]?.length ?? 0) > 0)) done.push('income')
  if (props.balanceSections.some(s => !s.computed && (lineItems.value.balance_sheet[s.key]?.length ?? 0) > 0)) done.push('balance_sheet')
  if (props.cashflowSections.some(s => !s.computed && (lineItems.value.cashflow[s.key]?.length ?? 0) > 0)) done.push('cashflow')
  return done
})

// ── FORMULA DISPLAY ──
function computedFormula(section) {
  if (!section.from) return ''
  return section.from.map(p => `${p.sign > 0 ? '+' : '−'} ${p.key.replace(/_/g, ' ')}`).join('  ').replace(/^\+ /, '')
}

// ── SETTLEMENT SCHEDULE ──
const settlementSections = ['current_assets', 'current_liabilities', 'non_current_liabilities']

const settlementModal = ref({
  open:        false,
  loading:     false,
  saving:      false,
  saveError:   '',
  lineItemId:  null,
  label:       '',
  totalAmount: 0,
  sectionKey:  '',
  rows:        [], // [{ month: 'YYYY-MM', label: 'Jan 2026', amount: 0, notes: '' }]
})

const settlementScheduledTotal = computed(() =>
  settlementModal.value.rows.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0)
)

const settlementRemaining = computed(() =>
  settlementModal.value.totalAmount - settlementScheduledTotal.value
)

function buildForecastMonths(periodTo) {
  const months = []
  const start = new Date(periodTo)
  start.setDate(1)
  start.setMonth(start.getMonth() + 1)
  for (let i = 0; i < 12; i++) {
    const d = new Date(start)
    d.setMonth(d.getMonth() + i)
    const yyyy = d.getFullYear()
    const mm   = String(d.getMonth() + 1).padStart(2, '0')
    months.push({
      month: `${yyyy}-${mm}`,
      label: d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }),
    })
  }
  return months
}

async function openSettlement(item, sectionKey) {
  settlementModal.value = {
    open:        true,
    loading:     true,
    saving:      false,
    saveError:   '',
    lineItemId:  item.id,
    label:       item.label || 'Line Item',
    totalAmount: parseFloat(item.amount) || 0,
    sectionKey,
    rows:        [],
  }

  // Build 12-month skeleton from period_to
  const skeleton = buildForecastMonths(props.statement.period_to)

  try {
    const res = await fetch(
      `/portfolio-companies/${props.company.id}/cash-forecast/settlement/${item.id}`,
      { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
    )
    // GET requests don't need CSRF
    const data = await res.json()

    // Merge existing schedule into skeleton
    const scheduleMap = {}
    ;(data.schedule ?? []).forEach(s => { scheduleMap[s.month] = s })

    settlementModal.value.rows = skeleton.map(sk => ({
      month:  sk.month,
      label:  sk.label,
      amount: scheduleMap[sk.month]?.amount ?? 0,
      notes:  scheduleMap[sk.month]?.notes  ?? '',
    }))
  } catch (e) {
    settlementModal.value.rows = skeleton.map(sk => ({ ...sk, amount: 0, notes: '' }))
  } finally {
    settlementModal.value.loading = false
  }
}

function closeSettlement() {
  settlementModal.value.open = false
}

function clearSettlement() {
  settlementModal.value.rows.forEach(r => { r.amount = 0; r.notes = '' })
}

async function saveSettlement() {
  settlementModal.value.saving  = true
  settlementModal.value.saveError = ''

  const nonZeroRows = settlementModal.value.rows.filter(r => parseFloat(r.amount) > 0)

  const payload = {
    line_item_id: settlementModal.value.lineItemId,
    schedule: settlementModal.value.rows.map(r => ({
      month:  r.month,
      amount: parseFloat(r.amount) || 0,
      notes:  r.notes || null,
    })),
  }

  try {
    // Laravel uses XSRF-TOKEN cookie — must decode it and send as X-XSRF-TOKEN
    const xsrfCookie = document.cookie.split(';')
      .map(c => c.trim())
      .find(c => c.startsWith('XSRF-TOKEN='))
    const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.split('=').slice(1).join('=')) : ''

    const res = await fetch(
      `/portfolio-companies/${props.company.id}/cash-forecast/settlement`,
      {
        method:  'POST',
        headers: {
          'Content-Type':     'application/json',
          'Accept':           'application/json',
          'X-XSRF-TOKEN':     xsrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      }
    )

    const data = await res.json().catch(() => ({}))

    if (res.ok && data.ok) {
      const items = getSectionItems('balance_sheet', settlementModal.value.sectionKey)
      const item  = items.find(i => i.id === settlementModal.value.lineItemId)
      if (item) item._hasSchedule = nonZeroRows.length > 0
      closeSettlement()
    } else {
      settlementModal.value.saveError = data.message || data.error || `Server error (${res.status}). Check Laravel log.`
    }
  } catch (e) {
    settlementModal.value.saveError = 'Network error: ' + e.message
  } finally {
    settlementModal.value.saving = false
  }
}

// ── SUBMIT ──
function submitForm() {
  errors.value = {}

  if (!form.value.period_from) { errors.value.period_from = 'Required'; return }
  if (!form.value.period_to)   { errors.value.period_to   = 'Required'; return }
  if (form.value.period_to <= form.value.period_from) {
    errors.value.period_to = 'Must be after the start date'
    return
  }

  saving.value = true

  // Build sections payload: { section_key: { line_items: [...] } }
  const sectionsPayload = {}
  const allTypes = ['income', 'balance_sheet', 'cashflow']
  allTypes.forEach(type => {
    Object.entries(lineItems.value[type]).forEach(([key, items]) => {
      sectionsPayload[key] = { line_items: items }
    })
  })

  const payload = {
    ...form.value,
    sections: sectionsPayload,
  }

  const url = isEditing.value
    ? `/portfolio-companies/${props.company.id}/financial-statements/${props.statement.id}`
    : `/portfolio-companies/${props.company.id}/financial-statements`

  const method = isEditing.value ? 'put' : 'post'

  router[method](url, payload, {
    onError:  (e) => { errors.value = e; saving.value = false },
    onFinish: ()  => { saving.value = false },
  })
}

// ── FORMAT ──
function formatNum(val) {
  if (val === null || val === undefined) return '—'
  return parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}
</script>