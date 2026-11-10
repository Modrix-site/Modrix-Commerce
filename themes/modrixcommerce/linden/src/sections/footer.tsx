import type { CSSProperties } from 'react';
import { usePage } from '@inertiajs/react';
import { Instagram, Facebook, Youtube, Globe, ArrowUp } from 'lucide-react';
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
    const menus = themeMenus(storefront?.navigation, s.menu_source).footer;
    const social = section.blocks.filter(
        (b) => b.type === 'social' && str(b.settings.link),
    );
    return (
        <footer
            className="lv-footer"
            style={
                {
                    background: str(s.background),
                    color: str(s.foreground),
                    '--lv-muted': str(s.muted),
                } as CSSProperties
            }
        >
            <div className="lv-frame lv-footer-grid">
                <div className="lv-footer-newsletter">
                    <h3>{str(s.newsletter_heading)}</h3>
                    <ExtensionSlot items={storefront?.footerSlots} />
                    <div className="lv-socials">
                        {social.map((b) => {
                            const Icon =
                                (
                                    {
                                        instagram: Instagram,
                                        facebook: Facebook,
                                        youtube: Youtube,
                                    } as Record<string, typeof Instagram>
                                )[str(b.settings.icon)] ?? Globe;
                            return (
                                <a
                                    key={b.id}
                                    href={str(b.settings.link)}
                                    aria-label={str(b.settings.label)}
                                    {...blockAttributes(b)}
                                >
                                    <Icon size={20} />
                                </a>
                            );
                        })}
                    </div>
                </div>
                {menus.length
                    ? menus.map((m, i) => (
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
                {str(s.qr_image) && (
                    <div className="lv-footer-qr">
                        <img src={str(s.qr_image)} alt={str(s.qr_alt)} />
                    </div>
                )}
                {(str(s.appstore_url) || str(s.playstore_url)) && (
                    <div className="lv-footer-app">
                        <h3>{str(s.app_heading)}</h3>
                        {str(s.appstore_url) && (
                            <a href={str(s.appstore_url)}>
                                {str(s.appstore_label)}
                            </a>
                        )}
                        {str(s.playstore_url) && (
                            <a href={str(s.playstore_url)}>
                                {str(s.playstore_label)}
                            </a>
                        )}
                    </div>
                )}
            </div>
            <div className="lv-frame lv-footer-bottom">
                <p>
                    © {new Date().getFullYear()}{' '}
                    {str(s.copyright) || storefront?.storeName}
                </p>
                <div>
                    {section.blocks
                        .filter((b) => b.type === 'payment')
                        .map((b) => (
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
                {s.show_top !== false && (
                    <button
                        type="button"
                        aria-label="Back to top"
                        onClick={() =>
                            window.scrollTo({
                                top: 0,
                                behavior: window.matchMedia(
                                    '(prefers-reduced-motion: reduce)',
                                ).matches
                                    ? 'instant'
                                    : 'smooth',
                            })
                        }
                    >
                        <ArrowUp size={18} />
                    </button>
                )}
            </div>
        </footer>
    );
}
