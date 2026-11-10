import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Grid2X2, List, SlidersHorizontal } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { ElectronicsCard } from '@/components/storefront/electronics-card';
import { home } from '@/routes';
import { category } from '@/routes/storefront';
import { PetLinks } from './components';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { ListingProps } from '@/pages/storefront/listing';

export default function PawzyCatalog({
    title,
    slots,
    description,
    meta,
    breadcrumbs,
    products,
    sort,
    filters = {},
    filterBrands = [],
    filterOptions = [],
}: ListingProps) {
    const { storefront } = usePage().props;
    const { theme } = useStoreTheme();
    const settings = theme?.settings ?? {};
    const showFilters = settings.catalog_show_filters !== false;
    const [mobileFilters, setMobileFilters] = useState(false);
    const [view, setView] = useState<'grid' | 'list'>('grid');
    const [minPrice, setMinPrice] = useState(String(filters.min_price ?? ''));
    const [maxPrice, setMaxPrice] = useState(String(filters.max_price ?? ''));
    const filtersRef = useRef<HTMLElement>(null);
    useEffect(() => {
        const closeFilters = (target: EventTarget | null, escape = false) => {
            if (
                settings.catalog_filter_layout === 'sidebar' ||
                !window.matchMedia('(min-width: 701px)').matches
            )
                return;
            filtersRef.current
                ?.querySelectorAll<HTMLDetailsElement>('details[open]')
                .forEach((details) => {
                    if (escape || !details.contains(target as Node)) {
                        details.open = false;
                        if (
                            escape &&
                            details.contains(document.activeElement)
                        ) {
                            details.querySelector('summary')?.focus();
                        }
                    }
                });
        };
        const onClick = (event: MouseEvent) => closeFilters(event.target);
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') closeFilters(event.target, true);
        };
        document.addEventListener('click', onClick, true);
        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.removeEventListener('click', onClick, true);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [settings.catalog_filter_layout]);
    const update = (
        patch: Record<string, string | number | number[] | undefined>,
    ) => {
        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        router.get(
            url.pathname,
            {
                q: url.searchParams.get('q') ?? undefined,
                lang: url.searchParams.get('lang') ?? undefined,
                ...filters,
                sort,
                ...patch,
            },
            { preserveScroll: true },
        );
    };
    const clearUrl = () => {
        const url = new URL(window.location.href);
        return `${url.pathname}${url.searchParams.has('q') ? `?q=${encodeURIComponent(url.searchParams.get('q') ?? '')}` : ''}`;
    };
    return (
        <>
            <Head title={meta.title}>
                {meta.description && (
                    <meta name="description" content={meta.description} />
                )}
            </Head>
            {settings.catalog_show_banner !== false && (
                <section className="pawzy-collection-banner">
                    {settings.catalog_banner && (
                        <img src={String(settings.catalog_banner)} alt="" />
                    )}
                    <h1>{title}</h1>
                    <p>
                        <Link href={home()}>Home</Link> / {title}
                    </p>
                </section>
            )}
            {settings.catalog_show_pet_links !== false && <PetLinks />}
            <section
                className="electronics-listing"
                data-filters={showFilters}
                data-filter-layout={settings.catalog_filter_layout ?? 'toolbar'}
                data-heading-align={String(
                    settings.catalog_heading_align ?? 'left',
                )}
                style={
                    {
                        '--catalog-columns': Number(
                            settings.catalog_columns ?? 4,
                        ),
                        '--catalog-image-height': `${Number(settings.catalog_image_height ?? 320)}px`,
                    } as CSSProperties
                }
            >
                <div className="electronics-listing-body">
                    {showFilters && (
                        <aside
                            ref={filtersRef}
                            id="pawzy-product-filters"
                            className="electronics-filters"
                            data-open={mobileFilters}
                            aria-label="Product filters"
                        >
                            <div className="electronics-filter-heading">
                                <strong>Filters</strong>
                                <Link href={clearUrl()}>Clear filters</Link>
                            </div>
                            <details>
                                <summary>
                                    Categories <ChevronDown size={14} />
                                </summary>
                                <div className="electronics-filter-body">
                                    {storefront?.categories.map((item) => (
                                        <Link
                                            key={item.id}
                                            href={category(item.slug)}
                                        >
                                            {item.name}
                                        </Link>
                                    ))}
                                </div>
                            </details>
                            <details>
                                <summary>
                                    Price range <ChevronDown size={14} />
                                </summary>
                                <form
                                    className="electronics-filter-body"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        update({
                                            min_price: minPrice || undefined,
                                            max_price: maxPrice || undefined,
                                        });
                                    }}
                                >
                                    <label>
                                        From
                                        <input
                                            type="number"
                                            aria-label="Minimum price"
                                            value={minPrice}
                                            onChange={(event) =>
                                                setMinPrice(event.target.value)
                                            }
                                            min={0}
                                            step="0.01"
                                        />
                                    </label>
                                    <label>
                                        To
                                        <input
                                            type="number"
                                            aria-label="Maximum price"
                                            value={maxPrice}
                                            onChange={(event) =>
                                                setMaxPrice(event.target.value)
                                            }
                                            min={0}
                                            step="0.01"
                                        />
                                    </label>
                                    <button
                                        className="electronics-button"
                                        type="submit"
                                    >
                                        APPLY PRICE
                                    </button>
                                </form>
                            </details>
                            {filterBrands.length > 0 && (
                                <details>
                                    <summary>
                                        Brand <ChevronDown size={14} />
                                    </summary>
                                    <div className="electronics-filter-body">
                                        <label>
                                            <input
                                                type="radio"
                                                name="brand"
                                                checked={!filters.brand_id}
                                                onChange={() =>
                                                    update({
                                                        brand_id: undefined,
                                                    })
                                                }
                                            />
                                            All brands
                                        </label>
                                        {filterBrands.map((brand) => (
                                            <label key={brand.id}>
                                                <input
                                                    type="radio"
                                                    name="brand"
                                                    checked={
                                                        String(
                                                            filters.brand_id,
                                                        ) === String(brand.id)
                                                    }
                                                    onChange={() =>
                                                        update({
                                                            brand_id: brand.id,
                                                        })
                                                    }
                                                />
                                                {brand.name}
                                            </label>
                                        ))}
                                    </div>
                                </details>
                            )}
                            {filterOptions.map((option) => (
                                <details key={option.id}>
                                    <summary>
                                        {option.name}
                                        <ChevronDown size={14} />
                                    </summary>
                                    <div className="electronics-filter-body">
                                        {option.values.map((value) => (
                                            <label key={value.id}>
                                                <input
                                                    type="checkbox"
                                                    checked={(
                                                        filters.options ?? []
                                                    )
                                                        .map(Number)
                                                        .includes(value.id)}
                                                    onChange={(event) =>
                                                        update({
                                                            options: event
                                                                .target.checked
                                                                ? [
                                                                      ...(filters.options ??
                                                                          []),
                                                                      value.id,
                                                                  ]
                                                                : (
                                                                      filters.options ??
                                                                      []
                                                                  ).filter(
                                                                      (id) =>
                                                                          Number(
                                                                              id,
                                                                          ) !==
                                                                          value.id,
                                                                  ),
                                                        })
                                                    }
                                                />
                                                {value.value}
                                            </label>
                                        ))}
                                    </div>
                                </details>
                            ))}
                        </aside>
                    )}
                    <div>
                        <div className="electronics-list-toolbar">
                            {settings.catalog_show_count !== false && (
                                <strong>{products.total} products found</strong>
                            )}
                            <button
                                className="electronics-mobile-filters"
                                aria-expanded={mobileFilters}
                                aria-controls="pawzy-product-filters"
                                onClick={() =>
                                    setMobileFilters((open) => !open)
                                }
                                type="button"
                            >
                                <SlidersHorizontal size={14} />
                                Filters
                            </button>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    aria-label="Grid view"
                                    aria-pressed={view === 'grid'}
                                    onClick={() => setView('grid')}
                                >
                                    <Grid2X2 size={16} />
                                </button>
                                <button
                                    type="button"
                                    aria-label="List view"
                                    aria-pressed={view === 'list'}
                                    onClick={() => setView('list')}
                                >
                                    <List size={16} />
                                </button>
                            </div>
                            <label>
                                Sort by{' '}
                                <select
                                    value={sort}
                                    onChange={(event) =>
                                        update({ sort: event.target.value })
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
                        <PlaceSlot place="catalog.before_products" />
                        {products.data.length ? (
                            <div
                                className="electronics-list-products"
                                data-view={view}
                            >
                                {products.data.map((product) => (
                                    <ElectronicsCard
                                        product={product}
                                        key={product.id}
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="electronics-empty">
                                No products match these filters.{' '}
                                <Link href={clearUrl()}>Clear filters</Link>
                            </p>
                        )}
                        {products.last_page > 1 && (
                            <nav
                                className="electronics-pagination"
                                aria-label="Pagination"
                            >
                                {products.prev_page_url && (
                                    <Link href={products.prev_page_url}>
                                        Previous
                                    </Link>
                                )}
                                <span className="current">
                                    {products.current_page} /{' '}
                                    {products.last_page}
                                </span>
                                {products.next_page_url && (
                                    <Link href={products.next_page_url}>
                                        Next
                                    </Link>
                                )}
                            </nav>
                        )}
                    </div>
                </div>
            </section>
            <ExtensionSlot items={slots} className="pawzy-catalog-extensions" />
            {description && (
                <section className="electronics-description">
                    <h2>About {title}</h2>
                    {description}
                </section>
            )}
        </>
    );
}
