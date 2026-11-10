import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Heart, ImageOff } from 'lucide-react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { ProductLabels } from '@/components/storefront/product-labels';
import { SaleTag } from '@/components/storefront/sale-notice';
import { BuyButton } from '@/components/storefront/buy-button';
import { useStoreSettings } from '@/hooks/use-store-settings';
import type { ProductCard } from '@/types';
import { readSaved, rememberSaved, savedKey } from './saved-products';

export function useTempo() {
    const { storefront } = usePage().props;
    const settings = storefront?.theme.settings ?? {};
    return {
        storefront,
        text: (key: string, fallback = '') => String(settings[key] ?? fallback),
        show: (key: string) => settings[key] !== false,
    };
}

export function SaveProduct({
    id,
    name,
    slug,
    label = false,
}: {
    id: number;
    name: string;
    slug: string;
    label?: boolean;
}) {
    const [saved, setSaved] = useState(false);
    const { show } = useTempo();
    useEffect(() => {
        const refresh = () => setSaved(readSaved().includes(id));
        refresh();
        window.addEventListener('storage', refresh);
        window.addEventListener('tempo:saved', refresh);
        return () => {
            window.removeEventListener('storage', refresh);
            window.removeEventListener('tempo:saved', refresh);
        };
    }, [id]);
    if (!show('show_saved')) return null;
    return (
        <button
            type="button"
            className={
                label ? 'store-button tp-outline tp-save-label' : 'tp-save'
            }
            aria-label={`${saved ? 'Remove' : 'Save'} ${name} for later on this device`}
            aria-pressed={saved}
            onClick={() => {
                const ids = new Set(readSaved());
                if (saved) ids.delete(id);
                else ids.add(id);
                setSaved(!saved);
                try {
                    if (!saved) rememberSaved({ id, name, slug });
                    localStorage.setItem(savedKey, JSON.stringify([...ids]));
                    window.dispatchEvent(new Event('tempo:saved'));
                } catch {
                    /* Device storage may be unavailable. */
                }
            }}
        >
            <Heart
                size={18}
                fill={saved ? 'currentColor' : 'none'}
                aria-hidden
            />
            {label && (saved ? 'Saved on this device' : 'Save for later')}
        </button>
    );
}

export function TempoCard({ product }: { product: ProductCard }) {
    const { catalog } = useStoreSettings();
    const url = `/p/${product.slug}`;
    const discounted =
        Number(product.compare_at_price?.amount) >
        Number(product.price?.amount);
    return (
        <article className="tp-card">
            <div className="tp-card-photo store-card-image">
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
                {catalog.badges && (
                    <SaleTag sale={product.sale} className="tp-card-sale" />
                )}
                <SaveProduct
                    id={product.id}
                    name={product.name}
                    slug={product.slug}
                />
            </div>
            <div className="tp-card-copy">
                {catalog.show_brand && product.brand && (
                    <p>{product.brand.name}</p>
                )}
                <h3>
                    <Link href={url}>{product.name}</Link>
                </h3>
                {!product.in_stock && <small>Unavailable</small>}
                <p className="store-price tp-card-price">
                    {product.has_variants && 'From '}
                    {product.price?.formatted ?? 'View product'}{' '}
                    {discounted && <s>{product.compare_at_price?.formatted}</s>}
                </p>
                <PlaceSlot place="product.card" extraProps={{ product }} />
                <BuyButton
                    product={product}
                    className="store-button tp-button tp-button-primary"
                />
            </div>
        </article>
    );
}

export function TempoRail({
    title,
    products,
}: {
    title: string;
    products: ProductCard[];
}) {
    if (!products.length) return null;
    return (
        <section
            className="tp-home-section tp-recommendations"
            aria-label={title}
        >
            <h2 className="tp-outlet-heading">{title}</h2>
            <div className="tp-product-grid">
                {products.map((product) => (
                    <TempoCard key={product.id} product={product} />
                ))}
            </div>
        </section>
    );
}
