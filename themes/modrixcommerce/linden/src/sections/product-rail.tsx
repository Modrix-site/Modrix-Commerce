import { useRef, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str, collection, Card } from '../components';
export default function Rail({ section }: ThemeSectionProps) {
    const s = section.settings;
    const [tab, setTab] = useState(0);
    const c = collection(
        section.blocks[tab]?.settings.collection ?? s.collection,
    );
    const products = (c?.products ?? []).slice(0, Number(s.limit) || 12);
    const ref = useRef<HTMLDivElement>(null);
    const scroll = (dir: number) => {
        const el = ref.current;
        if (el)
            el.scrollBy({
                left:
                    dir *
                    (getComputedStyle(el).direction === 'rtl' ? -1 : 1) *
                    el.clientWidth *
                    0.85,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                    .matches
                    ? 'instant'
                    : 'smooth',
            });
    };
    return (
        <section className="lv-shelf" style={{ background: str(s.background) }}>
            <div className="lv-frame">
                <h2>{str(s.heading)}</h2>
                {section.blocks.length > 0 && (
                    <div
                        className="lv-rail-tabs"
                        role="tablist"
                        aria-label={str(s.heading)}
                    >
                        {section.blocks.map((b, i) => (
                            <button
                                key={b.id}
                                type="button"
                                role="tab"
                                aria-selected={tab === i}
                                {...blockAttributes(b)}
                                onClick={() => {
                                    setTab(i);
                                    ref.current?.scrollTo({ left: 0 });
                                }}
                            >
                                {str(b.settings.label)}
                            </button>
                        ))}
                    </div>
                )}
                <div
                    className={
                        'lv-shelf-layout' +
                        (str(s.campaign_image) ? ' lv-shelf-campaign' : '')
                    }
                >
                    {str(s.campaign_image) && (
                        <Link
                            className="lv-campaign-tile"
                            href={str(s.campaign_link) || '/search'}
                        >
                            <img
                                src={str(s.campaign_image)}
                                alt=""
                                loading="lazy"
                            />
                            <div>
                                <h3>{str(s.campaign_heading)}</h3>
                                <span className="lv-btn lv-btn-light">
                                    {str(s.campaign_button)}
                                </span>
                            </div>
                        </Link>
                    )}
                    <div className="lv-rail-wrap">
                        <div
                            ref={ref}
                            className="lv-product-carousel"
                            tabIndex={0}
                            aria-label={str(s.heading) + ' products'}
                        >
                            {products.map((p) => (
                                <Card
                                    key={p.id}
                                    product={p}
                                    quickAdd={s.quick_add !== false}
                                />
                            ))}
                        </div>
                        <div className="lv-rail-arrows">
                            <button
                                aria-label={
                                    'Previous ' + str(s.heading) + ' products'
                                }
                                onClick={() => scroll(-1)}
                                type="button"
                            >
                                <ChevronLeft size={20} />
                            </button>
                            <button
                                aria-label={
                                    'Next ' + str(s.heading) + ' products'
                                }
                                onClick={() => scroll(1)}
                                type="button"
                            >
                                <ChevronRight size={20} />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
