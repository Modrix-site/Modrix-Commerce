import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function RichText({ section }: ThemeSectionProps) {
    const s = section.settings;
    return (
        <section
            className="vm-frame vm-section vm-richtext"
            style={{ background: str(s.background) }}
        >
            {str(s.image) && <img src={str(s.image)} alt="" loading="lazy" />}
            <div>
                <p className="vm-eyebrow">{str(s.eyebrow)}</p>
                <h2>{str(s.heading)}</h2>
                <p>{str(s.body)}</p>
                {str(s.button) && (
                    <Link className="vm-btn" href={str(s.link) || '/search'}>
                        {str(s.button)}
                    </Link>
                )}
            </div>
        </section>
    );
}
