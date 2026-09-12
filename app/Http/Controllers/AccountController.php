<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function forgot(): Response
    {
        return Inertia::render('Recovery', ['mode' => 'forgot']);
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:254']);
        Password::sendResetLink(['email' => strtolower($data['email'])]);

        return back()->with('success', 'If that email has an account, a reset link has been sent. Check your inbox.');
    }

    public function resetForm(Request $request, string $token): Response
    {
        return Inertia::render('Recovery', ['mode' => 'reset', 'token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(6)]]);
        $data['email'] = strtolower($data['email']);
        $status = Password::reset($data, function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $user->password = $password;
                $user->remember_token = Str::random(60);
                $user->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            });
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This reset link is invalid or expired. Request a new link.']);
        }

        return redirect('/login')->with('success', 'Password reset. You can now log in.');
    }

    public function settings(Request $request): Response
    {
        return Inertia::render('Account', ['profile' => $request->user()->only('name', 'email', 'email_verified_at'), 'sessions' => DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity'])->map(fn ($session) => ['current' => $session->id === $request->session()->getId(), 'ip_address' => $session->ip_address, 'user_agent' => $session->user_agent, 'last_activity' => $session->last_activity])]);
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', PasswordRule::min(6)]]);
        $user = $request->user();
        DB::transaction(function () use ($user, $data, $request) {
            $user->password = $data['password'];
            $user->remember_token = Str::random(60);
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        });
        $request->session()->forget(['platform_mfa_user', 'platform_mfa_until']);
        $request->session()->regenerate();

        return back()->with('success', 'Password changed. Other sessions have been signed out.');
    }

    public function revokeSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|current_password']);
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
        $request->user()->forceFill(['remember_token' => Str::random(60)])->save();

        return back()->with('success', 'Other sessions have been signed out.');
    }

    public function verification(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('success', 'Verification link sent. Check your inbox.');
    }

    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        abort_unless((string) $request->user()->id === $id && hash_equals(sha1($request->user()->email), $hash), 403);
        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect('/account')->with('success', 'Email address verified.');
    }
}
