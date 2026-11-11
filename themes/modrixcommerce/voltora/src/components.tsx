import { usePage } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useState } from 'react';
import { Heart, ImageOff } from 'lucide-react';
import { SaleTag } from '@/components/storefront/sale-notice';
import { BuyButton } from '@/components/storefront/buy-button';
import { ProductLabels } from '@/components/storefront/product-labels';
import { useStoreSettings } from '@/hooks/use-store-settings';
import type { ProductCard } from '@/types';
export function useVoltora() {
    const { storefront } = usePage().props;
    const settings = storefront?.theme.settings ?? {};
    return {
        storefront,
        settings,
        text: (key: string, fallback = '') => String(settings[key] ?? fallback),
        show: (key: string) => settings[key] !== false,
    };
}
export function VoltoraCard({ product }: { product: ProductCard }) {
    const { catalog } = useStoreSettings();
    const [saved, setSaved] = useState(() => {
        try {
            return (
                typeof window !== 'undefined' &&
                JSON.parse(
                    localStorage.getItem('voltora.saved') || '[]',
                ).includes(product.id)
            );
        } catch {
            return false;
        }
    });
    const { show } = useVoltora();
    const url = `/p/${product.slug}`;
    const current = Number(product.price?.amount);
    const previous = Number(product.compare_at_price?.amount);
    const discount =
        previous > current && current >= 0
            ? Math.round((1 - current / previous) * 100)
            : 0;
    return (
        <article className="voltora-card">
            {!product.in_stock && (
                <span className="voltora-badge">UNAVAILABLE</span>
            )}
            {show('show_saved') && (
                <button
                    className="voltora-save"
                    aria-label={`${saved ? 'Remove' : 'Save'} ${product.name} for later on this device`}
                    aria-pressed={saved}
                    onClick={() => {
                        const next = !saved;
                        setSaved(next);
                        try {
                            const key = 'voltora.saved';
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
            )}
            <Link href={url} className="voltora-card-photo store-card-image">
                {show('show_sale_badge') && discount > 0 && !product.sale && (
                    <span className="voltora-discount">{discount}% OFF</span>
                )}
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
            <Link href={url} className="voltora-card-info">
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
                {catalog.badges && show('show_sale_badge') && (
                    <SaleTag sale={product.sale} />
                )}
                <PlaceSlot place="product.card" extraProps={{ product }} />
                <small>{product.short_description}</small>
            </Link>
            {(product.show_buy_button ?? show('card_buy_button')) && (
                <BuyButton product={product} className="voltora-card-buy" />
            )}
        </article>
    );
}
export function VoltoraRail({
    title,
    products,
}: {
    title: string;
    products: ProductCard[];
}) {
    return (
        <section className="voltora-wrap">
            <div className="voltora-section-heading">
                <h2>{title}</h2>
                <Link href="/search">VIEW ALL</Link>
            </div>
            {products.length ? (
                <div className="voltora-rail">
                    {products.map((product) => (
                        <VoltoraCard key={product.id} product={product} />
                    ))}
                </div>
            ) : (
                <p className="voltora-empty">
                    Your selected products will appear here. Choose a product
                    source in the homepage customizer.
                </p>
            )}
        </section>
    );
}
