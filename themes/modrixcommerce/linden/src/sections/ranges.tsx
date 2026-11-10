import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Ranges({ section }: ThemeSectionProps) {
    return (
        <section className="lv-frame lv-section lv-ranges">
            <h2>{str(section.settings.heading)}</h2>
            <div>
                {section.blocks.map((b) => (
                    <Link
                        href={str(b.settings.link) || '/search'}
                        key={b.id}
                        {...blockAttributes(b)}
                    >
                        <img
                            src={str(b.settings.image)}
                            alt={str(b.settings.label)}
                            loading="lazy"
                        />
                        <h3>{str(b.settings.label)}</h3>
                    </Link>
                ))}
            </div>
        </section>
    );
}
