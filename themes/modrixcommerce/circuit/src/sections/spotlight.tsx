import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import { Spotlight } from '../index';
import { Frame, legacy } from '../section-adapters';
export default function SpotlightSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <Frame>
                <Spotlight
                    section={legacy(section, 'image_with_text', {
                        image: section.settings.image,
                    })}
                />
            </Frame>
        </CircuitSection>
    );
}
