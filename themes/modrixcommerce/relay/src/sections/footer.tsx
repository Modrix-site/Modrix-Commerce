import type { CSSProperties } from 'react';
import { usePage } from '@inertiajs/react';
import { Instagram, Facebook, Youtube, Globe } from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import { StoreMenuTree, themeMenus } from '@/components/storefront/store-menu';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Footer({ section }: ThemeSectionProps) {
    const { storefront } = usePage().props;
    const s = section.settings;
    const links = section.blocks.filter(
        (b) => b.type === 'link' && str(b.settings.link),
    );
    const groups = [...new Set(links.map((b) => str(b.settings.group)))];
    // Admin → Navigation's Footer 1–3 once saved there ("Footer menus" setting).
    const storeFooter = themeMenus(
        storefront?.navigation,
        s.menu_source,
    ).footer;
    const social = section.blocks.filter(
        (b) => b.type === 'social' && str(b.settings.link),
    );
    const payments = section.blocks.filter((b) => b.type === 'payment');
    return (
        <footer
            className="re-footer"
            style={
                {
                    background: str(s.background),
                    '--re-ink': str(s.foreground),
                    '--re-muted': str(s.muted),
                } as CSSProperties
            }
        >
            <div className="re-frame re-tech-footer-grid">
                {storeFooter.length > 0
                    ? storeFooter.map((m, i) => (
                          <nav key={i} aria-label={m.title}>
                              <h3>{m.title}</h3>
                              <StoreMenuTree menu={m} orientation="vertical" />
                          </nav>
                      ))
                    : groups.map((g) => (
                          <nav key={g} aria-label={g}>
                              <h3>{g}</h3>
                              {links
                                  .filter((b) => str(b.settings.group) === g)
                                  .map((b) => (
                                      <Link
                                          key={b.id}
                                          href={str(b.settings.link)}
                                          {...blockAttributes(b)}
                                      >
                                          {str(b.settings.label)}
                                      </Link>
                                  ))}
                          </nav>
                      ))}
                <div className="re-footer-signup">
                    <h3>{str(s.newsletter_heading)}</h3>
                    <p>{str(s.newsletter_body)}</p>
                    <ExtensionSlot items={storefront?.footerSlots} />
                    {social.length > 0 && (
                        <div className="re-socials">
                            {social.map((b) => {
                                const Icon =
                                    {
                                        instagram: Instagram,
                                        facebook: Facebook,
                                        youtube: Youtube,
                                    }[str(b.settings.label).toLowerCase()] ??
                                    Globe;
                                return (
                                    <Link
                                        key={b.id}
                                        href={str(b.settings.link)}
                                        aria-label={str(b.settings.label)}
                                        {...blockAttributes(b)}
                                    >
                                        <Icon size={18} />
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
            <div className="re-frame re-footer-bottom">
                <span>
                    © {new Date().getFullYear()} {str(s.copyright)}
                </span>
                <div className="re-payment-labels">
                    {payments.map((b) => (
                        <span key={b.id} {...blockAttributes(b)}>
                            {str(b.settings.image) ? (
                                <img
                                    src={str(b.settings.image)}
                                    alt={str(b.settings.label)}
                                />
                            ) : (
                                str(b.settings.label)
                            )}
                        </span>
                    ))}
                </div>
            </div>
        </footer>
    );
}
