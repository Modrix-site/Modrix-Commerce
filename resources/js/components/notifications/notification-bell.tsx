import { Link, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    Bell,
    CircleAlert,
    CircleCheck,
    CircleX,
    Download,
    Package,
    PackageCheck,
    ShoppingBag,
    Truck,
    Undo2,
    UserPlus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export type NotificationItem = {
    id: string;
    kind: string;
    title: string;
    body: string;
    icon: string;
    url: string | null;
    params: Record<string, unknown>;
    read: boolean;
    created_at: string | null;
};

type Feed = {
    items: NotificationItem[];
    more: boolean;
    unread: number;
    cursor: string;
};

const ICONS: Record<string, LucideIcon> = {
    'shopping-bag': ShoppingBag,
    banknote: Banknote,
    'circle-alert': CircleAlert,
    'circle-x': CircleX,
    'circle-check': CircleCheck,
    'undo-2': Undo2,
    package: Package,
    'package-check': PackageCheck,
    'user-plus': UserPlus,
    truck: Truck,
    download: Download,
};

export function NotificationIcon({
    icon,
    className,
}: {
    icon: string;
    className?: string;
}) {
    const Icon = ICONS[icon] ?? Bell;

    return <Icon className={cn('size-4 shrink-0', className)} aria-hidden />;
}

/** "5 min ago", "2 h ago", or the date. */
export function timeAgo(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const seconds = Math.max(
        0,
        Math.round((Date.now() - new Date(iso).getTime()) / 1000),
    );

    if (seconds < 60) {
        return 'just now';
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)} min ago`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)} h ago`;
    }

    return new Date(iso).toLocaleDateString();
}

const POLL_MS = 30_000;
const MAX_TOASTS = 3;

/**
 * Notification bell with a dropdown of the latest notifications.
 *
 * - `live`: checks for new ones every 30 s while the tab is visible and
 *   shows them as toasts (the admin).
 * - Otherwise the count comes from the page and refreshes on navigation
 *   (the storefront).
 */
export function NotificationBell({
    feedUrl,
    openUrl,
    readAllUrl,
    viewAllUrl,
    live = false,
    label = 'Notifications',
    viewAllLabel = 'View all',
    markAllLabel = 'Mark all as read',
    emptyLabel = 'No notifications yet.',
    translate = (item) => item,
    formatTime = timeAgo,
    className,
}: {
    feedUrl: string;
    openUrl: (id: string) => string;
    readAllUrl: string;
    viewAllUrl: string;
    live?: boolean;
    label?: string;
    viewAllLabel?: string;
    markAllLabel?: string;
    emptyLabel?: string;
    /** Customer bells translate titles in the shopper's language. */
    translate?: (item: NotificationItem) => NotificationItem;
    /** Defaults to "5 min ago" (English); the storefront passes a localized date. */
    formatTime?: (iso: string | null) => string;
    className?: string;
}) {
    const { notificationsUnread } = usePage<{
        notificationsUnread?: number;
    }>().props;
    const [unread, setUnread] = useState(notificationsUnread ?? 0);
    const [items, setItems] = useState<NotificationItem[] | null>(null);
    const cursor = useRef<string | null>(null);
    const seen = useRef<Set<string>>(new Set());

    useEffect(() => setUnread(notificationsUnread ?? 0), [notificationsUnread]);

    const load = useCallback(
        async (after: string | null) => {
            const url = after
                ? `${feedUrl}?after=${encodeURIComponent(after)}`
                : feedUrl;
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            return response.ok ? ((await response.json()) as Feed) : null;
        },
        [feedUrl],
    );

    const open = (item: NotificationItem) => router.post(openUrl(item.id));

    // Live: first call sets the cursor; later calls toast what is new.
    useEffect(() => {
        if (!live) {
            return;
        }

        let stopped = false;

        const tick = async () => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            const feed = await load(cursor.current).catch(() => null);

            if (!feed || stopped) {
                return;
            }

            setUnread(feed.unread);
            const fresh = feed.items.filter(
                (item) => !item.read && !seen.current.has(item.id),
            );
            feed.items.forEach((item) => seen.current.add(item.id));

            if (cursor.current !== null) {
                fresh.slice(0, MAX_TOASTS).forEach((item) => {
                    const shown = translate(item);
                    toast(shown.title, {
                        description: shown.body,
                        action: { label: 'Open', onClick: () => open(item) },
                    });
                });

                const extra = fresh.length - MAX_TOASTS + (feed.more ? 1 : 0);

                if (extra > 0) {
                    toast(`+${extra} more notifications`, {
                        action: {
                            label: viewAllLabel,
                            onClick: () => router.visit(viewAllUrl),
                        },
                    });
                }
            }

            cursor.current = feed.cursor;
        };

        void tick();
        const timer = window.setInterval(tick, POLL_MS);
        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                void tick();
            }
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            stopped = true;
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisible);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [live, load]);

    const onOpenChange = async (isOpen: boolean) => {
        if (isOpen) {
            const feed = await load(null).catch(() => null);
            setItems(feed ? feed.items.slice(0, 10) : []);

            if (feed) {
                setUnread(feed.unread);
            }
        }
    };

    return (
        <DropdownMenu onOpenChange={(o) => void onOpenChange(o)}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn('relative size-8', className)}
                    aria-label={
                        unread > 0 ? `${label} (${unread} unread)` : label
                    }
                >
                    <Bell className="size-4" />
                    {unread > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 font-semibold text-white tabular-nums">
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between border-b px-3 py-2">
                    <span className="text-sm font-semibold">{label}</span>
                    {unread > 0 && (
                        <button
                            type="button"
                            className="text-xs text-muted-foreground hover:text-foreground"
                            onClick={() =>
                                router.post(
                                    readAllUrl,
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setUnread(0);
                                            setItems(
                                                (list) =>
                                                    list?.map((i) => ({
                                                        ...i,
                                                        read: true,
                                                    })) ?? null,
                                            );
                                        },
                                    },
                                )
                            }
                        >
                            {markAllLabel}
                        </button>
                    )}
                </div>
                <ul className="max-h-96 overflow-y-auto">
                    {items === null ? (
                        <li className="px-3 py-6 text-center text-sm text-muted-foreground">
                            …
                        </li>
                    ) : items.length === 0 ? (
                        <li className="px-3 py-6 text-center text-sm text-muted-foreground">
                            {emptyLabel}
                        </li>
                    ) : (
                        items.map((raw) => {
                            const item = translate(raw);

                            return (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() => open(item)}
                                        className={cn(
                                            'flex w-full gap-3 px-3 py-2.5 text-left hover:bg-muted',
                                            !item.read && 'bg-primary/5',
                                        )}
                                    >
                                        <NotificationIcon
                                            icon={item.icon}
                                            className="mt-0.5 text-muted-foreground"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium">
                                                {item.title}
                                            </span>
                                            <span className="line-clamp-2 block text-xs text-muted-foreground">
                                                {item.body}
                                            </span>
                                            <span className="block text-[11px] text-muted-foreground">
                                                {formatTime(item.created_at)}
                                            </span>
                                        </span>
                                        {!item.read && (
                                            <span
                                                className="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                                                aria-label="Unread"
                                            />
                                        )}
                                    </button>
                                </li>
                            );
                        })
                    )}
                </ul>
                <div className="border-t px-3 py-2 text-center">
                    <Link
                        href={viewAllUrl}
                        className="text-sm font-medium hover:underline"
                    >
                        {viewAllLabel}
                    </Link>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
