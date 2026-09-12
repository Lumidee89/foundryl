<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InstructionController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WorkforceController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\RequireSuperadmin;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'));
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Auth', ['mode' => 'login']))->name('login');
    Route::get('/register', fn () => Inertia::render('Auth', ['mode' => 'register']));
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [WorkspaceController::class, 'dashboard'])->name('dashboard');
    Route::get('/projects', [WorkspaceController::class, 'projects']);
    Route::post('/projects', [WorkspaceController::class, 'createProject']);
    Route::get('/projects/{id}', [WorkspaceController::class, 'project'])->whereNumber('id');
    Route::patch('/activities/{id}', [WorkspaceController::class, 'updateActivity'])->whereNumber('id');
    Route::post('/gate/{id}/depart', [WorkspaceController::class, 'depart'])->whereNumber('id');
    Route::post('/directions', [WorkspaceController::class, 'direction']);
    Route::post('/directions/{id}/acknowledge', [WorkspaceController::class, 'acknowledge'])->whereNumber('id');
    Route::get('/{module}', [WorkspaceController::class, 'listing'])->whereIn('module', ['workers', 'gate', 'activities', 'diary']);
    Route::post('/{module}', [WorkspaceController::class, 'store'])->whereIn('module', ['workers', 'gate', 'activities', 'diary']);
});

Route::middleware('auth')->prefix('platform')->group(function () {
    Route::get('/security', [PlatformController::class, 'security']);
    Route::post('/security', [PlatformController::class, 'verify'])->middleware('throttle:6,1');
    Route::middleware(RequireSuperadmin::class)->group(function () {
        Route::get('/', [PlatformController::class, 'index']);
        Route::get('/companies/{id}', [PlatformController::class, 'company'])->whereNumber('id');
        Route::get('/companies/{id}/records/{module}', [PlatformController::class, 'records'])->whereNumber('id');
        Route::patch('/companies/{id}', [PlatformController::class, 'status'])->whereNumber('id');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [AccountController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AccountController::class, 'sendReset'])->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [AccountController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AccountController::class, 'reset'])->middleware('throttle:6,1');
});
Route::get('/invitations/{token}', [TeamController::class, 'invitationPage'])->middleware('throttle:30,1');
Route::post('/invitations/{token}', [TeamController::class, 'accept'])->middleware('throttle:6,1');
Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'settings'])->name('verification.notice');
    Route::patch('/account/password', [AccountController::class, 'password'])->middleware('throttle:6,1');
    Route::delete('/account/sessions', [AccountController::class, 'revokeSessions'])->middleware('throttle:6,1');
    Route::post('/email/verification', [AccountController::class, 'verification'])->middleware('throttle:3,1');
    Route::get('/email/verify/{id}/{hash}', [AccountController::class, 'verifyEmail'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::get('/workspaces', [TeamController::class, 'workspaces']);
    Route::post('/workspaces/switch', [TeamController::class, 'switchWorkspace']);
    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team/invitations', [TeamController::class, 'invite'])->middleware('throttle:10,1');
    Route::delete('/team/invitations/{id}', [TeamController::class, 'revoke']);
    Route::patch('/team/members/{id}', [TeamController::class, 'updateMember']);
    Route::delete('/team/members/{id}', [TeamController::class, 'removeMember']);
    Route::post('/team/departments', [TeamController::class, 'department']);
    Route::patch('/team/company', [TeamController::class, 'rename']);
    Route::get('/instructions', [InstructionController::class, 'index']);
    Route::post('/instructions', [InstructionController::class, 'store']);
    Route::patch('/instructions/{id}', [InstructionController::class, 'update']);
    Route::get('/notifications', [InstructionController::class, 'inbox']);
    Route::post('/notifications/{id}/read', [InstructionController::class, 'read']);
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/export', [ReportController::class, 'export']);
    Route::get('/audit', [ReportController::class, 'audit']);
});

Route::middleware('auth')->group(function () {
    Route::get('/visitors', [WorkforceController::class, 'visitors']);
    Route::post('/visitors', [WorkforceController::class, 'visit']);
    Route::patch('/visitors/{id}', [WorkforceController::class, 'visitorStatus']);
    Route::get('/workforce', [WorkforceController::class, 'deployments']);
    Route::post('/workforce', [WorkforceController::class, 'deploy']);
});

// Native clients use encrypted Laravel session cookies and CSRF protection.
Route::prefix('api/v1')->middleware('throttle:120,1')->group(function () {
    Route::get('/session', [\App\Http\Controllers\MobileController::class, 'session']);
    Route::middleware('guest')->group(function () {
        Route::post('/login', [\App\Http\Controllers\MobileController::class, 'login'])->middleware('throttle:6,1');
        Route::post('/register', [\App\Http\Controllers\MobileController::class, 'register'])->middleware('throttle:5,1');
    });
    Route::middleware('auth')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\MobileController::class, 'logout']);
        Route::get('/{module}/{id?}', [\App\Http\Controllers\MobileController::class, 'read'])->whereNumber('id');
        Route::post('/{module}/{id?}/{action?}', [\App\Http\Controllers\MobileController::class, 'write'])->whereNumber('id');
    });
});
