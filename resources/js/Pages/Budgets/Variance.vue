<template>
  <div class="min-h-screen bg-mp-page text-white">

    <!-- Top bar -->
    <div class="sticky top-0 z-40 bg-mp-page/95 backdrop-blur border-b border-mp-border px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Link :href="route('budgets.index', company.id)" class="text-white hover:text-white text-sm transition-colors">← Budgets</Link>
        <span class="text-white">/</span>
        <span class="text-white text-sm font-semibold">{{ budget.name }}</span>
        <span class="text-xs bg-mp-teal-subtle/50 text-white border border-mp-teal/40 px-2 py-0.5 rounded-full">Variance Report</span>
      </div>
      <div class="flex items-center gap-3">
        <div class="flex gap-1 bg-mp-card rounded-lg p-1">
          <button v-for="v in viewModes" :key="v.key" @click="viewMode = v.key"
            :class="viewMode === v.key ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
            class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors">{{ v.label }}</button>
        </div>
        <button @click="showInsights = !showInsights"
          :class="showInsights ? 'bg-mp-gold-dark text-white' : 'bg-mp-card-hover text-white hover:bg-mp-page'"
          class="text-xs font-semibold px-3 py-2 rounded-lg transition-colors flex items-center gap-1.5">
          💡 Auto Insights
        </button>
        <Link :href="route('budgets.actuals', [company.id, budget.id])"
          class="bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
          Enter Actuals
        </Link>

        <!-- ── Sales Directors button + dropdown ── -->
        <div v-if="directors.length" class="relative">
          <button @click="showDirectors = !showDirectors"
            :class="showDirectors ? 'bg-mp-teal-dark text-white' : 'bg-mp-card-hover text-white hover:bg-mp-page'"
            class="text-xs font-semibold px-3 py-2 rounded-lg transition-colors flex items-center gap-1.5">
            👥 Sales Directors
            <span class="bg-mp-teal/30 text-white text-xs px-1.5 py-0.5 rounded-full ml-1">{{ directors.length }}</span>
          </button>

          <!-- Dropdown -->
          <div v-if="showDirectors"
            class="absolute right-0 top-full mt-2 w-72 bg-mp-card border border-mp-border rounded-xl shadow-2xl z-50 overflow-hidden">
            <div class="px-4 py-3 border-b border-mp-border">
              <p class="text-xs font-semibold uppercase tracking-widest text-white">Sales Director Review Rooms</p>
              <p class="text-xs text-white mt-0.5">Each director sees their own private variance room</p>
            </div>
            <div class="py-1 divide-y divide-gray-800">
              <Link
                v-for="dir in directors"
                :key="dir.id"
                :href="route('budgets.director-review', [company.id, budget.id, dir.id])"
                class="flex items-center justify-between px-4 py-3 hover:bg-mp-card-hover transition-colors group"
                @click="showDirectors = false">
                <div>
                  <p class="text-sm font-semibold text-white group-hover:text-white transition-colors">{{ dir.name }}</p>
                  <p v-if="dir.title" class="text-xs text-white mt-0.5">{{ dir.title }}</p>
                </div>
                <span class="text-white group-hover:text-white text-lg transition-colors">→</span>
              </Link>
            </div>
            <div class="px-4 py-2 border-t border-mp-border">
              <p class="text-xs text-white">Directors are assigned in the Budget editor → 👥 Sales Directors tab</p>
            </div>
          </div>
        </div>

      </div>
    </div>

    <div class="max-w-[1900px] mx-auto px-4 py-6 space-y-6">

      <!-- ── AUTO INSIGHTS PANEL ─────────────────────────────────────────── -->
      <div v-if="showInsights" class="bg-mp-card border border-mp-gold/40 rounded-xl p-5">
        <div class="flex items-center gap-2 mb-4">
          <span class="text-lg">💡</span>
          <h3 class="text-base font-bold text-white">Auto Insights</h3>
          <span class="text-xs text-white">— based on entered actuals</span>
        </div>
        <div v-if="insights.length === 0" class="text-sm text-white italic">No actuals entered yet. Insights will appear once you start entering actual data.</div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          <div v-for="(ins, i) in insights" :key="i"
            :class="{
              'border-mp-success/40 bg-mp-success/10': ins.type === 'positive',
              'border-mp-danger/40 bg-mp-danger/10':     ins.type === 'negative',
              'border-mp-teal/40 bg-mp-teal-subtle/10':   ins.type === 'info',
              'border-mp-warning/40 bg-mp-warning/10':ins.type === 'warning',
            }"
            class="border rounded-xl p-3">
            <div class="flex items-start gap-2">
              <span class="text-lg mt-0.5">{{ ins.icon }}</span>
              <div>
                <p class="text-sm font-semibold text-white">{{ ins.title }}</p>
                <p class="text-xs text-white mt-0.5">{{ ins.body }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── CHARTS ROW ──────────────────────────────────────────────────── -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Chart 1: Budget vs Actual by month (income sections only) -->
        <div class="lg:col-span-2 bg-mp-card border border-mp-border rounded-xl p-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-white">Budget vs Actual — Monthly</h3>
            <select v-model="chartMetric" class="bg-mp-card-hover border border-mp-border rounded-lg px-2 py-1 text-xs text-white focus:outline-none">
              <option value="sales_revenue">Sales Revenue</option>
              <option value="gross_profit">Gross Profit</option>
              <option value="ebitda">EBITDA</option>
              <option value="net_profit">Net Profit</option>
            </select>
          </div>
          <div class="relative h-48">
            <svg class="w-full h-full" :viewBox="`0 0 ${chartW} ${chartH}`" preserveAspectRatio="none">
              <!-- Grid lines -->
              <line v-for="i in 4" :key="i" :x1="chartPad" :x2="chartW - chartPad"
                :y1="chartPad + ((chartH - chartPad*2) / 4) * (i-1)"
                :y2="chartPad + ((chartH - chartPad*2) / 4) * (i-1)"
                stroke="#1490a833" stroke-width="1" />
              <!-- Budget bars -->
              <rect v-for="(col, i) in chartCols" :key="'b'+i"
                :x="col.x" :y="col.by" :width="col.bw" :height="col.bh"
                fill="#00b4c8" opacity="0.5" rx="2" />
              <!-- Actual bars -->
              <rect v-for="(col, i) in chartCols" :key="'a'+i"
                :x="col.x + col.bw + 2" :y="col.ay" :width="col.bw" :height="col.ah"
                :fill="col.varPos ? '#22c55e' : '#ef4444'" opacity="0.8" rx="2" />
              <!-- Month labels -->
              <text v-for="(col, i) in chartCols" :key="'l'+i"
                :x="col.x + col.bw" :y="chartH - 2"
                text-anchor="middle" fill="#ffffff" font-size="9">
                {{ monthNames[i] }}
              </text>
            </svg>
          </div>
          <div class="flex items-center gap-4 mt-2 text-xs text-white">
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-mp-teal/50 inline-block"></span> Budget</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-mp-success inline-block"></span> Actual (on/above)</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-mp-danger inline-block"></span> Actual (below)</span>
          </div>
        </div>

        <!-- Chart 2: Top variances -->
        <div class="bg-mp-card border border-mp-border rounded-xl p-4">
          <h3 class="text-sm font-semibold text-white mb-3">Top Variances (Annual)</h3>
          <div class="space-y-2">
            <div v-if="topVariances.length === 0" class="text-xs text-white italic">No variance data yet.</div>
            <div v-for="(tv, i) in topVariances" :key="i" class="flex items-center gap-2">
              <div class="flex-1 min-w-0">
                <p class="text-xs text-white truncate">{{ tv.label }}</p>
                <div class="h-1.5 bg-mp-card-hover rounded-full mt-1 overflow-hidden">
                  <div :style="{ width: tv.pct + '%' }"
                    :class="tv.isGood ? 'bg-mp-success' : 'bg-mp-danger'"
                    class="h-full rounded-full transition-all"></div>
                </div>
              </div>
              <span :class="tv.isGood ? 'text-mp-success' : 'text-mp-danger'" class="text-xs font-bold flex-shrink-0">
                {{ tv.varStr }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- ── STATEMENT TABS ──────────────────────────────────────────────── -->
      <div class="flex gap-1 bg-mp-card rounded-lg p-1 w-fit">
        <button v-for="tab in statementTabs" :key="tab.key" @click="activeTab = tab.key"
          :class="activeTab === tab.key ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
          class="text-sm font-semibold px-5 py-2 rounded-md transition-colors">{{ tab.label }}</button>
      </div>

      <!-- ── VARIANCE TABLE ─────────────────────────────────────────────── -->
      <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse" style="min-width: 2000px">
            <thead>
              <tr class="bg-mp-card-hover text-white">
                <th class="text-left px-5 py-3.5 font-semibold sticky left-0 bg-mp-card-hover z-10 min-w-[280px] text-sm">Description</th>
                <template v-for="m in 12" :key="m">
                  <th class="px-1 py-3.5 font-semibold text-center min-w-[260px] border-l border-mp-border/50 text-sm">
                    {{ months[m] }}
                  </th>
                </template>
                <th class="px-2 py-3.5 font-semibold text-center min-w-[260px] border-l border-mp-border text-sm">Annual</th>
              </tr>
              <tr class="bg-mp-card-hover/70 text-white border-t border-mp-border text-m">
                <th class="px-5 py-2 text-left sticky left-0 bg-mp-card-hover/70 z-10"></th>
                <template v-for="m in 12" :key="m">
                  <th class="px-0 py-2 text-center border-l border-mp-border/50">
                    <div class="grid grid-cols-4 text-center gap-px">
                      <span class="text-white/80 font-semibold">Budget</span>
                      <span class="text-mp-success/80 font-semibold">Actual</span>
                      <span class="text-mp-warning/80 font-semibold">Var</span>
                      <span class="text-white font-semibold">💬</span>
                    </div>
                  </th>
                </template>
                <th class="px-0 py-2 text-center border-l border-mp-border">
                  <div class="grid grid-cols-3 text-center gap-px">
                    <span class="text-white/80 font-semibold">Budget</span>
                    <span class="text-mp-success/80 font-semibold">Actual</span>
                    <span class="text-mp-warning/80 font-semibold">Var</span>
                  </div>
                </th>
              </tr>
            </thead>

            <tbody>
              <template v-for="section in currentSections" :key="section.section_key">

                <!-- Section row (Grandpa) -->
                <tr :class="section.is_computed ? 'bg-mp-card-hover/80 border-t-2 border-mp-border' : 'bg-mp-card-hover/30 border-t border-mp-border'"
                  class="cursor-pointer hover:bg-mp-card-hover/50 transition-colors"
                  @click="toggleSection(section.section_key)">
                  <td class="px-5 py-3 sticky left-0 z-10" :class="section.is_computed ? 'bg-mp-card-hover/80' : 'bg-mp-card'">
                    <div class="flex items-center gap-2">
                      <span v-if="!section.is_computed" class="text-white text-sm">{{ expandedSections[section.section_key] ? '▼' : '▶' }}</span>
                      <span :class="section.is_computed ? 'font-bold text-mp-warning text-sm' : 'font-semibold text-white text-sm'">
                        {{ section.display_name }}
                      </span>
                      <span v-if="section.is_computed" class="text-xs text-white italic">auto</span>
                    </div>
                  </td>
                  <template v-for="m in 12" :key="m">
                    <td class="px-1 py-3 border-l border-mp-border/40">
                      <div class="grid grid-cols-4 text-center gap-px items-center">
                        <span class="text-white text-m">{{ fmtK(section[bKey][m]) }}</span>
                        <span class="text-mp-success text-m">{{ fmtK(section[aKey][m]) }}</span>
                        <span :class="varColor(section[vKey][m], section.section_key)" class="text-m font-semibold">
                          {{ fmtKVar(section[vKey][m]) }}
                        </span>
                        <span class="text-white text-xs">—</span>
                      </div>
                    </td>
                  </template>
                  <td class="px-1 py-3 border-l border-mp-border">
                    <div class="grid grid-cols-3 text-center gap-px">
                      <span class="text-white text-m font-semibold">{{ fmtK(annualSum(section[bKey])) }}</span>
                      <span class="text-mp-success text-m font-semibold">{{ fmtK(annualSum(section[aKey])) }}</span>
                      <span :class="varColor(annualSum(section[vKey]), section.section_key)" class="text-m font-bold">
                        {{ fmtKVar(annualSum(section[vKey])) }}
                      </span>
                    </div>
                  </td>
                </tr>

                <!-- Groups (Father) -->
                <template v-if="expandedSections[section.section_key] && !section.is_computed">
                  <template v-for="group in section.groups" :key="group.id">

                    <!-- Group row -->
                    <tr class="bg-mp-card/60 border-t border-mp-border/60 cursor-pointer hover:bg-mp-card-hover/30 transition-colors"
                      @click="toggleGroup(group.id)">
                      <td class="px-5 py-2.5 pl-9 sticky left-0 bg-mp-card/60 z-10">
                        <div class="flex items-center gap-2">
                          <span class="text-white text-sm">{{ expandedGroups[group.id] ? '▼' : '▶' }}</span>
                          <span class="text-sm font-semibold text-white">{{ group.name }}</span>
                        </div>
                      </td>
                      <template v-for="m in 12" :key="m">
                        <td class="px-1 py-2.5 border-l border-mp-border/30">
                          <div class="grid grid-cols-4 text-center gap-px items-center">
                            <span class="text-white text-sm">{{ fmtK(group[bKey][m]) }}</span>
                            <span class="text-mp-success text-sm">{{ fmtK(group[aKey][m]) }}</span>
                            <span :class="varColor(group[vKey][m], section.section_key)" class="text-sm">{{ fmtKVar(group[vKey][m]) }}</span>
                            <span class="text-white text-xs">—</span>
                          </div>
                        </td>
                      </template>
                      <td class="px-1 py-2.5 border-l border-mp-border">
                        <div class="grid grid-cols-3 text-center gap-px">
                          <span class="text-white text-sm">{{ fmtK(annualSum(group[bKey])) }}</span>
                          <span class="text-mp-success text-sm">{{ fmtK(annualSum(group[aKey])) }}</span>
                          <span :class="varColor(annualSum(group[vKey]), section.section_key)" class="text-sm">{{ fmtKVar(annualSum(group[vKey])) }}</span>
                        </div>
                      </td>
                    </tr>

                    <!-- Line items (Son) -->
                    <template v-if="expandedGroups[group.id]">
                      <tr v-for="item in group.line_items" :key="item.id"
                        class="border-t border-mp-border/40 hover:bg-mp-card-hover/20 transition-colors">
                        <td class="px-5 py-2 pl-16 sticky left-0 bg-mp-page z-10">
                          <span class="text-sm text-white">{{ item.label }}</span>
                        </td>
                        <template v-for="m in 12" :key="m">
                          <td class="px-1 py-2 border-l border-mp-border/40">
                            <div class="grid grid-cols-4 text-center gap-px items-center">
                              <span class="text-white/70 text-sm">{{ fmtK(item[bKey][m]) }}</span>
                              <span class="text-mp-success/70 text-sm">
                                {{ item.monthly_actual[m] !== null ? fmtK(item[aKey][m]) : '—' }}
                              </span>
                              <span v-if="item[vKey][m] !== null"
                                :class="varColor(item[vKey][m], section.section_key)" class="text-sm">
                                {{ fmtKVar(item[vKey][m]) }}
                              </span>
                              <span v-else class="text-white text-sm">—</span>

                              <!-- Comment/Action button -->
                              <button @click.stop="openNoteModal(item, m, section.section_key)"
                                class="mx-auto w-6 h-6 flex items-center justify-center rounded-md transition-colors text-xs"
                                :class="hasNote(item.id, m)
                                  ? 'bg-mp-gold-dark/30 text-white border border-mp-gold/50'
                                  : 'bg-mp-card-hover/50 text-white hover:text-white hover:bg-mp-page/50'"
                                :title="hasNote(item.id, m) ? 'View / edit note' : 'Add comment or action'">
                                {{ hasNote(item.id, m) ? '💬' : '+' }}
                              </button>
                            </div>
                          </td>
                        </template>
                        <td class="px-1 py-2 border-l border-mp-border">
                          <div class="grid grid-cols-3 text-center gap-px">
                            <span class="text-white/70 text-sm">{{ fmtK(annualSum(item[bKey])) }}</span>
                            <span class="text-mp-success/70 text-sm">{{ fmtK(annualSum(item[aKey])) }}</span>
                            <span :class="varColor(annualSum(item[vKey]), section.section_key)" class="text-sm">{{ fmtKVar(annualSum(item[vKey])) }}</span>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </template>
                </template>

              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Legend -->
      <div class="flex items-center gap-6 text-xs text-white flex-wrap">
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-mp-teal inline-block"></span> Budget</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-mp-success inline-block"></span> Actual</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-mp-warning inline-block"></span> Variance (Actual − Budget)</span>
        <span class="text-white">· 💬 = add comment / action per variance cell</span>
        <span class="text-white">· Click section / group rows to expand</span>
      </div>

    </div>

    <!-- ── COMMENT / ACTION MODAL ──────────────────────────────────────────── -->
    <div v-if="noteModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70"
      @click.self="noteModal.open = false">
      <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-lg shadow-2xl">

        <!-- Header -->
        <div class="px-6 py-4 border-b border-mp-border flex items-start justify-between gap-4">
          <div>
            <h3 class="text-base font-bold text-white">Variance Note</h3>
            <p class="text-xs text-white mt-0.5">
              <span class="text-white font-medium">{{ noteModal.itemLabel }}</span>
              · {{ months[noteModal.month] }} {{ budget.year }}
            </p>
            <div class="flex items-center gap-3 mt-1.5">
              <span class="text-xs text-white">Budget: {{ fmtK(noteModal.budget) }}</span>
              <span class="text-xs text-mp-success">Actual: {{ fmtK(noteModal.actual) }}</span>
              <span :class="noteModal.varVal >= 0 ? 'text-mp-success' : 'text-mp-danger'" class="text-xs font-bold">
                Var: {{ fmtKVar(noteModal.varVal) }}
              </span>
            </div>
          </div>
          <button @click="noteModal.open = false" class="text-white hover:text-white text-xl flex-shrink-0">✕</button>
        </div>

        <div class="px-6 py-5 space-y-4">
          <!-- Comment -->
          <div>
            <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1.5">
              💬 Variance Comment
            </label>
            <textarea v-model="noteModal.comment" rows="3"
              placeholder="Explain the reason for this variance…"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-mp-teal resize-none" />
          </div>

          <!-- Action -->
          <div>
            <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1.5">
              ⚡ Action to be Taken
            </label>
            <textarea v-model="noteModal.action" rows="3"
              placeholder="Describe the corrective action or next step…"
              class="w-full bg-mp-card-hover border border-mp-gold/50 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-mp-gold resize-none" />
          </div>

          <!-- Priority -->
          <div>
            <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1.5">Priority</label>
            <div class="flex gap-2">
              <button v-for="p in priorities" :key="p.key"
                @click="noteModal.priority = p.key"
                :class="noteModal.priority === p.key ? p.activeClass : 'bg-mp-card-hover border-mp-border text-white hover:text-white'"
                class="flex-1 text-xs font-semibold py-2 rounded-lg border transition-colors">
                {{ p.label }}
              </button>
            </div>
          </div>
        </div>

        <div class="px-6 py-4 border-t border-mp-border flex gap-3">
          <button v-if="hasNote(noteModal.itemId, noteModal.month)"
            @click="deleteNote"
            class="px-4 py-2.5 bg-mp-danger/40 hover:bg-mp-danger/70 text-mp-danger border border-mp-danger/50 text-sm font-semibold rounded-lg transition-colors">
            Delete
          </button>
          <button @click="noteModal.open = false"
            class="flex-1 bg-mp-card-hover hover:bg-mp-page text-white text-sm font-semibold py-2.5 rounded-lg transition-colors">
            Cancel
          </button>
          <button @click="saveNote"
            class="flex-1 bg-mp-teal hover:bg-mp-teal text-white text-sm font-semibold py-2.5 rounded-lg transition-colors">
            Save Note
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  company:   Object,
  budget:    Object,
  data:      Object,
  months:    Object,
  directors: { type: Array, default: () => [] },
})

const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']

// ─── Tabs ─────────────────────────────────────────────────────────────────────
const statementTabs = [
  { key: 'income',        label: 'Income Statement' },
  { key: 'balance_sheet', label: 'Balance Sheet' },
  { key: 'cashflow',      label: 'Cash Flow' },
]
const activeTab       = ref('income')
const currentSections = computed(() => props.data[activeTab.value] ?? [])

// ─── View mode ────────────────────────────────────────────────────────────────
const viewModes = [
  { key: 'monthly',    label: 'Monthly' },
  { key: 'cumulative', label: 'Cumulative YTD' },
]
const viewMode = ref('monthly')
const bKey = computed(() => viewMode.value === 'cumulative' ? 'cumulative_budget'   : 'monthly_budget')
const aKey = computed(() => viewMode.value === 'cumulative' ? 'cumulative_actual'   : 'monthly_actual')
const vKey = computed(() => viewMode.value === 'cumulative' ? 'cumulative_variance' : 'monthly_variance')

// ─── Expand/collapse ──────────────────────────────────────────────────────────
const expandedSections = ref({})
const expandedGroups   = ref({})
function toggleSection(key) { expandedSections.value[key] = !expandedSections.value[key] }
function toggleGroup(id)    { expandedGroups.value[id]    = !expandedGroups.value[id] }

// ─── Variance notes (stored in memory — can be wired to API later) ────────────
const varNotes = ref({})   // key: `${itemId}__${month}` → { comment, action, priority }

function noteKey(itemId, month) { return `${itemId}__${month}` }
function hasNote(itemId, month) {
  const n = varNotes.value[noteKey(itemId, month)]
  return n && (n.comment || n.action)
}

// ─── Note modal ───────────────────────────────────────────────────────────────
const priorities = [
  { key: 'low',    label: '🟢 Low',    activeClass: 'bg-mp-success/50 border-mp-success text-mp-success' },
  { key: 'medium', label: '🟡 Medium', activeClass: 'bg-mp-warning/50 border-mp-warning text-mp-warning' },
  { key: 'high',   label: '🔴 High',   activeClass: 'bg-mp-danger/50 border-mp-danger text-mp-danger' },
]

const noteModal = reactive({
  open:       false,
  itemId:     null,
  itemLabel:  '',
  month:      null,
  sectionKey: '',
  budget:     0,
  actual:     null,
  varVal:     null,
  comment:    '',
  action:     '',
  priority:   'medium',
})

function openNoteModal(item, m, sectionKey) {
  const existing = varNotes.value[noteKey(item.id, m)] ?? {}
  noteModal.open       = true
  noteModal.itemId     = item.id
  noteModal.itemLabel  = item.label
  noteModal.month      = m
  noteModal.sectionKey = sectionKey
  noteModal.budget     = item.monthly_budget?.[m] ?? 0
  noteModal.actual     = item.monthly_actual?.[m] ?? null
  noteModal.varVal     = item.monthly_variance?.[m] ?? null
  noteModal.comment    = existing.comment  ?? ''
  noteModal.action     = existing.action   ?? ''
  noteModal.priority   = existing.priority ?? 'medium'
}

function saveNote() {
  if (!noteModal.itemId) return
  varNotes.value[noteKey(noteModal.itemId, noteModal.month)] = {
    comment:  noteModal.comment,
    action:   noteModal.action,
    priority: noteModal.priority,
  }
  noteModal.open = false
}

function deleteNote() {
  delete varNotes.value[noteKey(noteModal.itemId, noteModal.month)]
  noteModal.open = false
}

// ─── Auto Insights ────────────────────────────────────────────────────────────
const showInsights  = ref(false)
const showDirectors = ref(false)

const insights = computed(() => {
  const list = []
  const income = props.data?.income ?? []

  function findSection(key) { return income.find(s => s.section_key === key) }
  function annualBudget(sec) { return annualSum(sec?.monthly_budget ?? {}) }
  function annualActual(sec) { return annualSum(sec?.monthly_actual ?? {}) }
  function annualVar(sec)    { return annualSum(sec?.monthly_variance ?? {}) }

  const rev     = findSection('sales_revenue')
  const cogs    = findSection('cogs')
  const gp      = findSection('gross_profit')
  const ebitda  = findSection('ebitda')
  const netP    = findSection('net_profit')
  const mktExp  = findSection('marketing_expenses')
  const gaExp   = findSection('ga_expenses')

  // 1. Revenue vs budget
  if (rev) {
    const vr = annualVar(rev)
    const bp = annualBudget(rev)
    if (bp !== 0) {
      const pct = ((vr / bp) * 100).toFixed(1)
      if (Math.abs(vr) > 0) {
        list.push({
          type:  vr >= 0 ? 'positive' : 'negative',
          icon:  vr >= 0 ? '📈' : '📉',
          title: vr >= 0 ? `Revenue above budget by ${pct}%` : `Revenue below budget by ${Math.abs(pct)}%`,
          body:  `Annual actual: ${fmtK(annualActual(rev))} vs budget ${fmtK(annualBudget(rev))}`,
        })
      }
    }
  }

  // 2. Gross margin
  if (gp && rev) {
    const gpA = annualActual(gp)
    const revA = annualActual(rev)
    const gpB  = annualBudget(gp)
    const revB = annualBudget(rev)
    if (revA > 0 && revB > 0) {
      const gmA = ((gpA / revA) * 100).toFixed(1)
      const gmB = ((gpB / revB) * 100).toFixed(1)
      const diff = (gmA - gmB).toFixed(1)
      if (Math.abs(diff) >= 0.5) {
        list.push({
          type:  diff >= 0 ? 'positive' : 'negative',
          icon:  diff >= 0 ? '✅' : '⚠️',
          title: `Gross margin ${diff >= 0 ? 'improved' : 'declined'} by ${Math.abs(diff)}pp`,
          body:  `Actual GM: ${gmA}% vs budgeted ${gmB}%`,
        })
      }
    }
  }

  // 3. COGS overspend
  if (cogs) {
    const cv = annualVar(cogs)
    const cb = annualBudget(cogs)
    if (cb !== 0 && cv > cb * 0.05) {
      list.push({
        type:  'negative',
        icon:  '⚠️',
        title: `COGS overspent by ${fmtK(cv)}`,
        body:  `Cost of goods sold exceeded budget by ${((cv/cb)*100).toFixed(1)}% — review supplier pricing or volume mix.`,
      })
    }
  }

  // 4. EBITDA
  if (ebitda) {
    const ev = annualVar(ebitda)
    const eb = annualBudget(ebitda)
    if (eb !== 0 && Math.abs(ev) > Math.abs(eb) * 0.03) {
      list.push({
        type:  ev >= 0 ? 'positive' : 'warning',
        icon:  ev >= 0 ? '💪' : '🔔',
        title: `EBITDA ${ev >= 0 ? 'beat' : 'missed'} budget by ${fmtK(Math.abs(ev))}`,
        body:  `${((Math.abs(ev)/Math.abs(eb))*100).toFixed(1)}% ${ev >= 0 ? 'above' : 'below'} plan`,
      })
    }
  }

  // 5. Marketing expenses
  if (mktExp) {
    const mv = annualVar(mktExp)
    const mb = annualBudget(mktExp)
    if (mb !== 0 && mv > mb * 0.1) {
      list.push({
        type:  'warning',
        icon:  '📣',
        title: `Marketing spend over budget by ${fmtK(mv)}`,
        body:  `${((mv/mb)*100).toFixed(1)}% above plan — review campaign ROI.`,
      })
    } else if (mb !== 0 && mv < -(mb * 0.1)) {
      list.push({
        type:  'info',
        icon:  '📣',
        title: `Marketing under-spent by ${fmtK(Math.abs(mv))}`,
        body:  `${((Math.abs(mv)/mb)*100).toFixed(1)}% below plan — campaigns may be delayed.`,
      })
    }
  }

  // 6. Net profit
  if (netP) {
    const nv = annualVar(netP)
    if (nv !== 0) {
      list.push({
        type:  nv >= 0 ? 'positive' : 'negative',
        icon:  nv >= 0 ? '🏆' : '🚨',
        title: `Net profit ${nv >= 0 ? 'exceeds' : 'falls short of'} budget`,
        body:  `Variance: ${fmtKVar(nv)} | Actual: ${fmtK(annualActual(netP))} | Budget: ${fmtK(annualBudget(netP))}`,
      })
    }
  }

  // 7. Months with highest variance
  if (rev) {
    let worstMonth = null, worstVal = 0
    for (let m = 1; m <= 12; m++) {
      const v = Math.abs(rev.monthly_variance?.[m] ?? 0)
      if (v > worstVal) { worstVal = v; worstMonth = m }
    }
    if (worstMonth && worstVal > 0) {
      const mv = rev.monthly_variance?.[worstMonth] ?? 0
      list.push({
        type:  'info',
        icon:  '📅',
        title: `${monthNames[worstMonth-1]} had the largest revenue variance`,
        body:  `${fmtKVar(mv)} vs budget — consider reviewing this month's drivers.`,
      })
    }
  }

  return list
})

// ─── Charts ───────────────────────────────────────────────────────────────────
const chartMetric = ref('sales_revenue')
const chartW = 600
const chartH = 160
const chartPad = 20

const chartCols = computed(() => {
  const income = props.data?.income ?? []
  const sec = income.find(s => s.section_key === chartMetric.value)
  if (!sec) return []

  const allVals = []
  for (let m = 1; m <= 12; m++) {
    allVals.push(sec.monthly_budget?.[m] ?? 0)
    allVals.push(sec.monthly_actual?.[m] ?? 0)
  }
  const maxVal = Math.max(...allVals, 1)

  const colW   = (chartW - chartPad * 2) / 12
  const barW   = (colW - 6) / 2
  const plotH  = chartH - chartPad * 2 - 12   // 12px for labels

  return Array.from({ length: 12 }, (_, i) => {
    const m  = i + 1
    const b  = sec.monthly_budget?.[m] ?? 0
    const a  = sec.monthly_actual?.[m] ?? 0
    const bh = Math.max((b / maxVal) * plotH, 1)
    const ah = a > 0 ? Math.max((a / maxVal) * plotH, 1) : 0
    return {
      x:      chartPad + i * colW,
      bw:     barW,
      by:     chartPad + plotH - bh,
      bh,
      ay:     chartPad + plotH - ah,
      ah,
      varPos: a >= b,
    }
  })
})

// ─── Top variances ────────────────────────────────────────────────────────────
const topVariances = computed(() => {
  const items = []
  const allSections = [
    ...(props.data?.income ?? []),
    ...(props.data?.balance_sheet ?? []),
    ...(props.data?.cashflow ?? []),
  ]
  allSections.forEach(sec => {
    if (sec.is_computed) return
    ;(sec.groups ?? []).forEach(grp => {
      ;(grp.line_items ?? []).forEach(item => {
        const v = annualSum(item.monthly_variance ?? {})
        if (v !== 0) {
          items.push({
            label:  item.label,
            var:    v,
            budget: annualSum(item.monthly_budget ?? {}),
            sKey:   sec.section_key,
          })
        }
      })
    })
  })

  items.sort((a, b) => Math.abs(b.var) - Math.abs(a.var))
  const top = items.slice(0, 6)
  const maxAbs = Math.max(...top.map(i => Math.abs(i.var)), 1)
  const expenseKeys = new Set(['cogs','marketing_expenses','ga_expenses','depreciation','taxes'])

  return top.map(t => ({
    label:  t.label,
    varStr: fmtKVar(t.var),
    pct:    Math.round((Math.abs(t.var) / maxAbs) * 100),
    isGood: expenseKeys.has(t.sKey) ? t.var < 0 : t.var > 0,
  }))
})

// ─── Formatters ───────────────────────────────────────────────────────────────
function fmtK(v) {
  if (v === null || v === undefined || isNaN(v)) return '—'
  const abs = Math.abs(v)
  if (abs >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M'
  if (abs >= 1_000)    return (v / 1_000).toFixed(0) + 'K'
  return Number(v).toFixed(0)
}
function fmtKVar(v) {
  if (v === null || v === undefined || isNaN(v)) return '—'
  const prefix = v > 0 ? '+' : ''
  const abs = Math.abs(v)
  if (abs >= 1_000_000) return prefix + (v / 1_000_000).toFixed(1) + 'M'
  if (abs >= 1_000)    return prefix + (v / 1_000).toFixed(0) + 'K'
  return prefix + Number(v).toFixed(0)
}
const expenseKeys = new Set(['cogs','marketing_expenses','ga_expenses','depreciation','taxes','finance_income_expense','cfi','current_liabilities','non_current_liabilities'])
function varColor(v, sectionKey) {
  if (v === null || v === 0) return 'text-white'
  const isExpense = expenseKeys.has(sectionKey)
  if (isExpense) return v > 0 ? 'text-mp-danger' : 'text-mp-success'
  return v > 0 ? 'text-mp-success' : 'text-mp-danger'
}
function annualSum(obj) {
  if (!obj) return 0
  return Object.values(obj).reduce((s, v) => s + (parseFloat(v) || 0), 0)
}
</script>