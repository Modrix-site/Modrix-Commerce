import { Form, Head, Link } from '@inertiajs/react';
import { ImageOff, Plus } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatusBadge } from '@/components/admin/status-badge';
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
import { useCan } from '@/hooks/use-can';
import { create, edit, index } from '@/routes/admin/products';
import type { CategoryOption, MoneyValue, Option, Paginated } from '@/types';

type ProductRow = {
    id: number;
    name: string;
    status: string;
    type: string;
    variants_count: number;
    sku: string | null;
    price_from: MoneyValue | null;
    stock: number | null;
    image: string | null;
};

type Props = {
    products: Paginated<ProductRow>;
    filters: { search: string; status: string; category: string; sort: string };
    categories: CategoryOption[];
    statuses: Option[];
};

export default function ProductsIndex({
    products,
    filters,
    categories,
    statuses,
}: Props) {
    const can = useCan();
    const filtered =
        filters.search !== '' ||
        filters.status !== '' ||
        filters.category !== '';

    return (
        <>
            <Head title="Products" />
            <AdminPage>
                <PageHeader
                    title="Products"
                    description="Manage your catalog. Prices are shown in the store currency."
                    actions={
                        can('products.create') && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> Add product
                                </Link>
                            </Button>
                        )
                    }
                />

                <Form
                    {...index.form()}
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end"
                >
                    <div className="grid gap-2 lg:col-span-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            type="search"
                            defaultValue={filters.search}
                            placeholder="Name or SKU"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <NativeSelect
                            id="status"
                            name="status"
                            defaultValue={filters.status}
                        >
                            <option value="">Any status</option>
                            {statuses.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="category">Category</Label>
                        <NativeSelect
                            id="category"
                            name="category"
                            defaultValue={filters.category}
                        >
                            <option value="">Any category</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {'  '.repeat(c.depth)}
                                    {c.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="sort">Sort by</Label>
                        <NativeSelect
                            id="sort"
                            name="sort"
                            defaultValue={filters.sort}
                        >
                            <option value="-updated">Recently updated</option>
                            <option value="-created">Newest</option>
                            <option value="name">Name A–Z</option>
                            <option value="-name">Name Z–A</option>
                        </NativeSelect>
                    </div>
                    <div className="flex gap-2 lg:col-span-5">
                        <Button type="submit" variant="secondary">
                            Apply
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
                                ? 'No products match these filters'
                                : 'No products yet'
                        }
                        description={
                            filtered
                                ? 'Try a different search or clear the filters.'
                                : 'Create your first product to start selling.'
                        }
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-14">
                                    <span className="sr-only">Image</span>
                                </TableHead>
                                <TableHead>Product</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Price</TableHead>
                                <TableHead>Stock</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.data.map((product) => (
                                <TableRow key={product.id}>
                                    <TableCell>
                                        {product.image ? (
                                            <img
                                                src={product.image}
                                                alt=""
                                                className="size-10 rounded border object-cover"
                                            />
                                        ) : (
                                            <div className="flex size-10 items-center justify-center rounded border text-muted-foreground">
                                                <ImageOff className="size-4" />
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {can('products.update') ? (
                                            <Link
                                                href={edit(product.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {product.name}
                                            </Link>
                                        ) : (
                                            <span className="font-medium">
                                                {product.name}
                                            </span>
                                        )}
                                        <div className="text-xs text-muted-foreground">
                                            {product.variants_count > 1
                                                ? `${product.variants_count} variants`
                                                : product.sku}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={product.status} />
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {product.price_from
                                            ? `${product.variants_count > 1 ? 'from ' : ''}${product.price_from.formatted}`
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {product.stock === null
                                            ? 'Not tracked'
                                            : product.stock}
                                    </TableCell>
                                </TableRow>
                            ))}
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

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: index() }],
};
