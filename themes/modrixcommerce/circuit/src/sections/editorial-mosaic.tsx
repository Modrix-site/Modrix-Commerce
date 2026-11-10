import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import { Mosaic } from '../index';
import { Frame, legacyTiles } from '../section-adapters';
export default function EditorialMosaicSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <Frame>
                <Mosaic section={legacyTiles(section)} />
            </Frame>
        </CircuitSection>
    );
}
