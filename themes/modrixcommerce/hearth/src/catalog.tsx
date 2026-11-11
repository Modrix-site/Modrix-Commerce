import { Head, router } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { useState } from 'react';
import { SlidersHorizontal } from 'lucide-react';
import type { CSSProperties } from 'react';
import type { ListingProps } from '@/pages/storefront/listing';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { HearthCard, useHearth } from './components';
import './style.css';
export default function HearthCatalog({
    title,
    description,
    meta,
    breadcrumbs,
    subcategories,
    products,
    sort,
    query,
    filters = {},
    filterBrands = [],
    filterOptions = [],
}: ListingProps) {
    const catalogSettings = useStoreSettings().catalog;
    const { storefront, text, show } = useHearth();
    const [open, setOpen] = useState(false);
    const [min, setMin] = useState(String(filters.min_price ?? ''));
    const [max, setMax] = useState(String(filters.max_price ?? ''));
    const update = (
        patch: Record<string, string | number | boolean | number[] | undefined>,
    ) =>
        router.get(
            window.location.pathname,
            {
                ...filters,
                q: query || undefined,
                sort,
                lang: storefront?.locale,
                currency: storefront?.displayCurrency,
                ...patch,
            },
            { preserveScroll: true },
        );
    const toggle = (id: number) =>
        update({
            options: (filters.options ?? []).includes(id)
                ? filters.options?.filter((value) => value !== id)
                : [...(filters.options ?? []), id],
        });
    return (
        <div
            className="hearth-catalog-page hearth-wrap"
            style={
                {
                    '--hearth-columns': Number(text('catalog_columns', '3')),
                    '--hearth-image-height': `${text('catalog_image_height', '280')}px`,
                } as CSSProperties
            }
        >
            <Head title={meta.title}>
                {meta.description && (
                    <meta name="description" content={meta.description} />
                )}
            </Head>
            <nav className="hearth-breadcrumb" aria-label="Breadcrumb">
                <Link href="/">Home</Link>
                <span>/</span>
                <Link href="/search">Catalogue</Link>
                {breadcrumbs.map((item) => (
                    <span key={item.slug}>
                        {' '}
                        / <Link href={`/c/${item.slug}`}>{item.name}</Link>
                    </span>
                ))}
            </nav>
            <h1 className="hearth-catalog-title">
                {query
                    ? `Search: ${query}`
                    : title === 'All products'
                      ? text('catalog_heading', 'Catalogue')
                      : title}
            </h1>
            {description && (
                <p className="hearth-catalog-description">{description}</p>
            )}
            {text('catalog_banner') && (
                <img
                    className="hearth-catalog-banner"
                    src={text('catalog_banner')}
                    alt=""
                />
            )}
            <nav className="hearth-subcategories" aria-label="Categories">
                {(subcategories.length
                    ? subcategories
                    : (storefront?.categories ?? [])
                ).map((item) => (
                    <Link href={`/c/${item.slug}`} key={item.id}>
                        {item.name}
                    </Link>
                ))}
            </nav>
            <div className="hearth-catalog-toolbar">
                {catalogSettings.filters && show('catalog_filters') && (
                    <button
                        className="hearth-primary"
                        aria-expanded={open}
                        aria-controls="hearth-filter-panel"
                        onClick={() => setOpen(!open)}
                    >
                        <SlidersHorizontal size={14} /> All filters
                    </button>
                )}
                {show('catalog_stock_filter') && (
                    <label>
                        <input
                            type="checkbox"
                            checked={Boolean(Number(filters.in_stock ?? 0))}
                            onChange={(e) =>
                                update({
                                    in_stock: e.target.checked ? 1 : undefined,
                                })
                            }
                        />{' '}
                        In stock
                    </label>
                )}
                <span className="hearth-results">
                    {products.total} products
                </span>
                <select
                    aria-label="Sort products"
                    value={sort}
                    onChange={(e) => update({ sort: e.target.value })}
                >
                    <option value="newest">By default</option>
                    <option value="price_asc">Price ascending</option>
                    <option value="price_desc">Price descending</option>
                    <option value="name">Name</option>
                </select>
            </div>
            {open && catalogSettings.filters && show('catalog_filters') && (
                <aside
                    id="hearth-filter-panel"
                    className="hearth-filter-panel"
                    aria-label="Product filters"
                >
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            update({
                                min_price: min || undefined,
                                max_price: max || undefined,
                            });
                        }}
                    >
                        <h3>Price range ({storefront?.displayCurrency})</h3>
                        <input
                            aria-label="Minimum price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={min}
                            onChange={(e) => setMin(e.target.value)}
                            placeholder="From"
                        />
                        <input
                            aria-label="Maximum price"
                            type="number"
                            min="0"
                            step="0.01"
                            value={max}
                            onChange={(e) => setMax(e.target.value)}
                            placeholder="To"
                        />
                        <button className="hearth-primary">Apply</button>
                    </form>
                    {filterBrands.length > 0 && (
                        <label>
                            Brand
                            <select
                                aria-label="Filter by brand"
                                value={filters.brand_id ?? ''}
                                onChange={(e) =>
                                    update({
                                        brand_id: e.target.value || undefined,
                                    })
                                }
                            >
                                <option value="">All brands</option>
                                {filterBrands.map((brand) => (
                                    <option key={brand.id} value={brand.id}>
                                        {brand.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    {filterOptions.map((option) => (
                        <fieldset key={option.id}>
                            <legend>{option.name}</legend>
                            {option.values.map((value) => (
                                <label key={value.id}>
                                    <input
                                        type="checkbox"
                                        checked={(
                                            filters.options ?? []
                                        ).includes(value.id)}
                                        onChange={() => toggle(value.id)}
                                    />
                                    {value.value}
                                </label>
                            ))}
                        </fieldset>
                    ))}
                    <button
                        onClick={() => {
                            setMin('');
                            setMax('');
                            update({
                                min_price: undefined,
                                max_price: undefined,
                                brand_id: undefined,
                                options: undefined,
                                in_stock: undefined,
                            });
                        }}
                    >
                        Clear filters
                    </button>
                </aside>
            )}
            {products.data.length ? (
                <div className="hearth-catalog-grid store-product-grid">
                    <PlaceSlot place="catalog.before_products" />
                    {products.data.map((product) => (
                        <HearthCard key={product.id} product={product} />
                    ))}
                </div>
            ) : (
                <div className="hearth-empty">
                    <h2>No products found</h2>
                    <p>Try another category or clear your filters.</p>
                </div>
            )}
            {products.last_page > 1 && (
                <nav className="hearth-pagination" aria-label="Pagination">
                    {products.prev_page_url && (
                        <Link href={products.prev_page_url}>Previous</Link>
                    )}
                    <span>
                        {products.current_page} / {products.last_page}
                    </span>
                    {products.next_page_url && (
                        <Link href={products.next_page_url}>Next</Link>
                    )}
                </nav>
            )}
        </div>
    );
}
