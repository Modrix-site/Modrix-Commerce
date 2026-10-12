import {
    Activity,
    ChartColumn,
    Circle,
    CreditCard,
    FolderTree,
    LayoutGrid,
    Package,
    Percent,
    Receipt,
    ScrollText,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Tags,
    Truck,
    UserRound,
    Users,
    Warehouse,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/**
 * Maps icon names sent by the server (core and modules) to lucide components.
 * Unknown names fall back to a neutral dot instead of failing.
 */
const icons: Record<string, LucideIcon> = {
    activity: Activity,
    'chart-column': ChartColumn,
    'credit-card': CreditCard,
    'folder-tree': FolderTree,
    package: Package,
    percent: Percent,
    receipt: Receipt,
    'shopping-cart': ShoppingCart,
    tags: Tags,
    truck: Truck,
    warehouse: Warehouse,
    'layout-grid': LayoutGrid,
    'scroll-text': ScrollText,
    settings: Settings,
    'shield-check': ShieldCheck,
    'user-round': UserRound,
    users: Users,
};

export function navIcon(name: string): LucideIcon {
    return icons[name] ?? Circle;
}
