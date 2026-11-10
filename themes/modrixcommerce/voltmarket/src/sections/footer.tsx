import type { CSSProperties } from 'react';
import { usePage, router } from '@inertiajs/react';
import { Globe, Instagram, Facebook, Youtube } from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import { StoreMenuTree, themeMenus } from '@/components/storefront/store-menu';
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
    const socials = section.blocks.filter(
        (b) => b.type === 'social' && str(b.settings.link),
    );
    const change = (key: string, value: string) => {
        const u = new URL(window.location.href);
        u.searchParams.set(key, value);
        router.get(u.pathname + u.search, {}, { preserveScroll: true });
    };
    return (
        <footer
            className="vm-footer"
            style={
                {
                    background: str(s.background),
                    '--vm-ink': str(s.foreground),
                    '--vm-muted': str(s.muted),
                    color: str(s.foreground),
                } as CSSProperties
            }
        >
            <div className="vm-frame vm-footer-grid">
                <div className="vm-footer-contact">
                    <h3>{str(s.heading)}</h3>
                    <p>{str(s.body)}</p>
                    {str(s.phone) && (
                        <a
                            className="vm-phone"
                            href={'tel:' + str(s.phone).replace(/[^\d+]/g, '')}
                        >
                            {str(s.phone)}
                        </a>
                    )}
                    {str(s.address) && (
                        <p style={{ whiteSpace: 'pre-line' }}>
                            {str(s.address)}
                        </p>
                    )}
                    {str(s.email) && (
                        <a href={'mailto:' + str(s.email)}>{str(s.email)}</a>
                    )}
                    {socials.length > 0 && (
                        <div className="vm-socials">
                            {socials.map((b) => {
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
            </div>
            {s.show_directory !== false &&
                Boolean(storefront?.categories.length) && (
                    <div className="vm-frame vm-footer-directory">
                        <strong>Explore our departments:</strong>
                        {storefront?.categories.map((c) => (
                            <Link href={'/c/' + c.slug} key={c.id}>
                                {c.name}
                            </Link>
                        ))}
                    </div>
                )}
            <div className="vm-frame vm-footer-bottom">
                <span>
                    © {new Date().getFullYear()} {str(s.copyright)}
                </span>
                {s.show_localization !== false && (
                    <div className="vm-footer-selects">
                        {(storefront?.currencies?.length ?? 0) > 1 && (
                            <select
                                aria-label="Footer currency"
                                value={
                                    storefront?.displayCurrency ??
                                    storefront?.currency
                                }
                                onChange={(e) =>
                                    change('currency', e.target.value)
                                }
                            >
                                {storefront?.currencies?.map((c) => (
                                    <option key={c.code} value={c.code}>
                                        {c.code}
                                    </option>
                                ))}
                            </select>
                        )}
                        {(storefront?.locales?.length ?? 0) > 1 && (
                            <select
                                aria-label="Footer language"
                                value={storefront?.locale}
                                onChange={(e) => change('lang', e.target.value)}
                            >
                                {storefront?.locales.map((l) => (
                                    <option key={l.code} value={l.code}>
                                        {l.name}
                                    </option>
                                ))}
                            </select>
                        )}
                    </div>
                )}
            </div>
        </footer>
    );
}
