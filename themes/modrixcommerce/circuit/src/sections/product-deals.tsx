import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import { Deals } from '../index';
import { Frame, legacyProducts } from '../section-adapters';
export default function ProductDealsSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <Frame>
                <Deals
                    section={legacyProducts(section)}
                    countdown={section.settings.countdown === true}
                />
            </Frame>
        </CircuitSection>
    );
}
