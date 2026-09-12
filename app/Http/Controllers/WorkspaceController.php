<?php

namespace App\Http\Controllers;

use App\Services\Workspace;
use App\Services\WorkspaceNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(private Workspace $workspace) {}

    public function dashboard(): Response
    {
        $projects = $this->workspace->projects()->get()->map(function ($project) {
            $activities = $this->workspace->records('activities')->where('project_id', $project->id);
            $project->activities_count = (clone $activities)->count();
            $project->progress = (int) ((clone $activities)->avg('progress') ?? 0);
            $project->workers_count = $this->workspace->records('gate_entries')->where('project_id', $project->id)->whereNull('departed_at')->count();

            return $project;
        });
        $today = $this->workspace->records('activities')->whereDate('planned_date', today());

        return Inertia::render('Dashboard', ['projects' => $projects, 'stats' => ['visitors' => $this->workspace->records('visitor_entries')->where('status', 'Onsite')->count(), 'deployments' => $this->workspace->records('worker_deployments')->whereDate('work_date', today())->count(), 'instructions' => $this->workspace->records('site_instructions')->where('status', '!=', 'Closed')->count(), 'projects' => $projects->where('status', 'Active')->count(), 'onsite' => $this->workspace->records('gate_entries')->whereNull('departed_at')->count(), 'planned' => (clone $today)->count(), 'completed' => (clone $today)->where('status', 'Completed')->count(), 'delayed' => (clone $today)->whereIn('status', ['Delayed', 'Blocked'])->count()], 'activities' => (clone $today)->orderBy('id', 'desc')->limit(6)->get(), 'directions' => $this->workspace->records('directions')->latest()->limit(3)->get()->map(function ($direction) {
            $direction->acknowledged = DB::table('direction_acknowledgments')->where('direction_id', $direction->id)->where('user_id', auth()->id())->exists();

            return $direction;
        }), 'recent' => $this->workspace->records('audit_logs')->latest()->limit(5)->get(['id', 'action', 'project_id', 'created_at']), 'today' => now()->format('l, j F Y')]);
    }

    public function projects(): Response
    {
        return Inertia::render('Projects', ['projects' => $this->workspace->projects()->latest()->get()]);
    }

    public function createProject(Request $request): RedirectResponse
    {
        $this->workspace->authorize('projects.manage');
        $validated = $request->validate(['name' => 'required|string|max:160', 'location' => 'required|string|max:160', 'description' => 'nullable|string|max:5000', 'start_date' => 'nullable|date']);
        DB::transaction(function () use ($validated) {
            $id = DB::table('projects')->insertGetId([...$validated, 'organization_id' => $this->workspace->organization()->id, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
            $this->workspace->audit('project.created', 'projects', $id, $id, $validated);
        });

        return back()->with('success', 'Project created. You can now add people and plan the day.');
    }

    public function project(int $id): Response
    {
        $project = $this->workspace->project($id);

        return Inertia::render('Project', ['project' => $project, 'activities' => $this->workspace->records('activities')->where('project_id', $id)->latest()->get(), 'workers' => $this->workspace->records('workers')->where('project_id', $id)->get(), 'diary' => $this->workspace->records('diary_entries')->where('project_id', $id)->latest()->limit(10)->get()]);
    }

    public function listing(Request $request, string $module): Response
    {
        $table = match ($module) {
            'workers' => 'workers','gate' => 'gate_entries','activities' => 'activities','diary' => 'diary_entries',default => abort(404)
        };
        $query = $this->workspace->records($table);
        if ($request->filled('project')) {
            $this->workspace->project((int) $request->input('project'));
            $query->where('project_id', (int) $request->input('project'));
        }
        if ($module === 'gate' && $request->input('status') === 'onsite') {
            $query->whereNull('departed_at');
        }
        if ($module === 'activities') {
            if ($request->input('date') === 'today') {
                $query->whereDate('planned_date', today());
            }
            if ($request->input('status') === 'attention') {
                $query->whereIn('status', ['Delayed', 'Blocked']);
            }
            if (in_array($request->input('status'), ['Completed', 'Delayed', 'Blocked'])) {
                $query->where('status', $request->input('status'));
            }
        }

        return Inertia::render('Records', ['module' => $module, 'records' => $query->latest()->paginate(20)->withQueryString(), 'projects' => $this->workspace->projects()->get(), 'workers' => $this->workspace->records('workers')->get(['id', 'name', 'project_id']), 'filters' => $request->only('project', 'status', 'date')]);
    }

    public function store(Request $request, string $module): RedirectResponse
    {
        $this->workspace->authorize($module.'.manage');
        $rules = match ($module) {
            'workers' => ['name' => 'required|string|max:160', 'trade' => 'required|string|max:120', 'phone' => 'nullable|string|max:40'],'activities' => ['title' => 'required|string|max:200', 'location' => 'required|string|max:160', 'responsible' => 'required|string|max:160', 'planned_date' => 'required|date'],'diary' => ['title' => 'required|string|max:200', 'notes' => 'required|string|max:10000', 'weather' => 'nullable|string|max:120', 'entry_date' => 'required|date'],'gate' => ['worker_id' => 'required|integer'],default => abort(404)
        };
        $validated = $request->validate(['project_id' => 'required|integer', ...$rules]);
        $this->workspace->project((int) $validated['project_id']);
        $table = match ($module) {
            'gate' => 'gate_entries','diary' => 'diary_entries',default => $module
        };
        DB::transaction(function () use ($module, $validated, $table) {
            if ($module === 'gate') {
                $worker = $this->workspace->records('workers')->where('id', $validated['worker_id'])->where('project_id', $validated['project_id'])->lockForUpdate()->first();
                abort_unless($worker, 404);
                if ($this->workspace->records('gate_entries')->where('worker_id', $worker->id)->whereNull('departed_at')->exists()) {
                    throw ValidationException::withMessages(['worker_id' => 'This worker is already onsite. Record their departure first.']);
                }
                $validated['arrived_at'] = now();
                $validated['recorded_by'] = auth()->id();
            }
            if (in_array($module, ['activities', 'diary'])) {
                $validated['created_by'] = auth()->id();
            }
            $id = DB::table($table)->insertGetId([...$validated, 'organization_id' => $this->workspace->organization()->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->workspace->audit($module.'.created', $table, $id, (int) $validated['project_id'], $validated);
        });

        return back()->with('success', match ($module) {
            'gate' => 'Arrival recorded. Onsite attendance updated.','workers' => 'Worker added to the project.','activities' => 'Activity added to the daily plan.',default => 'Site diary entry saved.'
        });
    }

    public function updateActivity(Request $request, int $id): RedirectResponse
    {
        $this->workspace->authorize('activities.manage');
        $validated = $request->validate(['status' => ['required', Rule::in(['Planned', 'In Progress', 'Completed', 'Delayed', 'Blocked', 'Not Completed'])], 'progress' => 'required|integer|min:0|max:100', 'reason' => 'nullable|required_if:status,Delayed,Blocked,Not Completed|string|max:3000']);
        if ($validated['status'] === 'Completed') {
            $validated['progress'] = 100;
        }
        if ($validated['status'] === 'Planned') {
            $validated['progress'] = 0;
        }
        if ($validated['status'] !== 'Completed' && $validated['progress'] == 100) {
            throw ValidationException::withMessages(['progress' => 'Choose Completed for an activity at 100%.']);
        }
        DB::transaction(function () use ($id, $validated) {
            $activity = $this->workspace->records('activities')->where('id', $id)->lockForUpdate()->first();
            abort_unless($activity, 404);
            $this->workspace->records('activities')->where('id', $id)->update([...$validated, 'updated_at' => now()]);
            $this->workspace->audit('activity.updated', 'activities', $id, $activity->project_id, $validated, (array) $activity);
        });

        return back()->with('success', 'Activity progress updated.');
    }

    public function depart(int $id): RedirectResponse
    {
        $this->workspace->authorize('gate.manage');
        DB::transaction(function () use ($id) {
            $entry = $this->workspace->records('gate_entries')->where('id', $id)->lockForUpdate()->first();
            abort_unless($entry, 404);
            if ($entry->departed_at) {
                return;
            }
            $this->workspace->records('gate_entries')->where('id', $id)->update(['departed_at' => now(), 'updated_at' => now()]);
            $this->workspace->audit('gate.departed', 'gate_entries', $id, $entry->project_id, ['departed_at' => now()->toIso8601String()]);
        });

        return back()->with('success', 'Departure recorded.');
    }

    public function direction(Request $request): RedirectResponse
    {
        $this->workspace->authorize('directions.manage');
        $validated = $request->validate(['project_id' => 'required|integer', 'message' => 'required|string|max:3000']);
        $this->workspace->project((int) $validated['project_id']);
        DB::transaction(function () use ($validated) {
            $id = DB::table('directions')->insertGetId([...$validated, 'organization_id' => $this->workspace->organization()->id, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $this->workspace->audit('direction.published', 'directions', $id, (int) $validated['project_id'], $validated);
            $notifications = app(WorkspaceNotifications::class);
            $notifications->send($this->workspace->organization()->id, (int) $validated['project_id'], $notifications->projectRecipients($this->workspace->organization()->id, (int) $validated['project_id']), 'New management direction', '/dashboard');
        });

        return back()->with('success', 'Direction published to the project.');
    }

    public function acknowledge(int $id): RedirectResponse
    {
        $direction = $this->workspace->record('directions', $id);
        DB::transaction(function () use ($id, $direction) {
            $inserted = DB::table('direction_acknowledgments')->insertOrIgnore(['organization_id' => $direction->organization_id, 'direction_id' => $id, 'user_id' => auth()->id(), 'acknowledged_at' => now()]);
            if ($inserted) {
                $this->workspace->audit('direction.acknowledged', 'directions', $id, $direction->project_id);
            }
        });

        return back()->with('success', 'Direction acknowledged.');
    }
}
