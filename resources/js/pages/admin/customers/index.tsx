import { Form, Head, Link } from '@inertiajs/react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
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
import { index, show } from '@/routes/admin/customers';
import type { Paginated } from '@/types';

type CustomerRow = {
    id: number;
    name: string;
    email: string;
    registered: boolean;
    orders_count: number;
};

export default function CustomersIndex({
    customers,
    filters,
}: {
    customers: Paginated<CustomerRow>;
    filters: { search: string };
}) {
    return (
        <>
            <Head title="Customers" />
            <AdminPage>
                <PageHeader
                    title="Customers"
                    description="Registered customers and guest buyers."
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
                            placeholder="Name or email"
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
                {customers.data.length === 0 ? (
                    <EmptyState title="No customers found" />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Customer</TableHead>
                                <TableHead>Account</TableHead>
                                <TableHead className="text-right">
                                    Orders
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {customers.data.map((c) => (
                                <TableRow key={c.id}>
                                    <TableCell>
                                        <Link
                                            href={show(c.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {c.name}
                                        </Link>
                                        <div className="text-muted-foreground">
                                            {c.email}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {c.registered ? (
                                            <Badge variant="secondary">
                                                Registered
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                Guest
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {c.orders_count}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination
                    from={customers.from}
                    to={customers.to}
                    total={customers.total}
                    prevUrl={customers.prev_page_url}
                    nextUrl={customers.next_page_url}
                />
            </AdminPage>
        </>
    );
}

CustomersIndex.layout = {
    breadcrumbs: [{ title: 'Customers', href: index() }],
};
