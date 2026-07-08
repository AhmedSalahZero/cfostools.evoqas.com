<template>
  <div class="min-h-screen bg-mp-page text-white">

    <!-- Top bar -->
    <div class="sticky top-0 z-40 bg-mp-page/95 backdrop-blur border-b border-mp-border px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Link :href="route('budgets.show', [company.id, budget.id])"
          class="text-white hover:text-white text-sm transition-colors">
          ← Variance Report
        </Link>
        <span class="text-white">/</span>
        <span class="text-white text-sm font-semibold">Review Room</span>
        <span class="bg-mp-teal-subtle/60 text-white text-xs font-semibold px-2.5 py-1 rounded-full border border-mp-teal/40">
          {{ director.name }}
        </span>
        <span v-if="director.title" class="text-white text-xs">{{ director.title }}</span>
      </div>
      <div class="flex items-center gap-3">
        <span class="text-white text-xs">{{ budget.name }} · {{ budget.year }} · {{ budget.currency }}</span>
        <div class="flex gap-1 bg-mp-card rounded-lg p-1">
          <button v-for="tab in ['overview', 'details', 'pipeline']" :key="tab"
            @click="activeTab = tab"
            :class="activeTab === tab ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
            class="text-xs font-semibold px-3 py-1.5 rounded-md transition-colors capitalize">
            {{ tab === 'pipeline' ? 'Pipeline & Prospects' : tab.charAt(0).toUpperCase() + tab.slice(1) }}
          </button>
        </div>
      </div>
    </div>

    <div class="max-w-[1600px] mx-auto px-6 py-6 space-y-6">

      <!-- ── Month selector bar ── -->
      <div class="flex gap-2 overflow-x-auto pb-1">
        <button v-for="m in 12" :key="m" @click="selectedMonth = m"
          :class="[
            'flex-shrink-0 px-3 py-2 rounded-lg text-xs font-semibold transition-all border',
            selectedMonth === m
              ? 'bg-mp-teal border-mp-teal text-white shadow-lg shadow-blue-900/30'
              : 'bg-mp-card border-mp-border text-white hover:text-white hover:border-mp-border',
            hasReview(m) ? 'ring-1 ring-mp-success/40' : ''
          ]">
          {{ months[m] }}
          <span v-if="hasReview(m)" class="ml-1 text-mp-success">✓</span>
        </button>
      </div>

      <!-- ── OVERVIEW TAB ── -->
      <template v-if="activeTab === 'overview'">

        <!-- KPI cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-mp-card border border-mp-border rounded-xl p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-white mb-1">Budget</p>
            <p class="text-2xl font-bold text-white">{{ fmtCur(monthBudget(selectedMonth)) }}</p>
            <p class="text-xs text-white mt-1">{{ months[selectedMonth] }} {{ budget.year }}</p>
          </div>
          <div class="bg-mp-card border border-mp-border rounded-xl p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-white mb-1">Actual</p>
            <p class="text-2xl font-bold" :class="monthActual(selectedMonth) >= monthBudget(selectedMonth) ? 'text-mp-success' : 'text-mp-danger'">
              {{ fmtCur(monthActual(selectedMonth)) }}
            </p>
            <p class="text-xs text-white mt-1">{{ achievementPct(selectedMonth) }}% of target</p>
          </div>
          <div class="bg-mp-card border border-mp-border rounded-xl p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-white mb-1">Variance</p>
            <p class="text-2xl font-bold" :class="monthVariance(selectedMonth) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
              {{ monthVariance(selectedMonth) >= 0 ? '+' : '' }}{{ fmtCur(monthVariance(selectedMonth)) }}
            </p>
            <p class="text-xs text-white mt-1">{{ monthVariance(selectedMonth) >= 0 ? 'Above' : 'Below' }} target</p>
          </div>
          <div class="bg-mp-card border border-mp-border rounded-xl p-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-white mb-1">YTD Progress</p>
            <p class="text-2xl font-bold text-white">{{ ytdAchievementPct() }}%</p>
            <p class="text-xs text-white mt-1">Jan–{{ months[selectedMonth] }}</p>
          </div>
        </div>

        <!-- Bar chart -->
        <div class="bg-mp-card border border-mp-border rounded-xl p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-white mb-4">Full Year — Budget vs Actual</p>
          <div class="overflow-x-auto">
            <div class="flex gap-1 items-end min-w-max" style="height:120px">
              <template v-for="m in 12" :key="m">
                <div class="flex flex-col items-center gap-0.5 w-16 flex-shrink-0">
                  <div class="flex items-end gap-0.5 w-full" style="height:90px">
                    <div class="flex-1 bg-mp-teal-subtle/50 rounded-t transition-all"
                      :style="{ height: barHeight(monthBudget(m), maxBarValue) + 'px' }" />
                    <div class="flex-1 rounded-t transition-all"
                      :class="monthActual(m) >= monthBudget(m) ? 'bg-mp-success/70' : 'bg-mp-danger/70'"
                      :style="{ height: barHeight(monthActual(m), maxBarValue) + 'px' }" />
                  </div>
                  <span class="text-xs text-white">{{ months[m] }}</span>
                </div>
              </template>
            </div>
            <div class="flex gap-4 mt-3">
              <div class="flex items-center gap-1.5"><div class="w-3 h-3 bg-mp-teal-subtle/50 rounded" /><span class="text-xs text-white">Budget</span></div>
              <div class="flex items-center gap-1.5"><div class="w-3 h-3 bg-mp-success/70 rounded" /><span class="text-xs text-white">Actual (above)</span></div>
              <div class="flex items-center gap-1.5"><div class="w-3 h-3 bg-mp-danger/70 rounded" /><span class="text-xs text-white">Actual (below)</span></div>
            </div>
          </div>
        </div>

        <!-- Review form -->
        <div class="bg-mp-card border border-mp-border rounded-xl p-5">
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-semibold uppercase tracking-widest text-white">{{ months[selectedMonth] }} Review</p>
            <div class="flex items-center gap-2">
              <span class="text-xs text-white">Priority:</span>
              <template v-for="p in ['low','medium','high']" :key="p">
                <button @click="currentReview.priority = p"
                  :class="[
                    'px-2.5 py-1 rounded text-xs font-semibold border transition-all',
                    currentReview.priority === p
                      ? p === 'high'   ? 'bg-mp-danger/30 border-mp-danger text-mp-danger'
                        : p === 'medium' ? 'bg-mp-warning/30 border-mp-warning text-mp-warning'
                        : 'bg-mp-success/30 border-mp-success text-mp-success'
                      : 'bg-mp-card-hover border-mp-border text-white hover:text-white'
                  ]">
                  {{ p.charAt(0).toUpperCase() + p.slice(1) }}
                </button>
              </template>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1">Variance Comment</label>
              <textarea v-model="currentReview.variance_comment" rows="4"
                placeholder="Why did actuals differ from budget this month?"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-mp-teal resize-none" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1">Action Taken / To Be Taken</label>
              <textarea v-model="currentReview.action_taken" rows="4"
                placeholder="What corrective actions were taken or are planned?"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-mp-teal resize-none" />
            </div>
          </div>
          <div class="flex justify-end mt-4">
            <button @click="saveReview" :disabled="saving"
              class="bg-mp-teal hover:bg-mp-teal disabled:opacity-50 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors">
              <span v-if="saving">Saving…</span>
              <span v-else-if="justSaved">✓ Saved</span>
              <span v-else>Save Review</span>
            </button>
          </div>
        </div>

      </template>

      <!-- ── DETAILS TAB ── -->
      <template v-if="activeTab === 'details'">
        <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
          <div class="px-5 py-3 border-b border-mp-border flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-widest text-white">Line-by-Line — {{ months[selectedMonth] }}</p>
            <div class="flex gap-1 bg-mp-card-hover rounded-lg p-0.5">
              <button v-for="v in ['monthly','ytd']" :key="v" @click="viewMode = v"
                :class="viewMode === v ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
                class="px-3 py-1 rounded text-xs font-semibold transition-colors">
                {{ v === 'ytd' ? 'YTD' : 'Monthly' }}
              </button>
            </div>
          </div>

          <div class="flex items-center px-5 py-2 border-b border-mp-border bg-mp-card-hover/50 text-xs font-semibold text-white">
            <div class="flex-1">Revenue Line Item</div>
            <div class="w-32 text-right">Budget</div>
            <div class="w-32 text-right">Actual</div>
            <div class="w-32 text-right">Variance</div>
            <div class="w-24 text-right">Ach%</div>
          </div>

          <template v-for="item in assignedItems" :key="item.id">
            <div class="flex items-center px-5 py-3 border-b border-mp-border/50 hover:bg-mp-card-hover/30 transition-colors">
              <div class="flex-1">
                <p class="text-sm text-white">{{ item.label }}</p>
                <p class="text-xs text-white">{{ item.group_name }}</p>
              </div>
              <div class="w-32 text-right text-sm text-white">{{ fmtCur(itemBudgetVal(item)) }}</div>
              <div class="w-32 text-right text-sm"
                :class="itemActualVal(item) >= itemBudgetVal(item) ? 'text-mp-success' : 'text-mp-danger'">
                {{ fmtCur(itemActualVal(item)) }}
              </div>
              <div class="w-32 text-right text-sm font-semibold"
                :class="itemVarianceVal(item) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
                {{ itemVarianceVal(item) >= 0 ? '+' : '' }}{{ fmtCur(itemVarianceVal(item)) }}
              </div>
              <div class="w-24 text-right text-sm"
                :class="itemAchPct(item) >= 100 ? 'text-mp-success' : itemAchPct(item) >= 80 ? 'text-mp-warning' : 'text-mp-danger'">
                {{ itemAchPct(item) }}%
              </div>
            </div>
          </template>

          <div v-if="!assignedItems.length" class="px-5 py-8 text-center text-white text-sm">
            No revenue line items have been assigned to this Sales Director yet.
          </div>

          <div v-if="assignedItems.length" class="flex items-center px-5 py-3 bg-mp-card-hover font-bold text-sm">
            <div class="flex-1 text-white">Total Revenue</div>
            <div class="w-32 text-right text-white">{{ fmtCur(monthBudget(selectedMonth)) }}</div>
            <div class="w-32 text-right" :class="monthActual(selectedMonth) >= monthBudget(selectedMonth) ? 'text-mp-success' : 'text-mp-danger'">
              {{ fmtCur(monthActual(selectedMonth)) }}
            </div>
            <div class="w-32 text-right" :class="monthVariance(selectedMonth) >= 0 ? 'text-mp-success' : 'text-mp-danger'">
              {{ monthVariance(selectedMonth) >= 0 ? '+' : '' }}{{ fmtCur(monthVariance(selectedMonth)) }}
            </div>
            <div class="w-24 text-right" :class="achievementPct(selectedMonth) >= 100 ? 'text-mp-success' : 'text-mp-warning'">
              {{ achievementPct(selectedMonth) }}%
            </div>
          </div>
        </div>
      </template>

      <!-- ── PIPELINE & PROSPECTS TAB ── -->
      <template v-if="activeTab === 'pipeline'">

        <!-- Two repeater cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Pipeline -->
          <div class="bg-mp-card border border-mp-border rounded-xl p-5 flex flex-col gap-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-mp-teal rounded-full"></div>
                <p class="text-xs font-semibold uppercase tracking-widest text-white">Pipeline — {{ months[selectedMonth] }}</p>
              </div>
              <span class="text-xs text-white">Confirmed deals not yet invoiced</span>
            </div>

            <div class="space-y-2">
              <!-- Column headers -->
              <div class="grid grid-cols-[1fr_140px_32px] gap-2 px-1">
                <span class="text-xs font-semibold text-white">Deal / Client Name</span>
                <span class="text-xs font-semibold text-white text-right">Amount ({{ budget.currency }})</span>
                <span></span>
              </div>
              <!-- Rows -->
              <template v-for="(row, ri) in currentReview.pipeline_items" :key="ri">
                <div class="grid grid-cols-[1fr_140px_32px] gap-2 items-center">
                  <input v-model="row.name" type="text" placeholder="Client / deal name…"
                    class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-1.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-mp-teal" />
                  <input v-model.number="row.amount" type="number" min="0" placeholder="0"
                    class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-1.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-mp-teal text-right" />
                  <button @click="removePipelineRow(ri)"
                    class="w-8 h-8 flex items-center justify-center text-white hover:text-mp-danger rounded-lg hover:bg-mp-card-hover transition-colors text-xs">
                    ✕
                  </button>
                </div>
              </template>
              <div v-if="!currentReview.pipeline_items.length"
                class="text-center py-4 text-xs text-white border border-dashed border-mp-border rounded-lg">
                No pipeline entries yet
              </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-mp-border">
              <button @click="addPipelineRow"
                class="text-xs text-white hover:text-white font-semibold flex items-center gap-1 transition-colors">
                + Add Row
              </button>
              <div class="text-right">
                <p class="text-xs text-white">Total Pipeline</p>
                <p class="text-xl font-bold text-white">{{ fmtCur(pipelineTotal) }}</p>
              </div>
            </div>
          </div>

          <!-- Prospects -->
          <div class="bg-mp-card border border-mp-border rounded-xl p-5 flex flex-col gap-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-mp-gold rounded-full"></div>
                <p class="text-xs font-semibold uppercase tracking-widest text-white">Prospects — {{ months[selectedMonth] }}</p>
              </div>
              <span class="text-xs text-white">Unconfirmed opportunities</span>
            </div>

            <div class="space-y-2">
              <div class="grid grid-cols-[1fr_140px_32px] gap-2 px-1">
                <span class="text-xs font-semibold text-white">Prospect / Opportunity Name</span>
                <span class="text-xs font-semibold text-white text-right">Potential ({{ budget.currency }})</span>
                <span></span>
              </div>
              <template v-for="(row, ri) in currentReview.prospects_items" :key="ri">
                <div class="grid grid-cols-[1fr_140px_32px] gap-2 items-center">
                  <input v-model="row.name" type="text" placeholder="Prospect / opportunity name…"
                    class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-1.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-mp-gold" />
                  <input v-model.number="row.amount" type="number" min="0" placeholder="0"
                    class="bg-mp-card-hover border border-mp-border rounded-lg px-3 py-1.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-mp-gold text-right" />
                  <button @click="removeProspectsRow(ri)"
                    class="w-8 h-8 flex items-center justify-center text-white hover:text-mp-danger rounded-lg hover:bg-mp-card-hover transition-colors text-xs">
                    ✕
                  </button>
                </div>
              </template>
              <div v-if="!currentReview.prospects_items.length"
                class="text-center py-4 text-xs text-white border border-dashed border-mp-border rounded-lg">
                No prospect entries yet
              </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-mp-border">
              <button @click="addProspectsRow"
                class="text-xs text-white hover:text-white font-semibold flex items-center gap-1 transition-colors">
                + Add Row
              </button>
              <div class="text-right">
                <p class="text-xs text-white">Total Prospects</p>
                <p class="text-xl font-bold text-white">{{ fmtCur(prospectsTotal) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Coverage analysis -->
        <div class="bg-mp-card border border-mp-border rounded-xl p-5">
          <p class="text-xs font-semibold uppercase tracking-widest text-white mb-4">Coverage Analysis — {{ months[selectedMonth] }}</p>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <div class="text-center">
              <p class="text-xs text-white mb-1">Budget Gap</p>
              <p class="text-xl font-bold" :class="budgetGap(selectedMonth) <= 0 ? 'text-mp-success' : 'text-mp-danger'">
                {{ fmtCur(Math.max(0, budgetGap(selectedMonth))) }}
              </p>
              <p class="text-xs text-white">remaining to hit target</p>
            </div>
            <div class="text-center">
              <p class="text-xs text-white mb-1">Pipeline</p>
              <p class="text-xl font-bold text-white">{{ fmtCur(pipelineTotal) }}</p>
              <p class="text-xs text-white">{{ currentReview.pipeline_items.length }} confirmed deal(s)</p>
            </div>
            <div class="text-center">
              <p class="text-xs text-white mb-1">Prospects</p>
              <p class="text-xl font-bold text-white">{{ fmtCur(prospectsTotal) }}</p>
              <p class="text-xs text-white">{{ currentReview.prospects_items.length }} opportunity(s)</p>
            </div>
            <div class="text-center">
              <p class="text-xs text-white mb-1">Coverage Ratio</p>
              <p class="text-xl font-bold"
                :class="coverageRatio >= 100 ? 'text-mp-success' : coverageRatio >= 70 ? 'text-mp-warning' : 'text-mp-danger'">
                {{ coverageRatio }}%
              </p>
              <p class="text-xs text-white">pipeline / gap</p>
            </div>
          </div>
          <div class="h-3 bg-mp-card-hover rounded-full overflow-hidden">
            <div class="h-full flex">
              <div class="bg-mp-success/70 transition-all" :style="{ width: Math.min(achievementPct(selectedMonth), 100) + '%' }" />
              <div class="bg-mp-teal/60 transition-all"
                :style="{ width: Math.min(pipelineTotal / Math.max(monthBudget(selectedMonth), 1) * 100, 100 - Math.min(achievementPct(selectedMonth), 100)) + '%' }" />
            </div>
          </div>
          <div class="flex gap-4 mt-2">
            <div class="flex items-center gap-1.5"><div class="w-2.5 h-2.5 bg-mp-success/70 rounded" /><span class="text-xs text-white">Actual ({{ achievementPct(selectedMonth) }}%)</span></div>
            <div class="flex items-center gap-1.5"><div class="w-2.5 h-2.5 bg-mp-teal/60 rounded" /><span class="text-xs text-white">Pipeline</span></div>
            <div class="flex items-center gap-1.5"><div class="w-2.5 h-2.5 bg-mp-page rounded" /><span class="text-xs text-white">Gap remaining</span></div>
          </div>
        </div>

        <!-- Variance comment & action -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="bg-mp-card border border-mp-border rounded-xl p-5">
            <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-2">Variance Comment</label>
            <textarea v-model="currentReview.variance_comment" rows="3"
              placeholder="Why did actuals differ from budget?"
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-mp-teal resize-none" />
          </div>
          <div class="bg-mp-card border border-mp-border rounded-xl p-5">
            <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-2">Action Taken</label>
            <textarea v-model="currentReview.action_taken" rows="3"
              placeholder="Corrective actions planned or taken..."
              class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-mp-teal resize-none" />
          </div>
        </div>

        <div class="flex justify-end">
          <button @click="saveReview" :disabled="saving"
            class="bg-mp-teal hover:bg-mp-teal disabled:opacity-50 text-white text-sm font-semibold px-6 py-2 rounded-lg transition-colors">
            <span v-if="saving">Saving…</span>
            <span v-else-if="justSaved">✓ Saved</span>
            <span v-else>Save {{ months[selectedMonth] }} Review</span>
          </button>
        </div>

      </template>

    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  company:         { type: Object, required: true },
  budget:          { type: Object, required: true },
  director:        { type: Object, required: true },
  assignedItems:   { type: Array,  default: () => [] },
  reviews:         { type: Object, default: () => ({}) },   // keyed by month number
  summaryBudget:   { type: Object, default: () => ({}) },
  summaryActual:   { type: Object, default: () => ({}) },
  summaryVariance: { type: Object, default: () => ({}) },
  months:          { type: Object, required: true },
  isAdmin:         { type: Boolean, default: false },
})

// ── State ─────────────────────────────────────────────────────────────────────
const activeTab     = ref('overview')
const selectedMonth = ref(new Date().getMonth() + 1)
const viewMode      = ref('monthly')
const saving        = ref(false)
const justSaved     = ref(false)

// ── Current review form ───────────────────────────────────────────────────────
const currentReview = reactive({
  variance_comment : '',
  action_taken     : '',
  pipeline_items   : [],   // [{ name, amount }, ...]
  prospects_items  : [],   // [{ name, amount }, ...]
  priority         : 'medium',
})

// ── Computed totals from repeater rows ────────────────────────────────────────
const pipelineTotal = computed(() =>
  currentReview.pipeline_items.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0)
)
const prospectsTotal = computed(() =>
  currentReview.prospects_items.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0)
)

// ── Repeater helpers ──────────────────────────────────────────────────────────
function addPipelineRow()      { currentReview.pipeline_items.push({ name: '', amount: null }) }
function removePipelineRow(i)  { currentReview.pipeline_items.splice(i, 1) }
function addProspectsRow()     { currentReview.prospects_items.push({ name: '', amount: null }) }
function removeProspectsRow(i) { currentReview.prospects_items.splice(i, 1) }

// ── Load review when month changes ────────────────────────────────────────────
function loadReviewForMonth(m) {
  // reviews keyed by month; PHP integer keys may become strings in JSON
  const r = props.reviews[m] ?? props.reviews[String(m)] ?? {}
  currentReview.variance_comment = r.variance_comment ?? ''
  currentReview.action_taken     = r.action_taken ?? ''
  currentReview.pipeline_items   = (r.pipeline_items ?? []).map(i => ({ name: i.name ?? '', amount: i.amount ?? null }))
  currentReview.prospects_items  = (r.prospects_items ?? []).map(i => ({ name: i.name ?? '', amount: i.amount ?? null }))
  currentReview.priority         = r.priority ?? 'medium'
}

watch(selectedMonth, loadReviewForMonth, { immediate: true })

// ── Has saved review? ─────────────────────────────────────────────────────────
function hasReview(m) {
  const r = props.reviews[m] ?? props.reviews[String(m)]
  if (!r) return false
  return !!(r.variance_comment || r.action_taken ||
    (r.pipeline_items && r.pipeline_items.length) ||
    (r.prospects_items && r.prospects_items.length))
}

// ── Summary helpers — always try int key AND string key ───────────────────────
function getKeyVal(obj, m) {
  if (!obj) return 0
  return parseFloat(obj[m] ?? obj[String(m)] ?? 0) || 0
}

function monthBudget(m)   { return getKeyVal(props.summaryBudget, m) }
function monthActual(m)   { return getKeyVal(props.summaryActual, m) }
function monthVariance(m) { return getKeyVal(props.summaryVariance, m) }

function achievementPct(m) {
  const b = monthBudget(m)
  return b ? Math.round((monthActual(m) / b) * 100) : 0
}
function ytdAchievementPct() {
  let totalB = 0, totalA = 0
  for (let m = 1; m <= selectedMonth.value; m++) {
    totalB += monthBudget(m)
    totalA += monthActual(m)
  }
  return totalB ? Math.round((totalA / totalB) * 100) : 0
}

// ── Chart ─────────────────────────────────────────────────────────────────────
const maxBarValue = computed(() => {
  let max = 0
  for (let m = 1; m <= 12; m++) max = Math.max(max, monthBudget(m), monthActual(m))
  return max || 1
})
function barHeight(val, maxVal) {
  return Math.max(2, Math.round(((parseFloat(val) || 0) / maxVal) * 80))
}

// ── Per-item helpers (Details tab) ────────────────────────────────────────────
// monthly_budget / monthly_actual from PHP may have string or int keys — handle both
function getItemMonthVal(item, field, m) {
  const obj = item[field] ?? {}
  return parseFloat(obj[m] ?? obj[String(m)] ?? 0) || 0
}

function itemBudgetVal(item)   { return viewMode.value === 'ytd' ? ytdBudget(item)   : getItemMonthVal(item, 'monthly_budget',   selectedMonth.value) }
function itemActualVal(item)   { return viewMode.value === 'ytd' ? ytdActual(item)   : getItemMonthVal(item, 'monthly_actual',   selectedMonth.value) }
function itemVarianceVal(item) { return viewMode.value === 'ytd' ? ytdVariance(item) : getItemMonthVal(item, 'monthly_variance', selectedMonth.value) }
function itemAchPct(item) {
  const b = itemBudgetVal(item)
  return b ? Math.round((itemActualVal(item) / b) * 100) : 0
}
function ytdBudget(item) {
  let s = 0; for (let m = 1; m <= selectedMonth.value; m++) s += getItemMonthVal(item, 'monthly_budget', m); return s
}
function ytdActual(item) {
  let s = 0; for (let m = 1; m <= selectedMonth.value; m++) s += getItemMonthVal(item, 'monthly_actual', m); return s
}
function ytdVariance(item) { return ytdActual(item) - ytdBudget(item) }

// ── Pipeline coverage ─────────────────────────────────────────────────────────
function budgetGap(m) { return monthBudget(m) - monthActual(m) }
const coverageRatio = computed(() => {
  const gap = budgetGap(selectedMonth.value)
  if (gap <= 0) return 100
  return Math.round((pipelineTotal.value / gap) * 100)
})

// ── Save ──────────────────────────────────────────────────────────────────────
async function saveReview() {
  if (saving.value) return
  saving.value    = true
  justSaved.value = false
  try {
    const token = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
    const res = await fetch(
      route('budgets.save-director-review', [props.company.id, props.budget.id, props.director.id]),
      {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
        },
        body: JSON.stringify({
          month:            selectedMonth.value,
          variance_comment: currentReview.variance_comment || null,
          action_taken:     currentReview.action_taken     || null,
          pipeline_items:   currentReview.pipeline_items,
          prospects_items:  currentReview.prospects_items,
          priority:         currentReview.priority,
        }),
      }
    )
    if (res.ok) {
      justSaved.value = true
      setTimeout(() => { justSaved.value = false }, 2500)
    }
  } finally {
    saving.value = false
  }
}

// ── Format ────────────────────────────────────────────────────────────────────
function fmtCur(v) {
  if (v === null || v === undefined || isNaN(v)) return '—'
  const abs = Math.abs(v)
  const prefix = v < 0 ? '-' : ''
  if (abs >= 1_000_000) return prefix + (abs / 1_000_000).toFixed(1) + 'M'
  if (abs >= 1_000)     return prefix + (abs / 1_000).toFixed(1) + 'K'
  return prefix + abs.toFixed(0)
}
</script>