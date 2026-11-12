import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import type { ListingProps } from '@/pages/storefront/listing';
import { CircuitSection } from '../components';
import CircuitCatalog from '../catalog';
export default function MainCollectionSection({
    section,
    page,
}: ThemeSectionProps<Record<string, unknown>, ListingProps>) {
    return (
        <CircuitSection section={section}>
            {page?.products ? <CircuitCatalog {...page} /> : null}
        </CircuitSection>
    );
}
