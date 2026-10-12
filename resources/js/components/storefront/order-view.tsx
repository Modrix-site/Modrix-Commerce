import { StatusBadge } from '@/components/admin/status-badge';
import { OrderTotals } from '@/components/storefront/order-totals';
import type { OrderAddressData, OrderDetail } from '@/types';

const dateFormat = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function formatDate(iso: string): string {
    return dateFormat.format(new Date(iso));
}

export function AddressBlock({ address }: { address: OrderAddressData }) {
    return (
        <address className="text-sm text-muted-foreground not-italic">
            {address.first_name} {address.last_name}
            <br />
            {address.company && (
                <>
                    {address.company}
                    <br />
                </>
            )}
            {address.line1}
            {address.line2 && `, ${address.line2}`}
            <br />
            {address.city}
            {address.region && `, ${address.region}`} {address.postal_code}
            <br />
            {address.country_name}
        </address>
    );
}

/**
 * Customer-facing order details (confirmation page and account order page).
 */
export function OrderView({ order }: { order: OrderDetail }) {
    return (
        <div className="grid gap-8 lg:grid-cols-3">
            <div className="grid content-start gap-6 lg:col-span-2">
                <div className="flex flex-wrap gap-2">
                    <StatusBadge status={order.payment_status} />
                    <StatusBadge status={order.fulfillment_status} />
                </div>
                <ul className="grid divide-y rounded-lg border">
                    {order.items.map((item) => (
                        <li
                            key={item.id}
                            className="flex justify-between gap-4 p-4 text-sm"
                        >
                            <span>
                                <span className="font-medium">
                                    {item.product_name}
                                </span>
                                {item.variant_name && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {item.variant_name}
                                    </span>
                                )}
                                <span className="block text-muted-foreground">
                                    {item.quantity} ×{' '}
                                    {item.unit_price.formatted}
                                </span>
                            </span>
                            <span className="tabular-nums">
                                {item.subtotal.formatted}
                            </span>
                        </li>
                    ))}
                </ul>
                {order.shipments.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">Shipping updates</h2>
                        {order.shipments.map((s, i) => (
                            <p
                                key={i}
                                className="text-sm text-muted-foreground"
                            >
                                Shipped {formatDate(s.shipped_at)}
                                {s.carrier && ` via ${s.carrier}`}
                                {s.tracking_number && (
                                    <>
                                        {' '}
                                        · Tracking{' '}
                                        {s.tracking_url ? (
                                            <a
                                                href={s.tracking_url}
                                                className="underline"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                {s.tracking_number}
                                            </a>
                                        ) : (
                                            s.tracking_number
                                        )}
                                    </>
                                )}
                                {s.delivered_at &&
                                    ` · Delivered ${formatDate(s.delivered_at)}`}
                            </p>
                        ))}
                    </section>
                )}
                {order.history.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-semibold">History</h2>
                        <ol className="grid gap-1 text-sm">
                            {order.history.map((event, i) => (
                                <li key={i} className="flex gap-3">
                                    <time
                                        className="shrink-0 text-muted-foreground"
                                        dateTime={event.created_at}
                                    >
                                        {formatDate(event.created_at)}
                                    </time>
                                    <span>{event.message}</span>
                                </li>
                            ))}
                        </ol>
                    </section>
                )}
            </div>
            <aside className="grid content-start gap-5 rounded-lg border p-5">
                <OrderTotals totals={order} />
                <div className="grid gap-1 text-sm">
                    <span className="font-medium">Payment</span>
                    <span className="text-muted-foreground">
                        {order.payment_method}
                    </span>
                </div>
                {order.shipping_method && (
                    <div className="grid gap-1 text-sm">
                        <span className="font-medium">Shipping method</span>
                        <span className="text-muted-foreground">
                            {order.shipping_method}
                        </span>
                    </div>
                )}
                {order.addresses.shipping && (
                    <div className="grid gap-1">
                        <span className="text-sm font-medium">
                            Shipping address
                        </span>
                        <AddressBlock address={order.addresses.shipping} />
                    </div>
                )}
            </aside>
        </div>
    );
}
