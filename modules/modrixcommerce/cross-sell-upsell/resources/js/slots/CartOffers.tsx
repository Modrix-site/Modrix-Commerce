import { OfferCard } from '../components/offer-item';
import type { OfferItem } from '../components/offer-item';

type Props = {
    heading: string;
    items: OfferItem[];
    place: 'cart';
    addUrl: string;
};

/** Cart page: "Complete your order" from offers the cart's products trigger. */
export default function CartOffers({ heading, items, addUrl }: Props) {
    if (items.length === 0) {
        return null;
    }

    return (
        <section
            className="cross-sell-cart grid gap-3 rounded-[var(--radius)] border bg-muted/40 p-4"
            aria-label={heading}
        >
            <h2 className="text-base font-semibold">{heading}</h2>
            <ul className="grid grid-cols-[repeat(auto-fill,minmax(8rem,1fr))] gap-3">
                {items.map((item) => (
                    <OfferCard
                        key={`${item.offer_id}-${item.id}`}
                        item={item}
                        place="cart"
                        addUrl={addUrl}
                    />
                ))}
            </ul>
        </section>
    );
}
