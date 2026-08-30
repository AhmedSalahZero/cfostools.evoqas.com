<template>
  <Head title="Expense Dashboard" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-mp-text-secondary">

      <!-- HEADER -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <Link :href="`/portfolio-companies/${company.id}`"
                class="flex items-center gap-2 text-sm text-mp-muted hover:text-mp-text-secondary transition-colors mb-2 w-fit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to {{ company.name }}
              </Link>
              <h1 class="text-2xl font-bold text-mp-text-secondary">Expense Dashboard</h1>
              <p class="text-mp-muted text-sm mt-1">{{ company.name }}</p>
            </div>
            <div class="flex gap-2">
              <span class="px-4 py-2 rounded-lg text-sm bg-mp-teal text-mp-text-secondary font-medium">Dashboard</span>
              <a :href="`/companies/${company.id}/expenses/upload`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-mp-text text-sm font-medium px-4 py-2 rounded-lg transition-colors"> ↑ Upload</a>
              <a :href="`/companies/${company.id}/expenses/reports`"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-mp-text text-sm font-medium px-4 py-2 rounded-lg transition-colors">Full Reports</a>
              <a :href="`/companies/${company.id}/expenses/breakeven`"
                class="px-4 py-2 rounded-lg text-sm bg-mp-gold hover:bg-mp-gold-dark text-mp-text-secondary font-medium transition-colors">
                📊 Breakeven
              </a>
              <a :href="`/companies/${company.id}/profitability`"
                class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm bg-mp-success hover:bg-mp-success text-mp-text-secondary font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                Profitability
              </a>
              <a :href="`/companies/${company.id}/sales`"
                class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm bg-mp-teal hover:bg-mp-teal-dark text-mp-text-secondary font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                Sales Dashboard
              </a>
              <a :href="`/portfolio-companies/${company.id}/financial-statements`"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-mp-text-secondary text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Financial Statements
              </a>
            </div>
          </div>

          <!-- Date Range -->
          <div class="flex items-center gap-4 mt-5">
            <div class="flex items-center gap-3 bg-mp-card-hover border border-mp-border rounded-xl px-4 py-2.5">
              <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              <input type="date" v-model="dateFrom" :min="minDate" :max="maxDate"
                class="bg-transparent text-mp-text-secondary text-sm focus:outline-none"/>
              <span class="text-mp-muted">→</span>
              <input type="date" v-model="dateTo" :min="minDate" :max="maxDate"
                class="bg-transparent text-mp-text-secondary text-sm focus:outline-none"/>
              <button @click="loadData"
                class="ml-2 bg-mp-teal hover:bg-mp-teal-dark text-mp-text-secondary text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                Apply
              </button>
            </div>
            <div v-if="loading" class="flex items-center gap-2 text-mp-muted text-sm">
              <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              Loading...
            </div>
          </div>
        </div>
      </div>

      <div v-if="!loading" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- ── SECTION 1: KPI CARDS ── -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-white uppercase tracking-widest mb-1">Total Expenses</p>
            <p class="text-lg font-bold text-mp-text-secondary">{{ fmt(kpis.total_expense) }}</p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-white uppercase tracking-widest mb-1">Total Revenue</p>
            <p class="text-lg font-bold text-mp-success">{{ fmt(kpis.total_revenue) }}</p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-white uppercase tracking-widest mb-1">Expense / Revenue</p>
            <p class="text-lg font-bold" :class="kpis.expense_to_rev > 80 ? 'text-mp-danger' : kpis.expense_to_rev > 60 ? 'text-mp-warning' : 'text-mp-success'">
              {{ kpis.expense_to_rev }}%
            </p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-white uppercase tracking-widest mb-1">Avg Monthly</p>
            <p class="text-lg font-bold text-mp-text-secondary">{{ fmt(kpis.avg_monthly) }}</p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-white uppercase tracking-widest mb-1">Categories</p>
            <p class="text-lg font-bold text-mp-text-secondary">{{ kpis.category_count }}</p>
          </div>
          <div class="bg-mp-card rounded-xl border border-mp-border p-4">
            <p class="text-xs text-white uppercase tracking-widest mb-1">Expense Items</p>
            <p class="text-lg font-bold text-mp-text-secondary">{{ kpis.item_count }}</p>
          </div>
        </div>

        <!-- ── SECTION 2: MONTHLY TREND LINE CHART (Chart.js — same as Sales Dashboard) ── -->
        <div class="bg-mp-card border border-mp-border rounded-xl p-6">
          <div class="flex items-center justify-between mb-4">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">Monthly Expense Trend</p>
            <div class="flex items-center gap-4 text-xs text-mp-muted">
              <span class="flex items-center gap-1.5"><span class="w-3 h-0.5 bg-mp-teal inline-block rounded"></span> Total Expense</span>
              <span class="flex items-center gap-1.5"><span class="w-3 h-0.5 bg-mp-success inline-block rounded" style="border-top: 2px dashed #10b981; background: transparent; display:inline-block"></span> MoM Growth %</span>
            </div>
          </div>
          <div style="height:280px">
            <canvas ref="trendChartCanvas"></canvas>
          </div>
        </div>

        <!-- ── SECTION 3: CATEGORY BREAKDOWN DONUT (Chart.js — same as Sales Dashboard) ── -->
        <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
          <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between">
            <p class="text-sm font-semibold text-mp-text-secondary">Category Breakdown</p>
            <div class="flex items-center bg-mp-card-hover rounded-lg p-0.5">
              <button @click="onBreakdownTabChange('chart')"
                :class="breakdownTab === 'chart' ? 'bg-mp-page text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text'"
                class="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-md transition-all">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
                </svg>
                Chart
              </button>
              <button @click="breakdownTab = 'table'"
                :class="breakdownTab === 'table' ? 'bg-mp-page text-mp-text-secondary' : 'text-mp-muted hover:text-mp-text'"
                class="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-md transition-all">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 4v16M6 4h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                </svg>
                Table
              </button>
            </div>
          </div>

          <!-- Chart -->
          <div v-if="breakdownTab === 'chart'" class="p-6">
            <DonutChart3D :data="categoryBreakdown" label-key="category" value-key="total" :height="300" />
          </div>

          <!-- Table -->
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase px-5 py-3">Category</th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-5 py-3">Total</th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-5 py-3">% Share</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <tr v-for="(cat, i) in categoryBreakdown" :key="cat.category" class="hover:bg-mp-card-hover/50 transition-colors">
                  <td class="px-5 py-2.5 text-mp-text-secondary">
                    <div class="flex items-center gap-2">
                      <div class="w-3 h-3 rounded-full flex-shrink-0" :style="`background:${PALETTE[i % PALETTE.length]}`"></div>
                      {{ cat.category }}
                    </div>
                  </td>
                  <td class="px-5 py-2.5 text-right text-mp-success font-semibold">{{ fmt(cat.total) }}</td>
                  <td class="px-5 py-2.5 text-right text-mp-muted">{{ cat.pct }}%</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="border-t-2 border-mp-border bg-mp-card-hover/50">
                  <td class="px-5 py-2.5 text-mp-text-secondary font-bold">Total</td>
                  <td class="px-5 py-2.5 text-right text-mp-text-secondary font-bold">{{ fmt(kpis.total_expense) }}</td>
                  <td class="px-5 py-2.5 text-right text-mp-muted">100%</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- ── SECTION 4: TOP EXPENSE ITEMS ── -->
        <div class="bg-mp-card rounded-xl border border-mp-border p-6">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Top 10 Expense Items</p>
          <div class="space-y-2">
            <div v-for="item in topItems" :key="item.item" class="flex items-center gap-4">
              <div class="w-40 text-xs text-mp-muted truncate text-right">
                <span class="text-mp-muted text-xs">{{ item.category }}</span><br/>
                <span class="text-mp-text-secondary">{{ item.item }}</span>
              </div>
              <div class="flex-1 bg-mp-card-hover rounded-full h-4 relative">
                <div class="bg-gradient-to-r from-mp-teal to-mp-teal-dark h-4 rounded-full"
                  :style="`width:${Math.max(item.pct, 1)}%`"></div>
              </div>
              <div class="w-28 text-right">
                <span class="text-xs text-mp-text">{{ fmt(item.total) }}</span>
                <span class="text-xs text-mp-muted ml-1">{{ item.pct }}%</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ── SECTION 5: MIN / AVG / MAX PER CATEGORY ── -->
        <div class="bg-mp-card rounded-xl border border-mp-border overflow-x-auto">
          <div class="px-6 py-4 border-b border-mp-border">
            <p class="text-xs font-semibold text-white uppercase tracking-widest">Min / Avg / Max per Category</p>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-mp-border">
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-3">Category</th>
                <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-4 py-3">Total</th>
                <th class="text-right text-xs font-semibold text-mp-success uppercase tracking-widest px-4 py-3">Min</th>
                <th class="text-right text-xs font-semibold text-white uppercase tracking-widest px-4 py-3">Avg</th>
                <th class="text-right text-xs font-semibold text-mp-danger uppercase tracking-widest px-4 py-3">Max</th>
                <th class="text-left text-xs font-semibold text-white uppercase tracking-widest px-4 py-3">Distribution</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
              <tr v-for="(s, i) in statsPerCategory" :key="s.category"
                :class="i % 2 === 0 ? '' : 'bg-mp-card-hover/30'"
                class="hover:bg-mp-teal-subtle/20 transition-colors">
                <td class="px-4 py-3 font-medium text-mp-text-secondary">{{ s.category }}</td>
                <td class="px-4 py-3 text-right text-mp-text">{{ fmt(s.total) }}</td>
                <td class="px-4 py-3 text-right text-mp-success">{{ fmt(s.min) }}</td>
                <td class="px-4 py-3 text-right text-white font-semibold">{{ fmt(s.avg) }}</td>
                <td class="px-4 py-3 text-right text-mp-danger">{{ fmt(s.max) }}</td>
                <td class="px-4 py-3">
                  <div class="relative w-full h-4 bg-mp-card-hover rounded-full overflow-hidden" style="min-width:120px">
                    <div class="absolute h-4 bg-mp-teal/40 rounded-full"
                      :style="`left:${minPct(s)}%;width:${rangePct(s)}%`"></div>
                    <div class="absolute w-1 h-4 bg-mp-teal"
                      :style="`left:${avgPct(s)}%`"></div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>


        <!-- ── SECTION 6: AUTO INSIGHTS ── -->
        <div v-if="insights.length > 0">
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Auto Insights & Alerts</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div v-for="(insight, i) in insights" :key="i"
              :class="{
                'bg-mp-success/30 border-mp-success/60':   insight.type === 'positive',
                'bg-mp-warning/30 border-mp-warning/60': insight.type === 'warning',
                'bg-mp-danger/30 border-mp-danger/60':       insight.type === 'danger',
              }"
              class="rounded-xl border p-4 flex gap-3">
              <span class="text-2xl flex-shrink-0 mt-0.5">{{ insight.icon }}</span>
              <div>
                <p class="text-sm font-semibold text-mp-text-secondary">{{ insight.title }}</p>
                <p class="text-xs text-mp-text mt-1 leading-relaxed">{{ insight.body }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ── SECTION 7: ANALYST NOTES ── -->
        <div>
          <p class="text-xs font-semibold text-white uppercase tracking-widest mb-4">
            Analyst Notes
            <span class="text-mp-muted normal-case font-normal ml-2">Saved per date range — {{ dateFrom }} → {{ dateTo }}</span>
          </p>
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <div v-if="notes.length > 0" class="divide-y divide-gray-800">
              <div v-for="(n, i) in notes" :key="n.id ?? i" class="p-5">
                <div class="flex items-center justify-between mb-3">
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-mp-teal flex items-center justify-center text-xs font-bold text-mp-text-secondary flex-shrink-0">
                      {{ (n.author ?? 'U').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <p class="text-sm font-semibold text-mp-text-secondary">{{ n.author }}</p>
                      <p class="text-xs text-mp-muted">{{ n.updated_at ? new Date(n.updated_at).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '' }}</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <button @click="startEdit(n)"
                      class="flex items-center gap-1 text-xs text-white hover:text-white bg-mp-teal-subtle/40 hover:bg-mp-teal-subtle/70 px-3 py-1.5 rounded-lg transition-colors">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                      Edit
                    </button>
                    <button @click="deleteNote(n.id)"
                      class="flex items-center gap-1 text-xs text-mp-danger hover:text-mp-danger bg-mp-danger/40 hover:bg-mp-danger/70 px-3 py-1.5 rounded-lg transition-colors">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      Delete
                    </button>
                  </div>
                </div>
                <div class="prose-dark text-sm text-mp-text leading-relaxed" v-html="n.note"></div>
              </div>
            </div>
            <div v-else class="px-6 py-4 text-xs text-mp-muted border-b border-mp-border">
              No notes saved for this date range yet. Write one below.
            </div>
            <!-- Rich Text Editor -->
            <div class="p-5">
              <p class="text-xs font-semibold text-mp-muted uppercase tracking-widest mb-3">
                {{ editingNoteId ? '✏️ Editing Note' : '+ New Note' }}
                <button v-if="editingNoteId" @click="cancelEdit" class="ml-3 text-mp-muted hover:text-mp-muted normal-case font-normal">Cancel</button>
              </p>
              <div class="flex flex-wrap items-center gap-1 bg-mp-card-hover border border-mp-border rounded-t-lg px-3 py-2">
                <div class="flex items-center gap-0.5 pr-2 border-r border-mp-border">
                  <button @click="editorCmd('bold')" class="w-7 h-7 rounded flex items-center justify-center text-sm font-bold text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page transition-colors">B</button>
                  <button @click="editorCmd('italic')" class="w-7 h-7 rounded flex items-center justify-center text-sm italic text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page transition-colors">I</button>
                  <button @click="editorCmd('underline')" class="w-7 h-7 rounded flex items-center justify-center text-sm underline text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page transition-colors">U</button>
                </div>
                <div class="flex items-center gap-0.5 px-2 border-r border-mp-border">
                  <button @click="editorCmd('h1')" class="px-2 h-7 rounded text-xs font-bold text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page transition-colors">H1</button>
                  <button @click="editorCmd('h2')" class="px-2 h-7 rounded text-xs font-bold text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page transition-colors">H2</button>
                </div>
                <div class="flex items-center gap-0.5 px-2 border-r border-mp-border">
                  <button @click="editorCmd('insertUnorderedList')" title="Bullet List" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                  </button>
                  <button @click="editorCmd('insertOrderedList')" title="Numbered List" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h10M7 16h10M3 8h.01M3 12h.01M3 16h.01"/></svg>
                  </button>
                </div>
                <div class="flex items-center gap-0.5 px-2 border-r border-mp-border">
                  <button @click="editorCmd('highlight', '#c9a84c')" class="w-5 h-5 rounded bg-mp-gold border border-mp-border hover:scale-110 transition-transform"></button>
                  <button @click="editorCmd('highlight', '#10b981')" class="w-5 h-5 rounded bg-mp-success border border-mp-border hover:scale-110 transition-transform"></button>
                  <button @click="editorCmd('highlight', '#ef4444')" class="w-5 h-5 rounded bg-mp-danger border border-mp-border hover:scale-110 transition-transform"></button>
                  <button @click="editorCmd('highlight', '#00b4c8')" class="w-5 h-5 rounded bg-mp-teal border border-mp-border hover:scale-110 transition-transform"></button>
                  <button @click="editorCmd('removeHighlight')" class="w-5 h-5 rounded bg-mp-muted border border-mp-border hover:scale-110 transition-transform text-mp-text-secondary text-xs flex items-center justify-center">✕</button>
                </div>
                <div class="flex items-center gap-0.5 pl-2">
                  <button @click="editorCmd('undo')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                  </button>
                  <button @click="editorCmd('redo')" class="w-7 h-7 rounded text-mp-muted hover:text-mp-text-secondary hover:bg-mp-page flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/></svg>
                  </button>
                </div>
              </div>
              <div id="exp-rich-editor" ref="editorEl" contenteditable="true"
                @input="onEditorInput"
                class="min-h-[180px] bg-mp-card-hover border border-t-0 border-mp-border rounded-b-lg px-5 py-4 text-mp-text-secondary text-sm leading-relaxed focus:outline-none"
                :data-placeholder="'Write your analysis, observations or action items for this period...'"></div>
              <div class="flex items-center justify-between mt-3">
                <p class="text-xs text-mp-muted">Rich text — supports bold, lists, highlights</p>
                <div class="flex items-center gap-3">
                  <span v-if="noteSaved" class="text-xs text-mp-success font-semibold">✓ Note saved</span>
                  <button @click="saveNote" :disabled="savingNote"
                    class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark disabled:opacity-50 text-mp-text-secondary text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    <svg v-if="savingNote" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    {{ savingNote ? 'Saving...' : (editingNoteId ? 'Update Note' : 'Save Note') }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import DonutChart3D from '@/Components/DonutChart3D.vue'
import axios from 'axios'

const props = defineProps({
  company:     Object,
  defaultFrom: String,
  defaultTo:   String,
  minDate:     String,
  maxDate:     String,
})

const dateFrom      = ref(props.defaultFrom)
const dateTo        = ref(props.defaultTo)
const loading       = ref(true)
const insights      = ref([])
const notes         = ref([])
const savingNote    = ref(false)
const noteSaved     = ref(false)
const editingNoteId = ref(null)
const editorEl      = ref(null)

const kpis              = ref({ total_expense: 0, total_revenue: 0, expense_to_rev: 0, category_count: 0, item_count: 0, avg_monthly: 0 })
const categoryBreakdown = ref([])
const monthlyTrend      = ref([])
const topItems          = ref([])
const statsPerCategory  = ref([])
const breakdownTab      = ref('chart')

// ── Canvas refs ──
const trendChartCanvas = ref(null)

// ── Chart.js instances ──
let Chart         = null
let trendChart    = null

function alpha(hex, a) {
  const r = parseInt(hex.slice(1,3),16)
  const g = parseInt(hex.slice(3,5),16)
  const b = parseInt(hex.slice(5,7),16)
  return `rgba(${r},${g},${b},${a})`
}

function compactNum(n) {
  if (n >= 1e9) return (n/1e9).toFixed(1) + 'B'
  if (n >= 1e6) return (n/1e6).toFixed(1) + 'M'
  if (n >= 1e3) return (n/1e3).toFixed(0) + 'K'
  return n.toFixed(0)
}

// ── Load Chart.js from CDN ──
async function loadChartJs() {
  if (Chart) return
  await new Promise((resolve, reject) => {
    if (window.Chart) { Chart = window.Chart; resolve(); return }
    const s = document.createElement('script')
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
    s.onload = () => { Chart = window.Chart; resolve() }
    s.onerror = reject
    document.head.appendChild(s)
  })
}

function destroyAllCharts() {
  if (trendChart) { trendChart.destroy(); trendChart = null }
}

// ── TREND CHART — IDENTICAL to Sales Dashboard renderTrendChart ──
function renderTrendChart(rows) {
  if (trendChart) { trendChart.destroy(); trendChart = null }
  const canvas = trendChartCanvas.value
  if (!canvas || !rows.length) return
  const ctx = canvas.getContext('2d')

  const labels = rows.map(r => r.period ?? r.month)
  const values = rows.map(r => r.value ?? r.expense)
  const growthRates = rows.map((r, i) => {
    if (i === 0) return null
    const curr = r.value ?? r.expense
    const prev = (rows[i-1].value ?? rows[i-1].expense)
    return prev > 0 ? parseFloat(((curr - prev)/prev*100).toFixed(1)) : null
  })

  trendChart = new Chart(ctx, {
    data: {
      labels,
      datasets: [
        {
          type: 'line',
          label: 'Total Expense',
          data: values,
          borderColor: '#00b4c8',
          backgroundColor: alpha('#00b4c8', 0.08),
          pointBackgroundColor: '#00b4c8',
          pointBorderColor: '#162d54',
          pointRadius: 4,
          pointHoverRadius: 6,
          fill: true,
          tension: 0.4,
          yAxisID: 'y',
        },
        {
          type: 'line',
          label: 'MoM Growth %',
          data: growthRates,
          borderColor: '#10b981',
          backgroundColor: 'transparent',
          pointBackgroundColor: '#10b981',
          pointBorderColor: '#0f7a90',
          pointRadius: 4,
          pointHoverRadius: 6,
          fill: false,
          tension: 0.4,
          borderDash: [5, 3],
          yAxisID: 'y2',
          spanGaps: true,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { color: '#ffffff', font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label: ctx => {
              if (ctx.datasetIndex === 0) return ' Total Expense: ' + Number(ctx.raw).toLocaleString('en-US', { maximumFractionDigits: 0 })
              return ctx.raw !== null ? ` MoM Growth: ${ctx.raw}%` : ''
            }
          }
        }
      },
      scales: {
        x:  { ticks: { color: '#ffffff', font: { size: 10 } }, grid: { color: '#112240' } },
        y:  { position: 'left',  ticks: { color: '#ffffff', font: { size: 10 }, callback: v => Number(v).toLocaleString('en-US', { notation: 'compact' }) }, grid: { color: '#112240' } },
        y2: { position: 'right', ticks: { color: '#10b981', font: { size: 10 }, callback: v => v + '%' }, grid: { drawOnChartArea: false } },
      }
    }
  })
}

// ── DONUT CHART — IDENTICAL to Sales Dashboard renderSingleBreakdownChart ──
// When user switches breakdown back to chart tab — <DonutChart3D>
// re-renders itself on mount, no manual re-render needed.
function onBreakdownTabChange(tab) {
  breakdownTab.value = tab
}

async function loadData() {
  loading.value = true
  destroyAllCharts()
  try {
    const res = await axios.get(`/companies/${props.company.id}/expenses/dashboard-data`, {
      params: { date_from: dateFrom.value, date_to: dateTo.value }
    })
    kpis.value              = res.data.kpis
    categoryBreakdown.value = res.data.category_breakdown
    monthlyTrend.value      = res.data.monthly_trend
    topItems.value          = res.data.top_items
    statsPerCategory.value  = res.data.stats_per_category

    loading.value = false
    await nextTick()
    await loadChartJs()
    await nextTick()
    // Same pattern as Sales Dashboard
    setTimeout(() => {
      renderTrendChart(res.data.monthly_trend || [])
    }, 100)
  } catch (e) {
    console.error(e)
    loading.value = false
  }
}

async function loadInsights() {
  try {
    const res = await axios.get(`/companies/${props.company.id}/expenses/insights`, {
      params: { date_from: dateFrom.value, date_to: dateTo.value }
    })
    insights.value = res.data.insights || []
  } catch(e) { console.error(e) }
}

async function loadNotes() {
  try {
    const res = await axios.get(`/companies/${props.company.id}/expenses/notes`, {
      params: { date_from: dateFrom.value, date_to: dateTo.value }
    })
    notes.value = res.data.notes || []
  } catch(e) { console.error(e) }
}

function startEdit(n) {
  editingNoteId.value = n.id
  nextTick(() => {
    if (editorEl.value) { editorEl.value.innerHTML = n.note; editorEl.value.focus() }
    setTimeout(() => editorEl.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 100)
  })
}

function cancelEdit() {
  editingNoteId.value = null
  if (editorEl.value) editorEl.value.innerHTML = ''
}

async function deleteNote(id) {
  if (!confirm('Delete this note?')) return
  try {
    await axios.delete(`/companies/${props.company.id}/expenses/notes/${id}`)
    await loadNotes()
  } catch(e) { console.error(e) }
}

async function saveNote() {
  const html = editorEl.value?.innerHTML?.trim()
  if (!html || html === '<br>') return
  savingNote.value = true; noteSaved.value = false
  try {
    if (editingNoteId.value) {
      await axios.put(`/companies/${props.company.id}/expenses/notes/${editingNoteId.value}`, { note: html })
    } else {
      await axios.post(`/companies/${props.company.id}/expenses/notes`, { date_from: dateFrom.value, date_to: dateTo.value, note: html })
    }
    noteSaved.value = true; editingNoteId.value = null
    if (editorEl.value) editorEl.value.innerHTML = ''
    await loadNotes()
    setTimeout(() => { noteSaved.value = false }, 3000)
  } catch(e) { console.error(e) } finally { savingNote.value = false }
}

function onEditorInput() {}

function editorCmd(cmd, value = null) {
  const el = editorEl.value; if (!el) return; el.focus()
  if (cmd === 'h1') { document.execCommand('formatBlock', false, 'h1') }
  else if (cmd === 'h2') { document.execCommand('formatBlock', false, 'h2') }
  else if (cmd === 'highlight') { document.execCommand('hiliteColor', false, value) }
  else if (cmd === 'removeHighlight') { document.execCommand('hiliteColor', false, 'transparent') }
  else { document.execCommand(cmd, false, value) }
}

// Override loadData to also reload insights and notes
const _origLoadData = loadData

onMounted(async () => {
  await loadData()
  await loadInsights()
  await loadNotes()
})

// ── MIN/AVG/MAX BAR HELPERS ──
function minPct(s) { return 0 }
function avgPct(s) {
  if (s.max === s.min) return 50
  return ((s.avg - s.min) / (s.max - s.min)) * 100
}
function rangePct(s) { return 100 }

function fmt(val) {
  if (!val && val !== 0) return '—'
  return Number(val).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}
</script>