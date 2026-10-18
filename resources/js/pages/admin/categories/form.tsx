import { Form, Head, Link } from '@inertiajs/react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/Catalog/CategoryController';
import { CategoryImage } from '@/components/admin/category-image';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/admin/categories';
import type { CategoryOption } from '@/types';

type CategoryData = {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    description: string | null;
    position: number;
    is_active: boolean;
    meta_title: string | null;
    meta_description: string | null;
    image_url: string | null;
};

export default function CategoryForm({
    category,
    parents,
}: {
    category: CategoryData | null;
    parents: CategoryOption[];
}) {
    const form = category
        ? CategoryController.update.form(category.id)
        : CategoryController.store.form();

    return (
        <>
            <Head title={category ? `Edit ${category.name}` : 'Add category'} />
            <AdminPage>
                <PageHeader
                    title={category ? category.name : 'Add category'}
                    actions={
                        category && <DeleteCategoryDialog category={category} />
                    }
                />

                <Form
                    {...form}
                    encType="multipart/form-data"
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Category image</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CategoryImage
                                        imageUrl={category?.image_url}
                                        error={errors.image}
                                    />
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Details</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id="name"
                                        label="Name"
                                        error={errors.name}
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={category?.name}
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        id="slug"
                                        label="URL slug"
                                        error={errors.slug}
                                        hint="Generated from the name when empty."
                                    >
                                        <Input
                                            id="slug"
                                            name="slug"
                                            defaultValue={category?.slug}
                                        />
                                    </FormField>
                                    <FormField
                                        id="parent_id"
                                        label="Parent category"
                                        error={errors.parent_id}
                                        hint="Choose a parent to create a subcategory, or leave empty for a top-level category."
                                    >
                                        <NativeSelect
                                            id="parent_id"
                                            name="parent_id"
                                            defaultValue={
                                                category?.parent_id
                                                    ? String(category.parent_id)
                                                    : ''
                                            }
                                        >
                                            <option value="">
                                                None (top level)
                                            </option>
                                            {parents.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {'  '.repeat(p.depth)}
                                                    {p.name}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                    <FormField
                                        id="position"
                                        label="Sort position"
                                        error={errors.position}
                                    >
                                        <Input
                                            id="position"
                                            name="position"
                                            type="number"
                                            min={0}
                                            defaultValue={
                                                category?.position ?? 0
                                            }
                                        />
                                    </FormField>
                                    <div className="sm:col-span-2">
                                        <FormField
                                            id="description"
                                            label="Description"
                                            error={errors.description}
                                        >
                                            <Textarea
                                                id="description"
                                                name="description"
                                                rows={3}
                                                defaultValue={
                                                    category?.description ?? ''
                                                }
                                            />
                                        </FormField>
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="is_active"
                                            value="1"
                                            defaultChecked={
                                                category?.is_active ?? true
                                            }
                                        />
                                        Visible on the storefront
                                    </label>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Search engine listing</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <FormField
                                        id="meta_title"
                                        label="Meta title"
                                        error={errors.meta_title}
                                    >
                                        <Input
                                            id="meta_title"
                                            name="meta_title"
                                            defaultValue={
                                                category?.meta_title ?? ''
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="meta_description"
                                        label="Meta description"
                                        error={errors.meta_description}
                                    >
                                        <Textarea
                                            id="meta_description"
                                            name="meta_description"
                                            rows={2}
                                            defaultValue={
                                                category?.meta_description ?? ''
                                            }
                                        />
                                    </FormField>
                                </CardContent>
                            </Card>
                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {category
                                        ? 'Save category'
                                        : 'Create category'}
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </AdminPage>
        </>
    );
}

function DeleteCategoryDialog({ category }: { category: CategoryData }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive">Delete</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete “{category.name}”?</DialogTitle>
                <DialogDescription>
                    Products in this category are kept; they are only removed
                    from it. Categories with subcategories cannot be deleted.
                </DialogDescription>
                <Form {...CategoryController.destroy.form(category.id)}>
                    {({ processing, errors }) => (
                        <>
                            <InputError
                                message={errors.category}
                                className="mb-4"
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Delete category
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

CategoryForm.layout = {
    breadcrumbs: [{ title: 'Categories', href: index() }],
};
