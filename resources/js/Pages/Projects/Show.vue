<template>
  <Head :title="`${project.name} — Projects`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ══ HEADER ══ -->
      <div class="bg-mp-card border-b border-mp-border sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 min-w-0">
              <Link :href="`/portfolio-companies/${company.id}/projects`"
                class="text-white hover:text-white text-sm transition-colors flex-shrink-0">← Projects</Link>
              <span class="text-white">/</span>
              <div class="min-w-0">
                <div v-if="project.phase" class="text-xs text-white font-semibold uppercase tracking-widest">{{ project.phase }}</div>
                <h1 class="text-lg font-bold text-white truncate">{{ project.name }}</h1>
              </div>
              <span :class="statusBadgeClass(project.status)"
                class="text-xs font-semibold px-2.5 py-1 rounded-full uppercase tracking-wide flex-shrink-0">
                {{ statusLabel(project.status) }}
              </span>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <!-- List / Kanban toggle -->
              <div class="flex gap-1 bg-mp-card-hover rounded-lg p-1">
                <button @click="viewMode = 'list'"
                  :class="viewMode === 'list' ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
                  class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-md transition-all">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                  </svg>
                  List
                </button>
                <button @click="viewMode = 'kanban'"
                  :class="viewMode === 'kanban' ? 'bg-mp-teal text-white' : 'text-white hover:text-white'"
                  class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-md transition-all">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                  </svg>
                  Kanban
                </button>
              </div>
              <button @click="showAddTaskModal = true"
                class="flex items-center gap-1.5 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-semibold px-3 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Task
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- ══ KPI SUMMARY ROW ══ -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
          <div class="bg-mp-card border border-mp-border rounded-xl p-4 text-center">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-1">Tasks</p>
            <p class="text-2xl font-bold text-white">{{ project.tasks.length }}</p>
          </div>
          <div class="bg-mp-card border border-mp-border rounded-xl p-4 text-center">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-1">Done</p>
            <p class="text-2xl font-bold text-mp-success">{{ doneTasks }}</p>
          </div>
          <div class="bg-mp-card border border-mp-border rounded-xl p-4 text-center">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-1">Hours Logged</p>
            <p class="text-2xl font-bold text-white">{{ totalHours.toFixed(1) }}</p>
          </div>
          <div class="bg-mp-card border border-mp-border rounded-xl p-4 text-center">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-1">Internal Cost</p>
            <p class="text-2xl font-bold text-white">{{ fmtMoney(project.total_internal_cost) }}</p>
          </div>
          <div class="bg-mp-card border border-mp-border rounded-xl p-4 text-center">
            <p class="text-xs font-semibold text-white uppercase tracking-widest mb-1">Total Cost</p>
            <p class="text-2xl font-bold text-white">{{ fmtMoney(project.total_project_cost) }}</p>
          </div>
        </div>

        <!-- Overall progress bar -->
        <div class="bg-mp-card border border-mp-border rounded-xl px-5 py-4">
          <div class="flex items-center justify-between text-sm mb-2">
            <span class="text-white font-medium">Overall Progress</span>
            <span class="text-white font-bold">{{ overallProgress }}%</span>
          </div>
          <div class="h-2.5 bg-mp-card-hover rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500"
              :class="overallProgress >= 100 ? 'bg-mp-success' : 'bg-mp-teal'"
              :style="`width:${overallProgress}%`"></div>
          </div>
          <div class="flex items-center gap-6 mt-2 text-xs text-white">
            <span>📅 {{ fmtDate(project.start_date) || 'No start date' }}</span>
            <span>🏁 {{ fmtDate(project.end_date) || 'No end date' }}</span>
          </div>
        </div>

        <!-- ══════════════════════════════════════════
             LIST VIEW
        ═══════════════════════════════════════════ -->
        <div v-if="viewMode === 'list'" class="space-y-2">
          <div v-if="project.tasks.length === 0" class="bg-mp-card border border-dashed border-mp-border rounded-xl p-10 text-center">
            <p class="text-white text-sm">No tasks yet — click "Add Task" to get started</p>
          </div>

          <template v-for="task in project.tasks" :key="task.id">
            <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden hover:border-mp-border transition-all">
              <!-- Task header row -->
              <div class="flex items-center gap-3 px-4 py-3 cursor-pointer"
                @click="toggleTask(task.id)">
                <!-- Order badge -->
                <span class="w-7 h-7 bg-mp-card-hover rounded-lg flex items-center justify-center text-xs font-bold text-white flex-shrink-0">
                  {{ task.order }}
                </span>

                <!-- Status dot -->
                <span :class="taskStatusDotClass(task.status)" class="w-2.5 h-2.5 rounded-full flex-shrink-0"></span>

                <!-- Name + description -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="font-semibold text-white text-sm truncate">{{ task.name }}</span>
                    <span v-if="task.depends_on_task_id" class="text-xs text-white flex-shrink-0">
                      ← depends on #{{ taskOrderById(task.depends_on_task_id) }}
                    </span>
                  </div>
                  <p v-if="task.description" class="text-xs text-white truncate mt-0.5">{{ task.description }}</p>
                </div>

                <!-- Priority -->
                <span :class="priorityBadgeClass(task.priority)"
                  class="text-xs font-semibold px-2 py-0.5 rounded-full uppercase tracking-wide flex-shrink-0">
                  {{ task.priority }}
                </span>

                <!-- Status badge -->
                <span :class="taskStatusBadgeClass(task.status)"
                  class="text-xs font-semibold px-2.5 py-0.5 rounded-full uppercase flex-shrink-0">
                  {{ taskStatusLabel(task.status) }}
                </span>

                <!-- Progress -->
                <div class="w-20 flex-shrink-0">
                  <div class="flex items-center justify-between text-xs mb-0.5">
                    <span class="text-white">{{ task.progress_pct }}%</span>
                  </div>
                  <div class="h-1.5 bg-mp-card-hover rounded-full overflow-hidden">
                    <div class="h-full bg-mp-teal rounded-full" :style="`width:${task.progress_pct}%`"></div>
                  </div>
                </div>

                <!-- Due date -->
                <span class="text-xs flex-shrink-0"
                  :class="isOverdue(task.due_date, task.status) ? 'text-mp-danger font-semibold' : 'text-white'">
                  {{ task.due_date ? fmtDate(task.due_date) : '—' }}
                </span>

                <!-- Assignees avatars -->
                <div class="flex -space-x-1 flex-shrink-0">
                  <template v-for="a in task.assignees.slice(0, 3)" :key="a.id">
                    <div class="w-6 h-6 rounded-full bg-mp-teal-dark border border-mp-border flex items-center justify-center text-xs font-bold text-white"
                      :title="a.name">{{ a.name.charAt(0).toUpperCase() }}</div>
                  </template>
                  <div v-if="task.assignees.length > 3"
                    class="w-6 h-6 rounded-full bg-mp-page border border-mp-border flex items-center justify-center text-xs text-white">
                    +{{ task.assignees.length - 3 }}
                  </div>
                </div>

                <!-- Hours -->
                <span class="text-xs text-white flex-shrink-0">⏱ {{ task.total_hours.toFixed(1) }}h</span>

                <!-- Actions -->
                <div class="flex items-center gap-1 flex-shrink-0" @click.stop>
                  <button @click="openLogModal(task)"
                    class="w-7 h-7 flex items-center justify-center rounded bg-mp-success/30 hover:bg-mp-success text-mp-success hover:text-white transition-colors text-xs font-bold"
                    title="Log time">⏱</button>
                  <button @click="openEditTask(task)"
                    class="w-7 h-7 flex items-center justify-center rounded bg-mp-card-hover hover:bg-mp-teal text-white hover:text-white transition-colors"
                    title="Edit task">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.536-6.536a2 2 0 012.828 2.828L11.828 13.828 9 14l.172-2.828z"/>
                    </svg>
                  </button>
                  <button @click="deleteTask(task.id)"
                    class="w-7 h-7 flex items-center justify-center rounded bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors"
                    title="Delete">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>

                <svg class="w-4 h-4 text-white flex-shrink-0 transition-transform"
                  :class="expandedTasks.includes(task.id) ? 'rotate-180' : ''"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </div>

              <!-- Expanded detail: logs list -->
              <div v-if="expandedTasks.includes(task.id)"
                class="border-t border-mp-border px-4 py-3 bg-mp-page/60">
                <div v-if="task.logs.length === 0" class="text-xs text-white text-center py-2">No time logs yet</div>
                <div v-else class="space-y-1.5">
                  <div v-for="log in task.logs" :key="log.id"
                    class="flex items-center gap-3 text-xs bg-mp-card rounded-lg px-3 py-2">
                    <span class="font-semibold text-white w-24 flex-shrink-0">{{ log.user_name }}</span>
                    <span class="text-white">{{ fmtDate(log.log_date) }}</span>
                    <span class="text-white font-semibold">{{ log.hours }}h</span>
                    <span v-if="log.progress_pct" class="text-white">→ {{ log.progress_pct }}% progress</span>
                    <span v-if="log.notes" class="text-white flex-1 truncate">{{ log.notes }}</span>
                    <button @click="deleteLog(task.id, log.id)"
                      class="ml-auto text-mp-danger hover:text-mp-danger transition-colors">✕</button>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- ══════════════════════════════════════════
             KANBAN VIEW
        ═══════════════════════════════════════════ -->
        <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <template v-for="col in kanbanColumns" :key="col.status">
            <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
              <!-- Column header -->
              <div :class="col.headerClass" class="px-4 py-3 flex items-center justify-between">
                <span class="text-sm font-bold">{{ col.label }}</span>
                <span class="text-xs font-semibold bg-black/20 px-2 py-0.5 rounded-full">
                  {{ tasksByStatus(col.status).length }}
                </span>
              </div>
              <!-- Cards -->
              <div class="p-3 space-y-2 min-h-40">
                <div v-for="task in tasksByStatus(col.status)" :key="task.id"
                  class="bg-mp-card-hover border border-mp-border rounded-lg p-3 hover:border-mp-border transition-all cursor-pointer group">
                  <div class="flex items-start justify-between gap-2 mb-2">
                    <span class="text-sm font-semibold text-white leading-tight">{{ task.name }}</span>
                    <span :class="priorityBadgeClass(task.priority)"
                      class="text-xs font-semibold px-1.5 py-0.5 rounded-full uppercase tracking-wide flex-shrink-0">
                      {{ task.priority.charAt(0).toUpperCase() }}
                    </span>
                  </div>
                  <!-- Progress bar -->
                  <div class="h-1 bg-mp-page rounded-full overflow-hidden mb-2">
                    <div class="h-full bg-mp-teal rounded-full" :style="`width:${task.progress_pct}%`"></div>
                  </div>
                  <div class="flex items-center justify-between text-xs text-white">
                    <span>{{ task.due_date ? fmtDate(task.due_date) : '—' }}</span>
                    <span>⏱ {{ task.total_hours.toFixed(1) }}h</span>
                  </div>
                  <!-- Assignees -->
                  <div class="flex items-center justify-between mt-2">
                    <div class="flex -space-x-1">
                      <template v-for="a in task.assignees.slice(0, 3)" :key="a.id">
                        <div class="w-5 h-5 rounded-full bg-mp-teal-dark border border-mp-border flex items-center justify-center text-xs font-bold text-white"
                          :title="a.name">{{ a.name.charAt(0) }}</div>
                      </template>
                    </div>
                    <!-- Quick actions (appear on hover) -->
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      <button @click.stop="openLogModal(task)"
                        class="w-6 h-6 flex items-center justify-center rounded bg-mp-success/50 hover:bg-mp-success text-mp-success hover:text-white text-xs transition-colors">⏱</button>
                      <button @click.stop="openEditTask(task)"
                        class="w-6 h-6 flex items-center justify-center rounded bg-mp-page hover:bg-mp-teal text-white hover:text-white transition-colors">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.536-6.536a2 2 0 012.828 2.828L11.828 13.828 9 14l.172-2.828z"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
                <div v-if="tasksByStatus(col.status).length === 0" class="text-center py-6">
                  <p class="text-xs text-white">No tasks</p>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- ══════════════════════════════════════════
             PROJECT EXPENSES SECTION
        ═══════════════════════════════════════════ -->
        <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-mp-border">
            <div>
              <p class="text-xs font-semibold text-white uppercase tracking-widest mb-0.5">External Expenses</p>
              <p class="text-white font-bold">Project Cost Card</p>
            </div>
            <div class="flex items-center gap-4">
              <div class="text-right">
                <p class="text-xs text-white">Total External</p>
                <p class="text-lg font-bold text-white">{{ fmtMoney(project.total_external_cost) }}</p>
              </div>
              <button @click="showExpenseModal = true"
                class="flex items-center gap-1.5 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-semibold px-3 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Expense
              </button>
            </div>
          </div>

          <div v-if="project.expenses.length === 0" class="p-8 text-center text-white text-sm">
            No external expenses logged yet
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-mp-border">
                  <th class="text-left text-xs font-semibold text-white uppercase px-5 py-3">Category</th>
                  <th class="text-left text-xs font-semibold text-white uppercase px-5 py-3">Description</th>
                  <th class="text-left text-xs font-semibold text-white uppercase px-5 py-3">Date</th>
                  <th class="text-right text-xs font-semibold text-white uppercase px-5 py-3">Amount</th>
                  <th class="text-center text-xs font-semibold text-white uppercase px-5 py-3">By</th>
                  <th class="px-5 py-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-800">
                <template v-for="exp in project.expenses" :key="exp.id">
                  <tr class="hover:bg-mp-card-hover/40 transition-colors">
                    <td class="px-5 py-3">
                      <span :class="expenseCategoryClass(exp.category)"
                        class="text-xs font-semibold px-2 py-0.5 rounded-full uppercase">
                        {{ exp.display_category || exp.category }}
                      </span>
                    </td>
                    <td class="px-5 py-3 text-white">{{ exp.description }}</td>
                    <td class="px-5 py-3 text-white">{{ fmtDate(exp.expense_date) }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-white">{{ fmtMoney(exp.amount) }}</td>
                    <td class="px-5 py-3 text-center text-white text-xs">{{ exp.created_by_name }}</td>
                    <td class="px-5 py-3 text-center">
                      <button @click="deleteExpense(exp.id)"
                        class="w-6 h-6 flex items-center justify-center rounded bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white mx-auto transition-colors">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                      </button>
                    </td>
                  </tr>
                </template>
              </tbody>
              <tfoot>
                <tr class="border-t border-mp-border bg-mp-card-hover/30">
                  <td colspan="3" class="px-5 py-3 text-sm font-bold text-white">Total Internal Labor + External</td>
                  <td class="px-5 py-3 text-right">
                    <div class="text-xs text-white">Internal: {{ fmtMoney(project.total_internal_cost) }}</div>
                    <div class="text-lg font-bold text-white">{{ fmtMoney(project.total_project_cost) }}</div>
                  </td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </div><!-- end main content -->

      <!-- ══════════════════════════════════════════
           ADD / EDIT TASK MODAL
      ═══════════════════════════════════════════ -->
      <div v-if="showAddTaskModal || editingTask"
        class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="closeTaskModal">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-xl shadow-2xl">
          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
            <h3 class="font-bold text-white">{{ editingTask ? 'Edit Task' : 'Add Task' }}</h3>
            <button @click="closeTaskModal" class="text-white hover:text-white">✕</button>
          </div>
          <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
            <div v-if="taskError" class="bg-mp-danger/20 border border-mp-danger text-mp-danger text-sm rounded-lg px-3 py-2">
              {{ taskError }}
            </div>
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Task Name *</label>
              <input v-model="taskForm.name" type="text" placeholder="e.g. Review financial statements"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal"/>
            </div>
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Description</label>
              <textarea v-model="taskForm.description" rows="2"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal resize-none"/>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Status</label>
                <select v-model="taskForm.status" @change="onTaskStatusChange"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal">
                  <option value="not_started">Not Started</option>
                  <option value="in_progress">In Progress</option>
                  <option value="completed">Completed</option>
                  <option value="blocked">Blocked</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Priority</label>
                <select v-model="taskForm.priority"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal">
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Start Date</label>
                <input v-model="taskForm.start_date" type="date"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Due Date</label>
                <input v-model="taskForm.due_date" type="date"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal"/>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Estimated Days</label>
                <input v-model="taskForm.estimated_days" type="number" min="1"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Progress %</label>
                <input v-model="taskForm.progress_pct" type="number" min="0" max="100"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal"/>
              </div>
            </div>
            <!-- Depends on -->
            <div v-if="project.tasks.length > 0">
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Depends On (runs after)</label>
              <select v-model="taskForm.depends_on_task_id"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal">
                <option :value="null">— None —</option>
                <template v-for="t in project.tasks" :key="t.id">
                  <option v-if="!editingTask || t.id !== editingTask.id" :value="t.id">
                    #{{ t.order }} {{ t.name }}
                  </option>
                </template>
              </select>
            </div>
            <!-- Assignees -->
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Assign To</label>
              <div class="flex flex-wrap gap-2">
                <template v-for="user in companyUsers" :key="user.id">
                  <button
                    @click="toggleAssignee(user.id)"
                    :class="taskForm.assignee_ids.includes(user.id)
                      ? 'bg-mp-teal text-white border-mp-teal'
                      : 'bg-mp-card-hover text-white border-mp-border hover:border-mp-border'"
                    class="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors">
                    <span class="w-4 h-4 rounded-full bg-mp-teal-dark/50 flex items-center justify-center font-bold text-xs">
                      {{ user.name.charAt(0) }}
                    </span>
                    {{ user.name }}
                  </button>
                </template>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-mp-border flex justify-end gap-3">
            <button @click="closeTaskModal"
              class="px-4 py-2 text-sm text-white bg-mp-card-hover hover:bg-mp-page rounded-lg transition-colors">Cancel</button>
            <button @click="saveTask" :disabled="saving"
              class="px-5 py-2 text-sm font-semibold bg-mp-teal hover:bg-mp-teal-dark text-white rounded-lg transition-colors disabled:opacity-50">
              {{ saving ? 'Saving...' : (editingTask ? 'Update Task' : 'Add Task') }}
            </button>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════
           LOG TIME MODAL
      ═══════════════════════════════════════════ -->
      <div v-if="showLogModal && loggingTask"
        class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="showLogModal = false">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-md shadow-2xl">
          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
            <div>
              <h3 class="font-bold text-white">Log Time</h3>
              <p class="text-xs text-white mt-0.5">{{ loggingTask.name }}</p>
            </div>
            <button @click="showLogModal = false" class="text-white hover:text-white">✕</button>
          </div>
          <div class="px-6 py-5 space-y-4">
            <div v-if="logError" class="bg-mp-danger/20 border border-mp-danger text-mp-danger text-sm rounded-lg px-3 py-2">
              {{ logError }}
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Date *</label>
                <input v-model="logForm.log_date" type="date"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Hours *</label>
                <input v-model="logForm.hours" type="number" min="0.25" max="24" step="0.25" placeholder="e.g. 3.5"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal"/>
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">
                Update Progress % <span class="text-white">(optional)</span>
              </label>
              <div class="flex items-center gap-3">
                <input v-model="logForm.progress_pct" type="range" min="0" max="100" step="5"
                  class="flex-1 accent-blue-500"/>
                <span class="text-white font-bold w-10 text-right">{{ logForm.progress_pct || 0 }}%</span>
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Notes</label>
              <textarea v-model="logForm.notes" rows="2" placeholder="What did you accomplish?"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal resize-none"/>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-mp-border flex justify-end gap-3">
            <button @click="showLogModal = false"
              class="px-4 py-2 text-sm text-white bg-mp-card-hover hover:bg-mp-page rounded-lg transition-colors">Cancel</button>
            <button @click="saveLog" :disabled="saving"
              class="px-5 py-2 text-sm font-semibold bg-mp-success hover:bg-mp-success text-white rounded-lg transition-colors disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Log Time' }}
            </button>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════
           ADD EXPENSE MODAL
      ═══════════════════════════════════════════ -->
      <div v-if="showExpenseModal"
        class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="showExpenseModal = false">
        <div class="bg-mp-card border border-mp-border rounded-2xl w-full max-w-md shadow-2xl">
          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
            <h3 class="font-bold text-white">Add External Expense</h3>
            <button @click="showExpenseModal = false" class="text-white hover:text-white">✕</button>
          </div>
          <div class="px-6 py-5 space-y-4">

            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Category *</label>
              <select v-model="expenseForm.category"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal">
                <optgroup label="People">
                  <option value="consultant">Consultant</option>
                  <option value="freelancer">Freelancer</option>
                  <option value="legal">Legal Fees</option>
                  <option value="accounting">Accounting &amp; Audit</option>
                  <option value="training">Training</option>
                </optgroup>
                <optgroup label="Technology">
                  <option value="software">Software / IT</option>
                  <option value="saas_subscription">SaaS Subscription</option>
                  <option value="hardware">Hardware / Equipment</option>
                </optgroup>
                <optgroup label="Operations">
                  <option value="purchase">Purchase</option>
                  <option value="raw_materials">Raw Materials</option>
                  <option value="maintenance">Maintenance &amp; Repair</option>
                  <option value="logistics">Logistics &amp; Shipping</option>
                  <option value="insurance">Insurance</option>
                </optgroup>
                <optgroup label="Commercial">
                  <option value="marketing">Marketing</option>
                  <option value="travel">Travel</option>
                  <option value="accommodation">Accommodation</option>
                </optgroup>
                <optgroup label="Finance &amp; Admin">
                  <option value="government_fees">Government Fees</option>
                  <option value="bank_charges">Bank Charges</option>
                </optgroup>
                <optgroup label="Other">
                  <option value="other">Other</option>
                </optgroup>
              </select>
            </div>
            <div>
            <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">
                Custom Label
                <span class="text-white normal-case font-normal ml-1">(optional — overrides category name in display)</span>
            </label>
            <input v-model="expenseForm.custom_category" type="text"
                :placeholder="expenseCategoryPlaceholder(expenseForm.category)"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal"/>
            <p v-if="expenseForm.custom_category" class="text-xs text-white mt-1.5">
                Will display as: <strong class="text-white">{{ expenseForm.custom_category }}</strong>
            </p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Description *</label>
              <input v-model="expenseForm.description" type="text" placeholder="e.g. Legal review by Smith & Co"
                class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal"/>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Amount *</label>
                <input v-model="expenseForm.amount" type="number" min="0" step="0.01" placeholder="0.00"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal"/>
              </div>
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">Date *</label>
                <input v-model="expenseForm.expense_date" type="date"
                  class="w-full bg-mp-card-hover border border-mp-border text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-mp-teal"/>
              </div>
            </div>
          </div>
          <div class="px-6 py-4 border-t border-mp-border flex justify-end gap-3">
            <button @click="showExpenseModal = false"
              class="px-4 py-2 text-sm text-white bg-mp-card-hover hover:bg-mp-page rounded-lg transition-colors">Cancel</button>
            <button @click="saveExpense" :disabled="saving"
              class="px-5 py-2 text-sm font-semibold bg-mp-gold-dark hover:bg-mp-gold text-white rounded-lg transition-colors disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Add Expense' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:      { type: Object, required: true },
  project:      { type: Object, required: true },
  companyUsers: { type: Array,  default: () => [] },
})

// Make project reactive so we can update it after API calls
const project = reactive(JSON.parse(JSON.stringify(props.project)))

const viewMode    = ref('list')
const saving      = ref(false)
const expandedTasks = ref([])

// ── Task Modal ──
const showAddTaskModal = ref(false)
const editingTask      = ref(null)
const taskError        = ref('')
const emptyTaskForm = () => ({
  name: '', description: '', status: 'not_started', priority: 'medium',
  estimated_days: '', start_date: '', due_date: '', progress_pct: 0,
  depends_on_task_id: null, assignee_ids: []
})
const taskForm = reactive(emptyTaskForm())

function emptyToNull(value) {
  if (value === '' || value === undefined) return null
  return value
}

function buildTaskPayload() {
  const progress = Number(taskForm.progress_pct ?? 0)
  const completed = taskForm.status === 'completed' || progress >= 100

  return {
    name: taskForm.name.trim(),
    description: taskForm.description || '',
    status: completed ? 'completed' : taskForm.status,
    priority: taskForm.priority,
    estimated_days: emptyToNull(taskForm.estimated_days),
    start_date: emptyToNull(taskForm.start_date),
    due_date: emptyToNull(taskForm.due_date),
    progress_pct: completed ? 100 : (Number.isFinite(progress) ? Math.min(100, Math.max(0, Math.round(progress))) : 0),
    depends_on_task_id: emptyToNull(taskForm.depends_on_task_id),
    assignee_ids: [...taskForm.assignee_ids],
  }
}

function onTaskStatusChange() {
  if (taskForm.status === 'completed') {
    taskForm.progress_pct = 100
  }
}

function buildLogPayload() {
  const hours = Number(logForm.hours)
  const progress = Number(logForm.progress_pct ?? 0)

  return {
    log_date: logForm.log_date,
    hours,
    notes: logForm.notes || '',
    progress_pct: Number.isFinite(progress) ? Math.min(100, Math.max(0, Math.round(progress))) : 0,
  }
}

function openEditTask(task) {
  editingTask.value = task
  taskError.value = ''
  Object.assign(taskForm, {
    name:               task.name,
    description:        task.description || '',
    status:             task.status,
    priority:           task.priority,
    estimated_days:     task.estimated_days || '',
    start_date:         task.start_date || '',
    due_date:           task.due_date || '',
    progress_pct:       task.progress_pct || 0,
    depends_on_task_id: task.depends_on_task_id || null,
    assignee_ids:       task.assignees.map(a => a.id),
  })
}
function closeTaskModal() {
  showAddTaskModal.value = false
  editingTask.value = null
  taskError.value = ''
  Object.assign(taskForm, emptyTaskForm())
}
function toggleAssignee(uid) {
  if (taskForm.assignee_ids[0] === uid) {
    taskForm.assignee_ids.splice(0, taskForm.assignee_ids.length)
    return
  }

  taskForm.assignee_ids.splice(0, taskForm.assignee_ids.length, uid)
}

// ── Log Modal ──
const showLogModal = ref(false)
const loggingTask  = ref(null)
const logError     = ref('')
const logForm = reactive({ log_date: '', hours: '', notes: '', progress_pct: 0 })

function openLogModal(task) {
  loggingTask.value = task
  logError.value = ''
  Object.assign(logForm, {
    log_date: new Date().toISOString().split('T')[0],
    hours: '',
    notes: '',
    progress_pct: task.progress_pct || 0,
  })
  showLogModal.value = true
}

// ── Expense Modal ──
const showExpenseModal = ref(false)
  const expenseForm = reactive({
    category: 'consultant', custom_category: '', description: '', amount: '', expense_date: ''
  })

// ── Computed ──
const doneTasks = computed(() => project.tasks.filter(t => t.status === 'completed').length)
const totalHours = computed(() => project.tasks.reduce((sum, t) => sum + t.total_hours, 0))
const overallProgress = computed(() => {
  if (!project.tasks.length) return 0
  return Math.round(project.tasks.reduce((sum, t) => sum + t.progress_pct, 0) / project.tasks.length)
})

const kanbanColumns = [
  { status: 'not_started', label: 'Not Started', headerClass: 'bg-mp-card-hover text-white' },
  { status: 'in_progress', label: 'In Progress',  headerClass: 'bg-mp-teal-subtle/60 text-white' },
  { status: 'blocked',     label: 'Blocked',       headerClass: 'bg-mp-danger/60 text-mp-danger' },
  { status: 'completed',   label: 'Completed',     headerClass: 'bg-mp-success/60 text-mp-success' },
]

function tasksByStatus(status) { return project.tasks.filter(t => t.status === status) }
function taskOrderById(id) { return project.tasks.find(t => t.id === id)?.order || id }
function toggleTask(id) {
  const idx = expandedTasks.value.indexOf(id)
  if (idx >= 0) expandedTasks.value.splice(idx, 1)
  else expandedTasks.value.push(id)
}

// ── API Calls ──
function getCsrfToken() {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

function apiFetch(url, opts = {}) {
  const { headers: extraHeaders, ...rest } = opts
  return fetch(url, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-XSRF-TOKEN': getCsrfToken(),
      ...extraHeaders,
    },
    ...rest,
  })
}
const base = `/portfolio-companies/${props.company.id}/projects/${props.project.id}`

async function refreshProject() {
  const res = await apiFetch(`${base}/refresh`)
  const data = await res.json()
  Object.assign(project, data)
}

async function saveTask() {
  taskError.value = ''
  if (!taskForm.name.trim()) {
    taskError.value = 'Task name is required.'
    return
  }

  saving.value = true
  try {
    const url = editingTask.value ? `${base}/tasks/${editingTask.value.id}` : `${base}/tasks`
    const method = editingTask.value ? 'PUT' : 'POST'
    const res = await apiFetch(url, { method, body: JSON.stringify(buildTaskPayload()) })

    let data = {}
    try {
      data = await res.json()
    } catch (_) {}

    if (!res.ok) {
      taskError.value = data.message
        || Object.values(data.errors || {})[0]?.[0]
        || 'Could not save this task. Please try again.'
      return
    }

    closeTaskModal()
    await refreshProject()
  } catch (_) {
    taskError.value = 'Could not save this task. Please try again.'
  } finally {
    saving.value = false
  }
}

async function deleteTask(taskId) {
  if (!confirm('Delete this task and all its time logs?')) return
  await apiFetch(`${base}/tasks/${taskId}`, { method: 'DELETE' })
  await refreshProject()
}

async function saveLog() {
  logError.value = ''
  const hours = Number(logForm.hours)

  if (!logForm.log_date) {
    logError.value = 'Date is required.'
    return
  }

  if (!Number.isFinite(hours) || hours < 0.25 || hours > 24) {
    logError.value = 'Hours must be between 0.25 and 24.'
    return
  }

  saving.value = true
  try {
    const res = await apiFetch(`${base}/tasks/${loggingTask.value.id}/logs`, {
      method: 'POST',
      body: JSON.stringify(buildLogPayload()),
    })

    let data = {}
    try {
      data = await res.json()
    } catch (_) {}

    if (!res.ok) {
      logError.value = data.message
        || Object.values(data.errors || {})[0]?.[0]
        || 'Could not save this time log. Please try again.'
      return
    }

    const taskId = loggingTask.value.id
    showLogModal.value = false
    await refreshProject()
    if (!expandedTasks.value.includes(taskId)) {
      expandedTasks.value.push(taskId)
    }
  } catch (_) {
    logError.value = 'Could not save this time log. Please try again.'
  } finally {
    saving.value = false
  }
}

async function deleteLog(taskId, logId) {
  await apiFetch(`${base}/tasks/${taskId}/logs/${logId}`, { method: 'DELETE' })
  await refreshProject()
}

async function saveExpense() {
  if (!expenseForm.description || !expenseForm.amount || !expenseForm.expense_date) return
  saving.value = true
  await apiFetch(`${base}/expenses`, { method: 'POST', body: JSON.stringify(expenseForm) })
  saving.value = false
  showExpenseModal.value = false
  Object.assign(expenseForm, { category: 'consultant', custom_category: '', description: '', amount: '', expense_date: '' })
}

async function deleteExpense(expId) {
  if (!confirm('Delete this expense?')) return
  await apiFetch(`${base}/expenses/${expId}`, { method: 'DELETE' })
  await refreshProject()
}

// ── Formatting ──
function fmtDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
function fmtMoney(v) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: project.currency || 'USD', maximumFractionDigits: 0 }).format(v || 0)
}
function isOverdue(due, status) {
  if (!due || status === 'completed') return false
  return new Date(due) < new Date()
}
function statusLabel(s) {
  return { not_started: 'Not Started', in_progress: 'In Progress', on_hold: 'On Hold', completed: 'Completed', cancelled: 'Cancelled' }[s] || s
}
function statusBadgeClass(s) {
  return { not_started: 'bg-mp-card-hover text-white', in_progress: 'bg-mp-teal-subtle/50 text-white border border-mp-teal/40', on_hold: 'bg-mp-warning/50 text-mp-warning', completed: 'bg-mp-success/50 text-mp-success', cancelled: 'bg-mp-danger/50 text-mp-danger' }[s] || ''
}
function taskStatusLabel(s) {
  return { not_started: 'Not Started', in_progress: 'In Progress', completed: 'Done', blocked: 'Blocked' }[s] || s
}
function taskStatusBadgeClass(s) {
  return { not_started: 'bg-mp-card-hover text-white', in_progress: 'bg-mp-teal-subtle/50 text-white', completed: 'bg-mp-success/50 text-mp-success', blocked: 'bg-mp-danger/50 text-mp-danger' }[s] || ''
}
function taskStatusDotClass(s) {
  return { not_started: 'bg-mp-muted', in_progress: 'bg-mp-teal', completed: 'bg-mp-success', blocked: 'bg-mp-danger' }[s] || 'bg-mp-muted'
}
function priorityBadgeClass(p) {
  return { low: 'bg-mp-card-hover text-white', medium: 'bg-mp-warning/50 text-mp-warning', high: 'bg-mp-danger/50 text-mp-danger' }[p] || ''
}
function expenseCategoryClass(c) {
    const map = {
      consultant: 'bg-mp-gold/50 text-white',
      freelancer: 'bg-mp-gold/50 text-white',
      legal: 'bg-mp-danger/50 text-mp-danger',
      accounting: 'bg-mp-warning/50 text-mp-warning',
      training: 'bg-mp-warning/50 text-mp-warning',
      software: 'bg-mp-teal-subtle/50 text-white',
      saas_subscription: 'bg-mp-teal-subtle/50 text-white',
      hardware: 'bg-mp-teal-subtle/50 text-white',
      purchase: 'bg-mp-gold/50 text-white',
      raw_materials: 'bg-mp-success/50 text-mp-success',
      maintenance: 'bg-mp-card-hover text-white',
      logistics: 'bg-mp-teal-subtle/50 text-white',
      insurance: 'bg-mp-teal-subtle/50 text-white',
      marketing: 'bg-mp-gold-dark/50 text-white',
      travel: 'bg-mp-success/50 text-mp-success',
      accommodation: 'bg-mp-success/50 text-mp-success',
      government_fees: 'bg-mp-page text-white',
      bank_charges: 'bg-mp-card-hover text-white',
      other: 'bg-mp-card-hover text-white',
    }
    return map[c] || 'bg-mp-card-hover text-white'
  }

// ADD this new function right after expenseCategoryClass:
  function expenseCategoryPlaceholder(c) {
    const map = {
      consultant: 'McKinsey Strategy Review',
      freelancer: 'John Doe — UX Design',
      legal: 'Smith & Co — SPA Review',
      accounting: 'KPMG — Annual Audit',
      training: 'Excel Advanced Course',
      software: 'Custom ERP Module',
      saas_subscription: 'Salesforce CRM',
      hardware: 'Dell Servers x5',
      purchase: 'Office Furniture',
      raw_materials: 'Steel Coils Q1',
      maintenance: 'AC Overhaul — HQ',
      logistics: 'DHL — Q3 Shipments',
      insurance: 'Property Insurance 2026',
      marketing: 'Google Ads Campaign',
      travel: 'Board Meeting — Dubai',
      accommodation: 'Marriott — 3 nights',
      government_fees: 'Commercial Registry Renewal',
      bank_charges: 'LC Issuance Fees',
      other: 'Add a specific label...',
    }
    return map[c] || 'Describe this expense...'
  }

</script>