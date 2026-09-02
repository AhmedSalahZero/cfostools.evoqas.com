<template>
  <Head :title="dashboard.name" />
  <AuthenticatedLayout>
    <div class="cd-report">

      <div class="topbar">
        <div class="brand">◆ {{ dashboard.name }}</div>
        <div style="display:flex; align-items:center; gap:18px;">
          <code v-if="isPublic" style="font-family:'IBM Plex Mono',monospace; font-size:11px; color:#9DB6DE; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.15); border-radius:6px; padding:5px 10px;">
            {{ shareUrl }}
          </code>
          <button v-if="isPublic" @click="copyLink"
            style="font-family:Inter,sans-serif; font-size:12px; font-weight:600; padding:6px 12px; border-radius:6px; border:none; cursor:pointer; background:#2563EB; color:#fff;">
            {{ copied ? 'Copied!' : 'Copy' }}
          </button>
          <Link :href="route('comparison-dashboard.index', company.id)" style="color:#93A9CB; font-size:13px; text-decoration:none;">← All Dashboards</Link>
          <button @click="toggleShare"
            style="font-family:'IBM Plex Mono',monospace; font-size:12px; font-weight:600; padding:6px 14px; border-radius:20px; border:1px solid rgba(255,255,255,.25); cursor:pointer;"
            :style="isPublic ? 'background:rgba(74,222,128,.15); color:#4ADE80; border-color:rgba(74,222,128,.4);' : 'background:transparent; color:#9DB6DE;'">
            {{ isPublic ? '● Shared' : 'Share Link' }}
          </button>
        </div>
      </div>

      <div class="hero">
        <div class="hero-inner">
          <div class="eyebrow">Expense Comparison Dashboard · Live Data</div>
          <h1>{{ dashboard.name }}</h1>
          <p class="sub">{{ periodsLabel }} — numbers always reflect current expense data, reload anytime to refresh</p>
          <div class="kpi-row" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
            <div class="kpi-card" v-for="(hp, i) in heroPairs" :key="i">
              <div class="label">Total Expense · {{ hp.label_a }} → {{ hp.label_b }}</div>
              <div class="value tabular" :class="hp.raw_pct >= 0 ? 'red' : 'green'">
                {{ hp.raw_pct >= 0 ? '+' : '' }}{{ hp.raw_pct }}%
              </div>
              <div class="detail">{{ fmt(hp.expense_a) }} → {{ fmt(hp.expense_b) }}</div>
              <div v-if="hp.ratio_change !== null" style="margin-top:8px; font-size:12px; color:#B9CBEA;">
                Expense/Revenue: {{ hp.ratio_a }}% → {{ hp.ratio_b }}%
                <span :class="hp.ratio_change >= 0 ? 'red' : 'green'" style="font-family:var(--mono); font-weight:600;">
                  ({{ hp.ratio_change >= 0 ? '+' : '' }}{{ hp.ratio_change }}pt)
                </span>
              </div>
              <div v-if="hp.was_aligned" style="margin-top:10px; font-size:11px; color:#93C5FD; background:rgba(37,99,235,.15); border:1px solid rgba(37,99,235,.35); border-radius:6px; padding:6px 8px;">
                ℹ Periods were different lengths, so {{ hp.label_a }} uses the same calendar months as {{ hp.label_b }} for a fair comparison.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Executive Summary -->
      <div class="section" style="padding-bottom:0;">
        <div class="card">
          <h3 style="font-size:16px; margin-bottom:4px;">🧭 Executive Summary</h3>
          <NoteBox section-key="hero_summary" :notes="notesData" @save="saveNote" />
        </div>
      </div>

      <!-- Key Takeaways -->
      <div class="section" style="padding-bottom:0;" v-if="takeaways.length">
        <div class="card">
          <h3 style="font-size:16px; margin-bottom:18px;">📌 Key Takeaways — At a Glance</h3>
          <div class="takeaway-grid">
            <div class="takeaway-item" v-for="(t, i) in takeaways" :key="t.key" :class="t.tone" style="position:relative;">
              <template v-if="editingTakeaway === t.key">
                <textarea v-model="takeawayDraft" rows="3"
                  style="width:100%; border:1px solid var(--border); border-radius:6px; padding:6px 8px; font-size:12px; font-family:Inter,sans-serif;"></textarea>
                <div style="display:flex; gap:6px; margin-top:6px;">
                  <button @click="saveTakeawayEdit(t.key)" style="font-size:11px; color:#fff; background:#2563EB; border:none; padding:4px 10px; border-radius:5px; cursor:pointer;">Save</button>
                  <button @click="resetTakeawayEdit(t.key)" style="font-size:11px; color:var(--muted); background:none; border:none; cursor:pointer;">Reset</button>
                  <button @click="editingTakeaway = null" style="font-size:11px; color:var(--muted); background:none; border:none; cursor:pointer;">Cancel</button>
                </div>
              </template>
              <template v-else>
                <button @click="startEditTakeaway(t.key)"
                  style="position:absolute; top:6px; right:8px; font-size:11px; color:#2563EB; background:none; border:none; cursor:pointer; opacity:.75;">✎</button>
                <template v-if="notesData[t.key] && notesData[t.key].is_auto === false">
                  <span class="tk-text" style="white-space:pre-wrap;">{{ notesData[t.key].note }}</span>
                </template>
                <template v-else>
                  <span class="tk-stat">{{ t.stat }}</span>
                  <span class="tk-text">{{ t.text }}</span>
                </template>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- 01 · Zoom Out -->
      <div class="section" id="zoom-out">
        <div class="section-head"><span class="section-tag">01 · ZOOM OUT</span></div>
        <h2>Overall Expense Performance</h2>
        <div class="grid-2">
          <div class="card">
            <h3>Total Expense Trend by Period</h3>
            <div class="chart-wrap tall"><canvas ref="zoomOutChart"></canvas></div>
          </div>
          <div class="card">
            <h3>Quick Overview</h3>
            <table>
              <thead>
                <tr>
                  <th>Metric</th>
                  <th class="num" v-for="(r,i) in zoomOut" :key="i">{{ r.label }} ({{ monthsLabel(r.days) }})</th>
                </tr>
              </thead>
              <tbody>
                <tr><td>Total Expense</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.total_expense) }}</td></tr>
                <tr><td>Daily Average</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.daily_avg) }}/day</td></tr>
                <tr><td>Categories</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ r.category_count.toLocaleString() }}</td></tr>
                <tr><td>Expense Items</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ r.item_count.toLocaleString() }}</td></tr>
                <tr><td>Avg / Category</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.avg_per_category) }}</td></tr>
                <tr><td>Avg / Item</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ fmt(r.avg_per_item) }}</td></tr>
                <tr><td>Revenue</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ r.revenue > 0 ? fmt(r.revenue) : '—' }}</td></tr>
                <tr><td>Expense / Revenue</td><td class="num val-cell" v-for="(r,i) in zoomOut" :key="i">{{ r.ratio_pct !== null ? r.ratio_pct + '%' : 'N/A' }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <NoteBox section-key="zoom_out" :notes="notesData" @save="saveNote" />
      </div>

      <!-- 02 · Zoom In -->
      <div class="section" v-for="pair in zoomIn" :key="pair.section_key">
        <div class="section-head"><span class="section-tag">02 · ZOOM IN</span></div>
        <h2>{{ pair.period_a.label }} → {{ pair.period_b.label }}</h2>

        <div class="grid-2">
          <div class="card">
            <h3>Biggest Category Movements</h3>
            <table>
              <thead>
                <tr>
                  <th>Category</th>
                  <th class="num">{{ pair.compare_period_a.label }}</th>
                  <th class="num">{{ pair.compare_period_b.label }}</th>
                  <th class="num">Change</th>
                  <th class="num">Change %</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(c,i) in pair.category_breakdown.slice(0,8)" :key="i">
                  <td>{{ c.label }}</td>
                  <td class="num val-cell">{{ fmt(c.value_a) }}</td>
                  <td class="num val-cell">{{ fmt(c.value_b) }}</td>
                  <td class="num"><span class="badge" :class="c.change>=0?'down':'up'">{{ c.change>=0?'▲':'▼' }} {{ fmt(Math.abs(c.change)) }}</span></td>
                  <td class="num"><span class="badge" :class="c.change>=0?'down':'up'">{{ c.change_pct !== null ? (c.change_pct>=0?'+':'') + c.change_pct + '%' : 'N/A' }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="card">
            <h3>Biggest Expense Item Movements</h3>
            <table>
              <thead>
                <tr>
                  <th>Expense Item</th>
                  <th class="num">{{ pair.compare_period_a.label }}</th>
                  <th class="num">{{ pair.compare_period_b.label }}</th>
                  <th class="num">Change</th>
                  <th class="num">Change %</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(s,i) in pair.item_breakdown.slice(0,8)" :key="i">
                  <td>{{ s.label }}</td>
                  <td class="num val-cell">{{ fmt(s.value_a) }}</td>
                  <td class="num val-cell">{{ fmt(s.value_b) }}</td>
                  <td class="num"><span class="badge" :class="s.change>=0?'down':'up'">{{ s.change>=0?'▲':'▼' }} {{ fmt(Math.abs(s.change)) }}</span></td>
                  <td class="num"><span class="badge" :class="s.change>=0?'down':'up'">{{ s.change_pct !== null ? (s.change_pct>=0?'+':'') + s.change_pct + '%' : 'N/A' }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <NoteBox :section-key="pair.section_key" :notes="notesData" @save="saveNote" />
      </div>

      <!-- 03 · Cost Changes (Vanishing expense items) -->
      <div class="section" v-for="pair in vanishing" :key="pair.section_key">
        <div class="section-head"><span class="section-tag">03 · COST CHANGES</span></div>
        <h2>{{ pair.period_a.label }} → {{ pair.period_b.label }}: Expenses That Didn't Repeat</h2>
        <p class="lede">Made up at least {{ pair.threshold_pct }}% of total expense in {{ pair.compare_period_a.label }} (≈ {{ fmtM(pair.threshold_value) }}), collapsing to under 5% of that value by {{ pair.compare_period_b.label }}. This is often a good sign (a contract ending, a one-off cost clearing) — but worth checking it isn't a data gap.</p>

        <div class="summary-banner">
          <div><div class="num">{{ pair.items_count }}</div><div class="lbl">Items affected</div></div>
          <div><div class="num">{{ fmtM(pair.items_total) }}</div><div class="lbl">Expense not repeated</div></div>
          <div><div class="num">{{ pair.items_cutoff }}</div><div class="lbl">Items = ~85% of that value</div></div>
        </div>
        <table class="vanish-table" v-if="pair.items.length">
          <thead><tr><th>#</th><th>Name</th><th>Trend</th><th>Value</th><th>Period</th></tr></thead>
          <tbody>
            <tr v-for="(p,i) in visibleRows(pair.items, pair.section_key + '_items', pair.items_cutoff)" :key="i">
              <td class="tabular">{{ i+1 }}</td>
              <td class="name-cell">{{ p.name }}</td>
              <td v-html="sparkline(p.value_a, p.value_b)"></td>
              <td class="val-cell"><span class="from">{{ fmtM(p.value_a) }}</span><span class="arrow">→</span><span class="to">{{ fmtM(p.value_b) }}</span></td>
              <td class="period-cell">{{ pair.compare_period_a.label }} → {{ pair.compare_period_b.label }}</td>
            </tr>
          </tbody>
        </table>
        <button v-if="pair.items.length > pair.items_cutoff" @click="toggleExpand(pair.section_key + '_items')"
          style="margin-top:10px; font-size:12px; color:#2563EB; background:none; border:none; cursor:pointer;">
          {{ expanded[pair.section_key + '_items'] ? 'Show Less' : `Show ${pair.items.length - pair.items_cutoff} More (${pair.items.length} total)` }}
        </button>

        <NoteBox :section-key="pair.section_key" :notes="notesData" @save="saveNote" />
      </div>

      <!-- 04 · Rank Movement -->
      <div class="section">
        <div class="section-head"><span class="section-tag">04 · RANK MOVEMENT</span></div>
        <h2>Top 100 Expense Items — Rank Movement</h2>
        <p class="lede">Each column is an independent leaderboard for that period. Below each rank, the small gray line shows that same item's real rank in earlier periods, even if outside that period's own Top 100.</p>
        <div class="leaderboard" ref="lbItems"></div>
        <NoteBox section-key="top_expense_items" :notes="notesData" @save="saveNote" />
      </div>

      <!-- 05 · Concentration -->
      <div class="section" v-if="concentration.length">
        <div class="section-head"><span class="section-tag">05 · CONCENTRATION</span></div>
        <h2>Expense Concentration by Category</h2>
        <p class="lede">For each category: the smallest set of top-spend items that make up ~85% of that category's value (the "core"), versus every other item making up the remaining ~15% (the "long tail").</p>
        <div v-for="pc in concentration" :key="pc.period.label" style="margin-bottom:28px;">
          <h3 style="font-family:var(--display); font-size:15px; color:var(--navy); margin-bottom:12px;">{{ pc.period.label }}</h3>
          <div class="card" style="padding:0;">
            <table>
              <thead>
                <tr>
                  <th>Category</th>
                  <th class="num">Total Items</th>
                  <th class="num">Core (~85% value)</th>
                  <th class="num">Long Tail (~15% value)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="cat in pc.categories" :key="cat.category">
                  <td>{{ cat.category }}</td>
                  <td class="num val-cell">{{ cat.total_items }}</td>
                  <td class="num val-cell">{{ cat.core_count }} <span style="color:var(--muted); font-size:11px;">({{ cat.core_pct }}%)</span></td>
                  <td class="num val-cell">{{ cat.tail_count }} <span style="color:var(--muted); font-size:11px;">({{ cat.tail_pct }}%)</span></td>
                </tr>
              </tbody>
              <tfoot>
                <tr style="border-top:2px solid var(--line); font-weight:700;">
                  <td>Total</td>
                  <td class="num val-cell">{{ ecTotal(pc.categories).total_items }}</td>
                  <td class="num val-cell">{{ ecTotal(pc.categories).core_count }} <span style="color:var(--muted); font-size:11px; font-weight:400;">({{ ecTotal(pc.categories).core_pct }}%)</span></td>
                  <td class="num val-cell">{{ ecTotal(pc.categories).tail_count }} <span style="color:var(--muted); font-size:11px; font-weight:400;">({{ ecTotal(pc.categories).tail_pct }}%)</span></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <NoteBox section-key="expense_concentration" :notes="notesData" @save="saveNote" />
      </div>

      <!-- 06 · Fixed vs Variable -->
      <div class="section" v-if="fixedVariable.length">
        <div class="section-head"><span class="section-tag">06 · COST STRUCTURE</span></div>
        <h2>Fixed vs Variable Costs</h2>
        <p class="lede">Each expense item is classified by how closely it correlates with revenue month-to-month (Pearson correlation ≥ 0.65 → Variable). Same methodology as the Breakeven page.</p>

        <div class="grid-3" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));">
          <div class="card" v-for="fv in fixedVariable" :key="fv.period.label">
            <h3>{{ fv.period.label }}</h3>
            <div v-if="!fv.has_revenue" style="font-size:12px; color:var(--amber-dark); background:var(--amber-light); border-radius:8px; padding:10px 12px; margin-bottom:12px;">
              No sales data for this period — everything defaults to Fixed until revenue data is uploaded.
            </div>
            <div class="wf-item">
              <div class="wf-label"><span class="name">Fixed</span><span class="val">{{ fmt(fv.fixed_total) }} ({{ fv.fixed_pct }}%)</span></div>
              <div class="wf-track"><div class="wf-fill" :style="`width:${fv.fixed_pct}%; background:var(--blue);`"></div></div>
            </div>
            <div class="wf-item">
              <div class="wf-label"><span class="name">Variable</span><span class="val">{{ fmt(fv.variable_total) }} ({{ fv.variable_pct }}%)</span></div>
              <div class="wf-track"><div class="wf-fill" :style="`width:${fv.variable_pct}%; background:var(--amber);`"></div></div>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:20px; padding:0;" v-if="fixedVariable.length && fixedVariable[fixedVariable.length-1].by_category.length">
          <table>
            <thead>
              <tr>
                <th>Category</th>
                <th class="num">Fixed</th>
                <th class="num">Variable</th>
                <th class="num">Fixed %</th>
                <th class="num">Variable %</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cat in fixedVariable[fixedVariable.length-1].by_category" :key="cat.category">
                <td>{{ cat.category }}</td>
                <td class="num val-cell">{{ fmt(cat.fixed) }}</td>
                <td class="num val-cell">{{ fmt(cat.variable) }}</td>
                <td class="num val-cell">{{ cat.fixed_pct }}%</td>
                <td class="num val-cell">{{ cat.variable_pct }}%</td>
              </tr>
            </tbody>
          </table>
          <p style="font-size:11px; color:var(--muted); padding:10px 12px;">Fixed/Variable by category for {{ fixedVariable[fixedVariable.length-1].period.label }} (most recent period shown).</p>
        </div>
        <NoteBox section-key="fixed_variable" :notes="notesData" @save="saveNote" />
      </div>

      <!-- 07 · Volatility & Outliers -->
      <div class="section" v-if="volatility.length">
        <div class="section-head"><span class="section-tag">07 · VOLATILITY</span></div>
        <h2>Volatility & Outliers</h2>
        <p class="lede">Expense items with at least one month that fell well outside their own typical range that period (IQR method — the same one used on the Reports page), sorted by most outlier months first. Needs at least 4 months of data per item to compute.</p>

        <div v-for="v in volatility" :key="v.period.label" style="margin-bottom:28px;">
          <h3 style="font-family:var(--display); font-size:15px; color:var(--navy); margin-bottom:12px;">{{ v.period.label }} — {{ v.items_with_outliers }} item(s) with outliers</h3>
          <div class="card" style="padding:0;" v-if="v.items.length">
            <table>
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Item</th>
                  <th class="num">Avg (Monthly)</th>
                  <th class="num">Min</th>
                  <th class="num">Max</th>
                  <th class="num">Outliers</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="it in v.items" :key="it.category + it.item">
                  <td style="font-size:12px; color:var(--muted);">{{ it.category }}</td>
                  <td>{{ it.item }}</td>
                  <td class="num val-cell">{{ fmt(it.avg) }}</td>
                  <td class="num val-cell" style="color:var(--green-dark);">{{ fmt(it.min) }}</td>
                  <td class="num val-cell" style="color:var(--red);">{{ fmt(it.max) }}</td>
                  <td class="num">
                    <span style="display:inline-block; white-space:nowrap; font-size:11px; background:rgba(217,119,6,.12); color:var(--amber-dark); border:1px solid rgba(217,119,6,.4); padding:2px 8px; border-radius:20px; font-weight:600;">
                      {{ it.outlier_count }} outlier{{ it.outlier_count > 1 ? 's' : '' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else style="font-size:12px; color:var(--muted);">No unusually volatile items in this period.</div>
        </div>
        <NoteBox section-key="volatility" :notes="notesData" @save="saveNote" />
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, defineComponent, h } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import axios from 'axios'
import '@/styles/comparison-dashboard.css'

const props = defineProps({ company: Object, dashboard: Object, zoomOut: Array, zoomIn: Array, vanishing: Array, top100: Object, heroPairs: Array, takeaways: Array, concentration: Array, fixedVariable: Array, volatility: Array, notes: Object })

function fmt(n) { return Math.round(parseFloat(n) || 0).toLocaleString('en-US') }
function fmtM(n) { return ((parseFloat(n) || 0) / 1e6).toFixed(1) + 'M' }
function monthsLabel(days) { return Math.round(days / 30.44) + ' mo' }

// Expense Concentration "Total" row — same weighted-by-size approach as
// the Sales dashboard's Product Concentration total, minus customers.
function ecTotal(categories) {
  const totalItems = categories.reduce((s, c) => s + c.total_items, 0)
  const coreCount  = categories.reduce((s, c) => s + c.core_count, 0)
  const tailCount  = categories.reduce((s, c) => s + c.tail_count, 0)
  const totalValue = categories.reduce((s, c) => s + (c.total_value || 0), 0)
  const coreValue  = categories.reduce((s, c) => s + (c.core_value || 0), 0)
  const tailValue  = categories.reduce((s, c) => s + (c.tail_value || 0), 0)
  return {
    total_items: totalItems,
    core_count: coreCount,
    core_pct: totalValue > 0 ? Math.round((coreValue / totalValue) * 1000) / 10 : 0,
    tail_count: tailCount,
    tail_pct: totalValue > 0 ? Math.round((tailValue / totalValue) * 1000) / 10 : 0,
  }
}

const periodsLabel = computed(() => props.dashboard.periods.map(p => p.label).join(' · '))

// ── Share toggle ──
const isPublic = ref(props.dashboard.is_public)
const shareToken = ref(props.dashboard.share_token)
const copied = ref(false)
const shareUrl = ref(`${window.location.origin}/cd/${shareToken.value}`)
async function toggleShare() {
  const res = await axios.post(route('comparison-dashboard.toggle-share', { company: props.company.id, dashboard: props.dashboard.id }))
  isPublic.value = res.data.is_public
}
function copyLink() { navigator.clipboard.writeText(shareUrl.value); copied.value = true; setTimeout(() => copied.value = false, 1500) }

// ── Notes (auto-generated draft, editable, saved override wins) ──
const notesData = ref({ ...props.notes })
async function saveNote(sectionKey, text) {
  await axios.post(route('comparison-dashboard.save-note', { company: props.company.id, dashboard: props.dashboard.id }), { section_key: sectionKey, note: text })
  notesData.value[sectionKey] = { note: text, is_auto: false, auto_fallback: notesData.value[sectionKey]?.auto_fallback }
}

// ── Key Takeaways: each card edits independently ──
const editingTakeaway = ref(null)
const takeawayDraft = ref('')
function startEditTakeaway(key) { takeawayDraft.value = notesData.value[key]?.note || ''; editingTakeaway.value = key }
function resetTakeawayEdit(key) { takeawayDraft.value = notesData.value[key]?.auto_fallback || '' }
async function saveTakeawayEdit(key) { await saveNote(key, takeawayDraft.value); editingTakeaway.value = null }

// ── Vanishing items: Show More expander per pair ──
const expanded = ref({})
function toggleExpand(key) { expanded.value[key] = !expanded.value[key] }
function visibleRows(rows, key, cutoff) { return expanded.value[key] ? rows : rows.slice(0, cutoff) }

const NoteBox = defineComponent({
  props: { sectionKey: String, notes: Object },
  emits: ['save'],
  setup(p, { emit }) {
    const editing = ref(false)
    const draft = ref('')
    function start() { draft.value = p.notes[p.sectionKey]?.note || ''; editing.value = true }
    function resetToAuto() { draft.value = p.notes[p.sectionKey]?.auto_fallback || '' }
    function save() { emit('save', p.sectionKey, draft.value); editing.value = false }
    return () => {
      const n = p.notes[p.sectionKey]
      return h('div', { style: 'margin-top:18px;' }, [
        !editing.value ? h('div', { style: 'display:flex; align-items:flex-start; justify-content:space-between; gap:12px;' }, [
          h('div', { class: 'note', style: 'flex:1;' }, [
            h('span', { style: 'font-size:10px; text-transform:uppercase; letter-spacing:.05em; opacity:.7; display:block; margin-bottom:4px;' }, n?.is_auto === false ? '✎ Edited' : '🤖 Auto-generated'),
            n?.note || '',
          ]),
          h('button', { style: 'font-size:12px; color:#2563EB; background:none; border:none; cursor:pointer; white-space:nowrap;', onClick: start }, 'Edit'),
        ]) : h('div', {}, [
          h('textarea', {
            value: draft.value, rows: 4,
            style: 'width:100%; border:1px solid var(--border); border-radius:8px; padding:10px 12px; font-size:13px; font-family:Inter,sans-serif;',
            onInput: e => draft.value = e.target.value,
          }),
          h('div', { style: 'display:flex; gap:8px; margin-top:8px;' }, [
            h('button', { style: 'background:#2563EB; color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:12px; cursor:pointer;', onClick: save }, 'Save'),
            h('button', { style: 'background:none; border:none; color:var(--muted); font-size:12px; cursor:pointer;', onClick: resetToAuto }, 'Reset to Auto-Generated'),
            h('button', { style: 'background:none; border:none; color:var(--muted); font-size:12px; cursor:pointer;', onClick: () => editing.value = false }, 'Cancel'),
          ]),
        ]),
      ])
    }
  },
})

function sparkline(vFrom, vTo) {
  const max = Math.max(vFrom, 1)
  const x0 = 4, y0 = 36, x1 = 30, y1 = 34 - (vFrom / max) * 28, x2 = 86, y2 = 36
  const m1 = (x0 + x1) / 2, m2 = (x1 + x2) / 2
  const path = `M${x0},${y0} C${m1},${y0} ${m1},${y1} ${x1},${y1} C${m2},${y1} ${m2},${y2} ${x2},${y2}`
  return `<svg viewBox="0 0 90 40"><path d="${path}" stroke="#D97706" stroke-width="2.5" fill="none" stroke-linecap="round"/>
    <circle cx="${x1}" cy="${y1.toFixed(1)}" r="3" fill="#D97706"/><circle cx="${x2}" cy="${y2}" r="3" fill="#DC2626"/></svg>`
}

// ── Top 100 Expense Items leaderboard (dynamic period count) ──
const lbItems = ref(null)

function renderLeaderboard(container, columns) {
  const names = columns.map(col => new Set(col.rows.map(r => r.name)))
  const limit = columns[0]?.limit || 100
  container.innerHTML = columns.map((col, ci) => {
    const topNValue = col.rows.reduce((s, r) => s + r.value, 0)
    return `
    <div class="lb-col">
      <div class="lb-col-head" style="display:block;">
        <div style="display:flex; align-items:baseline; justify-content:space-between; gap:10px;">
          <span>${col.label}</span>
          <span class="lb-col-total" style="white-space:nowrap;">${fmtM(topNValue)}</span>
        </div>
        <div style="font-family:var(--mono); font-weight:500; font-size:10.5px; color:#9DB6DE; margin-top:5px; line-height:1.5;" title="Both figures are a share of this period's TOTAL expense — all items, not just the Top ${limit}">Top ${limit} = ${col.top_n_share}% of total expense<br>Top 10 = ${col.top10_share}% of total expense</div>
      </div>
      <div class="lb-col-body">
        ${col.rows.map((r, i) => {
          const inOtherCount = columns.filter((_, j) => j !== ci && names[j].has(r.name)).length
          const dotColor = inOtherCount === columns.length - 1 ? '#16A34A' : inOtherCount > 0 ? '#D97706' : '#CBD5E1'
          const priorCols = columns.slice(0, ci)
          const prevLine = priorCols.map((pc, pj) => {
            const rk = r[`rank_${pj}`]
            const periodsBack = ci - pj
            return `Yr${periodsBack} ${rk != null ? '#'+rk : 'new'}`
          }).join(' · ')
          return `<div class="lb-row">
            <div class="lb-rankcol"><span class="lb-rank">#${i+1}</span>${prevLine ? `<span class="lb-prev">${prevLine}</span>` : ''}</div>
            <span class="lb-persist" style="background:${dotColor}"></span>
            <span class="lb-name" title="${r.name}">${r.name}</span>
            <span class="lb-value">${fmtM(r.value)}</span>
          </div>`
        }).join('')}
      </div>
    </div>`
  }).join('')
}

// ── Chart.js (lazy-loaded) ──
let Chart = null
async function loadChartJs() {
  if (window.Chart) { Chart = window.Chart; return }
  await new Promise((resolve) => {
    const s = document.createElement('script')
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
    s.onload = () => { Chart = window.Chart; resolve() }
    document.head.appendChild(s)
  })
}

const zoomOutChart = ref(null)
onMounted(async () => {
  await loadChartJs()
  await nextTick()
  new Chart(zoomOutChart.value.getContext('2d'), {
    type: 'line',
    data: {
      labels: props.zoomOut.map(r => r.label),
      datasets: [
        {
          label: 'Total Expense', data: props.zoomOut.map(r => r.total_expense),
          borderColor: '#D97706', backgroundColor: 'transparent',
          borderWidth: 3, tension: 0.4, yAxisID: 'y',
          pointRadius: 4, pointBackgroundColor: '#D97706',
        },
        {
          label: 'Growth %', data: props.zoomOut.map(r => r.growth_pct),
          borderColor: '#DC2626', backgroundColor: 'transparent',
          borderWidth: 2, borderDash: [6, 4], tension: 0.4, yAxisID: 'y1',
          pointRadius: 4, pointBackgroundColor: '#DC2626',
        },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top', labels: { boxWidth: 14 } } },
      scales: {
        y:  { position: 'left',  ticks: { callback: v => fmtM(v) } },
        y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => v + '%' } },
      },
    },
  })
  renderLeaderboard(lbItems.value, props.top100.items)
})
</script>
