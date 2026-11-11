import type { CSSProperties } from 'react';
import { Link } from '@inertiajs/react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { blockAttributes } from '@/components/storefront/theme-sections';
import { str, assetUrl } from '../components';

export default function CuratedGifts({ section }: ThemeSectionProps) {
    const s = section.settings;
    const cols = Number(s.columns) || 4;

    return (
        <section className="at-section">
            <div className="at-frame">
                {str(s.heading) && (
                    <div className="at-section-head">
                        <h2>{str(s.heading, 'The gift edit')}</h2>
                    </div>
                )}

                <div
                    className="at-gifts-grid"
                    style={{ '--at-gift-cols': cols } as CSSProperties}
                >
                    {section.blocks.map((b) => (
                        <Link
                            key={b.id}
                            href={str(b.settings.link, '/search')}
                            className="at-gift-card"
                            {...blockAttributes(b)}
                        >
                            <div className="at-gift-photo">
                                {str(b.settings.image) && (
                                    <img
                                        src={assetUrl(b.settings.image)}
                                        alt={str(
                                            b.settings.alt,
                                            str(b.settings.label),
                                        )}
                                        loading="lazy"
                                    />
                                )}
                            </div>
                            <span className="at-gift-label">
                                {str(b.settings.label)}
                            </span>
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}
