<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class PlatformController extends Controller
{
    private function audit(string $action, ?int $organization = null, ?string $reason = null): void
    {
        DB::table('platform_audit_logs')->insert(['actor_user_id' => auth()->id(), 'organization_id' => $organization, 'action' => $action, 'reason' => $reason, 'created_at' => now()]);
    }

    public function security(Request $request): Response
    {
        abort_unless($request->user()->is_superadmin, 403);
        $setup = ! $request->user()->platform_totp_confirmed_at;
        if ($setup && ! $request->session()->has('platform_pending_totp')) {
            $request->session()->put('platform_pending_totp', (new Google2FA)->generateSecretKey(32));
        }

        return Inertia::render('PlatformSecurity', ['setup' => $setup, 'secret' => $setup ? $request->session()->get('platform_pending_totp') : null]);
    }

    public function verify(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_superadmin, 403);
        $validated = $request->validate(['code' => 'required|digits:6']);
        DB::transaction(function () use ($request, $validated) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $secret = $user->platform_totp_confirmed_at ? $user->platform_totp_secret : $request->session()->get('platform_pending_totp');
            $step = $secret ? (new Google2FA)->verifyKeyNewer($secret, $validated['code'], $user->platform_totp_last_step ?? 0, 1) : false;
            if ($step === false) {
                throw ValidationException::withMessages(['code' => 'Invalid or already used code. Try the next code from your authenticator.']);
            }
            $user->platform_totp_secret = $secret;
            $user->platform_totp_confirmed_at ??= now();
            $user->platform_totp_last_step = $step;
            $user->save();
            $this->audit('superadmin.mfa_verified');
        });
        $request->session()->regenerate();
        $request->session()->forget('platform_pending_totp');
        $request->session()->put(['platform_mfa_user' => $request->user()->id, 'platform_mfa_until' => time() + 3600]);

        return redirect('/platform');
    }

    public function index(Request $request): Response
    {
        $search = $request->validate(['search' => 'nullable|string|max:120'])['search'] ?? '';
        $companies = DB::table('organizations')->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))->latest()->paginate(15)->withQueryString();
        $this->audit('platform.overview_viewed');

        return Inertia::render('Platform', ['companies' => $companies, 'search' => $search, 'stats' => ['Companies' => DB::table('organizations')->count(), 'Projects' => DB::table('projects')->count(), 'Registered users' => DB::table('users')->where('is_superadmin', false)->count(), 'Workers onsite' => DB::table('gate_entries')->whereNull('departed_at')->count()], 'events' => DB::table('platform_audit_logs')->join('users', 'users.id', '=', 'platform_audit_logs.actor_user_id')->orderByDesc('platform_audit_logs.id')->limit(15)->get(['platform_audit_logs.id', 'users.name as actor', 'action', 'organization_id', 'reason', 'platform_audit_logs.created_at'])]);
    }

    public function company(int $id): Response
    {
        $company = DB::table('organizations')->where('id', $id)->first();
        abort_unless($company, 404);
        $this->audit('company.viewed', $id);
        $counts = [];
        foreach (['projects', 'workers', 'activities', 'gate_entries', 'diary_entries', 'directions', 'site_instructions', 'visitor_entries', 'worker_deployments', 'audit_logs'] as $table) {
            $counts[$table] = DB::table($table)->where('organization_id', $id)->count();
        }

        return Inertia::render('PlatformCompany', ['company' => $company, 'counts' => $counts, 'members' => DB::table('organization_users')->join('users', 'users.id', '=', 'organization_users.user_id')->where('organization_id', $id)->get(['users.name', 'users.email', 'organization_users.role'])]);
    }

    public function records(Request $request, int $id, string $module): Response
    {
        $columns = match ($module) {
            'projects' => ['id', 'name', 'location', 'status', 'start_date'],
            'workers' => ['id', 'project_id', 'name', 'trade', 'phone'],
            'activities' => ['id', 'project_id', 'title', 'location', 'responsible', 'planned_date', 'status', 'progress', 'reason'],
            'gate_entries' => ['id', 'project_id', 'worker_id', 'arrived_at', 'departed_at'],
            'diary_entries' => ['id', 'project_id', 'title', 'notes', 'weather', 'entry_date'],
            'directions' => ['id', 'project_id', 'message', 'created_at'],
            'site_instructions' => ['id', 'project_id', 'title', 'description', 'assigned_to', 'due_date', 'status', 'completion_notes'],
            'visitor_entries' => ['id', 'project_id', 'name', 'purpose', 'host_id', 'status', 'arrived_at', 'departed_at'],
            'worker_deployments' => ['id', 'project_id', 'worker_id', 'supervisor_id', 'activity_id', 'team', 'location', 'work_date', 'expected_at'],
            'audit_logs' => ['id', 'project_id', 'actor_user_id', 'action', 'entity_type', 'entity_id', 'created_at'],
            default => abort(404)
        };
        $company = DB::table('organizations')->where('id', $id)->first();
        abort_unless($company, 404);
        $this->audit('company.records_viewed', $id, $module);

        return Inertia::render('PlatformRecords', ['company' => $company, 'module' => $module, 'columns' => $columns, 'records' => DB::table($module)->where('organization_id', $id)->orderByDesc('id')->paginate(25)->withQueryString()]);
    }

    public function status(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate(['suspended' => 'required|boolean', 'reason' => 'required|string|min:10|max:1000', 'password' => 'required|current_password']);
        DB::transaction(function () use ($id, $validated) {
            $company = DB::table('organizations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($company, 404);
            DB::table('organizations')->where('id', $id)->update(['suspended_at' => $validated['suspended'] ? now() : null, 'updated_at' => now()]);
            $this->audit($validated['suspended'] ? 'company.suspended' : 'company.reactivated', $id, $validated['reason']);
        });

        return back()->with('success', $validated['suspended'] ? 'Company workspace suspended.' : 'Company workspace reactivated.');
    }
}
