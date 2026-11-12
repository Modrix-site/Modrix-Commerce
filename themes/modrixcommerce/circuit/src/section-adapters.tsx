import type { ReactNode } from 'react';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import type { HomeSection } from '@/components/storefront/home-sections';
import type { ThemeSectionData } from '@/components/storefront/theme-sections';
import type { ProductCard } from '@/types';
import { useCircuit } from './components';
import { CampaignCopy, campaignStyle } from './index';
import './style.css';

/**
 * Circuit's home components were written for the platform's home sections;
 * these adapters feed them a section's settings and blocks from the theme
 * editor instead.
 */
export function legacy(
    section: Pick<ThemeSectionData, 'id' | 'settings'>,
    type: string,
    data: Record<string, unknown> = {},
): HomeSection {
    return {
        key: section.id,
        type,
        settings: section.settings,
        data,
    } as unknown as HomeSection;
}

type Collection = { handle: string; products: ProductCard[] } | null;

export function legacyProducts(section: ThemeSectionData): HomeSection {
    const collection = section.settings.collection as Collection;

    return legacy(
        {
            id: section.id,
            settings: {
                ...section.settings,
                source: collection?.handle ? 'category' : 'latest',
                category: collection?.handle ?? '',
            },
        },
        'featured_products',
        { products: collection?.products ?? [] },
    );
}

export function legacyTiles(section: ThemeSectionData): HomeSection {
    return legacy(section, 'promotion_grid', {
        tiles: section.blocks.map((block) => ({
            title: str(block.settings.title),
            text: str(block.settings.text),
            image: (block.settings.image as string | null) ?? null,
            link: str(block.settings.link),
        })),
    });
}

/** A setting value as text ('' for anything that is not a string or number). */
export function str(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : '';
}

/** Circuit's page width. */
export function Frame({ children }: { children: ReactNode }) {
    return <div className="ci-frame">{children}</div>;
}

/** A full-width campaign (the "Campaign banner" section). */
export function CampaignBanner({ section }: { section: ThemeSectionData }) {
    const { text } = useCircuit();
    const home = legacy(section, 'campaign', { image: section.settings.image });

    return (
        <Frame>
            <Link
                href={str(section.settings.button_link) || '/search'}
                className="ci-section ci-campaign ci-campaign-wide"
                style={campaignStyle(home)}
            >
                {home.data.image && (
                    <img src={home.data.image} alt="" loading="lazy" />
                )}
                <CampaignCopy
                    section={home}
                    shop={text('shop_button', 'Shop Now')}
                />
            </Link>
        </Frame>
    );
}
