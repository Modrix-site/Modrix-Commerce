import { Head } from '@inertiajs/react';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { HomeProps } from '@/pages/storefront/home';
import { VellaroRail, useVellaro } from './components';
import './style.css';

export default function VellaroIndex({ sections, slots }: HomeProps) {
    const { storefront, text, show } = useVellaro();
    const rows = sections.filter(
        (section) => section.type === 'featured_products',
    );
    const campaign = (i: number) =>
        show(`banner_${i}_show`) && (
            <section
                className="vellaro-campaign"
                key={`campaign-${i}`}
                style={{
                    minHeight: `${text('banner_height', '288')}px`,
                    backgroundImage: `linear-gradient(90deg,rgba(255,255,255,${Number(text('banner_overlay', '95')) / 100}),rgba(255,255,255,.6),transparent),url("${text(`banner_${i}_image`)}")`,
                }}
            >
                <div>
                    {i === 1 ? (
                        <h1>{text(`banner_${i}_title`)}</h1>
                    ) : (
                        <h2>{text(`banner_${i}_title`)}</h2>
                    )}
                    <p>{text(`banner_${i}_text`)}</p>
                    <Link
                        className="vellaro-primary"
                        href={text(`banner_${i}_link`, '/search')}
                    >
                        {text(`banner_${i}_button`, 'Explore collection')}
                    </Link>
                </div>
            </section>
        );
    const rail = (i: number) =>
        rows[i] && (
            <VellaroRail
                key={`rail-${i}`}
                title={String(rows[i].settings.title)}
                products={rows[i].data.products ?? []}
            />
        );
    return (
        <>
            <Head
                title={
                    storefront?.seoTitle || storefront?.storeName || 'Vellaro'
                }
            />
            <div className="vellaro-home vellaro-wrap">
                {campaign(1)}
                {rail(0)}
                {campaign(2)}
                {rail(1)}
                {campaign(3)}
                {rail(2)}
                {show('show_collections') && (
                    <section className="vellaro-collections">
                        <h2>{text('collections_heading')}</h2>
                        <div>
                            {[1, 2, 3, 4].map((i) => (
                                <Link
                                    key={i}
                                    href={text(
                                        `collection_${i}_link`,
                                        '/search',
                                    )}
                                >
                                    <img
                                        src={text(`collection_${i}_image`)}
                                        alt={text(`collection_${i}_title`)}
                                        loading="lazy"
                                    />
                                    <span>{text(`collection_${i}_title`)}</span>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
                {campaign(4)}
                {rail(3)}
                {campaign(5)}
                {rail(4)}
                {campaign(6)}
                <ExtensionSlot
                    items={slots.filter(
                        (item) => !item.component.includes('newsletter'),
                    )}
                />
            </div>
        </>
    );
}
