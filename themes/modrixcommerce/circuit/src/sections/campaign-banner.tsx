import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import { CampaignBanner } from '../section-adapters';
export default function CampaignBannerSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <CampaignBanner section={section} />
        </CircuitSection>
    );
}
