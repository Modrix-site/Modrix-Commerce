import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import { Brands } from '../index';
import { Frame, legacyTiles } from '../section-adapters';
export default function BrandLogosSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <Frame>
                <Brands section={legacyTiles(section)} />
            </Frame>
        </CircuitSection>
    );
}
