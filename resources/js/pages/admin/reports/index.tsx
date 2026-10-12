import { Form, Head } from '@inertiajs/react';
import { BarChart } from '@/components/admin/bar-chart';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
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
    orders: number;
    average_order_value: MoneyValue | null;
    refunds: MoneyValue;
    net_sales: MoneyValue;
    orders_by_status: Record<string, number>;
    series: { date: string; orders: number; sales: MoneyValue }[];
    top_products: {
        sku: string;
        name: string;
        quantity: number;
        revenue: MoneyValue;
    }[];
};

export default function Reports({ report }: { report: Report }) {
    const money = (value: number) =>
        new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: report.currency,
        }).format(value);

    return (
        <>
            <Head title="Reports" />
            <AdminPage>
                <PageHeader
                    title="Reports"
                    description={`${report.from} to ${report.to} (${report.timezone}). Sales exclude cancelled orders; refunds are counted on the day they were made.`}
                />
                <Form
                    {...index.form()}
                    className="flex flex-wrap items-end gap-3"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            name="from"
                            type="date"
                            defaultValue={report.from}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            name="to"
                            type="date"
                            defaultValue={report.to}
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        Update
                    </Button>
                </Form>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Metric
                        label="Gross sales"
                        value={report.gross_sales.formatted}
                    />
                    <Metric
                        label="Orders"
                        value={report.orders.toLocaleString()}
                    />
                    <Metric
                        label="Average order value"
                        value={report.average_order_value?.formatted ?? '—'}
                    />
                    <Metric
                        label="Refunds"
                        value={report.refunds.formatted}
                        hint={`Net ${report.net_sales.formatted}`}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Sales over time</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <BarChart
                            label="Daily sales"
                            data={report.series.map((d) => ({
                                label: d.date,
                                value: Number(d.sales.amount),
                            }))}
                            formatValue={money}
                        />
                        <details className="mt-4 text-sm">
                            <summary className="cursor-pointer text-muted-foreground">
                                Show data table
                            </summary>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">
                                            Orders
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Sales
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.series.map((d) => (
                                        <TableRow key={d.date}>
                                            <TableCell>{d.date}</TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {d.orders}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {d.sales.formatted}
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
                                By units sold, net of discounts.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {report.top_products.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No sales in this period.
                                </p>
                            ) : (
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
                                        {report.top_products.map((p) => (
                                            <TableRow key={p.sku}>
                                                <TableCell>
                                                    {p.name}
                                                    <div className="text-xs text-muted-foreground">
                                                        {p.sku}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {p.quantity}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {p.revenue.formatted}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Orders by status</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-2 text-sm">
                                {Object.entries(report.orders_by_status).map(
                                    ([status, count]) => (
                                        <div
                                            key={status}
                                            className="flex justify-between"
                                        >
                                            <dt className="capitalize">
                                                {status.replace('_', ' ')}
                                            </dt>
                                            <dd className="tabular-nums">
                                                {count}
                                            </dd>
                                        </div>
                                    ),
                                )}
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </AdminPage>
        </>
    );
}

function Metric({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{value}</CardTitle>
                {hint && <CardDescription>{hint}</CardDescription>}
            </CardHeader>
        </Card>
    );
}

Reports.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};
