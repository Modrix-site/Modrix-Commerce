import { Head } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import { HomeSections } from '@/components/storefront/home-sections';
import type { HomeSection } from '@/components/storefront/home-sections';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import type { HomeProps } from '@/pages/storefront/home';
import { TempoRail, useTempo } from './components';
import './style.css';

function Carousel({ slides }: { slides: HomeSection[] }) {
    const [index, setIndex] = useState(0);
    const { text } = useTempo();
    const active = slides[Math.min(index, slides.length - 1)];
    if (!active) return null;
    const change = (delta: number) =>
        setIndex((index + delta + slides.length) % slides.length);
    return (
        <section
            className="tp-outlet-hero"
            aria-roledescription="carousel"
            aria-label="Seasonal campaigns"
            data-position={text('hero_position', 'left')}
        >
            {active.data.image && (
                <img
                    className="tp-hero-image"
                    src={active.data.image}
                    alt=""
                    fetchPriority="high"
                />
            )}
            <div className="tp-hero-shade" />
            <div className="tp-frame">
                <div
                    className="tp-hero-copy"
                    aria-live="polite"
                    aria-atomic="true"
                >
                    {text('hero_eyebrow') && (
                        <p className="tp-eyebrow">{text('hero_eyebrow')}</p>
                    )}
                    <h1>{active.settings.heading}</h1>
                    <p>{active.settings.subheading}</p>
                    {active.settings.button_label && (
                        <Link
                            className="store-button tp-button tp-button-primary"
                            href={String(
                                active.settings.button_link || '/search',
                            )}
                        >
                            {active.settings.button_label}
                        </Link>
                    )}
                </div>
            </div>
            {slides.length > 1 && (
                <>
                    <button
                        type="button"
                        className="tp-outlet-prev"
                        aria-label="Previous campaign"
                        onClick={() => change(-1)}
                    >
                        <ChevronLeft size={18} />
                    </button>
                    <button
                        type="button"
                        className="tp-outlet-next"
                        aria-label="Next campaign"
                        onClick={() => change(1)}
                    >
                        <ChevronRight size={18} />
                    </button>
                    <div className="tp-outlet-dots">
                        {slides.map((slide, i) => (
                            <button
                                key={slide.key}
                                type="button"
                                aria-label={`Campaign ${i + 1}`}
                                aria-current={index === i ? 'true' : undefined}
                                onClick={() => setIndex(i)}
                            />
                        ))}
                    </div>
                </>
            )}
        </section>
    );
}

/** Whether a scrolling row has more than fits (its arrows are pointless otherwise). */
function useOverflow(track: React.RefObject<HTMLDivElement | null>) {
    const [overflows, setOverflows] = useState(false);
    useEffect(() => {
        const element = track.current;
        if (!element) return;
        const measure = () =>
            setOverflows(element.scrollWidth > element.clientWidth + 1);
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(element);
        return () => observer.disconnect();
    }, [track]);
    return overflows;
}

function Tiles({ section }: { section: HomeSection }) {
    const track = useRef<HTMLDivElement>(null);
    const overflows = useOverflow(track);
    const settings = section.settings;
    const style = String(settings.style || 'overlay');
    const tiles = section.data.tiles ?? [];
    if (!tiles.length) return null;
    return (
        <section
            className="tp-home-section tp-outlet-tiles"
            data-style={style}
            style={
                {
                    '--tp-tile-columns': Number(settings.columns || 4),
                    '--tp-tile-height': `${settings.image_height || 220}px`,
                    '--tp-tile-mobile-height': `${settings.mobile_image_height || 170}px`,
                } as CSSProperties
            }
            aria-label={String(settings.title || 'Explore collections')}
        >
            {settings.title && (
                <h2 className="tp-outlet-heading">{settings.title}</h2>
            )}
            <div className="tp-tile-wrap">
                <div
                    ref={track}
                    className="tp-tile-track"
                    data-wrap={settings.wrap === true}
                    tabIndex={settings.wrap === true ? undefined : 0}
                >
                    {tiles.map((tile, i) => (
                        <Link
                            className="tp-outlet-tile"
                            key={i}
                            href={tile.link || '/search'}
                        >
                            {tile.image && (
                                <img src={tile.image} alt="" loading="lazy" />
                            )}
                            <div>
                                <h3>{tile.title}</h3>
                                {tile.text && <p>{tile.text}</p>}
                                {style === 'overlay' && (
                                    <span aria-hidden="true">→</span>
                                )}
                            </div>
                        </Link>
                    ))}
                </div>
                {settings.wrap !== true && overflows && (
                    <div className="tp-tile-controls">
                        <button
                            type="button"
                            aria-label={`Scroll ${settings.title || 'collections'} backward`}
                            onClick={() =>
                                track.current?.scrollBy({
                                    left: -(track.current.clientWidth * 0.8),
                                    behavior: 'smooth',
                                })
                            }
                        >
                            <ChevronLeft size={16} />
                        </button>
                        <button
                            type="button"
                            aria-label={`Scroll ${settings.title || 'collections'} forward`}
                            onClick={() =>
                                track.current?.scrollBy({
                                    left: track.current.clientWidth * 0.8,
                                    behavior: 'smooth',
                                })
                            }
                        >
                            <ChevronRight size={16} />
                        </button>
                    </div>
                )}
            </div>
        </section>
    );
}

function SizeShopping() {
    const { text, show } = useTempo();
    if (!show('show_sizes')) return null;
    return (
        <section
            className="tp-home-section tp-size-shopping"
            aria-label="Shop by size"
        >
            <div>
                <p className="tp-eyebrow">{text('sizes_eyebrow')}</p>
                <h2>{text('sizes_heading')}</h2>
            </div>
            <nav aria-label="Size collections">
                {['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL'].map((size) => (
                    <Link
                        key={size}
                        href={text(
                            `size_${size.toLowerCase()}_link`,
                            '/search',
                        )}
                    >
                        {size}
                    </Link>
                ))}
            </nav>
        </section>
    );
}

export default function TempoIndex({ sections, slots }: HomeProps) {
    const { storefront, text, show } = useTempo();
    const slides = sections.filter((section) => section.type === 'hero');
    // A second untitled wrapped grid continues the preceding collection grid.
    const content: HomeSection[] = [];
    for (const section of sections.filter(
        (section) => section.type !== 'hero',
    )) {
        const last = content[content.length - 1];
        if (
            section.type === 'promotion_grid' &&
            !section.settings.title &&
            section.settings.wrap &&
            last?.type === 'promotion_grid' &&
            last.settings.wrap &&
            last.settings.style === section.settings.style
        ) {
            content[content.length - 1] = {
                ...last,
                data: {
                    ...last.data,
                    tiles: [
                        ...(last.data.tiles ?? []),
                        ...(section.data.tiles ?? []),
                    ],
                },
            };
        } else content.push(section);
    }
    const firstBanner = content.findIndex(
        (section) => section.type === 'promo_banner',
    );
    return (
        <div className="tp-home">
            <Head
                title={storefront?.seoTitle || storefront?.storeName || 'Tempo'}
            />
            <Carousel slides={slides} />
            {show('show_departments') && (
                <div
                    className="tp-departments"
                    style={{
                        background: text('department_background', '#f3e8c3'),
                    }}
                >
                    <nav className="tp-frame" aria-label="Departments">
                        {[1, 2, 3, 4, 5, 6].map(
                            (i) =>
                                text(`department_${i}_label`) && (
                                    <Link
                                        key={i}
                                        href={text(
                                            `department_${i}_link`,
                                            '/search',
                                        )}
                                    >
                                        {text(`department_${i}_label`)}
                                    </Link>
                                ),
                        )}
                    </nav>
                </div>
            )}
            <div className="tp-frame">
                {content.map((section, i) => (
                    <div key={section.key}>
                        {i === firstBanner && <SizeShopping />}
                        {section.type === 'promotion_grid' ? (
                            <Tiles section={section} />
                        ) : section.type === 'featured_products' ? (
                            <TempoRail
                                title={String(
                                    section.settings.title ||
                                        'Recommended for you',
                                )}
                                products={section.data.products ?? []}
                            />
                        ) : section.type === 'promo_banner' ? (
                            <section
                                className="tp-home-section tp-outlet-banner"
                                data-tone={section.settings.tone}
                            >
                                <div>
                                    <h2>{section.settings.text}</h2>
                                    {section.settings.subtext && (
                                        <p>{section.settings.subtext}</p>
                                    )}
                                </div>
                                {section.settings.button_label && (
                                    <Link
                                        className="store-button tp-button tp-button-primary"
                                        href={String(
                                            section.settings.button_link ||
                                                '/search',
                                        )}
                                    >
                                        {section.settings.button_label}
                                    </Link>
                                )}
                            </section>
                        ) : (
                            <HomeSections sections={[section]} />
                        )}
                    </div>
                ))}
                {firstBanner === -1 && <SizeShopping />}
                <ExtensionSlot items={slots} className="tp-home-extensions" />
            </div>
        </div>
    );
}
