import { Link, usePage } from '@inertiajs/react';
import { navIcon } from '@/components/admin/nav-icon';
import AppLogo from '@/components/app-logo';
import { NavUser } from '@/components/nav-user';
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
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import type { AdminNavGroup } from '@/types';

export function AppSidebar() {
    const { adminNavigation } = usePage().props;
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

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
                              href: editProfile().url,
                              icon: 'user-round',
                          },
                      ],
                  },
              ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {groups.map((group) => (
                    <SidebarGroup key={group.title} className="px-2 py-0">
                        <SidebarGroupLabel>{group.title}</SidebarGroupLabel>
                        <SidebarMenu>
                            {group.items.map((item) => {
                                const Icon = navIcon(item.icon);

                                return (
                                    <SidebarMenuItem key={item.key}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={
                                                item.key === 'dashboard'
                                                    ? isCurrentUrl(item.href)
                                                    : isCurrentOrParentUrl(
                                                          item.href,
                                                      )
                                            }
                                            tooltip={{ children: item.title }}
                                        >
                                            <Link href={item.href} prefetch>
                                                <Icon />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
