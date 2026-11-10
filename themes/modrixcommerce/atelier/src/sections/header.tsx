import { useEffect, useState } from 'react';
import { router, usePage, Link } from '@inertiajs/react';
import '../style.css';
import {
    Search,
    ShoppingBag,
    UserRound,
    Heart,
    Menu,
    X,
    ArrowRight,
} from 'lucide-react';
import { StoreMenuTree, themeMenus } from '@/components/storefront/store-menu';
import { PlaceSlot } from '@/components/extensions/place-slot';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str, assetUrl, WishlistDrawer, useDeviceList } from '../components';

export default function Header({ section }: ThemeSectionProps) {
    const { storefront } = usePage().props;
    const s = section.settings;
    const [searchOpen, setSearchOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [wishlistOpen, setWishlistOpen] = useState(false);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [compact, setCompact] = useState(false);

    const saved = useDeviceList('wishlist');
    const menus = themeMenus(storefront?.navigation, s.menu_source);
    const sticky = s.sticky !== false;
    const compactOnScroll = sticky && s.compact_on_scroll !== false;

    useEffect(() => {
        if (!compactOnScroll) {
            setCompact(false);
            return;
        }
        const onScroll = () => {
            setCompact((current) => (current ? window.scrollY > 40 : window.scrollY > 160));
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, [compactOnScroll]);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSearchOpen(false);
        router.get('/search', searchQuery.trim() ? { q: searchQuery.trim() } : {});
    };

    return (
        <header
            className="at-header-wrap at-header"
            data-sticky={sticky}
            data-compact={compact}
        >
            {s.announcement_enabled !== false && str(s.announcement_text) && (
                <div className="at-announcement">
                    <Link href={str(s.announcement_link, '/search')}>
                        <span>{str(s.announcement_text)}</span>
                        <ArrowRight size={12} />
                    </Link>
                </div>
            )}

            <div className="at-frame at-header-row">
                <div className="at-header-utility">
                    <button
                        className="at-icon-btn lg:hidden"
                        type="button"
                        aria-label="Open menu"
                        onClick={() => setMobileNavOpen(true)}
                    >
                        <Menu size={20} />
                    </button>

                    {s.show_language !== false && (storefront?.locales?.length ?? 0) > 1 && (
                        <select
                            aria-label="Language"
                            value={storefront?.locale}
                            className="bg-transparent border-none text-[11px] uppercase tracking-wider cursor-pointer"
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

                    <span className="hidden sm:inline">AE / AED</span>
                </div>

                <div className="at-header-logo">
                    <Link href="/">
                        {str(s.logo) ? (
                            <img
                                src={assetUrl(s.logo)}
                                alt={str(s.brand, 'ATELIER')}
                                style={{ width: Number(s.logo_width) || 160 }}
                            />
                        ) : (
                            str(s.brand, 'ATELIER')
                        )}
                    </Link>
                </div>

                <div className="at-header-actions">
                    {s.show_search !== false && (
                        <button
                            className="at-icon-btn"
                            type="button"
                            aria-label="Search collection"
                            onClick={() => setSearchOpen(!searchOpen)}
                        >
                            <Search size={18} />
                        </button>
                    )}

                    {s.show_account !== false && (
                        <Link
                            className="at-icon-btn"
                            href="/account"
                            aria-label={str(s.account_label, 'Account')}
                        >
                            <UserRound size={18} />
                        </Link>
                    )}

                    {s.show_wishlist !== false && (
                        <button
                            className="at-icon-btn"
                            type="button"
                            aria-label={str(s.wishlist_label, 'Favourites')}
                            onClick={() => setWishlistOpen(true)}
                        >
                            <Heart size={18} />
                            {saved.length > 0 && (
                                <span className="at-icon-count">{saved.length}</span>
                            )}
                        </button>
                    )}

                    <Link
                        className="at-icon-btn"
                        href="/cart"
                        aria-label={str(s.cart_label, 'Shopping bag')}
                    >
                        <ShoppingBag size={18} />
                        {(storefront?.cartCount ?? 0) > 0 && (
                            <span className="at-icon-count">{storefront?.cartCount}</span>
                        )}
                    </Link>

                    <PlaceSlot place="header.actions" />
                </div>
            </div>

            {/* Main Navigation */}
            <nav className="at-nav" aria-label="Main navigation">
                {menus.header ? (
                    <StoreMenuTree
                        menu={menus.header}
                        onNavigate={() => setMobileNavOpen(false)}
                    />
                ) : (
                    section.blocks.map((b) => (
                        <Link
                            key={b.id}
                            href={str(b.settings.link, '/search')}
                            {...blockAttributes(b)}
                        >
                            {str(b.settings.label)}
                        </Link>
                    ))
                )}
            </nav>

            {/* Dropdown Search Bar */}
            {searchOpen && (
                <div className="at-frame py-3 border-t border-neutral-200 bg-white">
                    <form
                        onSubmit={handleSearchSubmit}
                        className="flex items-center gap-3 max-w-2xl mx-auto"
                    >
                        <input
                            type="search"
                            autoFocus
                            placeholder={str(s.search_placeholder, 'Search the collection')}
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="flex-1 py-2 px-3 border border-neutral-300 text-sm focus:outline-none focus:border-neutral-900"
                        />
                        <button
                            type="submit"
                            className="at-btn at-btn-primary px-5 h-[38px] bg-black text-white hover:bg-neutral-800"
                        >
                            <Search size={14} className="mr-1 inline" />
                            Search
                        </button>
                        <button
                            type="button"
                            className="at-icon-btn"
                            onClick={() => setSearchOpen(false)}
                        >
                            <X size={18} />
                        </button>
                    </form>
                </div>
            )}

            {/* Mobile Navigation Drawer */}
            {mobileNavOpen && (
                <div className="fixed inset-0 z-50 flex">
                    <div
                        className="fixed inset-0 bg-black/40 backdrop-blur-sm"
                        onClick={() => setMobileNavOpen(false)}
                    />
                    <div className="relative w-4/5 max-w-xs bg-white h-full shadow-2xl flex flex-col p-6 z-10">
                        <div className="flex justify-between items-center pb-4 border-b border-neutral-200 mb-6">
                            <span className="font-medium tracking-widest text-sm uppercase">
                                {str(s.brand, 'ATELIER')}
                            </span>
                            <button
                                type="button"
                                className="at-icon-btn"
                                onClick={() => setMobileNavOpen(false)}
                            >
                                <X size={20} />
                            </button>
                        </div>
                        <div className="flex flex-col gap-4 overflow-y-auto">
                            {section.blocks.map((b) => (
                                <Link
                                    key={b.id}
                                    href={str(b.settings.link, '/search')}
                                    onClick={() => setMobileNavOpen(false)}
                                    className="text-sm uppercase tracking-wider py-2 border-b border-neutral-100"
                                >
                                    {str(b.settings.label)}
                                </Link>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            <WishlistDrawer
                open={wishlistOpen}
                onClose={() => setWishlistOpen(false)}
            />
        </header>
    );
}
