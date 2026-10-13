import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/translations';

type Field =
    | 'name'
    | 'short_description'
    | 'description'
    | 'meta_title'
    | 'meta_description';

type Props = {
    kind: 'product' | 'category';
    item: { id: number; name: string; slug: string; backUrl: string };
    fields: Field[];
    defaultLocale: { code: string; name: string };
    source: Partial<Record<Field, string | null>>;
    locales: {
        code: string;
        name: string;
        values: Partial<Record<Field, string | null>>;
        updateUrl: string;
    }[];
    languagesUrl: string;
};

const LABELS: Record<Field, string> = {
    name: 'Name',
    short_description: 'Short description',
    description: 'Description',
    meta_title: 'SEO title',
    meta_description: 'SEO description',
};

const LONG: Field[] = ['short_description', 'description', 'meta_description'];

export default function TranslationEdit({
    kind,
    item,
    fields,
    defaultLocale,
    source,
    locales,
    languagesUrl,
}: Props) {
    const [active, setActive] = useState(locales[0]?.code ?? '');
    const current = locales.find((l) => l.code === active);

    return (
        <>
            <Head title={`Translations: ${item.name}`} />
            <AdminPage>
                <PageHeader
                    title={`Translations: ${item.name}`}
                    description={`The left column shows the ${defaultLocale.name} text. Leave a field empty to show the ${defaultLocale.name} text in that language.`}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={item.backUrl}>Back to the {kind}</Link>
                        </Button>
                    }
                />

                {locales.length === 0 ? (
                    <EmptyState
                        title="No additional languages"
                        description="Turn on a second storefront language first."
                        action={
                            <Button asChild>
                                <Link href={languagesUrl}>
                                    Choose languages
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <div
                            role="tablist"
                            aria-label="Languages"
                            className="flex flex-wrap gap-1"
                        >
                            {locales.map((l) => {
                                const done = Boolean(l.values.name);

                                return (
                                    <button
                                        key={l.code}
                                        role="tab"
                                        type="button"
                                        aria-selected={l.code === active}
                                        onClick={() => setActive(l.code)}
                                        className={cn(
                                            'flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm',
                                            l.code === active
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'hover:bg-muted',
                                        )}
                                    >
                                        {l.name}
                                        <Badge
                                            variant={
                                                done ? 'secondary' : 'outline'
                                            }
                                            className={cn(
                                                l.code === active &&
                                                    'border-primary-foreground/60 text-primary-foreground',
                                                l.code === active &&
                                                    done &&
                                                    'bg-primary-foreground text-primary',
                                            )}
                                        >
                                            {done ? 'translated' : 'to do'}
                                        </Badge>
                                    </button>
                                );
                            })}
                        </div>

                        {current && (
                            <Card key={current.code}>
                                <CardContent>
                                    <Form
                                        action={current.updateUrl}
                                        method="put"
                                        options={{ preserveScroll: true }}
                                        className="grid gap-5"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                {fields.map((field) => {
                                                    const id = `${current.code}-${field}`;
                                                    const isLong =
                                                        LONG.includes(field);

                                                    return (
                                                        <div
                                                            key={field}
                                                            className="grid gap-2 lg:grid-cols-2 lg:gap-4"
                                                        >
                                                            <div className="grid content-start gap-1">
                                                                <span className="text-xs font-medium text-muted-foreground">
                                                                    {
                                                                        LABELS[
                                                                            field
                                                                        ]
                                                                    }{' '}
                                                                    (
                                                                    {
                                                                        defaultLocale.name
                                                                    }
                                                                    )
                                                                </span>
                                                                <p
                                                                    lang={
                                                                        defaultLocale.code
                                                                    }
                                                                    className="max-h-40 overflow-y-auto rounded-md bg-muted p-2 text-sm whitespace-pre-line"
                                                                >
                                                                    {source[
                                                                        field
                                                                    ] || '—'}
                                                                </p>
                                                            </div>
                                                            <div className="grid content-start gap-1">
                                                                <Label
                                                                    htmlFor={id}
                                                                >
                                                                    {
                                                                        LABELS[
                                                                            field
                                                                        ]
                                                                    }{' '}
                                                                    (
                                                                    {
                                                                        current.name
                                                                    }
                                                                    )
                                                                </Label>
                                                                {isLong ? (
                                                                    <Textarea
                                                                        id={id}
                                                                        name={
                                                                            field
                                                                        }
                                                                        lang={
                                                                            current.code
                                                                        }
                                                                        rows={
                                                                            field ===
                                                                            'description'
                                                                                ? 8
                                                                                : 3
                                                                        }
                                                                        defaultValue={
                                                                            current
                                                                                .values[
                                                                                field
                                                                            ] ??
                                                                            ''
                                                                        }
                                                                    />
                                                                ) : (
                                                                    <Input
                                                                        id={id}
                                                                        name={
                                                                            field
                                                                        }
                                                                        lang={
                                                                            current.code
                                                                        }
                                                                        defaultValue={
                                                                            current
                                                                                .values[
                                                                                field
                                                                            ] ??
                                                                            ''
                                                                        }
                                                                    />
                                                                )}
                                                                <InputError
                                                                    message={
                                                                        errors[
                                                                            field
                                                                        ]
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                                <div>
                                                    <Button
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        Save {current.name}
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}
            </AdminPage>
        </>
    );
}

TranslationEdit.layout = {
    breadcrumbs: [{ title: 'Translations', href: index() }],
};
