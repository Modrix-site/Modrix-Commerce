import { Head, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import AccountNotificationController from '@/actions/App/Http/Controllers/Storefront/AccountNotificationController';
import { NotificationIcon } from '@/components/notifications/notification-bell';
import type { NotificationItem } from '@/components/notifications/notification-bell';
import {
    AccountEmpty,
    AccountLayout,
    AccountPager,
} from '@/components/storefront/account-nav';
import { formatDate } from '@/components/storefront/order-view';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { customerNotificationText } from '@/components/storefront/notification-text';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export default function AccountNotifications({
    notifications,
}: {
    notifications: Paginated<NotificationItem>;
}) {
    const t = useT();
    const translate = customerNotificationText(t);
    const unread = notifications.data.some((item) => !item.read);

    return (
        <>
            <Head title={t('Notifications')} />
            <AccountLayout
                title={t('Notifications')}
                description={t('Updates about your orders, payments and returns.')}
                actions={
                    unread && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    AccountNotificationController.markAllRead.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck aria-hidden />
                            {t('Mark all as read')}
                        </Button>
                    )
                }
            >
                    {notifications.data.length === 0 ? (
                        <AccountEmpty
                            icon={Bell}
                            title={t('No notifications yet.')}
                        />
                    ) : (
                        <ul className="grid divide-y overflow-hidden rounded-xl border bg-card">
                            {notifications.data.map((raw) => {
                                const item = translate(raw);

                                return (
                                    <li key={item.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.post(
                                                    AccountNotificationController.open.url(
                                                        item.id,
                                                    ),
                                                )
                                            }
                                            className={cn(
                                                'flex w-full items-start gap-3 p-4 text-left hover:bg-muted/40',
                                                !item.read && 'bg-primary/5',
                                            )}
                                        >
                                            <NotificationIcon
                                                icon={item.icon}
                                                className="mt-1 text-muted-foreground"
                                            />
                                            <span className="grid min-w-0 flex-1">
                                                <span className="font-medium">
                                                    {item.title}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    {item.body}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {item.created_at &&
                                                        formatDate(
                                                            item.created_at,
                                                        )}
                                                </span>
                                            </span>
                                            {!item.read && (
                                                <span className="mt-2 size-2 shrink-0 rounded-full bg-primary">
                                                    <span className="sr-only">
                                                        {t('Unread')}
                                                    </span>
                                                </span>
                                            )}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    <AccountPager page={notifications} />
            </AccountLayout>
        </>
    );
}
