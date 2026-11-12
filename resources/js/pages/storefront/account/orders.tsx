import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Package } from 'lucide-react';
import { StatusBadge } from '@/components/admin/status-badge';
import {
    AccountEmpty,
    AccountLayout,
    AccountPager,
} from '@/components/storefront/account-nav';
import { formatDate } from '@/components/storefront/order-view';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { home } from '@/routes';
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
            <AccountLayout
                title={t('Orders')}
                description={t(
                    'Track deliveries, download invoices and request returns.',
                )}
            >
                {orders.data.length === 0 ? (
                    <AccountEmpty
                        icon={Package}
                        title={t('You have not placed any orders yet.')}
                        action={
                            <Button asChild>
                                <Link href={home()}>{t('Start shopping')}</Link>
                            </Button>
                        }
                    />
                ) : (
                    <ul className="grid gap-3">
                        {orders.data.map((order) => (
                            <li key={order.number}>
                                <Link
                                    href={show(order.number)}
                                    className="flex items-center gap-4 rounded-xl border bg-card p-4 transition-colors hover:border-foreground/30 sm:p-5"
                                >
                                    <span className="hidden size-11 shrink-0 place-items-center rounded-lg bg-muted sm:grid">
                                        <Package
                                            className="size-5"
                                            aria-hidden
                                        />
                                    </span>
                                    <span className="grid min-w-0 flex-1 gap-1">
                                        <span className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {t('Order :number', {
                                                    number: order.number,
                                                })}
                                            </span>
                                            <StatusBadge
                                                status={order.status}
                                            />
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {t('Placed :date', {
                                                date: formatDate(
                                                    order.placed_at,
                                                ),
                                            })}
                                        </span>
                                    </span>
                                    <span className="font-semibold tabular-nums">
                                        {order.grand_total.formatted}
                                    </span>
                                    <ChevronRight
                                        className="size-4 shrink-0 text-muted-foreground"
                                        aria-hidden
                                    />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <AccountPager page={orders} />
            </AccountLayout>
        </>
    );
}
