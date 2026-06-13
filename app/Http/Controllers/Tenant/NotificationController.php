<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $html = view('tenant.notifications.poll', compact(
            'notifications',
            'unreadCount'
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
}
