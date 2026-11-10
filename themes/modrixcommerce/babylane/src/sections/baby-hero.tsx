import {
    Bath,
    Baby,
    Moon,
    Shirt,
    Milk,
    ToyBrick,
    House,
    Heart,
} from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str } from '../components';
const icons = {
    bath: Bath,
    baby: Baby,
    moon: Moon,
    shirt: Shirt,
    milk: Milk,
    toy: ToyBrick,
    home: House,
    heart: Heart,
};
export default function Hero({ section }: ThemeSectionProps) {
    const s = section.settings;
    return (
        <section className="bl-frame bl-baby-hero">
            <aside className="bl-hero-departments">
                <nav aria-label="Baby departments">
                    {section.blocks
                        .filter((b) => b.type === 'category')
                        .map((b) => {
                            const Icon =
                                icons[
                                    str(b.settings.icon) as keyof typeof icons
                                ] ?? Baby;
                            return (
                                <Link
                                    key={b.id}
                                    href={str(b.settings.link) || '/search'}
                                    {...blockAttributes(b)}
                                >
                                    <Icon size={16} />
                                    {str(b.settings.label)}
                                </Link>
                            );
                        })}
                </nav>
            </aside>
            <div className="bl-baby-main">
                <img src={str(s.image)} alt="" fetchPriority="high" />
                <div className="bl-baby-main-copy">
                    <p>{str(s.eyebrow)}</p>
                    <h1 style={{ fontSize: Number(s.heading_size) || 32 }}>
                        {str(s.heading)}
                    </h1>
                    <span>{str(s.body)}</span>
                    <Link
                        className="bl-btn store-button"
                        href={str(s.link) || '/search'}
                    >
                        {str(s.button)}
                    </Link>
                </div>
            </div>
            <div className="bl-baby-side">
                {section.blocks
                    .filter((b) => b.type === 'promotion')
                    .map((b) => (
                        <article
                            key={b.id}
                            className="bl-baby-promo"
                            style={{ background: str(b.settings.background) }}
                            {...blockAttributes(b)}
                        >
                            <img src={str(b.settings.image)} alt="" />
                            <div>
                                <small>{str(b.settings.eyebrow)}</small>
                                <h3>{str(b.settings.heading)}</h3>
                                <p>{str(b.settings.body)}</p>
                                <Link href={str(b.settings.link) || '/search'}>
                                    {str(b.settings.button)}
                                </Link>
                            </div>
                        </article>
                    ))}
            </div>
            <div
                className="bl-baby-bottom"
                style={{ background: str(s.bottom_background) }}
            >
                <img src={str(s.bottom_image)} alt="" />
                <div>
                    <small>{str(s.bottom_eyebrow)}</small>
                    <h2>{str(s.bottom_heading)}</h2>
                    <p>{str(s.bottom_body)}</p>
                    <Link
                        href={str(s.bottom_link) || '/search'}
                        className="bl-btn store-button"
                    >
                        {str(s.bottom_button)}
                    </Link>
                </div>
            </div>
        </section>
    );
}
