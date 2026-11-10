import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { RichTextEditor } from '@/components/admin/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Faq = { question: string; answer: string };

type Fields = {
    title: string;
    slug: string;
    kind: string;
    content: string;
    faqs: Faq[];
    status: string;
    meta_title: string;
    meta_description: string;
};

const slugify = (value: string) =>
    value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

export default function EditPage({
    page,
    kind = 'page',
}: {
    page: ({ id: number; faqs?: Faq[] | null } & Omit<Fields, 'faqs'>) | null;
    kind?: string;
}) {
    const startingKind = page?.kind ?? kind;
    const form = useForm<Fields>({
        title: page?.title ?? (startingKind === 'faq' ? 'FAQs' : ''),
        slug: page?.slug ?? (startingKind === 'faq' ? 'faqs' : ''),
        kind: startingKind === 'faq' ? 'faq' : 'page',
        content: page?.content ?? '',
        faqs:
            page?.faqs && page.faqs.length > 0
                ? page.faqs
                : startingKind === 'faq'
                  ? [{ question: '', answer: '' }]
                  : [],
        status: page?.status ?? 'draft',
        meta_title: page?.meta_title ?? '',
        meta_description: page?.meta_description ?? '',
    });
    const updateFaq = (index: number, patch: Partial<Faq>) =>
        form.setData(
            'faqs',
            form.data.faqs.map((item, itemIndex) =>
                itemIndex === index ? { ...item, ...patch } : item,
            ),
        );

    return (
        <>
            <Head title={page ? 'Edit page' : 'New page'} />
            <AdminPage>
                <PageHeader
                    title={page ? page.title || 'Edit page' : 'New page'}
                    description={
                        form.data.kind === 'faq'
                            ? 'Edit the introduction and each question. Publishing links this page from the store footer.'
                            : 'Write the page with headings, lists and links. Drafts stay in the admin until you publish them.'
                    }
                    actions={
                        page ? (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            `/admin/pages/${page.id}/duplicate`,
                                        )
                                    }
                                >
                                    Duplicate
                                </Button>
                                {page.status === 'published' && (
                                    <Button variant="outline" asChild>
                                        <a
                                            href={`/pages/${page.slug}`}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            View page
                                        </a>
                                    </Button>
                                )}
                            </div>
                        ) : undefined
                    }
                />
                <form
                    className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (page) form.put(`/admin/pages/${page.id}`);
                        else form.post('/admin/pages');
                    }}
                >
                    <div className="grid gap-5 rounded-xl border bg-card p-6">
                        <FormField
                            id="title"
                            label="Title"
                            error={form.errors.title}
                        >
                            <Input
                                id="title"
                                required
                                value={form.data.title}
                                onChange={(e) => {
                                    const title = e.target.value;
                                    const previous = slugify(form.data.title);
                                    const followSlug =
                                        !page &&
                                        (form.data.slug === '' ||
                                            form.data.slug === previous);

                                    form.setData({
                                        ...form.data,
                                        title,
                                        slug: followSlug
                                            ? slugify(title)
                                            : form.data.slug,
                                    });
                                }}
                            />
                        </FormField>
                        <FormField
                            id="content"
                            label="Content"
                            error={form.errors.content}
                            hint={
                                form.data.kind === 'faq'
                                    ? 'Shown above the questions. Formatting, links and pasted styles follow the same rules as other pages.'
                                    : 'Headings, bold text, lists and links. Pasted styles and images are removed.'
                            }
                        >
                            <RichTextEditor
                                id="content"
                                label="Content"
                                value={form.data.content}
                                invalid={!!form.errors.content}
                                placeholder="Write the page…"
                                onChange={(html) =>
                                    form.setData('content', html)
                                }
                            />
                        </FormField>
                        {form.data.kind === 'faq' && (
                            <div className="grid gap-4">
                                <div className="flex items-center justify-between gap-3">
                                    <h2 className="text-sm font-medium">
                                        Questions
                                    </h2>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            form.setData('faqs', [
                                                ...form.data.faqs,
                                                { question: '', answer: '' },
                                            ])
                                        }
                                    >
                                        Add question
                                    </Button>
                                </div>
                                {form.data.faqs.map((item, index) => (
                                    <div
                                        key={index}
                                        className="grid gap-3 rounded-lg border p-4"
                                    >
                                        <FormField
                                            id={`faq-question-${index}`}
                                            label={`Question ${index + 1}`}
                                            error={
                                                form.errors[
                                                    `faqs.${index}.question`
                                                ]
                                            }
                                        >
                                            <Input
                                                id={`faq-question-${index}`}
                                                value={item.question}
                                                onChange={(e) =>
                                                    updateFaq(index, {
                                                        question:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id={`faq-answer-${index}`}
                                            label="Answer"
                                            error={
                                                form.errors[
                                                    `faqs.${index}.answer`
                                                ]
                                            }
                                        >
                                            <RichTextEditor
                                                id={`faq-answer-${index}`}
                                                label={`Answer ${index + 1}`}
                                                value={item.answer}
                                                onChange={(html) =>
                                                    updateFaq(index, {
                                                        answer: html,
                                                    })
                                                }
                                            />
                                        </FormField>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={index === 0}
                                                onClick={() => {
                                                    const faqs = [
                                                        ...form.data.faqs,
                                                    ];
                                                    const previous =
                                                        faqs[index - 1];
                                                    faqs[index - 1] =
                                                        faqs[index];
                                                    faqs[index] = previous;
                                                    form.setData('faqs', faqs);
                                                }}
                                            >
                                                Move up
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    index ===
                                                    form.data.faqs.length - 1
                                                }
                                                onClick={() => {
                                                    const faqs = [
                                                        ...form.data.faqs,
                                                    ];
                                                    const next =
                                                        faqs[index + 1];
                                                    faqs[index + 1] =
                                                        faqs[index];
                                                    faqs[index] = next;
                                                    form.setData('faqs', faqs);
                                                }}
                                            >
                                                Move down
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    form.setData(
                                                        'faqs',
                                                        form.data.faqs.filter(
                                                            (_, itemIndex) =>
                                                                itemIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="grid content-start gap-6">
                        <div className="grid gap-4 rounded-xl border bg-card p-6">
                            <FormField
                                id="kind"
                                label="Page type"
                                error={form.errors.kind}
                            >
                                <NativeSelect
                                    id="kind"
                                    value={form.data.kind}
                                    onChange={(e) => {
                                        const next = e.target.value;
                                        form.setData({
                                            ...form.data,
                                            kind: next,
                                            faqs:
                                                next === 'faq' &&
                                                form.data.faqs.length === 0
                                                    ? [
                                                          {
                                                              question: '',
                                                              answer: '',
                                                          },
                                                      ]
                                                    : form.data.faqs,
                                        });
                                    }}
                                >
                                    <option value="page">Standard page</option>
                                    <option value="faq">FAQ page</option>
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="status"
                                label="Visibility"
                                error={form.errors.status}
                            >
                                <NativeSelect
                                    id="status"
                                    value={form.data.status}
                                    onChange={(e) =>
                                        form.setData('status', e.target.value)
                                    }
                                >
                                    <option value="draft">Draft</option>
                                    <option value="published">
                                        Published
                                    </option>
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="slug"
                                label="URL slug"
                                error={form.errors.slug}
                                hint={`/pages/${form.data.slug || 'your-page'}`}
                            >
                                <Input
                                    id="slug"
                                    required
                                    value={form.data.slug}
                                    onChange={(e) =>
                                        form.setData('slug', e.target.value)
                                    }
                                />
                            </FormField>
                            <div className="flex flex-wrap gap-3">
                                <Button disabled={form.processing}>
                                    Save page
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/admin/pages">
                                        Back to pages
                                    </Link>
                                </Button>
                            </div>
                        </div>
                        <div className="grid gap-4 rounded-xl border bg-card p-6">
                            <div>
                                <h2 className="text-sm font-medium">
                                    Search listing
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Shown in search results. The page title is
                                    used when these are empty.
                                </p>
                            </div>
                            <FormField
                                id="meta_title"
                                label="SEO title"
                                error={form.errors.meta_title}
                            >
                                <Input
                                    id="meta_title"
                                    value={form.data.meta_title}
                                    maxLength={255}
                                    onChange={(e) =>
                                        form.setData(
                                            'meta_title',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="meta_description"
                                label="SEO description"
                                error={form.errors.meta_description}
                                hint="A short summary for search results, not the page body."
                            >
                                <Textarea
                                    id="meta_description"
                                    rows={4}
                                    maxLength={500}
                                    value={form.data.meta_description}
                                    onChange={(e) =>
                                        form.setData(
                                            'meta_description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </div>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}
