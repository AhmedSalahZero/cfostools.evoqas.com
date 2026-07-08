<template>
  <Head :title="`Live Editor — ${model.name}`" />
  <AuthenticatedLayout>
    <div class="bg-mp-page text-white flex flex-col" style="height: 100vh; overflow: hidden;">

      <!-- Top Bar -->
      <div class="bg-mp-card border-b border-mp-border shrink-0">
        <div class="max-w-full mx-auto px-6 py-4">
          <Link
            :href="`/portfolio-companies/${company.id}/financial-planning`"
            class="text-white hover:text-white flex items-center gap-2 mb-2 text-sm"
          >
            ← Back to Models
          </Link>
          <div class="flex justify-between items-center">
            <div>
              <h1 class="text-2xl font-bold">{{ model.name }}</h1>
              <p class="text-white text-sm mt-1">
                Live Editor — Formulas calculate automatically as you type
              </p>
            </div>
            <div class="flex items-center gap-3">
              <a
                :href="`/portfolio-companies/${company.id}/financial-planning/${model.id}/download`"
                class="bg-mp-page hover:bg-mp-muted px-4 py-2.5 rounded-lg font-medium flex items-center gap-2 text-sm"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Download Excel
              </a>
              <button
                @click="saveChanges"
                :disabled="saving"
                class="bg-mp-success hover:bg-mp-success px-6 py-2.5 rounded-lg font-medium flex items-center gap-2 disabled:opacity-50 transition-colors"
              >
                <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-4-4v8m0 0l3-3m-3 3l-3-3" />
                </svg>
                {{ saving ? 'Saving...' : 'Save Changes' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Flash message -->
      <div v-if="flashMessage" class="px-6 pt-4 w-full shrink-0">
        <div
          :class="flashMessage.type === 'success'
            ? 'bg-mp-success/50 border border-mp-success text-mp-success'
            : 'bg-mp-danger/50 border border-mp-danger text-mp-danger'"
          class="rounded-lg px-4 py-3 text-sm flex items-center gap-2"
        >
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          {{ flashMessage.text }}
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex-1 flex items-center justify-center text-white">
        <svg class="animate-spin w-6 h-6 mr-3" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        Loading spreadsheet...
      </div>

      <!-- Empty state -->
      <div v-else-if="!hasData" class="flex-1 flex flex-col items-center justify-center text-white">
        <svg class="w-12 h-12 mb-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <p class="text-sm">No data found in this file.</p>
        <p class="text-xs mt-1 text-white">Try downloading and re-uploading the Excel file.</p>
      </div>

      <!-- Univer spreadsheet container -->
      <div
        v-show="!loading && hasData"
        ref="univerContainer"
        style="flex: 1; min-height: 0; height: calc(100vh - 160px);"
      ></div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
  company: Object,
  model:   Object,
  sheets:  Object,  // { sheetName: [[row0col0, ...], ...] }
})

// ── State ─────────────────────────────────────────────────────────────────────
const univerContainer = ref(null)
const loading         = ref(true)
const saving          = ref(false)
const flashMessage    = ref(null)
let   flashTimer      = null

// univerAPI reference kept for save
let   univerAPI       = null

const sheetNames = computed(() => Object.keys(props.sheets || {}))
const hasData    = computed(() => sheetNames.value.length > 0)

const page = usePage()

// ── Mount ─────────────────────────────────────────────────────────────────────
onMounted(async () => {
  if (page.props.flash?.success) showFlash('success', page.props.flash.success)
  if (page.props.flash?.error)   showFlash('error',   page.props.flash.error)

  if (!hasData.value) {
    loading.value = false
    return
  }

  try {
    // Lazy-load Univer so it doesn't bloat other pages
    const presetsModule = await import('@univerjs/presets')
    const { createUniver, defaultTheme, LocaleType } = presetsModule

    const { UniverSheetsCorePreset } = await import('@univerjs/preset-sheets-core')
    const enUS = (await import('@univerjs/preset-sheets-core/locales/en-US')).default

    // Load Univer CSS (only once — Vite deduplicates)
    await import('@univerjs/presets/lib/styles/preset-sheets-core.css')

    await nextTick()

    // Univer renders into a canvas — the container MUST have an explicit pixel
    // height before createUniver is called, otherwise the canvas is 0px tall
    if (univerContainer.value) {
      univerContainer.value.style.height = (window.innerHeight - 160) + 'px'
    }

    // ── Convert props.sheets → Univer workbook snapshot ──────────────────────
    const worksheets  = {}
    const sheetOrder  = []

    sheetNames.value.forEach((name, idx) => {
      const sheetId  = `sheet_${idx}`
      const rawRows  = props.sheets[name] || []
      const cellData = {}

      rawRows.forEach((row, r) => {
        if (!row || !Array.isArray(row)) return
        const rowObj = {}
        row.forEach((cell, c) => {
          if (cell === null || cell === undefined || cell === '') return

          if (typeof cell === 'string' && cell.startsWith('=')) {
            // Formula — Univer evaluates these live
            rowObj[c] = { f: cell }
          } else if (typeof cell === 'number') {
            rowObj[c] = { v: cell, t: 2 }   // t:2 = NUMBER
          } else {
            rowObj[c] = { v: String(cell), t: 1 }  // t:1 = STRING
          }
        })

        if (Object.keys(rowObj).length > 0) {
          cellData[r] = rowObj
        }
      })

      worksheets[sheetId] = {
        id:          sheetId,
        name:        name,
        rowCount:    Math.max(rawRows.length + 20, 100),
        columnCount: 200,
        cellData,
      }
      sheetOrder.push(sheetId)
    })

    const workbookSnapshot = {
      id:         'wb_investawatch',
      name:       props.model.name,
      locale:     LocaleType.EN_US,
      sheets:     worksheets,
      sheetOrder,
    }

    // ── Init Univer ───────────────────────────────────────────────────────────
    const result = createUniver({
      locale:  LocaleType.EN_US,
      locales: { [LocaleType.EN_US]: enUS },
      theme:   defaultTheme,
      presets: [
        UniverSheetsCorePreset({
          container: univerContainer.value,
        }),
      ],
    })

    univerAPI = result.univerAPI

    // Load the workbook data
    univerAPI.createWorkbook(workbookSnapshot)

    // Resize the canvas when the window is resized
    window.addEventListener('resize', onResize)

    loading.value = false

  } catch (err) {
    console.error('[LiveEditor] Univer init failed:', err)
    showFlash('error', 'Failed to load the spreadsheet editor. Please refresh the page.')
    loading.value = false
  }
})

onBeforeUnmount(() => {
  if (flashTimer) clearTimeout(flashTimer)
  window.removeEventListener('resize', onResize)
  try { univerAPI?.dispose?.() } catch (_) {}
})

// ── Resize handler ────────────────────────────────────────────────────────────
function onResize() {
  if (univerContainer.value) {
    univerContainer.value.style.height = (window.innerHeight - 160) + 'px'
  }
}

// ── Save Changes ──────────────────────────────────────────────────────────────
function saveChanges() {
  if (!univerAPI) {
    showFlash('error', 'Editor not ready yet.')
    return
  }

  saving.value = true

  try {
    const fWorkbook = univerAPI.getActiveWorkbook()
    if (!fWorkbook) {
      showFlash('error', 'Could not read workbook.')
      saving.value = false
      return
    }

    const payload = {}

    // Use the internal workbook snapshot — most reliable way to get all cell data
    // including edits the user has made, with formula strings preserved
    const snapshot = fWorkbook.save()   // returns full IWorkbookData snapshot

    if (!snapshot || !snapshot.sheets) {
      showFlash('error', 'Could not read workbook data.')
      saving.value = false
      return
    }

    // snapshot.sheets is { sheetId: IWorksheetData }
    // snapshot.sheetOrder is [ sheetId, ... ] in display order
    const sheetOrder = snapshot.sheetOrder || Object.keys(snapshot.sheets)

    sheetOrder.forEach((sheetId) => {
      const wsData = snapshot.sheets[sheetId]
      if (!wsData) return

      // wsData.name is the real display name (e.g. "Sheet1", "Assumptions")
      const name     = wsData.name
      const cellData = wsData.cellData || {}

      // Find max row/col
      let maxRow = 0
      let maxCol = 0
      Object.keys(cellData).forEach(r => {
        const rNum = parseInt(r)
        if (rNum > maxRow) maxRow = rNum
        const rowObj = cellData[r] || {}
        Object.keys(rowObj).forEach(c => {
          const cNum = parseInt(c)
          if (cNum > maxCol) maxCol = cNum
        })
      })

      if (maxRow === 0 && maxCol === 0) {
        payload[name] = []
        return
      }

      // Build 2D array — row-major, 0-indexed
      const rows = []
      for (let r = 0; r <= maxRow; r++) {
        const row = []
        for (let c = 0; c <= maxCol; c++) {
          const cell = cellData[r]?.[c]
          if (!cell) { row.push(''); continue }
          // Preserve formula string so Excel round-trip keeps formulas
          if (cell.f)                                        row.push(cell.f)
          else if (cell.v !== undefined && cell.v !== null)  row.push(cell.v)
          else                                               row.push('')
        }
        rows.push(row)
      }

      payload[name] = rows
    })

    router.post(
      route('financial-planning.save-live', {
        company: props.company.id,
        model:   props.model.id,
      }),
      { sheets: payload },
      {
        preserveScroll: true,
        onSuccess: () => showFlash('success', 'Model saved successfully. Formulas preserved.'),
        onError:   () => showFlash('error',   'Save failed. Please try again.'),
        onFinish:  () => { saving.value = false },
      }
    )
  } catch (err) {
    console.error('[LiveEditor] Save error:', err)
    showFlash('error', 'Could not read sheet data to save.')
    saving.value = false
  }
}

// ── Flash helper ──────────────────────────────────────────────────────────────
function showFlash(type, text) {
  flashMessage.value = { type, text }
  if (flashTimer) clearTimeout(flashTimer)
  flashTimer = setTimeout(() => { flashMessage.value = null }, 5000)
}
</script>

<style>
/* Force Univer canvas to fill its container completely */
#univerContainer,
.univer-app-layout,
.univer-app-layout > div,
.univer-app-layout > div > div {
  height: 100% !important;
  min-height: 0 !important;
}

/* The main content area inside Univer (below toolbar) must stretch */
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