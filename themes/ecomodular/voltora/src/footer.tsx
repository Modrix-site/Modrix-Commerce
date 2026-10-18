import { StoreMenuLink as Link } from "@/components/storefront/footer-menus";
import { ExtensionSlot } from "@/components/extensions/extension-slot";
import { useT } from "@/lib/i18n";
import { useVoltora } from "./components";
import "./style.css";

export default function VoltoraFooter() {
    const { storefront, text, show } = useVoltora();
    const t = useT();
    const content = storefront?.theme.content ?? {};
    return (
        <footer className="voltora-footer">
            <div className="voltora-newsletter">
                <ExtensionSlot
                    items={storefront?.footerSlots?.filter(
                        (item) =>
                            show("footer_newsletter") ||
                            !item.component.includes("/newsletter/"),
                    )}
                    extraProps={{ heading: text("newsletter_heading") }}
                />
            </div>
            <div className="voltora-help">
                <Link href="/account">{t("Need help? Contact our team")}</Link>
                <Link href={content.help_link || "/account"}>
                    {t("Store information")}
                </Link>
                {content.footer_email && (
                    <a href={`mailto:${content.footer_email}`}>
                        {content.footer_email}
                    </a>
                )}
                {content.footer_phone && (
                    <a href={`tel:${content.footer_phone}`}>
                        {content.footer_phone}
                    </a>
                )}
            </div>
            <div className="voltora-wrap voltora-footer-grid">
                {storefront?.navigation?.footer.map((menu, i) => (
                    <nav key={i} aria-label={menu.title}>
                        <h3>{menu.title}</h3>
                        {menu.links.map((link, j) => (
                            <Link key={j} href={link.url}>
                                {link.label}
                            </Link>
                        ))}
                    </nav>
                ))}
                <nav aria-label={t("Payment methods")}>
                    <h3>{t("Payment methods")}</h3>
                    <Link href="/cart">{t("Secure checkout")}</Link>
                    <Link href="/account/orders">{t("Your orders")}</Link>
                </nav>
                <nav aria-label={t("Connect with us")}>
                    <h3>{t("Connect with us")}</h3>
                    <Link href="/account">{t("My account")}</Link>
                    {content.footer_email && (
                        <a href={`mailto:${content.footer_email}`}>
                            {t("Contact us")}
                        </a>
                    )}
                </nav>
            </div>
            <div className="voltora-bottom">{text("footer_copyright")}</div>
        </footer>
    );
}
