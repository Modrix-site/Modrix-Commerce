import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str, assetUrl } from '../components';

export default function CampaignBanner({ section }: ThemeSectionProps) {
    const s = section.settings;
    const height = Number(s.height) || 540;
    const overlay = (Number(s.overlay_opacity) || 35) / 100;
    const textColor = str(s.text_color, '#ffffff');

    return (
        <section className="at-campaign-banner" style={{ minHeight: `${height}px` }}>
            {str(s.image) && (
                <img
                    src={assetUrl(s.image)}
                    alt={str(s.alt, 'Campaign banner')}
                    loading="lazy"
                />
            )}
            <div
                className="at-campaign-overlay"
                style={{ opacity: overlay }}
            />
            <div className="at-frame w-full">
                <div className="at-campaign-content" style={{ color: textColor }}>
                    {str(s.eyebrow) && (
                        <p className="at-eyebrow" style={{ color: '#ffffff', opacity: 0.85 }}>
                            {str(s.eyebrow)}
                        </p>
                    )}
                    <h2 style={{ color: textColor }}>
                        {str(s.heading, 'THE WRITING COLLECTION')}
                    </h2>
                    {str(s.text) && (
                        <p>{str(s.text)}</p>
                    )}
                    <Link
                        href={str(s.link, '/search')}
                        className="at-btn bg-white text-neutral-900 border-white hover:bg-neutral-100"
                    >
                        <span>{str(s.button, 'Discover the collection')}</span>
                        <ArrowRight size={15} className="ml-2 inline" />
                    </Link>
                </div>
            </div>
        </section>
    );
}
