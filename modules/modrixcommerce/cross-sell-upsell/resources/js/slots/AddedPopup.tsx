import { Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';
import { product as productRoute } from '@/routes/storefront';
import {
    OfferAddButton,
    OfferPrice,
    OfferThumb,
} from '../components/offer-item';
import type { OfferItem } from '../components/offer-item';

type Props = {
    token: string;
    added: {
        name: string;
        variant_name: string | null;
        quantity: number;
        image: { url: string; alt: string | null } | null;
    };
    heading: string;
    items: OfferItem[];
    place: 'popup';
    addUrl: string;
    cartUrl: string;
    checkoutUrl: string;
};

/**
 * After a regular "add to cart": confirms the product and suggests the
 * matching offer's products, each with its own Add button. Shown from the
 * global overlay, so it works in every theme; it opens once per add.
 */
export default function AddedPopup({
    token,
    added,
    heading,
    items,
    addUrl,
    cartUrl,
    checkoutUrl,
}: Props) {
    const t = useT();
    const [open, setOpen] = useState(false);

    // A new token comes with every add to cart.
    useEffect(() => {
        setOpen(true);
    }, [token]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <div className="flex items-center gap-3 pr-6">
                    <OfferThumb
                        image={added.image}
                        name={added.name}
                        className="size-14"
                    />
                    <div className="grid min-w-0 gap-0.5">
                        <DialogTitle className="flex items-center gap-1.5 text-base">
                            <CircleCheck
                                className="size-4 shrink-0 text-primary"
                                aria-hidden
                            />
                            {t('Added to your cart')}
                        </DialogTitle>
                        <DialogDescription className="truncate">
                            {added.quantity > 1 ? `${added.quantity} × ` : ''}
                            {added.name}
                            {added.variant_name
                                ? ` · ${added.variant_name}`
                                : ''}
                        </DialogDescription>
                    </div>
                </div>

                {items.length > 0 && (
                    <section
                        className="grid gap-3 border-t pt-4"
                        aria-label={heading}
                    >
                        <h3 className="text-sm font-semibold">{heading}</h3>
                        <ul className="grid gap-3">
                            {items.map((item) => (
                                <li
                                    key={`${item.offer_id}-${item.id}`}
                                    className="flex items-center gap-3"
                                >
                                    <Link
                                        href={productRoute(item.slug).url}
                                        onClick={() => setOpen(false)}
                                    >
                                        <OfferThumb
                                            image={item.image}
                                            name={item.name}
                                            className="size-12"
                                        />
                                    </Link>
                                    <div className="grid min-w-0 flex-1 gap-0.5">
                                        <Link
                                            href={productRoute(item.slug).url}
                                            onClick={() => setOpen(false)}
                                            className="truncate text-sm font-medium hover:underline"
                                        >
                                            {item.name}
                                        </Link>
                                        <OfferPrice item={item} />
                                    </div>
                                    <OfferAddButton
                                        item={item}
                                        place="popup"
                                        addUrl={addUrl}
                                    />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <div className="grid gap-2 border-t pt-4 sm:grid-cols-2">
                    <Button variant="outline" asChild>
                        <Link href={cartUrl}>{t('View cart')}</Link>
                    </Button>
                    <Button asChild>
                        <Link href={checkoutUrl}>{t('Checkout')}</Link>
                    </Button>
                    <Button
                        variant="ghost"
                        className="sm:col-span-2"
                        onClick={() => setOpen(false)}
                    >
                        {t('Continue shopping')}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
