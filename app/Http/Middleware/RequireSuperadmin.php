<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_superadmin, 403);
        if ($request->session()->get('platform_mfa_user') !== $request->user()->id || $request->session()->get('platform_mfa_until', 0) < time()) {
            return redirect('/platform/security');
        }

        return $next($request);
    }
}
