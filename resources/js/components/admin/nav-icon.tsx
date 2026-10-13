import {
    Activity,
    Bell,
    ChartColumn,
    Circle,
    CreditCard,
    FolderTree,
    Image,
    Languages,
    KeyRound,
    LayoutGrid,
    MessageSquare,
    Palette,
    Package,
    Percent,
    Puzzle,
    Receipt,
    ScrollText,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Star,
    Store,
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
    bell: Bell,
    'message-square': MessageSquare,
    palette: Palette,
    puzzle: Puzzle,
    star: Star,
    'chart-column': ChartColumn,
    'credit-card': CreditCard,
    'folder-tree': FolderTree,
    image: Image,
    languages: Languages,
    'key-round': KeyRound,
    store: Store,
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
