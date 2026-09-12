<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
        $validated = $request->validate(['name' => 'required|string|max:120', 'company' => 'required|string|max:160', 'email' => 'required|email|max:254|unique:users', 'password' => ['required', 'confirmed', Password::min(6)]]);
        $user = DB::transaction(function () use ($validated) {
            $user = User::create(['name' => $validated['name'], 'email' => strtolower($validated['email']), 'password' => $validated['password']]);
            $organizationId = DB::table('organizations')->insertGetId(['name' => $validated['company'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('organization_users')->insert(['organization_id' => $organizationId, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);

            return $user;
        });
        $request->session()->forget('organization_id');
        Auth::login($user);
        $request->session()->regenerate();
        app(Workspace::class)->audit('workspace.created', 'organizations', app(Workspace::class)->organization()->id, null);

        return redirect('/dashboard');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt(['email' => strtolower($validated['email']), 'password' => $validated['password']])) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        $request->session()->regenerate();
        $request->session()->forget(['platform_mfa_user', 'platform_mfa_until', 'platform_pending_totp']);
        if ($request->user()->is_superadmin) {
            return redirect('/platform/security');
        }
        $membership = DB::table('organization_users')->join('organizations', 'organizations.id', '=', 'organization_users.organization_id')->where('user_id', Auth::id())->whereNull('suspended_at')->orderBy('organization_users.id')->first(['organization_id']);
        $request->session()->forget('organization_id');
        if (! $membership) {
            return redirect()->intended('/workspaces');
        }
        $request->session()->put('organization_id', $membership->organization_id);
        app(Workspace::class)->audit('auth.login', 'users', Auth::id(), null);

        return redirect()->intended('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
