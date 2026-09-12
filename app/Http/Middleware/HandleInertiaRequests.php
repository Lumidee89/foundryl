<?php

namespace App\Http\Middleware;

use App\Services\Workspace;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $workspace = app(Workspace::class);
        $publicOrAccount = $request->is('api/v1/session', 'api/v1/login', 'api/v1/register', 'api/v1/logout', 'api/v1/account', 'api/v1/workspaces', '/', 'login', 'register', 'forgot-password', 'reset-password*', 'invitations/*', 'account*', 'email/*', 'workspaces*');

        return [...parent::share($request), 'auth' => ['user' => $request->user()?->only('id', 'name', 'email', 'is_superadmin')], 'organization' => fn () => $request->user() && ! $request->user()->is_superadmin && ! $publicOrAccount ? $workspace->organization() : null, 'permissions' => fn () => $request->user() && ! $request->user()->is_superadmin && ! $publicOrAccount ? $workspace->permissions() : [], 'flash' => ['success' => fn () => $request->session()->get('success')]];
    }
}
