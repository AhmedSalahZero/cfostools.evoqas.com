<template>
  <div ref="rootEl" class="w-full h-full relative min-h-0">
    <div v-if="loading"
      class="absolute inset-0 z-10 flex items-center justify-center bg-mp-page text-white text-sm">
      Loading editor…
    </div>
    <p v-else-if="error" class="p-8 text-mp-danger text-sm text-center">{{ error }}</p>

    <div v-show="!loading && !error" ref="containerEl" class="w-full h-full"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'

// sheets: { sheetName: { rows: [[...]], formats: { "r,c": "#,##0.00" } } }
const props = defineProps({
  sheets:       { type: Object, required: true },
  workbookName: { type: String, default: 'Workbook' },
})

const emit = defineEmits(['ready', 'error'])

const rootEl      = ref(null)
const containerEl = ref(null)
const loading     = ref(true)
const error       = ref('')

let univerAPI   = null
let resizeObs   = null

onMounted(async () => {
  try {
    // Lazy-load Univer so it doesn't bloat the Data Room bundle
    const { createUniver, defaultTheme, LocaleType } = await import('@univerjs/presets')
    const { UniverSheetsCorePreset } = await import('@univerjs/preset-sheets-core')
    const enUS = (await import('@univerjs/preset-sheets-core/locales/en-US')).default
    await import('@univerjs/presets/lib/styles/preset-sheets-core.css')

    await nextTick()
    if (!containerEl.value) throw new Error('Editor container is not available.')

    // Univer renders into a canvas — the container MUST have an explicit pixel
    // height before createUniver runs, otherwise the canvas is 0px tall
    applyHeight()

    const result = createUniver({
      locale:  LocaleType.EN_US,
      locales: { [LocaleType.EN_US]: enUS },
      theme:   defaultTheme,
      presets: [UniverSheetsCorePreset({ container: containerEl.value })],
    })

    univerAPI = result.univerAPI
    univerAPI.createWorkbook(buildSnapshot(LocaleType))

    // Keep the canvas in step with the modal / window
    if (typeof ResizeObserver !== 'undefined' && rootEl.value) {
      resizeObs = new ResizeObserver(applyHeight)
      resizeObs.observe(rootEl.value)
    }
    window.addEventListener('resize', applyHeight)

    loading.value = false
    emit('ready')
  } catch (err) {
    console.error('[UniverSheetEditor] init failed:', err)
    error.value   = 'Failed to load the spreadsheet editor. Please refresh the page.'
    loading.value = false
    emit('error', error.value)
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', applyHeight)
  resizeObs?.disconnect()
  try { univerAPI?.dispose?.() } catch (_) {}
  univerAPI = null
})

function applyHeight() {
  if (!containerEl.value || !rootEl.value) return
  const h = rootEl.value.clientHeight
  if (h > 0) containerEl.value.style.height = h + 'px'
}

// ── props.sheets → Univer workbook snapshot ──────────────────────────────────
function buildSnapshot(LocaleType) {
  const worksheets = {}
  const sheetOrder = []
  const styles     = {}
  const styleIds   = new Map()   // number-format pattern → style id

  function styleIdFor(pattern) {
    if (!styleIds.has(pattern)) {
      const id = `nf_${styleIds.size}`
      styleIds.set(pattern, id)
      styles[id] = { n: { pattern } }
    }
    return styleIds.get(pattern)
  }

  Object.keys(props.sheets || {}).forEach((name, idx) => {
    const sheetId = `sheet_${idx}`
    const sheet   = props.sheets[name] || {}
    const rows    = Array.isArray(sheet.rows) ? sheet.rows : (Array.isArray(sheet) ? sheet : [])
    const formats = sheet.formats || {}
    const cellData = {}

    rows.forEach((row, r) => {
      if (!Array.isArray(row)) return
      const rowObj = {}

      row.forEach((cell, c) => {
        if (cell === null || cell === undefined || cell === '') return

        if (typeof cell === 'string' && cell.startsWith('=')) {
          rowObj[c] = { f: cell }                      // formula — Univer evaluates live
        } else if (typeof cell === 'number') {
          rowObj[c] = { v: cell, t: 2 }                // t:2 = NUMBER
        } else if (typeof cell === 'boolean') {
          rowObj[c] = { v: cell ? 1 : 0, t: 3 }        // t:3 = BOOLEAN
        } else {
          rowObj[c] = { v: String(cell), t: 1 }        // t:1 = STRING
        }

        const pattern = formats[`${r},${c}`]
        if (pattern) rowObj[c].s = styleIdFor(pattern)
      })

      if (Object.keys(rowObj).length > 0) cellData[r] = rowObj
    })

    worksheets[sheetId] = {
      id:          sheetId,
      name,
      rowCount:    Math.max(rows.length + 20, 100),
      columnCount: 200,
      cellData,
    }
    sheetOrder.push(sheetId)
  })

  return {
    id:     'wb_data_room',
    name:   props.workbookName,
    locale: LocaleType.EN_US,
    sheets: worksheets,
    sheetOrder,
    styles,
  }
}

// ── Univer workbook → { sheetName: [[...]] } for the server ──────────────────
function getSheets() {
  if (!univerAPI) throw new Error('Editor not ready yet.')

  const workbook = univerAPI.getActiveWorkbook()
  if (!workbook) throw new Error('Could not read workbook.')

  const snapshot = workbook.save()
  if (!snapshot?.sheets) throw new Error('Could not read workbook data.')

  const payload = {}
  const order   = snapshot.sheetOrder || Object.keys(snapshot.sheets)

  order.forEach((sheetId) => {
    const wsData = snapshot.sheets[sheetId]
    if (!wsData) return

    const cellData = wsData.cellData || {}
    let maxRow = -1
    let maxCol = -1

    Object.keys(cellData).forEach((r) => {
      const rowObj = cellData[r] || {}
      const keys   = Object.keys(rowObj)
      if (keys.length === 0) return
      maxRow = Math.max(maxRow, parseInt(r, 10))
      keys.forEach((c) => { maxCol = Math.max(maxCol, parseInt(c, 10)) })
    })

    if (maxRow < 0 || maxCol < 0) {
      payload[wsData.name] = []
      return
    }

    const rows = []
    for (let r = 0; r <= maxRow; r++) {
      const row = []
      for (let c = 0; c <= maxCol; c++) {
        const cell = cellData[r]?.[c]
        if (!cell)                                       row.push('')
        else if (cell.f)                                 row.push(cell.f)   // keep formulas
        else if (cell.v !== undefined && cell.v !== null) row.push(cell.v)
        else                                             row.push('')
      }
      rows.push(row)
    }

    payload[wsData.name] = rows
  })

  return payload
}

defineExpose({ getSheets })
</script>

<style>
/* Force the Univer canvas to fill its container completely */
.univer-app-layout,
.univer-app-layout > div,
.univer-app-layout > div > div {
  height: 100% !important;
  min-height: 0 !important;
}

.univer-sheets-view,
.univer-render-canvas-container,
.univer-render-canvas-container canvas {
  height: 100% !important;
  width: 100% !important;
}

/* Override any white backgrounds Univer injects */
.univer-app-layout {
  background: #0c1829 !important;
}
</style>
