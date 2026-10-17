import { ProductCard } from '@/components/storefront/product-card';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { ProductCard as ProductCardData } from '@/types';

/** "You may also need": related products of what is in the cart. */
export function CartCrossSells({
    products,
    className,
}: {
    products: ProductCardData[] | undefined;
    className?: string;
}) {
    const t = useT();

    if (!products || products.length === 0) {
        return null;
    }

    return (
        <section className={cn('store-cross-sells grid gap-4', className)}>
            <h2 className="text-lg font-semibold">{t('You may also need')}</h2>
            <ul className="grid grid-cols-2 gap-4 md:grid-cols-4">
                {products.map((product) => (
                    <li key={product.id}>
                        <ProductCard product={product} />
                    </li>
                ))}
            </ul>
        </section>
    );
}
