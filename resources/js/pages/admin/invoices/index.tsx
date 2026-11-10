import { Form, Head, Link } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
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
import { index as ordersIndex } from '@/routes/admin/orders';
import { index } from '@/routes/admin/invoices';
import type { MoneyValue, Paginated } from '@/types';

type InvoiceRow = {
    id: number;
    number: string;
    issued_at: string;
    order_number: string | null;
    order_url: string | null;
    pdf_url: string | null;
    buyer_name: string;
    buyer_email: string;
    total: MoneyValue;
};

export default function InvoicesIndex({
    invoices,
    filters,
}: {
    invoices: Paginated<InvoiceRow>;
    filters: { search: string };
}) {
    return (
        <>
            <Head title="Invoices" />
            <AdminPage>
                <PageHeader
                    title="Invoices"
                    description="Every invoice issued for an order. Issue an invoice from the order's page."
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
                            placeholder="Invoice number, order number or email"
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
                {invoices.data.length === 0 ? (
                    <EmptyState
                        icon={FileText}
                        title={
                            filters.search
                                ? 'No invoices found'
                                : 'No invoices yet'
                        }
                        description="Invoices appear here once they are issued from an order."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Invoice</TableHead>
                                <TableHead>Order</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Issued</TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                                <TableHead className="text-right">
                                    <span className="sr-only">PDF</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {invoices.data.map((invoice) => (
                                <TableRow key={invoice.id}>
                                    <TableCell className="font-semibold">
                                        {invoice.number}
                                    </TableCell>
                                    <TableCell>
                                        {invoice.order_url ? (
                                            <Link
                                                href={invoice.order_url}
                                                className="hover:underline"
                                            >
                                                {invoice.order_number}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-medium">
                                            {invoice.buyer_name ||
                                                invoice.buyer_email}
                                        </div>
                                        {invoice.buyer_name && (
                                            <div className="text-muted-foreground">
                                                {invoice.buyer_email}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {formatDate(invoice.issued_at)}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {invoice.total.formatted}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {invoice.pdf_url && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={invoice.pdf_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Download /> PDF
                                                </a>
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination
                    from={invoices.from}
                    to={invoices.to}
                    total={invoices.total}
                    prevUrl={invoices.prev_page_url}
                    nextUrl={invoices.next_page_url}
                />
            </AdminPage>
        </>
    );
}

InvoicesIndex.layout = {
    breadcrumbs: [
        { title: 'Orders', href: ordersIndex() },
        { title: 'Invoices', href: index() },
    ],
};
