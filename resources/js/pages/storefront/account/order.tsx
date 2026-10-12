import { Head, Link } from '@inertiajs/react';
import { formatDate, OrderView } from '@/components/storefront/order-view';
import { orders } from '@/routes/account';
import type { OrderDetail } from '@/types';

export default function AccountOrder({ order }: { order: OrderDetail }) {
    return (
        <>
            <Head title={`Order ${order.number}`} />
            <Link
                href={orders()}
                className="text-sm text-muted-foreground hover:text-foreground"
            >
                ← All orders
            </Link>
            <div className="my-4 grid gap-1">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Order {order.number}
                </h1>
                <p className="text-sm text-muted-foreground">
                    Placed {formatDate(order.placed_at)}
                </p>
            </div>
            <OrderView order={order} />
        </>
    );
}
