import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
export default function Apps({
    page,
}: ThemeSectionProps<
    Record<string, unknown>,
    { slots?: SlotItem[]; product?: unknown; cart?: unknown }
>) {
    if (
        page &&
        (Object.prototype.hasOwnProperty.call(page, 'product') ||
            Object.prototype.hasOwnProperty.call(page, 'cart'))
    )
        return null;
    return (
        <div className="re-frame">
            <ExtensionSlot items={page?.slots} />
        </div>
    );
}
