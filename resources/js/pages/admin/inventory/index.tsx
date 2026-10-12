import { Form, Head, Link } from '@inertiajs/react';
import InventoryController from '@/actions/App/Http/Controllers/Admin/InventoryController';
import { EmptyState } from '@/components/admin/empty-state';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
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
import { index, movements } from '@/routes/admin/inventory';
import type { Option, Paginated } from '@/types';

type StockRow = {
    id: number;
    sku: string;
    product_id: number;
    name: string;
    on_hand: number;
    threshold: number;
    allow_backorder: boolean;
};

type Props = {
    variants: Paginated<StockRow>;
    filters: { search: string; stock: string };
    reasons: Option[];
    canManage: boolean;
};

export default function InventoryIndex({
    variants,
    filters,
    reasons,
    canManage,
}: Props) {
    const filtered = filters.search !== '' || filters.stock !== '';

    return (
        <>
            <Head title="Inventory" />
            <AdminPage>
                <PageHeader
                    title="Inventory"
                    description="Stock levels for tracked variants. Every change is recorded in the stock history."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={movements()}>Stock history</Link>
                        </Button>
                    }
                />

                <Form
                    {...index.form()}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    <div className="grid gap-2 sm:w-72">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            type="search"
                            defaultValue={filters.search}
                            placeholder="Product or SKU"
                        />
                    </div>
                    <div className="grid gap-2 sm:w-44">
                        <Label htmlFor="stock">Stock level</Label>
                        <NativeSelect
                            id="stock"
                            name="stock"
                            defaultValue={filters.stock}
                        >
                            <option value="">All</option>
                            <option value="low">Low stock</option>
                            <option value="out">Out of stock</option>
                        </NativeSelect>
                    </div>
                    <div className="flex gap-2">
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

                {variants.data.length === 0 ? (
                    <EmptyState
                        title={
                            filtered
                                ? 'Nothing matches these filters'
                                : 'No tracked variants yet'
                        }
                        description="Variants with inventory tracking appear here."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>SKU</TableHead>
                                <TableHead>On hand</TableHead>
                                {canManage && (
                                    <TableHead>
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {variants.data.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell className="font-medium">
                                        {row.name}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {row.sku}
                                    </TableCell>
                                    <TableCell>
                                        <span className="flex items-center gap-2 tabular-nums">
                                            {row.on_hand}
                                            {row.on_hand <= 0 ? (
                                                <Badge variant="destructive">
                                                    Out of stock
                                                </Badge>
                                            ) : row.on_hand <= row.threshold ? (
                                                <Badge variant="outline">
                                                    Low
                                                </Badge>
                                            ) : null}
                                            {row.allow_backorder && (
                                                <Badge variant="secondary">
                                                    Backorders allowed
                                                </Badge>
                                            )}
                                        </span>
                                    </TableCell>
                                    {canManage && (
                                        <TableCell className="text-right">
                                            <AdjustDialog
                                                row={row}
                                                reasons={reasons}
                                            />
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <Pagination
                    from={variants.from}
                    to={variants.to}
                    total={variants.total}
                    prevUrl={variants.prev_page_url}
                    nextUrl={variants.next_page_url}
                />
            </AdminPage>
        </>
    );
}

function AdjustDialog({ row, reasons }: { row: StockRow; reasons: Option[] }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Adjust
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Adjust stock</DialogTitle>
                <DialogDescription>
                    {row.name} ({row.sku}): currently {row.on_hand} on hand.
                </DialogDescription>
                <Form
                    {...InventoryController.adjust.form(row.id)}
                    options={{ preserveScroll: true }}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id={`mode-${row.id}`}
                                    label="Action"
                                    error={errors.mode}
                                >
                                    <NativeSelect
                                        id={`mode-${row.id}`}
                                        name="mode"
                                        defaultValue="add"
                                    >
                                        <option value="add">
                                            Add to stock
                                        </option>
                                        <option value="remove">
                                            Remove from stock
                                        </option>
                                        <option value="set">
                                            Set stock count to
                                        </option>
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    id={`qty-${row.id}`}
                                    label="Quantity"
                                    error={errors.quantity}
                                >
                                    <Input
                                        id={`qty-${row.id}`}
                                        name="quantity"
                                        type="number"
                                        min={0}
                                        required
                                    />
                                </FormField>
                            </div>
                            <FormField
                                id={`reason-${row.id}`}
                                label="Reason"
                                error={errors.reason}
                            >
                                <NativeSelect
                                    id={`reason-${row.id}`}
                                    name="reason"
                                    defaultValue={reasons[0]?.value}
                                >
                                    {reasons.map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {r.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id={`note-${row.id}`}
                                label="Note"
                                error={errors.note}
                            >
                                <Input
                                    id={`note-${row.id}`}
                                    name="note"
                                    placeholder="Optional"
                                />
                            </FormField>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Save adjustment
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

InventoryIndex.layout = {
    breadcrumbs: [{ title: 'Inventory', href: index() }],
};
