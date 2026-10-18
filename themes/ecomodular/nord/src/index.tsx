import { Head } from '@inertiajs/react';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { HomeSections } from '@/components/storefront/home-sections';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import type { HomeProps } from '@/pages/storefront/home';
import { Editorial, NordRail, useNord } from './components';
import './style.css';

export default function NordHome({ sections, slots }: HomeProps) {
    const { storefront, text, show } = useNord();
    const featured = sections.filter(
        (section) => section.type === 'featured_products',
    );
    const extra = sections.filter(
        (section) => section.type !== 'featured_products',
    );
    return (
        <div className="nd-home">
            <Head
                title={storefront?.seoTitle || storefront?.storeName || 'Nord'}
            />
            {show('show_hero') && (
                <section className="nd-hero">
                    <div className="nd-campaign nd-campaign-hero">
                        {text('hero_image') && (
                            <img
                                src={text('hero_image')}
                                alt={text('hero_heading')}
                                fetchPriority="high"
                            />
                        )}
                        <div className="nd-campaign-copy">
                            <h1>{text('hero_heading')}</h1>
                            <Link
                                className="nd-button"
                                href={text('hero_link', '/search')}
                            >
                                {text('hero_button', 'Discover More')}
                            </Link>
                        </div>
                    </div>
                </section>
            )}
            <div className="nd-frame">
                <NordRail
                    title={String(
                        featured[0]?.settings.title || 'Top Picks for You',
                    )}
                    products={featured[0]?.data.products ?? []}
                />
                {show('show_dressing') && (
                    <section className="nd-campaign nd-campaign-editorial">
                        {text('dressing_image_1') && (
                            <img
                                src={text('dressing_image_1')}
                                alt="Fall dressing"
                                loading="lazy"
                            />
                        )}
                        <div className="nd-campaign-copy">
                            <h2>{text('dressing_heading')}</h2>
                            <p>{text('dressing_text')}</p>
                            <div className="nd-campaign-links">
                                <Link
                                    className="nd-button"
                                    href={text('dressing_link', '/search')}
                                >
                                    {text('dressing_button')}
                                </Link>
                                <Link
                                    className="nd-text-link"
                                    href={text(
                                        'dressing_secondary_link',
                                        '/search?sort=newest',
                                    )}
                                >
                                    {text('dressing_secondary')}
                                </Link>
                            </div>
                        </div>
                        {text('dressing_image_2') && (
                            <img
                                src={text('dressing_image_2')}
                                alt="New season styling"
                                loading="lazy"
                            />
                        )}
                    </section>
                )}
                <Editorial section="style" />
                {show('show_runway') && (
                    <section className="nd-campaign">
                        {text('runway_image') && (
                            <img
                                src={text('runway_image')}
                                alt={text('runway_heading')}
                                loading="lazy"
                            />
                        )}
                        <div className="nd-campaign-copy">
                            <h2>{text('runway_heading')}</h2>
                            <p>{text('runway_text')}</p>
                            <Link
                                className="nd-button"
                                href={text('runway_link', '/search')}
                            >
                                {text('runway_button')}
                            </Link>
                        </div>
                    </section>
                )}
                <Editorial section="designer" />
                {show('show_clearance') && (
                    <section className="nd-campaign nd-campaign-clearance">
                        <div
                            className="nd-markdowns"
                            style={{
                                background: text('clearance_color', '#ef373b'),
                            }}
                        >
                            {text('clearance_badge')
                                .split('\n')
                                .map((line, i) => (
                                    <span key={i}>{line}</span>
                                ))}
                        </div>
                        <div className="nd-campaign-copy">
                            <h2>{text('clearance_heading')}</h2>
                            <p>{text('clearance_text')}</p>
                            <Link
                                className="nd-button"
                                href={text('clearance_link', '/search')}
                            >
                                {text('clearance_button')}
                            </Link>
                        </div>
                    </section>
                )}
                <NordRail
                    title={String(
                        featured[1]?.settings.title || 'Top Sale Picks for You',
                    )}
                    products={featured[1]?.data.products ?? []}
                />
                <Editorial section="brands" />
                {featured.slice(2).map((section) => (
                    <NordRail
                        key={section.key}
                        title={String(section.settings.title)}
                        products={section.data.products ?? []}
                    />
                ))}
                {extra.length > 0 && <HomeSections sections={extra} />}
                <ExtensionSlot items={slots} className="nd-extensions" />
            </div>
        </div>
    );
}
