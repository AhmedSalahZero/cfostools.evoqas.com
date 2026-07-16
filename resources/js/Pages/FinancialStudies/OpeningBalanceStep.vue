<template>
  <Head :title="`Opening Balance — ${study.name}`" />
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
                i === 6 ? 'bg-mp-teal text-white' : 'text-white'
              ]">
                <span :class="[
                  'w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0',
                  i === 6 ? 'bg-white/20 text-white' : 'bg-mp-card-hover text-white'
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
              <h1 class="text-2xl font-bold text-white">Step 7 — Opening Balance Sheet</h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }} · {{ study.name }}</p>
            </div>
            <div class="flex items-center gap-3">
              <Link :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/fixed-assets`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                ← Back
              </Link>
              <button type="button" @click="save" :disabled="saving"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors disabled:opacity-50">
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

      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- ── AS OF DATE ── -->
        <div class="bg-mp-card rounded-xl border border-mp-border p-5">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Opening Balance Details</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-white mb-1.5">As of Date *</label>
              <input v-model="form.as_of_date" type="date"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
            </div>
            <div>
              <label class="block text-xs text-white mb-1.5">Notes (optional)</label>
              <input v-model="form.notes" type="text" placeholder="e.g. Audited figures, management accounts..."
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"/>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════
             ASSETS CARD
        ════════════════════════════════════════════════ -->
        <div class="rounded-xl border border-mp-border bg-mp-card overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 bg-mp-card-hover/60">
            <h3 class="font-bold text-sm text-white">📦 ASSETS</h3>
            <span class="text-sm font-bold text-mp-success">{{ formatNum(totalAssets) }} {{ currency }}</span>
          </div>

          <!-- ── NON-CURRENT ASSETS ── -->
          <div class="border-b border-mp-border">
            <div class="flex items-center justify-between px-5 py-2.5 bg-mp-card-hover/20">
              <span class="text-xs font-semibold text-white uppercase tracking-wide">Non-Current Assets</span>
              <span class="text-xs text-white">{{ formatNum(nonCurrentAssetsTotal) }} {{ currency }}</span>
            </div>

            <!-- Fixed Assets subsection -->
            <div class="px-5 py-4 border-b border-mp-border/50">
              <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-white uppercase tracking-wide">Fixed Assets</span>
                <div class="flex items-center gap-3">
                  <span class="text-xs text-white">Net FA: {{ formatNum(totalNetFA) }} {{ currency }}</span>
                  <button @click="addFixedAsset"
                    class="text-xs bg-mp-page hover:bg-mp-teal text-white hover:text-white px-2.5 py-1 rounded-lg transition-colors">+ Add Asset</button>
                </div>
              </div>

              <div v-if="!fixedAssets.length" class="text-center py-4 text-white text-sm">No fixed assets yet — click + Add Asset</div>

              <div v-for="(fa, idx) in fixedAssets" :key="idx"
                class="bg-mp-card-hover/40 border border-mp-border/50 rounded-xl p-4 mb-3">

                <!-- Row 1: Name + remove -->
                <div class="flex items-center justify-between mb-3">
                  <input v-model="fa.label" type="text" placeholder="e.g. Machinery & Equipment"
                    class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-mp-teal mr-3"/>
                  <button @click="fixedAssets.splice(idx, 1)"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-page hover:bg-mp-danger text-white hover:text-white transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>

                <!-- Row 2: FA fields grid -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3">
                  <div>
                    <label class="block text-xs text-white mb-1">Gross Fixed Assets</label>
                    <div class="relative">
                      <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                      <input v-model.number="fa.gross_amount" type="number" min="0" step="0.01" placeholder="0"
                        class="w-full bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs text-white mb-1">Accumulated Dep.</label>
                    <div class="relative">
                      <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                      <input v-model.number="fa.accum_dep" type="number" min="0" step="0.01" placeholder="0"
                        class="w-full bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs text-white mb-1">Monthly Dep. Amount</label>
                    <div class="relative">
                      <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                      <input v-model.number="fa.monthly_dep" type="number" min="0" step="0.01" placeholder="0"
                        class="w-full bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                    </div>
                  </div>
                  <div>
                    <label class="block text-xs text-white mb-1">Remaining Dep. Months</label>
                    <input v-model.number="fa.dep_months_remaining" type="number" min="0" step="1" placeholder="0"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white text-right focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  </div>
                </div>

                <!-- Row 3: Net FA display + Dep Allocation -->
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-4">
                    <div class="text-xs text-white">
                      Net Fixed Assets:
                      <span class="text-white font-semibold ml-1">{{ formatNum((fa.gross_amount || 0) - (fa.accum_dep || 0)) }} {{ currency }}</span>
                    </div>
                    <div class="text-xs text-white">|</div>
                    <div class="text-xs text-white">
                      Monthly Dep:
                      <span class="text-white font-medium ml-1">
                        Mfg {{ fa.dep_mfg_pct || 0 }}% / Admin {{ 100 - (fa.dep_mfg_pct || 0) }}%
                      </span>
                    </div>
                  </div>
                  <button @click="openDepAllocModal(idx)"
                    class="text-xs bg-mp-teal-subtle/50 hover:bg-mp-teal-dark border border-mp-teal text-white px-3 py-1.5 rounded-lg transition-colors">
                    ⚙ Dep Allocation
                  </button>
                </div>
              </div>

              <!-- Net FA subtotal -->
              <div v-if="fixedAssets.length" class="flex justify-end mt-2">
                <div class="text-xs text-white">
                  Total Net Fixed Assets:
                  <span class="text-white font-bold ml-1">{{ formatNum(totalNetFA) }} {{ currency }}</span>
                </div>
              </div>
            </div>

            <!-- Other Non-Current Assets (free rows) -->
            <div class="px-5 py-4">
              <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-white uppercase tracking-wide">Other Non-Current Assets</span>
                <button @click="addRow('other_non_current')"
                  class="text-xs bg-mp-page hover:bg-mp-teal text-white hover:text-white px-2.5 py-1 rounded-lg transition-colors">+ Add</button>
              </div>
              <div v-if="!sections.other_non_current.length" class="text-center py-3 text-white text-sm">No rows yet</div>
              <div v-for="(row, idx) in sections.other_non_current" :key="idx" class="flex items-center gap-3 mb-2">
                <input v-model="row.label" type="text" placeholder="e.g. Long-term Deposits"
                  class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                <div class="relative">
                  <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                  <input v-model.number="row.amount" type="number" min="0" step="0.01" placeholder="0"
                    class="bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right w-40 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                </div>
                <button @click="removeRow('other_non_current', idx)"
                  class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors flex-shrink-0">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- ── CURRENT ASSETS ── -->
          <div>
            <div class="flex items-center justify-between px-5 py-2.5 bg-mp-card-hover/20">
              <span class="text-xs font-semibold text-white uppercase tracking-wide">Current Assets</span>
              <span class="text-xs text-white">{{ formatNum(currentAssetsTotal) }} {{ currency }}</span>
            </div>

            <!-- Inventory — auto-loaded, read-only -->
            <div class="px-5 py-4 border-b border-mp-border/50">
              <p class="text-xs font-semibold text-white uppercase tracking-wide mb-3">Inventories <span class="text-white font-normal normal-case">(auto-loaded from Step 2 &amp; 3)</span></p>
              <div v-if="!inventoryRows.length" class="text-center py-3 text-white text-sm">No inventory products defined in previous steps.</div>
              <div v-for="(inv, idx) in inventoryRows" :key="idx" class="flex items-center gap-3 mb-2">
                <div class="flex items-center gap-2 flex-1">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                    :class="inv.type === 'manufacturing_fg' ? 'bg-mp-teal-subtle/50 text-white' : inv.type === 'manufacturing_rm' ? 'bg-mp-gold/50 text-white' : 'bg-mp-gold/50 text-white'">
                    {{ inv.type === 'manufacturing_fg' ? 'FG' : inv.type === 'manufacturing_rm' ? 'RM' : 'Trading' }}
                  </span>
                  <span class="text-sm text-white">{{ inv.label }}</span>
                </div>
                <div class="relative">
                  <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                  <input v-model.number="inv.amount" type="number" min="0" step="0.01" placeholder="0"
                    class="bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right w-44 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                </div>
                <div class="w-8 text-center text-white text-xs">🔒</div>
              </div>
            </div>

            <!-- Cash & Banks — dedicated fixed row → seeds opening cash in Cash Flow -->
            <div class="px-5 py-4 border-b border-mp-border/50">
              <p class="text-xs font-semibold text-white uppercase tracking-wide mb-3">
                Cash & Banks
                <span class="text-white font-normal normal-case ml-1">— seeds opening cash balance in Cash Flow Statement</span>
              </p>
              <div class="flex items-center gap-3">
                <span class="flex-1 text-sm text-white">Cash & Bank Balances</span>
                <div class="relative">
                  <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                  <input v-model.number="form.cash_bank" type="number" min="0" step="0.01" placeholder="0"
                    class="bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right w-44 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                </div>
                <div class="w-8"></div><!-- spacer to align with rows that have remove button -->
              </div>
            </div>

            <!-- Other Current Assets (with Settlement button) -->
            <div class="px-5 py-4">
              <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-white uppercase tracking-wide">Other Current Assets</span>
                <button @click="addRow('current_assets')"
                  class="text-xs bg-mp-page hover:bg-mp-teal text-white hover:text-white px-2.5 py-1 rounded-lg transition-colors">+ Add</button>
              </div>
              <div v-if="!sections.current_assets.length" class="text-center py-3 text-white text-sm">No rows yet</div>
              <div v-for="(row, idx) in sections.current_assets" :key="idx" class="mb-3">
                <div class="flex items-center gap-3">
                  <input v-model="row.label" type="text" placeholder="e.g. Trade Receivables, Cash & Bank"
                    class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  <div class="relative">
                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                    <input v-model.number="row.amount" type="number" min="0" step="0.01" placeholder="0"
                      class="bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right w-40 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  </div>
                  <button @click="openSettlementModal('current_assets', idx)"
                    :class="[
                      'text-xs px-3 py-2 rounded-lg border transition-colors flex-shrink-0 font-medium',
                      row.schedule && row.schedule.length
                        ? 'bg-mp-teal-subtle/50 border-mp-teal text-white hover:bg-mp-teal-subtle'
                        : 'bg-mp-page border-mp-border text-white hover:bg-mp-muted hover:text-white'
                    ]">
                    📅 Settlement
                  </button>
                  <button @click="removeRow('current_assets', idx)"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
                <!-- Settlement mini-summary -->
                <div v-if="row.schedule && row.schedule.length" class="ml-1 mt-1.5 pl-3 border-l-2 border-mp-teal/60">
                  <div class="flex items-center gap-2">
                    <span class="text-xs text-white font-semibold">Scheduled: {{ formatNum(scheduleTotal(row.schedule)) }} · Remaining: {{ formatNum((row.amount || 0) - scheduleTotal(row.schedule)) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════
             LIABILITIES CARD
        ════════════════════════════════════════════════ -->
        <div class="rounded-xl border border-mp-border bg-mp-card overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 bg-mp-card-hover/60">
            <h3 class="font-bold text-sm text-white">📋 LIABILITIES</h3>
            <span class="text-sm font-bold text-mp-danger">{{ formatNum(totalLiabilities) }} {{ currency }}</span>
          </div>

          <!-- Long-Term Liabilities -->
          <div class="border-b border-mp-border">
            <div class="flex items-center justify-between px-5 py-2.5 bg-mp-card-hover/20">
              <span class="text-xs font-semibold text-white uppercase tracking-wide">Long-Term Liabilities</span>
              <div class="flex items-center gap-3">
                <span class="text-xs text-white">{{ formatNum(sectionTotal('long_term_liabilities')) }} {{ currency }}</span>
                <button @click="addRow('long_term_liabilities')"
                  class="text-xs bg-mp-page hover:bg-mp-teal text-white hover:text-white px-2.5 py-1 rounded-lg transition-colors">+ Add</button>
              </div>
            </div>
            <div class="px-5 py-4">
              <div v-if="!sections.long_term_liabilities.length" class="text-center py-3 text-white text-sm">No rows yet</div>
              <div v-for="(row, idx) in sections.long_term_liabilities" :key="idx" class="mb-3">
                <div class="flex items-center gap-3">
                  <input v-model="row.label" type="text" placeholder="e.g. Long-term Bank Loans"
                    class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  <div class="relative">
                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                    <input v-model.number="row.amount" type="number" min="0" step="0.01" placeholder="0"
                      class="bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right w-40 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  </div>
                  <button @click="openSettlementModal('long_term_liabilities', idx)"
                    :class="[
                      'text-xs px-3 py-2 rounded-lg border transition-colors flex-shrink-0 font-medium',
                      row.schedule && row.schedule.length
                        ? 'bg-mp-teal-subtle/50 border-mp-teal text-white hover:bg-mp-teal-subtle'
                        : 'bg-mp-page border-mp-border text-white hover:bg-mp-muted hover:text-white'
                    ]">
                    📅 Settlement
                  </button>
                  <button @click="removeRow('long_term_liabilities', idx)"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
                <div v-if="row.schedule && row.schedule.length" class="ml-1 mt-1.5 pl-3 border-l-2 border-mp-teal/60">
                  <span class="text-xs text-white font-semibold">Scheduled: {{ formatNum(scheduleTotal(row.schedule)) }} · Remaining: {{ formatNum((row.amount || 0) - scheduleTotal(row.schedule)) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Current Liabilities -->
          <div>
            <div class="flex items-center justify-between px-5 py-2.5 bg-mp-card-hover/20">
              <span class="text-xs font-semibold text-white uppercase tracking-wide">Current Liabilities</span>
              <div class="flex items-center gap-3">
                <span class="text-xs text-white">{{ formatNum(sectionTotal('current_liabilities')) }} {{ currency }}</span>
                <button @click="addRow('current_liabilities')"
                  class="text-xs bg-mp-page hover:bg-mp-teal text-white hover:text-white px-2.5 py-1 rounded-lg transition-colors">+ Add</button>
              </div>
            </div>
            <div class="px-5 py-4">
              <div v-if="!sections.current_liabilities.length" class="text-center py-3 text-white text-sm">No rows yet</div>
              <div v-for="(row, idx) in sections.current_liabilities" :key="idx" class="mb-3">
                <div class="flex items-center gap-3">
                  <input v-model="row.label" type="text" placeholder="e.g. Trade Payables, Accruals"
                    class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  <div class="relative">
                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                    <input v-model.number="row.amount" type="number" min="0" step="0.01" placeholder="0"
                      class="bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right w-40 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
                  </div>
                  <button @click="openSettlementModal('current_liabilities', idx)"
                    :class="[
                      'text-xs px-3 py-2 rounded-lg border transition-colors flex-shrink-0 font-medium',
                      row.schedule && row.schedule.length
                        ? 'bg-mp-teal-subtle/50 border-mp-teal text-white hover:bg-mp-teal-subtle'
                        : 'bg-mp-page border-mp-border text-white hover:bg-mp-muted hover:text-white'
                    ]">
                    📅 Settlement
                  </button>
                  <button @click="removeRow('current_liabilities', idx)"
                    class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
                <div v-if="row.schedule && row.schedule.length" class="ml-1 mt-1.5 pl-3 border-l-2 border-mp-teal/60">
                  <span class="text-xs text-white font-semibold">Scheduled: {{ formatNum(scheduleTotal(row.schedule)) }} · Remaining: {{ formatNum((row.amount || 0) - scheduleTotal(row.schedule)) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════════════════════════
             EQUITY CARD
        ════════════════════════════════════════════════ -->
        <div class="rounded-xl border border-mp-border bg-mp-card overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3 bg-mp-card-hover/60">
            <h3 class="font-bold text-sm text-white">🏛 EQUITY</h3>
            <span class="text-sm font-bold text-white">{{ formatNum(totalEquity) }} {{ currency }}</span>
          </div>

          <!-- 3 Fixed equity rows — directly mapped to Results engine -->
          <div class="px-5 py-4 border-b border-mp-border/50 space-y-3">
            <p class="text-xs font-semibold text-white uppercase tracking-wide mb-1">Opening Equity Components</p>

            <!-- Paid-up Capital -->
            <div class="flex items-center gap-3">
              <div class="flex-1">
                <span class="text-sm text-white">Paid-up Capital</span>
                <p class="text-xs text-white mt-0.5">Seeds initial equity in Balance Sheet · used for Legal Reserve cap calculation</p>
              </div>
              <div class="relative">
                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                <input v-model.number="form.paid_up_capital" type="number" min="0" step="0.01" placeholder="0"
                  class="bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right w-44 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
              </div>
              <div class="w-8"></div>
            </div>

            <!-- Legal Reserve -->
            <div class="flex items-center gap-3">
              <div class="flex-1">
                <span class="text-sm text-white">Legal Reserve (Carried Forward)</span>
                <p class="text-xs text-white mt-0.5">Pre-existing reserve balance · study adds 5% of annual profit until 50% of paid-up capital</p>
              </div>
              <div class="relative">
                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                <input v-model.number="form.legal_reserve" type="number" min="0" step="0.01" placeholder="0"
                  class="bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right w-44 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
              </div>
              <div class="w-8"></div>
            </div>

            <!-- Retained Earnings -->
            <div class="flex items-center gap-3">
              <div class="flex-1">
                <span class="text-sm text-white">Retained Earnings (Carried Forward)</span>
                <p class="text-xs text-white mt-0.5">Opening retained earnings — cumulative profits from prior periods</p>
              </div>
              <div class="relative">
                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                <input v-model.number="form.retained_earnings" type="number" step="0.01" placeholder="0"
                  class="bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right w-44 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
              </div>
              <div class="w-8"></div>
            </div>
          </div>

          <!-- Additional free equity rows (optional) -->
          <div class="px-5 py-4">
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-semibold text-white uppercase tracking-wide">Other Equity Items (optional)</span>
              <button @click="addRow('equity')"
                class="text-xs bg-mp-page hover:bg-mp-teal text-white hover:text-white px-2.5 py-1 rounded-lg transition-colors">+ Add</button>
            </div>
            <div v-if="!sections.equity.length" class="text-center py-2 text-white text-sm">No additional rows</div>
            <div v-for="(row, idx) in sections.equity" :key="idx" class="flex items-center gap-3 mb-2">
              <input v-model="row.label" type="text" placeholder="e.g. Share Premium, Treasury Shares"
                class="flex-1 bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
              <div class="relative">
                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                <input v-model.number="row.amount" type="number" step="0.01" placeholder="0"
                  class="bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-2 text-sm text-white text-right w-40 focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
              </div>
              <button @click="removeRow('equity', idx)"
                class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors flex-shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- ── BALANCE CHECK CARD ── -->
        <div :class="[
          'rounded-xl border px-6 py-4 flex items-center justify-between',
          isBalanced
            ? 'bg-mp-success/30 border-mp-success'
            : 'bg-mp-danger/30 border-mp-danger'
        ]">
          <div class="flex items-center gap-3">
            <span class="text-xl">{{ isBalanced ? '✅' : '⚠️' }}</span>
            <div>
              <p class="text-sm font-semibold" :class="isBalanced ? 'text-mp-success' : 'text-mp-danger'">
                {{ isBalanced ? 'Balance sheet is balanced' : 'Balance sheet does not balance' }}
              </p>
              <p class="text-xs mt-0.5" :class="isBalanced ? 'text-mp-success/70' : 'text-mp-danger/70'">
                <template v-if="isBalanced">Total Assets = Total Liabilities + Equity</template>
                <template v-else>Difference: {{ formatNum(Math.abs(balanceDiff)) }} {{ currency }} — please check your figures</template>
              </p>
            </div>
          </div>
          <div class="text-right hidden md:block">
            <div class="text-xs text-white space-y-0.5">
              <div>Assets: <span class="text-white font-medium">{{ formatNum(totalAssets) }}</span></div>
              <div>Liab + Equity: <span class="text-white font-medium">{{ formatNum(totalLiabilities + totalEquity) }}</span></div>
            </div>
          </div>
        </div>

        <!-- Save error -->
        <p v-if="saveError" class="text-mp-danger text-sm text-right">{{ saveError }}</p>

        <!-- Bottom action buttons -->
        <div class="flex items-center justify-between pb-8">
          <Link :href="`/portfolio-companies/${company.id}/financial-studies/${study.id}/fixed-assets`"
            class="flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back: Fixed Assets
          </Link>
          <button @click="save" :disabled="saving"
            class="flex items-center gap-2 px-6 py-2.5 rounded-lg bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 text-white text-sm font-medium transition-colors">
            <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            <span>{{ saving ? 'Saving...' : 'Save & Next: Financial Results' }}</span>
            <svg v-if="!saving" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
          </button>
        </div>

      </div><!-- /container -->
    </div>

    <!-- ════════════════════════════════════════════════
         DEPRECIATION ALLOCATION MODAL
    ════════════════════════════════════════════════ -->
    <div v-if="depAllocModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
      <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-sm shadow-2xl">
        <div class="flex items-center justify-between px-5 py-4 border-b border-mp-border">
          <h3 class="font-semibold text-white text-sm">Depreciation Allocation</h3>
          <button @click="depAllocModal.open = false" class="text-white hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="p-5 space-y-4">
          <p class="text-xs text-white">
            Split monthly depreciation between Manufacturing (COGS) and Admin (SG&A). Must total 100%.
          </p>
          <div>
            <label class="block text-xs text-white mb-1.5">Manufacturing (COGS) %</label>
            <input v-model.number="depAllocModal.mfg_pct" type="number" min="0" max="100" step="1" placeholder="0"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal"
              @input="depAllocModal.mfg_pct = Math.min(100, Math.max(0, depAllocModal.mfg_pct || 0))"/>
          </div>
          <div class="bg-mp-card-hover/50 rounded-lg px-4 py-3 flex justify-between text-sm">
            <span class="text-white">Admin (SG&A) %</span>
            <span class="font-semibold" :class="(100 - (depAllocModal.mfg_pct || 0)) >= 0 ? 'text-white' : 'text-mp-danger'">
              {{ 100 - (depAllocModal.mfg_pct || 0) }}%
            </span>
          </div>
          <div :class="[
            'text-xs text-center py-2 rounded-lg font-medium',
            (depAllocModal.mfg_pct || 0) + (100 - (depAllocModal.mfg_pct || 0)) === 100
              ? 'text-mp-success bg-mp-success/20'
              : 'text-mp-danger bg-mp-danger/20'
          ]">
            Total: 100% ✓
          </div>
        </div>
        <div class="flex items-center justify-end gap-3 px-5 py-4 border-t border-mp-border">
          <button @click="depAllocModal.open = false"
            class="px-4 py-2 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
            Cancel
          </button>
          <button @click="saveDepAlloc"
            class="px-4 py-2 rounded-lg bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium transition-colors">
            Save Allocation
          </button>
        </div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════
         SETTLEMENT SCHEDULE MODAL
    ════════════════════════════════════════════════ -->
    <div v-if="settlementModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
      <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-2xl shadow-2xl max-h-[85vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-mp-border flex-shrink-0">
          <div>
            <h3 class="font-semibold text-white text-sm">📅 Settlement Schedule</h3>
            <p class="text-xs text-white mt-0.5">{{ settlementModal.rowLabel || 'Unnamed item' }} — Balance: {{ formatNum(settlementModal.rowAmount) }} {{ currency }}</p>
          </div>
          <button @click="settlementModal.open = false" class="text-white hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <div class="overflow-y-auto flex-1 p-5">
          <p class="text-xs text-white mb-4">
            Enter when each portion of this balance will be collected / paid during the study period.
            Leave a month blank (0) if no settlement occurs that month.
          </p>

          <div class="space-y-2">
            <div v-for="(slot, i) in settlementModal.slots" :key="i"
              class="flex items-center gap-3">
              <span class="text-xs text-white w-20 flex-shrink-0 text-right">{{ slot.month }}</span>
              <div class="relative flex-1">
                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-white text-xs">{{ currency }}</span>
                <input v-model.number="slot.amount" type="number" min="0" step="0.01" placeholder="0"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg pl-9 pr-3 py-1.5 text-sm text-white text-right focus:outline-none focus:ring-1 focus:ring-mp-teal"/>
              </div>
              <span class="text-xs w-5 text-center" :class="slot.amount > 0 ? 'text-mp-success' : 'text-white'">
                {{ slot.amount > 0 ? '✓' : '–' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Totals footer -->
        <div class="px-5 py-3 border-t border-mp-border bg-mp-card-hover/30 flex-shrink-0">
          <div class="flex items-center justify-between text-sm">
            <div class="flex items-center gap-4 text-xs">
              <span class="text-white">Scheduled: <span class="text-white font-medium">{{ formatNum(settlementScheduledTotal) }}</span></span>
              <span :class="Math.abs(settlementModal.rowAmount - settlementScheduledTotal) < 1 ? 'text-mp-success' : 'text-white'" class="text-xs">
                Remaining: {{ formatNum(settlementModal.rowAmount - settlementScheduledTotal) }}
              </span>
            </div>
            <div class="flex items-center gap-2">
              <button @click="clearSettlement"
                class="px-3 py-1.5 rounded-lg bg-mp-page hover:bg-mp-muted text-white text-xs font-medium transition-colors">
                Clear All
              </button>
              <button @click="saveSettlement"
                class="px-4 py-1.5 rounded-lg bg-mp-teal hover:bg-mp-teal-dark text-white text-xs font-medium transition-colors">
                Save Schedule
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// ── Props ──────────────────────────────────────────────────────────
const props = defineProps({
  company:   Object,  // { id, name, currency, company_phase }
  study:     Object,  // { id, name, start_date, duration_years, duration_months }
  products:  Array,   // from Step 1 — product definitions
  projections: Object, // from Step 2 — includes beg_inv_amount per product
  cogsData:  Array,   // from Step 3 — includes beg_inv_value for trading/RM
  savedData: Object,  // previously saved opening_balance JSON, null if first visit
  studyMonths: Array, // ['Jan 2026', 'Feb 2026', ...] — timeline for settlement modal
})

// ── Wizard steps ──────────────────────────────────────────────────
const wizardSteps = ['Setup', 'Sales Projection', 'COGS', 'Manpower', 'Expenses', 'Fixed Assets', 'Opening Balance', 'Results']

const currency = computed(() => props.company?.currency || 'USD')

// ── State ──────────────────────────────────────────────────────────
const saving    = ref(false)
const saveError = ref('')

const form = ref({
  as_of_date:        '',
  notes:             '',
  cash_bank:         0,   // → openingCash in cash flow engine
  paid_up_capital:   0,   // → equityPaidUp base in BS
  legal_reserve:     0,   // → opening legal reserve (carried forward)
  retained_earnings: 0,   // → opening retained earnings
})

// Fixed Assets array (each has: label, gross_amount, accum_dep, monthly_dep, dep_months_remaining, dep_mfg_pct)
const fixedAssets = ref([])

// Free-entry sections
const sections = ref({
  other_non_current:    [],
  current_assets:       [],
  long_term_liabilities: [],
  current_liabilities:  [],
  equity:               [],
})

// Inventory rows — built from products/projections/cogsData (partially editable amount)
const inventoryRows = ref([])

// ── Build inventory rows from Step 1/2/3 data ─────────────────────
// Data structures (confirmed from source files):
//
// SalesProjection saves: { sales: { products: [ { name, beg_inv_amount, beg_inv_qty, ... }, ... ] } }
// CogsStep saves per product: { name, nature, raw_materials: [ { name, beg_inventory_value, ... } ], beginning_inventory_value (trading) }
//
function buildInventoryRows() {
  const rows    = []
  const products = props.products || []

  // SalesProjection data: props.projections = { sales: { products: [...] } }
  // The controller extracts it as projections = { products: [...] } (see resultsStep)
  // For openingBalanceStep we pass the raw projections so check both shapes:
  const salesProducts = props.projections?.products
    ?? props.projections?.sales?.products
    ?? []

  // CogsStep data: props.cogsData = [ { name, nature, raw_materials: [...], beginning_inventory_value } ]
  const cogsData = props.cogsData || []

  products.forEach((prod, pi) => {

    if (prod.nature === 'manufacturing') {

      // ── Finished Goods — from SalesProjection.vue ────────────────────
      // Field: salesData[i].beg_inv_amount (set in Section F of each product panel)
      const salesProd = salesProducts.find(p => p.name === prod.name) ?? salesProducts[pi] ?? null
      const fgAmount  = parseFloat(salesProd?.beg_inv_amount || 0)

      rows.push({
        type:         'manufacturing_fg',
        label:        `${prod.name} — Finished Goods Beginning Inventory`,
        amount:       fgAmount,
        _productName: prod.name,
        _readonly:    true,  // display note only — user confirmed this in Step 2
      })

      // ── Raw Materials — from CogsStep.vue ────────────────────────────
      // Field: cogsForm[pi].raw_materials[rmi].beg_inventory_value
      const cogsProd = cogsData.find(c => c.name === prod.name) ?? cogsData[pi] ?? null
      const rawMaterials = cogsProd?.raw_materials ?? []

      rawMaterials.forEach(rm => {
        const rmVal = parseFloat(rm.beg_inventory_value || 0)
        if (rmVal > 0 || rm.name) {  // include even if zero so user can see/edit
          rows.push({
            type:         'manufacturing_rm',
            label:        `${rm.name || 'Raw Material'} (${prod.name}) — RM Beginning Inventory`,
            amount:       rmVal,
            _productName: prod.name,
            _readonly:    true,
          })
        }
      })

    } else if (prod.nature === 'trading') {

      // ── Trading Inventory — from CogsStep.vue ────────────────────────
      // Field: cogsForm[pi].beginning_inventory_value
      const cogsProd   = cogsData.find(c => c.name === prod.name) ?? cogsData[pi] ?? null
      const tradingVal = parseFloat(cogsProd?.beginning_inventory_value || 0)

      rows.push({
        type:         'trading',
        label:        `${prod.name} — Trading Inventory`,
        amount:       tradingVal,
        _productName: prod.name,
        _readonly:    true,
      })
    }
    // service products — no inventory
  })

  return rows
}

// ── Initialise ─────────────────────────────────────────────────────
onMounted(() => {
  // Build inventory rows from prior steps
  inventoryRows.value = buildInventoryRows()

  if (props.savedData) {
    form.value.as_of_date        = props.savedData.as_of_date        || ''
    form.value.notes             = props.savedData.notes             || ''
    form.value.cash_bank         = parseFloat(props.savedData.cash_bank         || 0)
    form.value.paid_up_capital   = parseFloat(props.savedData.paid_up_capital   || 0)
    form.value.legal_reserve     = parseFloat(props.savedData.legal_reserve     || 0)
    form.value.retained_earnings = parseFloat(props.savedData.retained_earnings || 0)

    // Restore fixed assets
    if (Array.isArray(props.savedData.fixed_assets)) {
      fixedAssets.value = props.savedData.fixed_assets.map(fa => ({
        label:                fa.label                || '',
        gross_amount:         parseFloat(fa.gross_amount)         || 0,
        accum_dep:            parseFloat(fa.accum_dep)            || 0,
        monthly_dep:          parseFloat(fa.monthly_dep)          || 0,
        dep_months_remaining: parseFloat(fa.dep_months_remaining) || 0,
        dep_mfg_pct:          parseFloat(fa.dep_mfg_pct)          ?? 0,
      }))
    }

    // Restore free sections — savedData comes from DB toArray() so keys are FLAT
    // e.g. savedData.current_assets (not savedData.sections.current_assets)
    ;['other_non_current', 'current_assets', 'long_term_liabilities', 'current_liabilities', 'equity'].forEach(key => {
      const rows = props.savedData[key]   // flat key directly on savedData
      if (Array.isArray(rows) && rows.length) {
        sections.value[key] = rows.map(r => ({
          label:    r.label    || '',
          amount:   parseFloat(r.amount) || 0,
          schedule: Array.isArray(r.schedule) ? r.schedule : [],
        }))
      }
    })

    // Restore inventory amounts (user may have overridden)
    if (Array.isArray(props.savedData.inventory)) {
      props.savedData.inventory.forEach(saved => {
        const match = inventoryRows.value.find(r => r.label === saved.label)
        if (match) match.amount = parseFloat(saved.amount) || 0
      })
    }
  }
})

// ── Row helpers ────────────────────────────────────────────────────
function addRow(sectionKey) {
  sections.value[sectionKey].push({ label: '', amount: 0, schedule: [] })
}
function removeRow(sectionKey, idx) {
  sections.value[sectionKey].splice(idx, 1)
}

// ── Fixed Asset helpers ───────────────────────────────────────────
function addFixedAsset() {
  fixedAssets.value.push({
    label:                '',
    gross_amount:         0,
    accum_dep:            0,
    monthly_dep:          0,
    dep_months_remaining: 0,
    dep_mfg_pct:          0,
  })
}

// ── Dep Allocation Modal ──────────────────────────────────────────
const depAllocModal = ref({ open: false, faIdx: null, mfg_pct: 0 })

function openDepAllocModal(idx) {
  depAllocModal.value = {
    open:    true,
    faIdx:   idx,
    mfg_pct: fixedAssets.value[idx].dep_mfg_pct ?? 0,
  }
}

function saveDepAlloc() {
  const idx = depAllocModal.value.faIdx
  fixedAssets.value[idx].dep_mfg_pct = Math.min(100, Math.max(0, depAllocModal.value.mfg_pct || 0))
  depAllocModal.value.open = false
}

// ── Settlement Modal ──────────────────────────────────────────────
const settlementModal = ref({
  open:       false,
  sectionKey: null,
  rowIdx:     null,
  rowLabel:   '',
  rowAmount:  0,
  slots:      [],
})

function buildMonthSlots() {
  // Use studyMonths prop if available, otherwise generate from study dates
  if (props.studyMonths && props.studyMonths.length) {
    return props.studyMonths.map(m => ({ month: m, amount: 0 }))
  }
  // Fallback: generate N months from study start
  const months = []
  const startStr = props.study?.start_date || props.study?.study_start_date || new Date().toISOString().slice(0, 7)
  const durationYears = props.study?.duration_years || 3
  const totalMonths = durationYears * 12
  const [sy, sm] = startStr.slice(0, 7).split('-').map(Number)
  for (let i = 0; i < totalMonths; i++) {
    const d = new Date(sy, sm - 1 + i, 1)
    const label = d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
    months.push({ month: label, amount: 0 })
  }
  return months
}

function openSettlementModal(sectionKey, rowIdx) {
  const row = sections.value[sectionKey][rowIdx]
  const slots = buildMonthSlots()

  // Restore existing schedule amounts
  if (row.schedule && row.schedule.length) {
    row.schedule.forEach(s => {
      const slot = slots.find(sl => sl.month === s.month)
      if (slot) slot.amount = s.amount
    })
  }

  settlementModal.value = {
    open:       true,
    sectionKey,
    rowIdx,
    rowLabel:  row.label || 'Unnamed',
    rowAmount: row.amount || 0,
    slots,
  }
}

const settlementScheduledTotal = computed(() =>
  (settlementModal.value.slots || []).reduce((s, sl) => s + (parseFloat(sl.amount) || 0), 0)
)

function saveSettlement() {
  const { sectionKey, rowIdx, slots } = settlementModal.value
  const nonZero = slots.filter(sl => (parseFloat(sl.amount) || 0) > 0)
  sections.value[sectionKey][rowIdx].schedule = nonZero.map(sl => ({
    month:  sl.month,
    amount: parseFloat(sl.amount) || 0,
  }))
  settlementModal.value.open = false
}

function clearSettlement() {
  settlementModal.value.slots.forEach(sl => { sl.amount = 0 })
}

function scheduleTotal(schedule) {
  return (schedule || []).reduce((s, sl) => s + (parseFloat(sl.amount) || 0), 0)
}

// ── Computed totals ────────────────────────────────────────────────
const totalNetFA = computed(() =>
  fixedAssets.value.reduce((s, fa) => s + ((parseFloat(fa.gross_amount) || 0) - (parseFloat(fa.accum_dep) || 0)), 0)
)

function sectionTotal(key) {
  return (sections.value[key] || []).reduce((s, r) => s + (parseFloat(r.amount) || 0), 0)
}

const inventoryTotal = computed(() =>
  inventoryRows.value.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0)
)

const nonCurrentAssetsTotal = computed(() =>
  totalNetFA.value + sectionTotal('other_non_current')
)

const currentAssetsTotal = computed(() =>
  (parseFloat(form.value.cash_bank) || 0) +
  inventoryTotal.value +
  sectionTotal('current_assets')
)

const totalAssets = computed(() =>
  nonCurrentAssetsTotal.value + currentAssetsTotal.value
)

const totalLiabilities = computed(() =>
  sectionTotal('long_term_liabilities') + sectionTotal('current_liabilities')
)

const totalEquity = computed(() =>
  (parseFloat(form.value.paid_up_capital)   || 0) +
  (parseFloat(form.value.legal_reserve)     || 0) +
  (parseFloat(form.value.retained_earnings) || 0) +
  sectionTotal('equity')
)

const balanceDiff = computed(() =>
  totalAssets.value - (totalLiabilities.value + totalEquity.value)
)

const isBalanced = computed(() => Math.abs(balanceDiff.value) < 1)

// ── Save ──────────────────────────────────────────────────────────
async function save() {
  saving.value   = true
  saveError.value = ''

  const xsrfCookie = document.cookie.split(';')
    .map(c => c.trim())
    .find(c => c.startsWith('XSRF-TOKEN='))
  const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.split('=').slice(1).join('=')) : ''

  // Send all fields FLAT at top level so controller reads them directly
  const payload = {
    source:                'manual',
    as_of_date:            form.value.as_of_date || null,
    notes:                 form.value.notes      || null,
    // Dedicated scalar fields
    cash_bank:             parseFloat(form.value.cash_bank)         || 0,
    paid_up_capital:       parseFloat(form.value.paid_up_capital)   || 0,
    legal_reserve:         parseFloat(form.value.legal_reserve)     || 0,
    retained_earnings:     parseFloat(form.value.retained_earnings) || 0,
    // Array sections
    fixed_assets:          fixedAssets.value,
    inventory:             inventoryRows.value.map(r => ({ label: r.label, type: r.type, amount: r.amount })),
    other_non_current:     sections.value.other_non_current,
    current_assets:        sections.value.current_assets,
    long_term_liabilities: sections.value.long_term_liabilities,
    current_liabilities:   sections.value.current_liabilities,
    equity:                sections.value.equity,
  }

  try {
    const res = await fetch(
      `/portfolio-companies/${props.company.id}/financial-studies/${props.study.id}/opening-balance`,
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

    if (res.ok && data.success) {
      window.location.href = data.redirect
    } else {
      saveError.value = data.message || `Server error (${res.status})`
    }
  } catch (e) {
    saveError.value = 'Network error: ' + e.message
  } finally {
    saving.value = false
  }
}

// ── Format helpers ─────────────────────────────────────────────────
function formatNum(val) {
  if (val === null || val === undefined) return '—'
  return parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}
</script>