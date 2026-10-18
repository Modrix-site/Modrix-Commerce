import { Head, router } from '@inertiajs/react';
import { LayoutGrid, List, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useStoreSettings } from '@/hooks/use-store-settings';
import type { ListingProps } from '@/pages/storefront/listing';
import { CircuitCard, useCircuit } from './components';
import './style.css';

type Grid = '2' | '3' | '4' | 'list';
const GRIDS: Grid[] = ['2', '3', '4', 'list'];
const GRID_KEY = 'circuit.grid';

/** The shopper's grid choice, kept on this device; the theme's default otherwise. */
function useGrid(fallback: Grid): [Grid, (grid: Grid) => void] {
    const [grid, setGrid] = useState<Grid>(fallback);
    useEffect(() => {
        try {
            const saved = localStorage.getItem(GRID_KEY) as Grid | null;
            if (saved && GRIDS.includes(saved)) setGrid(saved);
        } catch {
            /* Device storage may be unavailable. */
        }
    }, []);
    return [
        grid,
        (next) => {
            setGrid(next);
            try {
                localStorage.setItem(GRID_KEY, next);
            } catch {
                /* Device storage may be unavailable. */
            }
        },
    ];
}

function pages(current: number, last: number): (number | '…')[] {
    const set = new Set(
        [1, last, current - 1, current, current + 1].filter(
            (n) => n >= 1 && n <= last,
        ),
    );
    const sorted = [...set].sort((a, b) => a - b);
    return sorted.flatMap((n, i) =>
        i > 0 && n - sorted[i - 1] > 1 ? ['…' as const, n] : [n],
    );
}

export default function CircuitCatalog({
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
    slots = [],
}: ListingProps) {
    const { text, show, storefront } = useCircuit();
    const { catalog } = useStoreSettings();
    const dialog = useRef<HTMLDialogElement>(null);
    const fallback = (
        GRIDS.includes(text('catalog_grid', '3') as Grid)
            ? text('catalog_grid', '3')
            : '3'
    ) as Grid;
    const [grid, setGrid] = useGrid(fallback);
    const enabled = catalog.filters;
    const currency = storefront?.displayCurrency ?? storefront?.currency ?? '';

    const update = (
        patch: Record<string, string | number | number[] | boolean | undefined>,
    ) => {
        router.get(
            window.location.pathname,
            { ...filters, q: query || undefined, sort, ...patch },
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
    const categories = subcategories.length
        ? subcategories
        : (storefront?.categories ?? []);

    const filterForm = (prefix: string) => (
        <form
            className="ci-filters"
            onSubmit={apply}
            key={JSON.stringify(filters)}
        >
            {categories.length > 0 && (
                <details open>
                    <summary>Products Category</summary>
                    <nav aria-label="Categories">
                        {categories.map((category) => (
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
            <details open>
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
            <details open>
                <summary>Price</summary>
                <div className="ci-price-filter">
                    <label>
                        <span>{currency}</span>
                        <input
                            name="min_price"
                            aria-label="Minimum price"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            defaultValue={filters.min_price}
                        />
                    </label>
                    <span aria-hidden>–</span>
                    <label>
                        <span>{currency}</span>
                        <input
                            name="max_price"
                            aria-label="Maximum price"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Any"
                            defaultValue={filters.max_price}
                        />
                    </label>
                </div>
            </details>
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
            <button className="store-button ci-button" type="submit">
                Apply filters
            </button>
            {active && (
                <button
                    type="button"
                    className="ci-link-button"
                    onClick={clear}
                >
                    Clear all filters
                </button>
            )}
        </form>
    );

    return (
        <div className="ci-frame ci-catalog">
            <Head title={meta.title}>
                {meta.description && (
                    <meta name="description" content={meta.description} />
                )}
            </Head>
            <header className="ci-catalog-head">
                <nav className="ci-crumbs" aria-label="Breadcrumb">
                    <Link href="/">Home</Link>
                    {breadcrumbs.map((item) => (
                        <Link key={item.slug} href={`/c/${item.slug}`}>
                            {item.name}
                        </Link>
                    ))}
                    {!breadcrumbs.length && <span>{title}</span>}
                </nav>
                <h1>{title}</h1>
                {description && <p>{description}</p>}
            </header>
            <PlaceSlot place="catalog.before_products" />
            <div className="ci-catalog-layout" data-filters={enabled}>
                {enabled && (
                    <aside className="ci-sidebar" aria-label="Product filters">
                        {filterForm('desktop')}
                        {show('coupon_show') && text('coupon_heading') && (
                            <Link
                                className="ci-coupon"
                                href={text('coupon_link', '/search')}
                            >
                                {text('coupon_image') && (
                                    <img
                                        src={text('coupon_image')}
                                        alt=""
                                        loading="lazy"
                                    />
                                )}
                                <span>
                                    {text('coupon_eyebrow') && (
                                        <small>{text('coupon_eyebrow')}</small>
                                    )}
                                    <strong>{text('coupon_heading')}</strong>
                                    {text('coupon_button') && (
                                        <em>{text('coupon_button')}</em>
                                    )}
                                </span>
                            </Link>
                        )}
                    </aside>
                )}
                <section aria-label="Products">
                    <div className="ci-toolbar">
                        {enabled && (
                            <button
                                type="button"
                                className="ci-filter-button"
                                aria-haspopup="dialog"
                                onClick={() => dialog.current?.showModal()}
                            >
                                <SlidersHorizontal size={16} /> Filter
                            </button>
                        )}
                        <p role="status">
                            There {products.total === 1 ? 'is' : 'are'}{' '}
                            {products.total}{' '}
                            {products.total === 1 ? 'result' : 'results'} in
                            total
                        </p>
                        <div
                            className="ci-grid-switch"
                            role="group"
                            aria-label="Grid layout"
                        >
                            {GRIDS.map((option) => (
                                <button
                                    key={option}
                                    type="button"
                                    aria-pressed={grid === option}
                                    aria-label={
                                        option === 'list'
                                            ? 'List view'
                                            : `${option} columns`
                                    }
                                    onClick={() => setGrid(option)}
                                >
                                    {option === 'list' ? (
                                        <List size={16} />
                                    ) : (
                                        <LayoutGrid
                                            size={
                                                option === '2'
                                                    ? 13
                                                    : option === '3'
                                                      ? 15
                                                      : 17
                                            }
                                        />
                                    )}
                                </button>
                            ))}
                        </div>
                        <label className="ci-sort">
                            <span>Sort by:</span>
                            <select
                                value={sort}
                                onChange={(e) =>
                                    update({ sort: e.target.value })
                                }
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
                        </label>
                    </div>
                    {products.data.length ? (
                        <div
                            className="ci-grid store-product-grid"
                            data-grid={grid}
                        >
                            {products.data.map((product) => (
                                <CircuitCard
                                    key={product.id}
                                    product={product}
                                    variant={grid === 'list' ? 'list' : 'grid'}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="ci-empty">
                            <h2>No products found</h2>
                            <p>Try another search or change your filters.</p>
                            {active && (
                                <button
                                    className="store-button ci-button"
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
                        <nav className="ci-pagination" aria-label="Pagination">
                            {pages(
                                products.current_page,
                                products.last_page,
                            ).map((page, i) =>
                                page === '…' ? (
                                    <span key={`gap-${i}`} aria-hidden>
                                        …
                                    </span>
                                ) : page === products.current_page ? (
                                    <span key={page} aria-current="page">
                                        {page}
                                    </span>
                                ) : (
                                    <Link
                                        key={page}
                                        preserveScroll
                                        href={(() => {
                                            const url = new URL(
                                                products.next_page_url ??
                                                    products.prev_page_url ??
                                                    window.location.href,
                                                window.location.origin,
                                            );
                                            url.searchParams.set(
                                                'page',
                                                String(page),
                                            );
                                            return url.pathname + url.search;
                                        })()}
                                        aria-label={`Page ${page}`}
                                    >
                                        {page}
                                    </Link>
                                ),
                            )}
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
                    <ExtensionSlot items={slots} className="ci-extensions" />
                </section>
            </div>
            {enabled && (
                <dialog
                    ref={dialog}
                    className="ci-drawer"
                    aria-label="Product filters"
                    onClick={(event) => {
                        if (event.target === dialog.current)
                            dialog.current?.close();
                    }}
                >
                    <div className="ci-drawer-head">
                        <h2>Filter</h2>
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
