import { Head } from '@inertiajs/react';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { HomeProps } from '@/pages/storefront/home';
import { HearthRail, useHearth } from './components';
import './style.css';
export default function HearthIndex({ sections, slots }: HomeProps) {
    const { storefront, text, show } = useHearth();
    const featured = sections.filter(
        (section) => section.type === 'featured_products',
    );
    return (
        <>
            <Head
                title={
                    storefront?.seoTitle || storefront?.storeName || 'Hearth'
                }
            />
            {show('show_hero') && (
                <section
                    className="hearth-hero"
                    data-position={text('hero_position', 'left')}
                    style={{
                        backgroundImage: `linear-gradient(90deg,rgba(0,0,0,${Number(text('hero_overlay', '30')) / 100}),transparent),url("${text('hero_image')}")`,
                        minHeight: `${text('hero_height', '700')}px`,
                    }}
                >
                    <div>
                        <h1>{text('hero_heading')}</h1>
                        <p>{text('hero_text')}</p>
                        <Link
                            className="hearth-outline"
                            href={text('hero_link', '/search')}
                        >
                            {text('hero_button', 'MORE')}
                        </Link>
                    </div>
                </section>
            )}
            {show('show_categories') && (
                <section className="hearth-wrap hearth-categories">
                    <h2>{text('categories_heading')}</h2>
                    <div>
                        {Array.from({ length: 7 }, (_, i) => {
                            const label = text(`category_${i + 1}_label`);
                            return (
                                <Link
                                    key={i}
                                    href={text(
                                        `category_${i + 1}_link`,
                                        '/search',
                                    )}
                                >
                                    <img
                                        src={text(`category_${i + 1}_image`)}
                                        alt=""
                                        loading="lazy"
                                    />
                                    <span>{label}</span>
                                </Link>
                            );
                        })}
                    </div>
                    <Link className="hearth-primary" href="/search">
                        TO CATALOGUE
                    </Link>
                </section>
            )}
            {show('show_shortcuts') && (
                <section className="hearth-wrap hearth-shortcuts">
                    {[1, 2, 3].map((i) => (
                        <Link
                            key={i}
                            href={text(`shortcut_${i}_link`, '/search')}
                        >
                            <img
                                src={text(`shortcut_${i}_image`)}
                                alt=""
                                loading="lazy"
                            />
                            <h2>{text(`shortcut_${i}_title`)}</h2>
                        </Link>
                    ))}
                </section>
            )}
            {show('show_collection') && (
                <section className="hearth-wrap hearth-collection">
                    <Link href={text('collection_link', '/search')}>
                        <img
                            src={text('collection_image')}
                            alt={text('collection_heading')}
                            loading="lazy"
                        />
                    </Link>
                    <div>
                        <h2>{text('collection_heading')}</h2>
                        <p>{text('collection_text')}</p>
                        <Link href={text('collection_link', '/search')}>
                            EXPLORE COLLECTION
                        </Link>
                    </div>
                </section>
            )}
            {show('show_trust') && (
                <section className="hearth-wrap hearth-trust">
                    {[1, 2, 3, 4].map((i) => (
                        <div key={i}>
                            <span aria-hidden="true">◇</span>
                            <p>{text(`trust_${i}`)}</p>
                        </div>
                    ))}
                </section>
            )}
            <HearthRail
                title={String(featured[0]?.settings.title || 'Trending')}
                products={featured[0]?.data.products ?? []}
            />
            {show('show_promotions') && (
                <section className="hearth-wrap hearth-promotions">
                    {[1, 2, 3, 4].map((i) => (
                        <Link
                            key={i}
                            href={text(`promo_${i}_link`, '/search')}
                            style={{
                                backgroundColor: text(`promo_${i}_color`),
                                backgroundImage: text(`promo_${i}_image`)
                                    ? `linear-gradient(#0003,#0003),url("${text(`promo_${i}_image`)}")`
                                    : undefined,
                            }}
                            data-light={i === 2 && !text(`promo_${i}_image`)}
                        >
                            <h2>{text(`promo_${i}_title`)}</h2>
                            <p>{text(`promo_${i}_text`)}</p>
                        </Link>
                    ))}
                </section>
            )}
            <HearthRail
                title={String(featured[1]?.settings.title || 'New in')}
                products={featured[1]?.data.products ?? []}
            />
            {show('show_feature') && (
                <section className="hearth-feature">
                    <img
                        src={text('feature_image')}
                        alt={text('feature_heading')}
                        loading="lazy"
                    />
                    <div>
                        <h2>{text('feature_heading')}</h2>
                        <p>{text('feature_text')}</p>
                        <Link
                            className="hearth-outline"
                            href={text('feature_link', '/search')}
                        >
                            {text('feature_button', 'MORE')}
                        </Link>
                    </div>
                </section>
            )}
            <ExtensionSlot
                items={slots.filter(
                    (item) => !item.component.includes('newsletter'),
                )}
                className="hearth-wrap"
            />
        </>
    );
}
