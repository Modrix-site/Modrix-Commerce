import { Head, usePage } from '@inertiajs/react';
import { themeIndex } from '@/lib/theme-pages';
import { ThemeTemplate } from '@/components/storefront/theme-sections';
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
    const CustomIndex = storefront?.theme.frontend?.index
        ? themeIndex(storefront.theme.name)
        : undefined;
    if (storefront?.themeSections)
        return (
            <>
                <Head
                    title={
                        storefront.seoTitle || storefront.storeName || t('Home')
                    }
                />
                <ThemeTemplate page={{ slots }} />
            </>
        );
    if (CustomIndex) return <CustomIndex sections={sections} slots={slots} />;

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
