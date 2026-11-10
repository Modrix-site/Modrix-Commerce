import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Testimonials({ section }: ThemeSectionProps) {
    const quotes = section.blocks.filter((b) => str(b.settings.quote));
    if (!quotes.length) return null;
    return (
        <section className="bl-frame bl-section">
            <div className="bl-section-head">
                <h2>{str(section.settings.heading)}</h2>
            </div>
            <div className="bl-testimonials">
                {quotes.map((b) => (
                    <blockquote
                        className="bl-quote"
                        key={b.id}
                        {...blockAttributes(b)}
                    >
                        <span
                            className="bl-card-rating"
                            aria-label={
                                String(b.settings.rating) + ' out of 5 stars'
                            }
                        >
                            {'★'.repeat(
                                Math.max(
                                    1,
                                    Math.min(5, Number(b.settings.rating) || 5),
                                ),
                            )}
                        </span>
                        <p>{str(b.settings.quote)}</p>
                        <strong>{str(b.settings.name)}</strong>
                        <small>{str(b.settings.detail)}</small>
                    </blockquote>
                ))}
            </div>
        </section>
    );
}
