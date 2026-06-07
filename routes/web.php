<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\Tenant\AgentPanelController;
use App\Http\Controllers\Tenant\CampaignController;
use App\Http\Controllers\Tenant\ComingSoonController;
use App\Http\Controllers\Tenant\ContactListController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\SettingController;
use App\Http\Controllers\Tenant\SupervisionController;
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
        Route::patch('campaigns/{campaign}/start', [CampaignController::class, 'start'])->name('campaigns.start');
        Route::patch('campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
        Route::patch('campaigns/{campaign}/restart', [CampaignController::class, 'restart'])->name('campaigns.restart');
        Route::patch('campaigns/{campaign}/recycle', [CampaignController::class, 'recycle'])->name('campaigns.recycle');
        Route::patch('campaigns/{campaign}/dial', [CampaignController::class, 'dial'])->name('campaigns.dial');
        Route::post('campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');
        Route::resource('campaigns', CampaignController::class)->except(['create', 'show', 'edit']);
        Route::get('agent', [AgentPanelController::class, 'index'])->name('agent.index');
        Route::patch('agent/status', [AgentPanelController::class, 'status'])->name('agent.status');
        Route::patch('agent/pause', [AgentPanelController::class, 'pause'])->name('agent.pause');
        Route::post('agent/calls', [AgentPanelController::class, 'storeCall'])->name('agent.calls.store');
        Route::patch('agent/calls/end', [AgentPanelController::class, 'endCall'])->name('agent.calls.end');
        Route::get('supervision', [SupervisionController::class, 'index'])->name('supervision.index');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('telephony', ComingSoonController::class)->defaults('module', 'telephony')->name('telephony.index');
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('settings/pause-reasons', [SettingController::class, 'storePauseReason'])->name('settings.pause-reasons.store');
        Route::put('settings/pause-reasons/{pauseReason}', [SettingController::class, 'updatePauseReason'])->name('settings.pause-reasons.update');
        Route::delete('settings/pause-reasons/{pauseReason}', [SettingController::class, 'destroyPauseReason'])->name('settings.pause-reasons.destroy');
        Route::post('settings/dispositions', [SettingController::class, 'storeDisposition'])->name('settings.dispositions.store');
        Route::put('settings/dispositions/{disposition}', [SettingController::class, 'updateDisposition'])->name('settings.dispositions.update');
        Route::delete('settings/dispositions/{disposition}', [SettingController::class, 'destroyDisposition'])->name('settings.dispositions.destroy');
        Route::post('settings/blacklist', [SettingController::class, 'storeBlacklist'])->name('settings.blacklist.store');
        Route::put('settings/blacklist/{blacklistNumber}', [SettingController::class, 'updateBlacklist'])->name('settings.blacklist.update');
        Route::delete('settings/blacklist/{blacklistNumber}', [SettingController::class, 'destroyBlacklist'])->name('settings.blacklist.destroy');
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
