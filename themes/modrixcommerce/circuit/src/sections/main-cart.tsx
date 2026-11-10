import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import type { CartProps } from '@/pages/storefront/cart';
import { CircuitSection } from '../components';
import CircuitCart from '../cart';
export default function MainCartSection({
    section,
    page,
}: ThemeSectionProps<Record<string, unknown>, CartProps>) {
    return (
        <CircuitSection section={section}>
            {page ? <CircuitCart {...page} /> : null}
        </CircuitSection>
    );
}
