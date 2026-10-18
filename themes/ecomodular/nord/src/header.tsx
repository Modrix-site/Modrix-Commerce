import { router, usePage } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import {
    ChevronDown,
    Globe,
    Menu,
    Search,
    User,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { CustomerNotificationBell } from '@/components/storefront/customer-notification-bell';
import {
    CartIcon,
    SearchIconLink,
    useShowAccountLink,
    useShowCartCount,
} from '@/components/storefront/page-settings/header-parts';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { useNord } from './components';
import './style.css';

export default function NordHeader() {
    const { storefront, text, show } = useNord();
    const showAccount = useShowAccountLink();
    const showCount = useShowCartCount();
    const page = usePage();
    const { auth } = page.props;
    const [menu, setMenu] = useState(false);
    const links = storefront?.navigation?.header.links ?? [];
    const number = (key: string, fallback: number, min: number, max: number) =>
        Math.max(min, Math.min(max, Number(text(key)) || fallback));
    const switchCurrency = (currency: string) => {
        const url = new URL(window.location.href);
        url.searchParams.set('currency', currency);
        router.visit(url.pathname + url.search, { preserveScroll: true });
    };
    return (
        <header
            className={`nd-header ${show('sticky_header') ? 'nd-sticky' : ''}`}
        >
            <style>{`[data-theme="ecomodular/nord"]{--nd-width:${number('page_width', 1920, 1000, 2000)}px;--nd-gap:${number('section_gap', 64, 24, 100)}px;--nd-hero-height:${number('hero_height', 950, 440, 1100)}px;--nd-mobile-hero:${number('hero_mobile_height', 570, 320, 800)}px;--nd-columns:${number('catalog_columns', 4, 3, 6)}}`}</style>
            {show('show_notice') && (
                <div className="nd-promobar">
                    <div className="nd-frame">
                        <div className="nd-promo-message">
                            <span>{text('notice')}</span>
                            {text('notice_link_label') && (
                                <Link
                                    href={text(
                                        'notice_link',
                                        '/account/orders',
                                    )}
                                >
                                    {text('notice_link_label')}
                                </Link>
                            )}
                        </div>
                        <div className="nd-promo-country">
                            <Globe size={14} aria-hidden />
                            {(storefront?.currencies?.length ?? 0) > 1 ? (
                                <select
                                    aria-label="Store currency"
                                    value={storefront?.displayCurrency}
                                    disabled={!storefront?.canSwitchCurrency}
                                    onChange={(event) =>
                                        switchCurrency(event.target.value)
                                    }
                                >
                                    {storefront?.currencies?.map((currency) => (
                                        <option
                                            key={currency.code}
                                            value={currency.code}
                                        >
                                            {currency.code}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                (storefront?.displayCurrency ??
                                storefront?.currency)
                            )}
                        </div>
                    </div>
                </div>
            )}
            <div className="nd-frame nd-header-main">
                <button
                    className="nd-mobile-menu"
                    type="button"
                    onClick={() => setMenu((v) => !v)}
                    aria-label={menu ? 'Close menu' : 'Open menu'}
                    aria-expanded={menu}
                    aria-controls="nord-mobile-navigation"
                >
                    {menu ? <X size={20} /> : <Menu size={20} />}
                </button>
                <Link className="nd-logo" href="/" aria-label="Home">
                    {text('logo_image') || storefront?.theme.logo ? (
                        <img
                            src={
                                text('logo_image') ||
                                storefront?.theme.logo ||
                                ''
                            }
                            alt={text('brand', 'NORD')}
                        />
                    ) : (
                        text('brand', 'NORD')
                    )}
                </Link>
                <form
                    className="nd-search"
                    role="search"
                    action="/search"
                    method="get"
                >
                    <button type="submit" aria-label="Search">
                        <Search size={18} aria-hidden />
                    </button>
                    <input
                        type="search"
                        name="q"
                        aria-label="Search for products or brands"
                        placeholder={text('search_placeholder')}
                        defaultValue=""
                    />
                    {storefront?.locale && (
                        <input
                            type="hidden"
                            name="lang"
                            value={storefront.locale}
                        />
                    )}
                    {storefront?.displayCurrency && (
                        <input
                            type="hidden"
                            name="currency"
                            value={storefront.displayCurrency}
                        />
                    )}
                </form>
                <div className="nd-header-actions">
                    <button
                        className="nd-region-button"
                        type="button"
                        onClick={() => setMenu((v) => !v)}
                        aria-label="Region and language"
                        aria-expanded={menu}
                        aria-controls="nord-mobile-navigation"
                    >
                        <Globe size={18} />
                    </button>
                    <PlaceSlot place="header.actions" />
                    <SearchIconLink />
                    <Link
                        href={auth.user ? '/account' : '/login'}
                        className={showAccount ? undefined : 'store-hidden'}
                    >
                        <User size={18} aria-hidden />
                        <span>{auth.user ? 'Account' : 'Sign In'}</span>
                        <ChevronDown size={12} aria-hidden />
                    </Link>
                    <CustomerNotificationBell />
                    <Link
                        href="/cart"
                        aria-label={`Shopping bag, ${storefront?.cartCount ?? 0} items`}
                    >
                        <CartIcon size={20} />
                        {showCount && <span>{storefront?.cartCount ?? 0}</span>}
                    </Link>
                </div>
            </div>
            <nav className="nd-frame nd-nav" aria-label="Main navigation">
                {links.map((item, i) => (
                    <Link
                        key={`${item.url}-${i}`}
                        href={item.url}
                        className={
                            item.label.toLowerCase() === 'sale'
                                ? 'nd-sale'
                                : undefined
                        }
                    >
                        {item.label}
                    </Link>
                ))}
            </nav>
            <nav
                id="nord-mobile-navigation"
                className="nd-mobile-navigation nd-frame"
                aria-label="Mobile navigation"
                hidden={!menu}
                onKeyDown={(event) => {
                    if (event.key === 'Escape') setMenu(false);
                }}
            >
                {links.map((item, i) => (
                    <Link
                        key={`${item.url}-${i}`}
                        href={item.url}
                        onClick={() => setMenu(false)}
                    >
                        {item.label}
                    </Link>
                ))}
                {(storefront?.currencies?.length ?? 0) > 1 && (
                    <label>
                        Currency
                        <select
                            aria-label="Store currency"
                            value={storefront?.displayCurrency}
                            disabled={!storefront?.canSwitchCurrency}
                            onChange={(event) =>
                                switchCurrency(event.target.value)
                            }
                        >
                            {storefront?.currencies?.map((c) => (
                                <option key={c.code} value={c.code}>
                                    {c.code} — {c.name}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
                {(storefront?.locales?.length ?? 0) > 1 && (
                    <div className="nd-language-links">
                        {storefront?.locales.map((locale) => {
                            const destination = new URL(
                                page.url,
                                'https://store.example',
                            );
                            destination.searchParams.set('lang', locale.code);
                            return (
                                <Link
                                    key={locale.code}
                                    href={
                                        destination.pathname +
                                        destination.search
                                    }
                                    hrefLang={locale.code}
                                >
                                    {locale.name}
                                </Link>
                            );
                        })}
                    </div>
                )}
            </nav>
        </header>
    );
}
