import { cn } from '@/lib/utils';

/**
 * A product's description. `description_html` is cleaned by the server
 * (App\Core\Content\RichText); `description` is the plain-text fallback.
 */
export function ProductDescription({
    product,
    className,
}: {
    product: { description?: string | null; description_html?: string | null };
    className?: string;
}) {
    if (product.description_html) {
        return (
            <div
                className={cn('rich-text', className)}
                dangerouslySetInnerHTML={{ __html: product.description_html }}
            />
        );
    }

    if (!product.description) {
        return null;
    }

    return (
        <div className={cn('rich-text whitespace-pre-line', className)}>
            {product.description}
        </div>
    );
}
