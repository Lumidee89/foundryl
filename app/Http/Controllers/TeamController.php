<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\WorkspaceInvitation;
use App\Services\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    private const ROLES = ['manager', 'supervisor', 'security', 'viewer'];

    public function __construct(private Workspace $workspace) {}

    public function index(): Response
    {
        $this->workspace->authorize('team.manage');
        $organization = $this->workspace->organization()->id;

        return Inertia::render('Team', ['members' => DB::table('organization_users')->join('users', 'users.id', '=', 'organization_users.user_id')->where('organization_id', $organization)->select('organization_users.id', 'users.id as user_id', 'users.name', 'users.email', 'role', 'department')->get()->map(function ($member) use ($organization) {
            $member->project_ids = DB::table('project_users')->where('organization_id', $organization)->where('user_id', $member->user_id)->pluck('project_id');

            return $member;
        }), 'projects' => $this->workspace->projects()->get(), 'departments' => DB::table('departments')->where('organization_id', $organization)->orderBy('name')->get(), 'invitations' => DB::table('invitations')->where('organization_id', $organization)->latest()->limit(50)->get(['id', 'email', 'role', 'expires_at', 'accepted_at', 'revoked_at']), 'roles' => self::ROLES]);
    }

    private function validateAccess(Request $request): array
    {
        $data = $request->validate(['role' => ['required', Rule::in(self::ROLES)], 'project_ids' => 'required|array|min:1', 'project_ids.*' => 'integer|distinct', 'department' => 'nullable|string|max:120']);
        foreach ($data['project_ids'] as $project) {
            $this->workspace->project((int) $project);
        }
        if (! empty($data['department']) && ! DB::table('departments')->where('organization_id', $this->workspace->organization()->id)->where('name', $data['department'])->exists()) {
            throw ValidationException::withMessages(['department' => 'Choose a department in this company.']);
        }

        return $data;
    }

    public function invite(Request $request): RedirectResponse
    {
        $this->workspace->authorize('team.manage');
        $request->merge(['email' => is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : $request->input('email')]);
        $email = $request->validate(['email' => 'required|email|max:254'])['email'];
        $data = $this->validateAccess($request);
        $organization = $this->workspace->organization();
        $existing = User::where('email', $email)->first();
        if ($existing && ($existing->is_superadmin || DB::table('organization_users')->where('organization_id', $organization->id)->where('user_id', $existing->id)->exists())) {
            throw ValidationException::withMessages(['email' => 'That account cannot be invited, or already belongs to this company.']);
        }
        $token = Str::random(64);
        $id = DB::transaction(function () use ($data, $email, $organization, $token) {
            DB::table('invitations')->where('organization_id', $organization->id)->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $id = DB::table('invitations')->insertGetId(['organization_id' => $organization->id, 'email' => $email, 'role' => $data['role'], 'department' => $data['department'] ?? null, 'project_ids' => json_encode($data['project_ids']), 'token_hash' => hash('sha256', $token), 'invited_by' => auth()->id(), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
            $this->workspace->audit('invitation.created', 'invitations', $id, null, ['email' => $email, 'role' => $data['role'], 'project_ids' => $data['project_ids']]);

            return $id;
        });
        try {
            Notification::route('mail', $email)->notify(new WorkspaceInvitation($organization->name, url('/invitations/'.$token)));
        } catch (\Throwable $exception) {
            DB::table('invitations')->where('id', $id)->update(['revoked_at' => now()]);
            report($exception);
            throw ValidationException::withMessages(['email' => 'The invitation could not be delivered. Check mail configuration and try again.']);
        }

        return back()->with('success', 'Invitation sent. It expires in 7 days.');
    }

    public function revoke(int $id): RedirectResponse
    {
        $this->workspace->authorize('team.manage');
        DB::transaction(function () use ($id) {
            $query = DB::table('invitations')->where('organization_id', $this->workspace->organization()->id)->where('id', $id);
            $invite = (clone $query)->lockForUpdate()->first();
            abort_unless($invite, 404);
            abort_if($invite->accepted_at, 409, 'This invitation has already been accepted.');
            $query->update(['revoked_at' => now()]);
            $this->workspace->audit('invitation.revoked', 'invitations', $id, null);
        });

        return back()->with('success', 'Invitation revoked.');
    }

    private function invitation(string $token, bool $lock = false): object
    {
        $query = DB::table('invitations')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->whereNull('accepted_at')->where('expires_at', '>', now());
        if ($lock) {
            $query->lockForUpdate();
        }$invite = $query->first();
        abort_unless($invite, 404, 'This invitation has expired or is no longer available.');
        abort_unless(DB::table('organizations')->where('id', $invite->organization_id)->whereNull('suspended_at')->exists(), 403);

        return $invite;
    }

    public function invitationPage(string $token): Response
    {
        $invite = $this->invitation($token);
        if (! auth()->check() && User::where('email', $invite->email)->exists()) {
            session()->put('url.intended', url('/invitations/'.$token));
        }

        return Inertia::render('Invitation', ['invitation' => ['email' => $invite->email, 'role' => $invite->role, 'company' => DB::table('organizations')->where('id', $invite->organization_id)->value('name')], 'token' => $token, 'existingAccount' => User::where('email', $invite->email)->exists()]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $user = DB::transaction(function () use ($request, $token) {
            $invite = $this->invitation($token, true);
            $existing = User::where('email', $invite->email)->first();
            if ($existing) {
                abort_unless($request->user() && $request->user()->id === $existing->id && ! $existing->is_superadmin, 403, 'Log in with the invited email address first.');
                $user = $existing;
            } else {
                abort_if($request->user(), 403, 'Log out before creating the invited account.');
                $data = $request->validate(['name' => 'required|string|max:120', 'password' => ['required', 'confirmed', Password::min(6)]]);
                $user = User::create(['name' => $data['name'], 'email' => $invite->email, 'password' => $data['password']]);
            }
            if (DB::table('organization_users')->where('organization_id', $invite->organization_id)->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'You already belong to this workspace.']);
            }
            $projectIds = json_decode($invite->project_ids, true);
            abort_unless(DB::table('projects')->where('organization_id', $invite->organization_id)->whereIn('id', $projectIds)->count() === count($projectIds), 409);
            DB::table('organization_users')->insert(['organization_id' => $invite->organization_id, 'user_id' => $user->id, 'role' => $invite->role, 'department' => $invite->department, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($projectIds as $project) {
                DB::table('project_users')->insert(['organization_id' => $invite->organization_id, 'project_id' => $project, 'user_id' => $user->id]);
            }
            $user->markEmailAsVerified();
            DB::table('invitations')->where('id', $invite->id)->update(['accepted_at' => now()]);
            Auth::login($user);
            $request->session()->put('organization_id', $invite->organization_id);
            $this->workspace->audit('invitation.accepted', 'invitations', $invite->id, null, ['user_id' => $user->id]);

            return $user;
        });
        $request->session()->regenerate();

        return redirect('/dashboard')->with('success', 'Welcome to your team.');
    }

    public function updateMember(Request $request, int $id): RedirectResponse
    {
        $this->workspace->authorize('team.manage');
        $data = $this->validateAccess($request);
        DB::transaction(function () use ($data, $id) {
            $organization = $this->workspace->organization()->id;
            $query = DB::table('organization_users')->where('organization_id', $organization)->where('id', $id);
            $member = (clone $query)->lockForUpdate()->first();
            abort_unless($member, 404);
            abort_if($member->role === 'owner' || $member->user_id === auth()->id(), 403, 'The owner role cannot be changed here.');
            $query->update(['role' => $data['role'], 'department' => $data['department'] ?? null, 'updated_at' => now()]);
            DB::table('project_users')->where('organization_id', $organization)->where('user_id', $member->user_id)->delete();
            foreach ($data['project_ids'] as $project) {
                DB::table('project_users')->insert(['organization_id' => $organization, 'project_id' => $project, 'user_id' => $member->user_id]);
            }
            $this->workspace->audit('member.access_updated', 'organization_users', $id, null, $data, ['role' => $member->role]);
        });

        return back()->with('success', 'Member access updated. Changes take effect on their next request.');
    }

    public function removeMember(int $id): RedirectResponse
    {
        $this->workspace->authorize('team.manage');
        DB::transaction(function () use ($id) {
            $organization = $this->workspace->organization()->id;
            $member = DB::table('organization_users')->where('organization_id', $organization)->where('id', $id)->lockForUpdate()->first();
            abort_unless($member, 404);
            abort_if($member->role === 'owner' || $member->user_id === auth()->id(), 403);
            DB::table('project_users')->where('organization_id', $organization)->where('user_id', $member->user_id)->delete();
            DB::table('organization_users')->where('id', $id)->delete();
            $this->workspace->audit('member.removed', 'organization_users', $id, null, ['user_id' => $member->user_id]);
        });

        return back()->with('success', 'Workspace access removed. Historical records are retained.');
    }

    public function department(Request $request): RedirectResponse
    {
        $this->workspace->authorize('team.manage');
        $organization = $this->workspace->organization()->id;
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('departments')->where('organization_id', $organization)]]);
        DB::transaction(function () use ($organization, $data) {
            $id = DB::table('departments')->insertGetId([...$data, 'organization_id' => $organization, 'created_at' => now(), 'updated_at' => now()]);
            $this->workspace->audit('department.created', 'departments', $id, null, $data);
        });

        return back()->with('success', 'Department created.');
    }

    public function rename(Request $request): RedirectResponse
    {
        $this->workspace->authorize('company.manage');
        $data = $request->validate(['name' => 'required|string|max:160']);
        DB::transaction(function () use ($data) {
            $organization = $this->workspace->organization();
            DB::table('organizations')->where('id', $organization->id)->update([...$data, 'updated_at' => now()]);
            $this->workspace->audit('company.updated', 'organizations', $organization->id, null, $data, ['name' => $organization->name]);
        });

        return back()->with('success', 'Company name updated.');
    }

    public function workspaces(Request $request): Response
    {
        abort_if($request->user()->is_superadmin, 403);

        return Inertia::render('Workspaces', ['workspaces' => DB::table('organization_users')->join('organizations', 'organizations.id', '=', 'organization_users.organization_id')->where('user_id', auth()->id())->get(['organizations.id', 'organizations.name', 'suspended_at', 'role'])]);
    }

    public function switchWorkspace(Request $request): RedirectResponse
    {
        $id = $request->validate(['organization_id' => 'required|integer'])['organization_id'];
        abort_if($request->user()->is_superadmin, 403);
        abort_unless(DB::table('organization_users')->where('user_id', auth()->id())->where('organization_id', $id)->exists(), 403);
        abort_unless(DB::table('organizations')->where('id', $id)->whereNull('suspended_at')->exists(), 403);
        $request->session()->put('organization_id', (int) $id);

        return redirect('/dashboard');
    }
}
