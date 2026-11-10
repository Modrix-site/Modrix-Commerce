import { Suspense } from 'react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import type { ThemeProductProps } from '@/lib/theme-pages';
import { CircuitSection } from '../components';
import CircuitProduct from '../product';
export default function MainProductSection({
    section,
    page,
}: ThemeSectionProps<Record<string, unknown>, ThemeProductProps>) {
    return (
        <CircuitSection section={section}>
            {page?.product ? (
                <Suspense fallback={null}>
                    <CircuitProduct {...page} />
                </Suspense>
            ) : null}
        </CircuitSection>
    );
}
