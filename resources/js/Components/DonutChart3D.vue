<script setup>
/**
 * Components/DonutChart3D.vue
 * ------------------------------------------------------------------
 * Shared "glossy 3D-look" donut chart used across Sales, Export Sales,
 * and Expense Analysis dashboards/reports.
 *
 * Built on Chart.js (already a project dependency) rather than amCharts:
 * amCharts' free tier requires keeping its attribution logo visible
 * unless you hold a paid commercial license, and this project doesn't
 * have one. The same glossy look — radial gradient darkening toward
 * each slice's edge, a soft drop shadow, and grow-on-hover — is
 * recreated here with a small Chart.js plugin, with no licensing
 * requirement (Chart.js is MIT-licensed).
 *
 * Every slice gets a genuinely distinct color (see utils/chartColors),
 * never a repeated one, even for charts with many categories.
 */
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { generateDistinctColors, shadeColor } from '@/utils/chartColors'

const props = defineProps({
  data:        { type: Array, required: true }, // [{ [labelKey]: ..., [valueKey]: ... }]
  labelKey:    { type: String, default: 'label' },
  valueKey:    { type: String, default: 'value' },
  height:      { type: [Number, String], default: 280 },
  showTotal:   { type: Boolean, default: true },
  totalLabel:  { type: String, default: 'TOTAL' },
  valuePrefix: { type: String, default: '' },
  legendPosition: { type: String, default: 'right' }, // 'right' | 'bottom' | 'none'
})

const canvasEl = ref(null)
let chartInstance = null

let Chart = null
async function loadChartJs() {
  if (Chart) return Chart
  await new Promise((resolve, reject) => {
    if (window.Chart) { Chart = window.Chart; resolve(); return }
    const s = document.createElement('script')
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
    s.onload = () => { Chart = window.Chart; resolve() }
    s.onerror = reject
    document.head.appendChild(s)
  })
  return Chart
}

function destroy() {
  if (chartInstance) { chartInstance.destroy(); chartInstance = null }
}

async function build() {
  destroy()
  await nextTick()
  if (!canvasEl.value) return

  const rows = (props.data || []).filter(r => Number(r[props.valueKey]) > 0)
  if (!rows.length) return

  await loadChartJs()

  const colors = generateDistinctColors(rows.length)
  const total  = rows.reduce((s, r) => s + Number(r[props.valueKey] || 0), 0)

  const gradientDonut = {
    id: 'gradientDonut',
    beforeDraw(chart) {
      const meta = chart.getDatasetMeta(0)
      meta.data.forEach((arc, i) => {
        const { x: cx, y: cy, outerRadius: outer, innerRadius: inner } = arc
        const base = colors[i]
        const grad = chart.ctx.createRadialGradient(cx, cy, inner, cx, cy, outer)
        grad.addColorStop(0, shadeColor(base, 35))
        grad.addColorStop(0.55, base)
        grad.addColorStop(1, shadeColor(base, -30))
        arc.options.backgroundColor = grad
      })
    },
    beforeDatasetsDraw(chart) {
      chart.ctx.save()
      chart.ctx.shadowColor = 'rgba(0,0,0,0.35)'
      chart.ctx.shadowBlur = 10
      chart.ctx.shadowOffsetY = 5
    },
    afterDatasetsDraw(chart) {
      chart.ctx.restore()
    },
  }

  const centerTotal = {
    id: 'centerTotal',
    afterDraw(chart) {
      if (!props.showTotal) return
      const { ctx, chartArea } = chart
      const cx = (chartArea.left + chartArea.right) / 2
      const cy = (chartArea.top + chartArea.bottom) / 2
      ctx.save()
      ctx.textAlign = 'center'
      ctx.textBaseline = 'middle'
      ctx.fillStyle = '#8a94a6'
      ctx.font = '10px sans-serif'
      ctx.fillText(props.totalLabel, cx, cy - 10)
      ctx.fillStyle = '#e2e8f0'
      ctx.font = 'bold 18px sans-serif'
      ctx.fillText(props.valuePrefix + total.toLocaleString('en-US', { maximumFractionDigits: 0 }), cx, cy + 10)
      ctx.restore()
    },
  }

  chartInstance = new Chart(canvasEl.value.getContext('2d'), {
    type: 'doughnut',
    data: {
      labels: rows.map(r => r[props.labelKey]),
      datasets: [{
        data: rows.map(r => Number(r[props.valueKey])),
        backgroundColor: colors,
        borderColor: '#111a2e',
        borderWidth: 2,
        hoverOffset: 10,
        cutout: '58%',
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: props.legendPosition === 'none' ? { display: false } : {
          position: props.legendPosition,
          labels: { color: '#94a3b8', font: { size: 11 }, padding: 12 },
        },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.label}: ${props.valuePrefix}${Number(ctx.raw).toLocaleString('en-US')} (${(ctx.raw / total * 100).toFixed(1)}%)`,
          },
        },
      },
      animation: { animateRotate: true, animateScale: true },
    },
    plugins: [gradientDonut, centerTotal],
  })
}

onMounted(build)
onBeforeUnmount(destroy)
watch(() => props.data, build, { deep: true })
</script>

<template>
  <div :style="{ height: typeof height === 'number' ? height + 'px' : height }">
    <div v-if="!(data || []).some(r => Number(r[valueKey]) > 0)" class="h-full flex items-center justify-center text-xs text-mp-muted">
      No data for this selection.
    </div>
    <canvas v-else ref="canvasEl"></canvas>
  </div>
</template>