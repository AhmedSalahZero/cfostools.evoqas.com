<template>
  <div class="min-h-screen bg-mp-page text-white">

    <!-- Top bar -->
    <div class="sticky top-0 z-40 bg-mp-page/95 backdrop-blur border-b border-mp-border px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Link :href="route('budgets.index', company.id)" class="text-white hover:text-white text-sm transition-colors">
          ← Budgets
        </Link>
        <span class="text-white">/</span>
        <Link :href="route('budgets.show', [company.id, budget.id])" class="text-white hover:text-white text-sm transition-colors">
          {{ budget.name }}
        </Link>
        <span class="text-white">/</span>
        <span class="text-white text-sm font-semibold">Enter Actuals</span>
      </div>

      <div class="flex items-center gap-3">
        <!-- Statement type tabs -->
        <div class="flex gap-1 bg-mp-card rounded-lg p-1">
          <button
            v-for="tab in statementTabs"
            :key="tab.key"
            @click="activeTab = tab.key"
            :class="activeTab === tab.key ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
            class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors"
          >
            {{ tab.label }}
          </button>
        </div>

        <button
          @click="saveAll"
          :disabled="saving"
          class="bg-mp-teal hover:bg-mp-teal disabled:opacity-50 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors"
        >
          {{ saving ? 'Saving…' : 'Save Actuals' }}
        </button>
      </div>
    </div>

    <div class="max-w-[1800px] mx-auto px-4 py-6">

      <!-- Flash -->
      <div v-if="$page.props.flash?.success" class="mb-5 bg-mp-success/40 border border-mp-success text-white text-sm px-4 py-3 rounded-lg">
        {{ $page.props.flash.success }}
      </div>

      <!-- FS Import toolbar -->
      <div class="bg-mp-card border border-mp-border rounded-xl p-4 mb-5 flex items-center gap-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest text-white mb-1">Import from Financial Statements</p>
          <p class="text-xs text-white">Pull section totals from an existing financial statement into a specific month.</p>
        </div>
        <div class="flex items-center gap-3 ml-auto">
          <select
            v-model="importMonth"
            class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mp-teal"
          >
            <option v-for="(label, m) in months" :key="m" :value="m">{{ label }}</option>
          </select>
          <select
            v-model="importStatementId"
            class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mp-teal min-w-[260px]"
          >
            <option value="">— Select Financial Statement —</option>
            <option v-for="s in availableStatements" :key="s.id" :value="s.id">{{ s.label }}</option>
          </select>
          <button
            @click="doImportFs"
            :disabled="!importStatementId || importing"
            class="bg-mp-gold hover:bg-mp-gold-dark disabled:opacity-40 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors"
          >
            {{ importing ? 'Importing…' : 'Import' }}
          </button>
        </div>
      </div>

      <!-- Sections for active tab -->
      <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-xs border-collapse" style="min-width: 1400px">
            <thead>
              <tr class="bg-mp-card-hover text-white text-xs">
                <th class="text-left px-4 py-3 font-semibold sticky left-0 bg-mp-card-hover z-10 min-w-[280px]">
                  Description
                </th>
                <th
                  v-for="(label, m) in months"
                  :key="m"
                  class="px-1 py-3 font-semibold text-center min-w-[90px] border-l border-mp-border/50"
                >
                  {{ label }}
                </th>
                <th class="px-2 py-3 font-semibold text-center min-w-[80px] border-l border-mp-border">
                  Annual
                </th>
              </tr>
              <tr class="bg-mp-card-hover/60 text-white text-xs border-t border-mp-border">
                <th class="px-4 py-1 sticky left-0 bg-mp-card-hover/60 z-10 text-left text-white">
                  Budget / <span class="text-mp-success">Actual entry</span>
                </th>
                <th
                  v-for="(label, m) in months"
                  :key="m"
                  class="px-0 py-1 border-l border-mp-border/50"
                >
                  <div class="grid grid-cols-2 text-center text-xs gap-px">
                    <span class="text-white/60">Budget</span>
                    <span class="text-mp-success/60">Actual</span>
                  </div>
                </th>
                <th class="border-l border-mp-border"></th>
              </tr>
            </thead>

            <tbody>
              <template v-for="section in currentSections" :key="section.section_key">

                <!-- Computed section row -->
                <tr v-if="section.is_computed" class="bg-mp-card-hover/80 border-t-2 border-mp-border">
                  <td class="px-4 py-2.5 sticky left-0 bg-mp-card-hover/80 z-10">
                    <span class="font-bold text-mp-warning text-xs">{{ section.display_name }}</span>
                    <span class="ml-2 text-white italic text-xs">auto</span>
                  </td>
                  <template v-for="(label, m) in months" :key="m">
                    <td class="px-1 py-2.5 border-l border-mp-border/40">
                      <div class="grid grid-cols-2 text-center gap-px">
                        <span class="text-white text-xs">{{ fmtK(section.monthly_budget[m]) }}</span>
                        <span class="text-mp-warning text-xs font-semibold">{{ fmtK(computedActual(section, m)) }}</span>
                      </div>
                    </td>
                  </template>
                  <td class="px-2 py-2.5 border-l border-mp-border text-center text-xs font-bold text-mp-warning">
                    {{ fmtK(annualSum(section.monthly_budget)) }}
                  </td>
                </tr>

                <!-- Non-computed section -->
                <template v-else>
                  <tr class="bg-mp-card-hover/40 border-t border-mp-border cursor-pointer hover:bg-mp-card-hover/60 transition-colors"
                      @click="toggleSection(section.section_key)">
                    <td class="px-4 py-2.5 sticky left-0 bg-mp-card z-10">
                      <div class="flex items-center gap-2">
                        <span class="text-white text-xs">{{ expanded[section.section_key] ? '▼' : '▶' }}</span>
                        <span class="font-semibold text-white text-xs">{{ section.display_name }}</span>
                      </div>
                    </td>
                    <template v-for="(label, m) in months" :key="m">
                      <td class="px-1 py-2.5 border-l border-mp-border/40">
                        <div class="grid grid-cols-2 text-center gap-px">
                          <span class="text-white text-xs font-semibold">{{ fmtK(section.monthly_budget[m]) }}</span>
                          <span class="text-mp-success text-xs font-semibold">{{ fmtK(sectionActualTotal(section, m)) }}</span>
                        </div>
                      </td>
                    </template>
                    <td class="px-2 py-2.5 border-l border-mp-border text-center text-xs font-bold text-white">
                      {{ fmtK(annualSum(section.monthly_budget)) }}
                    </td>
                  </tr>

                  <template v-if="expanded[section.section_key]">
                    <template v-for="group in section.groups" :key="group.id">

                      <!-- Group row -->
                      <tr class="bg-mp-card/50 border-t border-mp-border/60 cursor-pointer hover:bg-mp-card-hover/30 transition-colors"
                          @click="toggleGroup(group.id)">
                        <td class="px-4 py-2 pl-8 sticky left-0 bg-mp-card/50 z-10">
                          <div class="flex items-center gap-2">
                            <span class="text-white text-xs">{{ expandedGroups[group.id] ? '▼' : '▶' }}</span>
                            <span class="font-semibold text-white text-sm">{{ group.name }}</span>
                          </div>
                        </td>
                        <template v-for="(label, m) in months" :key="m">
                          <td class="px-1 py-2 border-l border-mp-border/30">
                            <div class="grid grid-cols-2 text-center gap-px">
                              <span class="text-white text-xs">{{ fmtK(group.monthly_budget[m]) }}</span>
                              <span class="text-mp-success text-xs">{{ fmtK(groupActualTotal(group, m)) }}</span>
                            </div>
                          </td>
                        </template>
                        <td class="px-2 py-2 border-l border-mp-border text-center text-xs font-semibold text-white">
                          {{ fmtK(annualSum(group.monthly_budget)) }}
                        </td>
                      </tr>

                      <!-- Line items with editable actual inputs -->
                      <template v-if="expandedGroups[group.id]">
                        <tr
                          v-for="item in group.line_items"
                          :key="item.id"
                          class="border-t border-mp-border/40 hover:bg-mp-card-hover/20 transition-colors"
                        >
                          <td class="px-4 py-1.5 pl-14 sticky left-0 bg-mp-page z-10">
                            <span class="text-xs text-white">{{ item.label }}</span>
                          </td>
                          <template v-for="(label, m) in months" :key="m">
                            <td class="px-1 py-1 border-l border-mp-border/40">
                              <div class="grid grid-cols-2 gap-px text-center items-center">
                                <span class="text-white/60 text-xs">{{ fmtK(item.monthly_budget[m]) }}</span>
                                <input
                                  :value="actuals[item.id][m] ?? actuals[item.id][String(m)] ?? null"
                                  @change="actuals[item.id][m] = actuals[item.id][String(m)] = $event.target.value === '' ? null : Number($event.target.value)"
                                  type="number"
                                  placeholder="—"
                                  class="w-full bg-mp-card-hover/70 border border-mp-border/50 hover:border-mp-border focus:border-mp-success rounded px-1 py-0.5 text-xs text-mp-success text-center placeholder-gray-600 focus:outline-none transition-colors"
                                />
                              </div>
                            </td>
                          </template>
                          <td class="px-2 py-1.5 border-l border-mp-border text-center text-xs text-mp-success">
                            {{ fmtK(itemActualAnnual(item.id)) }}
                          </td>
                        </tr>
                      </template>

                    </template>
                  </template>
                </template>

              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Legend -->
      <div class="mt-4 flex items-center gap-6 text-xs text-white">
        <div class="flex items-center gap-1.5"><span class="text-white">Blue</span> = Budget</div>
        <div class="flex items-center gap-1.5"><span class="text-mp-success">Green inputs</span> = Enter actuals here</div>
        <div class="text-white">· Click section/group headers to expand · Save when done</div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
  company:              Object,
  budget:               Object,
  data:                 Object,
  months:               Object,
  availableStatements:  Array,
})

const page = usePage()

// ─── Tabs ─────────────────────────────────────────────────────────────────────
const statementTabs = [
  { key: 'income',        label: 'Income Statement' },
  { key: 'balance_sheet', label: 'Balance Sheet' },
  { key: 'cashflow',      label: 'Cash Flow' },
]
const activeTab = ref('income')
const currentSections = computed(() => props.data[activeTab.value] ?? [])

// ─── Expand/collapse ──────────────────────────────────────────────────────────
const expanded      = ref({})
const expandedGroups = ref({})
function toggleSection(k) { expanded.value[k]       = !expanded.value[k] }
function toggleGroup(id)  { expandedGroups.value[id] = !expandedGroups.value[id] }

// ─── Actuals state ────────────────────────────────────────────────────────────
// Build flat map: { item_id: { 1: value, 2: value, ... } }
const actuals = reactive({})

function initActuals(sections) {
  (sections ?? []).forEach(section => {
    if (section.is_computed) return
    ;(section.groups ?? []).forEach(grp => {
      ;(grp.line_items ?? []).forEach(item => {
        if (!actuals[item.id]) {
          const existing = item.monthly_actual ?? {}
          actuals[item.id] = {}
          for (let m = 1; m <= 12; m++) {
            // Store under BOTH integer and string key so v-model (string key from v-for)
            // and saveAll (integer key for server) both work correctly
            const val = existing[m] ?? existing[String(m)] ?? null
            actuals[item.id][m]        = val
            actuals[item.id][String(m)] = val
          }
        }
      })
    })
  })
}

// Init for all tabs
initActuals(props.data.income)
initActuals(props.data.balance_sheet)
initActuals(props.data.cashflow)

// ─── Aggregation helpers ───────────────────────────────────────────────────────
function itemActualAnnual(itemId) {
  return Object.values(actuals[itemId] ?? {}).reduce((s, v) => s + (parseFloat(v) || 0), 0)
}

function groupActualTotal(group, m) {
  return (group.line_items ?? []).reduce((s, item) => s + (parseFloat(actuals[item.id]?.[m]) || 0), 0)
}

function sectionActualTotal(section, m) {
  return (section.groups ?? []).reduce((s, grp) => s + groupActualTotal(grp, m), 0)
}

// For computed sections — derive actual from formula using live actuals
function computedActual(section, m) {
  const formula = section.computed_from
  if (!formula) return null
  let val = 0
  // We need section actual totals by key — build a lookup
  const allSections = [
    ...(props.data.income ?? []),
    ...(props.data.balance_sheet ?? []),
    ...(props.data.cashflow ?? []),
  ]
  for (const part of formula) {
    const sec = allSections.find(s => s.section_key === part.key)
    if (!sec) return null
    const total = sec.is_computed ? computedActual(sec, m) : sectionActualTotal(sec, m)
    val += (total ?? 0) * part.sign
  }
  return val
}

function annualSum(obj) {
  if (!obj) return 0
  return Object.values(obj).reduce((s, v) => s + (parseFloat(v) || 0), 0)
}

// ─── FS Import ────────────────────────────────────────────────────────────────
const importMonth       = ref(1)
const importStatementId = ref('')
const importing         = ref(false)

async function doImportFs() {
  if (!importStatementId.value || importing.value) return
  importing.value = true

  try {
    // Build XSRF token from cookie
    const xsrf = decodeURIComponent(document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '')

    const url = route('budgets.import-fs', [props.company.id, props.budget.id])
      + '?statement_id=' + importStatementId.value
      + '&month=' + importMonth.value

    const resp = await fetch(url, {
      headers: { 'X-XSRF-TOKEN': xsrf, 'Accept': 'application/json' },
      credentials: 'include',
    })

    if (!resp.ok) throw new Error('Import failed')
    const json = await resp.json()

    // Map FS section totals → line items
    // Strategy: find the section matching section_key, spread the total
    // across its line items proportionally to their budget amounts.
    // If a section has only 1 line item → put everything there.
    const allSections = [
      ...(props.data.income ?? []),
      ...(props.data.balance_sheet ?? []),
      ...(props.data.cashflow ?? []),
    ]

    for (const [sectionKey, total] of Object.entries(json.totals)) {
      const section = allSections.find(s => s.section_key === sectionKey)
      if (!section || section.is_computed) continue

      // Collect all line items in this section
      const allItems = (section.groups ?? []).flatMap(g => g.line_items ?? [])
      if (allItems.length === 0) continue

      if (allItems.length === 1) {
        // Single line item — put full amount there
        actuals[allItems[0].id][importMonth.value] = total
      } else {
        // Proportional split based on budget amounts for that month
        const budgetTotal = allItems.reduce((s, it) => s + (it.monthly_budget[importMonth.value] ?? 0), 0)
        allItems.forEach(item => {
          const budget = item.monthly_budget[importMonth.value] ?? 0
          const share  = budgetTotal > 0 ? (budget / budgetTotal) * total : total / allItems.length
          actuals[item.id][importMonth.value] = Math.round(share)
        })
      }
    }

    alert(`✓ Imported from: ${json.fs_label} → Month: ${props.months[importMonth.value]}`)
  } catch (e) {
    alert('Import failed. Please try again.')
  } finally {
    importing.value = false
  }
}

// ─── Save ─────────────────────────────────────────────────────────────────────
const saving = ref(false)

function saveAll() {
  if (saving.value) return
  saving.value = true

  // Build payload: all line items across all tabs
  const entries = []
  const allSections = [
    ...(props.data.income ?? []),
    ...(props.data.balance_sheet ?? []),
    ...(props.data.cashflow ?? []),
  ]

  allSections.forEach(section => {
    if (section.is_computed) return
    ;(section.groups ?? []).forEach(grp => {
      ;(grp.line_items ?? []).forEach(item => {
        // Normalise monthly values: convert string keys to integer keys for server
        const monthly = {}
        for (let m = 1; m <= 12; m++) {
          const val = actuals[item.id]?.[m] ?? actuals[item.id]?.[String(m)] ?? null
          monthly[m] = (val !== null && val !== '') ? Number(val) : null
        }
        entries.push({
          line_item_id: item.id,
          monthly,
          source: 'manual',
        })
      })
    })
  })

  router.post(route('budgets.save-actuals', [props.company.id, props.budget.id]), { actuals: entries }, {
    onSuccess: () => { saving.value = false },
    onError:   () => { saving.value = false },
    onFinish:  () => { saving.value = false },
  })
}

// ─── Formatter ────────────────────────────────────────────────────────────────
function fmtK(v) {
  if (v === null || v === undefined || isNaN(v)) return '—'
  const abs = Math.abs(v)
  if (abs >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M'
  if (abs >= 1_000)    return (v / 1_000).toFixed(0) + 'K'
  return v.toFixed(0)
}
</script>