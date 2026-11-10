import { OverflowMenuLinks } from '@/components/storefront/overflow-menu';
import { router } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { Menu, Search, User, X } from 'lucide-react';
import { useState } from 'react';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import {
    CartIcon,
    useShowAccountLink,
    useShowCartCount,
} from '@/components/storefront/page-settings/header-parts';
import { useHearth } from './components';
import './style.css';
export default function HearthHeader() {
    const { storefront, text, show } = useHearth();
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
            <style>{`.storefront[data-theme="modrixcommerce/hearth"]{--hearth-width:${Number(text('page_width', '1240'))}px;--hearth-gap:${Number(text('section_gap', '72'))}px;}`}</style>
            {show('show_notice') && (
                <div
                    className="hearth-notice"
                    style={{ background: text('notice_color', '#c7e7fc') }}
                >
                    {text('notice')}
                </div>
            )}
            <header className="hearth-header">
                <div className="hearth-header-top">
                    <Link
                        href="/"
                        className="hearth-wordmark"
                        aria-label={storefront?.storeName}
                    >
                        {text('logo_image') ? (
                            <img src={text('logo_image')} alt={text('brand')} />
                        ) : (
                            <>
                                {text('brand', 'HEARTH')}
                                <small>{text('brand_tagline', 'HOME')}</small>
                            </>
                        )}
                    </Link>
                    <div className="hearth-preferences">
                        {(storefront?.locales.length ?? 0) > 1 && (
                            <select
                                aria-label="Language"
                                value={storefront?.locale}
                                onChange={(e) =>
                                    preference('lang', e.target.value)
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
                                    preference('currency', e.target.value)
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
                            className={showAccount ? undefined : 'store-hidden'}
                        >
                            <User size={17} />
                        </Link>
                        <Link
                            href="/cart"
                            aria-label={`Shopping bag, ${storefront?.cartCount ?? 0} items`}
                        >
                            <CartIcon size={18} />
                            {showCount && (
                                <span>{storefront?.cartCount ?? 0}</span>
                            )}
                        </Link>
                    </div>
                </div>
                <nav className="hearth-nav" aria-label="Main navigation">
                    <button
                        aria-expanded={menu}
                        aria-controls="hearth-catalogue-menu"
                        onClick={() => {
                            setMenu(!menu);
                            setSearch(false);
                        }}
                    >
                        {menu ? <X size={15} /> : <Menu size={15} />} CATALOGUE
                    </button>
                    <OverflowMenuLinks
                        menu={storefront?.navigation?.header}
                        moreLabel="SEE MORE"
                    />
                    <button
                        aria-expanded={search}
                        aria-controls="hearth-search"
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
                        id="hearth-search"
                        className="hearth-search"
                        action="/search"
                        role="search"
                    >
                        <input
                            autoFocus
                            type="search"
                            name="q"
                            placeholder={text('search_placeholder')}
                            aria-label="Search products"
                            required
                        />
                        <button type="submit">SEARCH</button>
                    </form>
                )}
                {menu && (
                    <div id="hearth-catalogue-menu" className="hearth-mega">
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
                        <div className="hearth-mega-categories">
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
