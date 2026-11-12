import { Form, Head, Link } from '@inertiajs/react';
import { EmptyState } from '@/components/admin/empty-state';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
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
import { index, movements as movementsRoute } from '@/routes/admin/inventory';
import type { Option, Paginated } from '@/types';

type Movement = {
    id: number;
    sku: string;
    name: string;
    change: number;
    after: number;
    reason: string;
    actor: string | null;
    note: string | null;
    reference: string | null;
    created_at: string;
};

const dateFormat = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export default function InventoryMovements({
    movements,
    filters,
    reasons,
}: {
    movements: Paginated<Movement>;
    filters: { sku: string; reason: string };
    reasons: Option[];
}) {
    return (
        <>
            <Head title="Stock history" />
            <AdminPage>
                <PageHeader
                    title="Stock history"
                    description="Append-only ledger of every stock change."
                />

                <Form
                    {...movementsRoute.form()}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    <div className="grid gap-2 sm:w-56">
                        <Label htmlFor="sku">SKU</Label>
                        <Input id="sku" name="sku" defaultValue={filters.sku} />
                    </div>
                    <div className="grid gap-2 sm:w-56">
                        <Label htmlFor="reason">Reason</Label>
                        <NativeSelect
                            id="reason"
                            name="reason"
                            defaultValue={filters.reason}
                        >
                            <option value="">Any reason</option>
                            {reasons.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {r.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" variant="secondary">
                            Filter
                        </Button>
                        {(filters.sku || filters.reason) && (
                            <Button variant="ghost" asChild>
                                <Link href={movementsRoute()}>Clear</Link>
                            </Button>
                        )}
                    </div>
                </Form>

                {movements.data.length === 0 ? (
                    <EmptyState title="No stock movements" />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>When</TableHead>
                                <TableHead>Variant</TableHead>
                                <TableHead>Change</TableHead>
                                <TableHead>After</TableHead>
                                <TableHead>Reason</TableHead>
                                <TableHead>By</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {movements.data.map((m) => (
                                <TableRow key={m.id}>
                                    <TableCell className="whitespace-nowrap text-muted-foreground">
                                        {dateFormat.format(
                                            new Date(m.created_at),
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-medium">
                                            {m.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {m.sku}
                                        </div>
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {m.change > 0
                                            ? `+${m.change}`
                                            : m.change}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {m.after}
                                    </TableCell>
                                    <TableCell>
                                        {m.reason}
                                        {(m.note || m.reference) && (
                                            <div className="text-xs text-muted-foreground">
                                                {[m.reference, m.note]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {m.actor ?? 'System'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <Pagination
                    from={movements.from}
                    to={movements.to}
                    total={movements.total}
                    prevUrl={movements.prev_page_url}
                    nextUrl={movements.next_page_url}
                />
            </AdminPage>
        </>
    );
}

InventoryMovements.layout = {
    breadcrumbs: [
        { title: 'Inventory', href: index() },
        { title: 'Stock history', href: movementsRoute() },
    ],
};
