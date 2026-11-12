import { Head, Link } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/translations';
import { edit as languagesEdit } from '@/routes/admin/settings/languages';
import type { Paginated } from '@/types';

type Props = {
    defaultLocale: { code: string; name: string };
    stats: {
        code: string;
        name: string;
        products: number;
        categories: number;
    }[];
    totals: { products: number; categories: number };
    locale: string | null;
    type: 'products' | 'categories';
    missing: Paginated<{ id: number; name: string; url: string }> | null;
};

const percent = (part: number, total: number) =>
    total === 0 ? 100 : Math.round((part / total) * 100);

export default function TranslationsIndex({
    defaultLocale,
    stats,
    totals,
    locale,
    type,
    missing,
}: Props) {
    return (
        <>
            <Head title="Translations" />
            <AdminPage>
                <PageHeader
                    title="Translations"
                    description={`Texts are written in ${defaultLocale.name}. Translate products and categories for each additional storefront language; anything left empty shows the ${defaultLocale.name} text.`}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={languagesEdit()}>
                                <Languages /> Languages
                            </Link>
                        </Button>
                    }
                />

                {stats.length === 0 ? (
                    <EmptyState
                        title="No additional languages"
                        description="Turn on a second storefront language to start translating."
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
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {stats.map((s) => (
                                <Card
                                    key={s.code}
                                    className={cn(
                                        'py-4',
                                        s.code === locale && 'border-primary',
                                    )}
                                >
                                    <CardContent className="grid gap-2 text-sm">
                                        <Link
                                            href={index({
                                                query: { locale: s.code, type },
                                            })}
                                            className="font-medium hover:underline"
                                        >
                                            {s.name}
                                        </Link>
                                        <Progress
                                            label="Products"
                                            value={s.products}
                                            total={totals.products}
                                        />
                                        <Progress
                                            label="Categories"
                                            value={s.categories}
                                            total={totals.categories}
                                        />
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        {missing && locale && (
                            <section className="grid gap-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-lg font-semibold">
                                        Not yet translated into{' '}
                                        {
                                            stats.find((s) => s.code === locale)
                                                ?.name
                                        }
                                    </h2>
                                    <div className="ml-auto flex gap-1">
                                        {(
                                            ['products', 'categories'] as const
                                        ).map((t) => (
                                            <Button
                                                key={t}
                                                size="sm"
                                                variant={
                                                    t === type
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                asChild
                                            >
                                                <Link
                                                    href={index({
                                                        query: {
                                                            locale,
                                                            type: t,
                                                        },
                                                    })}
                                                >
                                                    {t === 'products'
                                                        ? 'Products'
                                                        : 'Categories'}
                                                </Link>
                                            </Button>
                                        ))}
                                    </div>
                                </div>
                                {missing.data.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Everything is translated.
                                    </p>
                                ) : (
                                    <ul className="divide-y rounded-lg border">
                                        {missing.data.map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex items-center justify-between gap-2 px-4 py-2 text-sm"
                                            >
                                                <span>{item.name}</span>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <Link href={item.url}>
                                                        Translate
                                                    </Link>
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <Pagination
                                    from={missing.from}
                                    to={missing.to}
                                    total={missing.total}
                                    prevUrl={missing.prev_page_url}
                                    nextUrl={missing.next_page_url}
                                />
                            </section>
                        )}
                    </>
                )}
            </AdminPage>
        </>
    );
}

function Progress({
    label,
    value,
    total,
}: {
    label: string;
    value: number;
    total: number;
}) {
    const pct = percent(value, total);

    return (
        <div className="grid gap-1">
            <span className="flex justify-between text-muted-foreground">
                {label}
                <span>
                    {value} / {total}
                </span>
            </span>
            <div
                className="h-2 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-label={`${label} translated`}
                aria-valuenow={pct}
                aria-valuemin={0}
                aria-valuemax={100}
            >
                <div
                    className="h-full bg-primary"
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
}

TranslationsIndex.layout = {
    breadcrumbs: [{ title: 'Translations', href: index() }],
};
