import { Form, Head, Link } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { formatDate } from '@/components/storefront/order-view';
import { Badge } from '@/components/ui/badge';
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
import { index } from '@/routes/admin/carts';
import { index as ordersIndex } from '@/routes/admin/orders';
import type { MoneyValue, Paginated } from '@/types';

type CartRow = {
    id: number;
    customer: { name: string; email: string } | null;
    items_count: number;
    products: string[];
    more_products: number;
    subtotal: MoneyValue;
    coupon_code: string | null;
    updated_at: string | null;
};

export default function CustomerCartsIndex({
    carts,
    filters,
}: {
    carts: Paginated<CartRow>;
    filters: { search: string };
}) {
    return (
        <>
            <Head title="Customer carts" />
            <AdminPage>
                <PageHeader
                    title="Customer carts"
                    description="What shoppers have in their carts right now, most recently active first. Guests show without a name."
                />
                <Form
                    {...index.form()}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    <div className="grid gap-2 sm:w-80">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            type="search"
                            defaultValue={filters.search}
                            placeholder="Customer name or email"
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" variant="secondary">
                            Search
                        </Button>
                        {filters.search && (
                            <Button variant="ghost" asChild>
                                <Link href={index()}>Clear</Link>
                            </Button>
                        )}
                    </div>
                </Form>
                {carts.data.length === 0 ? (
                    <EmptyState
                        icon={ShoppingCart}
                        title={
                            filters.search
                                ? 'No carts found'
                                : 'No carts with items'
                        }
                        description="Carts show here as soon as a shopper adds something."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Customer</TableHead>
                                <TableHead>Products</TableHead>
                                <TableHead className="text-right">
                                    Items
                                </TableHead>
                                <TableHead className="text-right">
                                    Subtotal
                                </TableHead>
                                <TableHead>Last activity</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {carts.data.map((cart) => (
                                <TableRow key={cart.id}>
                                    <TableCell>
                                        {cart.customer ? (
                                            <>
                                                <div className="font-medium">
                                                    {cart.customer.name}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {cart.customer.email}
                                                </div>
                                            </>
                                        ) : (
                                            <Badge
                                                className="status-badge"
                                                data-tone="neutral"
                                            >
                                                Guest
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {cart.products.join(', ')}
                                        {cart.more_products > 0 && (
                                            <div className="text-muted-foreground">
                                                +{cart.more_products} more
                                            </div>
                                        )}
                                        {cart.coupon_code && (
                                            <div className="text-muted-foreground">
                                                Coupon {cart.coupon_code}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {cart.items_count}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {cart.subtotal.formatted}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {cart.updated_at
                                            ? formatDate(cart.updated_at)
                                            : '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination
                    from={carts.from}
                    to={carts.to}
                    total={carts.total}
                    prevUrl={carts.prev_page_url}
                    nextUrl={carts.next_page_url}
                />
            </AdminPage>
        </>
    );
}

CustomerCartsIndex.layout = {
    breadcrumbs: [
        { title: 'Orders', href: ordersIndex() },
        { title: 'Customer carts', href: index() },
    ],
};
