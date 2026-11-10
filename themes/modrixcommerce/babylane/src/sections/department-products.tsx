import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { Card, str, collection } from '../components';
export default function Department({ section }: ThemeSectionProps) {
    const s = section.settings;
    const c = collection(s.collection);
    const all = c?.products ?? [];
    const offset = all.length ? (Number(s.offset) || 0) % all.length : 0;
    const products = [...all.slice(offset), ...all.slice(0, offset)].slice(
        0,
        Number(s.limit) || 6,
    );
    return (
        <section className="bl-frame bl-section bl-department-section">
            <aside>
                <h2 style={{ fontSize: Number(s.heading_size) || 18 }}>
                    {str(s.heading)}
                </h2>
                <nav>
                    {section.blocks.map((b) => (
                        <Link
                            key={b.id}
                            href={str(b.settings.link) || '/search'}
                            {...blockAttributes(b)}
                        >
                            {str(b.settings.label)}
                        </Link>
                    ))}
                </nav>
                <Link
                    className="bl-department-all"
                    href={str(s.link) || c?.url || '/search'}
                >
                    {str(s.view_label)}
                </Link>
            </aside>
            <div
                className="bl-department-promo"
                style={{ background: str(s.background) }}
            >
                <p>{str(s.eyebrow)}</p>
                <h3 style={{ fontSize: Number(s.banner_heading_size) || 28 }}>
                    {str(s.banner_heading)}
                </h3>
                {str(s.image) && (
                    <img src={str(s.image)} alt="" loading="lazy" />
                )}
                <span>{str(s.body)}</span>
                <Link
                    className="bl-btn store-button"
                    href={str(s.link) || c?.url || '/search'}
                >
                    {str(s.button)}
                </Link>
            </div>
            <div className="bl-department-products">
                {products.map((p) => (
                    <Card
                        key={p.id}
                        product={p}
                        quickAdd={s.quick_add === true}
                    />
                ))}
            </div>
        </section>
    );
}
