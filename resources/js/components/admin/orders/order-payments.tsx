import { Download } from 'lucide-react';
import { StatusBadge } from '@/components/admin/status-badge';
import { formatDate } from '@/components/storefront/order-view';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { AdminOrder } from './types';

/**
 * Money in and out of the order: totals with the balance due, and every
 * payment and refund with its method, reference and receipt.
 */
export function OrderPayments({ order }: { order: AdminOrder }) {
    const owing = Number(order.balance_due.amount) > 0;
    const rows = [
        ...order.payments.map((p) => ({
            key: `p${p.id}`,
            at: p.paid_at ?? p.created_at,
            type: 'Payment',
            method: p.method_label,
            amount: p.amount.formatted,
            status: p.status,
            reference: p.reference,
            by: p.recorded_by,
            note: p.note,
            failure: p.failure_reason,
            receipt: p.receipt_url,
            refund: false,
        })),
        ...order.refunds.map((r) => ({
            key: `r${r.id}`,
            at: r.created_at,
            type: 'Refund',
            method: order.payment_method,
            amount: `−${r.amount.formatted}`,
            status: r.status,
            reference: r.reference,
            by: r.recorded_by,
            note: r.reason,
            failure: r.failure_reason,
            receipt: null,
            refund: true,
        })),
    ].sort((a, b) => (b.at ?? '').localeCompare(a.at ?? ''));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Payments</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
                <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Figure label="Total" value={order.grand_total.formatted} />
                    <Figure label="Paid" value={order.paid_total.formatted} />
                    <Figure
                        label="Refunded"
                        value={order.refunded_total.formatted}
                    />
                    <Figure
                        label="Balance due"
                        value={order.balance_due.formatted}
                        highlight={owing && order.status !== 'cancelled'}
                    />
                </dl>

                {rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No payments yet ({order.payment_method}).
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                                <TableHead>
                                    <span className="sr-only">Receipt</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <TableRow key={row.key}>
                                    <TableCell className="align-top text-xs whitespace-nowrap text-muted-foreground">
                                        {row.at ? formatDate(row.at) : '—'}
                                    </TableCell>
                                    <TableCell className="align-top">
                                        <div className="font-medium">
                                            {row.type} · {row.method}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {[
                                                row.reference &&
                                                    `Ref ${row.reference}`,
                                                row.by && `by ${row.by}`,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </div>
                                        {row.note && (
                                            <div className="text-xs text-muted-foreground">
                                                {row.note}
                                            </div>
                                        )}
                                        {row.failure && (
                                            <div className="text-xs text-destructive">
                                                {row.failure}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="align-top">
                                        <StatusBadge status={row.status} />
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            'text-right align-top tabular-nums',
                                            row.refund && 'text-destructive',
                                        )}
                                    >
                                        {row.amount}
                                    </TableCell>
                                    <TableCell className="text-right align-top">
                                        {row.receipt && (
                                            <a
                                                href={row.receipt}
                                                download
                                                className="inline-flex text-muted-foreground hover:text-foreground"
                                                aria-label="Download receipt"
                                                title="Download receipt"
                                            >
                                                <Download className="size-4" />
                                            </a>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}

function Figure({
    label,
    value,
    highlight = false,
}: {
    label: string;
    value: string;
    highlight?: boolean;
}) {
    return (
        <div
            className={cn(
                'grid gap-0.5 rounded-md border p-3',
                highlight &&
                    'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950',
            )}
        >
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-base font-semibold tabular-nums">{value}</dd>
        </div>
    );
}
