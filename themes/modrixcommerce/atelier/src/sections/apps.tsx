import {
    ExtensionSlot,
    type SlotItem,
} from '@/components/extensions/extension-slot';
import { PlaceSlot } from '@/components/extensions/place-slot';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str } from '../components';

export default function Apps({
    section,
    page,
}: ThemeSectionProps<Record<string, unknown>, { slots?: SlotItem[] }>) {
    const s = section.settings;
    return (
        <div className="at-frame at-section">
            {str(s.heading) && (
                <div className="at-section-head">
                    <h2>{str(s.heading)}</h2>
                </div>
            )}
            <ExtensionSlot items={page?.slots} className="grid gap-8" />
            <PlaceSlot place="apps" />
        </div>
    );
}
