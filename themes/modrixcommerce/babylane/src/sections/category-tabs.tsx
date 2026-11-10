import { useState } from 'react';
import {
    Bath,
    Baby,
    Moon,
    Shirt,
    Milk,
    ToyBrick,
    House,
    Heart,
    Star,
} from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str, collection, tagIcon, tagList } from '../components';
const icons = {
    bath: Bath,
    baby: Baby,
    moon: Moon,
    shirt: Shirt,
    milk: Milk,
    toy: ToyBrick,
    home: House,
    heart: Heart,
    star: Star,
};
export default function Trends({ section }: ThemeSectionProps) {
    const [active, setActive] = useState(0);
    // Product tags by default; the collection blocks when chosen, or while
    // the store has no tagged products yet.
    const tags =
        str(section.settings.tab_source) === 'collections'
            ? []
            : tagList(section.settings.tab_tags);
    const tabs =
        tags.length > 0
            ? tags.map((tag) => ({
                  key: tag.slug,
                  label: tag.label,
                  icon: tagIcon(tag.name),
                  products: tag.products,
                  url: tag.url,
                  attributes: {},
              }))
            : section.blocks.map((b) => ({
                  key: b.id,
                  label: str(b.settings.label),
                  icon: str(b.settings.icon),
                  products: collection(b.settings.collection)?.products ?? [],
                  url: '',
                  attributes: blockAttributes(b),
              }));
    const selected = tabs[active] ?? tabs[0];
    return (
        <section className="bl-frame bl-section bl-trends">
            <h2>{str(section.settings.heading)}</h2>
            <div className="bl-trends-panel">
                <div
                    className="bl-category-tabs"
                    aria-label="Trending collections"
                >
                    {tabs.map((tab, i) => {
                        const Icon =
                            icons[tab.icon as keyof typeof icons] ?? Baby;
                        return (
                            <button
                                key={tab.key}
                                type="button"
                                aria-pressed={i === active}
                                onClick={() => setActive(i)}
                                {...tab.attributes}
                            >
                                <Icon size={24} strokeWidth={1.3} />
                                {tab.label}
                            </button>
                        );
                    })}
                </div>
                <div className="bl-trending-grid">
                    {(selected?.products ?? []).slice(0, 16).map((p) => (
                        <Link key={p.id} href={'/p/' + p.slug}>
                            {p.image && (
                                <img src={p.image.url} alt="" loading="lazy" />
                            )}
                            <span>{p.name}</span>
                        </Link>
                    ))}
                </div>
                {selected?.url && (
                    <Link className="bl-trends-all" href={selected.url}>
                        View all {selected.label}
                    </Link>
                )}
            </div>
        </section>
    );
}
