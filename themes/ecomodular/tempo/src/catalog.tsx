import { Head, router } from '@inertiajs/react';
import { SlidersHorizontal, X } from 'lucide-react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useStoreSettings } from '@/hooks/use-store-settings';
import type { ListingProps } from '@/pages/storefront/listing';
import { TempoCard, useTempo } from './components';
import './style.css';

export default function TempoCatalog({
    title,
    description,
    image,
    meta,
    breadcrumbs,
    subcategories,
    products,
    sort,
    query,
    filters = {},
    filterBrands = [],
    filterOptions = [],
    slots = [],
}: ListingProps) {
    const { text, show, storefront } = useTempo();
    const { catalog } = useStoreSettings();
    const dialog = useRef<HTMLDialogElement>(null);
    const enabled = catalog.filters && show('catalog_filters');
    const update = (
        patch: Record<string, string | number | number[] | boolean | undefined>,
    ) => {
        const url = new URL(window.location.href);
        router.get(
            url.pathname,
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
    };
    const clear = () =>
        update({
            min_price: undefined,
            max_price: undefined,
            brand_id: undefined,
            in_stock: undefined,
            options: undefined,
        });
    const apply = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const value = (key: string) => {
            const raw = data.get(key);
            return typeof raw === 'string' && raw !== '' ? raw : undefined;
        };
        update({
            min_price: value('min_price'),
            max_price: value('max_price'),
            brand_id: value('brand_id'),
            in_stock: data.has('in_stock') ? 1 : undefined,
            options: data.getAll('options').map(Number),
        });
        dialog.current?.close();
    };
    const active = Object.values(filters).some((value) =>
        Array.isArray(value)
            ? value.length > 0
            : value !== undefined &&
              value !== '' &&
              value !== false &&
              value !== '0' &&
              value !== 0,
    );
    const filterForm = (prefix: string) => (
        <form
            className="tp-filters"
            onSubmit={apply}
            key={JSON.stringify(filters)}
        >
            <h2>Filter by</h2>
            {(subcategories.length > 0 ||
                (storefront?.categories.length ?? 0) > 0) && (
                <details open={subcategories.length > 0}>
                    <summary>Category</summary>
                    <nav aria-label="Filter categories">
                        {(subcategories.length
                            ? subcategories
                            : (storefront?.categories ?? [])
                        ).map((category) => (
                            <Link
                                key={category.id}
                                href={`/c/${category.slug}`}
                            >
                                {category.name}
                            </Link>
                        ))}
                    </nav>
                </details>
            )}
            {filterBrands.length > 0 && (
                <details open={Boolean(filters.brand_id)}>
                    <summary>Brand</summary>
                    <select
                        name="brand_id"
                        aria-label="Filter by brand"
                        defaultValue={filters.brand_id ?? ''}
                    >
                        <option value="">All brands</option>
                        {filterBrands.map((brand) => (
                            <option key={brand.id} value={brand.id}>
                                {brand.name}
                            </option>
                        ))}
                    </select>
                </details>
            )}
            {filterOptions.map((option) => (
                <details
                    key={option.id}
                    open={option.values.some((value) =>
                        filters.options?.includes(value.id),
                    )}
                >
                    <summary>{option.name}</summary>
                    {option.values.map((value) => (
                        <label key={value.id}>
                            <input
                                type="checkbox"
                                name="options"
                                value={value.id}
                                defaultChecked={filters.options?.includes(
                                    value.id,
                                )}
                            />
                            {value.value}
                        </label>
                    ))}
                </details>
            ))}
            <details open={Boolean(filters.min_price || filters.max_price)}>
                <summary>
                    Price ({storefront?.displayCurrency ?? storefront?.currency}
                    )
                </summary>
                <div className="tp-price-filter">
                    <input
                        name="min_price"
                        aria-label="Minimum price"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="From"
                        defaultValue={filters.min_price}
                    />
                    <input
                        name="max_price"
                        aria-label="Maximum price"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="To"
                        defaultValue={filters.max_price}
                    />
                </div>
            </details>
            <details open={Boolean(Number(filters.in_stock ?? 0))}>
                <summary>Availability</summary>
                <label htmlFor={`${prefix}-stock`}>
                    <input
                        id={`${prefix}-stock`}
                        type="checkbox"
                        name="in_stock"
                        defaultChecked={Boolean(Number(filters.in_stock ?? 0))}
                    />
                    In stock
                </label>
            </details>
            <button
                className="store-button tp-button tp-button-primary"
                type="submit"
            >
                Apply filters
            </button>
            {active && (
                <button type="button" className="tp-clear" onClick={clear}>
                    Clear all filters
                </button>
            )}
        </form>
    );
    return (
        <div className="tp-frame tp-catalog">
            <Head title={meta.title}>
                {meta.description && (
                    <meta name="description" content={meta.description} />
                )}
            </Head>
            <nav className="tp-crumbs" aria-label="Breadcrumb">
                <Link href="/">Home</Link>
                {breadcrumbs.map((item) => (
                    <Link key={item.slug} href={`/c/${item.slug}`}>
                        {item.name}
                    </Link>
                ))}
            </nav>
            <div className="tp-catalog-intro">
                <h1>
                    {query
                        ? title
                        : title === 'All products'
                          ? text('catalog_heading', title)
                          : title}
                </h1>
                <p>{description || (!query && text('catalog_text'))}</p>
                {image && <img src={image} alt="" />}
            </div>
            <PlaceSlot place="catalog.before_products" />
            <div className="tp-catalog-layout" data-filters={enabled}>
                {enabled && (
                    <aside
                        className="tp-desktop-filters"
                        aria-label="Product filters"
                    >
                        {filterForm('desktop')}
                    </aside>
                )}
                <section aria-label="Products">
                    <div className="tp-catalog-toolbar">
                        {enabled && (
                            <button
                                type="button"
                                className="tp-mobile-filter"
                                aria-haspopup="dialog"
                                onClick={() => dialog.current?.showModal()}
                            >
                                <SlidersHorizontal size={16} />
                                Filters
                            </button>
                        )}
                        <p role="status">
                            {products.total}{' '}
                            {products.total === 1 ? 'product' : 'products'}
                        </p>
                        <select
                            value={sort}
                            aria-label="Sort products"
                            onChange={(e) => update({ sort: e.target.value })}
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
                    {products.data.length ? (
                        <div className="tp-product-grid store-product-grid">
                            {products.data.map((product) => (
                                <TempoCard key={product.id} product={product} />
                            ))}
                        </div>
                    ) : (
                        <div className="tp-empty">
                            <h2>No products found</h2>
                            <p>Try another search or change your filters.</p>
                            {active && (
                                <button
                                    className="store-button tp-button"
                                    type="button"
                                    onClick={clear}
                                >
                                    Clear filters
                                </button>
                            )}
                            <Link href="/search">Browse all products</Link>
                        </div>
                    )}
                    {products.last_page > 1 && (
                        <nav className="tp-pagination" aria-label="Pagination">
                            {products.prev_page_url && (
                                <Link
                                    href={products.prev_page_url}
                                    preserveScroll
                                    aria-label="Previous page"
                                >
                                    ‹
                                </Link>
                            )}
                            <span aria-current="page">
                                {products.current_page} / {products.last_page}
                            </span>
                            {products.next_page_url && (
                                <Link
                                    href={products.next_page_url}
                                    preserveScroll
                                    aria-label="Next page"
                                >
                                    ›
                                </Link>
                            )}
                        </nav>
                    )}
                    <ExtensionSlot items={slots} className="tp-extensions" />
                </section>
            </div>
            {enabled && (
                <dialog
                    ref={dialog}
                    className="tp-filter-dialog"
                    aria-label="Product filters"
                >
                    <div className="tp-menu-title">
                        <strong>Filters</strong>
                        <button
                            type="button"
                            aria-label="Close filters"
                            onClick={() => dialog.current?.close()}
                        >
                            <X size={20} />
                        </button>
                    </div>
                    {filterForm('mobile')}
                </dialog>
            )}
        </div>
    );
}
