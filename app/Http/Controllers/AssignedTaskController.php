<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class AssignedTaskController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $hasSeenAt = Schema::hasColumn('project_task_assignees', 'seen_at');

        $query = DB::table('project_task_assignees as pta')
            ->join('project_tasks as pt', 'pt.id', '=', 'pta.project_task_id')
            ->join('projects as p', 'p.id', '=', 'pt.project_id')
            ->join('portfolio_companies as pc', 'pc.id', '=', 'p.portfolio_company_id')
            ->where('pta.user_id', $user->id)
            ->select(
                'pta.project_task_id',
                'pt.id',
                'pt.name',
                'pt.status',
                'pt.priority',
                'pt.due_date',
                'pt.progress_pct',
                'p.id as project_id',
                'p.name as project_name',
                'pc.id as company_id',
                'pc.name as company_name'
            );

        if ($hasSeenAt) {
            $query->addSelect('pta.seen_at')
                ->orderByRaw("CASE WHEN pta.seen_at IS NULL THEN 0 ELSE 1 END");
        } else {
            $query->selectRaw('NULL as seen_at');
        }

        $tasks = $query
            ->orderBy('pt.due_date')
            ->orderByDesc('pt.updated_at')
            ->get()
            ->map(fn ($task) => [
                'id' => $task->id,
                'name' => $task->name,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date,
                'progress_pct' => (int) ($task->progress_pct ?? 0),
                'project_id' => $task->project_id,
                'project_name' => $task->project_name,
                'company_id' => $task->company_id,
                'company_name' => $task->company_name,
                'seen' => $task->seen_at !== null,
            ]);

        if ($hasSeenAt) {
            DB::table('project_task_assignees')
                ->where('user_id', $user->id)
                ->whereNull('seen_at')
                ->update(['seen_at' => now()]);
        }

        return Inertia::render('Tasks/MyTasks', [
            'tasks' => $tasks,
            'unseenCount' => $tasks->where('seen', false)->count(),
        ]);
    }
}
