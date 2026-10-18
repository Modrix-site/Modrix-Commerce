import { ArrowUp } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { PaymentBadges, useCircuit } from './components';
import './style.css';

const SOCIALS: [string, string][] = [
    ['facebook', 'Facebook'],
    ['instagram', 'Instagram'],
    ['tiktok', 'TikTok'],
    ['youtube', 'YouTube'],
    ['pinterest', 'Pinterest'],
];

function BackToTop() {
    const [visible, setVisible] = useState(false);
    useEffect(() => {
        const onScroll = () => setVisible(window.scrollY > 600);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);
    if (!visible) return null;
    return (
        <button
            type="button"
            className="ci-to-top"
            aria-label="Back to top"
            onClick={() =>
                window.scrollTo({
                    top: 0,
                    behavior: window.matchMedia(
                        '(prefers-reduced-motion: reduce)',
                    ).matches
                        ? 'auto'
                        : 'smooth',
                })
            }
        >
            <ArrowUp size={18} />
        </button>
    );
}

export default function CircuitFooter() {
    const { storefront, text } = useCircuit();
    const menus = (storefront?.navigation?.footer ?? []).slice(0, 2);
    const socials = SOCIALS.filter(([key]) => text(`social_${key}`));
    return (
        <footer className="ci-footer">
            <div className="ci-frame ci-footer-grid">
                <section aria-label={text('about_heading', 'About The Store')}>
                    <h2>{text('about_heading', 'About The Store')}</h2>
                    <p className="ci-footer-about">
                        {text('about_text')}{' '}
                        {text('map_link') && text('map_label') && (
                            <Link className="ci-strong" href={text('map_link')}>
                                {text('map_label')}
                            </Link>
                        )}
                    </p>
                    {text('phone') && (
                        <p className="ci-footer-contact">{text('phone')}</p>
                    )}
                    {text('email') && (
                        <p className="ci-footer-contact">
                            <a href={`mailto:${text('email')}`}>
                                {text('email')}
                            </a>
                        </p>
                    )}
                    {socials.length > 0 && (
                        <nav className="ci-socials" aria-label="Social media">
                            {socials.map(([key, label]) => (
                                <a
                                    key={key}
                                    href={text(`social_${key}`)}
                                    aria-label={label}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {label.slice(0, 2)}
                                </a>
                            ))}
                        </nav>
                    )}
                </section>
                {menus.map((menu, i) => (
                    <nav key={i} aria-label={menu.title}>
                        <h2>{menu.title}</h2>
                        {menu.links.map((link, j) => (
                            <Link key={j} href={link.url}>
                                {link.label}
                            </Link>
                        ))}
                    </nav>
                ))}
                <section
                    className="ci-newsletter"
                    aria-label={text(
                        'newsletter_heading',
                        'Sign Up to Newsletter',
                    )}
                >
                    <h2>
                        {text('newsletter_heading', 'Sign Up to Newsletter')}
                    </h2>
                    <p>{text('newsletter_text')}</p>
                    <ExtensionSlot items={storefront?.footerSlots} />
                </section>
            </div>
            <div className="ci-frame ci-footer-bottom">
                <p>
                    <span className="ci-muted">
                        {storefront?.displayCurrency ?? storefront?.currency}
                    </span>{' '}
                    ·{' '}
                    {text(
                        'copyright',
                        `© ${new Date().getFullYear()} ${storefront?.storeName ?? 'Circuit'}`,
                    )}
                </p>
                <PaymentBadges labels={text('payment_labels')} />
            </div>
            <BackToTop />
        </footer>
    );
}
