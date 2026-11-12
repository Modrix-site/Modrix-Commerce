import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { Frame } from '../section-adapters';

export default function AppsSection({
    page,
}: ThemeSectionProps<
    Record<string, unknown>,
    { slots?: SlotItem[] } | undefined
>) {
    return (
        <Frame>
            <ExtensionSlot items={page?.slots} className="ci-home-extensions" />
        </Frame>
    );
}
