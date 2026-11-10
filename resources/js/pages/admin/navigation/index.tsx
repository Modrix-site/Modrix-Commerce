import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    CornerDownRight,
    ChevronDown,
    ChevronRight,
    Menu,
    PanelLeft,
    Plus,
    Save,
    Smartphone,
    Trash2,
    Columns3,
} from 'lucide-react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import type { StoreMenu, StoreMenuItem, StoreMenus } from '@/types/storefront';
import {
    blankMenuItem,
    changeTree,
    getTreeItem,
    MENU_DEPTH,
    MENU_ITEMS,
    reparentTreeItem,
    removeTreeItem,
    treeCount,
    treeDepth,
    updateTreeItem,
} from '@/lib/navigation-tree';
import type { ItemPath } from '@/lib/navigation-tree';

type Targets = {
    pages: { id: number; title: string; status: string }[];
    categories: {
        id: number;
        name: string;
        parent_id: number | null;
        is_active: boolean;
    }[];
};
const placements = [
    {
        key: 'header',
        title: 'Top navigation',
        description: 'Theme header',
        icon: Menu,
    },
    {
        key: 'sidebar',
        title: 'Left sidebar',
        description: 'Desktop sidebar · mobile accordion',
        icon: PanelLeft,
    },
    {
        key: 'mobile',
        title: 'Mobile menu',
        description: 'Phone and tablet drawers',
        icon: Smartphone,
    },
    ...[0, 1, 2].map((i) => ({
        key: `footer.${i}`,
        title: `Footer ${i + 1}`,
        description: 'Footer navigation',
        icon: Columns3,
    })),
];
const normalize = (menu: StoreMenu, layout: StoreMenu['layout']) => ({
    layout,
    enabled: true,
    ...menu,
});

export default function Navigation({
    menus,
    pages,
    categories,
}: { menus: StoreMenus } & Targets) {
    const form = useForm<StoreMenus>({
        ...menus,
        header: normalize(menus.header, 'horizontal'),
        sidebar: normalize(
            menus.sidebar ?? {
                title: 'Browse departments',
                links: [],
                enabled: false,
            },
            'vertical',
        ),
        mobile: normalize(
            menus.mobile ?? {
                title: 'Mobile navigation',
                links: [],
                inherit_header: true,
            },
            'vertical',
        ),
        footer: menus.footer.map((menu) => normalize(menu, 'vertical')),
    });
    const errors: Record<string, string> = form.errors;
    const [placement, setPlacement] = useState('header');
    const [selected, setSelected] = useState<ItemPath>([]);
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});
    const footerIndex = placement.startsWith('footer.')
        ? Number(placement.split('.')[1])
        : -1;
    const menu =
        footerIndex >= 0
            ? form.data.footer[footerIndex]
            : form.data[placement as 'header' | 'sidebar' | 'mobile']!;
    const region = placements.find((item) => item.key === placement)!;
    const total = [
        form.data.header,
        form.data.sidebar!,
        form.data.mobile!,
        ...form.data.footer,
    ].reduce((sum, item) => sum + treeCount(item.links), 0);
    const maxRoots = footerIndex >= 0 ? 20 : 30;
    const inherited = placement === 'mobile' && menu.inherit_header === true;
    const current = selected.length
        ? getTreeItem(menu.links, selected)
        : undefined;
    const itemError = `${placement}.links.${selected.join('.children.')}`;
    const setMenu = (value: StoreMenu) => {
        if (footerIndex >= 0)
            form.setData(
                'footer',
                form.data.footer.map((item, i) =>
                    i === footerIndex ? value : item,
                ),
            );
        else form.setData(placement as 'header' | 'sidebar' | 'mobile', value);
    };
    const update = (patch: Partial<StoreMenuItem>) =>
        setMenu({
            ...menu,
            links: updateTreeItem(menu.links, selected, patch),
        });
    const add = (parent: ItemPath = []) => {
        const siblings = parent.length
            ? (getTreeItem(menu.links, parent)?.children ?? [])
            : menu.links;
        if (
            total >= MENU_ITEMS ||
            parent.length >= MENU_DEPTH ||
            siblings.length >= (parent.length ? 30 : maxRoots)
        )
            return;
        setMenu({
            ...menu,
            links: changeTree(menu.links, parent, (items) => [
                ...items,
                blankMenuItem(),
            ]),
        });
        setSelected([...parent, siblings.length]);
        setCollapsed((value) => ({ ...value, [parent.join('.')]: false }));
    };
    const move = (direction: number) => {
        const index = selected[selected.length - 1];
        setMenu({
            ...menu,
            links: changeTree(menu.links, selected.slice(0, -1), (siblings) => {
                const next = [...siblings];
                [next[index], next[index + direction]] = [
                    next[index + direction],
                    next[index],
                ];
                return next;
            }),
        });
        setSelected([...selected.slice(0, -1), index + direction]);
    };
    const reparent = (direction: 'indent' | 'outdent') => {
        const result = reparentTreeItem(menu.links, selected, direction);
        setMenu({ ...menu, links: result.items });
        setSelected(result.path);
    };
    const importCategories = () => {
        const build = (parent: number | null, depth = 1): StoreMenuItem[] =>
            categories
                .filter((c) => c.parent_id === parent && c.is_active)
                .slice(0, 30)
                .map((c) => ({
                    label: c.name,
                    url: '',
                    type: 'category',
                    category_id: c.id,
                    children: depth < MENU_DEPTH ? build(c.id, depth + 1) : [],
                }));
        const additions = build(null).slice(0, maxRoots - menu.links.length);
        if (total + treeCount(additions) > MENU_ITEMS) {
            form.setError(
                'header.title',
                'Category tree exceeds the 150-link limit. Add individual categories instead.',
            );
            return;
        }
        setMenu({ ...menu, links: [...menu.links, ...additions] });
        setSelected([]);
    };
    const renderTree = (
        items: StoreMenuItem[],
        parent: ItemPath = [],
    ): React.ReactNode => (
        <ul className={parent.length ? 'ml-5 border-l pl-2' : 'space-y-1'}>
            {items.map((item, i) => {
                const path = [...parent, i];
                const key = path.join('.');
                const active = key === selected.join('.');
                return (
                    <li key={key} className="py-0.5">
                        <div className="flex items-center gap-1">
                            {(item.children?.length ?? 0) > 0 && (
                                <button
                                    type="button"
                                    className="grid size-7 shrink-0 place-items-center rounded hover:bg-muted"
                                    aria-label={`${collapsed[key] ? 'Expand' : 'Collapse'} ${item.label}`}
                                    aria-expanded={!collapsed[key]}
                                    onClick={() =>
                                        setCollapsed((value) => ({
                                            ...value,
                                            [key]: !value[key],
                                        }))
                                    }
                                >
                                    {collapsed[key] ? (
                                        <ChevronRight size={14} />
                                    ) : (
                                        <ChevronDown size={14} />
                                    )}
                                </button>
                            )}
                            <button
                                type="button"
                                className={`flex w-full items-center gap-2 rounded-lg border px-3 py-3 text-left ${active ? 'border-primary bg-primary/5' : 'border-transparent hover:bg-muted'}`}
                                onClick={() => setSelected(path)}
                            >
                                <CornerDownRight
                                    size={14}
                                    className="shrink-0 text-muted-foreground"
                                />
                                <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                    {item.label || 'Untitled link'}
                                </span>
                                <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                    {item.type ?? 'link'}
                                </span>
                            </button>
                        </div>
                        {!collapsed[key] &&
                            (item.children?.length ?? 0) > 0 &&
                            renderTree(item.children!, path)}
                    </li>
                );
            })}
        </ul>
    );
    const siblings =
        selected.length > 1
            ? (getTreeItem(menu.links, selected.slice(0, -1))?.children ?? [])
            : menu.links;
    const index = selected[selected.length - 1] ?? 0;
    return (
        <>
            <Head title="Navigation" />
            <AdminPage>
                <PageHeader
                    title="Navigation"
                    description="Build nested menus for your theme’s top navigation, left sidebar, mobile drawer and footer."
                />
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put('/admin/navigation', { preserveScroll: true });
                    }}
                    className="grid gap-5"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-4">
                        <div>
                            <p className="text-sm font-medium">
                                Store navigation
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {total} / {MENU_ITEMS} links · up to{' '}
                                {MENU_DEPTH} levels · shared across themes
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Button asChild variant="outline">
                                <Link href="/admin/pages">Manage pages</Link>
                            </Button>
                            <Button disabled={form.processing}>
                                <Save size={15} />
                                {form.processing
                                    ? 'Saving…'
                                    : 'Save navigation'}
                            </Button>
                        </div>
                    </div>
                    <div className="grid gap-5 xl:grid-cols-[220px_minmax(0,1fr)]">
                        <aside
                            className="grid content-start gap-1 rounded-xl border bg-card p-2"
                            aria-label="Menu placements"
                        >
                            {placements.map(
                                ({ key, title, description, icon: Icon }) => (
                                    <button
                                        key={key}
                                        type="button"
                                        onClick={() => {
                                            setPlacement(key);
                                            setSelected([]);
                                        }}
                                        aria-pressed={placement === key}
                                        className={`flex gap-3 rounded-lg px-3 py-3 text-left ${placement === key ? 'bg-primary/10 text-primary' : 'hover:bg-muted'}`}
                                    >
                                        <Icon
                                            size={18}
                                            className="mt-0.5 shrink-0"
                                        />
                                        <span>
                                            <strong className="block text-sm font-medium">
                                                {title}
                                            </strong>
                                            <small className="block text-xs text-muted-foreground">
                                                {description}
                                            </small>
                                        </span>
                                    </button>
                                ),
                            )}
                        </aside>
                        <section className="min-w-0 rounded-xl border bg-card">
                            <div className="flex flex-wrap items-center justify-between gap-4 border-b p-5">
                                <div>
                                    <h2 className="font-semibold">
                                        {region.title}
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        {region.description}
                                    </p>
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={menu.enabled !== false}
                                        onChange={(event) =>
                                            setMenu({
                                                ...menu,
                                                enabled: event.target.checked,
                                            })
                                        }
                                    />
                                    Show this menu
                                </label>
                            </div>
                            <div className="grid gap-4 border-b p-5 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="menu-title">
                                        Menu title
                                    </Label>
                                    <Input
                                        id="menu-title"
                                        value={menu.title}
                                        onChange={(event) =>
                                            setMenu({
                                                ...menu,
                                                title: event.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={errors[`${placement}.title`]}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="menu-layout">Layout</Label>
                                    <select
                                        id="menu-layout"
                                        className="h-9 rounded-md border bg-background px-3 text-sm"
                                        value={menu.layout}
                                        onChange={(event) =>
                                            setMenu({
                                                ...menu,
                                                layout: event.target
                                                    .value as StoreMenu['layout'],
                                            })
                                        }
                                    >
                                        <option value="horizontal">
                                            Horizontal · dropdown submenus
                                        </option>
                                        <option value="vertical">
                                            Vertical · expandable tree
                                        </option>
                                    </select>
                                    <p className="text-xs text-muted-foreground">
                                        Mobile drawers always expand vertically.
                                    </p>
                                </div>
                                {placement === 'mobile' && (
                                    <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                        <input
                                            type="checkbox"
                                            checked={
                                                menu.inherit_header === true
                                            }
                                            onChange={(event) => {
                                                setMenu({
                                                    ...menu,
                                                    inherit_header:
                                                        event.target.checked,
                                                });
                                                setSelected([]);
                                            }}
                                        />
                                        Use the top navigation tree on mobile
                                    </label>
                                )}
                            </div>
                            {inherited ? (
                                <div className="p-8">
                                    <p className="text-sm text-muted-foreground">
                                        Mobile follows your top navigation,
                                        including every submenu. Turn off
                                        inheritance to build a separate menu.
                                    </p>
                                    <Button
                                        type="button"
                                        className="mt-4"
                                        variant="outline"
                                        onClick={() => {
                                            setPlacement('header');
                                            setSelected([]);
                                        }}
                                    >
                                        Edit top navigation
                                    </Button>
                                </div>
                            ) : (
                                <div className="grid min-h-96 lg:grid-cols-[minmax(0,1fr)_minmax(280px,360px)]">
                                    <div className="min-w-0 p-4">
                                        <div className="mb-4 flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => add()}
                                                disabled={
                                                    total >= MENU_ITEMS ||
                                                    menu.links.length >=
                                                        maxRoots
                                                }
                                            >
                                                <Plus size={14} />
                                                Add link
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={importCategories}
                                                disabled={
                                                    total >= MENU_ITEMS ||
                                                    menu.links.length >=
                                                        maxRoots
                                                }
                                            >
                                                Add category tree
                                            </Button>
                                        </div>
                                        {menu.links.length ? (
                                            <div className="max-h-[640px] overflow-y-auto">
                                                {renderTree(menu.links)}
                                            </div>
                                        ) : (
                                            <div className="grid min-h-48 place-content-center gap-2 rounded-lg border border-dashed text-center">
                                                <Menu
                                                    className="mx-auto text-muted-foreground"
                                                    size={24}
                                                />
                                                <p className="text-sm text-muted-foreground">
                                                    Start with a link or import
                                                    your categories.
                                                </p>
                                            </div>
                                        )}
                                        <InputError
                                            message={
                                                errors[`${placement}.links`]
                                            }
                                        />
                                        <p className="mt-4 text-xs text-muted-foreground">
                                            Select a link to edit it. Indent
                                            places it under the previous link;
                                            outdent moves it up a level.
                                        </p>
                                    </div>
                                    <div className="min-w-0 border-t bg-muted/20 p-5 lg:border-t-0 lg:border-l">
                                        {current ? (
                                            <div className="grid gap-4">
                                                <div className="flex items-center justify-between">
                                                    <h3 className="text-sm font-semibold">
                                                        Link details
                                                    </h3>
                                                    <span className="text-xs text-muted-foreground">
                                                        Level {selected.length}{' '}
                                                        / {MENU_DEPTH}
                                                    </span>
                                                </div>
                                                <div className="flex flex-wrap gap-1">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label="Move link up"
                                                        disabled={index === 0}
                                                        onClick={() => move(-1)}
                                                    >
                                                        <ArrowUp size={15} />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label="Move link down"
                                                        disabled={
                                                            index ===
                                                            siblings.length - 1
                                                        }
                                                        onClick={() => move(1)}
                                                    >
                                                        <ArrowDown size={15} />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label="Indent link into previous item"
                                                        disabled={
                                                            index === 0 ||
                                                            selected.length +
                                                                treeDepth(
                                                                    current,
                                                                ) >
                                                                MENU_DEPTH
                                                        }
                                                        onClick={() =>
                                                            reparent('indent')
                                                        }
                                                    >
                                                        <ArrowRight size={15} />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="outline"
                                                        aria-label="Outdent link"
                                                        disabled={
                                                            selected.length < 2
                                                        }
                                                        onClick={() =>
                                                            reparent('outdent')
                                                        }
                                                    >
                                                        <ArrowLeft size={15} />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label="Remove link and its submenu"
                                                        onClick={() => {
                                                            setMenu({
                                                                ...menu,
                                                                links: removeTreeItem(
                                                                    menu.links,
                                                                    selected,
                                                                ),
                                                            });
                                                            setSelected([]);
                                                        }}
                                                    >
                                                        <Trash2 size={15} />
                                                    </Button>
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="link-label">
                                                        Label
                                                    </Label>
                                                    <Input
                                                        id="link-label"
                                                        value={current.label}
                                                        onChange={(event) =>
                                                            update({
                                                                label: event
                                                                    .target
                                                                    .value,
                                                            })
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors[
                                                                `${itemError}.label`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="link-type">
                                                        Destination
                                                    </Label>
                                                    <select
                                                        id="link-type"
                                                        className="h-9 rounded-md border bg-background px-3 text-sm"
                                                        value={
                                                            current.type ??
                                                            'link'
                                                        }
                                                        onChange={(event) =>
                                                            update({
                                                                type: event
                                                                    .target
                                                                    .value as StoreMenuItem['type'],
                                                                url: '',
                                                                page_id: null,
                                                                category_id:
                                                                    null,
                                                            })
                                                        }
                                                    >
                                                        <option value="link">
                                                            Custom link
                                                        </option>
                                                        <option value="page">
                                                            Store page
                                                        </option>
                                                        <option value="category">
                                                            Product category
                                                        </option>
                                                    </select>
                                                </div>
                                                {current.type === 'page' ||
                                                current.type === 'category' ? (
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="link-target">
                                                            Choose{' '}
                                                            {current.type}
                                                        </Label>
                                                        <select
                                                            id="link-target"
                                                            className="h-9 w-full rounded-md border bg-background px-2 text-sm"
                                                            value={
                                                                (current.type ===
                                                                'page'
                                                                    ? current.page_id
                                                                    : current.category_id) ??
                                                                ''
                                                            }
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                const id =
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    );
                                                                update(
                                                                    current.type ===
                                                                        'page'
                                                                        ? {
                                                                              page_id:
                                                                                  id ||
                                                                                  null,
                                                                          }
                                                                        : {
                                                                              category_id:
                                                                                  id ||
                                                                                  null,
                                                                          },
                                                                );
                                                            }}
                                                        >
                                                            <option value="">
                                                                Select a
                                                                destination
                                                            </option>
                                                            {current.type ===
                                                            'page'
                                                                ? pages.map(
                                                                      (
                                                                          page,
                                                                      ) => (
                                                                          <option
                                                                              key={
                                                                                  page.id
                                                                              }
                                                                              value={
                                                                                  page.id
                                                                              }
                                                                          >
                                                                              {
                                                                                  page.title
                                                                              }
                                                                              {page.status !==
                                                                              'published'
                                                                                  ? ' (draft · hidden)'
                                                                                  : ''}
                                                                          </option>
                                                                      ),
                                                                  )
                                                                : categories.map(
                                                                      (
                                                                          category,
                                                                      ) => (
                                                                          <option
                                                                              key={
                                                                                  category.id
                                                                              }
                                                                              value={
                                                                                  category.id
                                                                              }
                                                                          >
                                                                              {category.parent_id
                                                                                  ? '↳ '
                                                                                  : ''}
                                                                              {
                                                                                  category.name
                                                                              }
                                                                              {!category.is_active
                                                                                  ? ' (inactive · hidden)'
                                                                                  : ''}
                                                                          </option>
                                                                      ),
                                                                  )}
                                                        </select>
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `${itemError}.${current.type === 'page' ? 'page_id' : 'category_id'}`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                ) : (
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="link-url">
                                                            Link address
                                                        </Label>
                                                        <Input
                                                            id="link-url"
                                                            value={current.url}
                                                            placeholder="/search or https://example.com"
                                                            onChange={(event) =>
                                                                update({
                                                                    url: event
                                                                        .target
                                                                        .value,
                                                                })
                                                            }
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `${itemError}.url`
                                                                ]
                                                            }
                                                        />
                                                    </div>
                                                )}
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        add(selected)
                                                    }
                                                    disabled={
                                                        selected.length >=
                                                            MENU_DEPTH ||
                                                        total >= MENU_ITEMS ||
                                                        (current.children
                                                            ?.length ?? 0) >= 30
                                                    }
                                                >
                                                    <CornerDownRight
                                                        size={15}
                                                    />
                                                    Add submenu link
                                                </Button>
                                                <InputError
                                                    message={
                                                        errors[
                                                            `${itemError}.children`
                                                        ]
                                                    }
                                                />
                                                {(current.children?.length ??
                                                    0) > 0 && (
                                                    <p className="text-xs text-muted-foreground">
                                                        Removing this link also
                                                        removes its{' '}
                                                        {treeCount(
                                                            current.children ??
                                                                [],
                                                        )}{' '}
                                                        descendants. Changes
                                                        apply when you save.
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            <div className="grid min-h-64 place-content-center gap-2 text-center">
                                                <CornerDownRight
                                                    className="mx-auto text-muted-foreground"
                                                    size={24}
                                                />
                                                <p className="text-sm text-muted-foreground">
                                                    Select a link to edit its
                                                    destination and submenu.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </section>
                    </div>
                    {Object.keys(errors).length > 0 && (
                        <div
                            role="alert"
                            className="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
                        >
                            <p className="font-medium">
                                Navigation was not saved. Review the highlighted
                                fields.
                            </p>
                            <ul className="mt-2 list-inside list-disc">
                                {Object.entries(errors)
                                    .slice(0, 8)
                                    .map(([key, message]) => (
                                        <li key={key}>
                                            {key}: {message}
                                        </li>
                                    ))}
                            </ul>
                        </div>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="max-w-2xl text-xs text-muted-foreground">
                            Page and category links follow URL changes. Draft,
                            deleted and inactive destinations hide their branch.
                            Menus survive theme changes.
                        </p>
                        <Button disabled={form.processing}>
                            <Save size={15} />
                            Save navigation
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}
