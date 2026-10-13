import { Head, Link } from '@inertiajs/react';
import { formatDate, OrderView } from '@/components/storefront/order-view';
import { useT } from '@/lib/i18n';
import { orders } from '@/routes/account';
import type { OrderDetail } from '@/types';

export default function AccountOrder({ order }: { order: OrderDetail }) {
    const t = useT();

    return (
        <>
            <Head title={t('Order :number', { number: order.number })} />
            <Link
                href={orders()}
                className="text-sm text-muted-foreground hover:text-foreground"
            >
                ← {t('All orders')}
            </Link>
            <div className="my-4 grid gap-1">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {t('Order :number', { number: order.number })}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {t('Placed :date', { date: formatDate(order.placed_at) })}
                </p>
            </div>
            <OrderView order={order} />
        </>
    );
}
