<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Support\Notifications\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function poll(Request $request): Response
    {
        $notifications = $request->user()
            ->appNotifications()
            ->latest()
            ->limit(8)
            ->get();
        $unreadCount = $request->user()
            ->appNotifications()
            ->whereNull('read_at')
            ->count();
        $notificationCategories = AppNotificationService::preferenceCategories();
        $notificationPreferences = $request->user()
            ->notificationPreferences()
            ->pluck('enabled', 'category')
            ->all();

        $html = view('tenant.notifications.poll', compact(
            'notifications',
            'unreadCount',
            'notificationCategories',
            'notificationPreferences'
        ))->fragment('notification-panel');

        return response($html)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Poll-Interval', '10');
    }

    public function markAsRead(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['read_at' => now()]);

        return back()->with('status', 'Notificacao marcada como lida.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()
            ->appNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'Notificacoes marcadas como lidas.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $categories = array_keys(AppNotificationService::preferenceCategories());
        $data = $request->validate([
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', Rule::in($categories)],
        ]);

        $enabledCategories = collect($data['categories'] ?? []);

        foreach ($categories as $category) {
            $request->user()->notificationPreferences()->updateOrCreate(
                ['category' => $category],
                [
                    'company_id' => $request->user()->company_id,
                    'enabled' => $enabledCategories->contains($category),
                ]
            );
        }

        return back()->with('status', 'Preferencias de notificacao atualizadas.');
    }
}
