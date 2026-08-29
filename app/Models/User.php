<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'company_id',
    'role_id',
    'user_type',
    'extension',
    'sip_secret',
    'status',
    'is_super_admin',
    'theme_preference',
    'items_per_page',
])]
#[Hidden(['password', 'remember_token', 'sip_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const TYPES = ['agent', 'supervisor', 'company_admin', 'super_admin'];

    public const THEME_PREFERENCES = ['thconectclaro', 'thconectescuro'];

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => mb_strtolower(trim((string) $value)),
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->where('teams.company_id', $this->company_id)
            ->withPivotValue('company_id', $this->company_id)
            ->withPivot('membership_type')
            ->withTimestamps();
    }

    public function assignedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('company_id', 'membership_type')
            ->withTimestamps();
    }

    public function assignedCampaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_user')
            ->withPivot('company_id')
            ->withTimestamps();
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    public function authoredAuditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function agentStatuses(): HasMany
    {
        return $this->hasMany(AgentStatus::class);
    }

    public function currentAgentStatus(): HasOne
    {
        return $this->hasOne(AgentStatus::class)->latestOfMany();
    }

    public function agentPauses(): HasMany
    {
        return $this->hasMany(AgentPause::class);
    }

    public function agentCampaignSessions(): HasMany
    {
        return $this->hasMany(AgentCampaignSession::class);
    }

    public function currentAgentCampaignSession(): HasOne
    {
        return $this->hasOne(AgentCampaignSession::class)->whereNull('ended_at')->latestOfMany();
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function reportExports(): HasMany
    {
        return $this->hasMany(ReportExport::class);
    }

    public function reportSchedules(): HasMany
    {
        return $this->hasMany(ReportSchedule::class);
    }

    public function hasPermission(string $permissionKey): bool
    {
        if ($this->is_super_admin || ! $this->company_id) {
            return false;
        }

        $role = $this->role;

        if (! $role || $role->company_id !== $this->company_id) {
            return false;
        }

        if ($this->user_type === 'company_admin' && $role->is_admin) {
            return true;
        }

        if ($this->user_type === 'agent') {
            $agentPermissions = [
                'dashboard.read',
                'agent.access',
                'notifications.read',
                'notifications.update',
            ];

            if (! in_array($permissionKey, $agentPermissions, true)) {
                return false;
            }
        }

        $role->loadMissing('permissions');

        return $role->permissions->contains('key', $permissionKey);
    }

    public function hasValidTenantAccess(): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        if ($this->status !== 'active' || ! $this->company_id) {
            return false;
        }

        $this->loadMissing('company.license');
        $license = $this->company?->license;

        return $this->company?->status === 'active'
            && $license
            && $license->status === 'active'
            && ! $license->starts_at->isAfter(today())
            && ! $license->ends_at->isBefore(today());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'items_per_page' => 'integer',
            'password' => 'hashed',
            'sip_secret' => 'encrypted',
        ];
    }
}
