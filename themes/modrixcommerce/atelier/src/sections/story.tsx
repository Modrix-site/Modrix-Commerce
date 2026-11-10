import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str, assetUrl } from '../components';

export default function Story({ section }: ThemeSectionProps) {
    const s = section.settings;
    const isImageRight = s.image_position === 'right';

    return (
        <section
            className="at-section"
            style={{ backgroundColor: str(s.background, '#f3f3f3') }}
        >
            <div className="at-frame">
                <div className="at-story">
                    {!isImageRight && (
                        <div className="overflow-hidden h-full min-h-[460px]">
                            {str(s.image) && (
                                <img
                                    src={assetUrl(s.image)}
                                    alt={str(s.alt, 'Story image')}
                                    className="at-story-image"
                                    loading="lazy"
                                />
                            )}
                        </div>
                    )}

                    <div className="at-story-copy">
                        {str(s.eyebrow) && (
                            <p className="at-eyebrow">{str(s.eyebrow)}</p>
                        )}
                        <h2>{str(s.heading, 'Companions for every journey')}</h2>
                        <p>{str(s.text)}</p>
                        <Link
                            href={str(s.link, '/search')}
                            className="at-btn at-btn-primary bg-black text-white hover:bg-neutral-800"
                        >
                            <span>{str(s.button, 'Discover more')}</span>
                            <ArrowRight size={15} className="ml-2 inline" />
                        </Link>
                    </div>

                    {isImageRight && (
                        <div className="overflow-hidden h-full min-h-[460px]">
                            {str(s.image) && (
                                <img
                                    src={assetUrl(s.image)}
                                    alt={str(s.alt, 'Story image')}
                                    className="at-story-image"
                                    loading="lazy"
                                />
                            )}
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}
