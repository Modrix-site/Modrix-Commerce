import { Form, Head, Link } from '@inertiajs/react';
import { CircleAlert, PauseCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import { OrderActions } from '@/components/admin/orders/order-actions';
import { OrderDocuments } from '@/components/admin/orders/order-documents';
import { OrderStepper } from '@/components/admin/orders/order-stepper';
import { OrderTimeline } from '@/components/admin/orders/order-timeline';
import type { AdminOrder } from '@/components/admin/orders/types';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import InputError from '@/components/input-error';
import { OrderTotals } from '@/components/storefront/order-totals';
import { AddressBlock, formatDate } from '@/components/storefront/order-view';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { show as customerShow } from '@/routes/admin/customers';
import { index } from '@/routes/admin/orders';

type Props = {
    order: AdminOrder;
    actions: string[];
    canAddNotes: boolean;
    canIssueInvoice: boolean;
};

export default function OrderShow({
    order,
    actions,
    canAddNotes,
    canIssueInvoice,
}: Props) {
    const heldReason = order.timeline.find((e) => e.kind === 'held')?.message;

    return (
        <>
            <Head title={`Order ${order.number}`} />
            <AdminPage>
                <PageHeader
                    title={`Order ${order.number}`}
                    description={`Placed ${formatDate(order.placed_at)} · ${order.email} · ${order.grand_total.formatted}`}
                    actions={<OrderActions order={order} actions={actions} />}
                />

                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge status={order.status} />
                    <StatusBadge status={order.payment_status} />
                    <StatusBadge status={order.fulfillment_status} />
                </div>

                {order.status === 'on_hold' && (
                    <Alert>
                        <PauseCircle aria-hidden />
                        <AlertTitle>This order is on hold</AlertTitle>
                        <AlertDescription>
                            {heldReason ?? 'Order put on hold.'} It cannot be
                            packed or shipped until the hold is released. The
                            customer still sees it as{' '}
                            {order.payment_status === 'unpaid'
                                ? 'pending'
                                : 'processing'}
                            .
                        </AlertDescription>
                    </Alert>
                )}
                {order.status === 'cancelled' && (
                    <Alert variant="destructive">
                        <CircleAlert aria-hidden />
                        <AlertTitle>This order was cancelled</AlertTitle>
                    </Alert>
                )}

                {order.status !== 'cancelled' && (
                    <Card>
                        <CardContent className="pt-6">
                            <OrderStepper steps={order.steps} />
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="grid content-start gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Items ({order.items.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Item</TableHead>
                                            <TableHead>Qty</TableHead>
                                            <TableHead className="text-right">
                                                Unit
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Discount
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Tax
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {order.items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    <div className="font-medium">
                                                        {item.product_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {[
                                                            item.variant_name,
                                                            item.sku,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                        {item.restocked_quantity >
                                                            0 &&
                                                            ` · ${item.restocked_quantity} restocked`}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {item.quantity}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {item.unit_price.formatted}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {item.discount.formatted}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {item.tax.formatted}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {item.total.formatted}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                                <div className="ml-auto w-full max-w-xs">
                                    <OrderTotals totals={order} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Timeline</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-6">
                                {canAddNotes && <NoteForm order={order} />}
                                <OrderTimeline entries={order.timeline} />
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid content-start gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Customer</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                {order.customer ? (
                                    <Link
                                        href={customerShow(order.customer.id)}
                                        className="font-medium hover:underline"
                                    >
                                        {order.customer.name}
                                    </Link>
                                ) : (
                                    <span>{order.email}</span>
                                )}
                                <span className="text-muted-foreground">
                                    {order.email}
                                    {order.phone && ` · ${order.phone}`}
                                    {order.customer &&
                                        !order.customer.registered &&
                                        ' · guest'}
                                </span>
                                {order.addresses.shipping && (
                                    <div className="grid gap-1">
                                        <span className="font-medium">
                                            Shipping
                                        </span>
                                        <AddressBlock
                                            address={order.addresses.shipping}
                                        />
                                        {order.shipping_method && (
                                            <span className="text-muted-foreground">
                                                {order.shipping_method}
                                            </span>
                                        )}
                                    </div>
                                )}
                                {order.addresses.billing && (
                                    <div className="grid gap-1">
                                        <span className="font-medium">
                                            Billing
                                        </span>
                                        <AddressBlock
                                            address={order.addresses.billing}
                                        />
                                    </div>
                                )}
                                {order.customer_note && (
                                    <div className="grid gap-1">
                                        <span className="font-medium">
                                            Customer note
                                        </span>
                                        <p className="whitespace-pre-line text-muted-foreground">
                                            {order.customer_note}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Payment</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-2 text-sm">
                                <span>{order.payment_method}</span>
                                <Row
                                    label="Total"
                                    value={order.grand_total.formatted}
                                />
                                <Row
                                    label="Paid"
                                    value={order.paid_total.formatted}
                                />
                                {Number(order.refunded_total.amount) > 0 && (
                                    <Row
                                        label="Refunded"
                                        value={order.refunded_total.formatted}
                                    />
                                )}
                                {order.payments.map((p) => (
                                    <p
                                        key={p.id}
                                        className="text-xs text-muted-foreground"
                                    >
                                        {p.amount.formatted} {p.status}{' '}
                                        {p.paid_at &&
                                            `on ${formatDate(p.paid_at)}`}
                                        {p.reference && ` · ref ${p.reference}`}
                                        {p.failure_reason && (
                                            <span className="block text-destructive">
                                                {p.failure_reason}
                                            </span>
                                        )}
                                    </p>
                                ))}
                                {order.refunds.map((r) => (
                                    <p
                                        key={r.id}
                                        className="text-xs text-muted-foreground"
                                    >
                                        Refund {r.amount.formatted}
                                        {r.status !== 'completed' &&
                                            ` (${r.status})`}
                                        : {r.reason}
                                        {r.reference && ` · ref ${r.reference}`}
                                        {r.failure_reason && (
                                            <span className="block text-destructive">
                                                {r.failure_reason}
                                            </span>
                                        )}
                                    </p>
                                ))}
                            </CardContent>
                        </Card>

                        <OrderDocuments
                            order={order}
                            canIssueInvoice={canIssueInvoice}
                        />
                    </div>
                </div>
            </AdminPage>
        </>
    );
}

function NoteForm({ order }: { order: AdminOrder }) {
    return (
        <Form
            {...OrderController.note.form(order.number)}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="grid gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <Textarea
                        name="message"
                        rows={2}
                        placeholder="Add a note"
                        aria-label="Note"
                        required
                    />
                    <InputError message={errors.message} />
                    <div className="flex items-center justify-between gap-2">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox name="customer_visible" value="1" />
                            Visible to the customer
                        </label>
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            disabled={processing}
                        >
                            Add note
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function Row({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex justify-between">
            <span className="text-muted-foreground">{label}</span>
            <span className="tabular-nums">{value}</span>
        </div>
    );
}

OrderShow.layout = {
    breadcrumbs: [{ title: 'Orders', href: index() }],
};
