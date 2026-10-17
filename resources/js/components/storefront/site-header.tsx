import { Form, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    Globe,
    Menu,
    Search,
    User,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { CustomerNotificationBell } from '@/components/storefront/customer-notification-bell';
import {
    CartIcon,
    SearchIconLink,
    useShowAccountLink,
    useShowCartCount,
} from '@/components/storefront/page-settings/header-parts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCompactOnScroll } from '@/hooks/use-compact-on-scroll';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard, home, login } from '@/routes';
import { show as cartShow } from '@/routes/cart';
import { category, search } from '@/routes/storefront';
import type { StorefrontShared } from '@/types';

type NavLink = { label: string; url: string; category_id?: number | null };

/** A category with its subcategories (up to three levels). */
export type CategoryNode = {
    id: number;
    name: string;
    slug: string;
    url: string;
    children: CategoryNode[];
};

/** Shared storefront props, including the optional menu and currencies. */
type Shared = StorefrontShared & {
    navigation?: { header: { links: NavLink[] } };
    categoryTree?: CategoryNode[];
    currencies?: { code: string; name: string }[];
    displayCurrency?: string;
    canSwitchCurrency?: boolean;
};

function useShared() {
    const { storefront, auth } = usePage<{
        storefront?: Shared;
        auth: { user: unknown; isStaff?: boolean };
    }>().props;
    // The header menu, or the top categories when no menu is set up.
    const links: NavLink[] =
        storefront?.navigation?.header.links ??
        (storefront?.categories ?? []).map((c) => ({
            label: c.name,
            url: category(c.slug).url,
        }));

    return { storefront, auth, links, tree: storefront?.categoryTree ?? [] };
}

/** The category a menu link points to (by id, or by its /c/ address). */
function findCategory(
    tree: CategoryNode[],
    link: NavLink,
): CategoryNode | undefined {
    for (const node of tree) {
        if (
            (link.category_id != null && node.id === link.category_id) ||
            node.url === link.url ||
            category(node.slug).url === link.url
        ) {
            return node;
        }

        const found = findCategory(node.children, link);

        if (found) {
            return found;
        }
    }

    return undefined;
}

/** Changes a query parameter (lang, currency) and reloads the page. */
function visitWith(key: string, value: string) {
    const params = new URLSearchParams(window.location.search);
    params.set(key, value);
    router.visit(`${window.location.pathname}?${params.toString()}`, {
        preserveScroll: true,
    });
}

/**
 * Wraps every theme's header. The header stays at the top of the page;
 * after scrolling it shrinks to a slim bar. Below 1024px every theme uses
 * the same mobile header: ☰ menu on the left, logo, then search, account
 * and cart on the right.
 */
export function SiteHeader({ children }: { children: ReactNode }) {
    const compact = useCompactOnScroll();

    return (
        <>
            <MobileNotice />
            <div
                className={cn(
                    'sticky top-0 z-40 bg-background transition-shadow motion-reduce:transition-none',
                    compact && 'shadow-md',
                )}
                data-compact={compact ? '' : undefined}
            >
                {/* Kept mounted (only hidden) so theme headers do not reload. */}
                <div className={cn('hidden', !compact && 'lg:block')}>
                    {children}
                </div>
                {compact && <CompactBar />}
                <MobileHeader compact={compact} />
            </div>
        </>
    );
}

/**
 * Phones: the theme's announcement bar (themes whose own header carries it,
 * e.g. Hearth). It sits above the sticky header and scrolls away with the
 * page while the header stays.
 */
function MobileNotice() {
    const { storefront } = useShared();
    const settings =
        (
            storefront?.theme as
                | { settings?: Record<string, unknown> }
                | undefined
        )?.settings ?? {};
    const notice = typeof settings.notice === 'string' ? settings.notice : '';

    if (notice === '' || settings.show_notice === false) {
        return null;
    }

    const background =
        typeof settings.notice_color === 'string'
            ? settings.notice_color
            : undefined;

    return (
        <div
            className="bg-primary px-4 py-2 text-center text-xs text-primary-foreground lg:hidden"
            style={
                background
                    ? { background, color: readableOn(background) }
                    : undefined
            }
        >
            {notice}
        </div>
    );
}

/** Black or white text, whichever reads better on a hex background. */
function readableOn(hex: string): string | undefined {
    const match = /^#?([0-9a-f]{6})$/i.exec(hex.trim());

    if (!match) {
        return undefined;
    }

    const value = parseInt(match[1], 16);
    const luminance =
        (0.299 * ((value >> 16) & 255) +
            0.587 * ((value >> 8) & 255) +
            0.114 * (value & 255)) /
        255;

    return luminance > 0.6 ? '#111111' : '#ffffff';
}

function Brand({ small = false }: { small?: boolean }) {
    const { storefront } = useShared();
    // Themes with their own wordmark or logo setting (e.g. Hearth) win.
    const settings =
        (
            storefront?.theme as
                | { settings?: Record<string, unknown> }
                | undefined
        )?.settings ?? {};
    const setting = (key: string) =>
        typeof settings[key] === 'string' && settings[key] !== ''
            ? (settings[key] as string)
            : null;
    const name = setting('brand') ?? storefront?.storeName ?? '';
    const logo = storefront?.theme.logo ?? setting('logo_image');

    return (
        <Link
            href={home()}
            className={cn(
                'block truncate font-semibold tracking-wide transition-all motion-reduce:transition-none',
                small ? 'text-base' : 'text-lg',
            )}
            aria-label={logo ? name : undefined}
        >
            {logo ? (
                <img
                    src={logo}
                    alt=""
                    className={cn(
                        'w-auto max-w-40 transition-all motion-reduce:transition-none',
                        small ? 'h-6' : 'h-8',
                    )}
                />
            ) : (
                name
            )}
        </Link>
    );
}

function Icons({ withSearch = true }: { withSearch?: boolean }) {
    const { storefront, auth } = useShared();
    const t = useT();
    const count = storefront?.cartCount ?? 0;
    const showAccount = useShowAccountLink();
    const showCount = useShowCartCount();

    return (
        <div className="flex items-center justify-end gap-0.5">
            {withSearch && (
                <Button variant="ghost" size="icon" asChild>
                    <Link href={search()} aria-label={t('Search products')}>
                        <Search />
                    </Link>
                </Button>
            )}
            <PlaceSlot place="header.actions" className="flex items-center gap-1" />
            <CustomerNotificationBell />
            <Button
                variant="ghost"
                size="icon"
                asChild
                className={showAccount ? undefined : "hidden"}
            >
                <Link
                    href={auth.user ? dashboard() : login()}
                    aria-label={auth.user ? t('Account') : t('Log in')}
                >
                    <User />
                </Link>
            </Button>
            <Button variant="ghost" size="icon" className="relative" asChild>
                <Link
                    href={cartShow()}
                    aria-label={t('Cart, :count items', { count })}
                >
                    <CartIcon />
                    {showCount && count > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-4 font-semibold text-primary-foreground tabular-nums">
                            {count > 99 ? '99+' : count}
                        </span>
                    )}
                </Link>
            </Button>
        </div>
    );
}

/** Desktop, scrolled: logo, the menu on one line, icons. */
function CompactBar() {
    const { links } = useShared();
    const t = useT();

    return (
        <div className="hidden border-b lg:block">
            <div className="mx-auto flex max-w-[var(--store-container,72rem)] items-center gap-6 px-4 py-2">
                <Brand small />
                <nav
                    aria-label={t('Main menu')}
                    className="flex min-w-0 flex-1 gap-5 overflow-hidden text-sm"
                >
                    {links.map((link, index) => (
                        <Link
                            key={index}
                            href={link.url}
                            className="whitespace-nowrap text-muted-foreground hover:text-foreground"
                        >
                            {link.label}
                        </Link>
                    ))}
                </nav>
                <Icons />
            </div>
        </div>
    );
}

/** Phones and tablets: ☰ | logo | search · account · cart. */
function MobileHeader({ compact }: { compact: boolean }) {
    const [open, setOpen] = useState(false);
    const t = useT();

    return (
        <div className="border-b lg:hidden">
            <div
                className={cn(
                    'grid grid-cols-[1fr_auto_1fr] items-center gap-2 px-2 transition-all motion-reduce:transition-none',
                    compact ? 'py-1' : 'py-3',
                )}
            >
                <div>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('Open menu')}
                        aria-expanded={open}
                        onClick={() => setOpen(true)}
                    >
                        <Menu />
                    </Button>
                </div>
                <div className="min-w-0 text-center">
                    <Brand small={compact} />
                </div>
                <Icons />
            </div>
            <MenuPanel open={open} onOpenChange={setOpen} />
        </div>
    );
}

/** The ☰ panel: search, menu links, language and currency, account. */
function MenuPanel({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { storefront, auth, links, tree } = useShared();
    const t = useT();
    const close = () => onOpenChange(false);
    const locales = storefront?.locales ?? [];
    const currencies = storefront?.currencies ?? [];

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="left"
                className="flex w-full max-w-none flex-col gap-0 overflow-y-auto p-0 sm:max-w-none"
            >
                <div className="border-b p-4 pr-12">
                    <SheetTitle className="truncate">
                        {storefront?.storeName ?? t('Menu')}
                    </SheetTitle>
                    <SheetDescription className="sr-only">
                        {t('Main menu')}
                    </SheetDescription>
                </div>

                <Form
                    {...search.form()}
                    role="search"
                    className="flex gap-2 border-b p-4"
                    onSuccess={close}
                >
                    <Input
                        type="search"
                        name="q"
                        placeholder={t('Search products')}
                        aria-label={t('Search products')}
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        size="icon"
                        aria-label={t('Search')}
                    >
                        <Search />
                    </Button>
                </Form>

                <nav aria-label={t('Main menu')} className="flex-1 py-2">
                    {tree.length > 0 && (
                        <MenuGroup title={t('Shop by category')}>
                            {tree.map((node) => (
                                <MenuItem
                                    key={node.id}
                                    label={node.name}
                                    url={node.url}
                                    items={node.children}
                                    onNavigate={close}
                                />
                            ))}
                        </MenuGroup>
                    )}
                    <MenuGroup title={tree.length > 0 ? t('Menu') : undefined}>
                        {links.map((link, index) => (
                            <MenuItem
                                key={index}
                                label={link.label}
                                url={link.url}
                                items={findCategory(tree, link)?.children ?? []}
                                onNavigate={close}
                            />
                        ))}
                    </MenuGroup>
                </nav>

                {(locales.length > 1 || currencies.length > 1) && (
                    <div className="grid gap-3 border-t p-4 text-sm">
                        {locales.length > 1 && (
                            <label className="flex items-center justify-between gap-3">
                                <span className="flex items-center gap-2">
                                    <Globe className="size-4" aria-hidden />
                                    {t('Language')}
                                </span>
                                <select
                                    value={storefront?.locale}
                                    onChange={(e) =>
                                        visitWith('lang', e.target.value)
                                    }
                                    className="h-9 rounded-md border bg-background px-2"
                                >
                                    {locales.map((l) => (
                                        <option
                                            key={l.code}
                                            value={l.code}
                                            lang={l.code}
                                        >
                                            {l.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        {currencies.length > 1 && (
                            <label className="flex items-center justify-between gap-3">
                                <span>{t('Currency')}</span>
                                <select
                                    value={storefront?.displayCurrency}
                                    disabled={!storefront?.canSwitchCurrency}
                                    onChange={(e) =>
                                        visitWith('currency', e.target.value)
                                    }
                                    className="h-9 rounded-md border bg-background px-2"
                                >
                                    {currencies.map((c) => (
                                        <option key={c.code} value={c.code}>
                                            {c.code} — {c.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                    </div>
                )}

                <div className="grid gap-1 border-t p-4 text-sm">
                    {auth.user ? (
                        <>
                            <Link
                                href={dashboard()}
                                onClick={close}
                                className="py-2 font-medium"
                            >
                                {auth.isStaff ? t('Admin') : t('My account')}
                            </Link>
                            {!auth.isStaff && (
                                <Link
                                    href="/account/orders"
                                    onClick={close}
                                    className="py-2"
                                >
                                    {t('My orders')}
                                </Link>
                            )}
                            {!auth.isStaff && (
                                <Link
                                    href="/account/notifications"
                                    onClick={close}
                                    className="py-2"
                                >
                                    {t('Notifications')}
                                </Link>
                            )}
                        </>
                    ) : (
                        <Link
                            href={login()}
                            onClick={close}
                            className="py-2 font-medium"
                        >
                            {t('Log in')}
                        </Link>
                    )}
                    <Link href={cartShow()} onClick={close} className="py-2">
                        {t('Cart')} ({storefront?.cartCount ?? 0})
                    </Link>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function MenuGroup({
    title,
    children,
}: {
    title?: string;
    children: ReactNode;
}) {
    return (
        <div className="py-1">
            {title && (
                <p className="px-4 pt-3 pb-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                    {title}
                </p>
            )}
            <ul>{children}</ul>
        </div>
    );
}

/**
 * A menu entry; with sub-items it gets an arrow that opens them in place
 * (sub-menus and subcategories, nested up to three levels).
 */
function MenuItem({
    label,
    url,
    items,
    depth = 0,
    onNavigate,
}: {
    label: string;
    url: string;
    items: CategoryNode[];
    depth?: number;
    onNavigate: () => void;
}) {
    const [open, setOpen] = useState(false);
    const t = useT();
    const padding = { paddingInlineStart: `${1 + depth * 1}rem` };

    return (
        <li>
            <div className="flex items-stretch border-b border-border/50">
                <Link
                    href={url}
                    onClick={onNavigate}
                    style={padding}
                    className={cn(
                        'flex-1 py-3 pr-2 hover:bg-muted',
                        depth === 0 ? 'text-base' : 'text-sm',
                    )}
                >
                    {label}
                </Link>
                {items.length > 0 && (
                    <button
                        type="button"
                        onClick={() => setOpen((o) => !o)}
                        aria-expanded={open}
                        aria-label={
                            open
                                ? t('Hide :name', { name: label })
                                : t('Show :name', { name: label })
                        }
                        className="flex w-12 items-center justify-center border-l border-border/50 hover:bg-muted"
                    >
                        <ChevronDown
                            className={cn(
                                'size-4 transition-transform motion-reduce:transition-none',
                                open && 'rotate-180',
                            )}
                            aria-hidden
                        />
                    </button>
                )}
            </div>
            {open && items.length > 0 && (
                <ul className="bg-muted/40">
                    {items.map((node) => (
                        <MenuItem
                            key={node.id}
                            label={node.name}
                            url={node.url}
                            items={node.children}
                            depth={depth + 1}
                            onNavigate={onNavigate}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}
