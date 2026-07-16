<template>
  <Head :title="`Report — ${study.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ── HEADER ── -->
      <div class="bg-mp-card border-b border-mp-border print:hidden">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
          <Link :href="`/portfolio-companies/${company.id}/financial-studies`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Studies
          </Link>
          <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
              <h1 class="text-2xl font-bold text-white">📄 Study Report</h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }} · {{ study.name }}</p>
            </div>
            <div class="flex items-center gap-3">
              <!-- Section jump nav -->
              <div class="hidden md:flex items-center gap-1 bg-mp-card-hover border border-mp-border rounded-lg px-2 py-1 flex-wrap">
                <button v-for="s in availableSections" :key="s.key"
                  type="button" @click="scrollTo(s.key)"
                  class="px-2 py-1 rounded text-xs text-white hover:text-white hover:bg-mp-page transition-colors whitespace-nowrap">
                  {{ s.icon }} {{ s.label }}
                </button>
              </div>
              <!-- Download PDF -->
              <button type="button" @click="downloadPdf"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Download PDF
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ── REPORT BODY ── -->
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8" id="report-body">

        <!-- ── COVER BLOCK ── -->
        <div class="bg-gradient-to-br from-mp-card to-mp-card-hover border border-mp-border rounded-2xl p-8 print:border-mp-border">
          <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-2">Financial Feasibility Study</p>
              <h2 class="text-3xl font-bold text-white mb-1">{{ study.name }}</h2>
              <p class="text-white text-base">{{ company.name }}</p>
            </div>
            <div class="text-right text-sm text-white space-y-1">
              <p><span class="text-white">Currency:</span> {{ study.study_currency }}</p>
              <p><span class="text-white">Duration:</span> {{ study.duration_years }} years</p>
              <p><span class="text-white">Period:</span> {{ fmtDate(study.study_start_date) }} – {{ fmtDate(study.study_end_date) }}</p>
              <p><span class="text-white">Type:</span> {{ study.business_type || '—' }}</p>
              <p><span class="text-white">Sector:</span> {{ study.business_sector || '—' }}</p>
            </div>
          </div>

          <!-- Step completion overview -->
          <div class="mt-6 pt-6 border-t border-mp-border grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div v-for="s in allSections" :key="s.key"
              :class="['rounded-xl p-3 text-center border transition-colors', hasData(s.key) ? 'bg-mp-success/30 border-mp-success/50' : 'bg-mp-card-hover/50 border-mp-border']">
              <p class="text-lg mb-1">{{ s.icon }}</p>
              <p class="text-xs font-medium" :class="hasData(s.key) ? 'text-mp-success' : 'text-white'">{{ s.label }}</p>
              <p class="text-xs mt-1" :class="hasData(s.key) ? 'text-mp-success' : 'text-white'">
                {{ hasWriteup(s.key) ? '✓ Written' : hasData(s.key) ? '◎ Data only' : 'Pending' }}
              </p>
            </div>
          </div>
        </div>

        <!-- ── SECTIONS ── -->
        <div v-for="section in availableSections" :key="section.key"
          :id="'section-' + section.key"
          class="bg-mp-card border border-mp-border rounded-2xl overflow-hidden print:border-mp-border print:break-inside-avoid">

          <!-- Section header -->
          <div class="px-8 py-5 border-b border-mp-border flex items-center gap-4"
            :style="{ borderLeftWidth: '4px', borderLeftColor: section.color }">
            <span class="text-2xl">{{ section.icon }}</span>
            <div>
              <p class="text-xs font-semibold uppercase tracking-widest mb-0.5"
                :style="{ color: section.color }">{{ section.stepNumber }}</p>
              <h3 class="text-white font-bold text-lg">{{ section.label }}</h3>
            </div>
            <div class="ml-auto text-xs text-white" v-if="writeups[section.key]?.updated_at">
              Last updated: {{ writeups[section.key].updated_at.slice(0,10) }}
            </div>
          </div>

          <!-- Data summary table -->
          <div v-if="section.tableData && section.tableData.rows.length > 0"
            class="px-8 py-5 border-b border-mp-border/60 bg-mp-card-hover/20">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">📊 Data Summary</p>
            <div class="overflow-x-auto rounded-lg border border-mp-border">
              <table class="w-full text-xs">
                <thead class="bg-mp-card-hover border-b border-mp-border">
                  <tr>
                    <th v-for="col in section.tableData.columns" :key="col.key"
                      :class="['py-2 px-4 font-semibold text-white uppercase tracking-wide whitespace-nowrap',
                        col.align === 'right' ? 'text-right' : 'text-left']">
                      {{ col.label }}
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                  <tr v-for="(row, ri) in section.tableData.rows" :key="ri" class="hover:bg-mp-card-hover/40">
                    <td v-for="col in section.tableData.columns" :key="col.key"
                      :class="['py-2 px-4 whitespace-nowrap',
                        col.align === 'right' ? 'text-right font-mono' : 'text-left',
                        col.highlight ? 'font-semibold text-white' : 'text-white',
                        col.isNeg && Number(String(row[col.key]).replace(/,/g,'')) < 0 ? 'text-mp-danger' : '']">
                      {{ row[col.key] }}
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="section.tableData.totals" class="border-t-2 border-mp-border bg-mp-card-hover/60">
                  <tr>
                    <td v-for="col in section.tableData.columns" :key="col.key"
                      :class="['py-2 px-4 font-bold whitespace-nowrap',
                        col.align === 'right' ? 'text-right font-mono' : 'text-left']"
                      :style="col.totalColor ? { color: col.totalColor } : { color: '#64748b' }">
                      {{ section.tableData.totals[col.key] ?? '' }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <!-- Write-up text -->
          <div class="px-8 py-6">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-3">✍️ Analysis</p>
            <div v-if="writeups[section.key]?.text"
              :dir="writeups[section.key]?.lang === 'ar' ? 'rtl' : 'ltr'"
              :class="['text-white text-sm leading-relaxed whitespace-pre-wrap',
                writeups[section.key]?.lang === 'ar' ? 'text-right' : '']">
              {{ writeups[section.key].text }}
            </div>
            <div v-else class="text-white text-sm italic">
              No write-up added yet for this section.
              <Link :href="sectionEditLink(section.key)"
                class="text-white hover:text-white ml-1 not-italic">
                Go to {{ section.label }} →
              </Link>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="availableSections.length === 0"
          class="text-center py-16 text-white">
          <p class="text-4xl mb-4">📝</p>
          <p class="text-lg font-medium text-white mb-2">No data yet</p>
          <p class="text-sm">Complete each study step to populate this report.</p>
        </div>

        <!-- Footer -->
        <div class="text-center text-xs text-white pb-8">
          Generated by CFOs Tools ·
          {{ new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}
        </div>

      </div><!-- /report-body -->
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// ── Props ──────────────────────────────────────────────────────────────────
const props = defineProps({
  company:         { type: Object, required: true },
  study:           { type: Object, required: true },
  writeups:        { type: Object, default: () => ({}) },
  products:        { type: Array,  default: () => [] },
  projections:     { type: Object, default: () => ({}) },   // { products: [...] }
  cogsData:        { type: Array,  default: () => [] },
  manpowerData:    { type: Array,  default: () => [] },
  expensesData:    { type: Array,  default: () => [] },
  fixedAssetsData: { type: Array,  default: () => [] },
  openingBalance:  { type: Object, default: null },
})

// ── Section definitions ────────────────────────────────────────────────────
const allSections = [
  { key: 'setup',           label: 'Study Setup',       icon: '🏗️',  stepNumber: 'Step 1', color: '#64748b', step: 'edit' },
  { key: 'sales',           label: 'Sales Projection',  icon: '📈',  stepNumber: 'Step 2', color: '#00b4c8', step: 'sales' },
  { key: 'cogs',            label: 'Cost of Goods',     icon: '🏭',  stepNumber: 'Step 3', color: '#f59e0b', step: 'cogs' },
  { key: 'manpower',        label: 'Manpower Plan',     icon: '👷',  stepNumber: 'Step 4', color: '#7c3aed', step: 'manpower' },
  { key: 'expenses',        label: 'Expenses Plan',     icon: '💸',  stepNumber: 'Step 5', color: '#ea580c', step: 'expenses' },
  { key: 'fixed_assets',    label: 'Fixed Assets',      icon: '🏢',  stepNumber: 'Step 6', color: '#0891b2', step: 'fixed-assets' },
  { key: 'opening_balance', label: 'Opening Balance',   icon: '⚖️',  stepNumber: 'Step 7', color: '#00b4c8', step: 'opening-balance' },
  { key: 'results',         label: 'Financial Results', icon: '📊',  stepNumber: 'Step 8', color: '#ef4444', step: 'results' },
]

// ── Data presence checks ────────────────────────────────────────────────────
function hasWriteup(key) {
  return !!props.writeups[key]?.text?.trim()
}

function hasData(key) {
  if (key === 'sales')           return (props.projections?.products?.length ?? 0) > 0
  if (key === 'cogs')            return props.cogsData.length > 0
  if (key === 'manpower')        return props.manpowerData.length > 0
  if (key === 'expenses')        return props.expensesData.length > 0
  if (key === 'fixed_assets')    return props.fixedAssetsData.length > 0
  if (key === 'opening_balance') return !!props.openingBalance?.sections
  if (key === 'setup')           return !!props.study?.name
  if (key === 'results')         return hasWriteup('results')
  return false
}

// Show a section if it has data OR a write-up
const availableSections = computed(() =>
  allSections
    .filter(s => hasData(s.key) || hasWriteup(s.key))
    .map(s => ({ ...s, tableData: buildTableData(s.key) }))
)

// ── Link back to each step ─────────────────────────────────────────────────
function sectionEditLink(key) {
  const s = allSections.find(x => x.key === key)
  if (!s) return '#'
  if (s.step === 'edit') return `/portfolio-companies/${props.company.id}/financial-studies/${props.study.id}/edit`
  return `/portfolio-companies/${props.company.id}/financial-studies/${props.study.id}/${s.step}`
}

// ── Build data summary tables per section ──────────────────────────────────
function buildTableData(key) {

  // ── STEP 1: Setup ─────────────────────────────────────────────────────────
  if (key === 'setup') {
    const rows = [
      { item: 'Business Type',    value: props.study.business_type   || '—' },
      { item: 'Business Sector',  value: props.study.business_sector || '—' },
      { item: 'Study Duration',   value: `${props.study.duration_years} years` },
      { item: 'Study Currency',   value: props.study.study_currency  || '—' },
      { item: 'Start Date',       value: fmtDate(props.study.study_start_date) },
      { item: 'End Date',         value: fmtDate(props.study.study_end_date) },
    ].filter(r => r.value && r.value !== '—')

    if (props.products.length > 0) {
      rows.push({ item: 'Products / Services', value: props.products.map(p => p.name).join(', ') })
    }

    return {
      columns: [
        { key: 'item',  label: 'Parameter', align: 'left' },
        { key: 'value', label: 'Value',     align: 'left', highlight: true },
      ],
      rows,
      totals: null,
    }
  }

  // ── STEP 2: Sales Projection ───────────────────────────────────────────────
  if (key === 'sales') {
    const salesProducts = props.projections?.products ?? []
    if (!salesProducts.length) return null

    const durationYears = props.study.duration_years ?? 1
    const startYear     = props.study.study_start_date
      ? new Date(props.study.study_start_date).getFullYear()
      : new Date().getFullYear()

    // Build columns: Product | Nature | Y1 | Y2 | ... | Yn | Local/Export
    const yearCols = []
    for (let y = 1; y <= Math.min(durationYears, 5); y++) {
      yearCols.push({ key: `y${y}`, label: `Y${y} (${startYear + y - 1})`, align: 'right', highlight: y === 1 })
    }

    const rows = salesProducts.map(prod => {
      const row = {
        product: prod.name || '—',
        nature:  prod.nature ? (prod.nature.charAt(0).toUpperCase() + prod.nature.slice(1)) : '—',
        split:   `${prod.market_split?.local_pct ?? 0}% / ${prod.market_split?.export_pct ?? 0}%`,
      }

      // Y1
      const y1Rev = (prod.year1_months ?? []).reduce((s, m) => s + (m.price || 0) * (m.volume || 0), 0)
      row.y1 = fmtNumber(y1Rev)

      // Y2
      if (durationYears >= 2) {
        const y2Rev = (prod.year2_months ?? []).reduce((s, m) => s + (m.price || 0) * (m.volume || 0), 0)
        row.y2 = fmtNumber(y2Rev)
      }

      // Y3+
      ;(prod.annual_years ?? []).forEach((yr, i) => {
        const yNum = i + 3
        if (yNum <= Math.min(durationYears, 5)) {
          row[`y${yNum}`] = fmtNumber((yr.price || 0) * (yr.volume || 0))
        }
      })

      return row
    })

    // Totals row
    const totals = { product: 'TOTAL', nature: '', split: '' }
    for (let y = 1; y <= Math.min(durationYears, 5); y++) {
      const sum = rows.reduce((s, r) => {
        const val = Number(String(r[`y${y}`] ?? '0').replace(/,/g, '')) || 0
        return s + val
      }, 0)
      totals[`y${y}`] = fmtNumber(sum)
    }

    const columns = [
      { key: 'product', label: 'Product',       align: 'left' },
      { key: 'nature',  label: 'Type',          align: 'left' },
      ...yearCols,
      { key: 'split',   label: 'Local/Export',  align: 'right' },
    ]

    return { columns, rows, totals }
  }

  // ── STEP 3: COGS ──────────────────────────────────────────────────────────
  if (key === 'cogs') {
    if (!props.cogsData.length) return null

    const rows = props.cogsData.map(c => {
      let method = '—'
      let costSummary = '—'

      if (c.nature === 'manufacturing') {
        const rms = c.raw_materials ?? []
        if (c.rm_method === 'bom') {
          const totalCostPU = rms.reduce((s, rm) => s + (rm.cost_per_unit || 0) * (rm.qty_per_unit || 0), 0)
          method      = 'BOM'
          costSummary = `${fmtNumber(totalCostPU)} / unit · ${rms.length} material(s)`
        } else {
          const totalPct = rms.reduce((s, rm) => s + (rm.pct_selling || 0), 0)
          method      = '% of Revenue'
          costSummary = `${totalPct.toFixed(1)}% of revenue`
        }
        const overheads = (c.overheads ?? []).length
        if (overheads > 0) costSummary += ` + ${overheads} overhead(s)`
      }
      else if (c.nature === 'trading') {
        method      = 'Purchase Cost'
        costSummary = `${fmtNumber(c.unit_purchase_cost)} / unit · ${c.inventory_days ?? 0}d inventory`
      }
      else if (c.nature === 'service') {
        if (c.service_method === 'pct_revenue') {
          method      = '% of Revenue'
          costSummary = `${c.service_pct ?? 0}% of revenue`
        } else {
          method      = 'Fixed Monthly'
          costSummary = `${fmtNumber(c.service_amount)} / month`
        }
      }

      return {
        product: c.product_name || '—',
        nature:  c.nature ? (c.nature.charAt(0).toUpperCase() + c.nature.slice(1)) : '—',
        method,
        cost:    costSummary,
      }
    })

    return {
      columns: [
        { key: 'product', label: 'Product',      align: 'left' },
        { key: 'nature',  label: 'Type',         align: 'left' },
        { key: 'method',  label: 'Cost Method',  align: 'left' },
        { key: 'cost',    label: 'Cost Summary', align: 'left', highlight: true },
      ],
      rows,
      totals: null,
    }
  }

  // ── STEP 4: Manpower ──────────────────────────────────────────────────────
  if (key === 'manpower') {
    if (!props.manpowerData.length) return null

    const depts = [
      { key: 'direct_labor',    label: 'Direct Labor' },
      { key: 'indirect_labor',  label: 'Indirect Labor' },
      { key: 'admin_management',label: 'Admin & Management' },
      { key: 'sales_marketing', label: 'Sales & Marketing' },
    ]

    const rows = depts.map(d => {
      const dRows = props.manpowerData.filter(r => r.dept === d.key)
      if (!dRows.length) return null
      const monthlyCost = dRows.reduce((sum, r) => {
        const gross = (r.net_salary || 0) * (1 + (r.salary_taxes_pct || 0) / 100 + (r.social_insurance_pct || 0) / 100)
        const avgCount = (r.y1_count ?? []).reduce((a, b) => a + b, 0) / 12
        return sum + gross * avgCount
      }, 0)
      const annualCost = monthlyCost * 12
      return {
        dept:   d.label,
        count:  dRows.length,
        monthly: fmtNumber(monthlyCost),
        annual:  fmtNumber(annualCost),
      }
    }).filter(Boolean)

    const totalMonthly = props.manpowerData.reduce((sum, r) => {
      const gross = (r.net_salary || 0) * (1 + (r.salary_taxes_pct || 0) / 100 + (r.social_insurance_pct || 0) / 100)
      const avg   = (r.y1_count ?? []).reduce((a, b) => a + b, 0) / 12
      return sum + gross * avg
    }, 0)

    return {
      columns: [
        { key: 'dept',    label: 'Department',         align: 'left' },
        { key: 'count',   label: 'Positions',          align: 'right' },
        { key: 'monthly', label: 'Monthly Cost Y1',    align: 'right', highlight: true, totalColor: '#10b981' },
        { key: 'annual',  label: 'Annual Cost Y1',     align: 'right', totalColor: '#10b981' },
      ],
      rows,
      totals: {
        dept:    'TOTAL',
        count:   props.manpowerData.length,
        monthly: fmtNumber(totalMonthly),
        annual:  fmtNumber(totalMonthly * 12),
      },
    }
  }

  // ── STEP 5: Expenses ──────────────────────────────────────────────────────
  if (key === 'expenses') {
    if (!props.expensesData.length) return null

    const cats = [
      { key: 'sales',         label: 'Sales Expenses' },
      { key: 'marketing',     label: 'Marketing' },
      { key: 'general_admin', label: 'General & Admin' },
      { key: 'finance',       label: 'Finance Expenses' },
    ]

    const rows = cats.map(c => {
      const cRows = props.expensesData.filter(r => r.category === c.key)
      if (!cRows.length) return null

      const fixedAnnual = cRows.reduce((sum, r) => {
        if (r.expense_type === 'pct_revenue') return sum
        if (r.expense_type === 'one_time')    return sum + (r.amount || 0)
        return sum + (r.amount || 0) * 12
      }, 0)
      const pctItems = cRows.filter(r => r.expense_type === 'pct_revenue').length

      return {
        category: c.label,
        count:    cRows.length,
        annual:   fmtNumber(fixedAnnual),
        pct:      pctItems > 0 ? `+${pctItems} % items` : '—',
      }
    }).filter(Boolean)

    const totalAnnual = props.expensesData.reduce((sum, r) => {
      if (r.expense_type === 'pct_revenue') return sum
      if (r.expense_type === 'one_time')    return sum + (r.amount || 0)
      return sum + (r.amount || 0) * 12
    }, 0)

    return {
      columns: [
        { key: 'category', label: 'Category',            align: 'left' },
        { key: 'count',    label: 'Items',               align: 'right' },
        { key: 'annual',   label: 'Annual Fixed (Y1)',   align: 'right', highlight: true, totalColor: '#fb923c' },
        { key: 'pct',      label: '% Revenue Items',     align: 'right' },
      ],
      rows,
      totals: {
        category: 'TOTAL',
        count:    props.expensesData.length,
        annual:   fmtNumber(totalAnnual),
        pct:      `${props.expensesData.filter(r => r.expense_type === 'pct_revenue').length} items`,
      },
    }
  }

  // ── STEP 6: Fixed Assets ──────────────────────────────────────────────────
  if (key === 'fixed_assets') {
    if (!props.fixedAssetsData.length) return null

    const rows = props.fixedAssetsData.map(a => {
      const depLabel = a.depreciation_duration > 0 ? `${a.depreciation_duration} yrs` : 'None'
      const funding  = a.equity_pct > 0
        ? `${a.equity_pct}% Equity / ${a.debt_pct ?? 0}% Debt`
        : 'Full Debt'

      return {
        name:    a.name || '—',
        count:   a.count || 0,
        total:   fmtNumber(a.total || 0),
        dep:     depLabel,
        funding,
        term:    a.payment_term || 'cash',
      }
    })

    const totalCapex  = props.fixedAssetsData.reduce((s, a) => s + (a.total || 0), 0)
    const totalEquity = props.fixedAssetsData.reduce((s, a) => s + (a.total || 0) * ((a.equity_pct || 0) / 100), 0)
    const totalDebt   = totalCapex - totalEquity

    return {
      columns: [
        { key: 'name',    label: 'Asset',          align: 'left' },
        { key: 'count',   label: 'Units',          align: 'right' },
        { key: 'total',   label: `Total Cost (${props.study.study_currency})`, align: 'right', highlight: true, totalColor: '#38bdf8' },
        { key: 'dep',     label: 'Depreciation',   align: 'right' },
        { key: 'funding', label: 'Funding',        align: 'left' },
        { key: 'term',    label: 'Payment',        align: 'left' },
      ],
      rows,
      totals: {
        name:    'TOTAL',
        count:   '',
        total:   fmtNumber(totalCapex),
        dep:     '',
        funding: `${fmtNumber(totalEquity)} equity / ${fmtNumber(totalDebt)} debt`,
        term:    '',
      },
    }
  }

  // ── STEP 7: Opening Balance ────────────────────────────────────────────────
  if (key === 'opening_balance') {
    const ob = props.openingBalance
    if (!ob?.sections) return null

    const sectionDefs = [
      { key: 'non_current_assets',      label: 'Non-Current Assets',      sign: +1 },
      { key: 'current_assets',          label: 'Current Assets',          sign: +1 },
      { key: 'non_current_liabilities', label: 'Non-Current Liabilities', sign: -1 },
      { key: 'current_liabilities',     label: 'Current Liabilities',     sign: -1 },
      { key: 'equity',                  label: 'Equity',                  sign: -1 },
    ]

    const rows = []
    let totalAssets = 0
    let totalLiabEq = 0

    for (const sec of sectionDefs) {
      const items = ob.sections[sec.key] ?? []
      if (!items.length) continue

      const total = items.reduce((s, r) => s + (Number(r.amount) || 0), 0)
      if (sec.sign === 1) totalAssets  += total
      else                totalLiabEq  += total

      rows.push({
        section: sec.label,
        items:   items.length,
        total:   fmtNumber(total),
        note:    sec.sign === 1 ? 'Asset' : 'L + E',
      })
    }

    const asOfDate = ob.as_of_date
      ? new Date(ob.as_of_date).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
      : '—'

    return {
      columns: [
        { key: 'section', label: `Section (as of ${asOfDate})`, align: 'left' },
        { key: 'items',   label: 'Line Items', align: 'right' },
        { key: 'total',   label: `Total (${props.study.study_currency})`, align: 'right', highlight: true, totalColor: '#10b981' },
        { key: 'note',    label: 'Type', align: 'left' },
      ],
      rows,
      totals: {
        section: 'Balance Check',
        items:   '',
        total:   Math.abs(totalAssets - totalLiabEq) < 1 ? '✅ Balanced' : `⚠️ Diff: ${fmtNumber(Math.abs(totalAssets - totalLiabEq))}`,
        note:    `Assets: ${fmtNumber(totalAssets)}`,
      },
    }
  }

  // ── STEP 8: Results (write-up only — no table, numbers come from the engine) ─
  if (key === 'results') return null

  return null
}

// ── Helpers ────────────────────────────────────────────────────────────────
function fmtNumber(n) {
  if (n === null || n === undefined) return '—'
  return Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
}

function scrollTo(key) {
  document.getElementById('section-' + key)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function downloadPdf() {
  window.print()
}
</script>

<style>
@media print {
  body { background: white !important; color: black !important; }
  .print\:hidden { display: none !important; }
  #report-body { max-width: 100% !important; padding: 0 !important; }
  .bg-mp-card, .bg-mp-page, .bg-mp-card-hover { background: white !important; }
  .text-white, .text-white, .text-white { color: #111 !important; }
  .text-white, .text-white { color: #555 !important; }
  .border-mp-border, .border-mp-border { border-color: #ddd !important; }
  .rounded-2xl, .rounded-xl, .rounded-lg { border-radius: 8px !important; }
  tr { page-break-inside: avoid; }
}
</style>