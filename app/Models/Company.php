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

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }
}
