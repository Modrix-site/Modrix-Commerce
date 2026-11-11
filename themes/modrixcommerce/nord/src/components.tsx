import { usePage } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { Heart, ImageOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { ProductLabels } from '@/components/storefront/product-labels';
import { SaleTag } from '@/components/storefront/sale-notice';
import { BuyButton } from '@/components/storefront/buy-button';
import { useStoreSettings } from '@/hooks/use-store-settings';
import type { ProductCard } from '@/types';

export function useNord() {
    const { storefront } = usePage().props;
    const settings = storefront?.theme.settings ?? {};
    return {
        storefront,
        text: (key: string, fallback = '') => String(settings[key] ?? fallback),
        show: (key: string) => settings[key] !== false,
    };
}

const savedKey = 'nord.saved-products';
function readSaved(): number[] {
    try {
        const value: unknown = JSON.parse(
            localStorage.getItem(savedKey) || '[]',
        );
        return Array.isArray(value)
            ? value.filter((id): id is number => Number.isInteger(id))
            : [];
    } catch {
        return [];
    }
}

/** Device-local saved products; shared across cards and the product page. */
export function SaveProduct({
    id,
    name,
    label = false,
}: {
    id: number;
    name: string;
    label?: boolean;
}) {
    const [saved, setSaved] = useState(false);
    const { show } = useNord();
    useEffect(() => {
        const refresh = () => setSaved(readSaved().includes(id));
        refresh();
        window.addEventListener('storage', refresh);
        window.addEventListener('nord:saved', refresh);
        return () => {
            window.removeEventListener('storage', refresh);
            window.removeEventListener('nord:saved', refresh);
        };
    }, [id]);
    if (!show('show_saved')) return null;
    return (
        <button
            type="button"
            className={label ? 'nd-outline nd-save-label' : 'nd-save'}
            aria-label={`${saved ? 'Remove' : 'Save'} ${name} for later on this device`}
            aria-pressed={saved}
            onClick={() => {
                const next = !saved;
                setSaved(next);
                try {
                    const ids = new Set(readSaved());
                    if (next) ids.add(id);
                    else ids.delete(id);
                    localStorage.setItem(savedKey, JSON.stringify([...ids]));
                    window.dispatchEvent(new Event('nord:saved'));
                } catch {
                    /* Saving is optional when device storage is unavailable. */
                }
            }}
        >
            <Heart
                size={label ? 20 : 16}
                fill={saved ? 'currentColor' : 'none'}
                aria-hidden
            />
            {label && (saved ? 'Saved for later' : 'Save for later')}
        </button>
    );
}

export function NordCard({ product }: { product: ProductCard }) {
    const url = `/p/${product.slug}`;
    const { catalog } = useStoreSettings();
    const discounted =
        Number(product.compare_at_price?.amount) >
        Number(product.price?.amount);
    return (
        <article className="nd-product-card">
            <div className="nd-product-photo store-card-image">
                <Link href={url} aria-label={product.name}>
                    {product.image ? (
                        <img
                            src={product.image.url}
                            alt={product.image.alt || product.name}
                            loading="lazy"
                        />
                    ) : (
                        <ImageOff size={36} aria-label="No product image" />
                    )}
                    {product.image &&
                        catalog.hover_image &&
                        product.hover_image && (
                            <img
                                className="store-card-hover"
                                src={product.hover_image.url}
                                alt=""
                                loading="lazy"
                            />
                        )}
                </Link>
                {catalog.badges && <ProductLabels labels={product.labels} />}
                <SaveProduct id={product.id} name={product.name} />
            </div>
            <div className="nd-product-copy">
                {catalog.badges && <SaleTag sale={product.sale} />}
                {catalog.show_brand && product.brand && (
                    <p className="nd-card-brand">{product.brand.name}</p>
                )}
                {!product.in_stock && (
                    <span className="nd-badge">Unavailable</span>
                )}
                {/* The whole title (short description and name) opens the product. */}
                <Link href={url} className="nd-card-title">
                    {product.short_description && (
                        <p className="nd-card-brand">
                            {product.short_description}
                        </p>
                    )}
                    <h3>{product.name}</h3>
                </Link>
                <PlaceSlot place="product.card" extraProps={{ product }} />
                <p className={`nd-card-price ${discounted ? 'nd-sale' : ''}`}>
                    {product.has_variants && 'From '}
                    {product.price?.formatted ?? 'View product'}
                </p>
                {discounted && (
                    <s className="nd-muted">
                        {product.compare_at_price?.formatted}
                    </s>
                )}
                <BuyButton product={product} className="nd-button" />
            </div>
        </article>
    );
}

export function NordRail({
    title,
    products,
    tabs = true,
}: {
    title: string;
    products: ProductCard[];
    tabs?: boolean;
}) {
    const { storefront } = useNord();
    if (!products.length) return null;
    return (
        <section className="nd-section nd-rail" aria-label={title}>
            <div className="nd-section-heading">
                <h2>{title}</h2>
                <Link className="nd-text-link" href="/search">
                    View All
                </Link>
            </div>
            {tabs && (
                <nav className="nd-tab-list" aria-label={`${title} categories`}>
                    <Link href="/search?sort=newest" className="nd-tab-active">
                        New Arrivals
                    </Link>
                    {storefront?.categories.slice(0, 4).map((category) => (
                        <Link key={category.id} href={`/c/${category.slug}`}>
                            {category.name}
                        </Link>
                    ))}
                </nav>
            )}
            <div
                className="nd-scroller"
                tabIndex={0}
                aria-label={`${title}, scroll for more products`}
            >
                {products.map((product) => (
                    <NordCard key={product.id} product={product} />
                ))}
            </div>
        </section>
    );
}

export function Editorial({
    section,
}: {
    section: 'style' | 'designer' | 'brands';
}) {
    const { text, show } = useNord();
    if (!show(`show_${section}`)) return null;
    return (
        <section className="nd-section nd-editorials">
            <h2>{text(`${section}_heading`)}</h2>
            <div
                className="nd-scroller nd-tiles"
                tabIndex={0}
                aria-label={text(`${section}_heading`)}
            >
                {[1, 2, 3].map((i) => (
                    <article className="nd-tile" key={i}>
                        <Link href={text(`${section}_${i}_link`, '/search')}>
                            {text(`${section}_${i}_image`) && (
                                <img
                                    src={text(`${section}_${i}_image`)}
                                    alt={text(`${section}_${i}_title`)}
                                    loading="lazy"
                                />
                            )}
                        </Link>
                        <div>
                            <h3>{text(`${section}_${i}_title`)}</h3>
                            <p>{text('editorial_text')}</p>
                            <Link
                                className="nd-button"
                                href={text(`${section}_${i}_link`, '/search')}
                            >
                                {text('editorial_button', 'Shop the Edit')}
                            </Link>
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
}
