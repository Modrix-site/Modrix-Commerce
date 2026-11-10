import { Head, router } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { SlidersHorizontal, X } from 'lucide-react';
import { useRef } from 'react';
import type { FormEvent } from 'react';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import type { ListingProps } from '@/pages/storefront/listing';
import { NordCard, NordRail, useNord } from './components';
import './style.css';

export default function NordCatalog({
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
    const catalogSettings = useStoreSettings().catalog;
    const { text, show } = useNord();
    const dialog = useRef<HTMLDialogElement>(null);
    const heading =
        title === 'All products' && !query
            ? text('catalog_heading', title)
            : title;
    const update = (
        patch: Record<string, string | number | boolean | number[] | undefined>,
    ) => {
        const current = new URL(window.location.href);
        const locale = current.searchParams.get('lang');
        const currency = current.searchParams.get('currency');
        router.get(
            current.pathname,
            {
                ...filters,
                sort,
                q: query || undefined,
                lang: locale || undefined,
                currency: currency || undefined,
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
            const entry = data.get(key);
            return typeof entry === 'string' && entry !== ''
                ? entry
                : undefined;
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
              value !== '0',
    );
    return (
        <div className="nd-frame nd-catalog">
            <Head title={meta.title}>
                {meta.description && (
                    <meta name="description" content={meta.description} />
                )}
            </Head>
            <nav className="nd-crumbs" aria-label="Breadcrumb">
                <Link href="/">Home</Link>
                {breadcrumbs.map((item) => (
                    <Link key={item.slug} href={`/c/${item.slug}`}>
                        {item.name}
                    </Link>
                ))}
                {!breadcrumbs.length && <span>{heading}</span>}
            </nav>
            {!query && show('catalog_campaigns') && (
                <>
                    <section className="nd-catalog-banner">
                        <div>
                            <h2>{text('catalog_banner_heading')}</h2>
                            <p>{text('catalog_banner_text')}</p>
                        </div>
                        <Link className="nd-button" href="/search?sort=newest">
                            {text('catalog_banner_button')}
                        </Link>
                    </section>
                    <section className="nd-campaign nd-catalog-editorial">
                        {text('catalog_editorial_image') && (
                            <img
                                src={text('catalog_editorial_image')}
                                alt={text('catalog_editorial_heading')}
                                loading="lazy"
                            />
                        )}
                        <div className="nd-campaign-copy">
                            <h2>{text('catalog_editorial_heading')}</h2>
                            <p>{text('catalog_editorial_text')}</p>
                            <Link
                                className="nd-button"
                                href={text('catalog_editorial_link', '/search')}
                            >
                                {text('catalog_editorial_button')}
                            </Link>
                        </div>
                    </section>
                    <NordRail
                        title="Top Picks for You"
                        products={products.data.slice(0, 6)}
                    />
                </>
            )}
            <h1>{heading}</h1>
            {image && <img className="nd-category-image" src={image} alt="" />}
            {description && (
                <p className="nd-catalog-description">{description}</p>
            )}
            {subcategories.length > 0 && (
                <nav className="nd-subcategories" aria-label="Subcategories">
                    {subcategories.map((category) => (
                        <Link key={category.id} href={`/c/${category.slug}`}>
                            {category.image_url && (
                                <img
                                    src={category.image_url}
                                    alt=""
                                    loading="lazy"
                                />
                            )}
                            {category.name}
                        </Link>
                    ))}
                </nav>
            )}
            <div className="nd-filter-bar">
                {(catalogSettings.filters && show('catalog_filters')) && (
                    <>
                        <button
                            type="button"
                            onClick={() => dialog.current?.showModal()}
                        >
                            <SlidersHorizontal size={16} aria-hidden />
                            Filters{active && ' •'}
                        </button>
                        <button
                            type="button"
                            onClick={() => dialog.current?.showModal()}
                        >
                            Price⌄
                        </button>
                        <button
                            type="button"
                            onClick={clear}
                            disabled={!active}
                        >
                            Clear All
                        </button>
                    </>
                )}
                <label className="nd-sort">
                    Sort by
                    <select
                        value={sort}
                        onChange={(event) =>
                            update({ sort: event.target.value })
                        }
                    >
                        <option value="newest">Newest</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="name">Name</option>
                    </select>
                </label>
            </div>
            <p className="nd-result-count" role="status">
                {products.total} {products.total === 1 ? 'item' : 'items'}
            </p>
            {products.data.length ? (
                <div className="nd-catalog-grid store-product-grid">
                    <PlaceSlot place="catalog.before_products" />
                    {products.data.map((product) => (
                        <NordCard key={product.id} product={product} />
                    ))}
                </div>
            ) : (
                <div className="nd-empty">
                    <h2>No products found</h2>
                    <p>Try another search or clear your filters.</p>
                    {active && (
                        <button
                            type="button"
                            className="nd-outline"
                            onClick={clear}
                        >
                            Clear filters
                        </button>
                    )}
                    <Link href="/search" className="nd-text-link">
                        Browse all products
                    </Link>
                </div>
            )}
            {products.last_page > 1 && (
                <nav className="nd-pagination" aria-label="Pagination">
                    {products.prev_page_url ? (
                        <Link
                            className="nd-outline"
                            href={products.prev_page_url}
                        >
                            Previous
                        </Link>
                    ) : (
                        <button className="nd-outline" disabled>
                            Previous
                        </button>
                    )}
                    <span>
                        Page {products.current_page} of {products.last_page}
                    </span>
                    {products.next_page_url ? (
                        <Link
                            className="nd-outline"
                            href={products.next_page_url}
                        >
                            Next
                        </Link>
                    ) : (
                        <button className="nd-outline" disabled>
                            Next
                        </button>
                    )}
                </nav>
            )}
            <ExtensionSlot items={slots} className="nd-extensions" />
            <dialog
                className="nd-filter-dialog"
                ref={dialog}
                aria-labelledby="nord-filter-title"
                onClick={(event) => {
                    if (event.target === event.currentTarget)
                        dialog.current?.close();
                }}
            >
                <header>
                    <h2 id="nord-filter-title">Filter products</h2>
                    <button
                        type="button"
                        onClick={() => dialog.current?.close()}
                        aria-label="Close filters"
                    >
                        <X size={22} />
                    </button>
                </header>
                <form key={JSON.stringify(filters)} onSubmit={apply}>
                    <fieldset>
                        <legend>Price</legend>
                        <div className="nd-price-inputs">
                            <label>
                                Minimum
                                <input
                                    name="min_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={filters.min_price}
                                />
                            </label>
                            <label>
                                Maximum
                                <input
                                    name="max_price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={filters.max_price}
                                />
                            </label>
                        </div>
                    </fieldset>
                    {filterBrands.length > 0 && (
                        <fieldset>
                            <legend>Brand</legend>
                            <select
                                aria-label="Brand"
                                name="brand_id"
                                defaultValue={filters.brand_id ?? ''}
                            >
                                <option value="">All brands</option>
                                {filterBrands.map((brand) => (
                                    <option key={brand.id} value={brand.id}>
                                        {brand.name}
                                    </option>
                                ))}
                            </select>
                        </fieldset>
                    )}
                    <fieldset>
                        <legend>Availability</legend>
                        <label className="nd-check">
                            <input
                                name="in_stock"
                                type="checkbox"
                                defaultChecked={
                                    filters.in_stock === true ||
                                    filters.in_stock === 1 ||
                                    filters.in_stock === '1'
                                }
                            />
                            In stock only
                        </label>
                    </fieldset>
                    {filterOptions.map((option) => (
                        <fieldset key={option.id}>
                            <legend>{option.name}</legend>
                            {option.values.map((value) => (
                                <label className="nd-check" key={value.id}>
                                    <input
                                        type="checkbox"
                                        name="options"
                                        value={value.id}
                                        defaultChecked={(
                                            filters.options ?? []
                                        ).includes(value.id)}
                                    />
                                    {value.value}
                                </label>
                            ))}
                        </fieldset>
                    ))}
                    <button className="nd-button" type="submit">
                        Apply filters
                    </button>
                </form>
            </dialog>
        </div>
    );
}
