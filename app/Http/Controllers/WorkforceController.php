<?php

namespace App\Http\Controllers;

use App\Services\Workspace;
use App\Services\WorkspaceNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkforceController extends Controller
{
    public function __construct(private Workspace $workspace, private WorkspaceNotifications $notifications) {}

    private function members(): Collection
    {
        return DB::table('organization_users')->join('users', 'users.id', '=', 'organization_users.user_id')->where('organization_id', $this->workspace->organization()->id)->where(function ($query) {
            $query->where('role', 'owner')->orWhereIn('user_id', DB::table('project_users')->select('user_id')->where('organization_id', $this->workspace->organization()->id)->whereIn('project_id', $this->workspace->projects()->select('id')));
        })->get(['users.id', 'users.name']);
    }

    public function visitors(Request $request): Response
    {
        $query = $this->workspace->records('visitor_entries');
        if ($request->filled('project')) {
            $query->where('project_id', $this->workspace->project((int) $request->input('project'))->id);
        }
        if ($request->input('status') === 'Onsite') {
            $query->where('status', 'Onsite');
        }

        return Inertia::render('Visitors', ['visitors' => $query->latest()->paginate(20)->withQueryString(), 'projects' => $this->workspace->projects()->get(), 'members' => $this->members(), 'filters' => $request->only('project', 'status')]);
    }

    public function visit(Request $request): RedirectResponse
    {
        $this->workspace->authorize('gate.manage');
        $data = $request->validate(['project_id' => 'required|integer', 'name' => 'required|string|max:160', 'vehicle' => 'nullable|string|max:80', 'purpose' => 'required|string|max:500', 'host_id' => 'required|integer']);
        $project = $this->workspace->project($data['project_id']);
        if (! in_array($data['host_id'], $this->notifications->projectRecipients($project->organization_id, $project->id))) {
            throw ValidationException::withMessages(['host_id' => 'Choose a host assigned to this project.']);
        }
        DB::transaction(function () use ($data, $project) {
            $id = DB::table('visitor_entries')->insertGetId([...$data, 'organization_id' => $project->organization_id, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $this->workspace->audit('visitor.requested', 'visitor_entries', $id, $project->id, $data);
            $this->notifications->send($project->organization_id, $project->id, [$data['host_id']], 'Visitor awaiting authorization: '.$data['name'], '/visitors?project='.$project->id);
        });

        return back()->with('success', 'Visit requested. The host must authorize entry.');
    }

    public function visitorStatus(Request $request, int $id): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(['Authorized', 'Rejected', 'Onsite', 'Departed'])]])['status'];
        DB::transaction(function () use ($id, $status) {
            $visit = $this->workspace->records('visitor_entries')->where('id', $id)->lockForUpdate()->first();
            abort_unless($visit, 404);
            $allowed = ['Pending' => ['Authorized', 'Rejected'], 'Authorized' => ['Onsite'], 'Onsite' => ['Departed']];
            abort_unless(in_array($status, $allowed[$visit->status] ?? [], true), 409, 'This visit changed. Refresh the page.');
            if (in_array($status, ['Authorized', 'Rejected'], true)) {
                abort_unless(auth()->id() === $visit->host_id, 403, 'Only the host can authorize this visit.');
                $values = ['authorized_by' => auth()->id(), 'authorized_at' => now()];
            } else {
                $this->workspace->authorize('gate.manage');
                $values = [$status === 'Onsite' ? 'arrived_at' : 'departed_at' => now()];
            }
            DB::table('visitor_entries')->where('id', $id)->update([...$values, 'status' => $status, 'updated_at' => now()]);
            $this->workspace->audit('visitor.'.strtolower($status), 'visitor_entries', $id, $visit->project_id, ['status' => $status], ['status' => $visit->status]);
            $this->notifications->send($visit->organization_id, $visit->project_id, [$visit->host_id, $visit->created_by], $visit->name.': '.$status, '/visitors?project='.$visit->project_id);
        });

        return back()->with('success', 'Visit updated.');
    }

    public function deployments(Request $request): Response
    {
        $input = $request->validate(['date' => 'nullable|date_format:Y-m-d', 'project' => 'nullable|integer']);
        $date = $input['date'] ?? today()->toDateString();
        $query = $this->workspace->records('worker_deployments')->where('work_date', $date);
        if (! empty($input['project'])) {
            $query->where('project_id', $this->workspace->project($input['project'])->id);
        }
        $gate = $this->workspace->records('gate_entries')->where('arrived_at', '<', Carbon::parse($date)->addDay())->where(fn ($q) => $q->whereNull('departed_at')->orWhere('departed_at', '>=', $date))->get()->groupBy('worker_id');
        $deployments = $query->orderBy('id')->get()->map(function ($row) use ($gate, $date) {
            $entries = $gate->get($row->worker_id, collect())->where('project_id', $row->project_id)->sortBy('arrived_at');
            $arrival = $entries->first();
            $row->attendance = $arrival ? (Carbon::parse($arrival->arrived_at)->greaterThan(Carbon::parse($date.' '.$row->expected_at)) ? 'Late' : 'Present') : (now()->greaterThan(Carbon::parse($date.' '.$row->expected_at)) ? 'Absent' : 'Expected');
            $row->arrived_at = $arrival?->arrived_at;
            $row->onsite = $entries->contains(fn ($entry) => ! $entry->departed_at);

            return $row;
        });

        return Inertia::render('Workforce', ['deployments' => $deployments, 'date' => $date, 'selectedProject' => $input['project'] ?? '', 'projects' => $this->workspace->projects()->get(), 'workers' => $this->workspace->records('workers')->get(['id', 'name', 'trade', 'project_id']), 'activities' => $this->workspace->records('activities')->whereDate('planned_date', $date)->get(['id', 'title', 'project_id']), 'members' => $this->members(), 'timezone' => config('app.timezone')]);
    }

    public function deploy(Request $request): RedirectResponse
    {
        $this->workspace->authorize('activities.manage');
        $data = $request->validate(['project_id' => 'required|integer', 'worker_id' => 'required|integer', 'activity_id' => 'required|integer', 'supervisor_id' => 'required|integer', 'team' => 'required|string|max:120', 'location' => 'required|string|max:160', 'work_date' => 'required|date_format:Y-m-d|after_or_equal:today', 'expected_at' => 'required|date_format:H:i']);
        $project = $this->workspace->project($data['project_id']);
        $activity = $this->workspace->record('activities', $data['activity_id']);
        abort_unless($activity->project_id === $project->id && $activity->planned_date === $data['work_date'], 422, 'Choose an activity for this project and date.');
        if (! in_array($data['supervisor_id'], $this->notifications->projectRecipients($project->organization_id, $project->id)) || ! DB::table('organization_users')->where('organization_id', $project->organization_id)->where('user_id', $data['supervisor_id'])->whereIn('role', ['owner', 'manager', 'supervisor'])->exists()) {
            throw ValidationException::withMessages(['supervisor_id' => 'Choose an assigned supervisor or manager.']);
        }
        DB::transaction(function () use ($data, $project) {
            $worker = $this->workspace->records('workers')->where('id', $data['worker_id'])->where('project_id', $project->id)->lockForUpdate()->first();
            abort_unless($worker, 404);
            if (DB::table('worker_deployments')->where('worker_id', $worker->id)->where('work_date', $data['work_date'])->exists()) {
                throw ValidationException::withMessages(['worker_id' => 'This worker is already deployed for this date.']);
            }
            $id = DB::table('worker_deployments')->insertGetId([...$data, 'organization_id' => $project->organization_id, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $this->workspace->audit('worker.deployed', 'worker_deployments', $id, $project->id, $data);
            $this->notifications->send($project->organization_id, $project->id, [$data['supervisor_id']], 'Worker assigned: '.$worker->name, '/workforce?date='.$data['work_date'].'&project='.$project->id);
        });

        return back()->with('success', 'Worker deployed. Attendance is calculated from gate records.');
    }
}
