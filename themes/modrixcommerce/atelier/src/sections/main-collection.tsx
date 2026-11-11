import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { SlidersHorizontal, X, ArrowRight } from 'lucide-react';
import '../style.css';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import type { ListingProps } from '@/pages/storefront/listing';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { Card, str, assetUrl } from '../components';

type Props = ThemeSectionProps<Record<string, unknown>, ListingProps>;

export default function MainCollection(props: Props) {
    return props.page?.products ? (
        <Catalog
            key={JSON.stringify([
                props.page.query,
                props.page.sort,
                props.page.filters,
            ])}
            {...props}
        />
    ) : null;
}

function Catalog({ section, page }: Props) {
    const s = section.settings;
    const { storefront } = usePage().props;
    const { title, products, query, filters, sort, filterBrands } = page;

    const productList = Array.isArray(products)
        ? products
        : (products?.data ?? []);

    const totalCount = Array.isArray(products)
        ? products.length
        : typeof products?.total === 'number'
          ? products.total
          : productList.length;

    const paginated = !Array.isArray(products) ? products : null;

    const [filtersOpen, setFiltersOpen] = useState(false);
    const [minPrice, setMinPrice] = useState(filters?.min_price ?? '');
    const [maxPrice, setMaxPrice] = useState(filters?.max_price ?? '');

    const visit = (updates: Record<string, string | null>) => {
        const u = new URL(window.location.href);
        Object.entries(updates).forEach(([k, v]) => {
            if (v === null || v === '') {
                u.searchParams.delete(k);
            } else {
                u.searchParams.set(k, v);
            }
        });
        u.searchParams.delete('page');
        router.get(u.pathname + u.search, {}, { preserveScroll: true });
    };

    const clearFilters = () => {
        const u = new URL(window.location.href);
        for (const k of new Set(u.searchParams.keys())) {
            if (!['q', 'sort', '_editor', 'currency', 'lang'].includes(k)) {
                u.searchParams.delete(k);
            }
        }
        router.get(u.pathname + u.search, {}, { preserveScroll: true });
    };

    const activeFilterCount =
        (filters?.brand_id ? 1 : 0) +
        (filters?.in_stock ? 1 : 0) +
        (filters?.on_sale ? 1 : 0) +
        (filters?.min_price || filters?.max_price ? 1 : 0);

    const cols = Number(s.columns) || 4;
    const bannerHeight = Number(s.banner_height) || 280;
    const bannerImg = str(s.banner_image);

    const categories = page.subcategories?.length
        ? page.subcategories
        : (storefront?.categories ?? []);

    return (
        <div className="at-catalog-page pb-20">
            {/* Top Banner */}
            {s.show_banner !== false && bannerImg && (
                <div
                    className="at-catalog-banner"
                    style={{ height: `${bannerHeight}px` }}
                >
                    <img
                        src={assetUrl(bannerImg)}
                        alt={title || str(s.heading)}
                        fetchPriority="high"
                    />
                </div>
            )}

            <div className="at-frame">
                {/* Breadcrumbs */}
                <nav className="at-breadcrumb" aria-label="Breadcrumb">
                    <Link href="/">Home</Link>
                    <span>/</span>
                    <Link href="/search">Products</Link>
                    {page.breadcrumbs.map((c) => (
                        <span key={c.slug} className="flex items-center gap-2">
                            <span>/</span>
                            <Link href={'/c/' + c.slug}>{c.name}</Link>
                        </span>
                    ))}
                    {query && <span> / Search: {query}</span>}
                </nav>

                {/* Catalog Title & Intro */}
                <div className="mb-8">
                    <h1 className="mb-2 text-3xl font-normal tracking-wide uppercase">
                        {query
                            ? `Search: "${query}"`
                            : title || str(s.heading, 'Explore all')}
                    </h1>
                    {(page.description || str(s.description)) && (
                        <p className="max-w-2xl text-sm leading-relaxed text-neutral-500">
                            {page.description || str(s.description)}
                        </p>
                    )}
                </div>

                {/* Toolbar */}
                <div className="at-catalog-toolbar">
                    <div className="flex items-center gap-3">
                        {s.show_filters !== false && (
                            <button
                                type="button"
                                className="inline-flex items-center gap-2 text-xs font-medium tracking-wider text-neutral-800 uppercase hover:text-black"
                                onClick={() => setFiltersOpen(true)}
                            >
                                <SlidersHorizontal size={14} />
                                <span>Filter</span>
                                {activeFilterCount > 0 && (
                                    <span className="flex h-4 w-4 items-center justify-center rounded-full bg-neutral-900 text-[10px] text-white">
                                        {activeFilterCount}
                                    </span>
                                )}
                            </button>
                        )}
                        {activeFilterCount > 0 && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="ml-2 text-xs text-neutral-400 underline hover:text-neutral-800"
                            >
                                Clear all
                            </button>
                        )}
                    </div>

                    <span className="at-catalog-results-count">
                        {totalCount} {totalCount === 1 ? 'Result' : 'Results'}
                    </span>

                    {s.show_sort !== false && (
                        <div className="flex items-center gap-2 text-xs">
                            <label
                                htmlFor="at-sort-select"
                                className="tracking-wider text-neutral-500 uppercase"
                            >
                                Sort by:
                            </label>
                            <select
                                id="at-sort-select"
                                value={sort || 'newest'}
                                onChange={(e) =>
                                    visit({ sort: e.target.value })
                                }
                                className="cursor-pointer border-none bg-transparent text-xs font-medium focus:outline-none"
                            >
                                <option value="newest">Newest</option>
                                <option value="price_asc">
                                    Price: low to high
                                </option>
                                <option value="price_desc">
                                    Price: high to low
                                </option>
                                <option value="name">Name</option>
                            </select>
                        </div>
                    )}
                </div>

                <PlaceSlot place="catalog.before_products" />

                {/* Product Grid */}
                {productList.length > 0 ? (
                    <div
                        className="at-product-grid"
                        style={{ '--at-cols': cols } as React.CSSProperties}
                    >
                        {productList.map((p) => (
                            <Card key={p.id} product={p} quickAdd />
                        ))}
                    </div>
                ) : (
                    <div className="my-8 border border-neutral-200 p-8 py-20 text-center">
                        <p className="mb-4 text-neutral-600">
                            No products match your current filters.
                        </p>
                        <button
                            type="button"
                            className="at-btn at-btn-primary bg-black text-white hover:bg-neutral-800"
                            onClick={clearFilters}
                        >
                            Clear filters
                        </button>
                    </div>
                )}

                <PlaceSlot place="catalog.after_products" />

                {/* Pagination */}
                {paginated && paginated.last_page > 1 && (
                    <nav
                        aria-label="Pagination"
                        className="mt-12 flex items-center justify-center gap-3 text-xs tracking-wider uppercase"
                    >
                        {paginated.prev_page_url ? (
                            <Link
                                href={paginated.prev_page_url}
                                className="at-btn at-btn-secondary px-4 py-2"
                                preserveScroll
                            >
                                Previous
                            </Link>
                        ) : (
                            <span className="at-btn at-btn-disabled cursor-not-allowed px-4 py-2 opacity-40">
                                Previous
                            </span>
                        )}
                        <span className="px-3 text-neutral-500">
                            Page {paginated.current_page} of{' '}
                            {paginated.last_page}
                        </span>
                        {paginated.next_page_url ? (
                            <Link
                                href={paginated.next_page_url}
                                className="at-btn at-btn-secondary px-4 py-2"
                                preserveScroll
                            >
                                Next
                            </Link>
                        ) : (
                            <span className="at-btn at-btn-disabled cursor-not-allowed px-4 py-2 opacity-40">
                                Next
                            </span>
                        )}
                    </nav>
                )}

                {/* Viewed count note */}
                {totalCount > 0 && (
                    <div className="mt-12 border-t border-neutral-200 pt-8 text-center">
                        <p className="text-xs tracking-wider text-neutral-400 uppercase">
                            Showing {productList.length} of {totalCount}{' '}
                            {totalCount === 1 ? 'product' : 'products'}
                        </p>
                    </div>
                )}
            </div>

            {/* Filter Drawer */}
            {filtersOpen && (
                <div className="fixed inset-0 z-50 flex justify-end">
                    <div
                        className="fixed inset-0 bg-black/40 backdrop-blur-sm"
                        onClick={() => setFiltersOpen(false)}
                    />
                    <div className="relative z-10 flex h-full w-full max-w-sm flex-col bg-white shadow-2xl">
                        <div className="flex items-center justify-between border-b border-neutral-200 p-6">
                            <h2 className="text-sm font-medium tracking-wider uppercase">
                                Filters{' '}
                                {activeFilterCount > 0 &&
                                    `(${activeFilterCount})`}
                            </h2>
                            <button
                                type="button"
                                className="at-icon-btn"
                                onClick={() => setFiltersOpen(false)}
                            >
                                <X size={20} />
                            </button>
                        </div>

                        <div className="flex-1 space-y-8 overflow-y-auto p-6">
                            {/* Categories */}
                            {s.show_categories !== false &&
                                categories.length > 0 && (
                                    <div>
                                        <h3 className="mb-3 text-xs font-semibold tracking-wider uppercase">
                                            Categories
                                        </h3>
                                        <div className="space-y-2">
                                            <Link
                                                href="/search"
                                                className="block py-1 text-sm text-neutral-600 hover:text-black"
                                                onClick={() =>
                                                    setFiltersOpen(false)
                                                }
                                            >
                                                All categories
                                            </Link>
                                            {categories.map((cat) => (
                                                <Link
                                                    key={cat.id}
                                                    href={'/c/' + cat.slug}
                                                    className="block py-1 text-sm text-neutral-600 hover:text-black"
                                                    onClick={() =>
                                                        setFiltersOpen(false)
                                                    }
                                                >
                                                    {cat.name}
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                )}

                            {/* Brands */}
                            {s.show_brands !== false &&
                                Boolean(filterBrands?.length) && (
                                    <div>
                                        <h3 className="mb-3 text-xs font-semibold tracking-wider uppercase">
                                            Brands
                                        </h3>
                                        <div className="space-y-2">
                                            {filterBrands?.map((b) => (
                                                <label
                                                    key={b.id}
                                                    className="flex cursor-pointer items-center gap-2 text-sm text-neutral-700"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            String(
                                                                filters?.brand_id,
                                                            ) === String(b.id)
                                                        }
                                                        onChange={(e) =>
                                                            visit({
                                                                brand_id: e
                                                                    .target
                                                                    .checked
                                                                    ? String(
                                                                          b.id,
                                                                      )
                                                                    : null,
                                                            })
                                                        }
                                                        className="rounded-none border-neutral-300"
                                                    />
                                                    <span>{b.name}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                )}

                            {/* Price range */}
                            {s.show_price !== false && (
                                <div>
                                    <h3 className="mb-3 text-xs font-semibold tracking-wider uppercase">
                                        Price
                                    </h3>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="number"
                                            placeholder="Min"
                                            value={minPrice}
                                            onChange={(e) =>
                                                setMinPrice(e.target.value)
                                            }
                                            className="w-full border border-neutral-300 p-2 text-xs"
                                        />
                                        <span className="text-neutral-400">
                                            –
                                        </span>
                                        <input
                                            type="number"
                                            placeholder="Max"
                                            value={maxPrice}
                                            onChange={(e) =>
                                                setMaxPrice(e.target.value)
                                            }
                                            className="w-full border border-neutral-300 p-2 text-xs"
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                visit({
                                                    min_price: minPrice || null,
                                                    max_price: maxPrice || null,
                                                })
                                            }
                                            className="at-btn at-btn-primary h-[34px] bg-black px-3 text-white hover:bg-neutral-800"
                                        >
                                            <ArrowRight size={14} />
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* Stock availability */}
                            {s.show_stock !== false && (
                                <div>
                                    <label className="flex cursor-pointer items-center gap-2 text-sm text-neutral-700">
                                        <input
                                            type="checkbox"
                                            checked={Boolean(filters?.in_stock)}
                                            onChange={(e) =>
                                                visit({
                                                    in_stock: e.target.checked
                                                        ? '1'
                                                        : null,
                                                })
                                            }
                                            className="rounded-none border-neutral-300"
                                        />
                                        <span>In stock only</span>
                                    </label>
                                </div>
                            )}
                        </div>

                        <div className="border-t border-neutral-200 p-6">
                            <button
                                type="button"
                                className="at-btn at-btn-primary w-full bg-black text-white hover:bg-neutral-800"
                                onClick={() => setFiltersOpen(false)}
                            >
                                Apply filters
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
