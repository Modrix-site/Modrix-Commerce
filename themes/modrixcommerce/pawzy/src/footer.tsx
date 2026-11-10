import { Link, usePage } from '@inertiajs/react';
import { ArrowUp, Mail, Phone } from 'lucide-react';
import { StoreFooterMenus } from '@/components/storefront/footer-menus';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import { Brand, TrustBand } from './components';

export default function PawzyFooter() {
    const { storefront } = usePage().props;
    const s = storefront?.theme.settings ?? {};
    const content = storefront?.theme.content ?? {};
    return (
        <>
            <TrustBand />
            {s.show_footer !== false && (
                <footer className="pawzy-footer">
                    <div className="pawzy-footer-inner">
                        <section>
                            <Brand />
                            <h3>
                                {String(
                                    s.newsletter_heading ??
                                        'Subscribe to our newsletter',
                                )}
                            </h3>
                            <p>{String(s.footer_intro ?? '')}</p>
                            {s.show_newsletter !== false && (
                                <>
                                    <ExtensionSlot
                                        items={storefront?.footerSlots}
                                    />
                                </>
                            )}
                        </section>
                        <section>
                            <h3>Contact us</h3>
                            {content.contact_email && (
                                <a href={`mailto:${content.contact_email}`}>
                                    <Mail size={15} />
                                    {content.contact_email}
                                </a>
                            )}
                            {content.contact_phone && (
                                <a
                                    href={`tel:${content.contact_phone.replace(/[^+0-9]/g, '')}`}
                                >
                                    <Phone size={15} />
                                    {content.contact_phone}
                                </a>
                            )}
                            <Link href="/account/orders">Track your order</Link>
                            <Link href="/search">
                                Find your pet's favourite
                            </Link>
                        </section>
                        {s.show_footer_links !== false && (
                            <>
                                {s.use_store_navigation === true ? (
                                    <StoreFooterMenus />
                                ) : (
                                    [1, 2, 3]
                                        .filter((col) =>
                                            [1, 2, 3, 4, 5, 6].some(
                                                (i) =>
                                                    content[
                                                        `footer_${col}_${i}_label`
                                                    ],
                                            ),
                                        )
                                        .map((col) => (
                                            <nav
                                                key={col}
                                                aria-label={
                                                    content[
                                                        `footer_${col}_title`
                                                    ]
                                                }
                                            >
                                                <h3>
                                                    {
                                                        content[
                                                            `footer_${col}_title`
                                                        ]
                                                    }
                                                </h3>
                                                {[1, 2, 3, 4, 5, 6].map(
                                                    (i) =>
                                                        content[
                                                            `footer_${col}_${i}_label`
                                                        ] && (
                                                            <Link
                                                                key={i}
                                                                href={
                                                                    content[
                                                                        `footer_${col}_${i}_link`
                                                                    ] ||
                                                                    '/search'
                                                                }
                                                            >
                                                                {
                                                                    content[
                                                                        `footer_${col}_${i}_label`
                                                                    ]
                                                                }
                                                            </Link>
                                                        ),
                                                )}
                                            </nav>
                                        ))
                                )}
                            </>
                        )}
                    </div>
                    <div className="pawzy-copyright">
                        <span>{storefront?.theme.footerText}</span>
                        <span>
                            Made for the love of pets{' '}
                            <a href="#main" aria-label="Back to top">
                                <ArrowUp size={18} />
                            </a>
                        </span>
                    </div>
                </footer>
            )}
        </>
    );
}
