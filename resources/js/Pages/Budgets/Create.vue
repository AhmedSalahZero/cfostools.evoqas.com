<template>
  <div class="min-h-screen bg-mp-page text-white">

    <!-- Top bar -->
    <div class="sticky top-0 z-40 bg-mp-page/95 backdrop-blur border-b border-mp-border px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Link :href="route('budgets.index', company.id)" class="text-white hover:text-white text-sm transition-colors">
          ← Budgets
        </Link>
        <span class="text-white">/</span>
        <span class="text-white text-sm font-semibold">{{ isEditing ? 'Edit Budget' : 'New Budget Statement' }}</span>
      </div>
      <div class="flex gap-1 bg-mp-card rounded-lg p-1">
        <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
          :class="activeTab === tab.key ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
          class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors">
          {{ tab.label }}
        </button>
      </div>
      <button @click="submit" :disabled="saving"
        class="bg-mp-teal hover:bg-mp-teal disabled:opacity-50 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors">
        {{ saving ? 'Saving…' : (isEditing ? 'Save Changes' : 'Create Budget') }}
      </button>
    </div>

    <div class="max-w-[1700px] mx-auto px-6 py-6">

      <!-- Header fields -->
      <div class="bg-mp-card border border-mp-border rounded-xl p-5 mb-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-white mb-4">Budget Details</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1">Budget Name</label>
            <input v-model="form.name" type="text" placeholder="e.g. 2026 Annual Budget"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-mp-teal" />
            <p v-if="errors.name" class="text-mp-danger text-xs mt-1">{{ errors.name }}</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1">Year</label>
            <select v-model.number="form.year" class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mp-teal">
              <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1">Status</label>
            <select v-model="form.status" class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mp-teal">
              <option value="draft">Draft</option>
              <option value="final">Final</option>
            </select>
          </div>
          <div class="md:col-span-4">
            <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1">Notes (optional)</label>
            <textarea v-model="form.notes" rows="2" placeholder="Any notes about this budget…"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-mp-teal resize-none" />
          </div>
        </div>
      </div>

      <!-- Section tabs content -->
      <template v-for="tab in tabs" :key="tab.key">
        <div v-show="activeTab === tab.key">

          <!-- ── Income / Balance / Cashflow tabs ── -->
          <template v-if="tab.key !== 'sales_directors'">
            <template v-for="(section, si) in getSections(tab.key)" :key="section.key">

              <!-- COMPUTED ROW -->
              <div v-if="section.computed" class="mb-2">
                <div class="bg-mp-card-hover/60 border border-mp-border rounded-xl overflow-hidden">
                  <div class="overflow-x-auto">
                    <div class="min-w-max">
                      <div class="flex items-center bg-mp-card-hover px-5 py-3">
                        <div class="w-72 flex-shrink-0 sticky left-0 bg-mp-card-hover z-10">
                          <span class="text-sm font-bold text-mp-warning">{{ section.label }}</span>
                          <span class="ml-2 text-xs text-white italic">auto-calculated</span>
                        </div>
                        <div class="flex gap-1 ml-2">
                          <div v-for="m in 12" :key="m" class="w-24 flex-shrink-0 text-center text-sm font-bold"
                            :class="computedSectionTotal(section.key, tab.key, m) < 0 ? 'text-mp-danger' : 'text-mp-warning'">
                            {{ formatNum(computedSectionTotal(section.key, tab.key, m)) }}
                          </div>
                        </div>
                        <div class="w-28 flex-shrink-0 text-center text-sm font-bold text-mp-warning sticky right-0 bg-mp-card-hover z-10 border-l border-mp-border/50 ml-2 pl-2">
                          {{ formatNum(computedSectionAnnual(section.key, tab.key)) }}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- NON-COMPUTED SECTION -->
              <div v-else class="mb-4 bg-mp-card border border-mp-border rounded-xl overflow-hidden">

                <!-- Shared horizontal scroll wrapper — ALL rows scroll together -->
                <div class="overflow-x-auto">
                  <div class="min-w-max">

                    <!-- Section header with month labels -->
                    <div class="flex items-center px-5 py-3 bg-mp-card border-b border-mp-border">
                      <!-- Sticky label column -->
                      <div class="w-72 flex-shrink-0 sticky left-0 bg-mp-card z-10">
                        <span class="text-xs font-semibold uppercase tracking-widest text-white">{{ section.label }}</span>
                      </div>
                      <!-- 12 month labels -->
                      <div class="flex gap-1 ml-2">
                        <div v-for="m in 12" :key="m" class="w-24 flex-shrink-0 text-center text-sm font-bold text-white">
                          {{ monthNames[m - 1] }}
                        </div>
                      </div>
                      <!-- Sticky Annual header -->
                      <div class="w-28 flex-shrink-0 text-center text-sm font-bold text-white sticky right-0 bg-mp-card z-10 pl-2 border-l border-mp-border/50 ml-2">
                        Annual
                      </div>
                    </div>

                    <!-- Groups (Father) -->
                    <template v-for="(group, gi) in form.sections[section.key]?.groups ?? []" :key="gi">

                      <!-- Group header -->
                      <div class="flex items-center px-5 py-2 bg-mp-card-hover/50 border-b border-mp-border/60">
                        <!-- Sticky label column -->
                        <div class="w-72 flex-shrink-0 sticky left-0 bg-mp-card-hover/90 z-10 flex items-center gap-2 pr-2">
                          <span class="text-xs text-white font-semibold w-4">{{ gi + 1 }}.</span>
                          <input v-model="group.name" type="text" placeholder="Group name (e.g. Product Line A)"
                            class="bg-transparent border border-transparent hover:border-mp-border focus:border-mp-teal rounded-lg px-2 py-1 text-sm font-semibold text-white placeholder-gray-600 focus:outline-none transition-colors flex-1 min-w-0" />
                          <button @click="removeGroup(section.key, gi)" class="text-mp-danger/60 hover:text-mp-danger text-xs transition-colors flex-shrink-0">✕</button>
                        </div>
                        <!-- 12 month totals -->
                        <div class="flex gap-1 ml-2">
                          <div v-for="m in 12" :key="m" class="w-24 flex-shrink-0 text-center text-sm font-semibold text-white">
                            {{ formatNum(groupMonthTotal(section.key, gi, m)) }}
                          </div>
                        </div>
                        <!-- Sticky Annual -->
                        <div class="w-28 flex-shrink-0 text-center text-sm font-semibold text-white sticky right-0 bg-mp-card-hover/90 z-10 pl-2 border-l border-mp-border/50 ml-2">
                          {{ formatNum(groupAnnualTotal(section.key, gi)) }}
                        </div>
                      </div>

                      <!-- Line items (Son) -->
                      <template v-for="(item, ii) in group.line_items" :key="item._uid">
                        <div class="border-b border-mp-border/40 hover:bg-mp-card-hover/10 transition-colors">

                          <!-- Input row -->
                          <div class="flex items-center px-5 py-1.5">
                            <!-- Sticky label + controls column -->
                            <div class="w-72 flex-shrink-0 sticky left-0 bg-mp-card/95 z-10 flex items-center gap-2 pr-2">
                              <span class="text-xs text-white w-4 text-right flex-shrink-0">{{ ii + 1 }}</span>
                              <input v-model="item.label" type="text" placeholder="Line item name"
                                class="bg-transparent border border-transparent hover:border-mp-border focus:border-mp-teal rounded-lg px-2 py-1 text-sm text-white placeholder-gray-600 focus:outline-none transition-colors flex-1 min-w-0" />

                              <!-- % / # toggle -->
                              <div class="flex items-center bg-mp-card-hover rounded-md border border-mp-border flex-shrink-0">
                                <button @click="setItemMode(item, 'number')"
                                  :class="item.input_mode !== 'pct_revenue' ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
                                  class="text-xs font-bold px-2 py-1 rounded-l-md transition-colors" title="Fixed number">
                                  #
                                </button>
                                <button @click="setItemMode(item, 'pct_revenue')"
                                  :class="item.input_mode === 'pct_revenue' ? 'bg-mp-gold-dark text-white' : 'text-white hover:text-white'"
                                  class="text-xs font-bold px-2 py-1 rounded-r-md transition-colors" title="% of selected revenues">
                                  %
                                </button>
                              </div>

                              <!-- % of revenue picker button (only in pct mode) -->
                              <button v-if="item.input_mode === 'pct_revenue'"
                                @click="openRevenuePicker(item, section.key)"
                                class="flex-shrink-0 text-xs px-2 py-1 rounded-md border transition-colors"
                                :class="item.revenue_basis?.length ? 'bg-mp-gold/40 border-mp-gold/50 text-white' : 'bg-mp-card-hover border-mp-border text-white hover:text-white'">
                                {{ item.revenue_basis?.length ? item.revenue_basis.length + ' rev.' : 'Pick rev.' }}
                              </button>

                              <button @click="removeLineItem(section.key, gi, ii)" class="text-mp-danger/40 hover:text-mp-danger text-xs transition-colors flex-shrink-0">✕</button>
                            </div>

                            <!-- 12 monthly inputs -->
                            <div class="flex gap-1 ml-2">
                              <template v-for="m in 12" :key="m">
                                <div class="w-24 flex-shrink-0 flex flex-col items-center gap-0.5">

                                  <!-- Number mode: formatted text input -->
                                  <input v-if="item.input_mode !== 'pct_revenue'"
                                    :value="focusedKey === (item._uid + '_' + m) ? (item.monthly_amounts[m] ?? '') : (item.monthly_amounts[m] ? formatNum(item.monthly_amounts[m]) : '')"
                                    @focus="onAmountFocus($event, item, m)"
                                    @blur="onAmountBlur($event, item, m)"
                                    type="text" inputmode="numeric" placeholder="0"
                                    class="w-full text-center bg-mp-card-hover/60 border border-mp-border/50 hover:border-mp-border focus:border-mp-teal rounded px-1 py-1.5 text-sm text-white placeholder-gray-600 focus:outline-none transition-colors" />

                                  <!-- % mode: percentage input -->
                                  <div v-else class="w-full flex items-center gap-0.5">
                                    <input
                                      :key="'pct_' + item._uid + '_' + m + '_' + (item.pct_amounts[m] ?? '')"
                                      :value="item.pct_amounts[m]"
                                      @input="item.pct_amounts[m] = $event.target.value === '' ? null : Number($event.target.value); recomputePctMonth(item, m)"
                                      type="number" placeholder="0" step="0.1" min="0" max="100"
                                      class="w-14 text-center bg-mp-gold/30 border border-mp-gold/50 hover:border-mp-gold focus:border-mp-gold rounded px-1 py-1.5 text-sm text-white placeholder-gray-600 focus:outline-none transition-colors" />
                                    <span class="text-xs text-white font-bold">%</span>
                                  </div>

                                  <!-- ··· copy-right dots button -->
                                  <button @click="copyRight(item, m)"
                                    title="Copy this value to all months to the right"
                                    class="text-white hover:text-white transition-colors leading-none text-xs px-1">
                                    ···→
                                  </button>

                                </div>
                              </template>
                            </div>

                            <!-- Sticky Annual total -->
                            <div class="w-28 flex-shrink-0 flex flex-col items-center justify-center py-1 sticky right-0 bg-mp-card/95 z-10 border-l border-mp-border/50 ml-2 pl-2">
                              <span class="text-sm font-semibold text-white">{{ formatNum(itemAnnualTotal(item)) }}</span>
                              <span v-if="item.input_mode === 'pct_revenue'" class="text-xs text-white">
                                {{ formatNum(itemPctAnnualAvg(item)) }}% avg
                              </span>
                            </div>
                          </div>

                          <!-- % mode info bar -->
                          <div v-if="item.input_mode === 'pct_revenue'" class="flex items-center px-5 pb-1.5 ml-[288px] gap-2">
                            <span class="text-xs text-white/70 italic">
                              % applied to:
                              <span v-if="!item.revenue_basis?.length" class="text-white">no revenue selected — pick above</span>
                              <span v-else class="text-white">{{ item.revenue_basis.join(', ') }}</span>
                            </span>
                          </div>

                        </div>
                      </template>

                      <!-- Add line item -->
                      <div class="flex items-center px-5 py-2 border-b border-mp-border/30">
                        <div class="w-72 flex-shrink-0 sticky left-0 bg-mp-card z-10 flex items-center gap-3 pl-7">
                          <button @click="addLineItem(section.key, gi)"
                            class="text-xs text-white hover:text-white transition-colors flex items-center gap-1">
                            + Add Line Item
                          </button>
                        </div>
                      </div>

                    </template>

                    <!-- Section total row -->
                    <div class="flex items-center px-5 py-3 bg-mp-card-hover/40 border-t border-mp-border">
                      <!-- Sticky label column -->
                      <div class="w-72 flex-shrink-0 sticky left-0 bg-mp-card-hover/95 z-10">
                        <button @click="addGroup(section.key)"
                          class="text-xs font-semibold text-white hover:text-white transition-colors">
                          + Add Group
                        </button>
                      </div>
                      <!-- 12 month totals -->
                      <div class="flex gap-1 ml-2">
                        <div v-for="m in 12" :key="m" class="w-24 flex-shrink-0 text-center text-sm font-bold text-white">
                          {{ formatNum(sectionMonthTotal(section.key, m)) }}
                        </div>
                      </div>
                      <!-- Sticky Annual total -->
                      <div class="w-28 flex-shrink-0 text-center text-sm font-bold text-white sticky right-0 bg-mp-card-hover/95 z-10 pl-2 border-l border-mp-border/50 ml-2">
                        {{ formatNum(sectionAnnualTotal(section.key)) }}
                      </div>
                    </div>

                  </div><!-- /min-w-max -->
                </div><!-- /overflow-x-auto -->

              </div>
            </template>
          </template>

          <!-- ── Sales Directors tab ── -->
          <div v-if="tab.key === 'sales_directors'">
            <SalesDirectorsPanel
              :orgUsers="orgUsers"
              :salesRevenueGroups="form.sections.sales_revenue?.groups ?? []"
              v-model:directors="directors"
              v-model:assignments="assignments"
            />
          </div>

        </div>
      </template>
    </div>

    <!-- ── Revenue Picker Modal ──────────────────────────────────────────────── -->
    <div v-if="revPicker.open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70">
      <div class="bg-mp-card border border-mp-gold/50 rounded-2xl w-full max-w-lg shadow-2xl">
        <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between">
          <div>
            <h3 class="text-base font-bold text-white">Select Revenue Basis</h3>
            <p class="text-xs text-white mt-0.5">Choose which revenue line items this % applies to</p>
          </div>
          <button @click="revPicker.open = false" class="text-white hover:text-white text-xl">✕</button>
        </div>

        <div class="px-6 py-4 max-h-[50vh] overflow-y-auto space-y-1">
          <p v-if="allRevenueLineItems.length === 0" class="text-sm text-white italic">
            No revenue line items found. Add items to the Sales Revenues section first.
          </p>
          <template v-for="grp in allRevenueLineItems" :key="grp.groupName">
            <p class="text-xs font-semibold text-white uppercase tracking-wider mt-3 mb-1">{{ grp.groupName }}</p>
            <label v-for="li in grp.items" :key="li.key"
              class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-mp-card-hover cursor-pointer transition-colors">
              <input type="checkbox" :value="li.key"
                v-model="revPicker.selected"
                class="w-4 h-4 rounded border-mp-border bg-mp-card-hover text-white focus:ring-mp-gold" />
              <div>
                <span class="text-sm text-white">{{ li.label }}</span>
                <span class="ml-2 text-xs text-white">
                  Annual: {{ formatNum(itemAnnualTotal(li.item)) }}
                </span>
              </div>
            </label>
          </template>
        </div>

        <!-- Quick select -->
        <div class="px-6 py-3 border-t border-mp-border flex gap-2">
          <button @click="revPicker.selected = allRevenueLineItems.flatMap(g => g.items.map(i => i.key))"
            class="text-xs text-white hover:text-white transition-colors">Select All</button>
          <button @click="revPicker.selected = []"
            class="text-xs text-white hover:text-white transition-colors">Clear</button>
        </div>

        <div class="px-6 py-4 border-t border-mp-border flex gap-3">
          <button @click="revPicker.open = false"
            class="flex-1 bg-mp-card-hover hover:bg-mp-page text-white text-sm font-semibold py-2.5 rounded-lg transition-colors">
            Cancel
          </button>
          <button @click="saveRevenuePicker"
            class="flex-1 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-semibold py-2.5 rounded-lg transition-colors">
            Apply ({{ revPicker.selected.length }} selected)
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import SalesDirectorsPanel from '@/Pages/Budgets/SalesDirectorsPanel.vue'

const props = defineProps({
  company:             Object,
  budget:              Object,
  existingSections:    Array,
  incomeSections:      Array,
  balanceSections:     Array,
  cashflowSections:    Array,
  currentYear:         Number,
  orgUsers:            { type: Array,  default: () => [] },
  existingDirectors:   { type: Array,  default: () => [] },
  existingAssignments: { type: Object, default: () => ({}) },
})

const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
const tabs = [
  { key: 'income',          label: 'Income Statement' },
  { key: 'balance_sheet',   label: 'Balance Sheet' },
  { key: 'cashflow',        label: 'Cash Flow' },
  { key: 'sales_directors', label: '👥 Sales Directors' },
]
const activeTab = ref('income')
const saving    = ref(false)
const errors    = ref({})

// ─── Sales Directors state ────────────────────────────────────────────────────
const directors = ref(
  (props.existingDirectors ?? []).map(d => ({
    id:      d.id,        // keep DB id so we can rebuild assignments on edit
    user_id: d.user_id,
    name:    d.name,
    title:   d.title ?? '',
  }))
)

// Server sends existingAssignments as { line_item_db_id: director_db_id }
// The SalesDirectorsPanel tracks assignments as path-keys: "g0_i0" -> directorIndex
// On edit we must convert DB-id format back to path-key format.
function buildInitialAssignments() {
  const out = {}
  if (!props.existingAssignments || !props.existingSections) return out

  // Map director DB id -> index in the directors array
  const dirDbIdToIdx = {}
  ;(props.existingDirectors ?? []).forEach((d, idx) => {
    dirDbIdToIdx[d.id] = idx
  })

  // Walk the sales_revenue section groups/items to map DB line item id -> path-key
  const revSection = (props.existingSections ?? []).find(s => s.section_key === 'sales_revenue')
  if (!revSection) return out

  ;(revSection.groups ?? []).forEach((grp, gi) => {
    ;(grp.line_items ?? []).forEach((li, ii) => {
      const dirDbId = props.existingAssignments[li.id]
      if (dirDbId !== undefined && dirDbIdToIdx[dirDbId] !== undefined) {
        out[`g${gi}_i${ii}`] = dirDbIdToIdx[dirDbId]
      }
    })
  })
  return out
}

const assignments = ref(buildInitialAssignments())

const yearOptions = computed(() => {
  const base = props.currentYear ?? new Date().getFullYear()
  return Array.from({ length: 10 }, (_, i) => base - 2 + i)
})

const isEditing = computed(() => !!props.budget)

const form = reactive({
  name:     props.budget?.name   ?? '',
  year:     props.budget?.year   ?? props.currentYear,
  status:   props.budget?.status ?? 'draft',
  notes:    props.budget?.notes  ?? '',
  sections: {},
})

const allSections = [
  ...props.incomeSections.map(s  => ({ ...s, tab: 'income' })),
  ...props.balanceSections.map(s => ({ ...s, tab: 'balance_sheet' })),
  ...props.cashflowSections.map(s => ({ ...s, tab: 'cashflow' })),
]

let _uid = 0

function blankMonthly() {
  const o = {}
  for (let m = 1; m <= 12; m++) o[m] = null
  return o
}

function blankLineItem() {
  return {
    _uid:            ++_uid,
    label:           '',
    input_mode:      'number',
    monthly_amounts: blankMonthly(),
    pct_amounts:     blankMonthly(),
    revenue_basis:   [],
  }
}

function blankGroup(name = '') {
  return { name, line_items: [blankLineItem()] }
}

function toMonthArray(src) {
  const o = blankMonthly()
  if (!src) return o
  for (let m = 1; m <= 12; m++) {
    o[m] = (Array.isArray(src) ? src[m] : (src[m] ?? src[String(m)])) ?? null
  }
  return o
}

allSections.forEach(sec => {
  if (sec.computed) return
  if (props.existingSections) {
    const existing = props.existingSections.find(s => s.section_key === sec.key)
    if (existing) {
      form.sections[sec.key] = {
        groups: existing.groups.map(g => ({
          name: g.name,
          line_items: g.line_items.map(li => ({
            _uid:            ++_uid,
            label:           li.label,
            input_mode:      li.input_mode    ?? 'number',
            monthly_amounts: toMonthArray(li.monthly_amounts),
            pct_amounts:     toMonthArray(li.pct_amounts),
            revenue_basis:   li.revenue_basis ?? [],
          })),
        })),
      }
      return
    }
  }
  form.sections[sec.key] = { groups: [blankGroup()] }
})

// ─── Section helpers ──────────────────────────────────────────────────────────
function getSections(tabKey) {
  const map = { income: props.incomeSections, balance_sheet: props.balanceSections, cashflow: props.cashflowSections }
  return map[tabKey] ?? []
}
function addGroup(sectionKey) {
  form.sections[sectionKey] ??= { groups: [] }
  form.sections[sectionKey].groups.push(blankGroup())
}
function removeGroup(sectionKey, gi) { form.sections[sectionKey].groups.splice(gi, 1) }
function addLineItem(sectionKey, gi) { form.sections[sectionKey].groups[gi].line_items.push(blankLineItem()) }
function removeLineItem(sectionKey, gi, ii) { form.sections[sectionKey].groups[gi].line_items.splice(ii, 1) }

// ─── Input mode toggle ────────────────────────────────────────────────────────
function setItemMode(item, mode) {
  item.input_mode = mode
  if (mode === 'number') {
    item.pct_amounts   = blankMonthly()
    item.revenue_basis = []
  } else {
    item.monthly_amounts = blankMonthly()
    item.pct_amounts     = blankMonthly()
  }
}

// ─── Copy-right (···→) ───────────────────────────────────────────────────────
function copyRight(item, m) {
  const isPct = item.input_mode === 'pct_revenue'
  if (isPct) {
    const src  = item.pct_amounts[m]
    const copy = { ...item.pct_amounts }
    for (let i = m + 1; i <= 12; i++) copy[i] = src
    item.pct_amounts = copy
    recomputeAllPctMonths(item)
  } else {
    const src  = item.monthly_amounts[m]
    const copy = { ...item.monthly_amounts }
    for (let i = m + 1; i <= 12; i++) copy[i] = src
    item.monthly_amounts = copy
  }
}

// ─── % of Revenue logic ───────────────────────────────────────────────────────
const allRevenueLineItems = computed(() => {
  const revSection = form.sections['sales_revenue']
  if (!revSection) return []
  return (revSection.groups ?? [])
    .filter(g => g.line_items?.length)
    .map((g, gi) => ({
      groupName: g.name || `Group ${gi + 1}`,
      items: g.line_items.map((li, ii) => ({
        key:   `sales_revenue__g${gi}__i${ii}`,
        label: li.label || `Item ${ii + 1}`,
        item:  li,
      })),
    }))
    .filter(g => g.items.length)
})

const revenueItemByKey = computed(() => {
  const map = {}
  allRevenueLineItems.value.forEach(g => g.items.forEach(i => { map[i.key] = i.item }))
  return map
})

function recomputePctMonth(item, m) {
  if (item.input_mode !== 'pct_revenue') return
  const pct = parseFloat(item.pct_amounts[m]) || 0
  if (!item.revenue_basis?.length) {
    item.monthly_amounts[m] = 0
    return
  }
  let revTotal = 0
  item.revenue_basis.forEach(key => {
    const ri = revenueItemByKey.value[key]
    if (ri) revTotal += parseFloat(ri.monthly_amounts[m]) || 0
  })
  item.monthly_amounts[m] = Math.round((pct / 100) * revTotal)
}

function recomputeAllPctMonths(item) {
  for (let m = 1; m <= 12; m++) recomputePctMonth(item, m)
}

// ─── Revenue picker modal ─────────────────────────────────────────────────────
const revPicker = reactive({ open: false, item: null, selected: [] })

function openRevenuePicker(item) {
  revPicker.item     = item
  revPicker.selected = [...(item.revenue_basis ?? [])]
  revPicker.open     = true
}

function saveRevenuePicker() {
  if (!revPicker.item) return
  revPicker.item.revenue_basis = [...revPicker.selected]
  recomputeAllPctMonths(revPicker.item)
  revPicker.open = false
}

// ─── Calculation helpers ──────────────────────────────────────────────────────
function itemAnnualTotal(item) {
  let s = 0
  for (let m = 1; m <= 12; m++) s += parseFloat(item.monthly_amounts[m]) || 0
  return s
}
function itemPctAnnualAvg(item) {
  const vals = []
  for (let m = 1; m <= 12; m++) {
    const v = parseFloat(item.pct_amounts[m]) || 0
    if (v > 0) vals.push(v)
  }
  if (!vals.length) return 0
  return (vals.reduce((s, v) => s + v, 0) / vals.length).toFixed(1)
}
function groupMonthTotal(sectionKey, gi, m) {
  const grp = form.sections[sectionKey]?.groups?.[gi]
  if (!grp) return 0
  return grp.line_items.reduce((s, li) => s + (parseFloat(li.monthly_amounts[m]) || 0), 0)
}
function groupAnnualTotal(sectionKey, gi) {
  let sum = 0
  for (let m = 1; m <= 12; m++) sum += groupMonthTotal(sectionKey, gi, m)
  return sum
}
function sectionMonthTotal(sectionKey, m) {
  const groups = form.sections[sectionKey]?.groups ?? []
  return groups.reduce((s, _, gi) => s + groupMonthTotal(sectionKey, gi, m), 0)
}
function sectionAnnualTotal(sectionKey) {
  let sum = 0
  for (let m = 1; m <= 12; m++) sum += sectionMonthTotal(sectionKey, m)
  return sum
}

const sectionMonthlyTotals = computed(() => {
  const map = {}
  allSections.forEach(sec => {
    if (!sec.computed) {
      const monthly = {}
      for (let m = 1; m <= 12; m++) monthly[m] = sectionMonthTotal(sec.key, m)
      map[sec.key] = monthly
    }
  })
  for (let pass = 0; pass < 10; pass++) {
    allSections.forEach(sec => {
      if (!sec.computed || !sec.from) return
      const monthly = {}
      let ok = true
      for (let m = 1; m <= 12; m++) {
        let val = 0
        for (const part of sec.from) {
          if (!(part.key in map)) { ok = false; break }
          val += (map[part.key][m] ?? 0) * part.sign
        }
        monthly[m] = ok ? val : 0
      }
      if (ok) map[sec.key] = monthly
    })
  }
  return map
})

function computedSectionTotal(sectionKey, tabKey, m) { return sectionMonthlyTotals.value[sectionKey]?.[m] ?? 0 }
function computedSectionAnnual(sectionKey) {
  return Object.values(sectionMonthlyTotals.value[sectionKey] ?? {}).reduce((s, v) => s + v, 0)
}

function formatNum(v) {
  if (v === null || v === undefined || v === '' || isNaN(Number(v))) return '—'
  const n = Number(v)
  if (n === 0) return '0'
  return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Math.round(n))
}

// Track which input cell is focused (by unique key) so we can show raw vs formatted
const focusedKey = ref(null)

function onAmountFocus(event, item, m) {
  focusedKey.value = item._uid + '_' + m
  event.target.value = item.monthly_amounts[m] ?? ''
}

function onAmountBlur(event, item, m) {
  const raw = event.target.value.replace(/,/g, '')
  item.monthly_amounts[m] = raw === '' ? null : Number(raw)
  focusedKey.value = null
}

// ─── Build assignment payload for server ─────────────────────────────────────
function buildAssignmentPayload() {
  const out = {}
  for (const [key, dirIdx] of Object.entries(assignments.value)) {
    if (dirIdx !== null && dirIdx !== undefined) {
      out[key] = dirIdx
    }
  }
  return out
}

// ─── Submit ───────────────────────────────────────────────────────────────────
function submit() {
  if (saving.value) return
  saving.value = true
  errors.value = {}
  const url    = isEditing.value ? route('budgets.update', [props.company.id, props.budget.id]) : route('budgets.store', props.company.id)
  const method = isEditing.value ? 'put' : 'post'
  router[method](url, {
    name:                   form.name,
    year:                   form.year,
    status:                 form.status,
    notes:                  form.notes,
    sections:               form.sections,
    sales_directors:        directors.value.filter(d => d.name),
    line_item_assignments:  buildAssignmentPayload(),
  }, {
    onError:  (e) => { errors.value = e },
    onFinish: () => { saving.value = false },
  })
}
</script>