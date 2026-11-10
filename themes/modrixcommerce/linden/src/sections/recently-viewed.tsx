import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { useDeviceList, str } from '../components';
export default function Recent({ section }: ThemeSectionProps) {
    const list = useDeviceList('recent');
    if (!list.length) return null;
    return (
        <section className="lv-frame lv-section lv-recent">
            <h2>{str(section.settings.heading)}</h2>
            <div className="lv-product-carousel">
                {list.slice(0, Number(section.settings.limit) || 4).map((p) => (
                    <article className="lv-card" key={p.id}>
                        <Link className="lv-card-image" href={'/p/' + p.slug}>
                            <img src={p.image} alt={p.name} loading="lazy" />
                        </Link>
                        <Link className="lv-card-name" href={'/p/' + p.slug}>
                            {p.name}
                        </Link>
                        <p className="lv-card-price">{p.price}</p>
                        <Link className="lv-btn" href={'/p/' + p.slug}>
                            {str(section.settings.button)}
                        </Link>
                    </article>
                ))}
            </div>
        </section>
    );
}
