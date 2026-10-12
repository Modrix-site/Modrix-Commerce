import { Form, Head, Link } from '@inertiajs/react';
import MarketplaceAdminController from '@/actions/App/Http/Controllers/Admin/MarketplaceAdminController';
import { EmptyState } from '@/components/admin/empty-state';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/admin/marketplace/orders';
import type { MoneyValue, Paginated } from '@/types';

type MarketplaceOrderRow = {
    number: string;
    email: string;
    status: string;
    total: MoneyValue;
    items: string[];
    created_at: string | null;
};

type Props = {
    orders: Paginated<MarketplaceOrderRow>;
    search: string;
};

export default function MarketplaceOrders({ orders, search }: Props) {
    return (
        <>
            <Head title="Marketplace orders" />
            <AdminPage>
                <PageHeader
                    title="Marketplace orders"
                    description="License purchases. Refunding an order refunds the payment with Stripe and stops its licenses from activating or downloading."
                />

                <Form
                    {...index.form()}
                    className="flex flex-wrap items-end gap-2"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            type="search"
                            defaultValue={search}
                            placeholder="Order number or email"
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                    {search !== '' && (
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Clear</Link>
                        </Button>
                    )}
                </Form>

                {orders.data.length === 0 ? (
                    <EmptyState title="No orders" />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Order</TableHead>
                                <TableHead>Placed</TableHead>
                                <TableHead>Buyer</TableHead>
                                <TableHead>Products</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {orders.data.map((o) => (
                                <TableRow key={o.number}>
                                    <TableCell className="font-mono text-xs">
                                        {o.number}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {o.created_at
                                            ? new Date(
                                                  o.created_at,
                                              ).toLocaleDateString()
                                            : '—'}
                                    </TableCell>
                                    <TableCell>{o.email}</TableCell>
                                    <TableCell>{o.items.join(', ')}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {o.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {o.total.formatted}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {o.status === 'paid' && (
                                            <RefundDialog order={o} />
                                        )}
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

function RefundDialog({ order }: { order: MarketplaceOrderRow }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Refund
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Refund {order.number}?</DialogTitle>
                <DialogDescription>
                    {order.total.formatted} is refunded in full. The buyer's
                    licenses are marked refunded: stores keep the files they
                    installed but receive no further updates.
                </DialogDescription>
                <Form
                    {...MarketplaceAdminController.refund.form(order.number)}
                    options={{ preserveScroll: true }}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField
                                id="reason"
                                label="Reason"
                                error={errors.reason}
                            >
                                <Textarea
                                    id="reason"
                                    name="reason"
                                    rows={3}
                                    required
                                />
                            </FormField>
                            <InputError message={errors.order} />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Refund
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

MarketplaceOrders.layout = {
    breadcrumbs: [{ title: 'Marketplace orders', href: index() }],
};
