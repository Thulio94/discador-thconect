<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['company_id', 'name', 'slug', 'is_admin'])]
class Role extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            if ($role->company_id !== null) {
                return;
            }

            $exists = static::query()
                ->whereNull('company_id')
                ->where('slug', $role->slug)
                ->when($role->exists, fn ($query) => $query->whereKeyNot($role->getKey()))
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'slug' => 'Ja existe um perfil global com este identificador.',
                ]);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
        ];
    }
}
