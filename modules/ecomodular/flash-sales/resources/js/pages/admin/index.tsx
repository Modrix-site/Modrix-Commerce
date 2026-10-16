import { Form, Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type SaleState = 'scheduled' | 'live' | 'ended' | 'off';

type Sale = {
    id: number;
    name: string;
    discount_type: 'percent' | 'fixed';
    discount_value: string;
    starts_at: string;
    ends_at: string;
    state: SaleState;
    products_count: number;
    edit_url: string;
    delete_url: string;
};

const stateBadge: Record<
    SaleState,
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    live: { label: 'Live', variant: 'default' },
    scheduled: { label: 'Scheduled', variant: 'secondary' },
    ended: { label: 'Ended', variant: 'outline' },
    off: { label: 'Off', variant: 'outline' },
};

export function discountLabel(
    type: 'percent' | 'fixed',
    value: string,
    currency: string,
): string {
    const n = Number(value);

    return type === 'percent'
        ? `${Number.isInteger(n) ? n : n.toFixed(2)}% off`
        : `${n.toFixed(2)} ${currency} off`;
}

export function dateRange(start: string, end: string): string {
    const f = new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

    return `${f.format(new Date(start))} – ${f.format(new Date(end))}`;
}

export default function FlashSalesIndex({
    sales,
    currency,
    createUrl,
}: {
    sales: Sale[];
    currency: string;
    createUrl: string;
}) {
    return (
        <>
            <Head title="Flash Sales" />
            <AdminPage>
                <PageHeader
                    title="Flash Sales"
                    description="Time-limited discounts. Shoppers see the sale price, the original price crossed out, a discount badge and a countdown; the cart charges the sale price."
                    actions={
                        <Button asChild>
                            <Link href={createUrl}>
                                <Plus /> New flash sale
                            </Link>
                        </Button>
                    }
                />

                {sales.length === 0 ? (
                    <EmptyState
                        title="No flash sales yet"
                        description="Create a sale, set its dates and discount, then add products — or switch the sale on from a product's editor."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Sale</TableHead>
                                <TableHead>Discount</TableHead>
                                <TableHead>When</TableHead>
                                <TableHead>Products</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sales.map((sale) => (
                                <TableRow key={sale.id}>
                                    <TableCell className="font-medium">
                                        <Link
                                            href={sale.edit_url}
                                            className="hover:underline"
                                        >
                                            {sale.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {discountLabel(
                                            sale.discount_type,
                                            sale.discount_value,
                                            currency,
                                        )}
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {dateRange(
                                            sale.starts_at,
                                            sale.ends_at,
                                        )}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {sale.products_count}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                stateBadge[sale.state].variant
                                            }
                                        >
                                            {stateBadge[sale.state].label}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={sale.edit_url}
                                                    aria-label={`Edit ${sale.name}`}
                                                >
                                                    <Pencil />
                                                </Link>
                                            </Button>
                                            <Form
                                                action={sale.delete_url}
                                                method="delete"
                                                onBefore={() =>
                                                    window.confirm(
                                                        `Delete “${sale.name}”? Its products go back to their normal prices.`,
                                                    )
                                                }
                                            >
                                                <Button
                                                    type="submit"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Delete ${sale.name}`}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </Form>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </AdminPage>
        </>
    );
}
