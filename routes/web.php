<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pending-approval', [AuthController::class, 'pendingApproval'])
    ->name('pending-approval');

Route::middleware('guest')->group(function (): void {
    Route::get('/register/{agentSlug?}', [AuthController::class, 'showRegisterForm'])
        ->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/login', [AuthController::class, 'showLoginForm'])
        ->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/admin/dashboard', fn () => view('dashboards.admin'))->name('admin.dashboard');
    Route::get('/agent/dashboard', fn () => view('dashboards.agent'))->name('agent.dashboard');
    Route::get('/buyer/dashboard', fn () => view('dashboards.buyer'))->name('buyer.dashboard');

    Route::get('/dashboard', function () {
        $user = auth()->user()?->loadMissing('role');

        return match ($user?->role?->slug) {
            \App\Models\Role::SLUG_SUPPLIER => redirect()->route('admin.dashboard'),
            \App\Models\Role::SLUG_AGENT => redirect()->route('agent.dashboard'),
            \App\Models\Role::SLUG_BUYER => redirect()->route('buyer.dashboard'),
            default => redirect('/'),
        };
    })->name('dashboard');
});
