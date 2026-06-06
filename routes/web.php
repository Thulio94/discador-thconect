<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\Tenant\CampaignController;
use App\Http\Controllers\Tenant\ComingSoonController;
use App\Http\Controllers\Tenant\ContactListController;
use App\Http\Controllers\Tenant\NotificationController;
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
        Route::resource('lists', ContactListController::class)->except(['create', 'show', 'edit']);
        Route::resource('campaigns', CampaignController::class)->except(['create', 'show', 'edit']);
        Route::get('agent', ComingSoonController::class)->defaults('module', 'agent')->name('agent.index');
        Route::get('supervision', ComingSoonController::class)->defaults('module', 'supervision')->name('supervision.index');
        Route::get('reports', ComingSoonController::class)->defaults('module', 'reports')->name('reports.index');
        Route::get('telephony', ComingSoonController::class)->defaults('module', 'telephony')->name('telephony.index');
        Route::get('settings', ComingSoonController::class)->defaults('module', 'settings')->name('settings.index');
        Route::get('integrations', ComingSoonController::class)->defaults('module', 'integrations')->name('integrations.index');
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    });

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
