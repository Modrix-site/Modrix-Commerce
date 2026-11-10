import { Form, Link } from '@inertiajs/react';
import { ImageOff, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { product as productRoute } from '@/routes/storefront';
import type { MoneyValue, ProductCard } from '@/types';

export type OfferItem = ProductCard & {
    offer_id: number;
    /** The price when added from the offer (null without a discount). */
    offer_price: MoneyValue | null;
};

export type OfferPlace = 'product' | 'popup' | 'cart' | 'checkout';

export function OfferThumb({
    image,
    name,
    className,
}: {
    image: { url: string; alt: string | null } | null;
    name: string;
    className?: string;
}) {
    if (!image) {
        return (
            <span
                className={cn(
                    'flex shrink-0 items-center justify-center rounded-[var(--radius)] bg-muted text-muted-foreground',
                    className,
                )}
                aria-hidden
            >
                <ImageOff className="size-4" />
            </span>
        );
    }

    return (
        <img
            src={image.url}
            alt={image.alt ?? name}
            loading="lazy"
            className={cn(
                'shrink-0 rounded-[var(--radius)] bg-muted object-cover',
                className,
            )}
        />
    );
}

/** The offer price with the regular price crossed out. */
export function OfferPrice({ item }: { item: OfferItem }) {
    const t = useT();

    if (!item.price) {
        return null;
    }

    const from = item.has_variants ? `${t('From')} ` : '';

    if (item.offer_price) {
        return (
            <span className="flex flex-wrap items-baseline gap-x-2 text-sm">
                <span className="font-semibold text-primary">
                    {from}
                    {item.offer_price.formatted}
                </span>
                <span className="text-xs text-muted-foreground line-through">
                    {item.price.formatted}
                </span>
            </span>
        );
    }

    return (
        <span className="flex flex-wrap items-baseline gap-x-2 text-sm">
            <span className="font-semibold">
                {from}
                {item.price.formatted}
            </span>
            {item.compare_at_price && (
                <span className="text-xs text-muted-foreground line-through">
                    {item.compare_at_price.formatted}
                </span>
            )}
        </span>
    );
}

/**
 * "Add" for a product with one variant; products with options link to their
 * page instead (the shopper picks the option there).
 */
export function OfferAddButton({
    item,
    place,
    addUrl,
    size = 'sm',
    className,
}: {
    item: OfferItem;
    place: OfferPlace;
    addUrl: string;
    size?: 'sm' | 'default';
    className?: string;
}) {
    const t = useT();

    if (item.variant_id === null) {
        return (
            <Button variant="outline" size={size} className={className} asChild>
                <Link href={productRoute(item.slug).url}>
                    {t('Choose options')}
                </Link>
            </Button>
        );
    }

    return (
        <Form
            action={addUrl}
            method="post"
            options={{ preserveScroll: true }}
            className={cn('contents')}
        >
            {({ processing }) => (
                <>
                    <input
                        type="hidden"
                        name="offer_id"
                        value={item.offer_id}
                    />
                    <input
                        type="hidden"
                        name="variant_id"
                        value={item.variant_id ?? ''}
                    />
                    <input type="hidden" name="place" value={place} />
                    <Button
                        type="submit"
                        size={size}
                        variant="outline"
                        className={className}
                        disabled={processing}
                        aria-label={t('Add :name to cart', { name: item.name })}
                    >
                        <Plus /> {t('Add')}
                    </Button>
                </>
            )}
        </Form>
    );
}

/** A suggested product as a small card (product page and cart). */
export function OfferCard({
    item,
    place,
    addUrl,
}: {
    item: OfferItem;
    place: OfferPlace;
    addUrl: string;
}) {
    return (
        <li className="flex min-w-0 flex-col gap-3 rounded-[var(--radius)] border bg-background p-3">
            <Link
                href={productRoute(item.slug).url}
                className="block overflow-hidden rounded-[var(--radius)]"
            >
                <OfferThumb
                    image={item.image}
                    name={item.name}
                    className="aspect-square w-full"
                />
            </Link>
            <div className="grid min-w-0 gap-1">
                <Link
                    href={productRoute(item.slug).url}
                    className="line-clamp-2 text-sm font-medium hover:underline"
                >
                    {item.name}
                </Link>
                <OfferPrice item={item} />
            </div>
            <OfferAddButton
                item={item}
                place={place}
                addUrl={addUrl}
                className="mt-auto w-full"
            />
        </li>
    );
}
