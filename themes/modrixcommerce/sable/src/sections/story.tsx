import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import { str } from '../utils';
export default function Story({ section }: ThemeSectionProps) {
    const s = section.settings;
    return (
        <section
            className="sa-story sa-frame"
            data-position={str(s.image_position)}
        >
            <div className="sa-story-copy">
                <p className="sa-eyebrow">{str(s.eyebrow)}</p>
                <h2>{str(s.heading)}</h2>
                <p>{str(s.body)}</p>
                {str(s.link) && str(s.button_label) && (
                    <Link className="sa-solid-button" href={str(s.link)}>
                        {str(s.button_label)}
                    </Link>
                )}
            </div>
            {str(s.image) && (
                <img src={str(s.image)} alt={str(s.alt)} loading="lazy" />
            )}
        </section>
    );
}
