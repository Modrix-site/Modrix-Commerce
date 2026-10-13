import { Head, usePage } from '@inertiajs/react';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import { HomeSections } from '@/components/storefront/home-sections';
import type { HomeSection } from '@/components/storefront/home-sections';
import { useT } from '@/lib/i18n';

type Props = {
    sections: HomeSection[];
    slots: SlotItem[];
};

/**
 * The home page is whatever sections the active theme (with the merchant's
 * changes) lists, followed by content placed by modules.
 */
export default function Home({ sections, slots }: Props) {
    const { storefront } = usePage().props;
    const t = useT();

    return (
        <>
            <Head title={storefront?.storeName ?? t('Home')} />
            <HomeSections sections={sections} />
            <ExtensionSlot items={slots} className="grid gap-8 py-10" />
        </>
    );
}
