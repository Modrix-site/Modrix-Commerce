import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import { Support } from '../index';
export default function SupportSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <Support />
        </CircuitSection>
    );
}
