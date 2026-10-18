import { Head, usePage } from '@inertiajs/react';
import { HomeSections } from '@/components/storefront/home-sections';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { HomeProps } from '@/pages/storefront/home';
import './style.css';

export default function MarketplaceIndex({ sections, slots }: HomeProps) {
    const { storefront } = usePage().props;
    const settings = storefront?.theme.settings ?? {};
    return (
        <>
            <Head
                title={
                    storefront?.seoTitle ||
                    storefront?.storeName ||
                    'Marketplace'
                }
            />
            <div
                className="marketplace-index"
                style={
                    {
                        '--marketplace-home-gap': `${Number(settings.home_section_gap ?? 20)}px`,
                    } as import('react').CSSProperties
                }
            >
                <HomeSections sections={sections} />
            </div>
            {settings.home_show_extensions !== false && (
                <ExtensionSlot items={slots} className="grid gap-8 py-10" />
            )}
        </>
    );
}
