import { Head, Link, router } from '@inertiajs/react';
import { ProductGrid } from '@/components/storefront/product-card';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { category } from '@/routes/storefront';
import type { Paginated, ProductCard } from '@/types';

type Props = {
    title: string;
    description: string | null;
    meta: { title: string; description: string | null };
    breadcrumbs: { name: string; slug: string }[];
    subcategories: { id: number; name: string; slug: string }[];
    products: Paginated<ProductCard>;
    sort: string;
    query: string | null;
};

const sorts = [
    { value: 'newest', label: 'Newest' },
    { value: 'price_asc', label: 'Price: low to high' },
    { value: 'price_desc', label: 'Price: high to low' },
    { value: 'name', label: 'Name' },
];

export default function Listing({
    title,
    description,
    meta,
    breadcrumbs,
    subcategories,
    products,
    sort,
}: Props) {
    const changeSort = (value: string) => {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', value);
        url.searchParams.delete('page');
        router.visit(url.pathname + url.search, { preserveScroll: true });
    };

    return (
        <>
            <Head title={meta.title}>
                {meta.description && (
                    <meta name="description" content={meta.description} />
                )}
            </Head>

            {breadcrumbs.length > 0 && (
                <nav
                    aria-label="Breadcrumb"
                    className="mb-4 text-sm text-muted-foreground"
                >
                    <ol className="flex flex-wrap gap-1">
                        <li>
                            <Link
                                href={home()}
                                className="hover:text-foreground"
                            >
                                Home
                            </Link>
                        </li>
                        {breadcrumbs.map((b) => (
                            <li
                                key={b.slug}
                                className="before:mr-1 before:content-['/']"
                            >
                                <Link
                                    href={category(b.slug)}
                                    className="hover:text-foreground"
                                >
                                    {b.name}
                                </Link>
                            </li>
                        ))}
                    </ol>
                </nav>
            )}

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="grid gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {description && (
                        <p className="max-w-2xl text-muted-foreground">
                            {description}
                        </p>
                    )}
                    <p className="text-sm text-muted-foreground">
                        {products.total}{' '}
                        {products.total === 1 ? 'product' : 'products'}
                    </p>
                </div>
                <label className="flex items-center gap-2 text-sm">
                    Sort by
                    <select
                        value={sort}
                        onChange={(e) => changeSort(e.target.value)}
                        className="h-9 rounded-md border border-input bg-transparent px-2 dark:bg-input/30"
                    >
                        {sorts.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </select>
                </label>
            </div>

            {subcategories.length > 0 && (
                <ul className="mb-6 flex flex-wrap gap-2">
                    {subcategories.map((c) => (
                        <li key={c.id}>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={category(c.slug)}>{c.name}</Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            {products.data.length > 0 ? (
                <ProductGrid products={products.data} />
            ) : (
                <p className="py-12 text-center text-muted-foreground">
                    No products found.
                </p>
            )}

            {products.last_page > 1 && (
                <nav
                    aria-label="Pagination"
                    className="mt-10 flex items-center justify-center gap-3 text-sm"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        asChild={!!products.prev_page_url}
                        disabled={!products.prev_page_url}
                    >
                        {products.prev_page_url ? (
                            <Link href={products.prev_page_url}>Previous</Link>
                        ) : (
                            <span>Previous</span>
                        )}
                    </Button>
                    <span>
                        Page {products.current_page} of {products.last_page}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        asChild={!!products.next_page_url}
                        disabled={!products.next_page_url}
                    >
                        {products.next_page_url ? (
                            <Link href={products.next_page_url}>Next</Link>
                        ) : (
                            <span>Next</span>
                        )}
                    </Button>
                </nav>
            )}
        </>
    );
}
