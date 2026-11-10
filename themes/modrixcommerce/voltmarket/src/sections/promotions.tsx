import type { CSSProperties } from 'react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str, columns } from '../components';
export default function Promotions({ section }: ThemeSectionProps) {
    return (
        <section
            className="vm-frame vm-promo-grid"
            style={columns(section.settings.columns, 3)}
        >
            {section.blocks.map((b) => (
                <article
                    className="vm-promo"
                    key={b.id}
                    style={
                        {
                            '--vm-panel':
                                str(b.settings.background) || '#f5f5f5',
                        } as CSSProperties
                    }
                    {...blockAttributes(b)}
                >
                    {str(b.settings.image) && (
                        <img
                            src={str(b.settings.image)}
                            alt=""
                            loading="lazy"
                        />
                    )}
                    <div>
                        <h3>{str(b.settings.heading)}</h3>
                        <p>{str(b.settings.body)}</p>
                        {str(b.settings.button) && (
                            <Link
                                href={str(b.settings.link) || '/search'}
                                className="vm-btn"
                            >
                                {str(b.settings.button)}
                            </Link>
                        )}
                    </div>
                </article>
            ))}
        </section>
    );
}
