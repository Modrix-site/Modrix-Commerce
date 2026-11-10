import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import CircuitFooter from '../footer';
export default function FooterSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <CircuitFooter />
        </CircuitSection>
    );
}
