import type { CSSProperties } from 'react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Hero({ section }: ThemeSectionProps) {
    const s = section.settings;
    return (
        <section
            className="lv-hero"
            style={
                {
                    background: str(s.background),
                    color: str(s.foreground),
                    '--lv-hero-title': `${Number(s.heading_size) || 56}px`,
                    '--lv-hero-height': `${Number(s.height) || 600}px`,
                } as CSSProperties
            }
        >
            {str(s.image) && (
                <img src={str(s.image)} alt={str(s.alt)} fetchPriority="high" />
            )}
            <div className="lv-frame">
                <div>
                    <p>{str(s.eyebrow)}</p>
                    <h1>{str(s.heading)}</h1>
                    <p>{str(s.body)}</p>
                    <Link
                        className="lv-btn lv-btn-light"
                        href={str(s.link) || '/search'}
                    >
                        {str(s.button)}
                    </Link>
                </div>
            </div>
        </section>
    );
}
