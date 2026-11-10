import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str, useDeviceList } from '../components';
export default function Recent({ section }: ThemeSectionProps) {
    const items = useDeviceList('recent');
    return (
        <section className="bl-frame bl-section">
            <div className="bl-section-head">
                <h2>{str(section.settings.heading)}</h2>
                <Link href="/search">{str(section.settings.view_label)}</Link>
            </div>
            <div className="bl-recent-products">
                {items.length ? (
                    items.map((p) => (
                        <Link key={p.id} href={'/p/' + p.slug}>
                            {p.image && <img src={p.image} alt={p.name} />}
                            <span>{p.name}</span>
                        </Link>
                    ))
                ) : (
                    <p>Products you view will appear here.</p>
                )}
            </div>
        </section>
    );
}
