<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\AuditLogController as SuperAdminAuditLogController;
use App\Http\Controllers\SuperAdmin\CompanyController;
use App\Http\Controllers\SuperAdmin\GodModeController;
use App\Http\Controllers\SuperAdmin\SystemStatusController;
use App\Http\Controllers\Tenant\AgentPanelController;
use App\Http\Controllers\Tenant\AuditLogController as TenantAuditLogController;
use App\Http\Controllers\Tenant\CampaignController;
use App\Http\Controllers\Tenant\ComingSoonController;
use App\Http\Controllers\Tenant\ContactListController;
use App\Http\Controllers\Tenant\NotificationController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\ReportScheduleController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\SettingController;
use App\Http\Controllers\Tenant\SupervisionController;
use App\Http\Controllers\Tenant\TeamController;
use App\Http\Controllers\Tenant\TelephonyController;
use App\Http\Controllers\Tenant\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class);

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'tenant.active'])
    ->name('dashboard');

Route::middleware(['auth', 'super.admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function (): void {
        Route::get('system-status/poll', [SystemStatusController::class, 'poll'])->name('system-status.poll');
        Route::get('system-status', [SystemStatusController::class, 'index'])->name('system-status.index');
        Route::get('audit-logs', [SuperAdminAuditLogController::class, 'index'])->name('audit-logs.index');
        Route::delete('audit-logs/purge', [SuperAdminAuditLogController::class, 'purge'])->name('audit-logs.purge');
        Route::post('companies/{company}/god-mode', [GodModeController::class, 'enter'])->name('companies.god-mode.enter');
        Route::delete('god-mode', [GodModeController::class, 'leave'])->name('god-mode.leave');
        Route::resource('companies', CompanyController::class)->except(['create', 'show', 'edit']);
    });

Route::middleware(['auth', 'verified', 'god-mode', 'tenant.active', 'tenant.user'])
    ->prefix('tenant')
    ->name('tenant.')
    ->group(function (): void {
        Route::get('audit-logs', [TenantAuditLogController::class, 'index'])->middleware('can:tenant.audit.read')->name('audit-logs.index');
        Route::get('users', [UserController::class, 'index'])->middleware('can:tenant.users.read')->name('users.index');
        Route::post('users', [UserController::class, 'store'])->middleware('can:tenant.users.create')->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('can:tenant.users.update')->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('can:tenant.users.delete')->name('users.destroy');

        Route::get('teams', [TeamController::class, 'index'])->middleware('can:tenant.teams.read')->name('teams.index');
        Route::post('teams', [TeamController::class, 'store'])->middleware('can:tenant.teams.create')->name('teams.store');
        Route::put('teams/{team}', [TeamController::class, 'update'])->middleware('can:tenant.teams.update')->name('teams.update');
        Route::delete('teams/{team}', [TeamController::class, 'destroy'])->middleware('can:tenant.teams.delete')->name('teams.destroy');

        Route::get('roles', [RoleController::class, 'index'])->middleware('can:tenant.roles.read')->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->middleware('can:tenant.roles.create')->name('roles.store');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('can:tenant.roles.update')->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('can:tenant.roles.delete')->name('roles.destroy');

        Route::get('lists', [ContactListController::class, 'index'])->middleware('can:tenant.lists.read')->name('lists.index');
        Route::post('lists', [ContactListController::class, 'store'])->middleware('can:tenant.lists.create')->name('lists.store');
        Route::put('lists/{list}', [ContactListController::class, 'update'])->middleware('can:tenant.lists.update')->name('lists.update');
        Route::delete('lists/{list}', [ContactListController::class, 'destroy'])->middleware('can:tenant.lists.delete')->name('lists.destroy');

        Route::get('campaigns', [CampaignController::class, 'index'])->middleware('can:tenant.campaigns.read')->name('campaigns.index');
        Route::post('campaigns', [CampaignController::class, 'store'])->middleware('can:tenant.campaigns.create')->name('campaigns.store');
        Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->middleware('can:tenant.campaigns.update')->name('campaigns.update');
        Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->middleware('can:tenant.campaigns.delete')->name('campaigns.destroy');
        Route::post('campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->middleware('can:tenant.campaigns.create')->name('campaigns.duplicate');
        Route::patch('campaigns/{campaign}/start', [CampaignController::class, 'start'])->middleware('can:tenant.campaigns.operate')->name('campaigns.start');
        Route::patch('campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->middleware('can:tenant.campaigns.operate')->name('campaigns.pause');
        Route::patch('campaigns/{campaign}/restart', [CampaignController::class, 'restart'])->middleware('can:tenant.campaigns.operate')->name('campaigns.restart');
        Route::patch('campaigns/{campaign}/recycle', [CampaignController::class, 'recycle'])->middleware('can:tenant.campaigns.operate')->name('campaigns.recycle');
        Route::patch('campaigns/{campaign}/dial', [CampaignController::class, 'dial'])->middleware(['can:tenant.campaigns.operate', 'god-mode.telephony-safe'])->name('campaigns.dial');

        Route::get('agent', [AgentPanelController::class, 'index'])->middleware(['can:tenant.agent.access', 'god-mode.telephony-safe'])->name('agent.index');
        Route::patch('agent/status', [AgentPanelController::class, 'status'])->middleware(['can:tenant.agent.access', 'god-mode.telephony-safe'])->name('agent.status');
        Route::patch('agent/pause', [AgentPanelController::class, 'pause'])->middleware(['can:tenant.agent.access', 'god-mode.telephony-safe'])->name('agent.pause');
        Route::post('agent/calls', [AgentPanelController::class, 'storeCall'])->middleware(['can:tenant.agent.access', 'god-mode.telephony-safe'])->name('agent.calls.store');
        Route::patch('agent/calls/end', [AgentPanelController::class, 'endCall'])->middleware(['can:tenant.agent.access', 'god-mode.telephony-safe'])->name('agent.calls.end');

        Route::get('supervision/poll', [SupervisionController::class, 'poll'])->middleware('can:tenant.supervision.read')->name('supervision.poll');
        Route::get('supervision', [SupervisionController::class, 'index'])->middleware('can:tenant.supervision.read')->name('supervision.index');

        Route::get('reports', [ReportController::class, 'index'])->middleware('can:tenant.reports.read')->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->middleware('can:tenant.reports.export')->name('reports.export');
        Route::get('reports/export/xlsx', [ReportController::class, 'exportXlsx'])->middleware('can:tenant.reports.export')->name('reports.export-xlsx');
        Route::get('reports/export/pdf', [ReportController::class, 'exportPdf'])->middleware('can:tenant.reports.export')->name('reports.export-pdf');
        Route::post('reports/schedules', [ReportScheduleController::class, 'store'])->middleware('can:tenant.reports.schedules')->name('reports.schedules.store');
        Route::put('reports/schedules/{reportSchedule}', [ReportScheduleController::class, 'update'])->middleware('can:tenant.reports.schedules')->name('reports.schedules.update');
        Route::delete('reports/schedules/{reportSchedule}', [ReportScheduleController::class, 'destroy'])->middleware('can:tenant.reports.schedules')->name('reports.schedules.destroy');
        Route::patch('reports/schedules/{reportSchedule}/status', [ReportScheduleController::class, 'toggleStatus'])->middleware('can:tenant.reports.schedules')->name('reports.schedules.status');
        Route::post('reports/schedules/{reportSchedule}/run', [ReportScheduleController::class, 'run'])->middleware('can:tenant.reports.schedules')->name('reports.schedules.run');

        Route::get('telephony', [TelephonyController::class, 'index'])->middleware('can:tenant.telephony.read')->name('telephony.index');
        Route::put('telephony', [TelephonyController::class, 'update'])->middleware('can:tenant.telephony.update')->name('telephony.update');
        Route::get('settings', [SettingController::class, 'index'])->middleware('can:tenant.settings.read')->name('settings.index');
        Route::put('settings/operational-parameters', [SettingController::class, 'updateOperationalParameters'])->middleware('can:tenant.settings.update')->name('settings.operational-parameters.update');
        Route::post('settings/pause-reasons', [SettingController::class, 'storePauseReason'])->middleware('can:tenant.settings.create')->name('settings.pause-reasons.store');
        Route::put('settings/pause-reasons/{pauseReason}', [SettingController::class, 'updatePauseReason'])->middleware('can:tenant.settings.update')->name('settings.pause-reasons.update');
        Route::delete('settings/pause-reasons/{pauseReason}', [SettingController::class, 'destroyPauseReason'])->middleware('can:tenant.settings.delete')->name('settings.pause-reasons.destroy');
        Route::post('settings/dispositions', [SettingController::class, 'storeDisposition'])->middleware('can:tenant.settings.create')->name('settings.dispositions.store');
        Route::put('settings/dispositions/{disposition}', [SettingController::class, 'updateDisposition'])->middleware('can:tenant.settings.update')->name('settings.dispositions.update');
        Route::delete('settings/dispositions/{disposition}', [SettingController::class, 'destroyDisposition'])->middleware('can:tenant.settings.delete')->name('settings.dispositions.destroy');
        Route::post('settings/blacklist', [SettingController::class, 'storeBlacklist'])->middleware('can:tenant.settings.create')->name('settings.blacklist.store');
        Route::put('settings/blacklist/{blacklistNumber}', [SettingController::class, 'updateBlacklist'])->middleware('can:tenant.settings.update')->name('settings.blacklist.update');
        Route::delete('settings/blacklist/{blacklistNumber}', [SettingController::class, 'destroyBlacklist'])->middleware('can:tenant.settings.delete')->name('settings.blacklist.destroy');

        Route::get('integrations', ComingSoonController::class)->middleware('can:tenant.integrations.read')->defaults('module', 'integrations')->name('integrations.index');
        Route::get('notifications/poll', [NotificationController::class, 'poll'])->middleware('can:tenant.notifications.read')->name('notifications.poll');
        Route::patch('notifications/preferences', [NotificationController::class, 'updatePreferences'])->middleware('can:tenant.notifications.update')->name('notifications.preferences.update');
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('can:tenant.notifications.update')->name('notifications.read-all');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('can:tenant.notifications.update')->name('notifications.read');
    });

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
