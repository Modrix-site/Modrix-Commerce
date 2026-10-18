import { Head } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Headset,
    Mail,
    MessageCircle,
    PackageCheck,
    Phone,
    RotateCcw,
    Ticket,
    Truck,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { CSSProperties } from 'react';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { HomeSections } from '@/components/storefront/home-sections';
import type { HomeSection } from '@/components/storefront/home-sections';
import type { HomeProps } from '@/pages/storefront/home';
import type { ProductCard } from '@/types';
import {
    CircuitCard,
    Countdown,
    ProductRow,
    SectionHeading,
    useCircuit,
} from './components';
import './style.css';

const s = (section: HomeSection, key: string) =>
    String(section.settings[key] ?? '');

function CampaignCopy({
    section,
    shop,
}: {
    section: HomeSection;
    shop: string;
}) {
    return (
        <div className="ci-campaign-copy">
            {s(section, 'eyebrow') && (
                <p className="ci-eyebrow">{s(section, 'eyebrow')}</p>
            )}
            <h2>{s(section, 'heading')}</h2>
            {s(section, 'body') && (
                <p className="ci-campaign-body">{s(section, 'body')}</p>
            )}
            {(s(section, 'button_label') || shop) && (
                <span className="ci-pill ci-pill-light">
                    {s(section, 'button_label') || shop}
                </span>
            )}
        </div>
    );
}

function campaignStyle(section: HomeSection): CSSProperties {
    return {
        background: s(section, 'background') || undefined,
        color: s(section, 'foreground') || undefined,
    };
}

/** The opening mosaic: a slider of large campaigns, a tall tile and stacked small tiles. */
function HeroMosaic({ campaigns }: { campaigns: HomeSection[] }) {
    const { text } = useCircuit();
    const slides = campaigns.filter((c) => s(c, 'height') === 'large');
    const tall = campaigns.filter((c) => s(c, 'height') === 'medium');
    const small = campaigns.filter((c) => s(c, 'height') === 'small');
    const [index, setIndex] = useState(0);
    useEffect(() => {
        if (
            slides.length < 2 ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        )
            return;
        const timer = window.setInterval(
            () => setIndex((i) => (i + 1) % slides.length),
            7000,
        );
        return () => window.clearInterval(timer);
    }, [slides.length]);
    const active = slides[index % Math.max(1, slides.length)];
    const step = (delta: number) =>
        setIndex((i) => (i + delta + slides.length) % slides.length);
    return (
        <section
            className="ci-hero"
            aria-label="Featured offers"
            data-tiles={tall.length + small.length > 0}
        >
            {active && (
                <div
                    className="ci-hero-slide"
                    style={campaignStyle(active)}
                    aria-roledescription="carousel"
                >
                    <Link
                        href={s(active, 'button_link') || '/search'}
                        className="ci-campaign"
                        aria-label={s(active, 'heading')}
                    >
                        {active.data.image && (
                            <img
                                src={active.data.image}
                                alt=""
                                fetchPriority="high"
                            />
                        )}
                        <CampaignCopy section={active} shop="" />
                    </Link>
                    {slides.length > 1 && (
                        <>
                            <button
                                type="button"
                                className="ci-slide-prev"
                                aria-label="Previous offer"
                                onClick={() => step(-1)}
                            >
                                <ChevronLeft size={18} />
                            </button>
                            <button
                                type="button"
                                className="ci-slide-next"
                                aria-label="Next offer"
                                onClick={() => step(1)}
                            >
                                <ChevronRight size={18} />
                            </button>
                            <div className="ci-dots">
                                {slides.map((slide, i) => (
                                    <button
                                        key={slide.key}
                                        type="button"
                                        aria-label={`Offer ${i + 1}`}
                                        aria-current={
                                            i === index % slides.length
                                                ? 'true'
                                                : undefined
                                        }
                                        onClick={() => setIndex(i)}
                                    />
                                ))}
                            </div>
                        </>
                    )}
                </div>
            )}
            {tall.map((tile) => (
                <Link
                    key={tile.key}
                    href={s(tile, 'button_link') || '/search'}
                    className="ci-campaign ci-hero-tall"
                    style={campaignStyle(tile)}
                >
                    {tile.data.image && (
                        <img src={tile.data.image} alt="" loading="lazy" />
                    )}
                    <CampaignCopy
                        section={tile}
                        shop={text('shop_button', 'Shop Now')}
                    />
                </Link>
            ))}
            {small.length > 0 && (
                <div className="ci-hero-stack">
                    {small.map((tile) => (
                        <Link
                            key={tile.key}
                            href={s(tile, 'button_link') || '/search'}
                            className="ci-campaign ci-hero-small"
                            style={campaignStyle(tile)}
                        >
                            {tile.data.image && (
                                <img
                                    src={tile.data.image}
                                    alt=""
                                    loading="lazy"
                                />
                            )}
                            <CampaignCopy section={tile} shop="" />
                        </Link>
                    ))}
                </div>
            )}
        </section>
    );
}

function Circles({ section }: { section: HomeSection }) {
    const tiles = section.data.tiles ?? [];
    if (!tiles.length) return null;
    return (
        <section
            className="ci-section ci-circles"
            aria-label={s(section, 'title') || 'Shop by category'}
        >
            {s(section, 'title') && (
                <SectionHeading title={s(section, 'title')} />
            )}
            <div className="ci-circle-track">
                {tiles.map((tile, i) => (
                    <Link
                        key={i}
                        href={tile.link || '/search'}
                        className="ci-circle"
                        data-tone={i % 3}
                    >
                        <span className="ci-circle-disc">
                            {tile.image ? (
                                <img src={tile.image} alt="" loading="lazy" />
                            ) : (
                                <b>{tile.text || tile.title}</b>
                            )}
                        </span>
                        <span>{tile.title}</span>
                    </Link>
                ))}
            </div>
        </section>
    );
}

function Deals({
    section,
    countdown,
}: {
    section: HomeSection;
    countdown: boolean;
}) {
    const { text } = useCircuit();
    const products = section.data.products ?? [];
    if (!products.length) return null;
    const viewAll =
        s(section, 'source') === 'category' && s(section, 'category')
            ? `/c/${s(section, 'category')}`
            : '/search';
    return (
        <section className="ci-section" aria-label={s(section, 'title')}>
            <SectionHeading
                title={s(section, 'title')}
                action={
                    countdown ? (
                        <Countdown
                            until={text('deals_end')}
                            label={text(
                                'deals_label',
                                'Hurry up! Offer ends in:',
                            )}
                        />
                    ) : (
                        <Link className="ci-view-all" href={viewAll}>
                            View All
                        </Link>
                    )
                }
            />
            <ProductRow
                products={products}
                columns={Math.min(5, Math.max(2, products.length))}
            />
        </section>
    );
}

function Picks({ section }: { section: HomeSection }) {
    const { text } = useCircuit();
    const products: ProductCard[] = section.data.products ?? [];
    const half = Math.ceil(products.length / 2);
    const column = (items: ProductCard[]) => (
        <div className="ci-picks-list">
            {items.map((product) => (
                <CircuitCard
                    key={product.id}
                    product={product}
                    variant="compact"
                />
            ))}
        </div>
    );
    return (
        <section className="ci-section" aria-label={s(section, 'title')}>
            <SectionHeading title={s(section, 'title')} />
            <div className="ci-picks">
                {column(products.slice(0, half))}
                <Link
                    href={text('picks_link', '/search')}
                    className="ci-campaign ci-picks-card"
                >
                    {text('picks_image') && (
                        <img src={text('picks_image')} alt="" loading="lazy" />
                    )}
                    <div className="ci-campaign-copy">
                        {text('picks_eyebrow') && (
                            <p className="ci-eyebrow">
                                {text('picks_eyebrow')}
                            </p>
                        )}
                        <h2>{text('picks_heading')}</h2>
                        {text('picks_text') && (
                            <p className="ci-campaign-body">
                                {text('picks_text')}
                            </p>
                        )}
                        {text('picks_button') && (
                            <span className="ci-pill ci-pill-light">
                                {text('picks_button')}
                            </span>
                        )}
                    </div>
                </Link>
                {column(products.slice(half))}
            </div>
        </section>
    );
}

function Voucher({ section }: { section: HomeSection }) {
    const { text } = useCircuit();
    const [copied, setCopied] = useState(false);
    const code = text('voucher_code');
    const [first, ...rest] = s(section, 'text').split(/(?<=Promotion)\s+/);
    return (
        <section
            className="ci-section ci-voucher"
            aria-label={s(section, 'text')}
        >
            <h2>
                <span>{first}</span>
                {rest.length > 0 && <span>{rest.join(' ')}</span>}
            </h2>
            {s(section, 'subtext') && <p>{s(section, 'subtext')}</p>}
            {code && (
                <button
                    type="button"
                    className="ci-voucher-code"
                    aria-label={`Copy voucher code ${code}`}
                    onClick={() => {
                        navigator.clipboard?.writeText(code).then(
                            () => setCopied(true),
                            () => setCopied(false),
                        );
                    }}
                >
                    <Ticket size={16} aria-hidden /> {copied ? 'Copied!' : code}
                </button>
            )}
            {s(section, 'button_label') && (
                <Link
                    className="ci-pill ci-pill-light"
                    href={s(section, 'button_link') || '/search'}
                >
                    {s(section, 'button_label')}
                </Link>
            )}
        </section>
    );
}

function Mosaic({ section }: { section: HomeSection }) {
    const { text } = useCircuit();
    const tiles = section.data.tiles ?? [];
    if (!tiles.length) return null;
    return (
        <section
            className="ci-section ci-mosaic-band"
            aria-label={s(section, 'title') || 'Offers'}
        >
            {s(section, 'title') && (
                <SectionHeading title={s(section, 'title')} />
            )}
            <div className="ci-mosaic" data-count={tiles.length}>
                {tiles.map((tile, i) => {
                    const big = i === tiles.length - 1 && tiles.length > 2;
                    return (
                        <Link
                            key={i}
                            href={tile.link || '/search'}
                            className="ci-campaign ci-mosaic-tile"
                            data-big={big}
                        >
                            {tile.image && (
                                <img src={tile.image} alt="" loading="lazy" />
                            )}
                            <div className="ci-campaign-copy">
                                {!big && tile.text && (
                                    <p className="ci-eyebrow">{tile.text}</p>
                                )}
                                <h2>{tile.title}</h2>
                                {big && tile.text && (
                                    <p className="ci-campaign-body">
                                        {tile.text}
                                    </p>
                                )}
                                <span
                                    className={
                                        big
                                            ? 'ci-pill ci-pill-light'
                                            : 'ci-link'
                                    }
                                >
                                    {big
                                        ? text('shop_button', 'Shop Now')
                                        : 'View All'}
                                </span>
                            </div>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}

function Brands({ section }: { section: HomeSection }) {
    const tiles = section.data.tiles ?? [];
    if (!tiles.length) return null;
    return (
        <section
            className="ci-section"
            aria-label={s(section, 'title') || 'Brands'}
        >
            <SectionHeading
                title={s(section, 'title') || 'Featured Brands'}
                action={
                    <Link className="ci-view-all" href="/search">
                        View All
                    </Link>
                }
            />
            <div className="ci-brands">
                {tiles.map((tile, i) => (
                    <Link
                        key={i}
                        href={tile.link || '/search'}
                        aria-label={tile.title}
                    >
                        {tile.image ? (
                            <img
                                src={tile.image}
                                alt={tile.title}
                                loading="lazy"
                            />
                        ) : (
                            <span>{tile.title}</span>
                        )}
                    </Link>
                ))}
            </div>
        </section>
    );
}

function Spotlight({ section }: { section: HomeSection }) {
    const { text } = useCircuit();
    return (
        <section
            className="ci-section ci-spotlight"
            data-image={s(section, 'image_position') || 'left'}
            aria-label={s(section, 'heading')}
        >
            {section.data.image && (
                <img src={section.data.image} alt="" loading="lazy" />
            )}
            <div className="ci-spotlight-copy">
                {text('spotlight_eyebrow') && (
                    <p className="ci-eyebrow">{text('spotlight_eyebrow')}</p>
                )}
                <h2>{s(section, 'heading')}</h2>
                {s(section, 'body') && <p>{s(section, 'body')}</p>}
                {s(section, 'button_label') && (
                    <Link
                        className="ci-spotlight-product"
                        href={s(section, 'button_link') || '/search'}
                    >
                        <span>{s(section, 'button_label')}</span>
                        {text('spotlight_price') && (
                            <b>{text('spotlight_price')}</b>
                        )}
                    </Link>
                )}
            </div>
        </section>
    );
}

function Services() {
    const { text, show } = useCircuit();
    if (!show('show_services')) return null;
    const icons = [Truck, RotateCcw, Headset];
    return (
        <section className="ci-section ci-services" aria-label="Our services">
            {[1, 2, 3].map((i) => {
                const Icon = icons[i - 1];
                return text(`service_${i}_title`) ? (
                    <div key={i}>
                        <Icon size={26} aria-hidden />
                        <h3>{text(`service_${i}_title`)}</h3>
                        <p>{text(`service_${i}_text`)}</p>
                    </div>
                ) : null;
            })}
        </section>
    );
}

function Support() {
    const { text, show } = useCircuit();
    if (!show('show_support')) return null;
    const icons = [Phone, MessageCircle, Mail];
    return (
        <section className="ci-support" aria-label={text('support_heading')}>
            <div className="ci-frame">
                {text('support_eyebrow') && (
                    <p className="ci-eyebrow">{text('support_eyebrow')}</p>
                )}
                <h2>{text('support_heading')}</h2>
                <div className="ci-support-cards">
                    {[1, 2, 3].map((i) => {
                        const Icon = icons[i - 1];
                        return text(`support_${i}_title`) ? (
                            <Link
                                key={i}
                                href={text(`support_${i}_link`, '/account')}
                            >
                                <Icon size={22} aria-hidden />
                                <span>
                                    <strong>
                                        {text(`support_${i}_title`)}
                                    </strong>
                                    <small>{text(`support_${i}_text`)}</small>
                                </span>
                            </Link>
                        ) : null;
                    })}
                </div>
                {text('trusted_text') && (
                    <p className="ci-trusted">
                        <PackageCheck size={14} aria-hidden />{' '}
                        {text('trusted_text')}
                    </p>
                )}
            </div>
        </section>
    );
}

export default function CircuitIndex({ sections, slots }: HomeProps) {
    const { storefront, text } = useCircuit();
    const opening: HomeSection[] = [];
    let start = 0;
    while (sections[start]?.type === 'campaign')
        opening.push(sections[start++]);
    const rest = sections.slice(start);
    const firstFeatured = rest.findIndex(
        (section) => section.type === 'featured_products',
    );
    const picksTitle = text('picks_title', 'Popular Picks')
        .trim()
        .toLowerCase();

    return (
        <div className="ci-home">
            <Head
                title={
                    storefront?.seoTitle || storefront?.storeName || 'Circuit'
                }
            />
            <div className="ci-home-top">
                <div className="ci-frame">
                    {opening.length > 0 && <HeroMosaic campaigns={opening} />}
                </div>
            </div>
            <div className="ci-frame">
                {rest.map((section, i) => {
                    const style = s(section, 'style');
                    let content;
                    if (section.type === 'promotion_grid' && style === 'circle')
                        content = <Circles section={section} />;
                    else if (
                        section.type === 'promotion_grid' &&
                        style === 'editorial'
                    )
                        content = <Mosaic section={section} />;
                    else if (
                        section.type === 'promotion_grid' &&
                        style === 'plain'
                    )
                        content = <Brands section={section} />;
                    else if (
                        section.type === 'featured_products' &&
                        s(section, 'title').trim().toLowerCase() === picksTitle
                    )
                        content = <Picks section={section} />;
                    else if (section.type === 'featured_products')
                        content = (
                            <Deals
                                section={section}
                                countdown={i === firstFeatured}
                            />
                        );
                    else if (section.type === 'promo_banner')
                        content = <Voucher section={section} />;
                    else if (section.type === 'image_with_text')
                        content = <Spotlight section={section} />;
                    else if (section.type === 'campaign')
                        content = (
                            <Link
                                href={s(section, 'button_link') || '/search'}
                                className="ci-section ci-campaign ci-campaign-wide"
                                style={campaignStyle(section)}
                            >
                                {section.data.image && (
                                    <img
                                        src={section.data.image}
                                        alt=""
                                        loading="lazy"
                                    />
                                )}
                                <CampaignCopy
                                    section={section}
                                    shop={text('shop_button', 'Shop Now')}
                                />
                            </Link>
                        );
                    else content = <HomeSections sections={[section]} />;
                    return <div key={section.key}>{content}</div>;
                })}
                <Services />
            </div>
            <Support />
            <div className="ci-frame">
                <ExtensionSlot items={slots} className="ci-home-extensions" />
            </div>
        </div>
    );
}
