<template>
  <Head :title="`${wbName} — Model Studio`" />
  <AuthenticatedLayout>
    <div class="flex flex-col bg-mp-page text-mp-text-secondary overflow-hidden" style="height:100vh;">

      <!-- ── TOP BAR ── -->
      <div class="bg-mp-card border-b border-mp-border px-4 py-2 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3">
          <Link :href="`/portfolio-companies/${company.id}/model-studio`"
            class="text-mp-muted hover:text-mp-text-secondary transition-colors flex items-center gap-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>Back
          </Link>
          <span class="text-mp-muted">|</span>
          <span class="text-xs text-mp-muted">{{ company.name }}</span>
          <span class="text-mp-muted">/</span>
          <input v-model="wbName" @blur="autoSave" @keyup.enter="$event.target.blur()"
            class="bg-transparent text-mp-text-secondary font-semibold text-sm focus:outline-none focus:border-b focus:border-mp-teal min-w-0 w-48"/>
        </div>

        <div class="flex items-center gap-3">
          <span class="text-xs" :class="saveStatus === 'Saved ✓' || saveStatus.startsWith('Saved ✓') ? 'text-mp-success' : saveStatus === 'Saving…' ? 'text-mp-warning' : 'text-mp-muted'">
            {{ saveStatus }}
          </span>
          <button @click="showChartPanel = !showChartPanel"
            :class="['flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-lg border transition-colors',
              showChartPanel ? 'bg-mp-gold border-mp-gold text-mp-text-secondary' : 'bg-mp-card-hover border-mp-border text-mp-text hover:text-mp-text-secondary']">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Charts
          </button>
          <button @click="saveNow"
            class="flex items-center gap-1.5 bg-mp-teal hover:bg-mp-teal-dark text-mp-text-secondary text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
            </svg>
            Save
          </button>
        </div>
      </div>

      <!-- ── MAIN AREA ── -->
      <div class="flex flex-1 overflow-hidden" style="min-height:0;">

        <!-- UNIVER CONTAINER — takes all available space -->
        <div class="flex-1 overflow-hidden" style="min-height:0; position:relative;">
          <div ref="univerContainer" style="position:absolute; inset:0;"></div>
        </div>

        <!-- CHART PANEL -->
        <div v-if="showChartPanel"
          class="w-80 bg-mp-card border-l border-mp-border flex flex-col overflow-y-auto flex-shrink-0">
          <div class="p-4 border-b border-mp-border flex items-center justify-between">
            <h3 class="text-sm font-semibold text-mp-text-secondary">📊 Charts</h3>
            <button @click="addChart"
              class="text-xs bg-mp-teal hover:bg-mp-teal-dark text-mp-text-secondary px-3 py-1 rounded-lg transition-colors">
              + Add Chart
            </button>
          </div>
          <div v-if="charts.length === 0" class="p-6 text-center">
            <p class="text-mp-muted text-xs">No charts yet. Click "+ Add Chart" to create one from your data.</p>
          </div>
          <div v-for="(chart, ci) in charts" :key="chart.id" class="border-b border-mp-border p-4">
            <div class="flex items-center justify-between mb-3">
              <input v-model="chart.title" placeholder="Chart title"
                class="bg-transparent text-sm font-medium text-mp-text-secondary focus:outline-none border-b border-transparent focus:border-mp-teal flex-1"/>
              <button @click="removeChart(ci)" class="text-mp-muted hover:text-mp-danger ml-2 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <label class="text-xs text-white uppercase tracking-widest font-semibold mb-1 block">Type</label>
            <select v-model="chart.type" class="w-full bg-mp-card-hover border border-mp-border text-mp-text-secondary text-xs rounded-lg px-3 py-1.5 mb-3 focus:outline-none focus:border-mp-teal">
              <option value="bar">Bar</option>
              <option value="line">Line</option>
              <option value="pie">Pie / Donut</option>
            </select>
            <label class="text-xs text-white uppercase tracking-widest font-semibold mb-1 block">Labels Range (e.g. A1:A12)</label>
            <input v-model="chart.labelsRange" placeholder="A1:A12"
              class="w-full bg-mp-card-hover border border-mp-border text-mp-text-secondary text-xs rounded-lg px-3 py-1.5 mb-3 focus:outline-none focus:border-mp-teal font-mono"/>
            <label class="text-xs text-white uppercase tracking-widest font-semibold mb-1 block">Data Range (e.g. B1:B12)</label>
            <input v-model="chart.dataRange" placeholder="B1:B12"
              class="w-full bg-mp-card-hover border border-mp-border text-mp-text-secondary text-xs rounded-lg px-3 py-1.5 mb-3 focus:outline-none focus:border-mp-teal font-mono"/>
            <button @click="renderChart(ci)"
              class="w-full bg-mp-gold hover:bg-mp-gold-dark text-mp-text-secondary text-xs font-semibold py-1.5 rounded-lg transition-colors mb-3">
              Update Chart
            </button>
            <canvas :ref="el => chartCanvases[ci] = el" :id="`chart-${ci}`"
              class="w-full rounded-lg bg-mp-card-hover" height="200"></canvas>
          </div>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Chart from 'chart.js/auto'

// Univer is loaded dynamically in initUniver() to reduce initial bundle size

const props = defineProps({
  company:  { type: Object, default: () => ({ id: null, name: '' }) },
  workbook: { type: Object, default: () => ({ id: null, name: 'Workbook', sheets_data: null, charts_data: [] }) },
})

// ── STATE ──
const wbName         = ref(props.workbook.name)
const saveStatus     = ref('All changes saved')
const showChartPanel = ref(false)
const univerContainer = ref(null)

let univerInstance  = null   // the Univer core instance
let univerAPI       = null   // the Facade API — use this for everything
let saveTimer       = null

// Charts (kept exactly as before, using Chart.js)
const charts        = reactive(props.workbook.charts_data ?? [])
const chartCanvases = ref([])
const chartInstances = {}

// ── HELPERS ──

/**
 * Convert the OLD format (array of { id, name, data: string[][] })
 * into a Univer IWorkbookData snapshot.
 * Called once when opening a workbook that was saved with the old Handsontable format.
 */
function legacyToUniverSnapshot(sheetsArray) {
  const sheetsMap = {}
  sheetsArray.forEach((s, idx) => {
    const cellData = {}
    if (Array.isArray(s.data)) {
      s.data.forEach((row, r) => {
        if (!Array.isArray(row)) return
        row.forEach((cell, c) => {
          if (cell === null || cell === undefined || cell === '') return
          if (!cellData[r]) cellData[r] = {}
          const strVal = String(cell)
          cellData[r][c] = strVal.startsWith('=')
            ? { f: strVal }          // formula
            : { v: cell }            // plain value
        })
      })
    }
    sheetsMap[s.id] = {
      id:       s.id,
      name:     s.name,
      tabColor: '',
      hidden:   0,
      index:    idx,
      rowCount: 1000,
      columnCount: 26,
      cellData,
      defaultRowHeight:  24,
      defaultColumnWidth: 93,
    }
  })

  // Pick the first sheet as the default active one
  const firstSheetId = sheetsArray[0]?.id ?? 'sheet_1'

  return {
    id:             `wb_${props.workbook.id}`,
    name:           props.workbook.name,
    sheetOrder:     sheetsArray.map(s => s.id),
    activeSheetId:  firstSheetId,
    sheets:         sheetsMap,
    locale:         'enUS',
  }
}

/**
 * Detect whether the saved data is the OLD Handsontable format or the NEW Univer snapshot.
 * Old format is an array: [{ id, name, data: [] }]
 * New format is an object: { id, sheets: {}, ... }
 */
function isLegacyFormat(data) {
  return Array.isArray(data)
}

// ── INIT UNIVER ──
async function initUniver() {
  if (!univerContainer.value) return

  const [
    { createUniver, LocaleType, mergeLocales },
    { UniverSheetsCorePreset },
    { default: UniverPresetSheetsCoreEnUS },
  ] = await Promise.all([
    import('@univerjs/presets'),
    import('@univerjs/preset-sheets-core'),
    import('@univerjs/preset-sheets-core/locales/en-US'),
    import('@univerjs/preset-sheets-core/lib/index.css'),
  ])

  // Decide what snapshot to load
  let snapshot
  const saved = props.workbook.sheets_data

  if (!saved || (Array.isArray(saved) && saved.length === 0)) {
    // Brand new workbook — let Univer create a blank sheet
    snapshot = null
  } else if (isLegacyFormat(saved)) {
    // Old Handsontable format — convert it
    snapshot = legacyToUniverSnapshot(saved)
  } else {
    // Already a Univer snapshot
    snapshot = saved
  }

  const { univer, univerAPI: api } = createUniver({
    locale: LocaleType.EN_US,
    locales: {
      [LocaleType.EN_US]: mergeLocales(
        UniverPresetSheetsCoreEnUS,
        
      ),
    },
    presets: [
      UniverSheetsCorePreset({
        container: univerContainer.value,
        
      }),
      
    ],
  })

  univerInstance = univer
  univerAPI      = api

  if (snapshot) {
    univerAPI.createWorkbook(snapshot)
  } else {
    univerAPI.createWorkbook({ name: wbName.value })
  }

  // Auto-save whenever any cell changes
  univerAPI.addEvent(univerAPI.Event.SheetValueChanged, () => {
    scheduleAutoSave()
  })
}

// ── SAVE ──
function scheduleAutoSave() {
  saveStatus.value = 'Unsaved changes'
  clearTimeout(saveTimer)
  saveTimer = setTimeout(autoSave, 2000)
}

async function autoSave() {
  await doSave()
}

async function saveNow() {
  await doSave()
}

async function doSave() {
  if (!univerAPI) return
  saveStatus.value = 'Saving…'

  // Get the full Univer snapshot — this is what we store in the DB
  const wb       = univerAPI.getActiveWorkbook()
  const snapshot = wb ? wb.save() : {}

  // Rename if the workbook title changed
  if (snapshot && wbName.value) {
    snapshot.name = wbName.value
  }

  try {
    const cookie = document.cookie.split(';').find(c => c.trim().startsWith('XSRF-TOKEN='))
    const token  = cookie ? decodeURIComponent(cookie.trim().slice(11)) : ''

    const res = await fetch(
      `/portfolio-companies/${props.company.id}/model-studio/${props.workbook.id}/save`,
      {
        method:  'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token },
        body: JSON.stringify({
          name:        wbName.value,
          sheets_data: snapshot,   // ← now a Univer snapshot object, not an array
          charts_data: charts,
        }),
      }
    )
    const json = await res.json()
    saveStatus.value = json.ok ? `Saved ✓  ${json.saved_at}` : 'Error saving'
  } catch (e) {
    console.error(e)
    saveStatus.value = 'Error saving'
  }
}

// ── CHARTS (unchanged from original — Chart.js sidebar) ──
function addChart() {
  charts.push({
    id:          `chart_${Date.now()}`,
    title:       'New Chart',
    type:        'bar',
    labelsRange: 'A1:A10',
    dataRange:   'B1:B10',
  })
}

function removeChart(ci) {
  if (chartInstances[ci]) { chartInstances[ci].destroy(); delete chartInstances[ci] }
  charts.splice(ci, 1)
}

function colLetterToIndex(l) {
  let col = 0
  for (let i = 0; i < l.length; i++) col = col * 26 + (l.charCodeAt(i) - 64)
  return col - 1
}

function renderChart(ci) {
  const chart  = charts[ci]
  const canvas = chartCanvases.value[ci]
  if (!canvas || !univerAPI) return

  // Read values from Univer via the Facade API
  function readRange(rangeStr) {
    try {
      const match = rangeStr.toUpperCase().match(/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/)
      if (!match) return []
      const c1 = colLetterToIndex(match[1]), r1 = parseInt(match[2]) - 1
      const c2 = colLetterToIndex(match[3]), r2 = parseInt(match[4]) - 1
      const wb = univerAPI.getActiveWorkbook()
      if (!wb) return []
      const ws = wb.getActiveSheet()
      if (!ws) return []
      const vals = []
      for (let r = r1; r <= r2; r++) {
        for (let c = c1; c <= c2; c++) {
          const cell = ws.getRange(r, c)
          vals.push(cell ? cell.getDisplayValue() : '')
        }
      }
      return vals
    } catch { return [] }
  }

  const labels   = readRange(chart.labelsRange)
  const dataVals = readRange(chart.dataRange).map(v => parseFloat(v) || 0)

  if (chartInstances[ci]) chartInstances[ci].destroy()

  const colors = dataVals.map((_, i) => `hsl(${(i * 47 + 200) % 360}, 65%, 55%)`)

  chartInstances[ci] = new Chart(canvas, {
    type: chart.type === 'pie' ? 'doughnut' : chart.type,
    data: {
      labels,
      datasets: [{
        label:           chart.title,
        data:            dataVals,
        backgroundColor: chart.type === 'line' ? 'rgba(99,179,237,0.15)' : colors,
        borderColor:     chart.type === 'line' ? '#00b4c8' : colors,
        borderWidth:     2,
        fill:            chart.type === 'line',
        tension:         0.4,
      }]
    },
    options: {
      responsive:          true,
      maintainAspectRatio: true,
      plugins: { legend: { labels: { color: '#e2e8f0', font: { size: 11 } } } },
      scales: chart.type === 'pie' ? {} : {
        x: { ticks: { color: '#64748b' }, grid: { color: '#112240' } },
        y: { ticks: { color: '#64748b' }, grid: { color: '#112240' } },
      }
    }
  })
}

// ── LIFECYCLE ──
onMounted(() => nextTick(() => initUniver()))

onBeforeUnmount(() => {
  clearTimeout(saveTimer)
  if (univerInstance) {
    univerInstance.dispose()
    univerInstance = null
    univerAPI      = null
  }
  Object.values(chartInstances).forEach(c => c?.destroy())
})
</script>

<style>
/*
  Univer renders itself inside the container div using its own DOM.
  We just need to make sure its z-index plays nicely with our top bar.
  No other custom CSS needed — Univer handles everything internally.
*/
.univer-app-container {
  height: 100% !important;
}
</style>