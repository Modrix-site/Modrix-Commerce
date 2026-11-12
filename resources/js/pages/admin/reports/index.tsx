import { Form, Head, Link, router } from '@inertiajs/react';
import {
    Banknote,
    ShoppingBag,
    Wallet,
    RotateCcw,
    Receipt,
    Clock,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { BarChart } from '@/components/admin/bar-chart';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/admin/reports';
import type { MoneyValue } from '@/types';

type Report = {
    from: string;
    to: string;
    timezone: string;
    currency: string;
    gross_sales: MoneyValue;
    payments_received: MoneyValue;
    net_collections: MoneyValue;
    outstanding: MoneyValue;
    refunds: MoneyValue;
    average_order_value: MoneyValue | null;
    orders: number;
    total_orders: number;
    paid_orders: number;
    payment_count: number;
    orders_by_status: Record<string, number>;
    payment_statuses: Record<string, number>;
    series: {
        date: string;
        orders: number;
        sales: MoneyValue;
        payments: MoneyValue;
    }[];
    top_products: {
        sku: string;
        name: string;
        quantity: number;
        revenue: MoneyValue;
    }[];
    recent_orders: {
        id: number;
        number: string;
        date: string;
        status: string;
        payment_status: string;
        total: MoneyValue;
        paid: MoneyValue;
    }[];
};
type Currency = {
    code: string;
    orders: number;
    sales: MoneyValue;
    payments: MoneyValue;
};
export default function Reports({
    report,
    currencies,
    storeCurrency,
    today,
}: {
    report: Report;
    currencies: Currency[];
    storeCurrency: string;
    today: string;
}) {
    const [chart, setChart] = useState<'sales' | 'payments'>('sales');
    const money = (value: number) =>
        new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: report.currency,
        }).format(value);
    const amount = (value: MoneyValue) => money(Number(value.amount));
    const changeCurrency = (currency: string) =>
        router.get(
            index.url(),
            { from: report.from, to: report.to, currency },
            { preserveScroll: true },
        );
    const preset = (days: number) => {
        const from = new Date(today + 'T12:00:00Z');
        from.setUTCDate(from.getUTCDate() - days + 1);
        router.get(
            index.url(),
            {
                from: from.toISOString().slice(0, 10),
                to: today,
                currency: report.currency,
            },
            { preserveScroll: true },
        );
    };
    return (
        <>
            <Head title="Sales reports" />
            <AdminPage>
                <PageHeader
                    title="Sales reports"
                    description="Track order sales, money received, refunds and outstanding balances."
                />
                <Card>
                    <CardContent className="pt-6">
                        <Form
                            key={`${report.from}:${report.to}:${report.currency}`}
                            {...index.form()}
                            className="flex flex-wrap items-end gap-3"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="from">From</Label>
                                        <Input
                                            id="from"
                                            name="from"
                                            type="date"
                                            defaultValue={report.from}
                                        />
                                        <InputError message={errors.from} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="to">To</Label>
                                        <Input
                                            id="to"
                                            name="to"
                                            type="date"
                                            defaultValue={report.to}
                                        />
                                        <InputError message={errors.to} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="report-currency">
                                            Order currency
                                        </Label>
                                        <select
                                            id="report-currency"
                                            name="currency"
                                            defaultValue={report.currency}
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            {currencies.map((item) => (
                                                <option
                                                    key={item.code}
                                                    value={item.code}
                                                >
                                                    {item.code} · {item.orders}{' '}
                                                    orders
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.currency} />
                                    </div>
                                    <Button disabled={processing}>
                                        Apply filters
                                    </Button>
                                    <div className="flex flex-wrap gap-2 sm:ms-auto">
                                        {[
                                            [1, 'Today'],
                                            [7, '7 days'],
                                            [30, '30 days'],
                                            [90, '90 days'],
                                        ].map(([days, label]) => (
                                            <Button
                                                key={days}
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    preset(Number(days))
                                                }
                                            >
                                                {label}
                                            </Button>
                                        ))}
                                    </div>
                                </>
                            )}
                        </Form>
                        <p className="mt-4 text-xs text-muted-foreground">
                            {report.from} – {report.to} · {report.timezone} ·
                            Amounts in original order currency. Sales include
                            non-cancelled orders; payments use the payment date.
                        </p>
                    </CardContent>
                </Card>
                {currencies.length > 1 && (
                    <div
                        className="flex flex-wrap gap-2"
                        aria-label="Currency summaries"
                    >
                        {currencies.map((item) => (
                            <Button
                                key={item.code}
                                variant={
                                    item.code === report.currency
                                        ? 'secondary'
                                        : 'outline'
                                }
                                onClick={() => changeCurrency(item.code)}
                            >
                                {item.code}{' '}
                                <span className="text-xs opacity-70">
                                    {item.orders} orders
                                </span>
                            </Button>
                        ))}
                        <p className="w-full text-xs text-muted-foreground">
                            Store currency: {storeCurrency}. Each currency is
                            reported separately; historical orders retain their
                            original currency.
                        </p>
                    </div>
                )}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <Metric
                        icon={ShoppingBag}
                        label="Order sales"
                        value={amount(report.gross_sales)}
                        hint="Order totals including shipping and tax; excludes cancellations."
                    />
                    <Metric
                        icon={Banknote}
                        label="Payments received"
                        value={amount(report.payments_received)}
                        hint={`${report.payment_count} successful payments received in this period.`}
                    />
                    <Metric
                        icon={Wallet}
                        label="Net collections"
                        value={amount(report.net_collections)}
                        hint="Successful payments minus completed refunds in this period."
                    />
                    <Metric
                        icon={Receipt}
                        label="Orders"
                        value={report.orders.toLocaleString()}
                        hint={`${report.paid_orders} fully paid · Average ${report.average_order_value ? amount(report.average_order_value) : '—'}`}
                    />
                    <Metric
                        icon={Clock}
                        label="Outstanding balance"
                        value={amount(report.outstanding)}
                        hint="Amount still due on non-cancelled orders placed in this period."
                    />
                    <Metric
                        icon={RotateCcw}
                        label="Completed refunds"
                        value={amount(report.refunds)}
                        hint="Counted on the refund record date; pending and failed refunds excluded."
                    />
                </div>
                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle>Performance over time</CardTitle>
                            <CardDescription className="mt-1">
                                Daily totals in {report.currency} ·{' '}
                                {report.timezone}
                            </CardDescription>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                variant={
                                    chart === 'sales' ? 'secondary' : 'outline'
                                }
                                onClick={() => setChart('sales')}
                            >
                                Order sales
                            </Button>
                            <Button
                                size="sm"
                                variant={
                                    chart === 'payments'
                                        ? 'secondary'
                                        : 'outline'
                                }
                                onClick={() => setChart('payments')}
                            >
                                Payments
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {report.series.some(
                            (day) => Number(day[chart].amount) > 0,
                        ) ? (
                            <BarChart
                                label={
                                    chart === 'sales'
                                        ? 'Daily order sales'
                                        : 'Daily payments received'
                                }
                                data={report.series.map((day) => ({
                                    label: day.date,
                                    value: Number(day[chart].amount),
                                }))}
                                formatValue={money}
                            />
                        ) : (
                            <div className="flex h-48 items-center justify-center rounded-lg border border-dashed bg-muted/20 text-center">
                                <div>
                                    <p className="font-medium">
                                        No{' '}
                                        {chart === 'sales'
                                            ? 'order sales'
                                            : 'successful payments'}{' '}
                                        in this period
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Try another date range or currency.
                                    </p>
                                </div>
                            </div>
                        )}
                        <details className="mt-4 text-sm">
                            <summary className="cursor-pointer text-muted-foreground">
                                View daily breakdown
                            </summary>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Orders</TableHead>
                                        <TableHead>Order sales</TableHead>
                                        <TableHead>Payments received</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.series.map((day) => (
                                        <TableRow key={day.date}>
                                            <TableCell>{day.date}</TableCell>
                                            <TableCell>{day.orders}</TableCell>
                                            <TableCell>
                                                {amount(day.sales)}
                                            </TableCell>
                                            <TableCell>
                                                {amount(day.payments)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </details>
                    </CardContent>
                </Card>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Top products</CardTitle>
                            <CardDescription>
                                Units ordered and product revenue after
                                discounts.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {report.top_products.length ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Product</TableHead>
                                            <TableHead className="text-right">
                                                Units
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Revenue
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {report.top_products.map((product) => (
                                            <TableRow key={product.sku}>
                                                <TableCell>
                                                    {product.name}
                                                    <div className="text-xs text-muted-foreground">
                                                        {product.sku}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {product.quantity}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {amount(product.revenue)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="py-6 text-sm text-muted-foreground">
                                    No products sold in this period and
                                    currency.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Order health</CardTitle>
                            <CardDescription>
                                {report.total_orders} orders placed in this
                                period, including cancellations.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6 sm:grid-cols-2">
                            <StatusList
                                title="Order status"
                                counts={report.orders_by_status}
                                total={report.total_orders}
                            />
                            <StatusList
                                title="Payment status"
                                counts={report.payment_statuses}
                                total={report.total_orders}
                            />
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Recent orders in this report</CardTitle>
                        <CardDescription>
                            Open an order to reconcile its payment and totals.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {report.recent_orders.length ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Order</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Payment</TableHead>
                                        <TableHead className="text-right">
                                            Order total
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Paid to date
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.recent_orders.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell>
                                                <Link
                                                    className="font-medium text-primary underline-offset-4 hover:underline"
                                                    href={`/admin/orders/${encodeURIComponent(order.number)}`}
                                                >
                                                    {order.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {order.date}
                                            </TableCell>
                                            <TableCell>
                                                {order.status}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        order.payment_status ===
                                                        'Paid'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {order.payment_status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {amount(order.total)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {amount(order.paid)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <p className="py-6 text-sm text-muted-foreground">
                                No orders match your filters.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}
function Metric({
    icon: Icon,
    label,
    value,
    hint,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    hint: string;
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardDescription>{label}</CardDescription>
                    <span className="rounded-lg bg-primary/10 p-2 text-primary">
                        <Icon className="size-4" />
                    </span>
                </div>
                <CardTitle className="text-2xl tabular-nums">{value}</CardTitle>
                <CardDescription className="text-xs leading-relaxed">
                    {hint}
                </CardDescription>
            </CardHeader>
        </Card>
    );
}
function StatusList({
    title,
    counts,
    total,
}: {
    title: string;
    counts: Record<string, number>;
    total: number;
}) {
    return (
        <div className="grid content-start gap-3">
            <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                {title}
            </h3>
            {Object.entries(counts).map(([status, count]) => (
                <div key={status}>
                    <div className="mb-1 flex justify-between gap-2 text-sm">
                        <span className="capitalize">
                            {status.replaceAll('_', ' ')}
                        </span>
                        <span className="tabular-nums">{count}</span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-primary"
                            style={{
                                width: `${total ? (count / total) * 100 : 0}%`,
                            }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}
Reports.layout = { breadcrumbs: [{ title: 'Reports', href: index() }] };
