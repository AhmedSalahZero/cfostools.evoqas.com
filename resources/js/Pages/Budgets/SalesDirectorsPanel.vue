<!-- ─────────────────────────────────────────────────────────────────────────────
     SalesDirectorsPanel.vue
     Embedded inside Budgets/Create.vue as a 4th tab "Sales Directors"
     Handles:
       1. Add / remove Sales Directors for this budget
       2. Assign each Sales Revenue line item to a director
     Props from parent Create.vue:
       - orgUsers: [{ id, name, email }]
       - salesRevenueGroups: derived from form.sections.sales_revenue.groups
       - directors / assignments: managed by parent via v-model (reactive arrays)
─────────────────────────────────────────────────────────────────────────────── -->
<template>
  <div class="space-y-6">

    <!-- ── Step 1: Add Directors ── -->
    <div class="bg-mp-card border border-mp-border rounded-xl p-5">
      <div class="flex items-center justify-between mb-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest text-white">Sales Directors</p>
          <p class="text-xs text-white mt-0.5">Add team members who own specific revenue targets</p>
        </div>
        <button @click="addDirector"
          class="bg-mp-teal hover:bg-mp-teal text-white text-xs font-semibold px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
          + Add Director
        </button>
      </div>

      <div v-if="!directors.length" class="text-center py-8 text-white text-sm border border-dashed border-mp-border rounded-lg">
        No sales directors added yet. Click "Add Director" to get started.
      </div>

      <div class="space-y-3">
        <template v-for="(dir, di) in directors" :key="di">
          <div class="bg-mp-card-hover/60 border border-mp-border rounded-lg p-4 flex items-start gap-4">
            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
              <!-- User picker -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1">User Account</label>
                <select v-model.number="dir.user_id"
                  @change="dir.name = orgUsers.find(u => u.id === dir.user_id)?.name ?? dir.name"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mp-teal">
                  <option :value="null" disabled>Select user…</option>
                  <template v-for="u in orgUsers" :key="u.id">
                    <option :value="u.id">{{ u.name }}</option>
                  </template>
                </select>
              </div>
              <!-- Display name (editable override) -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1">Display Name</label>
                <input v-model="dir.name" type="text" placeholder="Full name"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-mp-teal" />
              </div>
              <!-- Title -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-wider mb-1">Title (optional)</label>
                <input v-model="dir.title" type="text" placeholder="e.g. Regional Sales Director"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-mp-teal" />
              </div>
            </div>
            <button @click="removeDirector(di)"
              class="mt-6 text-white hover:text-mp-danger transition-colors text-lg leading-none">✕</button>
          </div>
        </template>
      </div>
    </div>

    <!-- ── Step 2: Assign line items to directors ── -->
    <div class="bg-mp-card border border-mp-border rounded-xl p-5">
      <div class="mb-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-white">Revenue Line Item Assignments</p>
        <p class="text-xs text-white mt-0.5">Assign each Sales Revenue line item to the responsible director. Unassigned items remain under general budget.</p>
      </div>

      <div v-if="!directors.length" class="text-center py-6 text-white text-sm border border-dashed border-mp-border rounded-lg">
        Add at least one Sales Director above before assigning line items.
      </div>

      <div v-else-if="!allRevenueItems.length" class="text-center py-6 text-white text-sm border border-dashed border-mp-border rounded-lg">
        No Sales Revenue line items found. Go to the Income Statement tab and add line items under Sales Revenues first.
      </div>

      <div v-else class="overflow-x-auto">
        <!-- Header -->
        <div class="flex items-center px-4 py-2 bg-mp-card-hover rounded-t-lg border border-mp-border text-xs font-semibold text-white">
          <div class="flex-1">Line Item</div>
          <div class="w-40 text-center">Group</div>
          <div class="w-48 text-center">Assigned Director</div>
          <div class="w-32 text-right">Annual Budget</div>
        </div>

        <template v-for="(item, idx) in allRevenueItems" :key="item._key">
          <div class="flex items-center px-4 py-3 border-b border-mp-border hover:bg-mp-card-hover/30 transition-colors">
            <div class="flex-1 text-sm text-white">{{ item.label || '(unnamed item)' }}</div>
            <div class="w-40 text-center text-xs text-white">{{ item.groupName }}</div>
            <div class="w-48 flex justify-center">
              <select v-model="assignments[item._key]"
                class="bg-mp-card-hover border border-mp-border rounded-lg px-2 py-1.5 text-xs text-white focus:outline-none focus:border-mp-teal w-full">
                <option :value="null">— Unassigned —</option>
                <template v-for="(dir, di) in directors" :key="di">
                  <option v-if="dir.name" :value="di">{{ dir.name }}</option>
                </template>
              </select>
            </div>
            <div class="w-32 text-right text-sm text-white">{{ fmtNum(item.annualTotal) }}</div>
          </div>
        </template>

        <!-- Director summary row -->
        <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3">
          <template v-for="(dir, di) in directors" :key="di">
            <div v-if="dir.name" class="bg-mp-card-hover/60 border border-mp-border rounded-lg p-3">
              <p class="text-xs font-semibold text-white truncate">{{ dir.name }}</p>
              <p class="text-sm font-bold text-white mt-1">{{ fmtNum(directorTotal(di)) }}</p>
              <p class="text-xs text-white">{{ directorItemCount(di) }} line items</p>
            </div>
          </template>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  orgUsers:            { type: Array, default: () => [] },
  salesRevenueGroups:  { type: Array, default: () => [] },  // from form.sections.sales_revenue.groups
})

const directors   = defineModel('directors',   { default: () => [] })
const assignments = defineModel('assignments', { default: () => ({}) })

// ── Director management ───────────────────────────────────────────────────────
function addDirector() {
  directors.value.push({ user_id: null, name: '', title: '' })
}
function removeDirector(di) {
  directors.value.splice(di, 1)
  // Clean up assignments pointing to this director
  for (const key in assignments.value) {
    if (assignments.value[key] === di) {
      assignments.value[key] = null
    }
    // Shift down indices > di
    if (assignments.value[key] > di) {
      assignments.value[key]--
    }
  }
}

// ── Flatten revenue line items from the Income Statement form ─────────────────
const allRevenueItems = computed(() => {
  const items = []
  ;(props.salesRevenueGroups ?? []).forEach((grp, gi) => {
    ;(grp.line_items ?? []).forEach((li, ii) => {
      const _key = `g${gi}_i${ii}`
      const annualTotal = Object.values(li.monthly_amounts ?? {}).reduce((s, v) => s + (parseFloat(v) || 0), 0)
      items.push({ _key, label: li.label, groupName: grp.name || `Group ${gi + 1}`, annualTotal })
    })
  })
  return items
})

// ── Per-director totals ───────────────────────────────────────────────────────
function directorTotal(di) {
  return allRevenueItems.value
    .filter(item => assignments.value[item._key] === di)
    .reduce((s, item) => s + item.annualTotal, 0)
}
function directorItemCount(di) {
  return allRevenueItems.value.filter(item => assignments.value[item._key] === di).length
}

// ── Formatter ─────────────────────────────────────────────────────────────────
function fmtNum(v) {
  if (!v) return '—'
  const abs = Math.abs(v)
  if (abs >= 1_000_000) return (v / 1_000_000).toFixed(1) + 'M'
  if (abs >= 1_000)     return (v / 1_000).toFixed(1) + 'K'
  return v.toFixed(0)
}
</script>