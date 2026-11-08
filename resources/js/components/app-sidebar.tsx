import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronLeft } from 'lucide-react';
import { navIcon } from '@/components/admin/nav-icon';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
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

    return (
        <Sidebar collapsible="icon" variant="sidebar" className="admin-sidebar">
            <SidebarHeader className="h-16 justify-center border-b border-sidebar-border px-4 py-2 group-data-[collapsible=icon]:px-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent active:bg-transparent"
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

            <SidebarContent className="gap-5 px-3 py-4 group-data-[collapsible=icon]:px-2">
                {groups.map((group) => (
                    <Collapsible
                        key={group.title}
                        defaultOpen
                        className="group/nav-section"
                    >
                        <SidebarGroup className="p-0">
                            <SidebarGroupLabel asChild>
                                <CollapsibleTrigger className="mb-1 flex w-full justify-between px-3 text-[10px] font-semibold tracking-wider uppercase">
                                    {group.title}
                                    <ChevronDown className="size-3 transition-transform group-data-[state=closed]/nav-section:-rotate-90" />
                                </CollapsibleTrigger>
                            </SidebarGroupLabel>
                            <CollapsibleContent>
                                <SidebarMenu>
                                    {group.items.map((item) => {
                                        const Icon = navIcon(item.icon);

                                        return (
                                            <SidebarMenuItem key={item.key}>
                                                <SidebarMenuButton
                                                    asChild
                                                    isActive={
                                                        item.key === 'dashboard'
                                                            ? isCurrentUrl(
                                                                  item.href,
                                                              )
                                                            : isCurrentOrParentUrl(
                                                                  item.href,
                                                              )
                                                    }
                                                    tooltip={{
                                                        children: item.title,
                                                    }}
                                                    className="h-9 rounded-lg px-3 font-medium group-data-[collapsible=icon]:size-10!"
                                                >
                                                    <Link
                                                        href={item.href}
                                                        prefetch
                                                        onClick={() =>
                                                            setOpenMobile(false)
                                                        }
                                                    >
                                                        <Icon />
                                                        <span>
                                                            {item.title}
                                                        </span>
                                                    </Link>
                                                </SidebarMenuButton>
                                            </SidebarMenuItem>
                                        );
                                    })}
                                </SidebarMenu>
                            </CollapsibleContent>
                        </SidebarGroup>
                    </Collapsible>
                ))}
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border p-3">
                <NavUser />
                <p className="px-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">v{platform.version}</p>
            </SidebarFooter>
        </Sidebar>
    );
}
