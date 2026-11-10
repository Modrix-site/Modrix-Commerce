import type { CSSProperties } from 'react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Strip({ section }: ThemeSectionProps) {
    const s = section.settings;
    if (s.enabled === false) return null;
    return (
        <div
            className="bl-promo-strip"
            style={
                {
                    background: str(s.background),
                    color: str(s.foreground),
                } as CSSProperties
            }
        >
            <div className="bl-frame">
                <div>
                    <strong>{str(s.heading)}</strong>
                    <span>{str(s.body)}</span>
                </div>
                {str(s.image) && <img src={str(s.image)} alt="" />}
                <Link
                    href={str(s.link) || '/search'}
                    style={{
                        background: str(s.button_background),
                        color: str(s.button_foreground),
                    }}
                >
                    {str(s.button)}
                </Link>
            </div>
        </div>
    );
}
