<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class WorkspaceNotifications
{
    public function send(int $organization, int $project, array $users, string $title, string $url): void
    {
        foreach (array_unique($users) as $user) {
            $member = DB::table('organization_users')->where('organization_id', $organization)->where('user_id', $user)->first();
            if (! $member || ($member->role !== 'owner' && ! DB::table('project_users')->where('organization_id', $organization)->where('project_id', $project)->where('user_id', $user)->exists())) {
                continue;
            }
            DB::table('workspace_notifications')->insert(['organization_id' => $organization, 'project_id' => $project, 'user_id' => $user, 'title' => $title, 'url' => $url, 'created_at' => now()]);
        }
    }

    public function projectRecipients(int $organization, int $project): array
    {
        return DB::table('organization_users')->where('organization_id', $organization)->where(function ($query) use ($organization, $project) {
            $query->where('role', 'owner')->orWhereIn('user_id', DB::table('project_users')->select('user_id')->where('organization_id', $organization)->where('project_id', $project));
        })->pluck('user_id')->all();
    }
}
