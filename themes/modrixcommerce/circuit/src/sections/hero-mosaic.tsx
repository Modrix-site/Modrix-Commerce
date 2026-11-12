import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import { HeroMosaic } from '../index';
import { legacy } from '../section-adapters';
export default function HeroMosaicSection({ section }: ThemeSectionProps) {
    const campaigns = section.blocks.map((block) =>
        legacy(
            {
                id: block.id,
                settings: { ...block.settings, height: block.settings.size },
            },
            'campaign',
            { image: block.settings.image },
        ),
    );
    return (
        <CircuitSection section={section}>
            <div className="ci-home-top">
                <div className="ci-frame">
                    {campaigns.length > 0 && (
                        <HeroMosaic campaigns={campaigns} />
                    )}
                </div>
            </div>
        </CircuitSection>
    );
}
