import { Link } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';
import { SaleTag } from '@/components/storefront/sale-notice';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { product as productRoute } from '@/routes/storefront';
import type { ProductCard as ProductCardData } from '@/types';

/**
 * Product card in the theme's chosen variant: "standard" (image with a
 * hairline border), "minimal" (borderless, edge-to-edge image) or "framed"
 * (the whole card is a bordered panel).
 */
export function ProductCard({ product }: { product: ProductCardData }) {
    const { productCard } = useStoreTheme();
    const t = useT();
    const framed = productCard === 'framed';

    return (
        <Link
            href={productRoute(product.slug)}
            className={cn(
                'group grid gap-2 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                framed && 'h-full content-start border bg-card p-3',
            )}
        >
            <div
                className={cn(
                    'aspect-square overflow-hidden bg-muted',
                    productCard === 'standard' && 'rounded-lg border',
                    productCard === 'minimal' && 'rounded-sm',
                    framed && 'rounded-md',
                )}
            >
                {product.image ? (
                    <img
                        src={product.image.url}
                        alt={product.image.alt ?? product.name}
                        loading="lazy"
                        className="size-full object-cover transition duration-300 group-hover:scale-[1.02] group-hover:opacity-95"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center text-muted-foreground">
                        <ImageOff className="size-6" aria-hidden />
                        <span className="sr-only">{t('No image')}</span>
                    </div>
                )}
            </div>
            <div
                className={cn(
                    'grid gap-0.5',
                    productCard === 'minimal' && 'text-center',
                )}
            >
                <span
                    className={cn(
                        'font-medium group-hover:underline',
                        productCard === 'minimal' && 'text-sm',
                    )}
                >
                    {product.name}
                </span>
                <Price product={product} />
                {!product.in_stock && (
                    <span className="text-xs text-muted-foreground">
                        {t('Out of stock')}
                    </span>
                )}
            </div>
        </Link>
    );
}

function Price({ product }: { product: ProductCardData }) {
    const t = useT();

    if (!product.price) {
        return null;
    }

    return (
        <span
            className={cn(
                'store-price text-sm tabular-nums',
                product.compare_at_price && 'store-price-sale',
            )}
        >
            {product.has_variants && `${t('From')} `}
            {product.price.formatted}
            {product.compare_at_price && (
                <s className="ml-2 text-muted-foreground">
                    <span className="sr-only">{t('Was')} </span>
                    {product.compare_at_price.formatted}
                </s>
            )}
            <SaleTag sale={product.sale} className="ml-2 align-middle" />
        </span>
    );
}

export function ProductGrid({ products }: { products: ProductCardData[] }) {
    const { gridColumns } = useStoreTheme();

    return (
        <ul
            className={cn(
                'grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3',
                gridColumns === 4 && 'lg:grid-cols-4',
            )}
        >
            {products.map((p) => (
                <li key={p.id}>
                    <ProductCard product={p} />
                </li>
            ))}
        </ul>
    );
}
