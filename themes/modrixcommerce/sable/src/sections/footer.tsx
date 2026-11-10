import { usePage, router } from "@inertiajs/react";
import { Instagram, Facebook, Youtube, Globe, LockKeyhole } from "lucide-react";
import { StoreMenuLink as Link } from "@/components/storefront/store-menu-link";
import { blockAttributes, type ThemeSectionProps } from "@/components/storefront/theme-sections";
import { str } from "../utils";
const socialIcons = {
    instagram: Instagram,
    facebook: Facebook,
    youtube: Youtube,
    pinterest: Globe,
    snapchat: Globe,
};
export default function Footer({ section }: ThemeSectionProps) {
    const s = section.settings;
    const { storefront } = usePage().props;
    const links = section.blocks.filter((b) => b.type === "link" && str(b.settings.link));
    const groups = [...new Set(links.map((b) => str(b.settings.group)))];
    const socials = section.blocks.filter((b) => b.type === "social" && str(b.settings.link));
    const payments = section.blocks.filter((b) => b.type === "payment" && str(b.settings.label));
    return (
        <footer className="sa-footer" style={{ background: str(s.background) }}>
            <div className="sa-commerce-frame sa-footer-grid">
                <div className="sa-footer-contact">
                    <Link href="/" className="sa-wordmark">
                        {str(s.brand)}
                    </Link>
                    {str(s.address) && <p>{str(s.address)}</p>}
                    {str(s.body) && <p>{str(s.body)}</p>}
                    {str(s.email) && <a href={"mailto:" + str(s.email)}>{str(s.email)}</a>}
                    {str(s.phone) && (
                        <a href={"tel:" + str(s.phone).replace(/[^\d+]/g, "")}>{str(s.phone)}</a>
                    )}
                </div>
                {groups.map((g) => (
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
            <div className="sa-footer-socials">
                {socials.map((b) => {
                    const Icon =
                        socialIcons[str(b.settings.network) as keyof typeof socialIcons] ?? Globe;
                    return (
                        <Link
                            key={b.id}
                            href={str(b.settings.link)}
                            aria-label={str(b.settings.network)}
                            {...blockAttributes(b)}
                        >
                            <Icon size={20} />
                        </Link>
                    );
                })}
            </div>
            <div className="sa-footer-lower">
                <div className="sa-commerce-frame sa-footer-bottom">
                    {s.show_language !== false && (
                        <label className="sa-language">
                            {str(s.language_label)}
                            <select
                                aria-label="Store language"
                                value={storefront?.locale ?? "en"}
                                onChange={(e) => {
                                    const u = new URL(window.location.href);
                                    u.searchParams.set("lang", e.target.value);
                                    router.get(u.pathname + u.search, {}, { preserveScroll: true });
                                }}
                            >
                                {(storefront?.locales?.length
                                    ? storefront.locales
                                    : [{ code: "en", name: "English" }]
                                ).map((locale) => (
                                    <option key={locale.code} value={locale.code}>
                                        {locale.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    <div className="sa-footer-payments">
                        {payments.length ? (
                            payments.map((b) => (
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
                            ))
                        ) : (
                            <span>
                                <LockKeyhole size={15} /> Secure checkout
                            </span>
                        )}
                    </div>
                    <p className="sa-footer-copyright">
                        © {new Date().getFullYear()} {str(s.copyright)}
                    </p>
                </div>
            </div>
        </footer>
    );
}
