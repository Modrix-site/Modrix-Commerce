import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ElectronicsCard } from '@/components/storefront/electronics-card';
import type { ProductCard } from '@/types';

const KEY = 'store.recent-products';
function read(): ProductCard[] {
    try {
        const values = JSON.parse(localStorage.getItem(KEY) ?? '[]');
        if (!Array.isArray(values)) return [];
        return values
            .filter((item): item is ProductCard =>
                Boolean(
                    item &&
                    Number.isInteger(item.id) &&
                    typeof item.name === 'string' &&
                    typeof item.slug === 'string' &&
                    /^[a-z0-9-]+$/.test(item.slug) &&
                    (!item.image ||
                        (typeof item.image.url === 'string' &&
                            /^https?:\/\/|^\//.test(item.image.url))) &&
                    (!item.price ||
                        (typeof item.price.formatted === 'string' &&
                            typeof item.price.amount === 'string')) &&
                    (!item.compare_at_price ||
                        typeof item.compare_at_price.formatted === 'string'),
                ),
            )
            .slice(0, 12);
    } catch {
        return [];
    }
}
export function rememberProduct(product: ProductCard) {
    try {
        localStorage.setItem(
            KEY,
            JSON.stringify(
                [
                    product,
                    ...read().filter((item) => item.id !== product.id),
                ].slice(0, 12),
            ),
        );
        window.dispatchEvent(new Event('store.recent-products'));
    } catch {
        /* Browsing works when storage is unavailable. */
    }
}
export function RecentlyViewed() {
    const page = usePage();
    const [products, setProducts] = useState<ProductCard[]>([]);
    useEffect(() => {
        const refresh = () => setProducts(read());
        refresh();
        window.addEventListener('store.recent-products', refresh);
        return () =>
            window.removeEventListener('store.recent-products', refresh);
    }, [page.url]);
    const selectedCurrency = page.props.storefront?.displayCurrency;
    const visibleProducts = products.filter(
        (product) =>
            !selectedCurrency ||
            !product.price ||
            (product.price.display_currency ?? product.price.currency) ===
                selectedCurrency,
    );
    if (!visibleProducts.length) return null;
    return (
        <section className="electronics-width electronics-rail mb-6">
            <div className="electronics-rail-header">
                <h2>Previously viewed</h2>
            </div>
            <div className="electronics-product-rail">
                {visibleProducts.slice(0, 4).map((product) => (
                    <ElectronicsCard key={product.id} product={product} />
                ))}
            </div>
        </section>
    );
}
