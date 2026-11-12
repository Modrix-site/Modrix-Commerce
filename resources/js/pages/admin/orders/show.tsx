import { Form, Head, Link } from '@inertiajs/react';
import {
    CircleAlert,
    Package,
    PauseCircle,
    ShoppingBag,
    Truck,
    Undo2,
    UserRound,
} from 'lucide-react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import { OrderActions } from '@/components/admin/orders/order-actions';
import type { MethodOption } from '@/components/admin/orders/order-actions';
import { OrderDocuments } from '@/components/admin/orders/order-documents';
import { OrderDownloads } from '@/components/admin/orders/order-downloads';
import { OrderPayments } from '@/components/admin/orders/order-payments';
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
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { useInitials } from '@/hooks/use-initials';
import { show as customerShow } from '@/routes/admin/customers';
import { index } from '@/routes/admin/orders';

type Props = {
    order: AdminOrder;
    actions: string[];
    canAddNotes: boolean;
    canIssueInvoice: boolean;
    emailDefaults: Record<string, boolean>;
    manualMethods: MethodOption[];
};

export default function OrderShow({
    order,
    actions,
    canAddNotes,
    canIssueInvoice,
    emailDefaults,
    manualMethods,
}: Props) {
    const initials = useInitials();
    const heldReason = order.timeline.find((e) => e.kind === 'held')?.message;
    const failedReason = order.timeline.find((e) =>
        e.message.startsWith('Delivery attempt failed'),
    )?.message;
    const returnReason = order.timeline.find(
        (e) => e.kind === 'returned',
    )?.message;

    return (
        <>
            <Head title={`Order ${order.number}`} />
            <AdminPage>
                <PageHeader
                    title={`Order ${order.number}`}
                    description={`Placed on ${formatDate(order.placed_at)}`}
                    actions={
                        <OrderActions
                            order={order}
                            actions={actions}
                            emailDefaults={emailDefaults}
                            manualMethods={manualMethods}
                        />
                    }
                />

                <div className="grid items-stretch gap-5 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Package
                                    className="size-4 text-muted-foreground"
                                    aria-hidden
                                />
                                Order Details
                            </CardTitle>
                            <CardDescription>
                                Core order information
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl>
                                <div className="admin-detail-row">
                                    <dt>Order ID</dt>
                                    <dd className="font-mono text-xs">
                                        {order.number}
                                    </dd>
                                </div>
                                <div className="admin-detail-row">
                                    <dt>Status</dt>
                                    <dd>
                                        <StatusBadge
                                            status={order.display_status}
                                        />
                                    </dd>
                                </div>
                                <div className="admin-detail-row">
                                    <dt>Date</dt>
                                    <dd>{formatDate(order.placed_at)}</dd>
                                </div>
                                <div className="admin-detail-row">
                                    <dt>Amount</dt>
                                    <dd className="tabular-nums">
                                        {order.grand_total.formatted}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UserRound
                                    className="size-4 text-muted-foreground"
                                    aria-hidden
                                />
                                Customer
                            </CardTitle>
                            <CardDescription>
                                Customer information
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-3">
                                <Avatar className="size-10 shrink-0">
                                    <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                                        {initials(
                                            order.customer?.name ?? order.email,
                                        )}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="min-w-0">
                                    {order.customer ? (
                                        <Link
                                            href={customerShow(
                                                order.customer.id,
                                            )}
                                            className="font-medium hover:text-primary hover:underline"
                                        >
                                            {order.customer.name}
                                        </Link>
                                    ) : (
                                        <span className="font-medium">
                                            Guest customer
                                        </span>
                                    )}
                                    <p
                                        className="truncate text-xs text-muted-foreground"
                                        title={order.email}
                                    >
                                        {order.email}
                                    </p>
                                    {order.phone && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {order.phone}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ShoppingBag
                                    className="size-4 text-muted-foreground"
                                    aria-hidden
                                />
                                Product
                            </CardTitle>
                            <CardDescription>
                                Purchased product details
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl>
                                <div className="admin-detail-row">
                                    <dt>Name</dt>
                                    <dd>
                                        {order.items[0]?.product_name ?? '—'}
                                        {order.items.length > 1 && (
                                            <p className="mt-1 text-xs font-normal text-muted-foreground">
                                                +{order.items.length - 1} more
                                                products
                                            </p>
                                        )}
                                    </dd>
                                </div>
                                <div className="admin-detail-row">
                                    <dt>Price</dt>
                                    <dd className="tabular-nums">
                                        {order.items[0]?.unit_price.formatted ??
                                            '—'}
                                    </dd>
                                </div>
                                <div className="admin-detail-row">
                                    <dt>Quantity</dt>
                                    <dd>
                                        {order.items.reduce(
                                            (quantity, item) =>
                                                quantity + item.quantity,
                                            0,
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                {order.status === 'on_hold' && (
                    <Alert>
                        <PauseCircle aria-hidden />
                        <AlertTitle>This order is on hold</AlertTitle>
                        <AlertDescription>
                            {heldReason ?? 'Order put on hold.'} It cannot be
                            picked, packed or shipped until the hold is
                            released. Customers never see the hold.
                        </AlertDescription>
                    </Alert>
                )}
                {order.fulfillment_status === 'delivery_failed' && (
                    <Alert variant="destructive">
                        <Truck aria-hidden />
                        <AlertTitle>Delivery failed</AlertTitle>
                        <AlertDescription>
                            {failedReason} Retry the delivery, or mark the order
                            delivered or returned.
                        </AlertDescription>
                    </Alert>
                )}
                {order.fulfillment_status === 'returned' && (
                    <Alert>
                        <Undo2 aria-hidden />
                        <AlertTitle>Returned</AlertTitle>
                        <AlertDescription>
                            {returnReason} Refund the customer from More actions
                            if needed.
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
                            <div className="mb-5 flex flex-wrap items-center gap-2 text-xs">
                                <span className="font-medium">
                                    Order progress
                                </span>
                                <span className="ml-auto text-muted-foreground">
                                    Payment
                                </span>
                                <StatusBadge status={order.payment_status} />
                                <span className="text-muted-foreground">
                                    Fulfilment
                                </span>
                                <StatusBadge
                                    status={order.fulfillment_status}
                                />
                            </div>
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

                        <OrderPayments order={order} />
                        <OrderDownloads
                            orderNumber={order.number}
                            downloads={order.downloads}
                        />

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
                                <CardTitle>Delivery information</CardTitle>
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

OrderShow.layout = {
    breadcrumbs: [{ title: 'Orders', href: index() }],
};
