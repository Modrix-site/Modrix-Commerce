import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str, columns } from '../components';
export default function Categories({ section }: ThemeSectionProps) {
    return (
        <section className="vm-frame vm-section">
            <div className="vm-section-head">
                <h2>{str(section.settings.heading)}</h2>
            </div>
            <div
                className="vm-categories"
                style={columns(section.settings.columns, 8)}
            >
                {section.blocks.map((b) => (
                    <Link
                        key={b.id}
                        className="vm-category"
                        href={str(b.settings.link) || '/search'}
                        {...blockAttributes(b)}
                    >
                        {str(b.settings.image) && (
                            <img
                                src={str(b.settings.image)}
                                alt=""
                                loading="lazy"
                            />
                        )}
                        <span>{str(b.settings.label)}</span>
                    </Link>
                ))}
            </div>
        </section>
    );
}
