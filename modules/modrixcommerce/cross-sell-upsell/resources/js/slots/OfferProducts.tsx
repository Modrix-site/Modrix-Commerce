import { OfferCard } from '../components/offer-item';
import type { OfferItem } from '../components/offer-item';

type Props = {
    heading: string;
    type: 'upsell' | 'cross_sell';
    items: OfferItem[];
    place: 'product';
    addUrl: string;
};

/**
 * Product page: the best matching offer's products (an upgrade or add-ons),
 * each with its offer price and an Add button.
 */
export default function OfferProducts({ heading, items, addUrl }: Props) {
    if (items.length === 0) {
        return null;
    }

    return (
        <section className="cross-sell-offers grid gap-3" aria-label={heading}>
            <h2 className="text-base font-semibold">{heading}</h2>
            {/* As many columns as fit, so it suits wide pages and narrow sidebars. */}
            <ul className="grid grid-cols-[repeat(auto-fill,minmax(8rem,1fr))] gap-3">
                {items.map((item) => (
                    <OfferCard
                        key={`${item.offer_id}-${item.id}`}
                        item={item}
                        place="product"
                        addUrl={addUrl}
                    />
                ))}
            </ul>
        </section>
    );
}
