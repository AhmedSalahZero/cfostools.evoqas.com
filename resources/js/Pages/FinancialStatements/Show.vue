<template>
  <Head :title="`Financial Analysis — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Statements
          </Link>
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white">📊 Financial Analysis</h1>
              <p class="text-white text-sm mt-0.5">
                {{ company.name }} ·
                <span class="text-white">{{ formatDate(statement.period_from) }}</span>
                <span class="text-white mx-1">→</span>
                <span class="text-white">{{ formatDate(statement.period_to) }}</span>
                <span class="ml-2 text-xs px-2 py-0.5 rounded-full font-semibold"
                  :class="statement.status === 'final' ? 'bg-mp-success/15 text-mp-success' : 'bg-mp-warning/15 text-mp-warning'">
                  {{ statement.status === 'final' ? '✓ Final' : '✏ Draft' }}
                </span>
              </p>
            </div>
            <div class="flex items-center gap-2">
              <!-- Cash Forecast button -->
              <Link :href="`/portfolio-companies/${company.id}/financial-statements/${statement.id}/cash-forecast`"
                class="flex items-center gap-2 bg-mp-teal-dark hover:bg-mp-teal text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                💧 Cash Forecast
              </Link>
              <Link :href="`/portfolio-companies/${company.id}/financial-statements/${statement.id}/edit`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page text-white hover:text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
              </Link>
              <a :href="`/portfolio-companies/${company.id}/financial-statements/${statement.id}/export`"
                class="flex items-center gap-2 bg-mp-success hover:bg-mp-success text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export Excel
              </a>
            </div>
          </div>
        </div>

        <!-- Tabs -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex gap-1 border-b border-mp-border -mb-[1px]">
            <button v-for="tab in viewTabs" :key="tab.key"
              @click="activeTab = tab.key"
              :class="[
                'px-5 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors',
                activeTab === tab.key
                  ? 'border-mp-teal text-white bg-mp-teal-subtle/20'
                  : 'border-transparent text-white hover:text-white'
              ]">
              {{ tab.icon }} {{ tab.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- CONTENT -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Balance sheet alert -->
        <div v-if="bsBalance && activeTab === 'balance_sheet'" :class="[
          'flex items-center gap-3 px-4 py-3 rounded-lg mb-6 text-sm',
          bsBalance.balanced
            ? 'bg-mp-success/40 border border-mp-success text-white'
            : 'bg-mp-danger/40 border border-mp-danger text-mp-danger'
        ]">
          <span>{{ bsBalance.balanced ? '✅' : '⚠️' }}</span>
          <span v-if="bsBalance.balanced">Balance sheet is balanced — Total Assets = Total Liabilities & Equity</span>
          <span v-else>
            Out of balance by <strong>{{ formatNum(bsBalance.difference) }} {{ statement.currency }}</strong>.
            Total Assets must equal Total Liabilities + Equity.
          </span>
        </div>

        <!-- Common-size toggle -->
        <div class="flex items-center justify-between mb-4"
          v-if="activeTab === 'income' || activeTab === 'balance_sheet'">
          <p class="text-xs text-white">
            <span v-if="activeTab === 'income'">% column = each line as % of Sales Revenue</span>
            <span v-else>% column = each section as % of Total Assets</span>
          </p>
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <span class="text-xs text-white">Show Common-Size %</span>
            <div @click="showCommonSize = !showCommonSize"
              :class="showCommonSize ? 'bg-mp-teal' : 'bg-mp-page'"
              class="relative w-10 h-5 rounded-full transition-colors cursor-pointer">
              <div :class="showCommonSize ? 'translate-x-5' : 'translate-x-0.5'"
                class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"></div>
            </div>
          </label>
        </div>

        <!-- ── INCOME STATEMENT ── -->
        <div v-if="activeTab === 'income'">
          <div v-if="!sections.income || !sections.income.length"
            class="text-center py-16 text-white bg-mp-card rounded-xl border border-mp-border">
            No income statement data entered yet.
          </div>
          <div v-else class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border bg-mp-card-hover/60">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Description</th>
                  <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">
                    Amount ({{ statement.currency }})
                  </th>
                  <th v-if="showCommonSize"
                    class="text-right text-xs font-semibold text-white uppercase tracking-widest px-4 py-3 w-20">%</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="sec in sections.income" :key="sec.section_key">
                  <!-- Section row -->
                  <tr :class="sec.is_computed
                    ? 'bg-mp-teal-subtle/20 border-t-2 border-mp-teal/40'
                    : 'bg-mp-card-hover/30 border-t border-mp-border'">
                    <td class="px-6 py-3">
                      <div class="flex items-center gap-2">
                        <span v-if="sec.is_computed"
                          class="text-xs bg-mp-teal-subtle text-white px-1.5 py-0.5 rounded font-semibold flex-shrink-0">AUTO</span>
                        <span :class="sec.is_computed ? 'text-white font-bold' : 'text-white font-semibold'">
                          {{ sec.display_name }}
                        </span>
                      </div>
                    </td>
                    <td class="px-6 py-3 text-right font-bold"
                      :class="sec.total < 0 ? 'text-mp-danger' : sec.is_computed ? 'text-white' : 'text-mp-success'">
                      {{ formatNum(sec.total) }}
                    </td>
                    <td v-if="showCommonSize" class="px-4 py-3 text-right text-white text-xs">
                      {{ sec.common_size !== null ? sec.common_size + '%' : '—' }}
                    </td>
                  </tr>
                  <!-- Line item sub-rows -->
                  <tr v-for="li in sec.line_items" :key="li.label"
                    class="border-t border-mp-border/40 hover:bg-mp-card-hover/20 transition-colors">
                    <td class="px-6 py-2 text-white pl-14 text-xs">→ {{ li.label }}</td>
                    <td class="px-6 py-2 text-right text-white text-xs">{{ formatNum(li.amount) }}</td>
                    <td v-if="showCommonSize" class="px-4 py-2"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ── BALANCE SHEET ── -->
        <div v-if="activeTab === 'balance_sheet'">
          <div v-if="!sections.balance_sheet || !sections.balance_sheet.length"
            class="text-center py-16 text-white bg-mp-card rounded-xl border border-mp-border">
            No balance sheet data entered yet.
          </div>
          <div v-else class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border bg-mp-card-hover/60">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Description</th>
                  <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">
                    Amount ({{ statement.currency }})
                  </th>
                  <th v-if="showCommonSize"
                    class="text-right text-xs font-semibold text-white uppercase tracking-widest px-4 py-3 w-20">%</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="sec in sections.balance_sheet" :key="sec.section_key">
                  <tr :class="sec.is_computed
                    ? 'bg-mp-teal-subtle/20 border-t-2 border-mp-teal/40'
                    : 'bg-mp-card-hover/30 border-t border-mp-border'">
                    <td class="px-6 py-3">
                      <div class="flex items-center gap-2">
                        <span v-if="sec.is_computed"
                          class="text-xs bg-mp-teal-subtle text-white px-1.5 py-0.5 rounded font-semibold flex-shrink-0">AUTO</span>
                        <span :class="sec.is_computed ? 'text-white font-bold' : 'text-white font-semibold'">
                          {{ sec.display_name }}
                        </span>
                      </div>
                    </td>
                    <td class="px-6 py-3 text-right font-bold"
                      :class="sec.total < 0 ? 'text-mp-danger' : sec.is_computed ? 'text-white' : 'text-mp-success'">
                      {{ formatNum(sec.total) }}
                    </td>
                    <td v-if="showCommonSize" class="px-4 py-3 text-right text-white text-xs">
                      {{ sec.common_size !== null ? sec.common_size + '%' : '—' }}
                    </td>
                  </tr>
                  <tr v-for="li in sec.line_items" :key="li.label"
                    class="border-t border-mp-border/40 hover:bg-mp-card-hover/20 transition-colors">
                    <td class="px-6 py-2 text-white pl-14 text-xs">→ {{ li.label }}</td>
                    <td class="px-6 py-2 text-right text-white text-xs">{{ formatNum(li.amount) }}</td>
                    <td v-if="showCommonSize" class="px-4 py-2"></td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ── CASH FLOW ── -->
        <div v-if="activeTab === 'cashflow'">
          <div v-if="!sections.cashflow || !sections.cashflow.length"
            class="text-center py-16 text-white bg-mp-card rounded-xl border border-mp-border">
            No cash flow data entered yet.
          </div>
          <div v-else class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border bg-mp-card-hover/60">
                  <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">Description</th>
                  <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-6 py-3">
                    Amount ({{ statement.currency }})
                  </th>
                </tr>
              </thead>
              <tbody>
                <template v-for="sec in sections.cashflow" :key="sec.section_key">
                  <tr :class="sec.is_computed
                    ? 'bg-mp-teal-subtle/20 border-t-2 border-mp-teal/40'
                    : 'bg-mp-card-hover/30 border-t border-mp-border'">
                    <td class="px-6 py-3">
                      <div class="flex items-center gap-2">
                        <span v-if="sec.is_computed"
                          class="text-xs bg-mp-teal-subtle text-white px-1.5 py-0.5 rounded font-semibold flex-shrink-0">AUTO</span>
                        <span :class="sec.is_computed ? 'text-white font-bold' : 'text-white font-semibold'">
                          {{ sec.display_name }}
                        </span>
                      </div>
                    </td>
                    <td class="px-6 py-3 text-right font-bold"
                      :class="sec.total < 0 ? 'text-mp-danger' : sec.is_computed ? 'text-white' : 'text-mp-success'">
                      {{ formatNum(sec.total) }}
                    </td>
                  </tr>
                  <tr v-for="li in sec.line_items" :key="li.label"
                    class="border-t border-mp-border/40 hover:bg-mp-card-hover/20 transition-colors">
                    <td class="px-6 py-2 text-white pl-14 text-xs">→ {{ li.label }}</td>
                    <td class="px-6 py-2 text-right text-white text-xs">{{ formatNum(li.amount) }}</td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ── RATIOS & ANALYSIS ── -->
        <div v-if="activeTab === 'ratios'">
          <div v-if="!ratios || !Object.keys(ratios).length"
            class="text-center text-white py-12 bg-mp-card rounded-xl border border-mp-border">
            Ratios appear once Income Statement and Balance Sheet data are saved.
          </div>
          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="(groupRatios, groupName) in ratios" :key="groupName"
              class="bg-mp-card rounded-xl border border-mp-border overflow-hidden">
              <div class="px-5 py-3 border-b border-mp-border flex items-center gap-2">
                <span class="text-lg">{{ groupIcon(groupName) }}</span>
                <h3 class="font-semibold text-white text-sm capitalize">{{ groupName }} Ratios</h3>
              </div>
              <div class="divide-y divide-gray-800">
                <div v-for="ratio in groupRatios" :key="ratio.key"
                  class="flex items-center justify-between px-5 py-3">
                  <div>
                    <p class="text-sm text-white">{{ ratio.label }}</p>
                    <p class="text-xs text-white mt-0.5">{{ ratioHint(ratio.key) }}</p>
                  </div>
                  <div class="text-right">
                    <span v-if="ratio.value !== null"
                      :class="ratioColor(ratio.key, ratio.value)"
                      class="text-base font-bold">
                      
                      {{ formatRatio(ratio.key, ratio.value) }}
                    </span>
                    <span v-else class="text-white text-xs italic">Need data</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- DSO/DIO/DPO auto-detect tip -->
          <div class="mt-4 bg-mp-teal-subtle/20 border border-mp-teal/40 rounded-lg px-4 py-3 text-xs text-white leading-relaxed">
            💡 <strong>DSO, DIO & DPO</strong> are auto-detected from your balance sheet line item labels.
            Name items containing: <em>"Receivable"</em> for DSO · <em>"Inventor"</em> for DIO · <em>"Payable"</em> for DPO.
            Example: "Trade Receivables", "Inventory", "Accounts Payable".
          </div>

          <!-- Analyst notes -->
          <div v-if="statement.notes" class="mt-6 bg-mp-card rounded-xl border border-mp-border p-5">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-2">Analyst Notes</p>
            <p class="text-white text-sm">{{ statement.notes }}</p>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:   Object,
  statement: Object,
  sections:  Object,
  ratios:    Object,
  bsBalance: Object,
})

const activeTab      = ref('income')
const showCommonSize = ref(true)

const viewTabs = [
  { key: 'income',        label: 'Income Statement', icon: '📊' },
  { key: 'balance_sheet', label: 'Balance Sheet',    icon: '⚖️' },
  { key: 'cashflow',      label: 'Cash Flow',        icon: '💧' },
  { key: 'ratios',        label: 'Ratios & Analysis',icon: '📐' },
]

function groupIcon(group) {
  const map = { profitability: '💰', liquidity: '💧', leverage: '⚖️', activity: '⚙️' }
  return map[group] ?? '📐'
}

function ratioHint(key) {
  const hints = {
    gross_margin_pct:     'Gross Profit ÷ Revenue',
    ebitda_margin_pct:    'EBITDA ÷ Revenue',
    net_margin_pct:       'Net Profit ÷ Revenue',
    roa:                  'Net Profit ÷ Total Assets',
    roe:                  'Net Profit ÷ Total Equity',
    current_ratio:        'Current Assets ÷ Current Liabilities',
    quick_ratio:          'Liquid Assets ÷ Current Liabilities',
    debt_to_equity:       'Total Liabilities ÷ Total Equity',
    debt_to_assets:       'Total Liabilities ÷ Total Assets',
    interest_coverage:    'EBIT ÷ Interest Expense',
    asset_turnover:       'Revenue ÷ Total Assets',
    receivables_turnover: 'Revenue ÷ Accounts Receivable',
    inventory_turnover:   'COGS ÷ Inventory',
    dso:                  '(Trade Receivables ÷ Revenue) × 365',
    dio:                  '(Inventory ÷ COGS) × 365',
    dpo:                  '(Accounts Payable ÷ COGS) × 365',
  }
  return hints[key] ?? ''
}

function formatRatio(key, value) {
  const pctKeys = ['gross_margin_pct','ebitda_margin_pct','net_margin_pct','roa','roe','debt_to_assets']
  const dayKeys = ['dso','dio','dpo']
  if (pctKeys.includes(key)) return value.toFixed(2) + '%'
  if (dayKeys.includes(key)) return value.toFixed(1) + ' days'
  return value.toFixed(2) + 'x'
}

function ratioColor(key, value) {
  const pctKeys = ['gross_margin_pct','ebitda_margin_pct','net_margin_pct','roa','roe']
  if (pctKeys.includes(key))       return value >= 10  ? 'text-mp-success' : value >= 0   ? 'text-mp-warning' : 'text-mp-danger'
  if (key === 'current_ratio')     return value >= 1.5 ? 'text-mp-success' : value >= 1   ? 'text-mp-warning' : 'text-mp-danger'
  if (key === 'quick_ratio')       return value >= 1   ? 'text-mp-success' : value >= 0.7 ? 'text-mp-warning' : 'text-mp-danger'
  if (key === 'debt_to_equity')    return value <= 1   ? 'text-mp-success' : value <= 2   ? 'text-mp-warning' : 'text-mp-danger'
  if (key === 'interest_coverage') return value >= 3   ? 'text-mp-success' : value >= 1.5 ? 'text-mp-warning' : 'text-mp-danger'
  if (key === 'dso')               return value <= 45  ? 'text-mp-success' : value <= 90  ? 'text-mp-warning' : 'text-mp-danger'
  if (key === 'dio')               return value <= 60  ? 'text-mp-success' : value <= 120 ? 'text-mp-warning' : 'text-mp-danger'
  if (key === 'dpo')               return value >= 30  ? 'text-mp-success' : 'text-mp-warning'
  return 'text-white'
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatNum(val) {
  if (val === null || val === undefined) return '—'
  return parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}
</script>