<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class MobileController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        return response()->json(['csrf_token' => csrf_token(), 'user' => $request->user()?->only('id', 'name', 'email', 'is_superadmin')])->header('Cache-Control', 'no-store');
    }

    public function login(Request $request): JsonResponse
    {
        app(AuthController::class)->login($request);
        if ($request->user()->is_superadmin) {
            app(AuthController::class)->logout($request);
            abort(403, 'Use the web platform dashboard for superadmin access and authenticator verification.');
        }

        return $this->session($request);
    }

    public function register(Request $request): JsonResponse
    {
        app(AuthController::class)->register($request);

        return $this->session($request);
    }

    public function logout(Request $request): JsonResponse
    {
        app(AuthController::class)->logout($request);

        return response()->json(['message' => 'Signed out.']);
    }

    private function payload(Response $page, Request $request): JsonResponse
    {
        $original = $request->header('X-Inertia');
        $request->headers->set('X-Inertia', 'true');
        try {
            $props = $page->toResponse($request)->getData(true)['props'];
        } finally {
            if ($original === null) {
                $request->headers->remove('X-Inertia');
            } else {
                $request->headers->set('X-Inertia', $original);
            }
        }

        return response()->json($props)->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request, string $module, ?int $id = null): JsonResponse
    {
        abort_if($request->user()->is_superadmin, 403);
        $workspace = app(WorkspaceController::class);
        $page = match ($module) {
            'dashboard' => $workspace->dashboard(),
            'projects' => $id ? $workspace->project($id) : $workspace->projects(),
            'workers', 'gate', 'activities', 'diary' => $workspace->listing($request, $module),
            'instructions' => app(InstructionController::class)->index($request),
            'notifications' => app(InstructionController::class)->inbox(),
            'visitors' => app(WorkforceController::class)->visitors($request),
            'workforce' => app(WorkforceController::class)->deployments($request),
            'reports' => app(ReportController::class)->index($request),
            'account' => app(AccountController::class)->settings($request),
            'workspaces' => app(TeamController::class)->workspaces($request),
            default => abort(404),
        };

        return $this->payload($page, $request);
    }

    public function write(Request $request, string $module, ?int $id = null, ?string $action = null): JsonResponse
    {
        abort_if($request->user()->is_superadmin, 403);
        $workspace = app(WorkspaceController::class);
        match (true) {
            $module === 'projects' && ! $id => $workspace->createProject($request),
            in_array($module, ['workers', 'gate', 'activities', 'diary'], true) && ! $id => $workspace->store($request, $module),
            $module === 'activities' && $id !== null => $workspace->updateActivity($request, $id),
            $module === 'gate' && $action === 'depart' && $id !== null => $workspace->depart($id),
            $module === 'directions' && ! $id => $workspace->direction($request),
            $module === 'directions' && $action === 'acknowledge' && $id !== null => $workspace->acknowledge($id),
            $module === 'instructions' && ! $id => app(InstructionController::class)->store($request),
            $module === 'instructions' && $id !== null => app(InstructionController::class)->update($request, $id),
            $module === 'notifications' && $action === 'read' && $id !== null => app(InstructionController::class)->read($id),
            $module === 'visitors' && ! $id => app(WorkforceController::class)->visit($request),
            $module === 'visitors' && $id !== null => app(WorkforceController::class)->visitorStatus($request, $id),
            $module === 'workforce' && ! $id => app(WorkforceController::class)->deploy($request),
            $module === 'workspaces' && ! $id => app(TeamController::class)->switchWorkspace($request),
            default => abort(404),
        };

        return response()->json(['message' => $request->session()->get('success', 'Saved.')])->header('Cache-Control', 'no-store');
    }
}
