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

class InstructionController extends Controller
{
    public function __construct(private Workspace $workspace, private WorkspaceNotifications $notifications) {}

    public function index(Request $request): Response
    {
        $query = $this->workspace->records('site_instructions');
        if ($request->filled('project')) {
            $project = $this->workspace->project((int) $request->query('project'));
            $query->where('project_id', $project->id);
        }
        $projects = $this->workspace->projects()->get();
        $organization = $this->workspace->organization()->id;

        return Inertia::render('Instructions', ['instructions' => $query->orderByDesc('id')->paginate(20)->withQueryString(), 'projects' => $projects, 'members' => DB::table('organization_users')->join('users', 'users.id', '=', 'organization_users.user_id')->where('organization_id', $organization)->where(function ($query) use ($projects, $organization) {
            $query->where('role', 'owner')->orWhereIn('user_id', DB::table('project_users')->select('user_id')->where('organization_id', $organization)->whereIn('project_id', $projects->pluck('id')));
        })->get(['users.id', 'users.name', 'role'])->map(function ($member) use ($organization) {
            $member->project_ids = DB::table('project_users')->where('organization_id', $organization)->where('user_id', $member->id)->pluck('project_id');

            return $member;
        }), 'selectedProject' => $request->query('project', '')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->workspace->authorize('instructions.manage');
        $data = $request->validate(['project_id' => 'required|integer', 'title' => 'required|string|max:200', 'description' => 'required|string|max:5000', 'assigned_to' => 'required|integer', 'due_date' => 'required|date']);
        $project = $this->workspace->project($data['project_id']);
        $recipients = $this->notifications->projectRecipients($project->organization_id, $project->id);
        if (! in_array($data['assigned_to'], $recipients)) {
            throw ValidationException::withMessages(['assigned_to' => 'Choose a member with access to this project.']);
        }
        DB::transaction(function () use ($data, $project) {
            $id = DB::table('site_instructions')->insertGetId([...$data, 'organization_id' => $project->organization_id, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $this->workspace->audit('instruction.issued', 'site_instructions', $id, $project->id, $data);
            $this->notifications->send($project->organization_id, $project->id, [$data['assigned_to']], 'New instruction: '.$data['title'], '/instructions?project='.$project->id);
        });

        return back()->with('success', 'Instruction issued and recipient notified.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['Acknowledged', 'In Progress', 'Completed', 'Closed'])], 'version' => 'required|integer|min:1', 'completion_notes' => 'nullable|required_if:status,Completed|string|max:5000']);
        DB::transaction(function () use ($id, $data) {
            $record = $this->workspace->records('site_instructions')->where('id', $id)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless($record->version === $data['version'] || $record->version == (int) $data['version'], 409, 'This instruction changed. Reload before updating.');
            $next = ['Issued' => 'Acknowledged', 'Acknowledged' => 'In Progress', 'In Progress' => 'Completed', 'Completed' => 'Closed'];
            if (($next[$record->status] ?? null) !== $data['status']) {
                throw ValidationException::withMessages(['status' => 'Follow the instruction sequence: acknowledge, start, complete, then verify and close.']);
            }
            if ($data['status'] === 'Closed') {
                $this->workspace->authorize('instructions.verify');
                abort_if(auth()->id() === $record->assigned_to, 403, 'A different manager must verify completion.');
            } else {
                abort_unless(auth()->id() === $record->assigned_to, 403, 'Only the assigned recipient can update this instruction.');
            }
            $values = ['status' => $data['status'], 'version' => $record->version + 1, 'updated_at' => now()];
            if ($data['status'] === 'Completed') {
                $values['completion_notes'] = $data['completion_notes'];
            }
            DB::table('site_instructions')->where('id', $id)->update($values);
            $this->workspace->audit('instruction.updated', 'site_instructions', $id, $record->project_id, $values, ['status' => $record->status]);
            $this->notifications->send($record->organization_id, $record->project_id, [$record->created_by, $record->assigned_to], $record->title.': '.$data['status'], '/instructions?project='.$record->project_id);
        });

        return back()->with('success', 'Instruction updated.');
    }

    public function inbox(): Response
    {
        $query = $this->workspace->records('workspace_notifications')->where('user_id', auth()->id());

        return Inertia::render('Notifications', ['notifications' => $query->orderByDesc('id')->paginate(25)]);
    }

    public function read(int $id): RedirectResponse
    {
        $query = $this->workspace->records('workspace_notifications')->where('user_id', auth()->id())->where('id',$id);
        abort_unless((clone $query)->exists(),404);
        $query->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
