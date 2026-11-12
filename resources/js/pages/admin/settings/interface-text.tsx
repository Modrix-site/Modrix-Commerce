import { Form, Head, Link } from '@inertiajs/react';
import InterfaceTextController from '@/actions/App/Http/Controllers/Admin/InterfaceTextController';
import { EmptyState } from '@/components/admin/empty-state';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { edit as languagesEdit } from '@/routes/admin/settings/languages';
import { index } from '@/routes/admin/settings/interface-text';
import type { Paginated } from '@/types';

type Row = {
    key: string;
    shipped: string | null;
    custom: string | null;
    translated: boolean;
};

type Props = {
    locales: { code: string; name: string }[];
    locale: string | null;
    filters: { search: string; filter: 'all' | 'missing' | 'custom' };
    rows: Paginated<Row>;
    counts: { total: number; translated: number; custom: number } | null;
};

export default function InterfaceText({
    locales,
    locale,
    filters,
    rows,
    counts,
}: Props) {
    const current = locales.find((l) => l.code === locale);

    return (
        <>
            <Head title="Storefront text" />
            <AdminPage>
                <PageHeader
                    title="Storefront text"
                    description="The storefront's own wording (buttons, labels, messages) in each language. Modrix Commerce ships Spanish, French and German; change any text here, or translate other languages. Leave a field empty to use the shipped translation, or English."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={languagesEdit()}>Languages</Link>
                        </Button>
                    }
                />

                {locales.length === 0 || !locale || !current ? (
                    <EmptyState
                        title="No languages to translate"
                        description="Storefront texts are written in English. Turn on another language to translate them."
                        action={
                            <Button asChild>
                                <Link href={languagesEdit()}>
                                    Choose languages
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <div className="flex flex-wrap gap-1" role="tablist">
                            {locales.map((l) => (
                                <Button
                                    key={l.code}
                                    size="sm"
                                    role="tab"
                                    aria-selected={l.code === locale}
                                    variant={
                                        l.code === locale
                                            ? 'default'
                                            : 'outline'
                                    }
                                    asChild
                                >
                                    <Link
                                        href={index({
                                            query: { locale: l.code },
                                        })}
                                    >
                                        {l.name}
                                    </Link>
                                </Button>
                            ))}
                        </div>

                        {counts && (
                            <p className="text-sm text-muted-foreground">
                                {counts.translated} of {counts.total} texts
                                translated into {current.name}
                                {counts.custom > 0 &&
                                    `, ${counts.custom} changed by you`}
                                .
                            </p>
                        )}

                        <Form
                            {...index.form()}
                            className="flex flex-wrap items-end gap-3"
                        >
                            <input type="hidden" name="locale" value={locale} />
                            <div className="grid gap-2">
                                <Label htmlFor="search">Search</Label>
                                <Input
                                    id="search"
                                    name="search"
                                    type="search"
                                    defaultValue={filters.search}
                                    placeholder="English or translated text"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="filter">Show</Label>
                                <NativeSelect
                                    id="filter"
                                    name="filter"
                                    defaultValue={filters.filter}
                                >
                                    <option value="all">All texts</option>
                                    <option value="missing">
                                        Not translated
                                    </option>
                                    <option value="custom">
                                        Changed by you
                                    </option>
                                </NativeSelect>
                            </div>
                            <Button type="submit" variant="secondary">
                                Filter
                            </Button>
                        </Form>

                        {rows.data.length === 0 ? (
                            <EmptyState title="No texts match" />
                        ) : (
                            <Form
                                {...InterfaceTextController.update.form(locale)}
                                options={{ preserveScroll: true }}
                                className="grid gap-4"
                            >
                                {({ processing }) => (
                                    <>
                                        <ul className="divide-y rounded-lg border">
                                            {rows.data.map((row, i) => (
                                                <li
                                                    key={row.key}
                                                    className="grid gap-2 p-3 lg:grid-cols-2 lg:items-center lg:gap-4"
                                                >
                                                    <div className="flex items-start gap-2 text-sm">
                                                        <span lang="en">
                                                            {row.key}
                                                        </span>
                                                        {!row.translated && (
                                                            <Badge variant="outline">
                                                                to do
                                                            </Badge>
                                                        )}
                                                        {row.custom !==
                                                            null && (
                                                            <Badge variant="secondary">
                                                                changed
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <input
                                                        type="hidden"
                                                        name={`texts[${i}][key]`}
                                                        value={row.key}
                                                    />
                                                    <Input
                                                        name={`texts[${i}][value]`}
                                                        lang={locale}
                                                        aria-label={`${current.name}: ${row.key}`}
                                                        defaultValue={
                                                            row.custom ?? ''
                                                        }
                                                        placeholder={
                                                            row.shipped ??
                                                            'Not translated: English is shown'
                                                        }
                                                        className={cn(
                                                            row.custom ===
                                                                null &&
                                                                row.shipped !==
                                                                    null &&
                                                                'placeholder:text-foreground/70',
                                                        )}
                                                    />
                                                </li>
                                            ))}
                                        </ul>
                                        <p className="text-xs text-muted-foreground">
                                            Keep placeholders such as :count or
                                            :name exactly as they are.
                                        </p>
                                        <div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Save this page
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        )}

                        <Pagination
                            from={rows.from}
                            to={rows.to}
                            total={rows.total}
                            prevUrl={rows.prev_page_url}
                            nextUrl={rows.next_page_url}
                        />
                    </>
                )}
            </AdminPage>
        </>
    );
}

InterfaceText.layout = {
    breadcrumbs: [{ title: 'Storefront text', href: index() }],
};
