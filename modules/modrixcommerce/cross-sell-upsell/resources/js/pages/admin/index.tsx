import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type OfferState = 'live' | 'scheduled' | 'ended' | 'off';

export type OfferSummary = {
    id: number;
    name: string;
    type: 'upsell' | 'cross_sell';
    trigger_type: 'any' | 'products' | 'categories';
    trigger_count: number;
    suggestion_mode: 'manual' | 'automatic';
    products_count: number;
    show_on_product: boolean;
    show_in_popup: boolean;
    show_in_cart: boolean;
    show_at_checkout: boolean;
    discount_type: 'percent' | 'fixed' | null;
    discount_value: string | null;
    priority: number;
    is_active: boolean;
    state: OfferState;
};

type Row = OfferSummary & {
    views: number;
    adds: number;
    orders: number;
    revenue: Record<string, string>;
    edit_url: string;
    delete_url: string;
};

export const stateBadge: Record<
    OfferState,
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    live: { label: 'Live', variant: 'default' },
    scheduled: { label: 'Scheduled', variant: 'secondary' },
    ended: { label: 'Ended', variant: 'outline' },
    off: { label: 'Off', variant: 'outline' },
};

function trigger(offer: OfferSummary): string {
    if (offer.trigger_type === 'any') {
        return 'Any product';
    }

    const noun = offer.trigger_type === 'products' ? 'product' : 'category';
    const plural =
        offer.trigger_type === 'products' ? 'products' : 'categories';

    return `${offer.trigger_count} ${offer.trigger_count === 1 ? noun : plural}`;
}

function places(offer: OfferSummary): string {
    return [
        offer.show_on_product && 'Product page',
        offer.show_in_popup && 'Pop-up',
        offer.show_in_cart && 'Cart',
        offer.show_at_checkout && 'Checkout',
    ]
        .filter(Boolean)
        .join(', ');
}

function discount(offer: OfferSummary, currency: string): string {
    if (!offer.discount_type || !offer.discount_value) {
        return '—';
    }

    const n = Number(offer.discount_value);

    return offer.discount_type === 'percent'
        ? `${Number.isInteger(n) ? n : n.toFixed(2)}% off`
        : `${n.toFixed(2)} ${currency} off`;
}

function rate(adds: number, views: number): string {
    return views === 0 ? '—' : `${((adds / views) * 100).toFixed(1)}%`;
}

export default function CrossSellIndex({
    offers,
    currency,
    createUrl,
}: {
    offers: Row[];
    currency: string;
    createUrl: string;
}) {
    const totals = offers.reduce(
        (sum, o) => ({
            views: sum.views + o.views,
            adds: sum.adds + o.adds,
            orders: sum.orders + o.orders,
            revenue: Number(o.revenue[currency] ?? 0) + sum.revenue,
        }),
        { views: 0, adds: 0, orders: 0, revenue: 0 },
    );

    return (
        <>
            <Head title="Cross-sell & Upsell" />
            <AdminPage>
                <PageHeader
                    title="Cross-sell & Upsell"
                    description="Offers suggest add-ons and upgrades on the product page, in the add-to-cart pop-up, in the cart and at checkout. An offer's discount applies while the product that triggered it is in the cart."
                    actions={
                        <Button asChild>
                            <Link href={createUrl}>
                                <Plus /> New offer
                            </Link>
                        </Button>
                    }
                />

                {offers.length === 0 ? (
                    <EmptyState
                        title="No offers yet"
                        description="Create an offer: choose what triggers it, which products to suggest, where it shows and an optional discount."
                    />
                ) : (
                    <>
                        <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {[
                                ['Views', totals.views.toLocaleString()],
                                ['Adds', totals.adds.toLocaleString()],
                                ['Orders', totals.orders.toLocaleString()],
                                [
                                    `Revenue (${currency})`,
                                    totals.revenue.toFixed(2),
                                ],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="rounded-lg border p-4"
                                >
                                    <dt className="text-xs text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="mt-1 text-xl font-semibold tabular-nums">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Offer</TableHead>
                                        <TableHead>Trigger</TableHead>
                                        <TableHead>Shows in</TableHead>
                                        <TableHead>Discount</TableHead>
                                        <TableHead className="text-right">
                                            Views
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Adds
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Orders
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Revenue
                                        </TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {offers.map((offer) => (
                                        <TableRow key={offer.id}>
                                            <TableCell>
                                                <Link
                                                    href={offer.edit_url}
                                                    className="font-medium hover:underline"
                                                >
                                                    {offer.name}
                                                </Link>
                                                <div className="text-xs text-muted-foreground">
                                                    {offer.type === 'upsell'
                                                        ? 'Upsell'
                                                        : 'Cross-sell'}
                                                    {' · '}
                                                    {offer.suggestion_mode ===
                                                    'automatic'
                                                        ? 'Automatic'
                                                        : `${offer.products_count} picked`}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {trigger(offer)}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {places(offer)}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {discount(offer, currency)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {offer.views}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {offer.adds}
                                                <div className="text-xs text-muted-foreground">
                                                    {rate(
                                                        offer.adds,
                                                        offer.views,
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {offer.orders}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {Object.keys(offer.revenue)
                                                    .length === 0
                                                    ? '—'
                                                    : Object.entries(
                                                          offer.revenue,
                                                      ).map(([cur, amount]) => (
                                                          <div key={cur}>
                                                              {amount} {cur}
                                                          </div>
                                                      ))}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        stateBadge[offer.state]
                                                            .variant
                                                    }
                                                >
                                                    {
                                                        stateBadge[offer.state]
                                                            .label
                                                    }
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                offer.edit_url
                                                            }
                                                            aria-label={`Edit ${offer.name}`}
                                                        >
                                                            <Pencil />
                                                        </Link>
                                                    </Button>
                                                    <Form
                                                        action={
                                                            offer.delete_url
                                                        }
                                                        method="delete"
                                                        onBefore={() =>
                                                            window.confirm(
                                                                `Delete “${offer.name}”? Its stats are deleted too, and products added from it go back to their regular price.`,
                                                            )
                                                        }
                                                    >
                                                        <Button
                                                            type="submit"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Delete ${offer.name}`}
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    </Form>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </>
                )}
            </AdminPage>
        </>
    );
}
