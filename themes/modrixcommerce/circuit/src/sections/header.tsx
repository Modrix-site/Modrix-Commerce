import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import CircuitHeader from '../header';
import { str } from '../section-adapters';
export default function HeaderSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <CircuitHeader
                announcements={section.blocks
                    .filter((block) => block.type === 'announcement')
                    .map((block) => str(block.settings.text))}
            />
        </CircuitSection>
    );
}
