import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import { Picks } from '../index';
import { Frame, legacyProducts } from '../section-adapters';
export default function PopularPicksSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <Frame>
                <Picks section={legacyProducts(section)} />
            </Frame>
        </CircuitSection>
    );
}
