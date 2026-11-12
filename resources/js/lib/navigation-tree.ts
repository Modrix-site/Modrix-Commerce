import type { StoreMenuItem } from '@/types/storefront';

export const MENU_DEPTH = 4;
export const MENU_ITEMS = 150;
export type ItemPath = number[];
export const blankMenuItem = (): StoreMenuItem => ({
    label: 'New link',
    url: '/search',
    type: 'link',
    children: [],
});
export function treeCount(items: StoreMenuItem[]): number {
    return items.reduce(
        (sum, item) => sum + 1 + treeCount(item.children ?? []),
        0,
    );
}
export function treeDepth(item: StoreMenuItem): number {
    return 1 + Math.max(0, ...(item.children ?? []).map(treeDepth));
}
export function getTreeItem(
    items: StoreMenuItem[],
    path: ItemPath,
): StoreMenuItem | undefined {
    const [index, ...rest] = path;
    const item = items[index];
    return rest.length ? getTreeItem(item?.children ?? [], rest) : item;
}
export function changeTree(
    items: StoreMenuItem[],
    parent: ItemPath,
    change: (siblings: StoreMenuItem[]) => StoreMenuItem[],
): StoreMenuItem[] {
    if (!parent.length) return change(items);
    const [index, ...rest] = parent;
    return items.map((item, i) =>
        i === index
            ? {
                  ...item,
                  children: changeTree(item.children ?? [], rest, change),
              }
            : item,
    );
}
export function updateTreeItem(
    items: StoreMenuItem[],
    path: ItemPath,
    patch: Partial<StoreMenuItem>,
): StoreMenuItem[] {
    return changeTree(items, path.slice(0, -1), (siblings) =>
        siblings.map((item, i) =>
            i === path[path.length - 1] ? { ...item, ...patch } : item,
        ),
    );
}
export function removeTreeItem(
    items: StoreMenuItem[],
    path: ItemPath,
): StoreMenuItem[] {
    return changeTree(items, path.slice(0, -1), (siblings) =>
        siblings.filter((_, i) => i !== path[path.length - 1]),
    );
}
export function reparentTreeItem(
    items: StoreMenuItem[],
    path: ItemPath,
    direction: 'indent' | 'outdent',
): { items: StoreMenuItem[]; path: ItemPath } {
    const item = getTreeItem(items, path);
    if (!item) return { items, path };
    const index = path[path.length - 1];
    const parent = path.slice(0, -1);
    if (direction === 'indent') {
        if (!index || path.length + treeDepth(item) > MENU_DEPTH)
            return { items, path };
        const previous = getTreeItem(items, [...parent, index - 1]);
        if (!previous) return { items, path };
        const next = changeTree(items, parent, (siblings) =>
            siblings.flatMap((sibling, i) =>
                i === index
                    ? []
                    : i === index - 1
                      ? [
                            {
                                ...sibling,
                                children: [...(sibling.children ?? []), item],
                            },
                        ]
                      : [sibling],
            ),
        );
        return {
            items: next,
            path: [...parent, index - 1, previous.children?.length ?? 0],
        };
    }
    if (path.length < 2) return { items, path };
    const ancestor = parent.slice(0, -1);
    const parentIndex = parent[parent.length - 1];
    const pruned = removeTreeItem(items, path);
    return {
        items: changeTree(pruned, ancestor, (siblings) => [
            ...siblings.slice(0, parentIndex + 1),
            item,
            ...siblings.slice(parentIndex + 1),
        ]),
        path: [...ancestor, parentIndex + 1],
    };
}
