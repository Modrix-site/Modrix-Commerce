import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
export default function Apps({
    page,
}: ThemeSectionProps<
    Record<string, unknown>,
    { slots?: SlotItem[]; product?: unknown }
>) {
    // The product page renders its modules itself, right after the description.
    if (page && Object.prototype.hasOwnProperty.call(page, 'product'))
        return null;
    return (
        <div className="bl-frame">
            <ExtensionSlot items={page?.slots} />
        </div>
    );
}
