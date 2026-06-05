<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    'status',
    'is_super_admin',
    'theme_preference',
    'items_per_page',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const TYPES = ['agent', 'supervisor', 'company_admin', 'super_admin'];

    public const THEME_PREFERENCES = ['thconectclaro', 'thconectescuro'];

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

    public function hasPermission(string $permissionKey): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $role = $this->role;

        if (! $role || $role->company_id !== $this->company_id) {
            return false;
        }

        $role->loadMissing('permissions');

        return $role->permissions->contains('key', $permissionKey);
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
        ];
    }
}
