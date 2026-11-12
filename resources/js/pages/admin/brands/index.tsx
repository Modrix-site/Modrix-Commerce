import { Form, Head } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import BrandController from '@/actions/App/Http/Controllers/Admin/Catalog/BrandController';
import { EmptyState } from '@/components/admin/empty-state';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { index as productsIndex } from '@/routes/admin/products';
import { index } from '@/routes/admin/brands';

type Brand = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    logo_media_id: number | null;
    logo_url: string | null;
    products_count: number;
    url: string;
};

type Props = {
    brands: Brand[];
    logos: { id: number; filename: string }[];
};

export default function Brands({ brands, logos }: Props) {
    const [editing, setEditing] = useState<Brand | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Brand | null>(null);

    return (
        <>
            <Head title="Brands" />
            <AdminPage>
                <PageHeader
                    title="Brands"
                    description="Brands appear on product pages and have their own page listing their products."
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Add brand
                        </Button>
                    }
                />

                {brands.length === 0 ? (
                    <EmptyState
                        title="No brands yet"
                        description="Add the brands or vendors you sell, then choose one on each product."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Brand</TableHead>
                                <TableHead>Products</TableHead>
                                <TableHead className="text-right">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {brands.map((brand) => (
                                <TableRow key={brand.id}>
                                    <TableCell>
                                        <div className="flex items-center gap-3">
                                            {brand.logo_url ? (
                                                <img
                                                    src={brand.logo_url}
                                                    alt=""
                                                    className="size-9 rounded border bg-white object-contain p-0.5"
                                                />
                                            ) : (
                                                <span className="flex size-9 items-center justify-center rounded border bg-muted text-xs font-semibold">
                                                    {brand.name
                                                        .slice(0, 2)
                                                        .toUpperCase()}
                                                </span>
                                            )}
                                            <div>
                                                <div className="font-medium">
                                                    {brand.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    /b/{brand.slug}
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <a
                                            href={productsIndex.url({
                                                query: { brand: brand.id },
                                            })}
                                            className="tabular-nums hover:underline"
                                        >
                                            {brand.products_count}
                                        </a>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <a
                                                    href={brand.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    aria-label={`View ${brand.name} on the store`}
                                                >
                                                    <ExternalLink />
                                                </a>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setEditing(brand)
                                                }
                                                aria-label={`Edit ${brand.name}`}
                                            >
                                                <Pencil />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setDeleting(brand)
                                                }
                                                aria-label={`Delete ${brand.name}`}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </AdminPage>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        {editing === 'new' ? 'Add brand' : 'Edit brand'}
                    </DialogTitle>
                    {editing !== null && (
                        <Form
                            key={editing === 'new' ? 'new' : editing.id}
                            {...(editing === 'new'
                                ? BrandController.store.form()
                                : BrandController.update.form(editing.id))}
                            onSuccess={() => setEditing(null)}
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => {
                                const brand =
                                    editing === 'new' ? null : editing;

                                return (
                                    <>
                                        <FormField
                                            id="brand-name"
                                            label="Name"
                                            error={errors.name}
                                        >
                                            <Input
                                                id="brand-name"
                                                name="name"
                                                defaultValue={brand?.name}
                                                required
                                                maxLength={120}
                                            />
                                        </FormField>
                                        <FormField
                                            id="brand-slug"
                                            label="URL slug"
                                            error={errors.slug}
                                            hint="Generated from the name when empty."
                                        >
                                            <Input
                                                id="brand-slug"
                                                name="slug"
                                                defaultValue={brand?.slug}
                                                maxLength={140}
                                            />
                                        </FormField>
                                        <FormField
                                            id="brand-description"
                                            label="Description"
                                            error={errors.description}
                                        >
                                            <Textarea
                                                id="brand-description"
                                                name="description"
                                                rows={3}
                                                defaultValue={
                                                    brand?.description ?? ''
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id="brand-logo"
                                            label="Logo"
                                            error={errors.logo_media_id}
                                            hint="An image from the media library."
                                        >
                                            <NativeSelect
                                                id="brand-logo"
                                                name="logo_media_id"
                                                defaultValue={
                                                    brand?.logo_media_id ?? ''
                                                }
                                            >
                                                <option value="">
                                                    No logo
                                                </option>
                                                {logos.map((l) => (
                                                    <option
                                                        key={l.id}
                                                        value={l.id}
                                                    >
                                                        {l.filename}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </FormField>
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                >
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Save brand
                                            </Button>
                                        </DialogFooter>
                                    </>
                                );
                            }}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete “{deleting?.name}”?</DialogTitle>
                    <DialogDescription>
                        Its {deleting?.products_count ?? 0} product(s) are kept
                        and simply no longer have a brand.
                    </DialogDescription>
                    {deleting && (
                        <Form
                            {...BrandController.destroy.form(deleting.id)}
                            onSuccess={() => setDeleting(null)}
                        >
                            {({ processing }) => (
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        Delete brand
                                    </Button>
                                </DialogFooter>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

Brands.layout = {
    breadcrumbs: [{ title: 'Brands', href: index() }],
};
