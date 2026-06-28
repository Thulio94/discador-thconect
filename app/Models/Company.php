<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'document', 'status'])]
class Company extends Model
{
    public function license(): HasOne
    {
        return $this->hasOne(CompanyLicense::class)->ofMany(
            ['starts_at' => 'max', 'id' => 'max'],
            fn ($query) => $query
                ->where('status', 'active')
                ->where('starts_at', '<=', today())
                ->where('ends_at', '>=', today())
        );
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(CompanyLicense::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function contactLists(): HasMany
    {
        return $this->hasMany(ContactList::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function agentStatuses(): HasMany
    {
        return $this->hasMany(AgentStatus::class);
    }

    public function agentPauses(): HasMany
    {
        return $this->hasMany(AgentPause::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function pauseReasons(): HasMany
    {
        return $this->hasMany(PauseReason::class);
    }

    public function dispositions(): HasMany
    {
        return $this->hasMany(Disposition::class);
    }

    public function blacklistNumbers(): HasMany
    {
        return $this->hasMany(BlacklistNumber::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function reportExports(): HasMany
    {
        return $this->hasMany(ReportExport::class);
    }

    public function reportSchedules(): HasMany
    {
        return $this->hasMany(ReportSchedule::class);
    }
}
