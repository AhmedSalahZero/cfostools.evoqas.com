<template>
  <Head :title="`Multi-Period View — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ═══════════════════ HEADER ═══════════════════ -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-5">

          <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Statements
          </Link>

          <div class="flex flex-wrap items-center gap-4 justify-between">
            <div>
              <h1 class="text-2xl font-bold text-white">📊 Multi-Period Financial View</h1>
              <p class="text-white text-sm mt-0.5">{{ company.name }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">

              <!-- Mode switcher -->
              <div class="flex items-center bg-mp-card-hover rounded-lg p-1 gap-0.5">
                <button v-for="m in modes" :key="m.key"
                  @click="switchMode(m.key)"
                  :class="[
                    'px-3 py-1.5 text-xs font-semibold rounded-md transition-colors',
                    currentMode === m.key ? 'bg-mp-teal text-white' : 'text-white hover:text-white'
                  ]">
                  {{ m.label }}
                </button>
              </div>

              <!-- Year selectors -->
              <template v-if="currentMode === 'monthly' || currentMode === 'yoy'">
                <select v-model="selectedYearA" @change="reloadData"
                  class="bg-mp-card-hover border border-mp-border text-white text-sm rounded-lg px-3 py-2 focus:border-mp-teal focus:outline-none">
                  <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                </select>
                <template v-if="currentMode === 'yoy'">
                  <span class="text-white text-sm">vs</span>
                  <select v-model="selectedYearB" @change="reloadData"
                    class="bg-mp-card-hover border border-mp-border text-white text-sm rounded-lg px-3 py-2 focus:border-mp-teal focus:outline-none">
                    <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                  </select>
                </template>
              </template>

              <!-- Expand / Collapse all -->
              <button @click="toggleAllSections"
                class="flex items-center gap-1.5 text-xs text-white hover:text-white bg-mp-card-hover hover:bg-mp-page border border-mp-border px-3 py-2 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h7"/>
                </svg>
                {{ allExpanded ? 'Collapse All' : 'Expand All' }}
              </button>

              <!-- Common-size toggle (monthly only) -->
              <label v-if="currentMode !== 'yoy'" class="flex items-center gap-2 cursor-pointer select-none">
                <span class="text-xs text-white">Common-Size %</span>
                <div @click="showCommonSize = !showCommonSize"
                  :class="showCommonSize ? 'bg-mp-teal' : 'bg-mp-page'"
                  class="relative w-10 h-5 rounded-full transition-colors cursor-pointer">
                  <div :class="showCommonSize ? 'translate-x-5' : 'translate-x-0.5'"
                    class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"></div>
                </div>
              </label>

              <!-- % change toggle (YoY only) -->
              <label v-if="currentMode === 'yoy'" class="flex items-center gap-2 cursor-pointer select-none">
                <span class="text-xs text-white">Show Δ%</span>
                <div @click="showPctChange = !showPctChange"
                  :class="showPctChange ? 'bg-mp-gold-dark' : 'bg-mp-page'"
                  class="relative w-10 h-5 rounded-full transition-colors cursor-pointer">
                  <div :class="showPctChange ? 'translate-x-5' : 'translate-x-0.5'"
                    class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"></div>
                </div>
              </label>

            </div>
          </div>

          <!-- Statement tabs -->
          <div class="flex gap-1 mt-5 border-b border-mp-border -mb-[1px]">
            <button v-for="tab in statementTabs" :key="tab.key"
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

      <!-- ═══════════════════ CONTENT ═══════════════════ -->
      <div class="px-4 sm:px-6 lg:px-8 py-6">

        <div v-if="activeTab !== 'ratios' && !filteredRows.length" class="bg-mp-card rounded-xl border border-mp-border p-12 text-center">
          <p class="text-white">No data available for the selected period and statement type.</p>
        </div>

        <!-- ── MONTHLY MODE ── -->
        <template v-else-if="currentMode === 'monthly' && activeTab !== 'ratios'">
          <div class="bg-mp-card rounded-xl border border-mp-border overflow-x-auto">
            <table class="text-sm" style="min-width: max-content; width: 100%;">
              <thead>
                <tr class="border-b border-mp-border bg-mp-card-hover/60">
                  <th class="sticky left-0 z-10 bg-mp-card-hover text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3.5 min-w-56">
                    Description
                  </th>
                  <th v-for="col in columnsA" :key="col.label"
                    :class="[
                      'text-right text-xs font-semibold uppercase tracking-widest px-4 py-3.5 min-w-36 whitespace-nowrap',
                      col.col_type === 'quarter' ? 'text-white bg-mp-gold/20 border-l border-mp-gold/40' :
                      col.col_type === 'ytd'     ? 'text-mp-success bg-mp-success/20 border-l border-mp-success/40' :
                                                   'text-white'
                    ]">
                    <div>{{ col.label }}</div>
                    <div v-if="col.col_type === 'quarter'" class="text-white font-normal normal-case text-xs mt-0.5">Quarterly</div>
                    <div v-if="col.col_type === 'ytd'"     class="text-mp-success font-normal normal-case text-xs mt-0.5">Year-to-Date</div>
                  </th>
                </tr>
              </thead>
              <tbody>
                <template v-for="row in filteredRows" :key="row.key">

                  <!-- ── SECTION HEADER ROW ── -->
                  <tr :class="[
                    'border-t transition-colors cursor-pointer select-none',
                    row.is_computed
                      ? 'bg-mp-teal-subtle/20 border-mp-teal/30 hover:bg-mp-teal-subtle/40'
                      : 'border-mp-border hover:bg-mp-card-hover/40 bg-mp-card-hover/20'
                  ]"
                    @click="!row.is_computed && toggleSection(row.key)">

                    <td class="sticky left-0 z-10 px-5 py-3"
                      :class="row.is_computed ? 'bg-mp-teal-subtle/40' : 'bg-mp-card-hover/40'">
                      <div class="flex items-center gap-2">
                        <!-- Expand chevron for non-computed rows with sub-items -->
                        <span v-if="!row.is_computed && (row.line_item_labels?.length ?? 0) > 0"
                          class="text-white transition-transform duration-200 shrink-0"
                          :class="expandedSections.has(row.key) ? 'rotate-90' : ''">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                          </svg>
                        </span>
                        <!-- Spacer for computed rows -->
                        <span v-else class="w-3.5 h-3.5 shrink-0 inline-block"></span>
                        <span v-if="row.is_computed" class="text-xs bg-mp-teal-subtle/60 text-white px-1.5 py-0.5 rounded shrink-0">AUTO</span>
                        <span :class="row.is_computed ? 'text-white font-bold' : 'text-white font-bold'">
                          {{ row.label }}
                        </span>
                        <span v-if="!row.is_computed && (row.line_item_labels?.length ?? 0) > 0"
                          class="text-xs text-white font-normal">
                          ({{ row.line_item_labels.length }})
                        </span>
                      </div>
                    </td>

                    <!-- Section total per column -->
                    <td v-for="col in columnsA" :key="col.label"
                      :class="[
                        'px-4 py-3 text-right',
                        col.col_type === 'quarter' ? 'bg-mp-gold/10 border-l border-mp-gold/30' :
                        col.col_type === 'ytd'     ? 'bg-mp-success/10 border-l border-mp-success/30' : ''
                      ]">
                      <div :class="[
                        'font-bold tabular-nums',
                        getVal(col, row.key) < 0       ? 'text-mp-danger' :
                        col.col_type === 'ytd'         ? 'text-mp-success' :
                        col.col_type === 'quarter'     ? 'text-white' :
                        row.is_computed                ? 'text-white' :
                                                         'text-white'
                      ]">
                        {{ formatNum(getVal(col, row.key)) }}
                      </div>
                      <div v-if="showCommonSize && getCommonBase(col, row.statement_type) !== 0"
                        class="text-xs text-white mt-0.5 tabular-nums">
                        {{ commonSizePct(col, row) }}%
                      </div>
                    </td>
                  </tr>

                  <!-- ── SUB-ROWS (line items) — shown when section expanded ── -->
                  <template v-if="!row.is_computed && expandedSections.has(row.key)">
                    <tr v-for="label in row.line_item_labels" :key="label"
                      class="border-t border-mp-border/60 hover:bg-mp-card-hover/20 transition-colors">

                      <!-- Label with indent -->
                      <td class="sticky left-0 z-10 bg-mp-card px-5 py-2.5">
                        <div class="flex items-center gap-2 pl-6">
                          <span class="w-1 h-1 rounded-full bg-mp-muted shrink-0"></span>
                          <span class="text-white text-xs font-medium">{{ label }}</span>
                        </div>
                      </td>

                      <!-- Amount per column -->
                      <td v-for="col in columnsA" :key="col.label"
                        :class="[
                          'px-4 py-2.5 text-right',
                          col.col_type === 'quarter' ? 'bg-mp-gold/5 border-l border-mp-gold/20' :
                          col.col_type === 'ytd'     ? 'bg-mp-success/5 border-l border-mp-success/20' : ''
                        ]">
                        <span :class="[
                          'tabular-nums text-xs',
                          getLineItemVal(col, row.key, label) < 0 ? 'text-mp-danger' :
                          getLineItemVal(col, row.key, label) === 0 ? 'text-white' :
                          col.col_type !== 'month' ? 'text-white' : 'text-white'
                        ]">
                          {{ getLineItemVal(col, row.key, label) !== 0
                              ? formatNum(getLineItemVal(col, row.key, label))
                              : '—' }}
                        </span>
                      </td>
                    </tr>
                  </template>

                </template>
              </tbody>
            </table>
          </div>

          <!-- Legend -->
          <div class="mt-3 flex flex-wrap items-center gap-5 text-xs text-white">
            <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-sm bg-mp-teal/60"></div><span>Monthly</span></div>
            <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-sm bg-mp-gold-dark/60"></div><span>Quarterly total</span></div>
            <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-sm bg-mp-success/60"></div><span>Year-to-Date</span></div>
            <div class="flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
              </svg>
              <span>Click a section row to expand/collapse sub-items</span>
            </div>
          </div>
        </template>

        <!-- ── YEAR-ON-YEAR MODE ── -->
        <template v-else-if="currentMode === 'yoy'">

          <!-- KPI summary cards -->
          <div v-if="yoySummaryKpis.length" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div v-for="kpi in yoySummaryKpis" :key="kpi.label"
              class="bg-mp-card rounded-xl border border-mp-border p-4">
              <p class="text-xs text-white mb-1">{{ kpi.label }}</p>
              <p class="text-white font-bold text-base tabular-nums">{{ kpi.valueA }}</p>
              <p class="text-white text-xs tabular-nums">{{ kpi.valueB }}</p>
              <div class="mt-2 flex items-center gap-1">
                <span :class="kpi.positive ? 'text-mp-success' : 'text-mp-danger'" class="text-sm font-semibold">
                  {{ kpi.positive ? '▲' : '▼' }} {{ kpi.pct }}%
                </span>
                <span class="text-white text-xs">vs {{ yearB }}</span>
              </div>
            </div>
          </div>

          <!-- YoY table -->
          <div class="bg-mp-card rounded-xl border border-mp-border overflow-x-auto">
            <table class="text-sm" style="min-width: max-content; width: 100%;">
              <thead>
                <!-- Month group labels -->
                <tr class="border-b border-mp-border/50 bg-mp-card-hover/40">
                  <th class="sticky left-0 z-10 bg-mp-card-hover px-5 py-2"></th>
                  <template v-for="monthNum in yoyMonths" :key="'yh'+monthNum">
                    <th :colspan="showPctChange ? 3 : 2"
                      class="text-center text-xs text-white font-medium px-3 py-2 border-l border-mp-border/50">
                      {{ monthName(monthNum) }}
                    </th>
                  </template>
                  <th :colspan="showPctChange ? 3 : 2"
                    class="text-center text-xs text-mp-success font-semibold px-4 py-2 border-l border-mp-border">
                    Full YTD
                  </th>
                </tr>
                <!-- Year sub-headers -->
                <tr class="border-b border-mp-border bg-mp-card-hover/60">
                  <th class="sticky left-0 z-10 bg-mp-card-hover text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3.5 min-w-56">
                    Description
                  </th>
                  <template v-for="monthNum in yoyMonths" :key="'mh'+monthNum">
                    <th class="text-right text-xs font-semibold text-white px-3 py-3.5 min-w-28 border-l border-mp-border/50 whitespace-nowrap">{{ yearA }}</th>
                    <th class="text-right text-xs font-semibold text-white px-3 py-3.5 min-w-28 whitespace-nowrap">{{ yearB }}</th>
                    <th v-if="showPctChange" class="text-right text-xs font-semibold text-white px-3 py-3.5 min-w-16">Δ%</th>
                  </template>
                  <th class="text-right text-xs font-semibold text-mp-success px-4 py-3.5 min-w-28 border-l border-mp-border whitespace-nowrap">{{ yearA }}</th>
                  <th class="text-right text-xs font-semibold text-white px-4 py-3.5 min-w-28 whitespace-nowrap">{{ yearB }}</th>
                  <th v-if="showPctChange" class="text-right text-xs font-semibold text-white px-4 py-3.5 min-w-16">Δ%</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="row in filteredRows" :key="row.key">

                  <!-- Section header row -->
                  <tr :class="[
                    'border-t transition-colors',
                    row.is_computed
                      ? 'bg-mp-teal-subtle/20 border-mp-teal/30 hover:bg-mp-teal-subtle/30'
                      : 'border-mp-border bg-mp-card-hover/20 hover:bg-mp-card-hover/40 cursor-pointer select-none'
                  ]"
                    @click="!row.is_computed && toggleSection(row.key)">

                    <td class="sticky left-0 z-10 px-5 py-3"
                      :class="row.is_computed ? 'bg-mp-teal-subtle/40' : 'bg-mp-card-hover/40'">
                      <div class="flex items-center gap-2">
                        <span v-if="!row.is_computed && (row.line_item_labels?.length ?? 0) > 0"
                          class="text-white transition-transform duration-200 shrink-0"
                          :class="expandedSections.has(row.key) ? 'rotate-90' : ''">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                          </svg>
                        </span>
                        <span v-else class="w-3.5 h-3.5 shrink-0 inline-block"></span>
                        <span v-if="row.is_computed" class="text-xs bg-mp-teal-subtle/60 text-white px-1.5 py-0.5 rounded shrink-0">AUTO</span>
                        <span :class="row.is_computed ? 'text-white font-bold' : 'text-white font-bold'">{{ row.label }}</span>
                        <span v-if="!row.is_computed && (row.line_item_labels?.length ?? 0) > 0"
                          class="text-xs text-white font-normal">({{ row.line_item_labels.length }})</span>
                      </div>
                    </td>

                    <!-- Month pairs — section totals -->
                    <template v-for="monthNum in yoyMonths" :key="'mv'+monthNum">
                      <td class="px-3 py-3 text-right border-l border-mp-border/50">
                        <span :class="['font-bold tabular-nums', yoyGetVal(monthNum,'A',row.key) < 0 ? 'text-mp-danger' : row.is_computed ? 'text-white' : 'text-white']">
                          {{ formatNum(yoyGetVal(monthNum, 'A', row.key)) }}
                        </span>
                      </td>
                      <td class="px-3 py-3 text-right">
                        <span class="text-white tabular-nums font-semibold">{{ formatNum(yoyGetVal(monthNum, 'B', row.key)) }}</span>
                      </td>
                      <td v-if="showPctChange" class="px-3 py-3 text-right">
                        <template v-if="yoyGetVal(monthNum,'B',row.key) !== 0">
                          <span :class="changePctColor(row.key, yoyPct(monthNum,row.key))" class="text-xs font-semibold tabular-nums">
                            {{ yoyPct(monthNum,row.key) >= 0 ? '+' : '' }}{{ yoyPct(monthNum,row.key).toFixed(1) }}%
                          </span>
                        </template>
                        <span v-else class="text-white text-xs">—</span>
                      </td>
                    </template>

                    <!-- YTD totals -->
                    <td class="px-4 py-3 text-right border-l border-mp-border">
                      <span :class="['font-bold tabular-nums', ytdVal('A',row.key) < 0 ? 'text-mp-danger' : row.is_computed ? 'text-mp-success' : 'text-mp-success']">
                        {{ formatNum(ytdVal('A', row.key)) }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <span class="text-white tabular-nums font-semibold">{{ formatNum(ytdVal('B', row.key)) }}</span>
                    </td>
                    <td v-if="showPctChange" class="px-4 py-3 text-right">
                      <template v-if="ytdVal('B',row.key) !== 0">
                        <span :class="changePctColor(row.key, ytdPct(row.key))" class="text-xs font-semibold tabular-nums">
                          {{ ytdPct(row.key) >= 0 ? '+' : '' }}{{ ytdPct(row.key).toFixed(1) }}%
                        </span>
                      </template>
                      <span v-else class="text-white text-xs">—</span>
                    </td>
                  </tr>

                  <!-- Sub-rows for YoY mode -->
                  <template v-if="!row.is_computed && expandedSections.has(row.key)">
                    <tr v-for="label in row.line_item_labels" :key="label"
                      class="border-t border-mp-border/60 hover:bg-mp-card-hover/20 transition-colors">

                      <td class="sticky left-0 z-10 bg-mp-card px-5 py-2">
                        <div class="flex items-center gap-2 pl-6">
                          <span class="w-1 h-1 rounded-full bg-mp-muted shrink-0"></span>
                          <span class="text-white text-xs font-medium">{{ label }}</span>
                        </div>
                      </td>

                      <template v-for="monthNum in yoyMonths" :key="'sli'+monthNum">
                        <td class="px-3 py-2 text-right border-l border-mp-border/30">
                          <span :class="['tabular-nums text-xs', yoyGetLineItemVal(monthNum,'A',row.key,label) < 0 ? 'text-mp-danger' : 'text-white']">
                            {{ yoyGetLineItemVal(monthNum,'A',row.key,label) !== 0 ? formatNum(yoyGetLineItemVal(monthNum,'A',row.key,label)) : '—' }}
                          </span>
                        </td>
                        <td class="px-3 py-2 text-right">
                          <span class="text-white tabular-nums text-xs">
                            {{ yoyGetLineItemVal(monthNum,'B',row.key,label) !== 0 ? formatNum(yoyGetLineItemVal(monthNum,'B',row.key,label)) : '—' }}
                          </span>
                        </td>
                        <td v-if="showPctChange" class="px-3 py-2 text-right">
                          <template v-if="yoyGetLineItemVal(monthNum,'B',row.key,label) !== 0">
                            <span :class="changePctColor(row.key, pctChange(yoyGetLineItemVal(monthNum,'B',row.key,label), yoyGetLineItemVal(monthNum,'A',row.key,label)))" class="text-xs tabular-nums">
                              {{ pctChange(yoyGetLineItemVal(monthNum,'B',row.key,label), yoyGetLineItemVal(monthNum,'A',row.key,label)).toFixed(1) }}%
                            </span>
                          </template>
                          <span v-else class="text-white text-xs">—</span>
                        </td>
                      </template>

                      <!-- YTD sub-row -->
                      <td class="px-4 py-2 text-right border-l border-mp-border">
                        <span :class="['tabular-nums text-xs', ytdLineItemVal('A',row.key,label) < 0 ? 'text-mp-danger' : 'text-white']">
                          {{ ytdLineItemVal('A',row.key,label) !== 0 ? formatNum(ytdLineItemVal('A',row.key,label)) : '—' }}
                        </span>
                      </td>
                      <td class="px-4 py-2 text-right">
                        <span class="text-white tabular-nums text-xs">
                          {{ ytdLineItemVal('B',row.key,label) !== 0 ? formatNum(ytdLineItemVal('B',row.key,label)) : '—' }}
                        </span>
                      </td>
                      <td v-if="showPctChange" class="px-4 py-2 text-right">
                        <template v-if="ytdLineItemVal('B',row.key,label) !== 0">
                          <span :class="changePctColor(row.key, pctChange(ytdLineItemVal('B',row.key,label), ytdLineItemVal('A',row.key,label)))" class="text-xs tabular-nums">
                            {{ pctChange(ytdLineItemVal('B',row.key,label), ytdLineItemVal('A',row.key,label)).toFixed(1) }}%
                          </span>
                        </template>
                        <span v-else class="text-white text-xs">—</span>
                      </td>
                    </tr>
                  </template>

                </template>
              </tbody>
            </table>
          </div>

          <!-- Legend -->
          <div class="mt-4 flex flex-wrap items-center gap-5 text-xs text-white">
            <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-sm bg-mp-teal"></div><span class="text-white font-medium">{{ yearA }}</span><span>(current)</span></div>
            <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-sm bg-mp-muted"></div><span>{{ yearB }} (comparison)</span></div>
            <div v-if="showPctChange" class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-sm bg-mp-gold-dark"></div><span>% Change</span></div>
            <div class="flex items-center gap-1.5"><span class="text-mp-success">▲ green</span> = improvement &nbsp; <span class="text-mp-danger">▼ red</span> = decline</div>
          </div>
        </template>

        <!-- ── CUSTOM MODE (with selected periods) ── -->
        <template v-else-if="currentMode === 'custom' && customColumns.length > 0 && activeTab !== 'ratios'">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-white">
              Comparing <span class="text-white font-medium">{{ customColumns.length }}</span> selected period(s)
            </p>
            <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
              class="text-sm text-white hover:text-white font-medium inline-flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
              </svg>
              Change periods
            </Link>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border overflow-x-auto">
            <table class="text-sm" style="min-width: max-content; width: 100%;">
              <thead>
                <tr class="border-b border-mp-border bg-mp-card-hover/60">
                  <th class="sticky left-0 z-10 bg-mp-card-hover text-left text-xs font-semibold text-white uppercase tracking-widest px-5 py-3.5 min-w-56">
                    Description
                  </th>
                  <th v-for="col in customColumns" :key="col.id || col.label"
                    class="text-right text-xs font-semibold text-white uppercase tracking-widest px-4 py-3.5 min-w-36 whitespace-nowrap">
                    {{ col.label }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <template v-for="row in filteredRows" :key="row.key">

                  <tr :class="[
                    'border-t transition-colors cursor-pointer select-none',
                    row.is_computed
                      ? 'bg-mp-teal-subtle/20 border-mp-teal/30 hover:bg-mp-teal-subtle/40'
                      : 'border-mp-border hover:bg-mp-card-hover/40 bg-mp-card-hover/20'
                  ]"
                    @click="!row.is_computed && toggleSection(row.key)">

                    <td class="sticky left-0 z-10 px-5 py-3"
                      :class="row.is_computed ? 'bg-mp-teal-subtle/40' : 'bg-mp-card-hover/40'">
                      <div class="flex items-center gap-2">
                        <span v-if="!row.is_computed && (row.line_item_labels?.length ?? 0) > 0"
                          class="text-white transition-transform duration-200 shrink-0"
                          :class="expandedSections.has(row.key) ? 'rotate-90' : ''">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                          </svg>
                        </span>
                        <span v-else class="w-3.5 h-3.5 shrink-0 inline-block"></span>
                        <span v-if="row.is_computed" class="text-xs bg-mp-teal-subtle/60 text-white px-1.5 py-0.5 rounded shrink-0">AUTO</span>
                        <span :class="row.is_computed ? 'text-white font-bold' : 'text-white font-bold'">
                          {{ row.label }}
                        </span>
                        <span v-if="!row.is_computed && (row.line_item_labels?.length ?? 0) > 0"
                          class="text-xs text-white font-normal">
                          ({{ row.line_item_labels.length }})
                        </span>
                      </div>
                    </td>

                    <td v-for="col in customColumns" :key="col.id || col.label"
                      class="px-4 py-3 text-right">
                      <div :class="[
                        'font-bold tabular-nums',
                        getVal(col, row.key) < 0 ? 'text-mp-danger' :
                        row.is_computed ? 'text-white' : 'text-white'
                      ]">
                        {{ formatNum(getVal(col, row.key)) }}
                      </div>
                      <div v-if="showCommonSize && getCommonBase(col, row.statement_type) !== 0"
                        class="text-xs text-white mt-0.5 tabular-nums">
                        {{ commonSizePct(col, row) }}%
                      </div>
                    </td>
                  </tr>

                  <template v-if="!row.is_computed && expandedSections.has(row.key)">
                    <tr v-for="label in row.line_item_labels" :key="label"
                      class="border-t border-mp-border/60 hover:bg-mp-card-hover/20 transition-colors">
                      <td class="sticky left-0 z-10 bg-mp-card px-5 py-2.5">
                        <div class="flex items-center gap-2 pl-6">
                          <span class="w-1 h-1 rounded-full bg-mp-muted shrink-0"></span>
                          <span class="text-white text-xs font-medium">{{ label }}</span>
                        </div>
                      </td>
                      <td v-for="col in customColumns" :key="col.id || col.label"
                        class="px-4 py-2.5 text-right">
                        <span :class="[
                          'tabular-nums text-xs',
                          getLineItemVal(col, row.key, label) < 0 ? 'text-mp-danger' :
                          getLineItemVal(col, row.key, label) === 0 ? 'text-white' : 'text-white'
                        ]">
                          {{ getLineItemVal(col, row.key, label) !== 0
                              ? formatNum(getLineItemVal(col, row.key, label))
                              : '—' }}
                        </span>
                      </td>
                    </tr>
                  </template>

                </template>
              </tbody>
            </table>
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-5 text-xs text-white">
            <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-sm bg-mp-gold-dark/60"></div><span>Custom periods</span></div>
            <div class="flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
              </svg>
              <span>Click a section row to expand/collapse line items</span>
            </div>
            <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
              class="text-white hover:text-white font-medium">
              ← Back to Statements
            </Link>
          </div>
        </template>

        <!-- ── RATIO ANALYSIS TAB (monthly + custom modes) ── -->
        <template v-else-if="activeTab === 'ratios' && (currentMode === 'monthly' || currentMode === 'custom')">

          <!-- Info banner -->
          <div class="mb-5 bg-mp-teal-subtle/20 border border-mp-teal/30 rounded-xl px-5 py-3 flex items-center gap-3">
            <span class="text-white text-lg">📐</span>
            <div>
              <p class="text-white text-sm font-medium">Ratio Analysis — Computed from Statement Totals</p>
              <p class="text-white text-xs mt-0.5">Ratios are derived from the P&L and Balance Sheet figures in each period column. Color thresholds are indicative benchmarks.</p>
            </div>
          </div>

          <!-- One card per ratio group -->
          <div v-for="group in ratioGroups" :key="group.key" class="mb-5">
            <div class="bg-mp-card rounded-xl border border-mp-border overflow-x-auto">
              <table class="text-sm" style="min-width: max-content; width: 100%;">
                <thead>
                  <tr :class="['border-b', groupColorClass(group.color, 'header')]">
                    <!-- Group label as sticky header cell -->
                    <th class="sticky left-0 z-10 text-left px-5 py-3 min-w-56"
                      :class="groupColorClass(group.color, 'header')">
                      <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-widest"
                          :class="groupColorClass(group.color, 'label')">
                          {{ group.label }}
                        </span>
                      </div>
                    </th>
                    <th v-for="col in ratioColumns" :key="col.label ?? col.id"
                      :class="[
                        'text-right text-xs font-semibold uppercase tracking-widest px-4 py-3 min-w-32 whitespace-nowrap',
                        col.col_type === 'quarter' ? 'text-white bg-mp-gold/20 border-l border-mp-gold/40' :
                        col.col_type === 'ytd'     ? 'text-mp-success bg-mp-success/20 border-l border-mp-success/40' :
                                                     groupColorClass(group.color, 'label')
                      ]">
                      {{ col.label }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="ratio in group.ratios" :key="ratio.key"
                    class="border-t border-mp-border/60 hover:bg-mp-card-hover/20 transition-colors">

                    <!-- Ratio label -->
                    <td class="sticky left-0 z-10 bg-mp-card px-5 py-3">
                      <div class="flex items-center gap-2.5">
                        <span class="text-xs px-1.5 py-0.5 rounded font-mono"
                          :class="groupColorClass(group.color, 'badge')">
                          {{ ratio.format === 'pct' ? '%' : ratio.format === 'x' ? '×' : ratio.format === 'days' ? 'd' : '#' }}
                        </span>
                        <span class="text-white font-medium text-sm">{{ ratio.label }}</span>
                        <span class="text-white text-xs">({{ ratio.higherBetter ? '↑ better' : '↓ better' }})</span>
                      </div>
                    </td>

                    <!-- Value per column -->
                    <td v-for="col in ratioColumns" :key="col.label ?? col.id"
                      :class="[
                        'px-4 py-3 text-right',
                        col.col_type === 'quarter' ? 'bg-mp-gold/10 border-l border-mp-gold/30' :
                        col.col_type === 'ytd'     ? 'bg-mp-success/10 border-l border-mp-success/30' : ''
                      ]">
                      <span :class="[
                        'tabular-nums font-semibold text-sm',
                        ratioColor(calcRatio(ratio.fn, col), ratio.higherBetter, ratio.format)
                      ]">
                        {{ formatRatioVal(calcRatio(ratio.fn, col), ratio.format) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Legend -->
          <div class="mt-3 flex flex-wrap items-center gap-5 text-xs text-white">
            <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-mp-success/60 inline-block"></span><span>Strong</span></div>
            <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-mp-warning/60 inline-block"></span><span>Moderate</span></div>
            <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-mp-danger/60 inline-block"></span><span>Weak</span></div>
            <div class="flex items-center gap-1.5"><span class="text-white">—</span><span>Insufficient data for this period</span></div>
            <div class="ml-auto text-white italic">Ratios requiring Balance Sheet data (liquidity, leverage) will show — for periods without BS entries.</div>
          </div>

        </template>

        <!-- ── RATIO TAB — YoY mode not supported ── -->
        <template v-else-if="activeTab === 'ratios' && currentMode === 'yoy'">
          <div class="bg-mp-card rounded-xl border border-mp-border p-12 text-center">
            <div class="w-14 h-14 bg-mp-teal-subtle/50 rounded-xl flex items-center justify-center mx-auto mb-4">
              <span class="text-3xl">📐</span>
            </div>
            <p class="text-white font-medium mb-1">Ratio Analysis</p>
            <p class="text-white text-sm">Switch to <strong class="text-white">Monthly</strong> or <strong class="text-white">Custom</strong> mode to view ratio trends across periods.</p>
          </div>
        </template>

        <!-- ── CUSTOM MODE (no periods selected — empty state) ── -->
        <template v-else-if="currentMode === 'custom'">
          <div class="bg-mp-card rounded-xl border border-mp-border p-12 text-center">
            <div class="w-14 h-14 bg-mp-gold/50 rounded-xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
              </svg>
            </div>
            <p class="text-white font-medium mb-1">Custom Period Comparison</p>
            <p class="text-white text-sm mb-5">Go back to the statements list, tick the checkboxes on the periods you want, then click "Compare".</p>
            <Link :href="`/portfolio-companies/${company.id}/financial-statements`"
              class="bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors inline-block">
              ← Back to Statements
            </Link>
          </div>
        </template>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:        Object,
  mode:           String,
  rows:           Array,
  columns:        { type: Array, default: () => [] },
  columnsA:       { type: Array, default: () => [] },
  columnsB:       { type: Array, default: () => [] },
  yearA:          Number,
  yearB:          Number,
  availableYears: { type: Array, default: () => [] },
})

// ── State ──
const currentMode     = ref(props.mode ?? 'monthly')
const selectedYearA   = ref(props.yearA ?? props.availableYears?.[props.availableYears.length - 1])
const selectedYearB   = ref(props.yearB ?? props.availableYears?.[props.availableYears.length - 2] ?? props.availableYears?.[0])
const activeTab       = ref('income')
const showCommonSize  = ref(false)
const showPctChange   = ref(true)

// Sections start collapsed (only computed/total rows visible by default)
const expandedSections = ref(new Set())

const modes = [
  { key: 'monthly', label: '📅 Monthly' },
  { key: 'yoy',     label: '🔁 Year vs Year' },
  { key: 'custom',  label: '⚙️ Custom' },
]
const statementTabs = [
  { key: 'income',        label: 'Income Statement', icon: '📊' },
  { key: 'balance_sheet', label: 'Balance Sheet',    icon: '⚖️' },
  { key: 'cashflow',      label: 'Cash Flow',        icon: '💧' },
  { key: 'ratios',        label: 'Ratio Analysis',   icon: '📐' },
]

const filteredRows = computed(() => {
  if (activeTab.value === 'ratios') return []  // ratios tab uses its own template
  return (props.rows ?? []).filter(r => r.statement_type === activeTab.value)
})

// In custom mode, columns to display (from compare?mode=custom&ids[]=...)
const customColumns = computed(() => props.columnsA?.length ? props.columnsA : (props.columns ?? []))

// ── Expand / Collapse ──
function toggleSection(key) {
  const s = new Set(expandedSections.value)
  s.has(key) ? s.delete(key) : s.add(key)
  expandedSections.value = s
}

const allExpanded = computed(() => {
  const expandable = filteredRows.value.filter(r => !r.is_computed && (r.line_item_labels?.length ?? 0) > 0)
  return expandable.length > 0 && expandable.every(r => expandedSections.value.has(r.key))
})

function toggleAllSections() {
  const expandable = filteredRows.value.filter(r => !r.is_computed && (r.line_item_labels?.length ?? 0) > 0)
  if (allExpanded.value) {
    expandedSections.value = new Set()
  } else {
    expandedSections.value = new Set(expandable.map(r => r.key))
  }
}

// Reset expanded state when changing tabs
function setTab(key) {
  activeTab.value = key
  expandedSections.value = new Set()
}

// ── Navigation ──
function switchMode(m) {
  currentMode.value = m
  expandedSections.value = new Set()
  if (m === 'custom') return
  reloadData()
}

function reloadData() {
  const params = new URLSearchParams({ mode: currentMode.value })
  if (selectedYearA.value) params.set('year_a', selectedYearA.value)
  if (currentMode.value === 'yoy' && selectedYearB.value) params.set('year_b', selectedYearB.value)
  router.visit(
    `/portfolio-companies/${props.company.id}/financial-statements/compare?${params.toString()}`,
    { preserveState: false, preserveScroll: true }
  )
}

// ── Monthly value helpers ──
function getVal(col, key) {
  return col?.totals?.[key] ?? 0
}

function getLineItemVal(col, sectionKey, label) {
  return col?.line_items?.[sectionKey]?.[label] ?? 0
}

function getCommonBase(col, type) {
  if (type === 'income')        return col?.totals?.['sales_revenue'] ?? 0
  if (type === 'balance_sheet') return col?.totals?.['total_assets']  ?? 0
  return 0
}

function commonSizePct(col, row) {
  const base = getCommonBase(col, row.statement_type)
  if (base === 0) return '—'
  return ((getVal(col, row.key) / base) * 100).toFixed(1)
}

// ── YoY helpers ──
const yoyMapA = computed(() => {
  const m = {}
  ;(props.columnsA ?? []).filter(c => c.col_type === 'month').forEach(c => { m[c.month_num] = c })
  return m
})
const yoyMapB = computed(() => {
  const m = {}
  ;(props.columnsB ?? []).filter(c => c.col_type === 'month').forEach(c => { m[c.month_num] = c })
  return m
})
const ytdColA = computed(() => (props.columnsA ?? []).find(c => c.col_type === 'ytd'))
const ytdColB = computed(() => (props.columnsB ?? []).find(c => c.col_type === 'ytd'))

const yoyMonths = computed(() => {
  const nums = new Set([
    ...Object.keys(yoyMapA.value).map(Number),
    ...Object.keys(yoyMapB.value).map(Number),
  ])
  return [...nums].sort((a, b) => a - b)
})

function yoyGetVal(monthNum, year, key) {
  const map = year === 'A' ? yoyMapA.value : yoyMapB.value
  return map[monthNum]?.totals?.[key] ?? 0
}

function yoyGetLineItemVal(monthNum, year, sectionKey, label) {
  const map = year === 'A' ? yoyMapA.value : yoyMapB.value
  return map[monthNum]?.line_items?.[sectionKey]?.[label] ?? 0
}

function yoyPct(monthNum, row) {
  return pctChange(yoyGetVal(monthNum, 'B', row.key), yoyGetVal(monthNum, 'A', row.key))
}

function ytdVal(year, key) {
  const col = year === 'A' ? ytdColA.value : ytdColB.value
  return col?.totals?.[key] ?? 0
}

function ytdLineItemVal(year, sectionKey, label) {
  const col = year === 'A' ? ytdColA.value : ytdColB.value
  return col?.line_items?.[sectionKey]?.[label] ?? 0
}

function ytdPct(row) {
  return pctChange(ytdVal('B', row.key), ytdVal('A', row.key))
}

// Expense-aware color logic
const expenseKeys = ['cogs','marketing_expenses','ga_expenses','depreciation','taxes',
                     'current_liabilities','non_current_liabilities','total_liabilities']

function changePctColor(key, val) {
  const isExpense = expenseKeys.includes(key)
  const good = isExpense ? val <= 0 : val >= 0
  return good ? 'text-mp-success' : 'text-mp-danger'
}

// KPI summary cards
const yoySummaryKpis = computed(() => {
  return [
    { key: 'sales_revenue', label: 'Revenue' },
    { key: 'gross_profit',  label: 'Gross Profit' },
    { key: 'ebitda',        label: 'EBITDA' },
    { key: 'net_profit',    label: 'Net Profit' },
  ].map(k => {
    const va = ytdVal('A', k.key)
    const vb = ytdVal('B', k.key)
    const p  = pctChange(vb, va)
    return { label: k.label + ' YTD', valueA: formatNum(va), valueB: vb ? formatNum(vb) : '—', pct: Math.abs(p).toFixed(1), positive: p >= 0 }
  }).filter(k => k.valueA !== '—')
})


// ── Ratio Analysis ──
const ratioGroups = [
  {
    key: 'profitability',
    label: 'Profitability',
    color: 'blue',
    ratios: [
      { key: 'gross_margin',    label: 'Gross Margin',         format: 'pct',  higherBetter: true,
        fn: t => safeDiv(t.gross_profit, t.sales_revenue) * 100 },
      { key: 'ebitda_margin',   label: 'EBITDA Margin',        format: 'pct',  higherBetter: true,
        fn: t => safeDiv(t.ebitda, t.sales_revenue) * 100 },
      { key: 'ebit_margin',     label: 'EBIT Margin',          format: 'pct',  higherBetter: true,
        fn: t => safeDiv(t.ebit, t.sales_revenue) * 100 },
      { key: 'net_margin',      label: 'Net Profit Margin',    format: 'pct',  higherBetter: true,
        fn: t => safeDiv(t.net_profit, t.sales_revenue) * 100 },
      { key: 'roa',             label: 'Return on Assets',     format: 'pct',  higherBetter: true,
        fn: t => safeDiv(t.net_profit, t.total_assets) * 100 },
      { key: 'roe',             label: 'Return on Equity',     format: 'pct',  higherBetter: true,
        fn: t => safeDiv(t.net_profit, t.equity) * 100 },
    ],
  },
  {
    key: 'liquidity',
    label: 'Liquidity',
    color: 'emerald',
    ratios: [
      { key: 'current_ratio',   label: 'Current Ratio',        format: 'x',    higherBetter: true,
        fn: t => safeDiv(t.current_assets, t.current_liabilities) },
      { key: 'quick_ratio',     label: 'Quick Ratio',          format: 'x',    higherBetter: true,
        fn: t => safeDiv((t.current_assets ?? 0) - (t.inventory ?? 0), t.current_liabilities) },
      { key: 'cash_ratio',      label: 'Cash Ratio',           format: 'x',    higherBetter: true,
        fn: t => safeDiv(t.cash ?? 0, t.current_liabilities) },
      { key: 'working_capital', label: 'Working Capital',      format: 'num',  higherBetter: true,
        fn: t => (t.current_assets ?? 0) - (t.current_liabilities ?? 0) },
    ],
  },
  {
    key: 'leverage',
    label: 'Leverage',
    color: 'orange',
    ratios: [
      { key: 'debt_to_equity',  label: 'Debt to Equity',       format: 'x',    higherBetter: false,
        fn: t => safeDiv(t.total_liabilities, t.equity) },
      { key: 'debt_to_assets',  label: 'Debt to Assets',       format: 'x',    higherBetter: false,
        fn: t => safeDiv(t.total_liabilities, t.total_assets) },
      { key: 'equity_ratio',    label: 'Equity Ratio',         format: 'pct',  higherBetter: true,
        fn: t => safeDiv(t.equity, t.total_assets) * 100 },
      { key: 'interest_cov',    label: 'Interest Coverage',    format: 'x',    higherBetter: true,
        fn: t => safeDiv(t.ebit, Math.abs(t.finance_income_expense ?? 0)) },
      { key: 'net_debt',        label: 'Net Debt',             format: 'num',  higherBetter: false,
        fn: t => (t.total_liabilities ?? 0) - (t.current_assets ?? 0) },
    ],
  },
  {
    key: 'activity',
    label: 'Activity & Efficiency',
    color: 'purple',
    ratios: [
      { key: 'asset_turnover',  label: 'Asset Turnover',       format: 'x',    higherBetter: true,
        fn: t => safeDiv(t.sales_revenue, t.total_assets) },
      { key: 'receivables_turn',label: 'Receivables Turnover', format: 'x',    higherBetter: true,
        fn: t => safeDiv(t.sales_revenue, t.accounts_receivable ?? (t.current_assets ?? 0) * 0.4) },
      { key: 'dso',             label: 'Days Sales Outstanding', format: 'days', higherBetter: false,
        fn: t => safeDiv((t.accounts_receivable ?? (t.current_assets ?? 0) * 0.4), t.sales_revenue) * 30 },
      { key: 'cogs_revenue',    label: 'COGS / Revenue',       format: 'pct',  higherBetter: false,
        fn: t => safeDiv(t.cogs, t.sales_revenue) * 100 },
      { key: 'opex_revenue',    label: 'OpEx / Revenue',       format: 'pct',  higherBetter: false,
        fn: t => safeDiv((t.marketing_expenses ?? 0) + (t.ga_expenses ?? 0), t.sales_revenue) * 100 },
    ],
  },
]

function safeDiv(num, den) {
  if (!den || den === 0) return null
  return num / den
}

function calcRatio(fn, col) {
  if (!col?.totals) return null
  return fn(col.totals)
}

function formatRatioVal(val, fmt) {
  if (val === null || val === undefined || isNaN(val) || !isFinite(val)) return '—'
  if (fmt === 'pct')  return val.toFixed(1) + '%'
  if (fmt === 'x')    return val.toFixed(2) + 'x'
  if (fmt === 'days') return Math.round(val) + 'd'
  if (fmt === 'num')  return formatNum(val)
  return val.toFixed(2)
}

function ratioColor(val, higherBetter, fmt) {
  if (val === null || val === undefined || isNaN(val) || !isFinite(val)) return 'text-white'
  const n = parseFloat(val)
  // For percentage/x ratios use thresholds; for raw numbers just neutral
  if (fmt === 'num') return 'text-white'
  const good = higherBetter ? n >= 0 : n <= 0
  if (fmt === 'pct') {
    if (higherBetter) return n >= 15 ? 'text-mp-success' : n >= 5 ? 'text-mp-warning' : 'text-mp-danger'
    return n <= 30 ? 'text-mp-success' : n <= 50 ? 'text-mp-warning' : 'text-mp-danger'
  }
  if (fmt === 'x') {
    if (higherBetter) return n >= 1.5 ? 'text-mp-success' : n >= 0.8 ? 'text-mp-warning' : 'text-mp-danger'
    return n <= 1 ? 'text-mp-success' : n <= 2 ? 'text-mp-warning' : 'text-mp-danger'
  }
  if (fmt === 'days') return n <= 45 ? 'text-mp-success' : n <= 90 ? 'text-mp-warning' : 'text-mp-danger'
  return 'text-white'
}

function groupColorClass(color, type) {
  const map = {
    blue:    { header: 'bg-mp-teal-subtle/30 border-mp-teal/40',   badge: 'bg-mp-teal-subtle/60 text-white',    label: 'text-white'    },
    emerald: { header: 'bg-mp-success/30 border-mp-success/40', badge: 'bg-mp-success/60 text-mp-success', label: 'text-mp-success' },
    orange:  { header: 'bg-mp-warning/30 border-mp-warning/40', badge: 'bg-mp-warning/60 text-mp-warning',  label: 'text-mp-warning'  },
    purple:  { header: 'bg-mp-gold/30 border-mp-gold/40', badge: 'bg-mp-gold/60 text-white',  label: 'text-white'  },
  }
  return map[color]?.[type] ?? ''
}

// Which columns to use for ratio tab (same logic as monthly/custom table)
const ratioColumns = computed(() => {
  if (currentMode.value === 'custom') return customColumns.value
  return props.columnsA ?? []
})

// ── Utilities ──
function pctChange(base, curr) {
  if (base === 0) return 0
  return ((curr - base) / Math.abs(base)) * 100
}

function monthName(num) {
  return new Date(2000, num - 1, 1).toLocaleDateString('en-US', { month: 'short' })
}

function formatNum(val) {
  if (val === null || val === undefined || val === 0) return '—'
  return parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}
</script>