import type { AdminNavGroup, AdminNavItem } from '@/types';

/**
 * The admin sidebar as a compact tree: a few top-level entries, each of
 * which can fold open to show its pages. Built from the server's groups.
 */
export type AdminNavEntry = {
    key: string;
    title: string;
    icon: string;
    /** Where the entry itself goes; a pure folder opens its first page. */
    href: string;
    children: AdminNavItem[];
};

/** Groups whose items stay as separate top-level entries. */
const FLAT = ['Overview', 'Sales', 'Account'];

/** Groups that fold under one of their own items (the first match). */
const LED_BY: Record<string, string> = { Catalog: 'products' };

/** Pages shown under another entry, wherever their group is. */
const NESTED: Record<string, string[]> = {
    orders: ['invoices', 'returns', 'refund-management', 'customer-carts'],
};

/** Name and icon for groups shown as a folder. */
const FOLDERS: Record<string, { title: string; icon: string }> = {
    Marketing: { title: 'Marketing', icon: 'megaphone' },
    Configuration: { title: 'Frontend', icon: 'store' },
    Administration: { title: 'Settings', icon: 'settings' },
    Marketplace: { title: 'Marketplace', icon: 'package-plus' },
};

export function adminNavTree(groups: AdminNavGroup[]): AdminNavEntry[] {
    const entries: AdminNavEntry[] = [];
    const nestedKeys = Object.values(NESTED).flat();
    const nested = new Map<string, AdminNavItem>();

    // Take the nested pages out of their groups first.
    groups = groups.map((group) => ({
        ...group,
        items: group.items.filter((item) => {
            // Extension pages are keyed "vendor/name:key".
            const key = item.key.split(':').pop() ?? item.key;

            if (nestedKeys.includes(key)) {
                nested.set(key, item);

                return false;
            }

            return true;
        }),
    }));

    for (const group of groups) {
        if (group.items.length === 0) {
            continue;
        }

        if (FLAT.includes(group.title) || group.items.length === 1) {
            for (const item of group.items) {
                entries.push(
                    item.key === 'dashboard'
                        ? {
                              ...item,
                              title: 'Home',
                              icon: 'house',
                              children: [],
                          }
                        : { ...item, children: [] },
                );
            }

            continue;
        }

        const leader = group.items.find(
            (item) => item.key === LED_BY[group.title],
        );

        if (leader) {
            entries.push({
                ...leader,
                children: group.items.filter((item) => item !== leader),
            });

            continue;
        }

        const folder = FOLDERS[group.title] ?? {
            title: group.title,
            icon: group.items[0].icon,
        };
        entries.push({
            key: `group:${group.title}`,
            title: folder.title,
            icon: folder.icon,
            href: group.items[0].href,
            children: group.items,
        });
    }

    for (const entry of entries) {
        for (const key of NESTED[entry.key] ?? []) {
            const item = nested.get(key);

            if (item) {
                entry.children.push(item);
            }
        }
    }

    // Products (with its pages) comes right after Home.
    const products = entries.findIndex((entry) => entry.key === 'products');
    const home = entries.findIndex((entry) => entry.key === 'dashboard');

    if (products > -1 && home > -1 && products !== home + 1) {
        const [entry] = entries.splice(products, 1);
        const after = entries.findIndex((e) => e.key === 'dashboard') + 1;
        entries.splice(after, 0, entry);
    }

    return entries;
}
