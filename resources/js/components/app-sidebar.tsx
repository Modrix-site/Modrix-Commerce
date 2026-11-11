import { Link, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { navIcon } from '@/components/admin/nav-icon';
import { adminNavTree } from '@/components/admin-nav-tree';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { show as accountShow } from '@/routes/account';
import type { AdminNavGroup } from '@/types';

export function AppSidebar() {
    const { adminNavigation, platform } = usePage().props;
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { toggleSidebar, state, setOpenMobile } = useSidebar();

    // Customers (no roles) receive no admin navigation; show their account links instead.
    const groups: AdminNavGroup[] =
        adminNavigation.length > 0
            ? adminNavigation
            : [
                  {
                      title: 'Account',
                      items: [
                          {
                              key: 'profile',
                              title: 'My account',
                              href: accountShow().url,
                              icon: 'user-round',
                          },
                      ],
                  },
              ];

    const entries = adminNavTree(groups);

    return (
        <Sidebar collapsible="icon" variant="inset" className="admin-sidebar">
            <SidebarHeader className="h-14 justify-center px-3 py-2 group-data-[collapsible=icon]:px-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto justify-center overflow-visible hover:bg-transparent active:bg-transparent"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <button
                type="button"
                onClick={toggleSidebar}
                aria-label={
                    state === 'expanded' ? 'Collapse sidebar' : 'Expand sidebar'
                }
                className="absolute top-20 -right-3 z-20 hidden size-6 items-center justify-center rounded-full border border-border bg-card text-muted-foreground shadow-xs hover:text-foreground md:flex"
            >
                <ChevronLeft
                    className={`size-3.5 transition-transform ${state === 'collapsed' ? 'rotate-180' : ''}`}
                />
            </button>

            <SidebarContent className="gap-0 px-2 py-3 group-data-[collapsible=icon]:px-2">
                <SidebarMenu className="gap-0.5">
                    {entries.map((entry) => {
                        const Icon = navIcon(entry.icon);
                        const childActive = entry.children.some((child) =>
                            isCurrentOrParentUrl(child.href),
                        );
                        const selfActive =
                            entry.key === 'dashboard'
                                ? isCurrentUrl(entry.href)
                                : isCurrentOrParentUrl(entry.href);
                        // A section shows its pages while you are in it.
                        const open =
                            entry.children.length > 0 &&
                            (selfActive || childActive);

                        return (
                            <SidebarMenuItem key={entry.key}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={
                                        entry.key.startsWith('group:')
                                            ? false
                                            : selfActive && !childActive
                                    }
                                    data-open={open}
                                    tooltip={{ children: entry.title }}
                                    className="admin-nav-parent h-8 rounded-lg px-2.5 font-semibold group-data-[collapsible=icon]:size-10!"
                                >
                                    <Link
                                        href={entry.href}
                                        prefetch
                                        onClick={() => setOpenMobile(false)}
                                    >
                                        <Icon />
                                        <span>{entry.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                                {open && (
                                    <SidebarMenuSub className="admin-nav-sub">
                                        {entry.children.map((child) => (
                                            <SidebarMenuSubItem key={child.key}>
                                                <SidebarMenuSubButton
                                                    asChild
                                                    isActive={isCurrentOrParentUrl(
                                                        child.href,
                                                    )}
                                                >
                                                    <Link
                                                        href={child.href}
                                                        prefetch
                                                        onClick={() =>
                                                            setOpenMobile(false)
                                                        }
                                                    >
                                                        <span>
                                                            {child.title}
                                                        </span>
                                                    </Link>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        ))}
                                    </SidebarMenuSub>
                                )}
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarContent>

            <SidebarFooter className="p-2">
                <NavUser />
                <p className="px-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                    v{platform.version}
                </p>
            </SidebarFooter>
        </Sidebar>
    );
}
