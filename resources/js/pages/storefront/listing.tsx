import { Head, Link, router } from '@inertiajs/react';
import { Suspense } from 'react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { ProductGrid } from '@/components/storefront/product-card';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { themeCatalog } from '@/lib/theme-pages';
import {
    ThemeTemplate,
    useThemeSections,
} from '@/components/storefront/theme-sections';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { home } from '@/routes';
import { category } from '@/routes/storefront';
import type { Paginated, ProductCard } from '@/types';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';

export type ListingProps = {
    slots?: SlotItem[];
    title: string;
    description: string | null;
    image?: string | null;
    meta: { title: string; description: string | null };
    breadcrumbs: { name: string; slug: string }[];
    subcategories: {
        id: number;
        name: string;
        slug: string;
        image_url?: string | null;
    }[];
    products: Paginated<ProductCard>;
    sort: string;
    query: string | null;
    filters?: {
        brand_id?: number | string;
        in_stock?: boolean | number | string;
        on_sale?: boolean | number | string;
        min_price?: string;
        max_price?: string;
        options?: number[];
    };
    filterBrands?: { id: number; name: string }[];
    filterOptions?: {
        id: number;
        name: string;
        values: { id: number; value: string }[];
    }[];
};

export default function Listing(props: ListingProps) {
    const {
        title,
        description,
        image,
        meta,
        breadcrumbs,
        subcategories,
        products,
        sort,
    } = props;
    const t = useT();
    const { theme } = useStoreTheme();
    const sectionsTemplate = useThemeSections() !== null;
    const sorts = [
        { value: 'newest', label: t('Newest') },
        { value: 'price_asc', label: t('Price: low to high') },
        { value: 'price_desc', label: t('Price: high to low') },
        { value: 'name', label: t('Name') },
    ];

    const changeSort = (value: string) => {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', value);
        url.searchParams.delete('page');
        router.visit(url.pathname + url.search, { preserveScroll: true });
    };

    const CustomCatalog = theme?.frontend?.catalog
        ? themeCatalog(theme.name)
        : undefined;
    if (sectionsTemplate)
        return (
            <>
                <Head title={meta.title}>
                    {meta.description && (
                        <meta name="description" content={meta.description} />
                    )}
                </Head>
                <ThemeTemplate page={{ ...props, sorts, changeSort }} />
            </>
        );
    if (CustomCatalog)
        return (
            <Suspense fallback={<p role="status">Loading catalog…</p>}>
                <CustomCatalog {...props} />
            </Suspense>
        );
    return (
        <>
            <Head title={meta.title}>
                {meta.description && (
                    <meta name="description" content={meta.description} />
                )}
            </Head>

            {breadcrumbs.length > 0 && (
                <nav
                    aria-label={t('Breadcrumb')}
                    className="mb-4 text-sm text-muted-foreground"
                >
                    <ol className="flex flex-wrap gap-1">
                        <li>
                            <Link
                                href={home()}
                                className="hover:text-foreground"
                            >
                                {t('Home')}
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
                    {image && (
                        <img
                            src={image}
                            alt=""
                            className="mb-2 h-14 w-auto self-start object-contain"
                        />
                    )}
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {description && (
                        <p className="max-w-2xl text-muted-foreground">
                            {description}
                        </p>
                    )}
                    <p className="text-sm text-muted-foreground">
                        {products.total === 1
                            ? t('1 product')
                            : t(':count products', { count: products.total })}
                    </p>
                </div>
                <label className="flex items-center gap-2 text-sm">
                    {t('Sort by')}
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
                            <Button
                                variant="outline"
                                className={
                                    c.image_url ? 'h-auto py-2' : undefined
                                }
                                size="sm"
                                asChild
                            >
                                <Link href={category(c.slug)}>
                                    {c.image_url && (
                                        <img
                                            src={c.image_url}
                                            alt=""
                                            className="size-12 rounded object-cover"
                                        />
                                    )}
                                    {c.name}
                                </Link>
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            <PlaceSlot place="catalog.before_products" className="mb-6" />

            {products.data.length > 0 ? (
                <ProductGrid products={products.data} />
            ) : (
                <p className="py-12 text-center text-muted-foreground">
                    {t('No products found.')}
                </p>
            )}

            {products.last_page > 1 && (
                <nav
                    aria-label={t('Pagination')}
                    className="mt-10 flex items-center justify-center gap-3 text-sm"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        asChild={!!products.prev_page_url}
                        disabled={!products.prev_page_url}
                    >
                        {products.prev_page_url ? (
                            <Link href={products.prev_page_url}>
                                {t('Previous')}
                            </Link>
                        ) : (
                            <span>{t('Previous')}</span>
                        )}
                    </Button>
                    <span>
                        {t('Page :page of :pages', {
                            page: products.current_page,
                            pages: products.last_page,
                        })}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        asChild={!!products.next_page_url}
                        disabled={!products.next_page_url}
                    >
                        {products.next_page_url ? (
                            <Link href={products.next_page_url}>
                                {t('Next')}
                            </Link>
                        ) : (
                            <span>{t('Next')}</span>
                        )}
                    </Button>
                </nav>
            )}
            <ExtensionSlot items={props.slots} className="mt-10 grid gap-8" />
        </>
    );
}
