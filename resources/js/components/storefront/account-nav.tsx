import { Link, usePage } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import {
    Bell,
    ChevronLeft,
    CircleDot,
    LayoutDashboard,
    LogOut,
    MapPin,
    Package,
    RotateCcw,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useT } from '@/lib/i18n';
import { cn, toUrl } from '@/lib/utils';
import { logout } from '@/routes';
import {
    addresses,
    notifications,
    orders,
    profile,
    security,
    show,
} from '@/routes/account';
import type { Auth } from '@/types';

const ICONS: Record<string, LucideIcon> = {
    overview: LayoutDashboard,
    orders: Package,
    returns: RotateCcw,
    notifications: Bell,
    addresses: MapPin,
    profile: UserRound,
    security: ShieldCheck,
    password: ShieldCheck,
};

type Href = NonNullable<InertiaLinkProps['href']>;

type NavItem = {
    key: string;
    title: string;
    href: Href;
};

function useAccountLinks(): NavItem[] {
    const { storefront } = usePage().props;
    const t = useT();
    const core: NavItem[] = [
        { key: 'overview', title: t('Overview'), href: show() },
        { key: 'orders', title: t('Orders'), href: orders() },
        {
            key: 'notifications',
            title: t('Notifications'),
            href: notifications(),
        },
        { key: 'addresses', title: t('Addresses'), href: addresses() },
        { key: 'profile', title: t('Profile'), href: profile() },
        {
            key: 'password',
            title: t('Change password'),
            href: '/account/password',
        },
        { key: 'security', title: t('Password & security'), href: security() },
    ];
    const extras = storefront?.accountLinks ?? [];

    return core.flatMap((item) => [
        item,
        ...extras
            .filter((extra) => extra.after === item.key)
            .map((extra) => ({
                key: extra.key,
                title: t(extra.title),
                href: extra.href,
            })),
    ]);
}

const initials = (name: string) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('') || '?';

/**
 * The account menu: wrapping tabs on small screens, a list with
 * icons beside the page on large screens.
 */
export function AccountNav({ className }: { className?: string }) {
    const { currentUrl, isCurrentUrl } = useCurrentUrl();
    const t = useT();
    const items = useAccountLinks();
    const overview = show().url;

    return (
        <nav
            aria-label={t('Account')}
            className={cn(
                'flex flex-wrap gap-2 text-sm lg:grid lg:gap-0.5',
                className,
            )}
        >
            {items.map((item) => {
                const Icon = ICONS[item.key] ?? CircleDot;
                const url = toUrl(item.href);
                // Overview is only current on itself, not on every account page.
                const current =
                    isCurrentUrl(item.href) ||
                    (url !== overview && currentUrl.startsWith(`${url}/`));

                return (
                    <Link
                        key={item.key}
                        href={item.href}
                        aria-current={current ? 'page' : undefined}
                        className="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 whitespace-nowrap text-muted-foreground transition-colors hover:bg-muted hover:text-foreground aria-[current=page]:border-foreground aria-[current=page]:bg-foreground aria-[current=page]:text-background lg:gap-2 lg:rounded-md lg:border-transparent lg:px-3 lg:py-2.5 lg:aria-[current=page]:border-transparent lg:aria-[current=page]:bg-muted lg:aria-[current=page]:font-medium lg:aria-[current=page]:text-foreground"
                    >
                        <Icon className="size-4 shrink-0" aria-hidden />
                        {item.title}
                    </Link>
                );
            })}
            <Link
                href={logout()}
                as="button"
                className="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 whitespace-nowrap text-muted-foreground transition-colors hover:bg-muted hover:text-foreground lg:mt-2 lg:gap-2 lg:rounded-md lg:border-0 lg:border-t lg:px-3 lg:pt-3.5"
            >
                <LogOut className="size-4 shrink-0" aria-hidden />
                {t('Log out')}
            </Link>
        </nav>
    );
}

function AccountCard() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const user = auth?.user;

    if (!user) {
        return null;
    }

    return (
        <div className="flex min-w-0 items-center gap-3">
            <span
                aria-hidden
                className="grid size-11 shrink-0 place-items-center rounded-full bg-foreground text-sm font-semibold text-background"
            >
                {initials(user.name)}
            </span>
            <span className="grid min-w-0">
                <span className="truncate font-semibold">{user.name}</span>
                <span className="truncate text-sm text-muted-foreground">
                    {user.email}
                </span>
            </span>
        </div>
    );
}

/**
 * The frame of every customer account page: who is signed in, the account
 * menu, and the page heading.
 */
export function AccountLayout({
    title,
    description,
    actions,
    back,
    children,
}: {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    back?: { href: Href; label: string };
    children: ReactNode;
}) {
    return (
        <div className="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-10">
            <aside className="grid min-w-0 content-start gap-5 lg:self-start lg:rounded-xl lg:border lg:bg-card lg:p-4">
                <AccountCard />
                <AccountNav />
            </aside>
            <div className="grid min-w-0 content-start gap-6">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="grid gap-1">
                        {back && (
                            <Link
                                href={back.href}
                                className="mb-1 inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                            >
                                <ChevronLeft className="size-4" aria-hidden />
                                {back.label}
                            </Link>
                        )}
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {title}
                        </h1>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions && (
                        <div className="flex flex-wrap gap-2">{actions}</div>
                    )}
                </header>
                {children}
            </div>
        </div>
    );
}

/** A bordered panel inside an account page. */
export function AccountSection({
    title,
    description,
    action,
    children,
    className,
}: {
    title?: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
    children?: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'grid content-start gap-4 rounded-xl border bg-card p-5 sm:p-6',
                className,
            )}
        >
            {(title || action) && (
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="grid gap-1">
                        {title && <h2 className="font-semibold">{title}</h2>}
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {action}
                </div>
            )}
            {children}
        </section>
    );
}

/** Previous / next links under a paginated account list. */
export function AccountPager({
    page,
}: {
    page: {
        last_page: number;
        current_page?: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    const t = useT();

    if (page.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-2 text-sm">
            {page.prev_page_url ? (
                <Button variant="outline" size="sm" asChild>
                    <Link href={page.prev_page_url} preserveScroll>
                        {t('Previous')}
                    </Link>
                </Button>
            ) : (
                <span />
            )}
            {page.current_page && (
                <span className="text-muted-foreground">
                    {t('Page :page of :pages', {
                        page: String(page.current_page),
                        pages: String(page.last_page),
                    })}
                </span>
            )}
            {page.next_page_url ? (
                <Button variant="outline" size="sm" asChild>
                    <Link href={page.next_page_url} preserveScroll>
                        {t('Next')}
                    </Link>
                </Button>
            ) : (
                <span />
            )}
        </div>
    );
}

/** A friendly placeholder for lists with nothing in them yet. */
export function AccountEmpty({
    icon: Icon,
    title,
    description,
    action,
}: {
    icon: LucideIcon;
    title: ReactNode;
    description?: ReactNode;
    action?: ReactNode;
}) {
    return (
        <div className="grid justify-items-center gap-3 rounded-xl border border-dashed px-6 py-12 text-center">
            <span className="grid size-12 place-items-center rounded-full bg-muted">
                <Icon className="size-5 text-muted-foreground" aria-hidden />
            </span>
            <div className="grid gap-1">
                <p className="font-medium">{title}</p>
                {description && (
                    <p className="max-w-sm text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {action}
        </div>
    );
}
