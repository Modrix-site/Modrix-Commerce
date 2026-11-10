import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Logos({ section }: ThemeSectionProps) {
    return (
        <section className="bl-frame bl-section bl-logo-section">
            <h2>{str(section.settings.heading)}</h2>
            <div className="bl-logo-grid">
                {section.blocks.map((b, i) => (
                    <Link
                        key={b.id}
                        href={str(b.settings.link) || '/search'}
                        {...blockAttributes(b)}
                    >
                        {str(b.settings.image) ? (
                            <img
                                src={str(b.settings.image)}
                                alt={str(b.settings.label)}
                            />
                        ) : (
                            <span
                                style={{ color: str(b.settings.color) }}
                                className={
                                    'bl-logo-word bl-logo-word-' + (i % 4)
                                }
                            >
                                {str(b.settings.label)}
                            </span>
                        )}
                    </Link>
                ))}
            </div>
        </section>
    );
}
