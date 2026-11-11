import { Form, Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    Download,
    FileSpreadsheet,
    FileText,
    PlusCircle,
    Receipt,
    Undo2,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import InputError from '@/components/input-error';
import { formatDate } from '@/components/storefront/order-view';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
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

type Option = { value: string; label: string };

type RefundLine = {
    id: number;
    order_item_id: number;
    name: string;
    sku: string;
    quantity: number;
    restocked: boolean;
};

type PaymentTrack = {
    channel: string;
    channel_label: string;
    payout_status: string;
    payout_status_label: string;
    reference: string;
    destination: string;
    notes: string;
    processed_at: string | null;
    updated_by: string | null;
};

type RefundEntry = {
    id: number;
    order_id: number;
    order_number: string;
    order_url: string;
    customer_name: string;
    customer_email: string;
    payment_method_name: string;
    amount: string;
    amount_raw: string;
    currency: string;
    type: 'full' | 'partial';
    reason: string;
    status: string;
    provider_reference: string | null;
    failure_reason: string | null;
    actor: string;
    created_at: string | null;
    items: RefundLine[];
    credit_memo: {
        id: number;
        number: string;
        issued_at: string;
        pdf_url: string;
    } | null;
    generate_memo_url: string;
    update_track_url: string;
    payment_track: PaymentTrack;
};

type CreditMemoLine = {
    sku: string;
    name: string;
    variant: string | null;
    quantity: number;
    restocked: boolean;
    unit_price: string;
    tax: string;
    total: string;
};

type CreditMemoEntry = {
    id: number;
    number: string;
    refund_id: number;
    order_number: string;
    order_url: string | null;
    buyer_email: string;
    subtotal: string;
    tax_total: string;
    shipping_refund: string | null;
    adjustment_amount: string | null;
    grand_total: string;
    lines_count: number;
    lines: CreditMemoLine[];
    notes: string | null;
    issued_by: string;
    issued_at: string;
    pdf_url: string;
};

type OrderItemOption = {
    id: number;
    sku: string;
    product_name: string;
    variant_name: string | null;
    quantity: number;
    restocked_quantity: number;
    restockable_quantity: number;
    unit_price_formatted: string;
    effective_unit_amount: string;
    total_formatted: string;
};

type RefundableOrder = {
    id: number;
    number: string;
    order_url: string;
    store_refund_url: string;
    customer_name: string;
    email: string;
    currency: string;
    payment_method_code: string;
    payment_method_name: string;
    is_manual_payment: boolean;
    grand_total: string;
    paid_total: string;
    refunded_total: string;
    refundable_formatted: string;
    refundable_amount: string;
    shipping_total_amount: string;
    shipping_total_formatted: string;
    placed_at: string;
    items: OrderItemOption[];
};

type Props = {
    tab: 'refunds' | 'credit_memos' | 'orders';
    status: string;
    search: string;
    stats: {
        total_refunded: string;
        completed_count: number;
        pending_count: number;
        failed_count: number;
        credit_memos_count: number;
        refundable_orders_count: number;
    };
    refunds: RefundEntry[];
    creditMemos: CreditMemoEntry[];
    refundableOrders: RefundableOrder[];
    channels: Option[];
    payoutStatuses: Option[];
    defaults: {
        auto_generate_credit_memo: boolean;
        default_restock: boolean;
    };
};

export default function RefundManagementIndex({
    tab,
    status,
    stats,
    refunds,
    creditMemos,
    refundableOrders,
    channels,
    payoutStatuses,
    defaults,
}: Props) {
    const [activeTab, setActiveTab] = useState<
        'refunds' | 'credit_memos' | 'orders'
    >(tab);
    const [processOrder, setProcessOrder] = useState<RefundableOrder | null>(
        null,
    );
    const [trackingRefund, setTrackingRefund] = useState<RefundEntry | null>(
        null,
    );
    const [viewingMemo, setViewingMemo] = useState<CreditMemoEntry | null>(
        null,
    );

    return (
        <>
            <Head title="Refunds & Credit Memos" />
            <AdminPage>
                <PageHeader
                    title="Refunds & Credit Memos"
                    description="Process partial and full refunds, issue printable credit memos, and track refund payouts across gateway and manual channels."
                    actions={
                        refundableOrders.length > 0 ? (
                            <Button
                                onClick={() =>
                                    setProcessOrder(refundableOrders[0])
                                }
                            >
                                <PlusCircle className="size-4" aria-hidden />
                                Process refund
                            </Button>
                        ) : null
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        icon={<Undo2 className="size-4 text-rose-600" />}
                        label="Total refunded"
                        value={stats.total_refunded}
                        detail={`${stats.completed_count} completed refund${stats.completed_count === 1 ? '' : 's'}`}
                    />
                    <StatCard
                        icon={<FileText className="size-4 text-primary" />}
                        label="Credit memos issued"
                        value={String(stats.credit_memos_count)}
                        detail="Immutable financial documents"
                    />
                    <StatCard
                        icon={<Clock className="size-4 text-amber-600" />}
                        label="Pending / failed payouts"
                        value={`${stats.pending_count} / ${stats.failed_count}`}
                        detail="Awaiting settlement or review"
                    />
                    <StatCard
                        icon={
                            <CheckCircle2 className="size-4 text-emerald-600" />
                        }
                        label="Refundable orders"
                        value={String(stats.refundable_orders_count)}
                        detail="Paid orders eligible for refund"
                    />
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 border-b pb-3">
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant={
                                activeTab === 'refunds' ? 'default' : 'outline'
                            }
                            size="sm"
                            onClick={() => setActiveTab('refunds')}
                        >
                            <Receipt className="size-4" aria-hidden />
                            Refunds & Payments ({refunds.length})
                        </Button>
                        <Button
                            variant={
                                activeTab === 'credit_memos'
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            onClick={() => setActiveTab('credit_memos')}
                        >
                            <FileSpreadsheet className="size-4" aria-hidden />
                            Credit Memos ({creditMemos.length})
                        </Button>
                        <Button
                            variant={
                                activeTab === 'orders' ? 'default' : 'outline'
                            }
                            size="sm"
                            onClick={() => setActiveTab('orders')}
                        >
                            <Wallet className="size-4" aria-hidden />
                            Refundable Orders ({refundableOrders.length})
                        </Button>
                    </div>

                    {activeTab === 'refunds' && (
                        <div className="flex flex-wrap gap-1.5">
                            {(
                                [
                                    'all',
                                    'completed',
                                    'pending',
                                    'failed',
                                ] as const
                            ).map((item) => (
                                <Button
                                    key={item}
                                    asChild
                                    size="sm"
                                    variant={
                                        status === item ? 'secondary' : 'ghost'
                                    }
                                >
                                    <Link
                                        href={`/admin/m/modrixcommerce/refund-management?status=${item}`}
                                    >
                                        {item.charAt(0).toUpperCase() +
                                            item.slice(1)}
                                    </Link>
                                </Button>
                            ))}
                        </div>
                    )}
                </div>

                {activeTab === 'refunds' && (
                    <RefundsTable
                        refunds={refunds}
                        onTrack={(r) => setTrackingRefund(r)}
                        onOpenProcess={() => setActiveTab('orders')}
                    />
                )}

                {activeTab === 'credit_memos' && (
                    <CreditMemosTable
                        creditMemos={creditMemos}
                        onView={(memo) => setViewingMemo(memo)}
                    />
                )}

                {activeTab === 'orders' && (
                    <RefundableOrdersTable
                        orders={refundableOrders}
                        onRefund={(order) => setProcessOrder(order)}
                    />
                )}

                {processOrder && (
                    <ProcessRefundDialog
                        order={processOrder}
                        orders={refundableOrders}
                        onChangeOrder={(order) => setProcessOrder(order)}
                        channels={channels}
                        payoutStatuses={payoutStatuses}
                        defaults={defaults}
                        onClose={() => setProcessOrder(null)}
                    />
                )}

                {trackingRefund && (
                    <TrackPaymentDialog
                        refund={trackingRefund}
                        channels={channels}
                        payoutStatuses={payoutStatuses}
                        onClose={() => setTrackingRefund(null)}
                    />
                )}

                {viewingMemo && (
                    <CreditMemoPreviewDialog
                        memo={viewingMemo}
                        onClose={() => setViewingMemo(null)}
                    />
                )}
            </AdminPage>
        </>
    );
}

function StatCard({
    icon,
    label,
    value,
    detail,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    detail: string;
}) {
    return (
        <Card>
            <CardContent className="grid gap-1 pt-5">
                <div className="flex items-center justify-between text-xs font-medium text-muted-foreground">
                    <span>{label}</span>
                    {icon}
                </div>
                <div className="text-2xl font-semibold tabular-nums">
                    {value}
                </div>
                <div className="text-xs text-muted-foreground">{detail}</div>
            </CardContent>
        </Card>
    );
}

function RefundsTable({
    refunds,
    onTrack,
    onOpenProcess,
}: {
    refunds: RefundEntry[];
    onTrack: (refund: RefundEntry) => void;
    onOpenProcess: () => void;
}) {
    if (refunds.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-start justify-between gap-3 py-8 sm:flex-row sm:items-center">
                    <div>
                        <p className="font-medium">No refunds recorded yet</p>
                        <p className="text-sm text-muted-foreground">
                            Select a paid order to process a partial or full
                            refund and generate its credit memo.
                        </p>
                    </div>
                    <Button variant="outline" size="sm" onClick={onOpenProcess}>
                        View refundable orders
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Refund ledger & payment tracking</CardTitle>
                <CardDescription>
                    Every partial or full refund with its credit memo and
                    settlement status.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Refund / Date</TableHead>
                            <TableHead>Order & Customer</TableHead>
                            <TableHead>Type & Reason</TableHead>
                            <TableHead>Payment Tracking</TableHead>
                            <TableHead>Credit Memo</TableHead>
                            <TableHead className="text-right">Amount</TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {refunds.map((refund) => (
                            <TableRow key={refund.id}>
                                <TableCell className="align-top whitespace-nowrap">
                                    <div className="font-mono text-xs font-medium">
                                        #{refund.id}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {refund.created_at
                                            ? formatDate(refund.created_at)
                                            : '—'}
                                    </div>
                                    <div className="mt-1">
                                        <StatusBadge status={refund.status} />
                                    </div>
                                </TableCell>
                                <TableCell className="align-top">
                                    <Link
                                        href={refund.order_url}
                                        className="font-mono text-xs font-semibold underline"
                                    >
                                        {refund.order_number}
                                    </Link>
                                    <div className="text-sm">
                                        {refund.customer_name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {refund.customer_email}
                                    </div>
                                </TableCell>
                                <TableCell className="max-w-xs align-top">
                                    <div className="flex items-center gap-1.5">
                                        <Badge
                                            variant={
                                                refund.type === 'full'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {refund.type === 'full'
                                                ? 'Full refund'
                                                : 'Partial refund'}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            by {refund.actor}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-sm">
                                        {refund.reason}
                                    </p>
                                    {refund.items.length > 0 && (
                                        <ul className="mt-1 space-y-0.5 text-xs text-muted-foreground">
                                            {refund.items.map((line) => (
                                                <li key={line.id}>
                                                    {line.quantity} ×{' '}
                                                    {line.name}
                                                    {line.restocked
                                                        ? ' (restocked)'
                                                        : ''}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                    {refund.failure_reason && (
                                        <p className="mt-1 text-xs text-destructive">
                                            {refund.failure_reason}
                                        </p>
                                    )}
                                </TableCell>
                                <TableCell className="align-top text-xs">
                                    <div className="font-medium text-foreground">
                                        {refund.payment_track.channel_label}
                                    </div>
                                    <div className="mt-0.5">
                                        <Badge
                                            variant={
                                                refund.payment_track
                                                    .payout_status === 'settled'
                                                    ? 'default'
                                                    : refund.payment_track
                                                            .payout_status ===
                                                        'failed'
                                                      ? 'destructive'
                                                      : 'outline'
                                            }
                                        >
                                            {
                                                refund.payment_track
                                                    .payout_status_label
                                            }
                                        </Badge>
                                    </div>
                                    {refund.payment_track.reference && (
                                        <div className="mt-1 font-mono text-muted-foreground">
                                            Ref:{' '}
                                            {refund.payment_track.reference}
                                        </div>
                                    )}
                                    {refund.payment_track.destination && (
                                        <div className="text-muted-foreground">
                                            To:{' '}
                                            {refund.payment_track.destination}
                                        </div>
                                    )}
                                </TableCell>
                                <TableCell className="align-top">
                                    {refund.credit_memo ? (
                                        <div className="grid gap-1">
                                            <span className="font-mono text-xs font-semibold">
                                                {refund.credit_memo.number}
                                            </span>
                                            <a
                                                href={
                                                    refund.credit_memo.pdf_url
                                                }
                                                download
                                                className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                            >
                                                <Download
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                                Download PDF
                                            </a>
                                        </div>
                                    ) : (
                                        <Form
                                            action={refund.generate_memo_url}
                                            method="post"
                                            options={{ preserveScroll: true }}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    <FileText
                                                        className="size-3.5"
                                                        aria-hidden
                                                    />
                                                    Issue credit memo
                                                </Button>
                                            )}
                                        </Form>
                                    )}
                                </TableCell>
                                <TableCell className="text-right align-top font-semibold whitespace-nowrap text-destructive tabular-nums">
                                    −{refund.amount}
                                </TableCell>
                                <TableCell className="text-right align-top whitespace-nowrap">
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        onClick={() => onTrack(refund)}
                                    >
                                        Track payment
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}

function CreditMemosTable({
    creditMemos,
    onView,
}: {
    creditMemos: CreditMemoEntry[];
    onView: (memo: CreditMemoEntry) => void;
}) {
    if (creditMemos.length === 0) {
        return (
            <Card>
                <CardContent className="py-8 text-sm text-muted-foreground">
                    No credit memos have been issued yet.
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Issued credit memos</CardTitle>
                <CardDescription>
                    Numbered, immutable credit memos ready for accounting and
                    customer download.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Credit Memo #</TableHead>
                            <TableHead>Order</TableHead>
                            <TableHead>Customer</TableHead>
                            <TableHead>Issued</TableHead>
                            <TableHead className="text-right">
                                Subtotal / Tax
                            </TableHead>
                            <TableHead className="text-right">
                                Total Credited
                            </TableHead>
                            <TableHead className="text-right">
                                Document
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {creditMemos.map((memo) => (
                            <TableRow key={memo.id}>
                                <TableCell className="font-mono text-xs font-semibold">
                                    {memo.number}
                                </TableCell>
                                <TableCell>
                                    {memo.order_url ? (
                                        <Link
                                            href={memo.order_url}
                                            className="font-mono text-xs underline"
                                        >
                                            {memo.order_number}
                                        </Link>
                                    ) : (
                                        <span className="font-mono text-xs">
                                            {memo.order_number}
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="text-sm">
                                    <div>{memo.buyer_email}</div>
                                    {memo.notes && (
                                        <div className="text-xs text-muted-foreground">
                                            {memo.notes}
                                        </div>
                                    )}
                                </TableCell>
                                <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                    <div>{formatDate(memo.issued_at)}</div>
                                    <div>by {memo.issued_by}</div>
                                </TableCell>
                                <TableCell className="text-right text-xs tabular-nums">
                                    <div>Subtotal: {memo.subtotal}</div>
                                    <div className="text-muted-foreground">
                                        Tax: {memo.tax_total}
                                    </div>
                                </TableCell>
                                <TableCell className="text-right font-semibold tabular-nums">
                                    {memo.grand_total}
                                </TableCell>
                                <TableCell className="text-right whitespace-nowrap">
                                    <div className="inline-flex items-center gap-2">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => onView(memo)}
                                        >
                                            Preview
                                        </Button>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <a href={memo.pdf_url} download>
                                                <Download
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                                PDF
                                            </a>
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}

function RefundableOrdersTable({
    orders,
    onRefund,
}: {
    orders: RefundableOrder[];
    onRefund: (order: RefundableOrder) => void;
}) {
    if (orders.length === 0) {
        return (
            <Card>
                <CardContent className="py-8 text-sm text-muted-foreground">
                    There are currently no paid orders with a remaining
                    refundable balance.
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Refundable orders</CardTitle>
                <CardDescription>
                    Select an order to issue a partial item refund, custom
                    amount refund, or full refund.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Order</TableHead>
                            <TableHead>Customer</TableHead>
                            <TableHead>Payment Method</TableHead>
                            <TableHead className="text-right">Paid</TableHead>
                            <TableHead className="text-right">
                                Already Refunded
                            </TableHead>
                            <TableHead className="text-right">
                                Refundable Balance
                            </TableHead>
                            <TableHead className="text-right">Action</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {orders.map((order) => (
                            <TableRow key={order.id}>
                                <TableCell>
                                    <Link
                                        href={order.order_url}
                                        className="font-mono text-xs font-semibold underline"
                                    >
                                        {order.number}
                                    </Link>
                                    <div className="text-xs text-muted-foreground">
                                        {formatDate(order.placed_at)}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="text-sm font-medium">
                                        {order.customer_name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {order.email}
                                    </div>
                                </TableCell>
                                <TableCell className="text-sm">
                                    {order.payment_method_name}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {order.paid_total}
                                </TableCell>
                                <TableCell className="text-right text-muted-foreground tabular-nums">
                                    {order.refunded_total}
                                </TableCell>
                                <TableCell className="text-right font-semibold tabular-nums">
                                    {order.refundable_formatted}
                                </TableCell>
                                <TableCell className="text-right">
                                    <Button
                                        size="sm"
                                        onClick={() => onRefund(order)}
                                    >
                                        Process refund
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}

function ProcessRefundDialog({
    order,
    orders,
    onChangeOrder,
    channels,
    payoutStatuses,
    defaults,
    onClose,
}: {
    order: RefundableOrder;
    orders: RefundableOrder[];
    onChangeOrder: (order: RefundableOrder) => void;
    channels: Option[];
    payoutStatuses: Option[];
    defaults: {
        auto_generate_credit_memo: boolean;
        default_restock: boolean;
    };
    onClose: () => void;
}) {
    const [mode, setMode] = useState<'full' | 'items' | 'custom'>('items');
    const [quantities, setQuantities] = useState<Record<number, number>>(() =>
        Object.fromEntries(order.items.map((item) => [item.id, item.quantity])),
    );
    const [restocks, setRestocks] = useState<Record<number, number>>(() =>
        Object.fromEntries(
            order.items.map((item) => [
                item.id,
                defaults.default_restock ? item.restockable_quantity : 0,
            ]),
        ),
    );
    const [shippingRefund, setShippingRefund] = useState('0.00');
    const [adjustment, setAdjustment] = useState('0.00');
    const [customAmount, setCustomAmount] = useState(order.refundable_amount);

    const handleOrderSwitch = (orderId: number) => {
        const next = orders.find((o) => o.id === orderId);
        if (!next) return;
        onChangeOrder(next);
        setQuantities(
            Object.fromEntries(
                next.items.map((item) => [item.id, item.quantity]),
            ),
        );
        setRestocks(
            Object.fromEntries(
                next.items.map((item) => [
                    item.id,
                    defaults.default_restock ? item.restockable_quantity : 0,
                ]),
            ),
        );
        setShippingRefund('0.00');
        setAdjustment('0.00');
        setCustomAmount(next.refundable_amount);
    };

    const computedItemsTotal = order.items.reduce((sum, item) => {
        const qty = quantities[item.id] ?? 0;
        const unit = Number.parseFloat(item.effective_unit_amount) || 0;
        return sum + qty * unit;
    }, 0);

    const shippingNum = Number.parseFloat(shippingRefund) || 0;
    const adjustmentNum = Number.parseFloat(adjustment) || 0;
    const maxRefundable = Number.parseFloat(order.refundable_amount) || 0;
    const calculatedPartial = Math.min(
        maxRefundable,
        Math.max(0, computedItemsTotal + shippingNum + adjustmentNum),
    );

    const defaultChannel = order.is_manual_payment
        ? channels.some((c) => c.value === order.payment_method_code)
            ? order.payment_method_code
            : 'bank_transfer'
        : 'gateway';

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>Process refund — Order {order.number}</DialogTitle>
                <DialogDescription>
                    Refundable balance: {order.refundable_formatted} (
                    {order.payment_method_name}). Choose full, item-based
                    partial, or custom refund.
                </DialogDescription>

                {orders.length > 1 && (
                    <FormField id="select-order" label="Order">
                        <NativeSelect
                            id="select-order"
                            value={String(order.id)}
                            onChange={(e) =>
                                handleOrderSwitch(Number(e.target.value))
                            }
                        >
                            {orders.map((o) => (
                                <option key={o.id} value={o.id}>
                                    {o.number} — {o.customer_name} (Refundable:{' '}
                                    {o.refundable_formatted})
                                </option>
                            ))}
                        </NativeSelect>
                    </FormField>
                )}

                <Form
                    action={order.store_refund_url}
                    method="post"
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField id="refund-mode" label="Refund mode">
                                <NativeSelect
                                    id="refund-mode"
                                    name="mode"
                                    value={mode}
                                    onChange={(e) =>
                                        setMode(
                                            e.target.value as
                                                | 'full'
                                                | 'items'
                                                | 'custom',
                                        )
                                    }
                                >
                                    <option value="items">
                                        Partial / Line-item calculator
                                    </option>
                                    <option value="full">
                                        Full remaining balance (
                                        {order.refundable_formatted})
                                    </option>
                                    <option value="custom">
                                        Custom amount
                                    </option>
                                </NativeSelect>
                            </FormField>

                            {mode === 'full' && (
                                <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                    <div className="font-medium">
                                        Full refund of{' '}
                                        {order.refundable_formatted}
                                    </div>
                                    <label className="mt-2 flex items-center gap-2 text-sm">
                                        <input
                                            type="hidden"
                                            name="restock_all"
                                            value="0"
                                        />
                                        <Checkbox
                                            name="restock_all"
                                            value="1"
                                            defaultChecked={
                                                defaults.default_restock
                                            }
                                        />
                                        Return all remaining un-restocked items
                                        to inventory
                                    </label>
                                </div>
                            )}

                            {mode === 'items' && (
                                <div className="grid gap-3 rounded-md border p-3">
                                    <div className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Select items & restock quantities
                                    </div>
                                    {order.items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="grid gap-2 border-b pb-2 last:border-b-0 last:pb-0 sm:grid-cols-[1fr_auto_auto]"
                                        >
                                            <div>
                                                <div className="text-sm font-medium">
                                                    {item.product_name}{' '}
                                                    {item.variant_name &&
                                                        `(${item.variant_name})`}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    SKU: {item.sku} · Ordered:{' '}
                                                    {item.quantity} ·{' '}
                                                    {item.unit_price_formatted}{' '}
                                                    each
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <label
                                                    htmlFor={`qty-${item.id}`}
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Refund qty
                                                </label>
                                                <Input
                                                    id={`qty-${item.id}`}
                                                    name={`items[${item.id}][quantity]`}
                                                    type="number"
                                                    min={0}
                                                    max={item.quantity}
                                                    value={
                                                        quantities[item.id] ?? 0
                                                    }
                                                    onChange={(e) => {
                                                        const val = Math.max(
                                                            0,
                                                            Math.min(
                                                                item.quantity,
                                                                Number.parseInt(
                                                                    e.target
                                                                        .value ||
                                                                        '0',
                                                                    10,
                                                                ),
                                                            ),
                                                        );
                                                        setQuantities(
                                                            (prev) => ({
                                                                ...prev,
                                                                [item.id]: val,
                                                            }),
                                                        );
                                                    }}
                                                    className="w-20"
                                                />
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <label
                                                    htmlFor={`restock-${item.id}`}
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Restock
                                                </label>
                                                <Input
                                                    id={`restock-${item.id}`}
                                                    name={`items[${item.id}][restock]`}
                                                    type="number"
                                                    min={0}
                                                    max={
                                                        item.restockable_quantity
                                                    }
                                                    value={
                                                        restocks[item.id] ?? 0
                                                    }
                                                    disabled={
                                                        item.restockable_quantity ===
                                                        0
                                                    }
                                                    onChange={(e) => {
                                                        const val = Math.max(
                                                            0,
                                                            Math.min(
                                                                item.restockable_quantity,
                                                                Number.parseInt(
                                                                    e.target
                                                                        .value ||
                                                                        '0',
                                                                    10,
                                                                ),
                                                            ),
                                                        );
                                                        setRestocks((prev) => ({
                                                            ...prev,
                                                            [item.id]: val,
                                                        }));
                                                    }}
                                                    className="w-20"
                                                />
                                            </div>
                                        </div>
                                    ))}

                                    <div className="grid gap-3 pt-2 sm:grid-cols-3">
                                        <FormField
                                            id="shipping_refund"
                                            label={`Shipping refund (${order.currency})`}
                                        >
                                            <Input
                                                id="shipping_refund"
                                                name="shipping_refund"
                                                inputMode="decimal"
                                                value={shippingRefund}
                                                onChange={(e) =>
                                                    setShippingRefund(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id="adjustment_amount"
                                            label={`Adjustment (+/- ${order.currency})`}
                                        >
                                            <Input
                                                id="adjustment_amount"
                                                name="adjustment_amount"
                                                inputMode="decimal"
                                                value={adjustment}
                                                onChange={(e) =>
                                                    setAdjustment(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id="calculated-amount"
                                            label={`Refund total (${order.currency})`}
                                        >
                                            <Input
                                                id="calculated-amount"
                                                name="amount"
                                                inputMode="decimal"
                                                value={calculatedPartial.toFixed(
                                                    2,
                                                )}
                                                readOnly
                                                className="bg-muted font-semibold"
                                            />
                                        </FormField>
                                    </div>
                                </div>
                            )}

                            {mode === 'custom' && (
                                <FormField
                                    id="custom-amount"
                                    label={`Refund amount (${order.currency})`}
                                >
                                    <Input
                                        id="custom-amount"
                                        name="amount"
                                        inputMode="decimal"
                                        value={customAmount}
                                        onChange={(e) =>
                                            setCustomAmount(e.target.value)
                                        }
                                        required
                                    />
                                </FormField>
                            )}

                            <FormField id="refund-reason" label="Refund reason">
                                <Input
                                    id="refund-reason"
                                    name="reason"
                                    placeholder="e.g. Customer returned item, damaged in transit, price adjustment"
                                    required
                                />
                            </FormField>

                            <div className="grid gap-3 rounded-md border p-3 sm:grid-cols-2">
                                <FormField
                                    id="track-channel"
                                    label="Refund payment channel"
                                >
                                    <NativeSelect
                                        id="track-channel"
                                        name="channel"
                                        defaultValue={defaultChannel}
                                    >
                                        {channels.map((c) => (
                                            <option
                                                key={c.value}
                                                value={c.value}
                                            >
                                                {c.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    id="track-status"
                                    label="Payout status"
                                >
                                    <NativeSelect
                                        id="track-status"
                                        name="payout_status"
                                        defaultValue="settled"
                                    >
                                        {payoutStatuses.map((s) => (
                                            <option
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    id="track-reference"
                                    label="Transaction / payout reference (optional)"
                                >
                                    <Input
                                        id="track-reference"
                                        name="reference"
                                        placeholder="Gateway refund ID or bank transfer ref"
                                    />
                                </FormField>
                                <FormField
                                    id="track-destination"
                                    label="Recipient / account destination (optional)"
                                >
                                    <Input
                                        id="track-destination"
                                        name="destination"
                                        defaultValue={order.email}
                                    />
                                </FormField>
                            </div>

                            <FormField
                                id="credit-memo-notes"
                                label="Credit memo notes (optional)"
                            >
                                <Textarea
                                    id="credit-memo-notes"
                                    name="notes"
                                    rows={2}
                                    placeholder="Printed on the credit memo PDF"
                                />
                            </FormField>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="hidden"
                                    name="generate_credit_memo"
                                    value="0"
                                />
                                <Checkbox
                                    name="generate_credit_memo"
                                    value="1"
                                    defaultChecked={
                                        defaults.auto_generate_credit_memo
                                    }
                                />
                                Generate numbered credit memo immediately
                            </label>

                            <InputError
                                message={
                                    errors.order ??
                                    errors.amount ??
                                    errors.items ??
                                    errors.reason ??
                                    errors.shipping_refund ??
                                    errors.adjustment_amount
                                }
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Process refund
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function TrackPaymentDialog({
    refund,
    channels,
    payoutStatuses,
    onClose,
}: {
    refund: RefundEntry;
    channels: Option[];
    payoutStatuses: Option[];
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle>
                    Track refund payment — #{refund.id} ({refund.order_number})
                </DialogTitle>
                <DialogDescription>
                    Update how and when the refund of {refund.amount} was
                    settled with {refund.customer_name}.
                </DialogDescription>

                <Form
                    action={refund.update_track_url}
                    method="post"
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField id="channel" label="Payout channel">
                                    <NativeSelect
                                        id="channel"
                                        name="channel"
                                        defaultValue={
                                            refund.payment_track.channel
                                        }
                                    >
                                        {channels.map((c) => (
                                            <option
                                                key={c.value}
                                                value={c.value}
                                            >
                                                {c.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    id="payout_status"
                                    label="Payout status"
                                >
                                    <NativeSelect
                                        id="payout_status"
                                        name="payout_status"
                                        defaultValue={
                                            refund.payment_track.payout_status
                                        }
                                    >
                                        {payoutStatuses.map((s) => (
                                            <option
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                            </div>

                            <FormField
                                id="reference"
                                label="Settlement reference / transaction ID"
                            >
                                <Input
                                    id="reference"
                                    name="reference"
                                    defaultValue={
                                        refund.payment_track.reference
                                    }
                                    placeholder="e.g. re_3Q9x..., WIRE-99412"
                                />
                            </FormField>

                            <FormField
                                id="destination"
                                label="Recipient / account destination"
                            >
                                <Input
                                    id="destination"
                                    name="destination"
                                    defaultValue={
                                        refund.payment_track.destination
                                    }
                                />
                            </FormField>

                            <FormField id="notes" label="Internal payout notes">
                                <Textarea
                                    id="notes"
                                    name="notes"
                                    rows={3}
                                    defaultValue={refund.payment_track.notes}
                                />
                            </FormField>

                            <InputError
                                message={
                                    errors.channel ??
                                    errors.payout_status ??
                                    errors.reference
                                }
                            />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Close
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Save payment tracking
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function CreditMemoPreviewDialog({
    memo,
    onClose,
}: {
    memo: CreditMemoEntry;
    onClose: () => void;
}) {
    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>Credit Memo {memo.number}</DialogTitle>
                <DialogDescription>
                    Issued on {formatDate(memo.issued_at)} for order{' '}
                    {memo.order_number} ({memo.buyer_email}).
                </DialogDescription>

                <div className="grid gap-3 text-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Item</TableHead>
                                <TableHead className="text-right">
                                    Qty
                                </TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {memo.lines.map((line, idx) => (
                                <TableRow key={`${line.sku}-${idx}`}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {line.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            SKU: {line.sku}
                                            {line.restocked
                                                ? ' · Restocked'
                                                : ''}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {line.quantity}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {line.total}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    <div className="grid gap-1 border-t pt-2 text-right text-xs">
                        <div>Subtotal: {memo.subtotal}</div>
                        {memo.shipping_refund && (
                            <div>Shipping refunded: {memo.shipping_refund}</div>
                        )}
                        <div>Tax: {memo.tax_total}</div>
                        {memo.adjustment_amount && (
                            <div>Adjustment: {memo.adjustment_amount}</div>
                        )}
                        <div className="text-sm font-semibold">
                            Total credited: {memo.grand_total}
                        </div>
                    </div>

                    {memo.notes && (
                        <div className="rounded-md bg-muted/40 p-2.5 text-xs">
                            <span className="font-medium">Notes: </span>
                            {memo.notes}
                        </div>
                    )}
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Close
                        </Button>
                    </DialogClose>
                    <Button asChild>
                        <a href={memo.pdf_url} download>
                            <Download className="size-4" aria-hidden />
                            Download PDF
                        </a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
