import type { CSSProperties } from 'react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Hero({ section }: ThemeSectionProps) {
    const s = section.settings;
    return (
        <section
            className="re-tech-hero"
            style={
                {
                    background: str(s.background),
                    '--re-hero-heading': (Number(s.heading_size) || 44) + 'px',
                } as CSSProperties
            }
        >
            {str(s.image) && (
                <img src={str(s.image)} alt="" fetchPriority="high" />
            )}
            <div className="re-frame">
                <div className="re-tech-hero-copy">
                    <p>{str(s.eyebrow)}</p>
                    <h1>{str(s.heading)}</h1>
                    <strong style={{ color: str(s.highlight_color) }}>
                        {str(s.highlight)}
                    </strong>
                    <Link
                        className="re-btn store-button"
                        href={str(s.link) || '/search'}
                    >
                        {str(s.button)}
                    </Link>
                </div>
            </div>
        </section>
    );
}
