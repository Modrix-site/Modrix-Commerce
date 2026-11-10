import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import { Services } from '../index';
import { Frame } from '../section-adapters';
export default function ServicesSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <Frame>
                <Services />
            </Frame>
        </CircuitSection>
    );
}
