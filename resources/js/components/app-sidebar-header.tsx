import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Moon, Palette, Plus, Search, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { navIcon } from '@/components/admin/nav-icon';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { AdminNotificationBell } from '@/components/notifications/admin-notification-bell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { adminAccents, useAdminAccent } from '@/hooks/use-admin-accent';
import { useAppearance } from '@/hooks/use-appearance';
import { useCan } from '@/hooks/use-can';
import { useInitials } from '@/hooks/use-initials';
import { useSidebarAppearance } from '@/hooks/use-sidebar-appearance';
import { dashboard } from '@/routes';
import { create as createProduct } from '@/routes/admin/products';
import type { BreadcrumbItem } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { props, url } = usePage<{ order?: { number: string } }>();
    const { auth, adminNavigation, order } = props;
    const { setOpenMobile } = useSidebar();
    const can = useCan();
    const initials = useInitials();
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const { accent, updateAccent } = useAdminAccent();
    const { sidebarAppearance, updateSidebarAppearance } =
        useSidebarAppearance();
    const [searchOpen, setSearchOpen] = useState(false);
    const [query, setQuery] = useState('');
    const navigation = adminNavigation.flatMap((group) =>
        group.items.map((item) => ({ ...item, group: group.title })),
    );
    const matches = navigation.filter((item) =>
        `${item.title} ${item.group}`
            .toLowerCase()
            .includes(query.trim().toLowerCase()),
    );
    const baseTrail =
        breadcrumbs[0]?.title === 'Dashboard'
            ? breadcrumbs
            : [{ title: 'Dashboard', href: dashboard() }, ...breadcrumbs];
    const trail =
        order?.number && breadcrumbs.at(-1)?.title === 'Orders'
            ? [...baseTrail, { title: order.number, href: url }]
            : baseTrail;

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setSearchOpen((open) => !open);
            }
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    return (
        <>
            <header className="admin-toolbar flex h-16 shrink-0 items-center justify-between gap-3 border-b px-4 md:px-6">
                <div className="flex min-w-0 items-center gap-3">
                    <SidebarTrigger className="md:hidden" />
                    <Dialog
                        open={searchOpen}
                        onOpenChange={(open) => {
                            setSearchOpen(open);
                            if (!open) setQuery('');
                        }}
                    >
                        <DialogTrigger asChild>
                            <Button
                                variant="outline"
                                className="h-9 w-44 justify-start bg-transparent font-normal text-muted-foreground sm:w-72"
                            >
                                <Search className="size-4" aria-hidden />
                                <span className="truncate">
                                    Search anything...
                                </span>
                                <kbd className="ml-auto hidden rounded border px-1 text-[10px] sm:inline">
                                    Ctrl K
                                </kbd>
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="gap-4 p-5 sm:max-w-lg">
                            <DialogTitle>Find a page</DialogTitle>
                            <DialogDescription>
                                Search your store workspace.
                            </DialogDescription>
                            <Input
                                autoFocus
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Search pages..."
                                aria-label="Search pages"
                            />
                            <nav
                                aria-label="Search results"
                                className="grid max-h-80 gap-1 overflow-y-auto"
                            >
                                {matches.map((item) => {
                                    const Icon = navIcon(item.icon);
                                    return (
                                        <Link
                                            key={item.key}
                                            href={item.href}
                                            className="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm hover:bg-accent focus-visible:bg-accent"
                                            onClick={() => {
                                                setSearchOpen(false);
                                                setQuery('');
                                                setOpenMobile(false);
                                            }}
                                        >
                                            <Icon
                                                className="size-4 text-muted-foreground"
                                                aria-hidden
                                            />
                                            <span>{item.title}</span>
                                            <span className="ml-auto text-xs text-muted-foreground">
                                                {item.group}
                                            </span>
                                        </Link>
                                    );
                                })}
                                {matches.length === 0 && (
                                    <p className="py-8 text-center text-sm text-muted-foreground">
                                        No pages found.
                                    </p>
                                )}
                            </nav>
                        </DialogContent>
                    </Dialog>
                </div>
                <div className="flex shrink-0 items-center gap-1 sm:gap-2">
                    {can('products.create') && (
                        <Button
                            asChild
                            size="sm"
                            className="mr-2 hidden sm:inline-flex"
                        >
                            <Link href={createProduct()}>
                                <Plus aria-hidden />
                                New product
                            </Link>
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 text-muted-foreground"
                        aria-label={
                            resolvedAppearance === 'dark'
                                ? 'Switch to light mode'
                                : 'Switch to dark mode'
                        }
                        onClick={() =>
                            updateAppearance(
                                resolvedAppearance === 'dark'
                                    ? 'light'
                                    : 'dark',
                            )
                        }
                    >
                        {resolvedAppearance === 'dark' ? (
                            <Sun className="size-4" />
                        ) : (
                            <Moon className="size-4" />
                        )}
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 text-muted-foreground"
                                aria-label="Customize appearance"
                            >
                                <Palette className="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-52">
                            <DropdownMenuLabel>Accent color</DropdownMenuLabel>
                            <DropdownMenuRadioGroup
                                value={accent}
                                onValueChange={(value) =>
                                    updateAccent(value as typeof accent)
                                }
                            >
                                {adminAccents.map((item) => (
                                    <DropdownMenuRadioItem
                                        key={item.value}
                                        value={item.value}
                                    >
                                        <span
                                            className="size-3 rounded-full"
                                            style={{
                                                backgroundColor: item.color,
                                            }}
                                        />
                                        {item.label}
                                    </DropdownMenuRadioItem>
                                ))}
                            </DropdownMenuRadioGroup>
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel>Sidebar</DropdownMenuLabel>
                            <DropdownMenuRadioGroup
                                value={sidebarAppearance}
                                onValueChange={(value) =>
                                    updateSidebarAppearance(
                                        value as 'dark' | 'light',
                                    )
                                }
                            >
                                <DropdownMenuRadioItem value="dark">
                                    Black sidebar (default)
                                </DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="light">
                                    White sidebar
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel>Appearance</DropdownMenuLabel>
                            <DropdownMenuRadioGroup
                                value={appearance}
                                onValueChange={(value) =>
                                    updateAppearance(
                                        value as 'light' | 'dark' | 'system',
                                    )
                                }
                            >
                                <DropdownMenuRadioItem value="light">
                                    Light
                                </DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="dark">
                                    Dark
                                </DropdownMenuRadioItem>
                                <DropdownMenuRadioItem value="system">
                                    System
                                </DropdownMenuRadioItem>
                            </DropdownMenuRadioGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="hidden size-8 text-muted-foreground sm:inline-flex"
                    >
                        <a
                            href="/"
                            target="_blank"
                            rel="noreferrer"
                            aria-label="Open storefront"
                        >
                            <ArrowUpRight className="size-4" />
                        </a>
                    </Button>
                    <AdminNotificationBell className="text-muted-foreground" />
                    {auth.user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="ml-1 size-8 rounded-full"
                                    aria-label="Open account menu"
                                >
                                    <Avatar className="size-8">
                                        <AvatarImage
                                            src={auth.user.avatar}
                                            alt=""
                                        />
                                        <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                                            {initials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-60">
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </header>
            <div className="px-4 pt-6 pb-1 md:px-6">
                <Breadcrumbs breadcrumbs={trail} />
            </div>
        </>
    );
}
