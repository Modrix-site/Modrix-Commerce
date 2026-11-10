import type { CSSProperties } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str, assetUrl } from '../components';

export default function Hero({ section }: ThemeSectionProps) {
    const s = section.settings;
    const bg = str(s.background, '#eeece7');
    const photoBg = str(s.photo_background, '#dcdada');
    const height = Number(s.height) || 640;
    const headingSize = Number(s.heading_size) || 56;

    return (
        <section
            className="at-hero"
            style={
                {
                    backgroundColor: bg,
                    '--at-hero-min-h': `${height}px`,
                } as CSSProperties
            }
        >
            <div className="at-hero-grid" style={{ minHeight: `${height}px` }}>
                <div className="at-hero-copy">
                    {str(s.eyebrow) && (
                        <p className="at-eyebrow">{str(s.eyebrow)}</p>
                    )}
                    <h1 style={{ fontSize: `clamp(28px, 4vw, ${headingSize}px)` }}>
                        {str(s.heading, 'The art of everyday.')}
                    </h1>
                    {str(s.text) && (
                        <p className="at-hero-desc">{str(s.text)}</p>
                    )}
                    <div>
                        <Link
                            href={str(s.link, '/search')}
                            className="at-btn at-btn-primary bg-black text-white hover:bg-neutral-800"
                        >
                            <span>{str(s.button, 'Discover the collection')}</span>
                            <ArrowRight size={15} className="ml-2 inline" />
                        </Link>
                    </div>
                </div>

                <div
                    className="at-hero-photo-wrap"
                    style={{ backgroundColor: photoBg }}
                >
                    {str(s.image) && (
                        <img
                            src={assetUrl(s.image)}
                            alt={str(s.alt, 'Everyday essentials')}
                            fetchPriority="high"
                        />
                    )}
                </div>
            </div>
        </section>
    );
}
