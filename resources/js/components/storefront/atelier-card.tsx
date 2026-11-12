import { Link } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { ArrowRight, Heart, ImageOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import { BuyButton } from '@/components/storefront/buy-button';
import { ProductLabels } from '@/components/storefront/product-labels';
import { SaleTag } from '@/components/storefront/sale-notice';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { product as productRoute } from '@/routes/storefront';
import type { ProductCard } from '@/types';

/**
 * Editorial product tile: a quiet photo panel with an optional sale tag, a
 * wishlist heart in the corner, a caption and a "view" bar that rises on
 * hover. Used when the theme's product cards are "atelier".
 */
export function AtelierCard({ product }: { product: ProductCard }) {
    const { catalog } = useStoreSettings();
    const [saved, setSaved] = useState(false);
    const { theme } = useStoreTheme();
    const showWishlist = theme?.settings?.show_wishlist !== false;

    useEffect(() => {
        try {
            const ids = JSON.parse(
                localStorage.getItem('store.saved-products') ?? '[]',
            );
            setSaved(Array.isArray(ids) && ids.includes(product.id));
        } catch {
            setSaved(false);
        }
    }, [product.id]);

    const toggleSaved = () => {
        try {
            const stored = JSON.parse(
                localStorage.getItem('store.saved-products') ?? '[]',
            );
            const ids = Array.isArray(stored)
                ? stored.filter((id) => Number.isInteger(id))
                : [];
            localStorage.setItem(
                'store.saved-products',
                JSON.stringify(
                    saved
                        ? ids.filter((id) => id !== product.id)
                        : [...ids, product.id],
                ),
            );
            setSaved((value) => !value);
        } catch {
            setSaved(false);
        }
    };

    const href = productRoute(product.slug);

    return (
        <article className="ecom-card">
            <div className="ecom-card-photo store-card-image">
                {catalog.badges && <ProductLabels labels={product.labels} />}
                {product.compare_at_price && (
                    <span className="ecom-tag">Sale</span>
                )}
                {!product.in_stock && (
                    <span className="ecom-tag ecom-tag-muted">Sold out</span>
                )}
                {showWishlist && (
                    <button
                        className="ecom-heart"
                        type="button"
                        aria-label={`Save ${product.name}`}
                        aria-pressed={saved}
                        onClick={toggleSaved}
                    >
                        <Heart
                            size={18}
                            fill={saved ? 'currentColor' : 'none'}
                        />
                    </button>
                )}
                <Link
                    href={href}
                    className="ecom-photo-link"
                    aria-label={product.name}
                >
                    {product.image ? (
                        <img
                            src={product.image.url}
                            alt={product.image.alt ?? product.name}
                            loading="lazy"
                        />
                    ) : (
                        <span className="ecom-photo-empty">
                            <ImageOff size={28} aria-hidden />
                        </span>
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
                    <span className="ecom-quick" aria-hidden>
                        View details
                        <ArrowRight size={14} />
                    </span>
                </Link>
            </div>
            <div className="ecom-card-caption">
                <h3>
                    <Link href={href}>{product.name}</Link>
                </h3>
                <p className="ecom-price">
                    {product.has_variants && 'From '}
                    {product.price?.formatted}
                    {product.compare_at_price && (
                        <s>{product.compare_at_price.formatted}</s>
                    )}
                </p>
                {catalog.badges && (
                    <SaleTag sale={product.sale} className="mt-1" />
                )}
                <PlaceSlot place="product.card" extraProps={{ product }} />
                <BuyButton product={product} variant="atelier" />
            </div>
        </article>
    );
}
