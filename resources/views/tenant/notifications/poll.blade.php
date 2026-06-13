@fragment('notification-panel')
    <x-ui.notification-panel
        :notifications="$notifications"
        :unread-count="$unreadCount"
    />
@endfragment
