import { Form, Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import InputError from '@/components/input-error';
import { OrderTotals } from '@/components/storefront/order-totals';
import { AddressBlock, formatDate } from '@/components/storefront/order-view';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
import type { MoneyValue, OrderDetail } from '@/types';

type AdminOrder = OrderDetail & {
    id: number;
    phone: string | null;
    ip_address: string | null;
    customer: { id: number; name: string; registered: boolean } | null;
    paid_total: MoneyValue;
    refunded_total: MoneyValue;
    refundable: MoneyValue;
    payments: {
        id: number;
        method: string;
        provider: string;
        reference: string | null;
        amount: MoneyValue;
        status: string;
        failure_reason: string | null;
        paid_at: string | null;
        created_at: string | null;
    }[];
    refunds: {
        id: number;
        amount: MoneyValue;
        reason: string;
        status: string;
        reference: string | null;
        failure_reason: string | null;
        created_at: string | null;
    }[];
};

type Props = {
    order: AdminOrder;
    actions: string[];
    canAddNotes: boolean;
};

export default function OrderShow({ order, actions, canAddNotes }: Props) {
    const can = (action: string) => actions.includes(action);

    return (
        <>
            <Head title={`Order ${order.number}`} />
            <AdminPage>
                <PageHeader
                    title={`Order ${order.number}`}
                    description={`Placed ${formatDate(order.placed_at)} · ${order.email}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {can('record_payment') && (
                                <RecordPaymentDialog order={order} />
                            )}
                            {can('ship') && <ShipDialog order={order} />}
                            {can('mark_delivered') && (
                                <ActionDialog
                                    order={order}
                                    trigger="Mark delivered"
                                    title="Mark this order as delivered?"
                                    description="The customer's order will show as delivered. Paid orders become completed."
                                    form={OrderController.deliver.form(
                                        order.number,
                                    )}
                                    submit="Mark delivered"
                                />
                            )}
                            {can('refund') && <RefundDialog order={order} />}
                            {can('cancel') && <CancelDialog order={order} />}
                        </div>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <StatusBadge status={order.status} />
                    <StatusBadge status={order.payment_status} />
                    <StatusBadge status={order.fulfillment_status} />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="grid content-start gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Items</CardTitle>
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
                                <CardTitle>History</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <ol className="grid gap-2 text-sm">
                                    {order.history.map((event) => (
                                        <li
                                            key={event.id}
                                            className="grid gap-0.5 border-l-2 pl-3"
                                        >
                                            <span>{event.message}</span>
                                            <span className="text-xs text-muted-foreground">
                                                {formatDate(event.created_at)}
                                                {event.actor &&
                                                    ` · ${event.actor}`}
                                                {event.customer_visible
                                                    ? ' · visible to customer'
                                                    : ' · internal'}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                                {canAddNotes && (
                                    <Form
                                        {...OrderController.note.form(
                                            order.number,
                                        )}
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
                                                <InputError
                                                    message={errors.message}
                                                />
                                                <div className="flex items-center justify-between gap-2">
                                                    <label className="flex items-center gap-2 text-sm">
                                                        <Checkbox
                                                            name="customer_visible"
                                                            value="1"
                                                        />
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
                                )}
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
                                    label="Paid"
                                    value={order.paid_total.formatted}
                                />
                                <Row
                                    label="Refunded"
                                    value={order.refunded_total.formatted}
                                />
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
                    </div>
                </div>
            </AdminPage>
        </>
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

type FormDef = ReturnType<typeof OrderController.deliver.form>;

function ActionDialog({
    trigger,
    title,
    description,
    form,
    submit,
    variant = 'default',
    children,
}: {
    order: AdminOrder;
    trigger: string;
    title: string;
    description: string;
    form: FormDef;
    submit: string;
    variant?: 'default' | 'destructive' | 'outline';
    children?: ReactNode;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant={variant === 'destructive' ? 'outline' : variant}
                >
                    {trigger}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {children}
                            <InputError
                                message={
                                    errors.order ??
                                    errors.amount ??
                                    errors.restock
                                }
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant={
                                        variant === 'destructive'
                                            ? 'destructive'
                                            : 'default'
                                    }
                                    disabled={processing}
                                >
                                    {submit}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RecordPaymentDialog({ order }: { order: AdminOrder }) {
    return (
        <ActionDialog
            order={order}
            trigger="Record payment"
            title="Record payment"
            description={`Confirm that ${order.grand_total.formatted} was received via ${order.payment_method}. Only record payments you have verified in your bank account or cash register.`}
            form={OrderController.recordPayment.form(order.number)}
            submit="Record payment"
        >
            <FormField id="reference" label="Reference (optional)">
                <Input
                    id="reference"
                    name="reference"
                    placeholder="Bank transaction ID"
                />
            </FormField>
            <FormField id="pay-note" label="Note (optional)">
                <Input id="pay-note" name="note" />
            </FormField>
        </ActionDialog>
    );
}

function ShipDialog({ order }: { order: AdminOrder }) {
    return (
        <ActionDialog
            order={order}
            trigger="Mark shipped"
            title="Mark as shipped"
            description="The customer is emailed with the tracking details you enter."
            form={OrderController.ship.form(order.number)}
            submit="Mark shipped"
        >
            <FormField id="carrier" label="Carrier (optional)">
                <Input id="carrier" name="carrier" />
            </FormField>
            <FormField id="tracking_number" label="Tracking number (optional)">
                <Input id="tracking_number" name="tracking_number" />
            </FormField>
            <FormField id="tracking_url" label="Tracking URL (optional)">
                <Input id="tracking_url" name="tracking_url" type="url" />
            </FormField>
        </ActionDialog>
    );
}

function CancelDialog({ order }: { order: AdminOrder }) {
    return (
        <ActionDialog
            order={order}
            trigger="Cancel order"
            title={`Cancel order ${order.number}?`}
            description="Stock is returned to inventory and the customer is notified. This cannot be undone."
            form={OrderController.cancel.form(order.number)}
            submit="Cancel order"
            variant="destructive"
        >
            <FormField id="reason" label="Reason">
                <Input id="reason" name="reason" required />
            </FormField>
        </ActionDialog>
    );
}

function RefundDialog({ order }: { order: AdminOrder }) {
    return (
        <ActionDialog
            order={order}
            trigger="Refund"
            title="Record a refund"
            description={`Up to ${order.refundable.formatted} can be refunded. For ${order.payment_method}, return the money outside the store, then record it here.`}
            form={OrderController.refund.form(order.number)}
            submit="Record refund"
            variant="destructive"
        >
            <FormField id="amount" label={`Amount (${order.currency})`}>
                <Input
                    id="amount"
                    name="amount"
                    inputMode="decimal"
                    defaultValue={order.refundable.amount}
                    required
                />
            </FormField>
            <FormField id="refund-reason" label="Reason">
                <Input id="refund-reason" name="reason" required />
            </FormField>
            <fieldset className="grid gap-2">
                <legend className="mb-1 text-sm font-medium">
                    Return items to stock
                </legend>
                {order.items.map((item) => {
                    const max = item.quantity - item.restocked_quantity;

                    return (
                        <label
                            key={item.id}
                            className="flex items-center justify-between gap-3 text-sm"
                        >
                            <span>
                                {item.product_name}{' '}
                                {item.variant_name && `(${item.variant_name})`}
                            </span>
                            <Input
                                name={`restock[${item.id}]`}
                                type="number"
                                min={0}
                                max={max}
                                defaultValue={0}
                                disabled={max === 0}
                                className="w-20"
                                aria-label={`Restock quantity for ${item.sku}`}
                            />
                        </label>
                    );
                })}
            </fieldset>
        </ActionDialog>
    );
}

OrderShow.layout = {
    breadcrumbs: [{ title: 'Orders', href: index() }],
};
