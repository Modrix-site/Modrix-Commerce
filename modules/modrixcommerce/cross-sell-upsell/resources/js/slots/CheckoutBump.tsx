import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { OfferPrice, OfferThumb } from '../components/offer-item';
import type { OfferItem } from '../components/offer-item';

type BumpItem = OfferItem & { variant_id: number; added: boolean };

type Props = {
    heading: string;
    items: BumpItem[];
    addUrl: string;
    removeUrl: string;
};

/**
 * Checkout add-on ("order bump"): a tick box per product. Ticking adds it to
 * the cart at the offer price, unticking takes it out; the checkout totals
 * reload and what the shopper typed in the form stays.
 */
export default function CheckoutBump({
    heading,
    items,
    addUrl,
    removeUrl,
}: Props) {
    const t = useT();
    const [busy, setBusy] = useState<number | null>(null);

    if (items.length === 0) {
        return null;
    }

    const toggle = (item: BumpItem) => {
        setBusy(item.variant_id);
        router.post(
            item.added ? removeUrl : addUrl,
            {
                offer_id: item.offer_id,
                variant_id: item.variant_id,
                place: 'checkout',
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setBusy(null),
            },
        );
    };

    return (
        <section
            className="cross-sell-bump grid gap-3 rounded-[var(--radius)] border-2 border-dashed border-primary/40 bg-primary/5 p-4"
            aria-label={heading}
        >
            <h2 className="text-sm font-semibold">{heading}</h2>
            <ul className="grid gap-3">
                {items.map((item) => {
                    const id = `cross-sell-bump-${item.offer_id}-${item.variant_id}`;

                    return (
                        <li
                            key={id}
                            className={cn(
                                'flex items-center gap-3',
                                busy === item.variant_id && 'opacity-60',
                            )}
                        >
                            <Checkbox
                                id={id}
                                checked={item.added}
                                disabled={busy !== null}
                                onCheckedChange={() => toggle(item)}
                            />
                            <OfferThumb
                                image={item.image}
                                name={item.name}
                                className="size-12"
                            />
                            <label
                                htmlFor={id}
                                className="grid min-w-0 flex-1 cursor-pointer gap-0.5"
                            >
                                <span className="text-sm font-medium">
                                    {item.added
                                        ? t(':name added to your order', {
                                              name: item.name,
                                          })
                                        : t('Yes, add :name', {
                                              name: item.name,
                                          })}
                                </span>
                                <OfferPrice item={item} />
                            </label>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
