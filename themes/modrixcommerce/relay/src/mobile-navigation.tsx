import * as Dialog from '@radix-ui/react-dialog';
import { router, usePage } from '@inertiajs/react';
import {
    ListTree,
    Menu,
    Minus,
    Plus,
    Search,
    ShoppingBag,
    X,
} from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import { StoreMenuLink as Link } from '@/components/storefront/store-menu-link';
import { StoreMenuTree, themeMenus } from '@/components/storefront/store-menu';
import type { CategoryNode } from '@/components/storefront/site-header';
import type { ThemeSectionData } from '@/components/storefront/theme-sections';
import type { StorefrontShared } from '@/types';
import { str } from './components';

type Panel = 'menu' | 'categories' | 'search';

/** Phone navigation, with its bar inside the modal so tabs remain usable. */
export default function MobileNavigation({
    section,
}: {
    section: ThemeSectionData;
}) {
    const { storefront } = usePage<{
        storefront?: StorefrontShared & { categoryTree?: CategoryNode[] };
    }>().props;
    const { url } = usePage();
    const s = section.settings;
    const [panel, setPanel] = useState<Panel | null>(null);
    const [query, setQuery] = useState('');
    const trigger = useRef<HTMLButtonElement | null>(null);
    const searchInput = useRef<HTMLInputElement>(null);
    const id = useId();
    const enabled = s.show_mobile_bar !== false;
    const labels = {
        menu: str(s.mobile_menu_label) || 'Menu',
        categories: str(s.mobile_categories_label) || 'Categories',
        search: str(s.mobile_search_label) || 'Search',
        cart: str(s.mobile_cart_label) || 'Cart',
    };
    const style = {
        '--re-mobile-active': str(s.mobile_active_color) || '#0068ad',
    } as CSSProperties;
    const categories: CategoryNode[] = storefront?.categoryTree?.length
        ? storefront.categoryTree
        : (storefront?.categories ?? []).map((c) => ({
              ...c,
              url: '/c/' + c.slug,
              children: [],
          }));
    // Admin → Navigation's Mobile menu once saved there (header "Menus" setting).
    const menu = themeMenus(storefront?.navigation, s.menu_source).mobile;
    const path = url.split('?')[0];
    const close = () => setPanel(null);

    useEffect(() => {
        const media = window.matchMedia('(max-width: 767px)');
        const resize = () => {
            if (!media.matches) setPanel(null);
        };
        media.addEventListener('change', resize);
        const remove = router.on('navigate', () => setPanel(null));
        return () => {
            media.removeEventListener('change', resize);
            remove();
        };
    }, []);
    useEffect(() => {
        if (!enabled) setPanel(null);
    }, [enabled]);
    useEffect(() => {
        if (panel === 'search') searchInput.current?.focus();
    }, [panel]);

    const bar = (inside = false) => (
        <nav
            className="re-mobile-bar"
            style={style}
            aria-label="Mobile navigation"
        >
            {(
                [
                    { key: 'menu', Icon: Menu },
                    { key: 'categories', Icon: ListTree },
                    { key: 'search', Icon: Search },
                ] as const
            ).map(({ key, Icon }) => (
                <button
                    key={key}
                    type="button"
                    aria-expanded={panel === key}
                    aria-controls={id}
                    data-active={
                        panel === key ||
                        (!panel &&
                            ((key === 'categories' && path.startsWith('/c/')) ||
                                (key === 'search' && path === '/search')))
                    }
                    onClick={(e) => {
                        if (!inside) trigger.current = e.currentTarget;
                        setPanel(panel === key ? null : key);
                    }}
                >
                    <Icon size={28} strokeWidth={1.3} aria-hidden />
                    <span>{labels[key]}</span>
                </button>
            ))}
            <Link
                href="/cart"
                aria-label={
                    labels.cart + ', ' + (storefront?.cartCount ?? 0) + ' items'
                }
                aria-current={path === '/cart' ? 'page' : undefined}
                data-active={!panel && path === '/cart'}
                onClick={close}
            >
                <span className="re-mobile-cart-icon">
                    <ShoppingBag size={28} strokeWidth={1.3} aria-hidden />
                    {(storefront?.cartCount ?? 0) > 0 && (
                        <span className="re-count">
                            {storefront?.cartCount}
                        </span>
                    )}
                </span>
                <span>{labels.cart}</span>
            </Link>
        </nav>
    );

    if (!enabled) return null;
    return (
        <>
            {bar()}
            <Dialog.Root
                open={panel !== null}
                onOpenChange={(open) => {
                    if (!open) close();
                }}
            >
                <Dialog.Portal>
                    <Dialog.Overlay className="re-mobile-overlay" />
                    <Dialog.Content
                        id={id}
                        className="re-shell re-mobile-modal"
                        style={style}
                        onCloseAutoFocus={(e) => {
                            e.preventDefault();
                            trigger.current?.focus();
                        }}
                        onOpenAutoFocus={(e) => {
                            if (panel === 'search') {
                                e.preventDefault();
                                searchInput.current?.focus();
                            }
                        }}
                    >
                        <div className="re-mobile-panel">
                            <div className="re-mobile-panel-heading">
                                <Dialog.Title>
                                    {panel ? labels[panel] : ''}
                                </Dialog.Title>
                                <Dialog.Close aria-label="Close mobile navigation">
                                    <X size={22} strokeWidth={1.5} />
                                </Dialog.Close>
                            </div>
                            <Dialog.Description className="sr-only">
                                Browse the store, explore categories, or search
                                for products.
                            </Dialog.Description>
                            <div className="re-mobile-panel-body">
                                {panel === 'categories' && (
                                    <nav aria-label="Product categories">
                                        {categories.length ? (
                                            <ul className="re-mobile-categories">
                                                {categories.map((c) => (
                                                    <CategoryBranch
                                                        key={c.id}
                                                        category={c}
                                                        onNavigate={close}
                                                    />
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="re-mobile-empty">
                                                No categories are available yet.
                                            </p>
                                        )}
                                        <Link
                                            href="/search"
                                            className="re-mobile-browse"
                                            onClick={close}
                                        >
                                            Browse all products
                                        </Link>
                                    </nav>
                                )}
                                {panel === 'menu' && (
                                    <nav
                                        className="re-mobile-main-menu"
                                        aria-label="Store menu"
                                    >
                                        {menu ? (
                                            <StoreMenuTree
                                                menu={menu}
                                                orientation="vertical"
                                                onNavigate={close}
                                            />
                                        ) : (
                                            section.blocks.map((b) => (
                                                <Link
                                                    key={b.id}
                                                    href={
                                                        str(b.settings.link) ||
                                                        '/search'
                                                    }
                                                    onClick={close}
                                                >
                                                    {str(b.settings.label)}
                                                </Link>
                                            ))
                                        )}
                                        <Link href="/account" onClick={close}>
                                            {str(s.account_label) ||
                                                'My account'}
                                        </Link>
                                    </nav>
                                )}
                                {panel === 'search' && (
                                    <div className="re-mobile-search-content">
                                        <form
                                            role="search"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                close();
                                                router.get(
                                                    '/search',
                                                    query.trim()
                                                        ? { q: query.trim() }
                                                        : {},
                                                );
                                            }}
                                        >
                                            <label htmlFor={id + '-search'}>
                                                {str(s.search_placeholder) ||
                                                    'Search products'}
                                            </label>
                                            <input
                                                ref={searchInput}
                                                id={id + '-search'}
                                                name="q"
                                                type="search"
                                                enterKeyHint="search"
                                                placeholder={str(
                                                    s.search_placeholder,
                                                )}
                                                value={query}
                                                onChange={(e) =>
                                                    setQuery(e.target.value)
                                                }
                                            />
                                            <button
                                                type="submit"
                                                className="re-btn"
                                            >
                                                {str(s.search_button) ||
                                                    'Search'}
                                            </button>
                                        </form>
                                        <Link
                                            href="/search"
                                            className="re-mobile-browse"
                                            onClick={close}
                                        >
                                            Browse all products
                                        </Link>
                                    </div>
                                )}
                            </div>
                        </div>
                        {bar(true)}
                    </Dialog.Content>
                </Dialog.Portal>
            </Dialog.Root>
        </>
    );
}

function CategoryBranch({
    category,
    onNavigate,
}: {
    category: CategoryNode;
    onNavigate: () => void;
}) {
    const [open, setOpen] = useState(false);
    const id = useId();
    return (
        <li>
            <div className="re-mobile-category-row">
                <Link href={category.url} onClick={onNavigate}>
                    {category.name}
                </Link>
                {category.children.length > 0 && (
                    <button
                        type="button"
                        aria-label={
                            (open ? 'Collapse ' : 'Expand ') + category.name
                        }
                        aria-expanded={open}
                        aria-controls={id}
                        onClick={() => setOpen(!open)}
                    >
                        {open ? (
                            <Minus size={21} strokeWidth={1.3} />
                        ) : (
                            <Plus size={21} strokeWidth={1.3} />
                        )}
                    </button>
                )}
            </div>
            {category.children.length > 0 && (
                <ul id={id} hidden={!open}>
                    {category.children.map((c) => (
                        <CategoryBranch
                            key={c.id}
                            category={c}
                            onNavigate={onNavigate}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}
