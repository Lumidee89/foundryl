<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Workspace
{
    public function membership(): object
    {
        $user = auth()->user();
        abort_unless($user, 401);
        abort_if($user->is_superadmin, 403, 'Use the platform administration area.');
        $memberships = DB::table('organization_users')->where('user_id', $user->id);
        $membership = session()->has('organization_id') ? (clone $memberships)->where('organization_id', session('organization_id'))->first() : $memberships->orderBy('id')->first();
        abort_unless($membership, 403);
        abort_if(DB::table('organizations')->where('id', $membership->organization_id)->whereNotNull('suspended_at')->exists(), 403, 'This workspace is suspended. Contact Foundryl support.');

        return $membership;
    }

    public function organization(): object
    {
        return DB::table('organizations')->where('id', $this->membership()->organization_id)->first();
    }

    public function permissions(): array
    {
        return match ($this->membership()->role) {
            'owner' => ['team.manage', 'company.manage', 'instructions.manage', 'instructions.verify', 'reports.export', 'projects.manage', 'workers.manage', 'gate.manage', 'activities.manage', 'diary.manage', 'directions.manage', 'audit.view'],'manager' => ['instructions.manage', 'instructions.verify', 'reports.export', 'workers.manage', 'gate.manage', 'activities.manage', 'diary.manage', 'directions.manage'],'supervisor' => ['instructions.manage', 'gate.manage', 'activities.manage', 'diary.manage'],'security' => ['gate.manage'],default => []
        };
    }

    public function authorize(string $permission): void
    {
        abort_unless(in_array($permission, $this->permissions(), true), 403);
    }

    public function projects(): Builder
    {
        $membership = $this->membership();
        $query = DB::table('projects')->where('organization_id', $membership->organization_id);
        if ($membership->role !== 'owner') {
            $query->whereIn('id', DB::table('project_users')->select('project_id')->where('organization_id', $membership->organization_id)->where('user_id', auth()->id()));
        }

        return $query;
    }

    public function project(int $id): object
    {
        $project = $this->projects()->where('id', $id)->first();
        abort_unless($project, 404);

        return $project;
    }

    public function records(string $table): Builder
    {
        return DB::table($table)->where('organization_id', $this->membership()->organization_id)->whereIn('project_id', $this->projects()->select('id'));
    }

    public function record(string $table, int $id): object
    {
        $record = $this->records($table)->where('id', $id)->first();
        abort_unless($record, 404);

        return $record;
    }

    public function audit(string $action, string $type, int $id, ?int $project, array $after = [], ?array $before = null): void
    {
        DB::table('audit_logs')->insert(['organization_id' => $this->membership()->organization_id, 'project_id' => $project, 'actor_user_id' => auth()->id(), 'action' => $action, 'entity_type' => $type, 'entity_id' => $id, 'previous_state' => $before ? json_encode($before) : null, 'new_state' => json_encode($after), 'request_id' => (string) Str::uuid(), 'created_at' => now()]);
    }
}
