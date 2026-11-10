import { Link, usePage } from '@inertiajs/react';
import { PawPrint, ShieldCheck, Truck, RotateCcw } from 'lucide-react';
import { category } from '@/routes/storefront';

export const asset = (file: string) =>
    `/theme-assets/modrixcommerce/pawzy/assets/images/${file}?v=1.0.0`;
export function Brand() {
    const { storefront } = usePage().props;
    return (
        <Link href="/" className="pawzy-brand">
            {storefront?.theme.logo ? (
                <img src={storefront.theme.logo} alt={storefront.storeName} />
            ) : (
                <>
                    <PawPrint size={30} />
                    <span>
                        {String(storefront?.theme.settings?.brand ?? 'Pawzy')}
                    </span>
                </>
            )}
        </Link>
    );
}
export function PetLinks() {
    const cats = usePage().props.storefront?.categories ?? [];
    const featured = [
        'pawzy-food',
        'pawzy-grooming',
        'pawzy-fashion',
        'pawzy-bedding',
        'pawzy-toys',
    ];
    const selected = featured.flatMap((slug) =>
        cats.filter((c) => c.slug === slug),
    );
    const collections = selected.length ? selected : cats.slice(0, 6);
    const images = [
        'food.jpg',
        'care.jpg',
        'fashion.jpg',
        'bed-heart.jpg',
        'ball.jpg',
        'dog.jpg',
    ];
    return (
        <nav className="pawzy-pet-links" aria-label="Shop pet collections">
            {collections.map((c, i) => (
                <Link href={category(c.slug)} key={c.id}>
                    <img src={asset(images[i])} alt="" />
                    <strong>{c.name}</strong>
                </Link>
            ))}
        </nav>
    );
}
export function TrustBand() {
    if (usePage().props.storefront?.theme.settings?.show_trust_band === false)
        return null;
    return (
        <section className="pawzy-trust" aria-label="Shopping benefits">
            {[
                [
                    Truck,
                    'Delivery options',
                    'See shipping methods and charges at checkout.',
                ],
                [
                    ShieldCheck,
                    'Secured payments',
                    'Shop with the payment methods offered by this store.',
                ],
                [
                    RotateCcw,
                    'Return policy',
                    'Contact our team for help with returns and exchanges.',
                ],
            ].map(([Icon, title, text]) => {
                const Symbol = Icon as typeof Truck;
                return (
                    <div key={String(title)}>
                        <span>
                            <Symbol size={22} />
                        </span>
                        <div>
                            <h3>{String(title)}</h3>
                            <p>{String(text)}</p>
                        </div>
                    </div>
                );
            })}
        </section>
    );
}
