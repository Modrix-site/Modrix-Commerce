import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

/** Server-provided, permission-filtered admin navigation (see App\Core\Admin\AdminNavigation). */
export type AdminNavItem = {
    key: string;
    title: string;
    href: string;
    icon: string;
};

export type AdminNavGroup = {
    title: string;
    items: AdminNavItem[];
};
