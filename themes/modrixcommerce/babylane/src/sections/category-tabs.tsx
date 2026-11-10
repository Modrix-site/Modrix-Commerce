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
import { str, collection } from '../components';
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
    const selected = section.blocks[active] ?? section.blocks[0];
    const c = collection(selected?.settings.collection);
    return (
        <section className="bl-frame bl-section bl-trends">
            <h2>{str(section.settings.heading)}</h2>
            <div className="bl-trends-panel">
                <div
                    className="bl-category-tabs"
                    aria-label="Trending collections"
                >
                    {section.blocks.map((b, i) => {
                        const Icon =
                            icons[str(b.settings.icon) as keyof typeof icons] ??
                            Baby;
                        return (
                            <button
                                key={b.id}
                                type="button"
                                aria-pressed={i === active}
                                onClick={() => setActive(i)}
                                {...blockAttributes(b)}
                            >
                                <Icon size={24} strokeWidth={1.3} />
                                {str(b.settings.label)}
                            </button>
                        );
                    })}
                </div>
                <div className="bl-trending-grid">
                    {(c?.products ?? []).slice(0, 16).map((p) => (
                        <Link key={p.id} href={'/p/' + p.slug}>
                            {p.image && (
                                <img src={p.image.url} alt="" loading="lazy" />
                            )}
                            <span>{p.name}</span>
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}
