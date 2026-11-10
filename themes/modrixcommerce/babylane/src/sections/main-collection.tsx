import { BabyCategoryTree } from '../category-tree';
import type { CategoryNode } from '@/components/storefront/site-header';
import type { StorefrontShared } from '@/types';
import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { Grid2X2, List, SlidersHorizontal } from 'lucide-react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import type { ListingProps } from '@/pages/storefront/listing';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { Card, str, columns } from '../components';
type Props = ThemeSectionProps<Record<string, unknown>, ListingProps>;
export default function Collection(props: Props) {
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
    const {
        props: { storefront },
        url: currentUrl,
    } = usePage();
    const {
        title,
        products,
        query,
        filters,
        sort,
        filterBrands,
        filterOptions,
    } = page;
    const [open, setOpen] = useState(false);
    const [list, setList] = useState(false);
    const [min, setMin] = useState(filters?.min_price ?? '');
    const [max, setMax] = useState(filters?.max_price ?? '');
    const [priceError, setPriceError] = useState('');
    const visit = (updates: Record<string, string | null>) => {
        const u = new URL(window.location.href);
        Object.entries(updates).forEach(([k, v]) =>
            v === null || v === ''
                ? u.searchParams.delete(k)
                : u.searchParams.set(k, v),
        );
        u.searchParams.delete('page');
        router.get(u.pathname + u.search, {}, { preserveScroll: true });
    };
    const options = new Set(filters?.options ?? []);
    const optionChange = (id: number) => {
        const next = new Set(options);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        const u = new URL(window.location.href);
        for (const k of new Set(u.searchParams.keys()))
            if (k.startsWith('options')) u.searchParams.delete(k);
        next.forEach((v) => u.searchParams.append('options[]', String(v)));
        u.searchParams.delete('page');
        router.get(u.pathname + u.search, {}, { preserveScroll: true });
    };
    const clear = () => {
        const u = new URL(window.location.href);
        for (const k of new Set(u.searchParams.keys()))
            if (!['q', 'sort', '_editor', 'currency', 'lang'].includes(k))
                u.searchParams.delete(k);
        router.get(u.pathname + u.search, {}, { preserveScroll: true });
    };
    const active = Boolean(
        filters?.brand_id ||
        filters?.in_stock ||
        filters?.on_sale ||
        filters?.min_price ||
        filters?.max_price ||
        filters?.options?.length,
    );
    const categories = page.subcategories?.length
        ? page.subcategories
        : (storefront?.categories ?? []);
    return (
        <>
            <nav className="bl-breadcrumb" aria-label="Breadcrumb">
                <div className="bl-frame">
                    <Link href="/">Home</Link>
                    <span>/</span>
                    <Link href="/search">Products</Link>
                    {page.breadcrumbs.map((c) => (
                        <span key={c.slug}>
                            {' '}
                            / <Link href={'/c/' + c.slug}>{c.name}</Link>
                        </span>
                    ))}
                    {query && <span> / Search: {query}</span>}
                </div>
            </nav>
            <div className="bl-frame bl-catalog">
                <button
                    type="button"
                    className="bl-btn bl-btn-outline bl-filter-toggle store-button"
                    aria-expanded={open}
                    aria-controls="bl-filter-sidebar"
                    onClick={() => setOpen(!open)}
                >
                    <SlidersHorizontal size={15} />
                    {open ? 'Hide filters' : 'Filter products'}
                </button>
                <aside
                    id="bl-filter-sidebar"
                    aria-label="Product filters"
                    className={open ? 'bl-filters-open' : ''}
                >
                    {s.show_categories !== false && (
                        <div className="bl-filter">
                            <h2>Categories</h2>
                            <Link href="/search">‹ All categories</Link>
                            <BabyCategoryTree
                                nodes={
                                    (
                                        storefront as
                                            | (StorefrontShared & {
                                                  categoryTree?: CategoryNode[];
                                              })
                                            | undefined
                                    )?.categoryTree ??
                                    categories.map((c) => ({
                                        ...c,
                                        url: '/c/' + c.slug,
                                        children: [],
                                    }))
                                }
                                active={page.breadcrumbs.at(-1)?.slug}
                            />
                        </div>
                    )}
                    {s.show_brands !== false &&
                        Boolean(filterBrands?.length) && (
                            <div className="bl-filter">
                                <h2>Brands</h2>
                                {filterBrands?.map((b) => (
                                    <label key={b.id}>
                                        <input
                                            type="checkbox"
                                            checked={
                                                String(filters?.brand_id) ===
                                                String(b.id)
                                            }
                                            onChange={(e) =>
                                                visit({
                                                    brand_id: e.target.checked
                                                        ? String(b.id)
                                                        : null,
                                                })
                                            }
                                        />
                                        {b.name}
                                    </label>
                                ))}
                            </div>
                        )}
                    {s.show_price !== false && (
                        <form
                            className="bl-filter"
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (min && max && Number(min) > Number(max)) {
                                    setPriceError(
                                        'Minimum price must be below maximum price.',
                                    );
                                    return;
                                }
                                setPriceError('');
                                visit({
                                    min_price: min || null,
                                    max_price: max || null,
                                });
                            }}
                        >
                            <h2>Price</h2>
                            <div className="bl-price-inputs">
                                <label>
                                    From
                                    <input
                                        type="number"
                                        min="0"
                                        step=".01"
                                        value={min}
                                        onChange={(e) => setMin(e.target.value)}
                                        aria-label="Minimum price"
                                    />
                                </label>
                                <label>
                                    To
                                    <input
                                        type="number"
                                        min="0"
                                        step=".01"
                                        value={max}
                                        onChange={(e) => setMax(e.target.value)}
                                        aria-label="Maximum price"
                                    />
                                </label>
                            </div>
                            {priceError && <p role="alert">{priceError}</p>}
                            <button className="bl-btn store-button">
                                Apply price
                            </button>
                        </form>
                    )}
                    {s.show_sale !== false && (
                        <div className="bl-filter">
                            <h2>On sale</h2>
                            <label>
                                <input
                                    type="checkbox"
                                    checked={Boolean(filters?.on_sale)}
                                    onChange={(e) =>
                                        visit({
                                            on_sale: e.target.checked
                                                ? '1'
                                                : null,
                                        })
                                    }
                                />
                                Show discounted products
                            </label>
                        </div>
                    )}
                    <div className="bl-filter">
                        <h2>Availability</h2>
                        <label>
                            <input
                                type="checkbox"
                                checked={Boolean(filters?.in_stock)}
                                onChange={(e) =>
                                    visit({
                                        in_stock: e.target.checked ? '1' : null,
                                    })
                                }
                            />
                            In stock only
                        </label>
                    </div>
                    {s.show_options !== false &&
                        filterOptions?.map((o) => (
                            <div className="bl-filter" key={o.id}>
                                <h2>{o.name}</h2>
                                {o.values.map((v) => (
                                    <label key={v.id}>
                                        <input
                                            type="checkbox"
                                            checked={options.has(v.id)}
                                            onChange={() => optionChange(v.id)}
                                        />
                                        {v.value}
                                    </label>
                                ))}
                            </div>
                        ))}
                    {active && (
                        <button
                            className="bl-btn bl-btn-outline store-button"
                            onClick={clear}
                        >
                            Clear all filters
                        </button>
                    )}
                </aside>
                <div>
                    <h1>
                        {query || page.breadcrumbs.length
                            ? title
                            : str(s.heading) || title}
                    </h1>
                    {page.description && (
                        <p className="bl-muted" style={{ marginBottom: 20 }}>
                            {page.description}
                        </p>
                    )}
                    <div className="bl-toolbar">
                        <p>
                            <strong>{products.total}</strong> products found
                        </p>
                        <select
                            aria-label="Sort products"
                            value={sort}
                            onChange={(e) => visit({ sort: e.target.value })}
                        >
                            <option value="newest">Newest</option>
                            <option value="price_asc">
                                Price: low to high
                            </option>
                            <option value="price_desc">
                                Price: high to low
                            </option>
                            <option value="name">Name: A–Z</option>
                        </select>
                        <button
                            className="bl-icon"
                            onClick={() => setList(false)}
                            aria-label="Grid view"
                            aria-pressed={!list}
                        >
                            <Grid2X2 size={17} />
                        </button>
                        <button
                            className="bl-icon"
                            onClick={() => setList(true)}
                            aria-label="List view"
                            aria-pressed={list}
                        >
                            <List size={18} />
                        </button>
                    </div>
                    {active && (
                        <button
                            type="button"
                            className="bl-active-filters"
                            onClick={clear}
                        >
                            Clear active filters ×
                        </button>
                    )}
                    <PlaceSlot place="catalog.before_products" />
                    {products.data.length ? (
                        <div
                            className={
                                'bl-product-grid ' +
                                (list ? 'bl-list-view' : '')
                            }
                            style={columns(s.columns, 5)}
                        >
                            {products.data.map((p) => (
                                <Card
                                    key={p.id}
                                    product={p}
                                    quickAdd={s.quick_add !== false}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="bl-empty">
                            <h2>{str(s.empty_heading)}</h2>
                            <p>{str(s.empty_text)}</p>
                            <button
                                className="bl-btn store-button"
                                onClick={clear}
                            >
                                Clear filters
                            </button>
                        </div>
                    )}
                    {products.last_page > 1 && (
                        <nav
                            className="bl-pagination"
                            aria-label="Product pages"
                        >
                            {products.prev_page_url && (
                                <Link
                                    href={products.prev_page_url}
                                    aria-label="Previous page"
                                >
                                    ←
                                </Link>
                            )}
                            {Array.from(
                                { length: products.last_page },
                                (_, i) => i + 1,
                            )
                                .filter(
                                    (n) =>
                                        n === 1 ||
                                        n === products.last_page ||
                                        Math.abs(n - products.current_page) < 3,
                                )
                                .map((n) =>
                                    n === products.current_page ? (
                                        <span key={n} aria-current="page">
                                            {n}
                                        </span>
                                    ) : (
                                        <Link
                                            key={n}
                                            href={(() => {
                                                const u = new URL(
                                                    currentUrl,
                                                    'http://localhost',
                                                );
                                                u.searchParams.set(
                                                    'page',
                                                    String(n),
                                                );
                                                return u.pathname + u.search;
                                            })()}
                                        >
                                            {n}
                                        </Link>
                                    ),
                                )}
                            {products.next_page_url && (
                                <Link
                                    href={products.next_page_url}
                                    aria-label="Next page"
                                >
                                    →
                                </Link>
                            )}
                        </nav>
                    )}
                    <PlaceSlot place="catalog.after_products" />
                </div>
            </div>
        </>
    );
}
