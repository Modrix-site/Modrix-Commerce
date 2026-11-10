import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import {
    Search,
    ShoppingBag,
    UserRound,
    Heart,
    Menu,
    Leaf,
} from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import { StoreMenuTree, themeMenus } from '@/components/storefront/store-menu';
import { PlaceSlot } from '@/components/extensions/place-slot';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str, ProductDrawer, useDeviceList } from '../components';
import MobileNavigation from '../mobile-navigation';
export default function Header({ section }: ThemeSectionProps) {
    const { storefront } = usePage().props;
    const s = section.settings;
    const [q, setQ] = useState('');
    const [wish, setWish] = useState(false);
    const [mobile, setMobile] = useState(false);
    const saved = useDeviceList('wishlist');
    const menus = themeMenus(storefront?.navigation, s.menu_source);
    const sticky = s.sticky !== false;
    const compactOnScroll = sticky && s.compact_on_scroll !== false;
    const [compact, setCompact] = useState(false);
    useEffect(() => {
        if (!compactOnScroll) {
            setCompact(false);
            return;
        }
        // Hysteresis: shrink past 200px, grow back above 40px, so the
        // header's own height change can't make it flicker.
        const update = () =>
            setCompact((on) =>
                on ? window.scrollY > 40 : window.scrollY > 200,
            );
        update();
        window.addEventListener('scroll', update, { passive: true });
        return () => window.removeEventListener('scroll', update);
    }, [compactOnScroll]);
    return (
        <header
            className="lv-header"
            data-sticky={sticky}
            data-compact={compact}
            style={{ background: str(s.background), color: str(s.foreground) }}
        >
            <div className="lv-frame lv-header-main">
                <button
                    className="lv-icon lv-header-menu"
                    type="button"
                    aria-label="Open navigation"
                    aria-expanded={mobile}
                    onClick={() => setMobile(!mobile)}
                >
                    <Menu size={24} />
                </button>
                <form
                    role="search"
                    className="lv-search"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get('/search', q.trim() ? { q: q.trim() } : {});
                    }}
                >
                    <input
                        type="search"
                        placeholder={str(s.search_placeholder)}
                        aria-label={str(s.search_placeholder)}
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                    <button aria-label="Search products">
                        <Search size={20} />
                    </button>
                </form>
                <Link href="/" className="lv-wordmark">
                    {str(s.logo) ? (
                        <img src={str(s.logo)} alt={str(s.brand)} />
                    ) : (
                        <>
                            <Leaf size={28} strokeWidth={1.2} />
                            <span>{str(s.brand)}</span>
                        </>
                    )}
                </Link>
                <div className="lv-header-actions">
                    {s.show_language !== false &&
                        (storefront?.locales?.length ?? 0) > 1 && (
                            <select
                                aria-label="Language"
                                value={storefront?.locale}
                                onChange={(e) => {
                                    const u = new URL(window.location.href);
                                    u.searchParams.set('lang', e.target.value);
                                    router.get(u.pathname + u.search);
                                }}
                            >
                                {storefront?.locales.map((l) => (
                                    <option key={l.code} value={l.code}>
                                        {l.name}
                                    </option>
                                ))}
                            </select>
                        )}
                    {s.show_wishlist === true && (
                        <button
                            className="lv-icon"
                            aria-label="Open wishlist"
                            type="button"
                            onClick={() => setWish(true)}
                        >
                            <Heart size={21} />
                            {saved.length > 0 && (
                                <span className="lv-count">{saved.length}</span>
                            )}
                        </button>
                    )}
                    <Link
                        href="/account"
                        className="lv-icon"
                        aria-label={str(s.account_label) || 'My account'}
                    >
                        <UserRound size={21} />
                    </Link>
                    <Link
                        href="/cart"
                        className="lv-icon"
                        aria-label={
                            'Shopping cart, ' +
                            (storefront?.cartCount ?? 0) +
                            ' items'
                        }
                    >
                        <ShoppingBag size={22} />
                        {(storefront?.cartCount ?? 0) > 0 && (
                            <span className="lv-count">
                                {storefront?.cartCount}
                            </span>
                        )}
                    </Link>
                    <PlaceSlot place="header.actions" />
                </div>
            </div>
            <nav
                className={'lv-frame lv-nav' + (mobile ? ' lv-nav-open' : '')}
                aria-label="Main navigation"
            >
                {menus.header ? (
                    <StoreMenuTree
                        menu={menus.header}
                        onNavigate={() => setMobile(false)}
                    />
                ) : (
                    section.blocks.map((b) => (
                        <Link
                            key={b.id}
                            href={str(b.settings.link) || '/search'}
                            {...blockAttributes(b)}
                            onClick={() => setMobile(false)}
                        >
                            {str(b.settings.label)}
                        </Link>
                    ))
                )}
            </nav>
            <ProductDrawer
                kind="wishlist"
                open={wish}
                onClose={() => setWish(false)}
            />
            <MobileNavigation section={section} />
        </header>
    );
}
