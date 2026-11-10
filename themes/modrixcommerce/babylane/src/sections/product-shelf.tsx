import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str, Card, Countdown, collection, columns } from '../components';
export default function Shelf({ section }: ThemeSectionProps) {
    const s = section.settings;
    const c = collection(s.collection);
    const all = (c?.products ?? []).filter(
        (p) =>
            s.discounted !== true ||
            Number(p.compare_at_price?.amount) > Number(p.price?.amount),
    );
    const skip = all.length ? (Number(s.offset) || 0) % all.length : 0;
    const products = [...all.slice(skip), ...all.slice(0, skip)].slice(
        0,
        Number(s.limit) || 6,
    );
    if (!products.length) return null;
    return (
        <section className="bl-frame bl-section">
            <div
                className={
                    'bl-section-head ' + (s.filled !== false ? 'bl-filled' : '')
                }
            >
                <h2>
                    {str(s.heading)}
                    {str(s.ends_at) && (
                        <Countdown
                            endsAt={str(s.ends_at)}
                            label={str(s.countdown_label)}
                        />
                    )}
                </h2>
                {str(s.view_label) && (
                    <Link href={str(s.link) || c?.url || '/search'}>
                        {str(s.view_label)}
                    </Link>
                )}
            </div>
            <div className="bl-product-grid" style={columns(s.columns)}>
                {products.map((p) => (
                    <Card
                        key={p.id}
                        product={p}
                        quickAdd={s.quick_add !== false}
                    />
                ))}
            </div>
        </section>
    );
}
