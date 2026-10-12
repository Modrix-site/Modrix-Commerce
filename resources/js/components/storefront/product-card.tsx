import { Link } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';
import { product as productRoute } from '@/routes/storefront';
import type { ProductCard as ProductCardData } from '@/types';

export function ProductCard({ product }: { product: ProductCardData }) {
    return (
        <Link
            href={productRoute(product.slug)}
            className="group grid gap-2 rounded-lg focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div className="aspect-square overflow-hidden rounded-lg border bg-muted">
                {product.image ? (
                    <img
                        src={product.image.url}
                        alt={product.image.alt ?? product.name}
                        loading="lazy"
                        className="size-full object-cover transition-opacity group-hover:opacity-90"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center text-muted-foreground">
                        <ImageOff className="size-6" aria-hidden />
                        <span className="sr-only">No image</span>
                    </div>
                )}
            </div>
            <div className="grid gap-0.5">
                <span className="font-medium group-hover:underline">
                    {product.name}
                </span>
                <Price product={product} />
                {!product.in_stock && (
                    <span className="text-xs text-muted-foreground">
                        Out of stock
                    </span>
                )}
            </div>
        </Link>
    );
}

function Price({ product }: { product: ProductCardData }) {
    if (!product.price) {
        return null;
    }

    return (
        <span className="text-sm tabular-nums">
            {product.has_variants && 'From '}
            {product.price.formatted}
            {product.compare_at_price && (
                <s className="ml-2 text-muted-foreground">
                    <span className="sr-only">Was </span>
                    {product.compare_at_price.formatted}
                </s>
            )}
        </span>
    );
}

export function ProductGrid({ products }: { products: ProductCardData[] }) {
    return (
        <ul className="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
            {products.map((p) => (
                <li key={p.id}>
                    <ProductCard product={p} />
                </li>
            ))}
        </ul>
    );
}
