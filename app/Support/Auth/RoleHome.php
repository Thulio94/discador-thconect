<?php

namespace App\Support\Auth;

use App\Models\User;

final class RoleHome
{
    public static function route(User $user): string
    {
        if ($user->user_type === 'agent') {
            return route('tenant.agent.index', absolute: false);
        }

        if ($user->is_super_admin || $user->hasPermission('dashboard.read')) {
            return route('dashboard', absolute: false);
        }

        if ($user->hasPermission('supervision.read')) {
            return route('tenant.supervision.index', absolute: false);
        }

        return route('profile.edit', absolute: false);
    }
}
