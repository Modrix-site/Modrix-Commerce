import { Form, Head, Link } from '@inertiajs/react';
import { EmptyState } from '@/components/admin/empty-state';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
import { formatDate } from '@/components/storefront/order-view';
import { Button } from '@/components/ui/button';
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
import { index, show } from '@/routes/admin/orders';
import type { Option, OrderSummary, Paginated } from '@/types';

type Filters = {
    search: string;
    status: string;
    payment_status: string;
    fulfillment_status: string;
    from: string;
    to: string;
};

type Props = {
    orders: Paginated<OrderSummary>;
    filters: Filters;
    statuses: Option[];
    paymentStatuses: Option[];
    fulfillmentStatuses: Option[];
};

export default function OrdersIndex({
    orders,
    filters,
    statuses,
    paymentStatuses,
    fulfillmentStatuses,
}: Props) {
    const filtered = Object.values(filters).some((v) => v !== '');

    const select = (name: keyof Filters, label: string, options: Option[]) => (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <NativeSelect id={name} name={name} defaultValue={filters[name]}>
                <option value="">Any</option>
                {options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </NativeSelect>
        </div>
    );

    return (
        <>
            <Head title="Orders" />
            <AdminPage>
                <PageHeader
                    title="Orders"
                    description="Dates use the store timezone."
                />

                <Form
                    {...index.form()}
                    className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6 lg:items-end"
                >
                    <div className="grid gap-2 sm:col-span-3 lg:col-span-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            type="search"
                            defaultValue={filters.search}
                            placeholder="Order number or email"
                        />
                    </div>
                    {select('status', 'Status', statuses)}
                    {select('payment_status', 'Payment', paymentStatuses)}
                    {select(
                        'fulfillment_status',
                        'Fulfilment',
                        fulfillmentStatuses,
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            name="from"
                            type="date"
                            defaultValue={filters.from}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            name="to"
                            type="date"
                            defaultValue={filters.to}
                        />
                    </div>
                    <div className="flex gap-2 sm:col-span-3 lg:col-span-6">
                        <Button type="submit" variant="secondary">
                            Filter
                        </Button>
                        {filtered && (
                            <Button variant="ghost" asChild>
                                <Link href={index()}>Clear</Link>
                            </Button>
                        )}
                    </div>
                </Form>

                {orders.data.length === 0 ? (
                    <EmptyState
                        title={
                            filtered
                                ? 'No orders match these filters'
                                : 'No orders yet'
                        }
                        description={
                            filtered
                                ? 'Try different filters.'
                                : 'Orders placed on the storefront appear here.'
                        }
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Order</TableHead>
                                <TableHead>Placed</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Payment</TableHead>
                                <TableHead>Fulfilment</TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {orders.data.map((order) => (
                                <TableRow key={order.number}>
                                    <TableCell>
                                        <Link
                                            href={show(order.number)}
                                            className="font-medium hover:underline"
                                        >
                                            {order.number}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {formatDate(order.placed_at)}
                                    </TableCell>
                                    <TableCell>{order.email}</TableCell>
                                    <TableCell>
                                        <StatusBadge status={order.status} />
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={order.payment_status}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={order.fulfillment_status}
                                        />
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {order.grand_total.formatted}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <Pagination
                    from={orders.from}
                    to={orders.to}
                    total={orders.total}
                    prevUrl={orders.prev_page_url}
                    nextUrl={orders.next_page_url}
                />
            </AdminPage>
        </>
    );
}

OrdersIndex.layout = {
    breadcrumbs: [{ title: 'Orders', href: index() }],
};
