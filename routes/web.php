<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\Tenant\ComingSoonController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\TeamController;
use App\Http\Controllers\Tenant\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'tenant.active'])
    ->name('dashboard');

Route::middleware(['auth', 'super.admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function (): void {
        Route::resource('companies', CompanyController::class)->except(['create', 'show', 'edit']);
    });

Route::middleware(['auth', 'verified', 'tenant.active', 'tenant.user'])
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function (): void {
        Route::resource('users', UserController::class)->except(['create', 'show', 'edit']);
        Route::resource('teams', TeamController::class)->except(['create', 'show', 'edit']);
        Route::resource('roles', RoleController::class)->except(['create', 'show', 'edit']);
        Route::get('integrations', ComingSoonController::class)->name('integrations.index');
    });

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
