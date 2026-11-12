import { usePage } from '@inertiajs/react';
import AccountNotificationController from '@/actions/App/Http/Controllers/Storefront/AccountNotificationController';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { formatDate } from '@/components/storefront/order-view';
import { useT } from '@/lib/i18n';
import { customerNotificationText } from '@/components/storefront/notification-text';

/**
 * Storefront bell for signed-in customers (staff use the admin bell). The
 * count refreshes on navigation; no polling.
 */
export function CustomerNotificationBell({
    className,
}: {
    className?: string;
}) {
    const { auth } = usePage().props;
    const t = useT();

    if (!auth.user || auth.isStaff) {
        return null;
    }

    return (
        <NotificationBell
            className={className}
            feedUrl={AccountNotificationController.feed.url()}
            openUrl={(id) => AccountNotificationController.open.url(id)}
            readAllUrl={AccountNotificationController.markAllRead.url()}
            viewAllUrl={AccountNotificationController.index.url()}
            label={t('Notifications')}
            viewAllLabel={t('View all')}
            markAllLabel={t('Mark all as read')}
            emptyLabel={t('No notifications yet.')}
            unreadLabel={t('Unread')}
            translate={customerNotificationText(t)}
            formatTime={(iso) => (iso ? formatDate(iso) : '')}
        />
    );
}
