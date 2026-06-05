<?php

namespace App\Support\Notifications;

use App\Events\AppNotificationCreated;
use App\Models\AppNotification;
use App\Models\User;

class AppNotificationService
{
    public function createForUser(User $user, string $type, string $severity, string $title, string $message, ?string $actionUrl = null): AppNotification
    {
        $notification = AppNotification::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
        ]);

        event(new AppNotificationCreated($notification));

        return $notification;
    }
}
