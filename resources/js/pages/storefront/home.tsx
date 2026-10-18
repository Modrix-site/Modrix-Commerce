import { Head, usePage } from '@inertiajs/react';
import { Suspense } from 'react';
import { themeIndex } from '@/lib/theme-pages';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import { HomeSections } from '@/components/storefront/home-sections';
import type { HomeSection } from '@/components/storefront/home-sections';
import { useT } from '@/lib/i18n';

export type HomeProps = {
    sections: HomeSection[];
    slots: SlotItem[];
};

/**
 * The home page is whatever sections the active theme (with the merchant's
 * changes) lists, followed by content placed by modules.
 */
export default function Home({ sections, slots }: HomeProps) {
    const { storefront } = usePage().props;
    const t = useT();
    const CustomIndex = themeIndex(storefront?.theme.name);
    if (CustomIndex)
        return (
            <Suspense fallback={<p role="status">Loading storefront…</p>}>
                <CustomIndex sections={sections} slots={slots} />
            </Suspense>
        );

    return (
        <>
            <Head
                title={
                    storefront?.seoTitle || storefront?.storeName || t('Home')
                }
            />
            <HomeSections sections={sections} />
            <ExtensionSlot items={slots} className="grid gap-8 py-10" />
        </>
    );
}
