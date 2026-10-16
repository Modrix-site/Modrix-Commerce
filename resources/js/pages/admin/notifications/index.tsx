import { Head, Link, router } from '@inertiajs/react';
import { Settings, Trash2 } from 'lucide-react';
import NotificationController from '@/actions/App/Http/Controllers/Admin/NotificationController';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import {
    NotificationIcon,
    timeAgo,
} from '@/components/notifications/notification-bell';
import type { NotificationItem } from '@/components/notifications/notification-bell';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export default function NotificationsIndex({
    notifications,
    filter,
}: {
    notifications: Paginated<NotificationItem>;
    filter: 'all' | 'unread';
}) {
    const options = { preserveScroll: true } as const;

    return (
        <>
            <Head title="Notifications" />
            <AdminPage>
                <PageHeader
                    title="Notifications"
                    description="Orders, payments, stock and customers that need your attention."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        NotificationController.markAllRead.url(),
                                        {},
                                        options,
                                    )
                                }
                            >
                                Mark all as read
                            </Button>
                            <Button variant="ghost" asChild>
                                <Link
                                    href={NotificationController.settings.url()}
                                >
                                    <Settings /> Settings
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <div className="flex gap-1 border-b">
                    {(['all', 'unread'] as const).map((key) => (
                        <Link
                            key={key}
                            href={NotificationController.index.url({
                                query: key === 'all' ? {} : { filter: key },
                            })}
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm font-medium',
                                filter === key
                                    ? 'border-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {key === 'all' ? 'All' : 'Unread'}
                        </Link>
                    ))}
                </div>

                {notifications.data.length === 0 ? (
                    <EmptyState
                        title={
                            filter === 'unread'
                                ? 'You are all caught up'
                                : 'No notifications yet'
                        }
                        description="New orders, payments, low stock and new customers will show up here."
                    />
                ) : (
                    <Card className="gap-0! divide-y py-0!">
                        {notifications.data.map((item) => (
                            <div
                                key={item.id}
                                className={cn(
                                    'flex items-start gap-3 px-4 py-3',
                                    !item.read && 'bg-primary/5',
                                )}
                            >
                                <NotificationIcon
                                    icon={item.icon}
                                    className="mt-1 text-muted-foreground"
                                />
                                <button
                                    type="button"
                                    className="min-w-0 flex-1 text-left"
                                    onClick={() =>
                                        router.post(
                                            NotificationController.open.url(
                                                item.id,
                                            ),
                                        )
                                    }
                                >
                                    <span className="block font-medium">
                                        {item.title}
                                    </span>
                                    <span className="block text-sm text-muted-foreground">
                                        {item.body}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {timeAgo(item.created_at)}
                                    </span>
                                </button>
                                <div className="flex shrink-0 items-center gap-1">
                                    {!item.read && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    NotificationController.markRead.url(
                                                        item.id,
                                                    ),
                                                    {},
                                                    options,
                                                )
                                            }
                                        >
                                            Mark read
                                        </Button>
                                    )}
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-8"
                                        aria-label={`Delete ${item.title}`}
                                        onClick={() =>
                                            router.delete(
                                                NotificationController.destroy.url(
                                                    item.id,
                                                ),
                                                options,
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </Card>
                )}

                <Pagination
                    from={notifications.from}
                    to={notifications.to}
                    total={notifications.total}
                    prevUrl={notifications.prev_page_url}
                    nextUrl={notifications.next_page_url}
                />
            </AdminPage>
        </>
    );
}
