import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Categories({ section }: ThemeSectionProps) {
    return (
        <section className="re-frame re-section re-tech-categories">
            <h2
                style={{
                    fontSize: Number(section.settings.heading_size) || 18,
                }}
            >
                {str(section.settings.heading)}
            </h2>
            <div>
                {section.blocks.map((b) => (
                    <Link
                        key={b.id}
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
