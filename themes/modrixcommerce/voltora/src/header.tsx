import { StoreMenuTree } from '@/components/storefront/store-menu';
import { router, usePage } from '@inertiajs/react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { User, Search, MapPin, ShoppingBag, ShoppingCart } from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import { SearchIconLink } from '@/components/storefront/page-settings/header-parts';
import { useT } from '@/lib/i18n';
import { useVoltora } from './components';
import './style.css';

export default function VoltoraHeader() {
    const { storefront, text, show } = useVoltora();
    const t = useT();
    const signedIn = usePage().props.auth?.user != null;
    const cartIsBag = text('cart_icon', 'cart') === 'bag';
    const CartGlyph = cartIsBag ? ShoppingBag : ShoppingCart;
    const topLink = text('top_link_label').trim();
    const hex = (key: string) =>
        /^#[0-9a-f]{6}$/i.test(text(key)) ? text(key) : '';
    const barBackground = hex('bar_background');
    const barText = hex('bar_text');
    const navBackground = hex('nav_background');
    const navText = hex('nav_text');
    const preference = (key: string, value: string) => {
        const params = new URLSearchParams(window.location.search);
        params.set(key, value);
        router.visit(`${window.location.pathname}?${params}`, {
            preserveScroll: true,
        });
    };
    return (
        <>
            <style>{`.storefront[data-theme="modrixcommerce/voltora"]{--voltora-width:${Number(text('page_width', '1140'))}px;--voltora-gap:${Number(text('section_gap', '28'))}px;${barBackground ? `--voltora-bar-bg:${barBackground};` : ''}${barText ? `--voltora-bar-fg:${barText};` : ''}${navBackground ? `--voltora-nav-bg:${navBackground};` : ''}${navText ? `--voltora-nav-fg:${navText};` : ''}}`}</style>
            <header
                className="voltora-header"
                data-sticky={show('sticky_header')}
            >
                <div className="voltora-header-top">
                    <Link
                        href="/"
                        className="voltora-logo"
                        aria-label={storefront?.storeName}
                    >
                        {text('logo_image') ? (
                            <img src={text('logo_image')} alt={text('brand')} />
                        ) : (
                            <>
                                {text('brand', 'Voltora')}
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
                            aria-label={t('Search products')}
                            placeholder={text('search_placeholder')}
                        />
                        <button aria-label={t('Search')}>
                            <Search size={18} />
                        </button>
                    </form>
                    <div className="voltora-preferences">
                        {show('show_language') &&
                            (storefront?.locales.length ?? 0) > 1 && (
                                <select
                                    aria-label={t('Language')}
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
                        {show('show_currency') &&
                            ((storefront?.currencies?.length ?? 0) > 1 ? (
                                <select
                                    aria-label={t('Currency')}
                                    value={storefront?.displayCurrency}
                                    disabled={!storefront?.canSwitchCurrency}
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
                            ))}
                        <PlaceSlot place="header.actions" />
                        <SearchIconLink />
                        {topLink !== '' && (
                            <Link href={text('top_link') || '/'}>
                                {topLink}
                            </Link>
                        )}
                        {show('show_account') && (
                            <Link href="/account">
                                <User size={16} />
                                <span>
                                    {signedIn
                                        ? t('Account')
                                        : t(text('account_label', 'Sign in'))}
                                </span>
                            </Link>
                        )}
                        <Link
                            href="/cart"
                            aria-label={`${t('Cart')} (${storefront?.cartCount ?? 0})`}
                        >
                            <CartGlyph size={18} />
                            {show('show_cart_count') &&
                                (storefront?.cartCount ?? 0)}
                        </Link>
                    </div>
                </div>
                {show('show_navigation') && (
                    <nav
                        className="voltora-nav"
                        aria-label={t('Main navigation')}
                    >
                        <StoreMenuTree menu={storefront?.navigation?.header} />
                        {show('show_location') && (
                            <Link href="/cart" className="voltora-location">
                                <MapPin size={12} />
                                {text('location_label')}
                            </Link>
                        )}
                    </nav>
                )}
            </header>
        </>
    );
}
