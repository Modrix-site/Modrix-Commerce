import { router } from "@inertiajs/react";
import { PlaceSlot } from "@/components/extensions/place-slot";
import { User, Search, MapPin } from "lucide-react";
import { StoreMenuLink as Link } from "@/components/storefront/footer-menus";
import {
    CartIcon,
    SearchIconLink,
    useShowAccountLink,
    useShowCartCount,
} from "@/components/storefront/page-settings/header-parts";
import { useT } from "@/lib/i18n";
import { useVoltora } from "./components";
import "./style.css";

export default function VoltoraHeader() {
    const { storefront, text, show } = useVoltora();
    const t = useT();
    const showAccount = useShowAccountLink();
    const showCount = useShowCartCount();
    const preference = (key: string, value: string) => {
        const params = new URLSearchParams(window.location.search);
        params.set(key, value);
        router.visit(`${window.location.pathname}?${params}`, {
            preserveScroll: true,
        });
    };
    return (
        <>
            <style>{`.storefront[data-theme="ecomodular/voltora"]{--voltora-width:${Number(text("page_width", "1140"))}px;--voltora-gap:${Number(text("section_gap", "28"))}px;}`}</style>
            <header
                className="voltora-header"
                data-sticky={show("sticky_header")}
            >
                <div className="voltora-header-top">
                    <Link
                        href="/"
                        className="voltora-logo"
                        aria-label={storefront?.storeName}
                    >
                        {text("logo_image") ? (
                            <img src={text("logo_image")} alt={text("brand")} />
                        ) : (
                            <>
                                {text("brand", "Voltora")}
                                <small>✦</small>
                            </>
                        )}
                    </Link>
                    <form
                        action="/search"
                        className="voltora-search"
                        role="search"
                    >
                        <input
                            type="search"
                            name="q"
                            aria-label={t("Search products")}
                            placeholder={text("search_placeholder")}
                        />
                        <button aria-label={t("Search")}>
                            <Search size={18} />
                        </button>
                    </form>
                    <div className="voltora-preferences">
                        {(storefront?.locales.length ?? 0) > 1 && (
                            <select
                                aria-label={t("Language")}
                                value={storefront?.locale}
                                onChange={(e) =>
                                    preference("lang", e.target.value)
                                }
                            >
                                {storefront?.locales.map((locale) => (
                                    <option
                                        key={locale.code}
                                        value={locale.code}
                                    >
                                        {locale.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        {(storefront?.currencies?.length ?? 0) > 1 ? (
                            <select
                                aria-label={t("Currency")}
                                value={storefront?.displayCurrency}
                                disabled={!storefront?.canSwitchCurrency}
                                onChange={(e) =>
                                    preference("currency", e.target.value)
                                }
                            >
                                {storefront?.currencies?.map((currency) => (
                                    <option key={currency.code}>
                                        {currency.code}
                                    </option>
                                ))}
                            </select>
                        ) : (
                            <span>
                                {storefront?.displayCurrency ||
                                    storefront?.currency}
                            </span>
                        )}
                        <PlaceSlot place="header.actions" />
                        <SearchIconLink />
                        <Link href="/account" className={showAccount ? undefined : "store-hidden"}>
                            <User size={16} />
                            <span>{t("Sign in")}</span>
                        </Link>
                        <Link
                            href="/cart"
                            aria-label={`${t("Cart")} (${storefront?.cartCount ?? 0})`}
                        >
                            <CartIcon size={18} fallback="cart" />
                            {showCount && (storefront?.cartCount ?? 0)}
                        </Link>
                    </div>
                </div>
                <nav className="voltora-nav" aria-label={t("Main navigation")}>
                    {storefront?.navigation?.header.links.map((item, i) => (
                        <Link key={i} href={item.url}>
                            {item.label}
                        </Link>
                    ))}
                    {show("show_location") && (
                        <Link href="/cart" className="voltora-location">
                            <MapPin size={12} />
                            {text("location_label")}
                        </Link>
                    )}
                </nav>
            </header>
        </>
    );
}
