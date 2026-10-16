import { Form, Head, Link, router } from '@inertiajs/react';
import { Copy, Download, ImageOff, Star, Plus, Upload } from 'lucide-react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Admin/Catalog/ProductController';
import ProductCsvController from '@/actions/App/Http/Controllers/Admin/Catalog/ProductCsvController';
import { EmptyState } from '@/components/admin/empty-state';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { ProductBulkBar } from '@/components/admin/products/product-bulk-bar';
import { Pagination } from '@/components/admin/pagination';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { StatusBadge } from '@/components/admin/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useCan } from '@/hooks/use-can';
import { create, edit, index } from '@/routes/admin/products';
import type { CategoryOption, MoneyValue, Option, Paginated } from '@/types';

type ProductRow = {
    id: number;
    name: string;
    status: string;
    type: string;
    variants_count: number;
    brand: string | null;
    is_featured: boolean;
    sku: string | null;
    price_from: MoneyValue | null;
    stock: number | null;
    image: string | null;
};

type Props = {
    products: Paginated<ProductRow>;
    filters: {
        search: string;
        status: string;
        category: string;
        brand: string;
        tag: string;
        featured: string;
        sort: string;
    };
    categories: CategoryOption[];
    brands: { id: number; name: string }[];
    statuses: Option[];
    importErrors: string[];
};

export default function ProductsIndex({
    products,
    filters,
    categories,
    brands,
    statuses,
    importErrors,
}: Props) {
    const can = useCan();
    // Products selected for a bulk action (on this page).
    const [selected, setSelected] = useState<number[]>([]);
    const pageIds = products.data.map((p) => p.id);
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.includes(id));
    const toggle = (id: number, on: boolean) =>
        setSelected((s) =>
            on ? (s.includes(id) ? s : [...s, id]) : s.filter((x) => x !== id),
        );
    const filtered =
        filters.search !== '' ||
        filters.status !== '' ||
        filters.category !== '' ||
        filters.brand !== '' ||
        filters.tag !== '' ||
        filters.featured !== '';

    return (
        <>
            <Head title="Products" />
            <AdminPage>
                <PageHeader
                    title="Products"
                    description="Manage your catalog. Prices are shown in the store currency."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                {/* Plain anchor: a file download, not an Inertia visit */}
                                <a href={ProductCsvController.export.url()}>
                                    <Download /> Export CSV
                                </a>
                            </Button>
                            {can('products.create') &&
                                can('products.update') && <ImportDialog />}
                            {can('products.create') && (
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus /> Add product
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />

                {importErrors.length > 0 && (
                    <Alert variant="destructive">
                        <AlertTitle>
                            Import failed. No changes were made.
                        </AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-4">
                                {importErrors.map((error, i) => (
                                    <li key={i}>{error}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

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
                        <Label htmlFor="brand">Brand</Label>
                        <NativeSelect
                            id="brand"
                            name="brand"
                            defaultValue={filters.brand}
                        >
                            <option value="">Any brand</option>
                            {brands.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tag">Tag</Label>
                        <Input
                            id="tag"
                            name="tag"
                            defaultValue={filters.tag}
                            placeholder="Exact tag"
                        />
                    </div>
                    <label className="flex items-center gap-2 self-end pb-2 text-sm">
                        <input type="hidden" name="featured" value="" />
                        <Checkbox
                            name="featured"
                            value="1"
                            defaultChecked={filters.featured === '1'}
                        />
                        Featured only
                    </label>
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
                                <TableHead className="w-8">
                                    <Checkbox
                                        aria-label="Select all products on this page"
                                        checked={allSelected}
                                        onCheckedChange={(c) =>
                                            setSelected(
                                                c === true ? pageIds : [],
                                            )
                                        }
                                    />
                                </TableHead>
                                <TableHead className="w-14">
                                    <span className="sr-only">Image</span>
                                </TableHead>
                                <TableHead>Product</TableHead>
                                <TableHead>Brand</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Price</TableHead>
                                <TableHead>Stock</TableHead>
                                <TableHead className="w-10">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.data.map((product) => (
                                <TableRow key={product.id}>
                                    <TableCell>
                                        <Checkbox
                                            aria-label={`Select ${product.name}`}
                                            checked={selected.includes(
                                                product.id,
                                            )}
                                            onCheckedChange={(c) =>
                                                toggle(product.id, c === true)
                                            }
                                        />
                                    </TableCell>
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
                                    <TableCell className="text-muted-foreground">
                                        {product.brand ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            <StatusBadge
                                                status={product.status}
                                            />
                                            {product.is_featured && (
                                                <Badge variant="outline">
                                                    <Star className="size-3" />
                                                    Featured
                                                </Badge>
                                            )}
                                        </div>
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
                                    <TableCell>
                                        {can('products.create') && (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="size-8"
                                                title="Duplicate"
                                                aria-label={`Duplicate ${product.name}`}
                                                onClick={() =>
                                                    router.post(
                                                        ProductController.duplicate.url(
                                                            product.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Copy className="size-4" />
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <ProductBulkBar
                    selected={selected}
                    onClear={() => setSelected([])}
                    categories={categories}
                    brands={brands}
                    canUpdate={can('products.update')}
                    canDelete={can('products.delete')}
                />

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

function ImportDialog() {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Upload /> Import CSV
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Import products</DialogTitle>
                <DialogDescription>
                    Use the same columns as the export. Rows are matched by SKU:
                    existing SKUs are updated and new SKUs create simple
                    products. If any row has an error, nothing is imported.
                </DialogDescription>
                <Form
                    {...ProductCsvController.import.form()}
                    resetOnSuccess
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="csv-file">CSV file</Label>
                                <Input
                                    id="csv-file"
                                    type="file"
                                    name="file"
                                    accept=".csv,text/csv"
                                    required
                                />
                                <InputError message={errors.file} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Import
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: index() }],
};
