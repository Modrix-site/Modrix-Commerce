import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str, assetUrl } from '../components';

export default function Editorial({ section }: ThemeSectionProps) {
    const s = section.settings;
    const height = Number(s.image_height) || 420;

    const cards = [
        {
            image: str(s.card_1_image),
            alt: str(s.card_1_alt, 'Writing collection'),
            heading: str(s.card_1_heading, 'THE WRITING EDIT'),
            text: str(s.card_1_text, 'An invitation to put pen to paper.'),
            button: str(s.card_1_button, 'Discover now'),
            link: str(s.card_1_link, '/search'),
        },
        {
            image: str(s.card_2_image),
            alt: str(s.card_2_alt, 'Everyday companions'),
            heading: str(s.card_2_heading, 'EVERYDAY COMPANIONS'),
            text: str(s.card_2_text, 'Timeless pieces for a life in motion.'),
            button: str(s.card_2_button, 'Discover now'),
            link: str(s.card_2_link, '/search'),
        },
    ];

    return (
        <section className="at-section">
            <div className="at-frame">
                <div className="at-editorial-grid">
                    {cards.map((card, i) => (
                        <article className="at-editorial-card" key={i}>
                            <Link
                                href={card.link}
                                className="at-editorial-photo"
                                style={{ height: `${height}px` }}
                                aria-label={card.heading}
                            >
                                {card.image && (
                                    <img
                                        src={assetUrl(card.image)}
                                        alt={card.alt}
                                        loading="lazy"
                                    />
                                )}
                            </Link>
                            <div className="at-editorial-caption">
                                <div>
                                    <h3>{card.heading}</h3>
                                    <p>{card.text}</p>
                                </div>
                                <Link href={card.link} className="at-text-link">
                                    <span>{card.button}</span>
                                    <ArrowRight size={14} />
                                </Link>
                            </div>
                        </article>
                    ))}
                </div>
            </div>
        </section>
    );
}
