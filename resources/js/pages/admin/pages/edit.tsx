import { Head, Link, useForm } from '@inertiajs/react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
type Fields = {
    title: string;
    slug: string;
    content: string;
    status: string;
    meta_title: string;
    meta_description: string;
};
export default function EditPage({
    page,
}: {
    page: ({ id: number } & Fields) | null;
}) {
    const form = useForm<Fields>({
        title: page?.title ?? '',
        slug: page?.slug ?? '',
        content: page?.content ?? '',
        status: page?.status ?? 'draft',
        meta_title: page?.meta_title ?? '',
        meta_description: page?.meta_description ?? '',
    });
    return (
        <>
            <Head title={page ? 'Edit page' : 'New page'} />
            <AdminPage>
                <PageHeader
                    title={page ? 'Edit page' : 'New page'}
                    description="Publish useful content for your storefront. Draft pages are only available in the admin."
                />
                <form
                    className="grid max-w-4xl gap-5 rounded-xl border bg-card p-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        if (page) form.put(`/admin/pages/${page.id}`);
                        else form.post('/admin/pages');
                    }}
                >
                    {(['title', 'slug'] as const).map((key) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={key}>
                                {key === 'title' ? 'Title' : 'URL slug'}
                            </Label>
                            <Input
                                id={key}
                                required
                                value={form.data[key]}
                                onChange={(e) => {
                                    form.setData(key, e.target.value);
                                    if (
                                        key === 'title' &&
                                        !page &&
                                        (!form.data.slug ||
                                            form.data.slug ===
                                                form.data.title
                                                    .toLowerCase()
                                                    .replace(/[^a-z0-9]+/g, '-')
                                                    .replace(/^-|-$/g, ''))
                                    )
                                        form.setData(
                                            'slug',
                                            e.target.value
                                                .toLowerCase()
                                                .replace(/[^a-z0-9]+/g, '-')
                                                .replace(/^-|-$/g, ''),
                                        );
                                }}
                            />
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                    <p className="text-sm text-muted-foreground">
                        Store address: /pages/{form.data.slug}
                    </p>
                    <Label htmlFor="content">Content</Label>
                    <Textarea
                        id="content"
                        rows={14}
                        value={form.data.content}
                        onChange={(e) =>
                            form.setData('content', e.target.value)
                        }
                    />
                    <InputError message={form.errors.content} />
                    <Label htmlFor="status">Visibility</Label>
                    <select
                        id="status"
                        className="rounded-md border bg-background p-2"
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                    <InputError message={form.errors.status} />
                    <Label htmlFor="meta_title">SEO title</Label>
                    <Input
                        id="meta_title"
                        value={form.data.meta_title}
                        onChange={(e) =>
                            form.setData('meta_title', e.target.value)
                        }
                    />
                    <InputError message={form.errors.meta_title} />
                    <Label htmlFor="meta_description">SEO description</Label>
                    <Textarea
                        id="meta_description"
                        value={form.data.meta_description}
                        onChange={(e) =>
                            form.setData('meta_description', e.target.value)
                        }
                    />
                    <InputError message={form.errors.meta_description} />
                    <div className="flex gap-3">
                        <Button disabled={form.processing}>Save page</Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/pages">Back to pages</Link>
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}
