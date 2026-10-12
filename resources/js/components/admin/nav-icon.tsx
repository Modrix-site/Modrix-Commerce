import {
    Activity,
    Circle,
    LayoutGrid,
    ScrollText,
    Settings,
    ShieldCheck,
    UserRound,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/**
 * Maps icon names sent by the server (core and modules) to lucide components.
 * Unknown names fall back to a neutral dot instead of failing.
 */
const icons: Record<string, LucideIcon> = {
    activity: Activity,
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
