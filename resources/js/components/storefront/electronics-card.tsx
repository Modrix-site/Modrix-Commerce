import { Link } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { useStoreSettings } from '@/hooks/use-store-settings';
import { Heart, ImageOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import { BuyButton } from '@/components/storefront/buy-button';
import { ProductLabels } from '@/components/storefront/product-labels';
import { SaleTag } from '@/components/storefront/sale-notice';
import { product as productRoute } from '@/routes/storefront';
import type { ProductCard } from '@/types';

export function ElectronicsCard({ product }: { product: ProductCard }) {
    const { catalog } = useStoreSettings();
    const [saved, setSaved] = useState(false);
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
    const saving =
        product.price && product.compare_at_price
            ? Number(
                  product.compare_at_price.display_amount ??
                      product.compare_at_price.amount,
              ) - Number(product.price.display_amount ?? product.price.amount)
            : 0;
    return (
        <article className="electronics-card">
            <button
                className="electronics-favorite"
                type="button"
                aria-label={`Save ${product.name}`}
                aria-pressed={saved}
                onClick={() => {
                    try {
                        const stored = JSON.parse(
                            localStorage.getItem('store.saved-products') ??
                                '[]',
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
                }}
            >
                <Heart size={19} fill={saved ? 'currentColor' : 'none'} />
            </button>
            <div className="relative store-card-image">
                {catalog.badges && <ProductLabels labels={product.labels} />}
                <Link
                    href={productRoute(product.slug)}
                    className="electronics-card-image"
                >
                    {product.image ? (
                        <img
                            src={product.image.url}
                            alt={product.image.alt ?? product.name}
                            loading="lazy"
                        />
                    ) : (
                        <ImageOff size={28} />
                    )}
                    {product.image && catalog.hover_image && product.hover_image && (
                        <img
                            className="store-card-hover"
                            src={product.hover_image.url}
                            alt=""
                            loading="lazy"
                        />
                    )}
                </Link>
            </div>
            <Link
                href={productRoute(product.slug)}
                className="electronics-card-title"
            >
                {product.name}
            </Link>
            {catalog.badges && (
                <SaleTag sale={product.sale} className="mt-1" />
            )}
            <PlaceSlot place="product.card" extraProps={{ product }} />
            <div className="electronics-card-price">
                <div>
                    {product.compare_at_price && (
                        <s>{product.compare_at_price.formatted}</s>
                    )}
                    <strong>
                        {product.has_variants ? 'From ' : ''}
                        {product.price?.formatted}
                    </strong>
                </div>
                {saving > 0 && (
                    <span className="electronics-saving">
                        Save{' '}
                        {product.price?.display_currency ??
                            product.price?.currency}{' '}
                        {saving.toLocaleString(undefined, {
                            maximumFractionDigits: 2,
                        })}
                    </span>
                )}
            </div>
            <BuyButton product={product} variant="electronics" />
        </article>
    );
}
