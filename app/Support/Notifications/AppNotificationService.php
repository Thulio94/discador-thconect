<?php

namespace App\Support\Notifications;

use App\Events\AppNotificationCreated;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Collection;

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

    public function createForUserOnce(User $user, string $type, string $severity, string $title, string $message, ?string $actionUrl = null): AppNotification
    {
        $notification = AppNotification::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('title', $title)
            ->where('message', $message)
            ->where('action_url', $actionUrl)
            ->latest()
            ->first();

        if ($notification) {
            return $notification;
        }

        return $this->createForUser($user, $type, $severity, $title, $message, $actionUrl);
    }

    /**
     * @return Collection<int, AppNotification>
     */
    public function createForCompanyAudience(int $companyId, array $userTypes, string $type, string $severity, string $title, string $message, ?string $actionUrl = null, bool $dedupe = false): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereIn('user_type', $userTypes)
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): AppNotification => $dedupe
                ? $this->createForUserOnce($user, $type, $severity, $title, $message, $actionUrl)
                : $this->createForUser($user, $type, $severity, $title, $message, $actionUrl));
    }
}
