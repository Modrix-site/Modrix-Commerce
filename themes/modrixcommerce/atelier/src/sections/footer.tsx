import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUp,
    Instagram,
    Facebook,
    Youtube,
} from 'lucide-react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { str } from '../components';

export default function Footer({ section }: ThemeSectionProps) {
    const s = section.settings;
    const { storefront } = usePage().props;
    const [email, setEmail] = useState('');
    const [subscribed, setSubscribed] = useState(false);

    const handleNewsletterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (email.trim()) {
            setSubscribed(true);
            setEmail('');
        }
    };

    const scrollToTop = () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <footer className="at-footer">
            <div className="at-frame">
                {/* Editorial Top Intro & Newsletter */}
                {s.show_intro !== false && (
                    <div className="at-footer-intro">
                        <div>
                            <p className="at-eyebrow">
                                {str(s.intro_brand, 'ATELIER')}
                            </p>
                            <h2>
                                {str(
                                    s.intro_heading,
                                    'Considered essentials. Lasting stories.',
                                )}
                            </h2>
                            <p className="max-w-md text-sm leading-relaxed text-neutral-500">
                                {str(
                                    s.intro_text,
                                    'Discover objects that bring intention to everyday life.',
                                )}
                            </p>
                        </div>

                        {s.newsletter_enabled !== false && (
                            <div className="at-newsletter-form">
                                <p className="at-eyebrow">
                                    {str(
                                        s.newsletter_heading,
                                        'Join our newsletter',
                                    )}
                                </p>
                                <p className="text-sm text-neutral-500">
                                    {str(
                                        s.newsletter_text,
                                        'Be the first to discover new collections and stories.',
                                    )}
                                </p>
                                <form
                                    onSubmit={handleNewsletterSubmit}
                                    className="at-newsletter-input-wrap"
                                >
                                    <input
                                        type="email"
                                        required
                                        placeholder={str(
                                            s.newsletter_placeholder,
                                            'Email address',
                                        )}
                                        value={email}
                                        onChange={(e) =>
                                            setEmail(e.target.value)
                                        }
                                        aria-label={str(
                                            s.newsletter_placeholder,
                                            'Email address',
                                        )}
                                    />
                                    <button
                                        type="submit"
                                        aria-label={str(
                                            s.newsletter_button,
                                            'Sign up',
                                        )}
                                        className="at-icon-btn"
                                    >
                                        <ArrowRight size={18} />
                                    </button>
                                </form>
                                {subscribed && (
                                    <p
                                        className="mt-2 text-xs font-medium text-neutral-800"
                                        role="status"
                                    >
                                        {str(
                                            s.newsletter_success,
                                            'Thank you for subscribing.',
                                        )}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {/* Footer Navigation Columns & Social */}
                <div className="at-footer-links-grid">
                    {section.blocks.map((b) => {
                        const lines = str(b.settings.links)
                            .split('\n')
                            .filter(Boolean);
                        return (
                            <div className="at-footer-col" key={b.id}>
                                <h3>{str(b.settings.title)}</h3>
                                <ul>
                                    {lines.map((line, i) => {
                                        const [label, url] = line
                                            .split('|')
                                            .map((x) => x.trim());
                                        return (
                                            <li key={i}>
                                                <Link href={url || '/search'}>
                                                    {label}
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        );
                    })}

                    {/* Social Media Column */}
                    {(str(s.social_instagram) ||
                        str(s.social_facebook) ||
                        str(s.social_youtube)) && (
                        <div className="at-footer-col">
                            <h3>{str(s.social_heading, 'Follow us')}</h3>
                            <div className="mt-2 flex items-center gap-3">
                                {str(s.social_instagram) && (
                                    <a
                                        href={str(s.social_instagram)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="at-icon-btn"
                                        aria-label="Instagram"
                                    >
                                        <Instagram size={18} />
                                    </a>
                                )}
                                {str(s.social_facebook) && (
                                    <a
                                        href={str(s.social_facebook)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="at-icon-btn"
                                        aria-label="Facebook"
                                    >
                                        <Facebook size={18} />
                                    </a>
                                )}
                                {str(s.social_youtube) && (
                                    <a
                                        href={str(s.social_youtube)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="at-icon-btn"
                                        aria-label="YouTube"
                                    >
                                        <Youtube size={18} />
                                    </a>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                {/* Extension Slot in Footer */}
                <PlaceSlot place="storefront.footer" />

                {/* Bottom Bar */}
                <div className="at-footer-bottom">
                    <div>
                        <span>
                            {str(s.copyright) ||
                                `© ${new Date().getFullYear()} ${storefront?.storeName || 'Atelier'}. All rights reserved.`}
                        </span>
                    </div>

                    <div className="flex items-center gap-6">
                        <span>AE / AED</span>
                        {s.back_to_top !== false && (
                            <button
                                type="button"
                                onClick={scrollToTop}
                                className="inline-flex items-center gap-1.5 text-[11px] tracking-wider uppercase hover:text-black"
                            >
                                <span>{str(s.top_label, 'Back to top')}</span>
                                <ArrowUp size={13} />
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </footer>
    );
}
