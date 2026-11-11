import type { CSSProperties } from 'react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import { str } from '../utils';
export default function ImageBanner({ section }: ThemeSectionProps) {
    const s = section.settings;
    const Heading = s.primary_heading ? 'h1' : 'h2';
    return (
        <div
            className={
                'sa-banner sa-banner-' +
                str(s.height) +
                (s.inset ? ' sa-inset' : '')
            }
            style={
                {
                    color: str(s.foreground),
                    '--sa-shade': Number(s.overlay) / 100,
                } as CSSProperties
            }
            data-align={str(s.alignment)}
        >
            {str(s.image) && (
                <picture>
                    {str(s.mobile_image) && (
                        <source
                            media="(max-width: 640px)"
                            srcSet={str(s.mobile_image)}
                        />
                    )}
                    <img
                        src={str(s.image)}
                        alt={str(s.alt)}
                        loading={s.height === 'large' ? 'eager' : 'lazy'}
                        fetchPriority={s.height === 'large' ? 'high' : 'auto'}
                    />
                </picture>
            )}
            <div className="sa-banner-copy">
                {str(s.eyebrow) && (
                    <p className="sa-eyebrow">{str(s.eyebrow)}</p>
                )}
                {str(s.heading) && <Heading>{str(s.heading)}</Heading>}
                {str(s.body) && <p className="sa-banner-body">{str(s.body)}</p>}
                {str(s.link) && str(s.button_label) && (
                    <Link className="sa-outline-button" href={str(s.link)}>
                        {str(s.button_label)}
                    </Link>
                )}
            </div>
        </div>
    );
}
