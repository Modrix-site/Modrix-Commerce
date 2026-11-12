import { ProductGrid } from '@/components/storefront/product-card';
import { useT } from '@/lib/i18n';
import type { ProductCard } from '@/types';

/**
 * Product page: "Customers also bought" (upsells), then "You may also like"
 * (related products, or products from the same category).
 */
export function RelatedProducts({
    productId,
    upsells = [],
    related = [],
}: {
    productId: number;
    upsells?: ProductCard[];
    related?: ProductCard[];
}) {
    const t = useT();
    const upsellIds = new Set(upsells.map((p) => p.id));
    const sections = [
        { title: t('Customers also bought'), products: upsells },
        {
            title: t('You may also like'),
            products: related.filter((p) => !upsellIds.has(p.id)),
        },
    ].map((s) => ({
        ...s,
        products: s.products.filter((p) => p.id !== productId),
    }));

    return (
        <>
            {sections
                .filter((s) => s.products.length > 0)
                .map((s) => (
                    <section key={s.title} className="mt-12 grid gap-4">
                        <h2 className="text-lg font-semibold">{s.title}</h2>
                        <ProductGrid products={s.products} />
                    </section>
                ))}
        </>
    );
}
