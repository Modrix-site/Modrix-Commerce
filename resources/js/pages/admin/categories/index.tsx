import { Head, Link } from '@inertiajs/react';
import { ImageIcon, Plus } from 'lucide-react';
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
import { create, edit, index } from '@/routes/admin/categories';
import { edit as translationsEdit } from '@/routes/admin/categories/translations';

type CategoryRow = {
    id: number;
    name: string;
    slug: string;
    depth: number;
    is_active: boolean;
    products_count: number;
    image_url: string | null;
};

export default function CategoriesIndex({
    categories,
}: {
    categories: CategoryRow[];
}) {
    return (
        <>
            <Head title="Categories" />
            <AdminPage>
                <PageHeader
                    title="Categories"
                    description="Organise products into nested categories (up to five levels)."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> Add category
                            </Link>
                        </Button>
                    }
                />

                {categories.length === 0 ? (
                    <EmptyState
                        title="No categories yet"
                        description="Categories help customers browse your catalog."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Slug</TableHead>
                                <TableHead>Products</TableHead>
                                <TableHead>
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categories.map((category) => (
                                <TableRow key={category.id}>
                                    <TableCell>
                                        <span
                                            className="flex items-center gap-2 font-medium"
                                            style={{
                                                paddingLeft: `${category.depth * 1.5}rem`,
                                            }}
                                        >
                                            {category.depth > 0 && (
                                                <span
                                                    aria-hidden
                                                    className="text-muted-foreground"
                                                >
                                                    └
                                                </span>
                                            )}
                                            {category.image_url ? (
                                                <img
                                                    src={category.image_url}
                                                    alt=""
                                                    className="size-10 shrink-0 rounded-md border bg-background object-cover"
                                                />
                                            ) : (
                                                <span className="flex size-10 shrink-0 items-center justify-center rounded-md border bg-muted text-muted-foreground">
                                                    <ImageIcon className="size-4" />
                                                </span>
                                            )}
                                            {category.name}
                                            {!category.is_active && (
                                                <Badge variant="outline">
                                                    Hidden
                                                </Badge>
                                            )}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {category.slug}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {category.products_count}
                                    </TableCell>
                                    <TableCell className="space-x-2 text-right whitespace-nowrap">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={translationsEdit(
                                                    category.id,
                                                )}
                                            >
                                                Translate
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={edit(category.id)}>
                                                Edit
                                            </Link>
                                        </Button>
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

CategoriesIndex.layout = {
    breadcrumbs: [{ title: 'Categories', href: index() }],
};
