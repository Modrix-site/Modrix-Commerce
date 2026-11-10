import { useEffect, useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import {
    Menu,
    ShoppingCart,
    Heart,
    ChartNoAxesColumnIncreasing,
    UserRound,
} from 'lucide-react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import { StoreMenuTree, themeMenus } from '@/components/storefront/store-menu';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetDescription,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { str, useDeviceList, ProductDrawer } from '../components';
import MobileNavigation from '../mobile-navigation';
import { RecentMenu } from '../recent';
export default function Header({ section }: ThemeSectionProps) {
    const { storefront } = usePage().props;
    const s = section.settings;
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState('');
    const [mobile, setMobile] = useState(false);
    const [drawer, setDrawer] = useState<'wishlist' | 'compare' | null>(null);
    const wishlist = useDeviceList('wishlist');
    const compare = useDeviceList('compare');
    // Admin → Navigation menus once saved there (header "Menus" setting).
    const menus = themeMenus(storefront?.navigation, s.menu_source);
    const nav = menus.header;
    const blockLinks = section.blocks;
    const storeNav = nav !== undefined;
    const closeDepartments = () =>
        document
            .querySelectorAll('details.bl-department[open]')
            .forEach((d) => d.removeAttribute('open'));
    const links = (onNavigate?: () => void) =>
        storeNav ? (
            <StoreMenuTree menu={nav} onNavigate={onNavigate} />
        ) : (
            blockLinks.map((b) => (
                <Link
                    key={b.id}
                    href={str(b.settings.link) || '/search'}
                    {...blockAttributes(b)}
                    onClick={onNavigate}
                >
                    {str(b.settings.label)}
                </Link>
            ))
        );
    const sticky = s.sticky !== false;
    const compactOnScroll = sticky && s.compact_on_scroll !== false;
    const [compact, setCompact] = useState(false);
    useEffect(() => {
        if (!compactOnScroll) {
            setCompact(false);
            return;
        }
        // Shrink well past the header, grow back near the top: the gap keeps
        // the header from flickering as its own height changes the page.
        const update = () =>
            setCompact((on) =>
                on ? window.scrollY > 40 : window.scrollY > 200,
            );
        update();
        window.addEventListener('scroll', update, { passive: true });
        return () => window.removeEventListener('scroll', update);
    }, [compactOnScroll]);
    const localize = (key: string, value: string) => {
        const u = new URL(window.location.href);
        u.searchParams.set(key, value);
        router.get(u.pathname + u.search, {}, { preserveScroll: true });
    };
    return (
        <header
            data-sticky={sticky}
            data-compact={compact}
            className={
                'bl-header' +
                (s.show_mobile_bar !== false ? ' bl-has-mobile-bar' : '')
            }
        >
            <div className="bl-frame bl-head-top">
                <Link className="bl-wordmark" href="/">
                    {str(s.logo) ? (
                        <img src={str(s.logo)} alt={str(s.brand)} />
                    ) : str(s.brand) === 'Baby Lane' ? (
                        <>
                            baby<span>lane</span>
                        </>
                    ) : (
                        str(s.brand)
                    )}
                </Link>
                <form
                    className="bl-search"
                    role="search"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            category ? '/c/' + category : '/search',
                            query ? { q: query } : {},
                            { preserveScroll: false },
                        );
                    }}
                >
                    <select
                        aria-label="Search category"
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                    >
                        <option value="">All</option>
                        {storefront?.categories.map((c) => (
                            <option value={c.slug} key={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                    <input
                        aria-label="Search products"
                        name="q"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={str(s.search_placeholder)}
                    />
                    <button type="submit">{str(s.search_button)}</button>
                </form>
                <div className="bl-actions">
                    {s.show_compare !== false && (
                        <button
                            className="bl-icon"
                            type="button"
                            aria-label="Compare products"
                            onClick={() => setDrawer('compare')}
                        >
                            <ChartNoAxesColumnIncreasing size={27} />
                            <span className="bl-count">{compare.length}</span>
                        </button>
                    )}
                    {s.show_wishlist !== false && (
                        <button
                            className="bl-icon"
                            type="button"
                            aria-label="Open wishlist"
                            onClick={() => setDrawer('wishlist')}
                        >
                            <Heart size={27} />
                            <span className="bl-count">{wishlist.length}</span>
                        </button>
                    )}
                    <Link
                        className="bl-icon"
                        href="/cart"
                        aria-label={
                            'Shopping cart, ' +
                            (storefront?.cartCount ?? 0) +
                            ' items'
                        }
                    >
                        <ShoppingCart size={28} />
                        <span className="bl-count">
                            {storefront?.cartCount ?? 0}
                        </span>
                    </Link>
                    <Link href="/account" className="bl-account">
                        <UserRound size={28} />
                        <span>{str(s.account_label)}</span>
                    </Link>
                    <PlaceSlot place="header.actions" />
                </div>
            </div>
            <div className="bl-head-nav">
                <div className="bl-frame bl-nav-row">
                    <Sheet open={mobile} onOpenChange={setMobile}>
                        <SheetTrigger className="bl-icon bl-mobile">
                            <Menu size={18} />
                            <span>{str(s.department_label)}</span>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            className="bl-shell bl-mobile-menu"
                        >
                            <SheetTitle>{str(s.brand)}</SheetTitle>
                            <SheetDescription>
                                Find your next everyday favourite.
                            </SheetDescription>
                            <nav className="bl-mobile-links">
                                {menus.mobile ? (
                                    <StoreMenuTree
                                        menu={menus.mobile}
                                        orientation="vertical"
                                        onNavigate={() => setMobile(false)}
                                    />
                                ) : (
                                    <>
                                        {links(() => setMobile(false))}
                                        <hr />
                                        {storefront?.categories.map((c) => (
                                            <Link
                                                key={c.id}
                                                href={'/c/' + c.slug}
                                                onClick={() => setMobile(false)}
                                            >
                                                {c.name}
                                            </Link>
                                        ))}
                                    </>
                                )}
                                <Link
                                    href="/account"
                                    onClick={() => setMobile(false)}
                                >
                                    My account
                                </Link>
                            </nav>
                        </SheetContent>
                    </Sheet>
                    <details
                        className="bl-department bl-desktop-dept"
                        onKeyDown={(e) => {
                            if (e.key === 'Escape')
                                e.currentTarget.open = false;
                        }}
                    >
                        <summary>
                            <Menu size={17} />
                            {str(s.department_label)}
                        </summary>
                        <nav className="bl-dept-menu">
                            {menus.sidebar ? (
                                <StoreMenuTree
                                    menu={menus.sidebar}
                                    orientation="vertical"
                                    onNavigate={closeDepartments}
                                />
                            ) : (
                                <>
                                    <Link
                                        href="/search"
                                        onClick={(e) => {
                                            e.currentTarget
                                                .closest('details')
                                                ?.removeAttribute('open');
                                        }}
                                    >
                                        All products
                                    </Link>
                                    {storefront?.categories.map((c) => (
                                        <Link
                                            key={c.id}
                                            href={'/c/' + c.slug}
                                            onClick={(e) => {
                                                e.currentTarget
                                                    .closest('details')
                                                    ?.removeAttribute('open');
                                            }}
                                        >
                                            {c.name}
                                        </Link>
                                    ))}
                                </>
                            )}
                        </nav>
                    </details>
                    <nav className="bl-nav-links" aria-label="Main navigation">
                        {s.show_main_menu === true ? (
                            links()
                        ) : (
                            <RecentMenu label={str(s.recent_label)} />
                        )}
                    </nav>
                    <div className="bl-utility">
                        <Link href="/account/orders">Track your order</Link>
                        {s.show_localization !== false && (
                            <>
                                {(storefront?.currencies?.length ?? 0) > 1 && (
                                    <select
                                        aria-label="Display currency"
                                        value={
                                            storefront?.displayCurrency ??
                                            storefront?.currency
                                        }
                                        onChange={(e) =>
                                            localize('currency', e.target.value)
                                        }
                                    >
                                        {storefront?.currencies?.map((c) => (
                                            <option key={c.code} value={c.code}>
                                                {c.code}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {(storefront?.locales?.length ?? 0) > 1 && (
                                    <select
                                        aria-label="Language"
                                        value={storefront?.locale}
                                        onChange={(e) =>
                                            localize('lang', e.target.value)
                                        }
                                    >
                                        {storefront?.locales.map((l) => (
                                            <option key={l.code} value={l.code}>
                                                {l.name}
                                            </option>
                                        ))}
                                    </select>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>
            <ProductDrawer
                kind={drawer ?? 'wishlist'}
                open={drawer !== null}
                onClose={() => setDrawer(null)}
            />
            <MobileNavigation section={section} />
        </header>
    );
}
