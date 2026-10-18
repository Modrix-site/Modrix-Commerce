import { router } from "@inertiajs/react";
import { PlaceSlot } from '@/components/extensions/place-slot';
import { Menu, Search, User, X } from "lucide-react";
import { useState } from "react";
import { StoreMenuLink as Link } from "@/components/storefront/footer-menus";
import {
    CartIcon,
    SearchIconLink,
    useShowAccountLink,
    useShowCartCount,
} from "@/components/storefront/page-settings/header-parts";
import { useVellaro } from "./components";
import "./style.css";
export default function VellaroHeader() {
    const { storefront, text, show } = useVellaro();
    const showAccount = useShowAccountLink();
    const showCount = useShowCartCount();
    const [menu, setMenu] = useState(false);
    const [search, setSearch] = useState(false);
    const preference = (key: string, value: string) => {
        const params = new URLSearchParams(window.location.search);
        params.set(key, value);
        router.visit(`${window.location.pathname}?${params}`, {
            preserveScroll: true,
        });
    };
    return (
        <>
            <style>{`.storefront[data-theme="ecomodular/vellaro"]{--vellaro-width:${Number(text("page_width", "1800"))}px;--vellaro-gap:${Number(text("section_gap", "32"))}px;}`}</style>
            {show("show_notice") && (
                <div
                    className="vellaro-notice"
                    style={{ background: text("notice_color", "#181616") }}
                >
                    {text("notice")}
                </div>
            )}
            <header className="vellaro-header">
                <div className="vellaro-header-top">
                    <div className="vellaro-benefits">
                        {[1, 2, 3].map((i) => (
                            <span key={i}>♧ {text(`header_benefit_${i}`)}</span>
                        ))}
                    </div>
                    <Link
                        href="/"
                        className="vellaro-wordmark"
                        aria-label={storefront?.storeName}
                    >
                        {text("logo_image") ? (
                            <img src={text("logo_image")} alt={text("brand")} />
                        ) : (
                            <>
                                {text("brand", "VELLARO")}
                                <small>{text("brand_tagline", "")}</small>
                            </>
                        )}
                    </Link>
                    <div className="vellaro-preferences">
                        {(storefront?.locales.length ?? 0) > 1 && (
                            <select
                                aria-label="Language"
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
                                aria-label="Currency"
                                disabled={!storefront?.canSwitchCurrency}
                                value={storefront?.displayCurrency}
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
                        <Link
                            href="/account"
                            aria-label="My account"
                            className={showAccount ? undefined : "store-hidden"}
                        >
                            <User size={17} />
                        </Link>
                        <Link
                            href="/cart"
                            aria-label={`Shopping bag, ${storefront?.cartCount ?? 0} items`}
                        >
                            <CartIcon size={18} />
                            {showCount && <span>{storefront?.cartCount ?? 0}</span>}
                        </Link>
                    </div>
                </div>
                <nav className="vellaro-nav" aria-label="Main navigation">
                    <button
                        aria-expanded={menu}
                        aria-controls="vellaro-catalogue-menu"
                        onClick={() => {
                            setMenu(!menu);
                            setSearch(false);
                        }}
                    >
                        {menu ? <X size={15} /> : <Menu size={15} />} All items
                    </button>
                    {storefront?.navigation?.header.links.map((item, i) => (
                        <Link key={i} href={item.url}>
                            {item.label}
                        </Link>
                    ))}
                    <button
                        aria-expanded={search}
                        aria-controls="vellaro-search"
                        onClick={() => {
                            setSearch(!search);
                            setMenu(false);
                        }}
                    >
                        <Search size={14} /> SEARCH
                    </button>
                </nav>
                {search && (
                    <form
                        id="vellaro-search"
                        className="vellaro-search"
                        action="/search"
                        role="search"
                    >
                        <input
                            autoFocus
                            type="search"
                            name="q"
                            placeholder={text("search_placeholder")}
                            aria-label="Search products"
                            required
                        />
                        <button type="submit">SEARCH</button>
                    </form>
                )}
                {menu && (
                    <div id="vellaro-catalogue-menu" className="vellaro-mega">
                        <div>
                            <h2>Catalogue</h2>
                            <Link href="/search" onClick={() => setMenu(false)}>
                                All products
                            </Link>
                            <Link
                                href="/search?sort=newest"
                                onClick={() => setMenu(false)}
                            >
                                New arrivals
                            </Link>
                        </div>
                        <div className="vellaro-mega-categories">
                            {storefront?.categories.map((category) => (
                                <Link
                                    key={category.id}
                                    href={`/c/${category.slug}`}
                                    onClick={() => setMenu(false)}
                                >
                                    {category.name}
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </header>
        </>
    );
}
