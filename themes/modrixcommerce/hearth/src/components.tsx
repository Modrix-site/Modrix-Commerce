import { usePage } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useState } from 'react';
import { Heart, ImageOff } from 'lucide-react';
import { ProductLabels } from '@/components/storefront/product-labels';
import { SaleTag } from '@/components/storefront/sale-notice';
import { useStoreSettings } from '@/hooks/use-store-settings';
import type { ProductCard } from '@/types';
export function useHearth() {
    const { storefront } = usePage().props;
    const settings = storefront?.theme.settings ?? {};
    return {
        storefront,
        settings,
        text: (key: string, fallback = '') => String(settings[key] ?? fallback),
        show: (key: string) => settings[key] !== false,
    };
}
export function HearthCard({ product }: { product: ProductCard }) {
    const { catalog } = useStoreSettings();
    const [saved, setSaved] = useState(() => {
        try {
            return (
                typeof window !== 'undefined' &&
                JSON.parse(
                    localStorage.getItem('hearth.saved') || '[]',
                ).includes(product.id)
            );
        } catch {
            return false;
        }
    });
    const url = `/p/${product.slug}`;
    return (
        <article className="hearth-card">
            {!product.in_stock && (
                <span className="hearth-badge">UNAVAILABLE</span>
            )}
            <button
                className="hearth-save"
                aria-label={`${saved ? 'Remove' : 'Save'} ${product.name} for later on this device`}
                aria-pressed={saved}
                onClick={() => {
                    const next = !saved;
                    setSaved(next);
                    try {
                        const key = 'hearth.saved';
                        const ids = new Set<number>(
                            JSON.parse(localStorage.getItem(key) || '[]'),
                        );
                        if (next) ids.add(product.id);
                        else ids.delete(product.id);
                        localStorage.setItem(key, JSON.stringify([...ids]));
                    } catch {}
                }}
            >
                <Heart size={17} fill={saved ? 'currentColor' : 'none'} />
            </button>
            <Link href={url} className="hearth-card-photo store-card-image">
                {catalog.badges && <ProductLabels labels={product.labels} />}
                {product.image ? (
                    <img
                        src={product.image.url}
                        alt={product.image.alt || product.name}
                        loading="lazy"
                    />
                ) : (
                    <ImageOff size={34} />
                )}
                {product.image && catalog.hover_image && product.hover_image && (
                    <img
                        className='store-card-hover'
                        src={product.hover_image.url}
                        alt=''
                        loading='lazy'
                    />
                )}
            </Link>
            <Link href={url} className="hearth-card-info">
                <strong>
                    {product.has_variants ? 'From ' : ''}
                    {product.price?.formatted ?? 'View product'}
                </strong>
                <span>{product.name}</span>
                {catalog.show_brand && product.brand && (
                    <small>{product.brand.name}</small>
                )}
                {product.compare_at_price && (
                    <s>{product.compare_at_price.formatted}</s>
                )}
                {catalog.badges && (
                    <SaleTag sale={product.sale} className="mx-auto" />
                )}
                <PlaceSlot place="product.card" extraProps={{ product }} />
                <small>{product.short_description}</small>
            </Link>
        </article>
    );
}
export function HearthRail({
    title,
    products,
}: {
    title: string;
    products: ProductCard[];
}) {
    return (
        <section className="hearth-wrap">
            <div className="hearth-section-heading">
                <h2>{title}</h2>
                <Link href="/search">VIEW ALL</Link>
            </div>
            {products.length ? (
                <div className="hearth-rail">
                    {products.map((product) => (
                        <HearthCard key={product.id} product={product} />
                    ))}
                </div>
            ) : (
                <p className="hearth-empty">
                    Your selected products will appear here. Choose a product
                    source in the homepage customizer.
                </p>
            )}
        </section>
    );
}
