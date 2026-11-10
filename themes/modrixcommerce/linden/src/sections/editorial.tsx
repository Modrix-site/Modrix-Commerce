import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Editorial({ section }: ThemeSectionProps) {
    const s = section.settings;
    return (
        <section
            className="lv-editorial"
            style={{ background: str(s.background), color: str(s.foreground) }}
        >
            <div className="lv-frame">
                <img src={str(s.image)} alt={str(s.alt)} loading="lazy" />
                <div>
                    <h2>{str(s.heading)}</h2>
                    <p>{str(s.body)}</p>
                    <Link
                        className="lv-btn lv-btn-solid store-button"
                        href={str(s.link) || '/search'}
                    >
                        {str(s.button)}
                    </Link>
                </div>
            </div>
        </section>
    );
}
