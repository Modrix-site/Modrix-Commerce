import { Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    CheckCircle2,
    Flame,
    Package,
    Search,
    SlidersHorizontal,
    Sparkles,
    Star,
    Store,
    Tag,
    TrendingUp,
    X,
} from 'lucide-react';
import { Suspense, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { ProductGrid } from '@/components/storefront/product-card';
import {
    ThemeSectionList,
    type ThemeSectionData,
} from '@/components/storefront/theme-sections';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { themeCatalog } from '@/lib/theme-pages';
import { cn } from '@/lib/utils';
import type { ListingProps } from '@/pages/storefront/listing';

export interface MarketplaceDepartmentFacet {
    id: number;
    name: string;
    slug: string;
    count: number;
    image_url?: string | null;
}

export interface MarketplaceVendorFacet {
    id: number;
    store_name: string;
    slug: string;
    is_verified: boolean;
    rating: number;
    products_count: number;
    url: string;
}

export interface MarketplaceProductVendorMeta {
    vendor_id: number;
    store_name: string;
    slug: string;
    is_verified: boolean;
    rating: number;
    store_url: string;
}

export interface MarketplaceCatalogStats {
    total_products: number;
    deals_count: number;
    verified_seller_products_count: number;
    sellers_count: number;
    direct_count: number;
}

export type ExtendedListingFilters = NonNullable<ListingProps['filters']> & {
    category?: string;
    vendor?: string;
    verified_only?: number | boolean | string;
    min_rating?: number | string;
};

export interface MarketplaceCatalogViewProps extends Omit<
    ListingProps,
    'filters'
> {
    filters?: ExtendedListingFilters;
    themeCollectionSections?: ThemeSectionData[];
    departments?: MarketplaceDepartmentFacet[];
    filterVendors?: MarketplaceVendorFacet[];
    productVendors?: Record<number, MarketplaceProductVendorMeta>;
    catalogStats?: MarketplaceCatalogStats;
    activeCategory?: MarketplaceDepartmentFacet | null;
    pageUrl: string;
    hideVendorFilter?: boolean;
    searchPlaceholder?: string;
}

export const AMAZON_SORTS: Record<string, string> = {
    newest: 'Featured & Newest',
    best_selling: 'Best Sellers',
    rating: 'Avg. Seller & Customer Review',
    price_asc: 'Price: Low to High',
    price_desc: 'Price: High to Low',
    name: 'Name: A to Z',
};

export function MarketplaceThemeCatalog({
    title,
    description,
    meta,
    breadcrumbs = [],
    products,
    sort = 'newest',
    query = null,
    filters = {},
    filterBrands = [],
    filterTags = [],
    filterOptions = [],
    slots = [],
    themeCollectionSections = [],
    departments = [],
    filterVendors = [],
    productVendors = {},
    catalogStats,
    activeCategory = null,
    pageUrl,
    hideVendorFilter = false,
    searchPlaceholder = 'Search products, brands, SKUs & verified sellers...',
}: MarketplaceCatalogViewProps) {
    const { theme } = useStoreTheme();
    const [searchText, setSearchText] = useState(query ?? '');

    const CustomCatalog = useMemo(
        () => (theme?.frontend?.catalog ? themeCatalog(theme.name) : undefined),
        [theme?.frontend?.catalog, theme?.name],
    );

    const navigateWith = (
        patch: Record<string, string | number | number[] | boolean | undefined>,
        nextQuery?: string,
    ) => {
        const merged: Record<string, unknown> = {
            ...filters,
            q:
                nextQuery !== undefined
                    ? nextQuery || undefined
                    : query || undefined,
            sort,
            ...patch,
        };

        const cleaned: Record<string, string | number | number[] | boolean> =
            {};
        for (const [key, val] of Object.entries(merged)) {
            if (
                val === undefined ||
                val === null ||
                val === '' ||
                val === false
            ) {
                continue;
            }
            if (Array.isArray(val) && val.length === 0) {
                continue;
            }
            cleaned[key] =
                val === true ? 1 : (val as string | number | number[]);
        }

        router.get(pageUrl, cleaned, {
            preserveScroll: true,
        });
    };

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        navigateWith({}, searchText.trim() || undefined);
    };

    const clearAllFilters = () => {
        setSearchText('');
        router.get(pageUrl, {}, { preserveScroll: true });
    };

    const activeFilterChips: { label: string; clear: () => void }[] = [];
    if (query) {
        activeFilterChips.push({
            label: `Search: "${query}"`,
            clear: () => {
                setSearchText('');
                navigateWith({}, '');
            },
        });
    }
    if (filters.category) {
        const deptName =
            departments.find((d) => d.slug === filters.category)?.name ??
            activeCategory?.name ??
            filters.category;
        activeFilterChips.push({
            label: `Department: ${deptName}`,
            clear: () => navigateWith({ category: undefined }),
        });
    }
    if (!hideVendorFilter && filters.vendor) {
        const vLabel =
            filters.vendor === 'direct'
                ? 'Direct Marketplace Store'
                : filters.vendor === 'marketplace'
                  ? 'Third-Party Sellers'
                  : (filterVendors.find((v) => v.slug === filters.vendor)
                        ?.store_name ?? filters.vendor);
        activeFilterChips.push({
            label: `Seller: ${vLabel}`,
            clear: () => navigateWith({ vendor: undefined }),
        });
    }
    if (filters.verified_only) {
        activeFilterChips.push({
            label: 'KYB Verified Sellers Only',
            clear: () => navigateWith({ verified_only: undefined }),
        });
    }
    if (filters.on_sale) {
        activeFilterChips.push({
            label: "Today's Deals",
            clear: () => navigateWith({ on_sale: undefined }),
        });
    }
    if (filters.in_stock) {
        activeFilterChips.push({
            label: 'In Stock Ready to Ship',
            clear: () => navigateWith({ in_stock: undefined }),
        });
    }
    if (filters.min_rating) {
        activeFilterChips.push({
            label: `${filters.min_rating}★ & Up Sellers`,
            clear: () => navigateWith({ min_rating: undefined }),
        });
    }
    if (filters.brand_id) {
        const bName =
            filterBrands.find((b) => String(b.id) === String(filters.brand_id))
                ?.name ?? `Brand #${filters.brand_id}`;
        activeFilterChips.push({
            label: `Brand: ${bName}`,
            clear: () => navigateWith({ brand_id: undefined }),
        });
    }
    if (filters.min_price || filters.max_price) {
        activeFilterChips.push({
            label: `Price: ${filters.min_price || '0'} – ${filters.max_price || '∞'}`,
            clear: () =>
                navigateWith({ min_price: undefined, max_price: undefined }),
        });
    }

    // Build ListingProps expected by all themes (Babylane, Voltmarket, Atelier, Linden, Circuit, Marketplace, etc.)
    // Pass subcategories: [] inside theme component because interactive staying-in-page Departments render above cleanly.
    const listingProps: ListingProps & {
        sorts: Record<string, string>;
        changeSort: (value: string) => void;
    } = {
        title,
        description,
        meta,
        breadcrumbs,
        subcategories: [],
        products,
        sort,
        query,
        filters,
        filterBrands,
        filterTags,
        filterOptions,
        slots,
        sorts: AMAZON_SORTS,
        changeSort: (value: string) => navigateWith({ sort: value }),
    };

    const usesThemeSections = themeCollectionSections.length > 0;

    return (
        <div className="space-y-5">
            {/* Scoped alignment so theme frames match the parent storefront container edge-to-edge */}
            <style>{`
                .mv-theme-catalog :is(.bl-frame, .vm-frame, .at-frame, .lv-frame, .sb-frame, .rl-frame, .hm-frame, .mk-frame, .nr-frame, .pz-frame, .tp-frame, .vl-frame, .vt-frame, .cc-frame) {
                    width: 100%;
                    max-width: 100%;
                }
            `}</style>

            {/* Amazon-Style Search, Department Strip, Quick Facets & Extended Marketplace Controls */}
            <div className="space-y-3.5 rounded-xl border bg-card p-4 shadow-xs">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <form
                        onSubmit={handleSearch}
                        className="flex max-w-2xl flex-1 items-center gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={searchText}
                                onChange={(e) => setSearchText(e.target.value)}
                                placeholder={searchPlaceholder}
                                className="h-9 bg-background pl-9"
                            />
                        </div>
                        <Button type="submit" size="sm" className="h-9 px-4">
                            Search
                        </Button>
                    </form>

                    <div className="flex flex-wrap items-center gap-2">
                        {!hideVendorFilter && (
                            <select
                                aria-label="Filter by Seller"
                                value={filters.vendor ?? ''}
                                onChange={(e) =>
                                    navigateWith({
                                        vendor: e.target.value || undefined,
                                    })
                                }
                                className="h-9 rounded-md border bg-background px-3 text-xs font-medium focus:ring-2 focus:ring-ring focus:outline-none"
                            >
                                <option value="">
                                    All Sellers (
                                    {catalogStats?.total_products ??
                                        products.total}
                                    )
                                </option>
                                {(catalogStats?.sellers_count ?? 0) > 0 && (
                                    <option value="marketplace">
                                        All Marketplace Vendors Only
                                    </option>
                                )}
                                {(catalogStats?.direct_count ?? 0) > 0 && (
                                    <option value="direct">
                                        Direct Store Only (
                                        {catalogStats?.direct_count})
                                    </option>
                                )}
                                {filterVendors.map((v) => (
                                    <option key={v.id} value={v.slug}>
                                        {v.is_verified ? '✓ ' : ''}
                                        {v.store_name} ({v.products_count})
                                    </option>
                                ))}
                            </select>
                        )}

                        <select
                            aria-label="Sort Marketplace Products"
                            value={sort}
                            onChange={(e) =>
                                navigateWith({ sort: e.target.value })
                            }
                            className="h-9 rounded-md border bg-background px-3 text-xs font-medium focus:ring-2 focus:ring-ring focus:outline-none"
                        >
                            {Object.entries(AMAZON_SORTS).map(
                                ([key, label]) => (
                                    <option key={key} value={key}>
                                        Sort: {label}
                                    </option>
                                ),
                            )}
                        </select>
                    </div>
                </div>

                {/* Department / Category Filter Pills */}
                {departments.length > 0 && (
                    <div className="flex flex-wrap items-center gap-1.5 border-t pt-3">
                        <span className="mr-1 inline-flex items-center gap-1 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                            <Package className="size-3.5" />
                            Departments:
                        </span>
                        <button
                            type="button"
                            onClick={() =>
                                navigateWith({ category: undefined })
                            }
                            className={cn(
                                'rounded-full border px-3 py-1 text-xs font-medium transition',
                                !filters.category
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'bg-background hover:bg-muted',
                            )}
                        >
                            All (
                            {catalogStats?.total_products ?? products.total})
                        </button>
                        {departments.map((dept) => {
                            const active = filters.category === dept.slug;
                            return (
                                <button
                                    key={dept.id}
                                    type="button"
                                    onClick={() =>
                                        navigateWith({
                                            category: active
                                                ? undefined
                                                : dept.slug,
                                        })
                                    }
                                    className={cn(
                                        'rounded-full border px-3 py-1 text-xs font-medium transition',
                                        active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'bg-background hover:bg-muted',
                                    )}
                                >
                                    {dept.name} ({dept.count})
                                </button>
                            );
                        })}
                    </div>
                )}

                {/* Amazon-Style Quick Marketplace Refinement Pills */}
                <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                    <span className="inline-flex items-center gap-1 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                        <SlidersHorizontal className="size-3.5" />
                        Quick Filters:
                    </span>

                    <button
                        type="button"
                        onClick={() =>
                            navigateWith({
                                verified_only: filters.verified_only
                                    ? undefined
                                    : 1,
                            })
                        }
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition',
                            filters.verified_only
                                ? 'border-emerald-600 bg-emerald-600 text-white'
                                : 'bg-background hover:bg-muted',
                        )}
                    >
                        <BadgeCheck className="size-3.5" />
                        Verified Sellers Only
                        {catalogStats && (
                            <span className="opacity-75">
                                ({catalogStats.verified_seller_products_count})
                            </span>
                        )}
                    </button>

                    <button
                        type="button"
                        onClick={() =>
                            navigateWith({
                                on_sale: filters.on_sale ? undefined : 1,
                            })
                        }
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition',
                            filters.on_sale
                                ? 'border-rose-600 bg-rose-600 text-white'
                                : 'bg-background hover:bg-muted',
                        )}
                    >
                        <Flame className="size-3.5" />
                        Today&apos;s Deals
                        {catalogStats && catalogStats.deals_count > 0 && (
                            <span className="opacity-75">
                                ({catalogStats.deals_count})
                            </span>
                        )}
                    </button>

                    <button
                        type="button"
                        onClick={() =>
                            navigateWith({
                                min_rating:
                                    Number(filters.min_rating) === 4
                                        ? undefined
                                        : 4,
                            })
                        }
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition',
                            Number(filters.min_rating) === 4
                                ? 'border-amber-500 bg-amber-500 text-white'
                                : 'bg-background hover:bg-muted',
                        )}
                    >
                        <Star className="size-3.5 fill-current" />
                        4★ &amp; Up Sellers
                    </button>

                    <button
                        type="button"
                        onClick={() =>
                            navigateWith({
                                in_stock: filters.in_stock ? undefined : 1,
                            })
                        }
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition',
                            filters.in_stock
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'bg-background hover:bg-muted',
                        )}
                    >
                        <CheckCircle2 className="size-3.5" />
                        In Stock
                    </button>

                    <button
                        type="button"
                        onClick={() =>
                            navigateWith({
                                sort:
                                    sort === 'best_selling'
                                        ? 'newest'
                                        : 'best_selling',
                            })
                        }
                        className={cn(
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition',
                            sort === 'best_selling'
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'bg-background hover:bg-muted',
                        )}
                    >
                        <TrendingUp className="size-3.5" />
                        Best Sellers
                    </button>

                    <div className="ml-auto hidden items-center gap-1.5 text-[11px] text-muted-foreground sm:flex">
                        <Sparkles className="size-3.5 text-primary" />
                        <span>
                            Theme:{' '}
                            <strong className="text-foreground">
                                {theme?.displayName ?? 'Storefront Default'}
                            </strong>
                        </span>
                    </div>
                </div>

                {/* Active Filter Chips */}
                {activeFilterChips.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 border-t pt-2.5 text-xs">
                        <span className="text-muted-foreground">Active:</span>
                        {activeFilterChips.map((chip) => (
                            <button
                                key={chip.label}
                                type="button"
                                onClick={chip.clear}
                                className="inline-flex items-center gap-1 rounded-md border bg-muted/60 px-2 py-0.5 font-medium hover:bg-muted"
                            >
                                <span>{chip.label}</span>
                                <X className="size-3 text-muted-foreground" />
                            </button>
                        ))}
                        <button
                            type="button"
                            onClick={clearAllFilters}
                            className="ml-1 font-medium text-primary hover:underline"
                        >
                            Clear all filters
                        </button>
                    </div>
                )}
            </div>

            {/* Active Theme Product Catalog Renderer */}
            {usesThemeSections ? (
                <div className="mv-theme-catalog">
                    <ThemeSectionList
                        sections={themeCollectionSections}
                        area="main"
                        page={listingProps}
                    />
                </div>
            ) : CustomCatalog ? (
                <div className="mv-theme-catalog">
                    <Suspense
                        fallback={
                            <ThemeFallbackCatalog
                                {...listingProps}
                                productVendors={productVendors}
                                onUpdate={navigateWith}
                                onClear={clearAllFilters}
                            />
                        }
                    >
                        <CustomCatalog {...listingProps} />
                    </Suspense>
                </div>
            ) : (
                <ThemeFallbackCatalog
                    {...listingProps}
                    productVendors={productVendors}
                    onUpdate={navigateWith}
                    onClear={clearAllFilters}
                />
            )}
        </div>
    );
}

/**
 * Clean faceted catalog view used for core/minimal themes (such as Prism or built-in) that do not override catalog layout.
 * Uses core ProductGrid so cards follow the active theme's card variant automatically.
 */
function ThemeFallbackCatalog({
    products,
    sort,
    filters = {},
    filterBrands = [],
    filterOptions = [],
    productVendors = {},
    onUpdate,
    onClear,
}: ListingProps & {
    productVendors?: Record<number, MarketplaceProductVendorMeta>;
    onUpdate: (
        patch: Record<string, string | number | number[] | boolean | undefined>,
    ) => void;
    onClear: () => void;
}) {
    const [minPrice, setMinPrice] = useState(filters.min_price ?? '');
    const [maxPrice, setMaxPrice] = useState(filters.max_price ?? '');
    const selectedOptions = Array.isArray(filters.options)
        ? filters.options.map(Number)
        : [];

    const toggleOption = (id: number) => {
        const next = selectedOptions.includes(id)
            ? selectedOptions.filter((v) => v !== id)
            : [...selectedOptions, id];
        onUpdate({ options: next });
    };

    const applyPrice = (e: FormEvent) => {
        e.preventDefault();
        onUpdate({
            min_price: minPrice || undefined,
            max_price: maxPrice || undefined,
        });
    };

    return (
        <div className="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
            <aside className="space-y-4">
                <div className="space-y-3 rounded-xl border bg-card p-4">
                    <h2 className="text-sm font-semibold">Price Range</h2>
                    <form onSubmit={applyPrice} className="space-y-2">
                        <div className="grid grid-cols-2 gap-2">
                            <Input
                                type="number"
                                min="0"
                                step="any"
                                placeholder="Min"
                                value={minPrice}
                                onChange={(e) => setMinPrice(e.target.value)}
                                className="h-8 text-xs"
                            />
                            <Input
                                type="number"
                                min="0"
                                step="any"
                                placeholder="Max"
                                value={maxPrice}
                                onChange={(e) => setMaxPrice(e.target.value)}
                                className="h-8 text-xs"
                            />
                        </div>
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            className="h-8 w-full text-xs"
                        >
                            Apply Price
                        </Button>
                    </form>
                </div>

                {filterBrands.length > 0 && (
                    <div className="space-y-2 rounded-xl border bg-card p-4">
                        <h2 className="text-sm font-semibold">Brands</h2>
                        <div className="space-y-1.5 text-xs">
                            {filterBrands.map((b) => {
                                const checked =
                                    String(filters.brand_id) === String(b.id);
                                return (
                                    <label
                                        key={b.id}
                                        className="flex cursor-pointer items-center gap-2"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={() =>
                                                onUpdate({
                                                    brand_id: checked
                                                        ? undefined
                                                        : b.id,
                                                })
                                            }
                                        />
                                        <span>{b.name}</span>
                                    </label>
                                );
                            })}
                        </div>
                    </div>
                )}

                {filterOptions.map((opt) => (
                    <div
                        key={opt.id}
                        className="space-y-2 rounded-xl border bg-card p-4"
                    >
                        <h2 className="text-sm font-semibold">{opt.name}</h2>
                        <div className="space-y-1.5 text-xs">
                            {opt.values.map((v) => (
                                <label
                                    key={v.id}
                                    className="flex cursor-pointer items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        checked={selectedOptions.includes(v.id)}
                                        onChange={() => toggleOption(v.id)}
                                    />
                                    <span>{v.value}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                ))}
            </aside>

            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card px-4 py-2.5 text-xs">
                    <span className="text-muted-foreground">
                        Showing{' '}
                        <strong className="text-foreground">
                            {products.from ?? 0}–{products.to ?? 0}
                        </strong>{' '}
                        of{' '}
                        <strong className="text-foreground">
                            {products.total}
                        </strong>{' '}
                        products
                    </span>
                    <div className="flex items-center gap-2">
                        <Tag className="size-3.5 text-muted-foreground" />
                        <span>Sort: {AMAZON_SORTS[sort] ?? sort}</span>
                    </div>
                </div>

                {products.data.length === 0 ? (
                    <div className="space-y-3 rounded-xl border border-dashed p-12 text-center">
                        <Store className="mx-auto size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            No products match your current marketplace filters.
                        </p>
                        <Button variant="outline" size="sm" onClick={onClear}>
                            Reset Marketplace Filters
                        </Button>
                    </div>
                ) : (
                    <>
                        <ProductGrid products={products.data} />

                        {Object.keys(productVendors).length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    Sellers on this page:
                                </span>
                                {Array.from(
                                    new Map(
                                        Object.values(productVendors).map(
                                            (v) => [v.vendor_id, v],
                                        ),
                                    ).values(),
                                ).map((v) => (
                                    <Link
                                        key={v.vendor_id}
                                        href={v.store_url}
                                        className="inline-flex items-center gap-1 rounded-full border bg-background px-2.5 py-0.5 hover:border-primary"
                                    >
                                        {v.is_verified && (
                                            <BadgeCheck className="size-3 text-emerald-600" />
                                        )}
                                        <span>{v.store_name}</span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </>
                )}

                {products.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-center gap-1.5 pt-2">
                        {products.links.map((link, idx) => {
                            const text = link.label
                                .replace(/&laquo;\s*/gi, '‹ ')
                                .replace(/\s*&raquo;/gi, ' ›')
                                .trim();
                            return link.url ? (
                                <Link
                                    key={idx}
                                    href={link.url}
                                    preserveScroll
                                    className={cn(
                                        'inline-flex min-w-8 items-center justify-center rounded-md border px-2.5 py-1 text-xs font-medium',
                                        link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'bg-card hover:bg-muted',
                                    )}
                                >
                                    {text}
                                </Link>
                            ) : (
                                <span
                                    key={idx}
                                    className="inline-flex min-w-8 items-center justify-center rounded-md border px-2.5 py-1 text-xs text-muted-foreground opacity-50"
                                >
                                    {text}
                                </span>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}
