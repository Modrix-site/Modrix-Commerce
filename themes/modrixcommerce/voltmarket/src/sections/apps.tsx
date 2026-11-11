import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
export default function Apps({
    page,
}: ThemeSectionProps<Record<string, unknown>, { slots?: SlotItem[] }>) {
    return (
        <div className="vm-frame">
            <ExtensionSlot items={page?.slots} />
        </div>
    );
}
