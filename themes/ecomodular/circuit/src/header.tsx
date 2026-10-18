import { router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Clock,
    Heart,
    Menu,
    Search,
    ShoppingCart,
    User,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { CustomerNotificationBell } from '@/components/storefront/customer-notification-bell';
import { StoreMenuLink as Link } from '@/components/storefront/footer-menus';
import {
    useShowAccountLink,
    useShowCartCount,
} from '@/components/storefront/page-settings/header-parts';
import { SearchToggle } from '@/components/storefront/search-toggle';
import {
    CategoryIcon,
    CircuitVariables,
    useCircuit,
    useDeviceList,
} from './components';
import type { SavedProduct } from './components';
import './style.css';

/** Products kept on this device (wishlist or recently viewed), in a side panel. */
function ProductPanel({
    kind,
    open,
    onClose,
}: {
    kind: 'saved' | 'recent';
    open: boolean;
    onClose: () => void;
}) {
    const items = useDeviceList(kind);
    const dialog = useRef<HTMLDialogElement>(null);
    useEffect(() => {
        if (open && !dialog.current?.open) dialog.current?.showModal();
        if (!open && dialog.current?.open) dialog.current.close();
    }, [open]);
    const title = kind === 'saved' ? 'Wishlist' : 'Recently viewed products';
    return (
        <dialog
            ref={dialog}
            className="ci-drawer"
            aria-label={title}
            onClose={onClose}
            onClick={(event) => {
                if (event.target === dialog.current) onClose();
            }}
        >
            <div className="ci-drawer-head">
                <h2>{title}</h2>
                <button type="button" aria-label="Close" onClick={onClose}>
                    <X size={20} />
                </button>
            </div>
            {items.length === 0 ? (
                <p className="ci-muted">
                    {kind === 'saved'
                        ? 'Tap the heart on a product to keep it here.'
                        : 'Products you look at appear here.'}
                </p>
            ) : (
                <ul className="ci-drawer-list">
                    {items.map((item: SavedProduct) => (
                        <li key={item.id}>
                            <Link href={`/p/${item.slug}`} onClick={onClose}>
                                {item.image ? (
                                    <img
                                        src={item.image}
                                        alt=""
                                        loading="lazy"
                                    />
                                ) : (
                                    <span className="ci-drawer-thumb" />
                                )}
                                <span>
                                    <strong>{item.name}</strong>
                                    {item.price && <small>{item.price}</small>}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
            <p className="ci-drawer-note">Kept on this device only.</p>
        </dialog>
    );
}

function Announcements() {
    const { text } = useCircuit();
    const messages = [1, 2, 3]
        .map((i) => text(`announcement_${i}`))
        .filter(Boolean);
    const [index, setIndex] = useState(0);
    useEffect(() => {
        if (messages.length < 2) return;
        const reduce = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
        if (reduce) return;
        const timer = window.setInterval(
            () => setIndex((i) => (i + 1) % messages.length),
            6000,
        );
        return () => window.clearInterval(timer);
    }, [messages.length]);
    if (!messages.length) return <div />;
    const step = (delta: number) =>
        setIndex((i) => (i + delta + messages.length) % messages.length);
    return (
        <div className="ci-announce">
            {messages.length > 1 && (
                <button
                    type="button"
                    aria-label="Previous announcement"
                    onClick={() => step(-1)}
                >
                    <ChevronLeft size={14} />
                </button>
            )}
            {messages.length > 1 && (
                <button
                    type="button"
                    aria-label="Next announcement"
                    onClick={() => step(1)}
                >
                    <ChevronRight size={14} />
                </button>
            )}
            <p aria-live="polite">{messages[index % messages.length]}</p>
        </div>
    );
}

export default function CircuitHeader() {
    const { storefront, text, show } = useCircuit();
    const page = usePage();
    const { auth } = page.props;
    const showAccount = useShowAccountLink();
    const showCount = useShowCartCount();
    const [panel, setPanel] = useState<'saved' | 'recent' | null>(null);
    const [shopOpen, setShopOpen] = useState(false);
    const menu = useRef<HTMLDialogElement>(null);
    const saved = useDeviceList('saved');
    const links = storefront?.navigation?.header.links ?? [];
    const categories = storefront?.categories ?? [];
    const count = storefront?.cartCount ?? 0;
    const brand = text('brand', storefront?.storeName ?? 'circuit');
    const logo = text('logo_image') || storefront?.theme.logo || '';

    const change = (key: string, value: string) => {
        const target = new URL(page.url, window.location.origin);
        target.searchParams.set(key, value);
        router.visit(target.pathname + target.search, { preserveScroll: true });
    };

    const region = (
        <>
            {(storefront?.currencies?.length ?? 0) > 1 ? (
                <select
                    aria-label="Currency"
                    disabled={!storefront?.canSwitchCurrency}
                    value={storefront?.displayCurrency}
                    onChange={(e) => change('currency', e.target.value)}
                >
                    {storefront?.currencies?.map((c) => (
                        <option key={c.code} value={c.code}>
                            {c.code}
                        </option>
                    ))}
                </select>
            ) : (
                <span>
                    {storefront?.displayCurrency ?? storefront?.currency}
                </span>
            )}
            {(storefront?.locales.length ?? 0) > 1 && (
                <select
                    aria-label="Language"
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
        </>
    );

    const search = (
        <form className="ci-search" role="search" action="/search" method="get">
            <Search size={16} aria-hidden />
            <input
                name="q"
                type="search"
                aria-label="Search products"
                placeholder={text('search_placeholder', "I'm looking for…")}
            />
        </form>
    );

    const cart = (
        <Link
            href="/cart"
            className="ci-cart"
            aria-label={`Cart, ${count} items`}
        >
            <span className="ci-icon-badge">
                <ShoppingCart size={22} aria-hidden />
                {showCount && <b>{count}</b>}
            </span>
            <span className="ci-cart-label">Cart</span>
        </Link>
    );

    return (
        <header className="ci-header">
            <CircuitVariables />
            <div className="ci-topbar">
                <div className="ci-frame ci-topbar-inner">
                    <Announcements />
                    <nav className="ci-toplinks" aria-label="Store information">
                        {[1, 2, 3].map((i) =>
                            text(`top_link_${i}_label`) ? (
                                <Link key={i} href={text(`top_link_${i}`, '/')}>
                                    {text(`top_link_${i}_label`)}
                                </Link>
                            ) : null,
                        )}
                        <span className="ci-region">{region}</span>
                    </nav>
                </div>
            </div>
            <div className="ci-mainbar">
                <div className="ci-frame ci-mainbar-inner">
                    <button
                        type="button"
                        className="ci-menu-button"
                        aria-label="Open menu"
                        aria-haspopup="dialog"
                        onClick={() => menu.current?.showModal()}
                    >
                        <Menu size={22} />
                    </button>
                    <Link
                        href="/"
                        className="ci-logo"
                        aria-label={`${brand} home`}
                    >
                        {logo ? <img src={logo} alt={brand} /> : brand}
                    </Link>
                    <div className="ci-search-wrap">{search}</div>
                    <div className="ci-actions">
                        <SearchToggle className="ci-search-toggle">
                            <Search size={22} aria-hidden />
                        </SearchToggle>
                        <PlaceSlot
                            place="header.actions"
                            className="flex items-center gap-1"
                        />
                        {showAccount && (
                            <Link
                                href={auth.user ? '/account' : '/login'}
                                aria-label={
                                    auth.user ? 'My account' : 'Sign in'
                                }
                            >
                                <User size={22} />
                            </Link>
                        )}
                        <CustomerNotificationBell />
                        {show('show_saved') && (
                            <button
                                type="button"
                                className="ci-wish"
                                aria-label={`Wishlist, ${saved.length} saved`}
                                onClick={() => setPanel('saved')}
                            >
                                <span className="ci-icon-badge">
                                    <Heart size={22} aria-hidden />
                                    <b>{saved.length}</b>
                                </span>
                            </button>
                        )}
                        {cart}
                    </div>
                </div>
            </div>
            <div className="ci-menubar">
                <div className="ci-frame ci-menubar-inner">
                    <nav className="ci-menu" aria-label="Main navigation">
                        {categories.length > 0 && (
                            <div
                                className="ci-shop"
                                onMouseLeave={() => setShopOpen(false)}
                            >
                                <button
                                    type="button"
                                    aria-expanded={shopOpen}
                                    aria-controls="ci-shop-menu"
                                    onClick={() => setShopOpen(!shopOpen)}
                                    onMouseEnter={() => setShopOpen(true)}
                                >
                                    {text('shop_menu_label', 'Shop')}{' '}
                                    <ChevronDown size={14} aria-hidden />
                                </button>
                                <div
                                    id="ci-shop-menu"
                                    className="ci-dropdown"
                                    hidden={!shopOpen}
                                >
                                    {categories.map((category) => (
                                        <Link
                                            key={category.id}
                                            href={`/c/${category.slug}`}
                                            onClick={() => setShopOpen(false)}
                                        >
                                            <CategoryIcon
                                                name={category.name}
                                                size={16}
                                            />
                                            {category.name}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}
                        {links.map((item, i) => (
                            <Link key={i} href={item.url}>
                                {item.label}
                            </Link>
                        ))}
                        {text('highlight_label') && (
                            <Link
                                className="ci-menu-highlight"
                                href={text('highlight_link', '/search')}
                            >
                                {text('highlight_label')}
                            </Link>
                        )}
                    </nav>
                    {show('show_recent') && (
                        <button
                            type="button"
                            className="ci-recent"
                            onClick={() => setPanel('recent')}
                        >
                            <Clock size={16} aria-hidden /> Recently Viewed
                            Products
                        </button>
                    )}
                </div>
            </div>
            {show('show_rail') && categories.length > 0 && (
                <nav className="ci-rail" aria-label="Categories">
                    <button
                        type="button"
                        className="ci-rail-menu"
                        aria-label="Open menu"
                        aria-haspopup="dialog"
                        onClick={() => menu.current?.showModal()}
                    >
                        <Menu size={20} />
                    </button>
                    {categories.slice(0, 10).map((category) => (
                        <Link
                            key={category.id}
                            href={`/c/${category.slug}`}
                            aria-label={category.name}
                            title={category.name}
                        >
                            <CategoryIcon name={category.name} size={22} />
                        </Link>
                    ))}
                </nav>
            )}
            <dialog
                ref={menu}
                className="ci-drawer ci-menu-drawer"
                aria-label="Store menu"
                onClick={(event) => {
                    if (event.target === menu.current) menu.current?.close();
                }}
            >
                <div className="ci-drawer-head">
                    <strong>{brand}</strong>
                    <button
                        type="button"
                        aria-label="Close menu"
                        onClick={() => menu.current?.close()}
                    >
                        <X size={20} />
                    </button>
                </div>
                {search}
                <nav aria-label="Mobile navigation">
                    {links.map((item, i) => (
                        <Link
                            key={i}
                            href={item.url}
                            onClick={() => menu.current?.close()}
                        >
                            {item.label}
                        </Link>
                    ))}
                    {text('highlight_label') && (
                        <Link
                            className="ci-menu-highlight"
                            href={text('highlight_link', '/search')}
                            onClick={() => menu.current?.close()}
                        >
                            {text('highlight_label')}
                        </Link>
                    )}
                </nav>
                {categories.length > 0 && (
                    <nav
                        aria-label="Categories"
                        className="ci-drawer-categories"
                    >
                        <h3>{text('shop_menu_label', 'Shop')}</h3>
                        {categories.map((category) => (
                            <Link
                                key={category.id}
                                href={`/c/${category.slug}`}
                                onClick={() => menu.current?.close()}
                            >
                                <CategoryIcon name={category.name} size={16} />
                                {category.name}
                            </Link>
                        ))}
                    </nav>
                )}
                <div className="ci-region ci-drawer-region">{region}</div>
            </dialog>
            <ProductPanel
                kind={panel ?? 'recent'}
                open={panel !== null}
                onClose={() => setPanel(null)}
            />
        </header>
    );
}
