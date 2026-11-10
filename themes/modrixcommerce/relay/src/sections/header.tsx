import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import {
    Menu,
    Heart,
    ShoppingBag,
    Smartphone,
    Tablet,
    Laptop,
    Volume2,
    Gamepad2,
    Headphones,
    Cable,
    UserRound,
} from 'lucide-react';
import type { CategoryNode } from '@/components/storefront/site-header';
import type { StorefrontShared } from '@/types';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import { StoreMenuTree, themeMenus } from '@/components/storefront/store-menu';
import { PlaceSlot } from '@/components/extensions/place-slot';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str, useDeviceList, ProductDrawer } from '../components';
import MobileNavigation from '../mobile-navigation';
const icons = {
    phone: Smartphone,
    tablet: Tablet,
    laptop: Laptop,
    audio: Volume2,
    gaming: Gamepad2,
    headphones: Headphones,
    accessories: Cable,
};
export default function Header({ section }: ThemeSectionProps) {
    const { storefront } = usePage().props;
    const s = section.settings;
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState('');
    const [drawer, setDrawer] = useState<'wishlist' | 'compare' | null>(null);
    const wishlist = useDeviceList('wishlist');
    const sticky = s.sticky !== false;
    const [compact, setCompact] = useState(false);
    useEffect(() => {
        if (!sticky || s.compact_on_scroll === false) {
            setCompact(false);
            return;
        }
        const update = () =>
            setCompact((on) =>
                on ? window.scrollY > 40 : window.scrollY > 200,
            );
        update();
        window.addEventListener('scroll', update, { passive: true });
        return () => window.removeEventListener('scroll', update);
    }, [sticky, s.compact_on_scroll]);
    const categories =
        (
            storefront as
                | (StorefrontShared & { categoryTree?: CategoryNode[] })
                | undefined
        )?.categoryTree ??
        storefront?.categories ??
        [];
    // Admin → Navigation menus once saved there (header "Menus" setting).
    const menus = themeMenus(storefront?.navigation, s.menu_source);
    const nav = menus.header;
    return (
        <header
            className="re-header"
            data-sticky={sticky}
            data-compact={compact}
        >
            <div className="re-frame re-head-top">
                <div className="re-brand-menu">
                    <Link href="/" className="re-wordmark">
                        {str(s.logo) ? (
                            <img src={str(s.logo)} alt={str(s.brand)} />
                        ) : (
                            <>
                                {str(s.brand)}
                                <span>{str(s.suffix)}</span>
                            </>
                        )}
                    </Link>
                    <details
                        className="re-department"
                        onKeyDown={(e) => {
                            if (e.key === 'Escape')
                                e.currentTarget.open = false;
                        }}
                    >
                        <summary aria-label={str(s.department_label)}>
                            <Menu size={24} />
                        </summary>
                        <nav className="re-dept-menu" aria-label="Departments">
                            {menus.sidebar ? (
                                <StoreMenuTree
                                    menu={menus.sidebar}
                                    orientation="vertical"
                                    onNavigate={() =>
                                        document
                                            .querySelectorAll(
                                                'details.re-department[open]',
                                            )
                                            .forEach((d) =>
                                                d.removeAttribute('open'),
                                            )
                                    }
                                />
                            ) : (
                                <>
                                    <Link href="/search">All products</Link>
                                    {categories.map((c) => (
                                        <Link
                                            key={c.id}
                                            href={'/c/' + c.slug}
                                            onClick={(e) =>
                                                e.currentTarget
                                                    .closest('details')
                                                    ?.removeAttribute('open')
                                            }
                                        >
                                            {c.name}
                                        </Link>
                                    ))}
                                </>
                            )}
                        </nav>
                    </details>
                </div>
                <form
                    className="re-search"
                    role="search"
                    onSubmit={(e) => {
                        e.preventDefault();
                        const data = query.trim() ? { q: query.trim() } : {};
                        router.get(
                            category ? '/c/' + category : '/search',
                            data,
                        );
                    }}
                >
                    <select
                        aria-label="Search category"
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                    >
                        <option value="">{str(s.all_label)}</option>
                        {categories.map((c) => (
                            <option value={c.slug} key={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                    <input
                        type="search"
                        aria-label={str(s.search_placeholder)}
                        placeholder={str(s.search_placeholder)}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <button type="submit">{str(s.search_label)}</button>
                </form>
                <div className="re-actions">
                    {s.show_wishlist !== false && (
                        <button
                            type="button"
                            className="re-icon"
                            aria-label="Open wishlist"
                            onClick={() => setDrawer('wishlist')}
                        >
                            <Heart size={29} />
                            <span className="re-count">{wishlist.length}</span>
                        </button>
                    )}
                    <Link
                        className="re-icon"
                        href="/cart"
                        aria-label={
                            'Shopping cart, ' +
                            (storefront?.cartCount ?? 0) +
                            ' items'
                        }
                    >
                        <ShoppingBag size={29} />
                        <span className="re-count">
                            {storefront?.cartCount ?? 0}
                        </span>
                    </Link>
                    <Link
                        className="re-mobile-account re-icon"
                        href="/account"
                        aria-label="My account"
                    >
                        <UserRound size={26} />
                    </Link>
                    <PlaceSlot place="header.actions" />
                </div>
            </div>
            <div className="re-category-bar">
                <nav className="re-frame" aria-label="Main navigation">
                    {nav ? (
                        <StoreMenuTree menu={nav} />
                    ) : (
                        section.blocks.map((b) => {
                            const Icon =
                                icons[
                                    str(b.settings.icon) as keyof typeof icons
                                ] ?? Cable;
                            return (
                                <Link
                                    key={b.id}
                                    href={str(b.settings.link) || '/search'}
                                    {...blockAttributes(b)}
                                >
                                    <Icon size={17} />
                                    {str(b.settings.label)}
                                </Link>
                            );
                        })
                    )}
                </nav>
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
