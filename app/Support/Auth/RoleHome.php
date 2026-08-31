<?php

namespace App\Support\Auth;

use App\Models\User;

final class RoleHome
{
    public static function route(User $user): string
    {
        return match ($user->user_type) {
            'agent' => route('tenant.agent.index', absolute: false),
            default => route('dashboard', absolute: false),
        };
    }
}
