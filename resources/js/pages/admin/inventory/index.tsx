import { Form, Head, Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight, ImageOff } from 'lucide-react';
import { Fragment, useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { NativeSelect } from '@/components/admin/form-field';
import { AdjustStockDialog } from '@/components/admin/inventory/adjust-stock-dialog';
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
import { cn } from '@/lib/utils';
import { index, movements } from '@/routes/admin/inventory';
import type { Option, Paginated } from '@/types';

type VariantStock = {
    id: number;
    sku: string;
    name: string | null;
    available: number;
    committed: number;
    threshold: number;
    state: 'ok' | 'low' | 'out';
    allow_backorder: boolean;
    match: boolean;
    url: string;
};

type ProductStock = {
    id: number;
    name: string;
    image_url: string | null;
    available: number;
    committed: number;
    low: number;
    out: number;
    untracked: number;
    variants: VariantStock[];
};

type Props = {
    products: Paginated<ProductStock>;
    filters: { search: string; stock: string };
    reasons: Option[];
    canManage: boolean;
};

export default function InventoryIndex({
    products,
    filters,
    reasons,
    canManage,
}: Props) {
    const filtered = filters.search !== '' || filters.stock !== '';
    // Products found through one of their variants open to show it.
    const [open, setOpen] = useState<Set<number>>(
        () =>
            new Set(
                products.data
                    .filter(
                        (p) =>
                            p.variants.length > 1 &&
                            p.variants.some((v) => v.match),
                    )
                    .map((p) => p.id),
            ),
    );
    const toggle = (id: number) =>
        setOpen((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });

    return (
        <>
            <Head title="Inventory" />
            <AdminPage>
                <PageHeader
                    title="Inventory"
                    description="Stock per product and variant. Available is what can be sold; committed is sold but not shipped yet. Every change is recorded in the stock history."
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
                            placeholder="Product or variant SKU"
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

                {products.data.length === 0 ? (
                    <EmptyState
                        title={
                            filtered
                                ? 'Nothing matches these filters'
                                : 'No tracked products yet'
                        }
                        description="Products whose variants track inventory appear here."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Product</TableHead>
                                <TableHead>SKU</TableHead>
                                <TableHead className="text-right">
                                    Committed
                                </TableHead>
                                <TableHead className="text-right">
                                    Available
                                </TableHead>
                                {canManage && (
                                    <TableHead>
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                )}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.data.map((product) => {
                                const single =
                                    product.variants.length === 1
                                        ? product.variants[0]
                                        : null;
                                const expanded = open.has(product.id);

                                return (
                                    <Fragment key={product.id}>
                                        <TableRow
                                            className={cn(
                                                !single && 'cursor-pointer',
                                            )}
                                            onClick={
                                                single
                                                    ? undefined
                                                    : () => toggle(product.id)
                                            }
                                        >
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    {single ? (
                                                        <span className="w-4" />
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            aria-expanded={
                                                                expanded
                                                            }
                                                            aria-label={`${expanded ? 'Hide' : 'Show'} variants of ${product.name}`}
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                toggle(
                                                                    product.id,
                                                                );
                                                            }}
                                                            className="rounded p-0.5 hover:bg-muted"
                                                        >
                                                            {expanded ? (
                                                                <ChevronDown className="size-4" />
                                                            ) : (
                                                                <ChevronRight className="size-4" />
                                                            )}
                                                        </button>
                                                    )}
                                                    <Thumb
                                                        url={product.image_url}
                                                    />
                                                    <div className="grid">
                                                        {single ? (
                                                            <Link
                                                                href={
                                                                    single.url
                                                                }
                                                                className="font-medium hover:underline"
                                                            >
                                                                {product.name}
                                                            </Link>
                                                        ) : (
                                                            <span className="font-medium">
                                                                {product.name}
                                                            </span>
                                                        )}
                                                        <span className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                                            {!single &&
                                                                `${product.variants.length} variants`}
                                                            {product.untracked >
                                                                0 &&
                                                                ` · ${product.untracked} not tracked`}
                                                            <StockBadges
                                                                low={
                                                                    product.low
                                                                }
                                                                out={
                                                                    product.out
                                                                }
                                                                single={
                                                                    single !==
                                                                    null
                                                                }
                                                            />
                                                        </span>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {single?.sku ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {product.committed}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {product.available}
                                            </TableCell>
                                            {canManage && (
                                                <TableCell
                                                    className="text-right"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    {single && (
                                                        <Adjust
                                                            variant={single}
                                                            productName={
                                                                product.name
                                                            }
                                                            reasons={reasons}
                                                        />
                                                    )}
                                                </TableCell>
                                            )}
                                        </TableRow>
                                        {expanded &&
                                            product.variants.map((variant) => (
                                                <TableRow
                                                    key={variant.id}
                                                    className={cn(
                                                        'bg-muted/30',
                                                        variant.match &&
                                                            filtered &&
                                                            'bg-primary/5',
                                                    )}
                                                >
                                                    <TableCell className="pl-16">
                                                        <Link
                                                            href={variant.url}
                                                            className="hover:underline"
                                                        >
                                                            {variant.name ??
                                                                'Default'}
                                                        </Link>
                                                        <span className="ml-2 inline-flex gap-1.5">
                                                            <StockBadges
                                                                low={
                                                                    variant.state ===
                                                                    'low'
                                                                        ? 1
                                                                        : 0
                                                                }
                                                                out={
                                                                    variant.state ===
                                                                    'out'
                                                                        ? 1
                                                                        : 0
                                                                }
                                                                single
                                                            />
                                                            {variant.allow_backorder && (
                                                                <Badge variant="secondary">
                                                                    Backorders
                                                                    allowed
                                                                </Badge>
                                                            )}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground">
                                                        {variant.sku}
                                                    </TableCell>
                                                    <TableCell className="text-right text-muted-foreground tabular-nums">
                                                        {variant.committed}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {variant.available}
                                                    </TableCell>
                                                    {canManage && (
                                                        <TableCell className="text-right">
                                                            <Adjust
                                                                variant={
                                                                    variant
                                                                }
                                                                productName={
                                                                    product.name
                                                                }
                                                                reasons={
                                                                    reasons
                                                                }
                                                            />
                                                        </TableCell>
                                                    )}
                                                </TableRow>
                                            ))}
                                    </Fragment>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}

                <Pagination
                    from={products.from}
                    to={products.to}
                    total={products.total}
                    prevUrl={products.prev_page_url}
                    nextUrl={products.next_page_url}
                />
            </AdminPage>
        </>
    );
}

function Adjust({
    variant,
    productName,
    reasons,
}: {
    variant: VariantStock;
    productName: string;
    reasons: Option[];
}) {
    return (
        <AdjustStockDialog
            row={{
                id: variant.id,
                name: variant.name
                    ? `${productName} — ${variant.name}`
                    : productName,
                sku: variant.sku,
                on_hand: variant.available,
            }}
            reasons={reasons}
        />
    );
}

function StockBadges({
    low,
    out,
    single,
}: {
    low: number;
    out: number;
    single: boolean;
}) {
    return (
        <>
            {out > 0 && (
                <Badge variant="destructive">
                    {single ? 'Out of stock' : `${out} out of stock`}
                </Badge>
            )}
            {low > 0 && (
                <Badge variant="outline">{single ? 'Low' : `${low} low`}</Badge>
            )}
        </>
    );
}

function Thumb({ url }: { url: string | null }) {
    return url ? (
        <img
            src={url}
            alt=""
            className="size-9 shrink-0 rounded-md border object-cover"
            loading="lazy"
        />
    ) : (
        <span className="flex size-9 shrink-0 items-center justify-center rounded-md border bg-muted text-muted-foreground">
            <ImageOff className="size-4" />
        </span>
    );
}

InventoryIndex.layout = {
    breadcrumbs: [{ title: 'Inventory', href: index() }],
};
