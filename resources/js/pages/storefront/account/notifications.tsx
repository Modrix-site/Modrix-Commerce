import { Head, Link, router } from '@inertiajs/react';
import AccountNotificationController from '@/actions/App/Http/Controllers/Storefront/AccountNotificationController';
import { NotificationIcon } from '@/components/notifications/notification-bell';
import type { NotificationItem } from '@/components/notifications/notification-bell';
import { AccountNav } from '@/components/storefront/account-nav';
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
            <div className="grid gap-8 md:grid-cols-[12rem_1fr]">
                <AccountNav />
                <div className="grid content-start gap-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('Notifications')}
                        </h1>
                        {unread && (
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
                                {t('Mark all as read')}
                            </Button>
                        )}
                    </div>
                    {notifications.data.length === 0 ? (
                        <p className="text-muted-foreground">
                            {t('No notifications yet.')}
                        </p>
                    ) : (
                        <ul className="grid divide-y rounded-lg border">
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
                                                <span
                                                    className="mt-2 size-2 shrink-0 rounded-full bg-primary"
                                                    aria-label={t('Unread')}
                                                />
                                            )}
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    {notifications.last_page > 1 && (
                        <div className="flex gap-2">
                            {notifications.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={notifications.prev_page_url}>
                                        {t('Previous')}
                                    </Link>
                                </Button>
                            )}
                            {notifications.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={notifications.next_page_url}>
                                        {t('Next')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
