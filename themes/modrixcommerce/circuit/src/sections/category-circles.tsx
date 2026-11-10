import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import { Circles } from '../index';
import { Frame, legacyTiles } from '../section-adapters';
export default function CategoryCirclesSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <Frame>
                <Circles section={legacyTiles(section)} />
            </Frame>
        </CircuitSection>
    );
}
