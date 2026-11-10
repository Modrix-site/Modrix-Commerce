import { useRef } from 'react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { Card, Countdown, collection, str } from '../components';
export default function Rail({ section }: ThemeSectionProps) {
    const s = section.settings;
    const c = collection(s.collection);
    const products = (c?.products ?? []).slice(0, Number(s.limit) || 10);
    const rail = useRef<HTMLDivElement>(null);
    const scroll = (direction: number) => {
        const node = rail.current;
        if (!node) return;
        const rtl = getComputedStyle(node).direction === 'rtl' ? -1 : 1;
        node.scrollBy({
            left: direction * rtl * node.clientWidth * 0.8,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                .matches
                ? 'instant'
                : 'smooth',
        });
    };
    return (
        <section
            className={
                're-frame re-section re-tech-rail' +
                (s.deals === true ? ' re-tech-deals' : '') +
                (s.compact === true ? ' re-tech-accessories' : '')
            }
            aria-label={str(s.heading)}
        >
            <div className="re-section-head">
                <h2 style={{ fontSize: Number(s.heading_size) || 20 }}>
                    {str(s.heading)}
                </h2>
                {s.deals === true && (
                    <Countdown
                        endsAt={str(s.ends_at)}
                        label={str(s.countdown_label)}
                    />
                )}
                <nav aria-label={str(s.heading) + ' links'}>
                    {section.blocks.map((b) => (
                        <Link
                            key={b.id}
                            href={str(b.settings.link) || '/search'}
                            {...blockAttributes(b)}
                        >
                            {str(b.settings.label)}
                        </Link>
                    ))}
                    {str(s.view_label) && (
                        <Link href={str(s.link) || c?.url || '/search'}>
                            {str(s.view_label)}
                        </Link>
                    )}
                </nav>
            </div>
            {s.compact === true ? (
                <div className="re-accessory-grid">
                    {products.map((p) => (
                        <Card key={p.id} product={p} quickAdd={false} />
                    ))}
                </div>
            ) : (
                <div className="re-rail-wrap">
                    <div
                        className="re-product-carousel"
                        ref={rail}
                        tabIndex={0}
                        aria-label={str(s.heading) + ' products'}
                    >
                        {products.map((p) => (
                            <Card
                                key={p.id}
                                product={p}
                                quickAdd={s.quick_add === true}
                            />
                        ))}
                    </div>
                    {products.length > 5 && (
                        <>
                            <button
                                type="button"
                                className="re-rail-prev"
                                aria-label={
                                    'Previous ' + str(s.heading) + ' products'
                                }
                                onClick={() => scroll(-1)}
                            >
                                <ArrowLeft size={22} />
                            </button>
                            <button
                                type="button"
                                className="re-rail-next"
                                aria-label={
                                    'Next ' + str(s.heading) + ' products'
                                }
                                onClick={() => scroll(1)}
                            >
                                <ArrowRight size={22} />
                            </button>
                        </>
                    )}
                </div>
            )}
        </section>
    );
}
