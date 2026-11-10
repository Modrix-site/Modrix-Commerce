import type { CSSProperties } from 'react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Hero({ section }: ThemeSectionProps) {
    const s = section.settings;
    return (
        <section className="vm-frame vm-hero">
            <div
                className="vm-banner"
                style={
                    {
                        background: str(s.background),
                        '--vm-hero-background': str(s.background),
                    } as CSSProperties
                }
            >
                {str(s.image) && (
                    <img src={str(s.image)} alt="" fetchPriority="high" />
                )}
                <div className="vm-banner-copy">
                    <p className="vm-eyebrow">{str(s.eyebrow)}</p>
                    <h1>{str(s.heading)}</h1>
                    <p>{str(s.body)}</p>
                    {str(s.button) && (
                        <Link
                            href={str(s.link) || '/search'}
                            className="vm-btn"
                        >
                            {str(s.button)}
                        </Link>
                    )}
                </div>
            </div>
            <div className="vm-hero-side">
                {section.blocks.slice(0, 2).map((b) => (
                    <article
                        className="vm-promo"
                        key={b.id}
                        style={
                            {
                                '--vm-panel': str(b.settings.background),
                            } as CSSProperties
                        }
                        {...blockAttributes(b)}
                    >
                        {str(b.settings.image) && (
                            <img src={str(b.settings.image)} alt="" />
                        )}
                        <div>
                            <h3>{str(b.settings.heading)}</h3>
                            <p>{str(b.settings.body)}</p>
                            <Link
                                href={str(b.settings.link) || '/search'}
                                className="vm-btn"
                            >
                                {str(b.settings.button)}
                            </Link>
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
}
