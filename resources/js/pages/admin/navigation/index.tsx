import { Head, Link, useForm } from '@inertiajs/react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import type { StoreMenu, StoreMenus } from '@/types/storefront';
type Targets = {
    pages: { id: number; title: string; status: string }[];
    categories: {
        id: number;
        name: string;
        parent_id: number | null;
        is_active: boolean;
    }[];
};
export default function Navigation({
    menus,
    pages,
    categories,
}: { menus: StoreMenus } & Targets) {
    const form = useForm<StoreMenus>(menus);
    const errors: Record<string, string> = form.errors;
    const editor = (
        menu: StoreMenu,
        path: string,
        title: string,
        max: number,
        change: (menu: StoreMenu) => void,
    ) => (
        <section className="grid gap-4 rounded-xl border bg-card p-5">
            <h2 className="font-semibold">{title}</h2>
            <Label htmlFor={`${path}-title`}>Menu title</Label>
            <Input
                id={`${path}-title`}
                value={menu.title}
                onChange={(e) => change({ ...menu, title: e.target.value })}
            />
            <InputError message={errors[`${path}.title`]} />
            {menu.links.map((link, index) => {
                const update = (patch: Partial<typeof link>) =>
                    change({
                        ...menu,
                        links: menu.links.map((item, i) =>
                            i === index ? { ...item, ...patch } : item,
                        ),
                    });
                const move = (direction: number) => {
                    const links = [...menu.links];
                    [links[index], links[index + direction]] = [
                        links[index + direction],
                        links[index],
                    ];
                    change({ ...menu, links });
                };
                return (
                    <div
                        key={index}
                        className="grid gap-2 rounded-lg border p-3"
                    >
                        <div className="flex items-center justify-between">
                            <span>Link {index + 1}</span>
                            <div className="flex gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled={index === 0}
                                    onClick={() => move(-1)}
                                >
                                    Up
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled={index === menu.links.length - 1}
                                    onClick={() => move(1)}
                                >
                                    Down
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() =>
                                        change({
                                            ...menu,
                                            links: menu.links.filter(
                                                (_, i) => i !== index,
                                            ),
                                        })
                                    }
                                >
                                    Remove
                                </Button>
                            </div>
                        </div>
                        <Label htmlFor={`${path}-${index}-label`}>Label</Label>
                        <Input
                            id={`${path}-${index}-label`}
                            value={link.label}
                            onChange={(e) => update({ label: e.target.value })}
                        />
                        <InputError
                            message={errors[`${path}.links.${index}.label`]}
                        />
                        <Label htmlFor={`${path}-${index}-type`}>Link to</Label>
                        <select
                            id={`${path}-${index}-type`}
                            className="rounded-md border bg-background p-2"
                            value={link.type ?? 'link'}
                            onChange={(e) =>
                                update({
                                    type: e.target.value as
                                        | 'page'
                                        | 'category'
                                        | 'link',
                                    url: '',
                                    page_id: null,
                                    category_id: null,
                                })
                            }
                        >
                            <option value="link">Custom link</option>
                            <option value="page">Page</option>
                            <option value="category">Category</option>
                        </select>
                        {link.type === 'page' || link.type === 'category' ? (
                            <>
                                <Label htmlFor={`${path}-${index}-target`}>
                                    Choose {link.type}
                                </Label>
                                <select
                                    id={`${path}-${index}-target`}
                                    className="rounded-md border bg-background p-2"
                                    value={
                                        (link.type === 'page'
                                            ? link.page_id
                                            : link.category_id) ?? ''
                                    }
                                    onChange={(e) => {
                                        const id = Number(e.target.value);
                                        const name =
                                            link.type === 'page'
                                                ? pages.find((p) => p.id === id)
                                                      ?.title
                                                : categories.find(
                                                      (c) => c.id === id,
                                                  )?.name;
                                        update({
                                            [link.type === 'page'
                                                ? 'page_id'
                                                : 'category_id']: id || null,
                                            label: link.label || name || '',
                                        });
                                    }}
                                >
                                    <option value="">
                                        Select a {link.type}
                                    </option>
                                    {link.type === 'page'
                                        ? pages.map((p) => (
                                              <option key={p.id} value={p.id}>
                                                  {p.title}
                                                  {p.status === 'draft'
                                                      ? ' (draft - hidden)'
                                                      : ''}
                                              </option>
                                          ))
                                        : categories.map((c) => (
                                              <option key={c.id} value={c.id}>
                                                  {c.parent_id
                                                      ? 'Subcategory: '
                                                      : ''}
                                                  {c.name}
                                                  {!c.is_active
                                                      ? ' (inactive - hidden)'
                                                      : ''}
                                              </option>
                                          ))}
                                </select>
                                <InputError
                                    message={
                                        errors[
                                            `${path}.links.${index}.${link.type === 'page' ? 'page_id' : 'category_id'}`
                                        ]
                                    }
                                />
                            </>
                        ) : (
                            <>
                                <Label htmlFor={`${path}-${index}-url`}>
                                    Link address
                                </Label>
                                <Input
                                    id={`${path}-${index}-url`}
                                    placeholder="/search or https://example.com"
                                    value={link.url}
                                    onChange={(e) =>
                                        update({ url: e.target.value })
                                    }
                                />
                                <InputError
                                    message={
                                        errors[`${path}.links.${index}.url`]
                                    }
                                />
                            </>
                        )}
                    </div>
                );
            })}
            <InputError message={errors[`${path}.links`]} />
            <Button
                type="button"
                variant="outline"
                disabled={menu.links.length >= max}
                onClick={() =>
                    change({
                        ...menu,
                        links: [
                            ...menu.links,
                            { label: '', url: '', type: 'link' },
                        ],
                    })
                }
            >
                Add link
            </Button>
        </section>
    );
    return (
        <>
            <Head title="Navigation" />
            <AdminPage>
                <PageHeader
                    title="Navigation"
                    description="Connect header and footer menus to pages, categories or custom links. Published pages and active categories appear automatically."
                />
                <Button asChild variant="outline" className="mb-4">
                    <Link href="/admin/pages">Manage pages</Link>
                </Button>
                <form
                    className="grid gap-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put('/admin/navigation', { preserveScroll: true });
                    }}
                >
                    {editor(
                        form.data.header,
                        'header',
                        'Header navigation',
                        30,
                        (menu) => form.setData('header', menu),
                    )}
                    <div className="grid gap-4 xl:grid-cols-3">
                        {form.data.footer.map((menu, index) => (
                            <div key={index}>
                                {editor(
                                    menu,
                                    `footer.${index}`,
                                    `Footer column ${index + 1}`,
                                    20,
                                    (next) =>
                                        form.setData(
                                            'footer',
                                            form.data.footer.map((item, i) =>
                                                i === index ? next : item,
                                            ),
                                        ),
                                )}
                            </div>
                        ))}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Page and category links follow URL changes. Draft,
                        deleted and inactive destinations are hidden from the
                        storefront.
                    </p>
                    <Button className="w-fit" disabled={form.processing}>
                        Save navigation
                    </Button>
                </form>
            </AdminPage>
        </>
    );
}
