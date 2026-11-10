import { Zap } from 'lucide-react';
import { ProductCard } from '@/components/storefront/product-card';
import { remaining, useNow } from '@/components/storefront/sale-notice';
import { useT } from '@/lib/i18n';
import type { ProductCard as ProductCardData } from '@/types';

/**
 * Home page: the live sale with a countdown and its products.
 */
export default function FlashSaleBanner({
    name,
    endsAt,
    products,
}: {
    name: string;
    endsAt: string;
    products: ProductCardData[];
}) {
    const t = useT();
    const left = remaining(endsAt, useNow());

    if (left === null || products.length === 0) {
        return null;
    }

    return (
        <section
            aria-label={name}
            className="grid gap-4 rounded-xl border border-orange-300 bg-orange-50/60 p-4 sm:p-6 dark:border-orange-900 dark:bg-orange-950/40"
        >
            <div className="flex flex-wrap items-end justify-between gap-2">
                <h2 className="flex items-center gap-2 text-xl font-semibold tracking-tight">
                    <Zap className="size-5 text-orange-500" aria-hidden />
                    {name}
                </h2>
                <p className="text-sm">
                    {t('Ends in')}{' '}
                    <span className="font-mono font-semibold tabular-nums">
                        {left}
                    </span>
                </p>
            </div>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                {products.map((product) => (
                    <ProductCard key={product.id} product={product} />
                ))}
            </div>
        </section>
    );
}
