import { usePage } from '@inertiajs/react';
import { usePlaceItems } from '@/components/extensions/place-slot';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Newsletter({ section }: ThemeSectionProps) {
    const { storefront } = usePage().props;
    const placed = usePlaceItems('storefront.footer');
    const items = placed.length ? placed : (storefront?.footerSlots ?? []);
    if (!items.length) return null;
    return (
        <section className="bl-newsletter">
            <div className="bl-frame bl-newsletter-inner">
                <div>
                    <h2>{str(section.settings.heading)}</h2>
                    <p>{str(section.settings.body)}</p>
                </div>
                <ExtensionSlot items={items} />
            </div>
        </section>
    );
}
