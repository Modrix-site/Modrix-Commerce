import NotificationController from '@/actions/App/Http/Controllers/Admin/NotificationController';
import { NotificationBell } from '@/components/notifications/notification-bell';

/** Admin header bell: live (new notifications pop up as toasts). */
export function AdminNotificationBell({ className }: { className?: string }) {
    return (
        <NotificationBell
            live
            className={className}
            feedUrl={NotificationController.feed.url()}
            openUrl={(id) => NotificationController.open.url(id)}
            readAllUrl={NotificationController.markAllRead.url()}
            viewAllUrl={NotificationController.index.url()}
        />
    );
}
