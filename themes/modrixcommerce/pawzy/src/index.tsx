import { PetCompare } from './compare';
import { Countdown } from './countdown';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    PawPrint,
    RotateCcw,
    ShieldCheck,
    Truck,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import {
    HomeSections,
    type HomeSection,
} from '@/components/storefront/home-sections';
import { ProductGrid } from '@/components/storefront/product-card';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { HomeProps } from '@/pages/storefront/home';

function Section({ section, first }: { section: HomeSection; first: boolean }) {
    const { storefront } = usePage().props;
    const s = section.settings;
    const text = (key: string) => String(s[key] ?? '');
    const isHero = section.type === 'campaign' || section.type === 'hero';
    if (
        isHero &&
        !first &&
        storefront?.theme.settings?.show_comparison !== false &&
        storefront?.theme.settings?.comparison_before &&
        storefront?.theme.settings?.comparison_after
    )
        return (
            <PetCompare
                heading={text('heading')}
                before={String(storefront.theme.settings.comparison_before)}
                after={String(storefront.theme.settings.comparison_after)}
            />
        );
    if (isHero) {
        const hero = storefront?.theme.settings ?? {};
        const setting = (key: string, fallback: string) => {
            const value = hero[key];
            return typeof value === 'string' && value !== '' ? value : fallback;
        };
        const eyebrow = first
            ? setting('hero_eyebrow', text('eyebrow') || 'For your favourite companion')
            : text('eyebrow') || 'For your favourite companion';
        const heading = first ? setting('hero_heading', text('heading')) : text('heading');
        const body = first ? setting('hero_body', text('body')) : text('body');
        const buttonLabel = first
            ? setting('hero_button_label', text('button_label'))
            : text('button_label');
        const buttonLink = first
            ? setting('hero_button_link', text('button_link') || '/search')
            : text('button_link') || '/search';
        const secondLabel = first ? setting('hero_button_2_label', '') : '';
        const secondLink = first ? setting('hero_button_2_link', '/search') : '/search';
        const image = first
            ? setting('hero_image', section.data.image ?? '')
            : section.data.image;
        const background = first
            ? setting('hero_background', text('background') || '#ef90b5')
            : text('background') || '#ef90b5';
        const foreground = first
            ? setting('hero_foreground', text('foreground') || '#263344')
            : text('foreground') || '#263344';
        if (first && hero.hero_show === false) return null;
        return (
            <section
                className={`pawzy-campaign ${first ? 'pawzy-hero' : ''}`}
                data-position={String(hero.hero_text_position ?? 'left')}
                data-valign={first ? String(hero.hero_valign ?? 'center') : 'center'}
                data-image={first ? String(hero.hero_image_layout ?? 'side') : 'side'}
                data-bleed={first && hero.hero_full_bleed === true ? 'true' : 'false'}
                style={
                    {
                        backgroundColor: background,
                        '--pawzy-campaign-background': background,
                        color: foreground,
                    } as CSSProperties
                }
            >
                {image && (
                    <img
                        src={image}
                        alt=""
                        fetchPriority={first ? 'high' : 'auto'}
                    />
                )}
                <div>
                    {eyebrow && <p className="pawzy-eyebrow">{eyebrow}</p>}
                    {first ? <h1>{heading}</h1> : <h2>{heading}</h2>}
                    {body && <p>{body}</p>}
                    {(buttonLabel || secondLabel) && (
                        <div className="pawzy-hero-actions">
                            {buttonLabel && (
                                <Link
                                    className="pawzy-button store-button"
                                    href={buttonLink}
                                >
                                    {buttonLabel} <ArrowRight size={15} />
                                </Link>
                            )}
                            {secondLabel && (
                                <Link
                                    className="pawzy-button pawzy-button-secondary"
                                    href={secondLink}
                                >
                                    {secondLabel}
                                </Link>
                            )}
                        </div>
                    )}
                </div>
            </section>
        );
    }
    if (section.type === 'featured_products')
        return (
            <section className="pawzy-featured">
                <header className="pawzy-section-heading">
                    <p>Our products</p>
                    <h2>{text('title')}</h2>
                </header>
                <ProductGrid products={section.data.products ?? []} />
                {!section.data.products?.length && (
                    <p className="pawzy-empty">
                        Your collection is ready for products. Import Pawzy's
                        demo catalog in Appearance, or add your own products.
                    </p>
                )}
            </section>
        );
    if (section.type === 'promotion_grid')
        return (
            <section className="pawzy-tiles-section" data-style={text('style')}>
                {text('title') && (
                    <header className="pawzy-section-heading">
                        <p>For every little companion</p>
                        <h2>{text('title')}</h2>
                    </header>
                )}
                <div
                    className="pawzy-tiles"
                    style={
                        {
                            '--pawzy-tile-columns': Number(s.columns ?? 3),
                        } as CSSProperties
                    }
                >
                    {section.data.tiles?.map((tile, i) => (
                        <Link
                            href={tile.link || '/search'}
                            key={i}
                            className="pawzy-tile"
                        >
                            {tile.image ? (
                                <img src={tile.image} alt="" loading="lazy" />
                            ) : (
                                (() => {
                                    const Icon = [
                                        RotateCcw,
                                        Truck,
                                        ShieldCheck,
                                        PawPrint,
                                    ][i % 4];
                                    return (
                                        <Icon size={24} aria-hidden="true" />
                                    );
                                })()
                            )}
                            <div>
                                <h3>{tile.title}</h3>
                                {tile.text && <p>{tile.text}</p>}
                            </div>
                            {text('style') === 'card' && (
                                <ArrowRight
                                    className="pawzy-tile-arrow"
                                    size={20}
                                    aria-hidden="true"
                                />
                            )}
                        </Link>
                    ))}
                </div>
            </section>
        );
    if (section.type === 'image_with_text')
        return (
            <section
                className="pawzy-image-text"
                data-position={text('image_position')}
            >
                {section.data.image && (
                    <img src={section.data.image} alt="" loading="lazy" />
                )}
                <div>
                    <p className="pawzy-eyebrow">Happy pets, happy homes</p>
                    <h2>{text('heading')}</h2>
                    <p className="pawzy-copy">{text('body')}</p>
                    {text('button_label') && (
                        <Link
                            className="pawzy-button store-button"
                            href={text('button_link') || '/search'}
                        >
                            {text('button_label')}
                        </Link>
                    )}
                </div>
            </section>
        );
    if (section.type === 'promo_banner')
        return (
            <section className="pawzy-promo">
                <h2>{text('text')}</h2>
                {storefront?.theme.settings?.promotion_end && (
                    <Countdown
                        end={String(storefront.theme.settings.promotion_end)}
                    />
                )}
                <Link
                    href={text('button_link') || '/search'}
                    className="pawzy-button store-button"
                >
                    {text('button_label')}
                </Link>
            </section>
        );
    return <HomeSections sections={[section]} />;
}

export default function PawzyHome({ sections, slots }: HomeProps) {
    const { storefront } = usePage().props;
    const s = storefront?.theme.settings ?? {};
    return (
        <>
            <Head
                title={storefront?.seoTitle || storefront?.storeName || 'Pawzy'}
            />
            <div className="pawzy-home">
                {sections.map((section, i) => (
                    <Section
                        section={section}
                        first={i === 0}
                        key={section.key}
                    />
                ))}
                <ExtensionSlot items={slots} />
                {s.show_faq !== false && (
                    <section className="pawzy-faq">
                        <header className="pawzy-section-heading">
                            <p>Good to know</p>
                            <h2>
                                {String(
                                    s.faq_title ?? 'Frequently Asked Questions',
                                )}
                            </h2>
                        </header>
                        {[1, 2, 3, 4, 5].map(
                            (i) =>
                                s[`faq_${i}_question`] && (
                                    <details key={i}>
                                        <summary>
                                            {String(s[`faq_${i}_question`])}
                                        </summary>
                                        <p>
                                            {String(s[`faq_${i}_answer`] ?? '')}
                                        </p>
                                    </details>
                                ),
                        )}
                    </section>
                )}
            </div>
        </>
    );
}
