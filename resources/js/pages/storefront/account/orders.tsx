import { Head, Link } from '@inertiajs/react';
import { StatusBadge } from '@/components/admin/status-badge';
import { AccountNav } from '@/components/storefront/account-nav';
import { formatDate } from '@/components/storefront/order-view';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { show } from '@/routes/account/orders';
import type { OrderSummary, Paginated } from '@/types';

export default function AccountOrders({
    orders,
}: {
    orders: Paginated<OrderSummary>;
}) {
    const t = useT();

    return (
        <>
            <Head title={t('My orders')} />
            <div className="grid gap-8 md:grid-cols-[12rem_1fr]">
                <AccountNav />
                <div className="grid content-start gap-6">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Orders')}
                    </h1>
                    {orders.data.length === 0 ? (
                        <p className="text-muted-foreground">
                            {t('You have not placed any orders yet.')}
                        </p>
                    ) : (
                        <ul className="grid divide-y rounded-lg border">
                            {orders.data.map((order) => (
                                <li key={order.number}>
                                    <Link
                                        href={show(order.number)}
                                        className="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-muted/40"
                                    >
                                        <span className="grid">
                                            <span className="font-medium">
                                                {order.number}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {formatDate(order.placed_at)}
                                            </span>
                                        </span>
                                        <span className="flex items-center gap-2">
                                            <StatusBadge
                                                status={order.status}
                                            />
                                            <span className="tabular-nums">
                                                {order.grand_total.formatted}
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    {orders.last_page > 1 && (
                        <div className="flex gap-2">
                            {orders.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={orders.prev_page_url}>
                                        {t('Previous')}
                                    </Link>
                                </Button>
                            )}
                            {orders.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={orders.next_page_url}>
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
