import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { StatusBadge } from '@/components/admin/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Page = {
    id: number;
    title: string;
    slug: string;
    kind: string;
    status: string;
    updated_at: string | null;
    deleted_at: string | null;
};

const filters = [
    { id: 'all', label: 'All' },
    { id: 'published', label: 'Published' },
    { id: 'draft', label: 'Drafts' },
    { id: 'deleted', label: 'Deleted' },
] as const;

export default function Pages({ pages }: { pages: Page[] }) {
    const [search, setSearch] = useState('');
    const [filter, setFilter] =
        useState<(typeof filters)[number]['id']>('all');
    const visible = pages.filter((page) => {
        const matchesSearch = `${page.title} ${page.slug}`
            .toLowerCase()
            .includes(search.toLowerCase());
        const matchesFilter =
            filter === 'all' ||
            (filter === 'deleted'
                ? page.deleted_at !== null
                : page.deleted_at === null && page.status === filter);

        return matchesSearch && matchesFilter;
    });

    return (
        <>
            <Head title="Pages" />
            <AdminPage>
                <PageHeader
                    title="Pages"
                    description="Information, policies and FAQ pages. A published FAQ page is linked from the footer and edited here."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/admin/pages/create?kind=faq">
                                    Add FAQ page
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href="/admin/pages/create">Add page</Link>
                            </Button>
                        </div>
                    }
                />
                <div className="mb-5 flex flex-wrap items-center gap-3">
                    <Input
                        aria-label="Search pages"
                        placeholder="Search pages"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />
                    <div className="flex flex-wrap gap-1">
                        {filters.map((item) => (
                            <Button
                                key={item.id}
                                type="button"
                                size="sm"
                                variant={
                                    filter === item.id ? 'default' : 'outline'
                                }
                                onClick={() => setFilter(item.id)}
                            >
                                {item.label}
                            </Button>
                        ))}
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/admin/navigation">Navigation</Link>
                    </Button>
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="p-4 font-medium">Title</th>
                                <th className="p-4 font-medium">Address</th>
                                <th className="p-4 font-medium">Status</th>
                                <th className="p-4 font-medium">Updated</th>
                                <th className="p-4 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visible.map((page) => (
                                <tr key={page.id} className="border-b">
                                    <td className="p-4 font-medium">
                                        <span className="inline-flex items-center gap-2">
                                            {page.title}
                                            {page.kind === 'faq' && (
                                                <Badge variant="secondary">
                                                    FAQ
                                                </Badge>
                                            )}
                                        </span>
                                    </td>
                                    <td className="p-4 text-muted-foreground">
                                        /pages/{page.slug}
                                    </td>
                                    <td className="p-4">
                                        {page.deleted_at ? (
                                            <Badge variant="destructive">
                                                Deleted
                                            </Badge>
                                        ) : (
                                            <StatusBadge status={page.status} />
                                        )}
                                    </td>
                                    <td className="p-4 text-muted-foreground">
                                        {page.updated_at
                                            ? new Date(
                                                  page.updated_at,
                                              ).toLocaleDateString()
                                            : ''}
                                    </td>
                                    <td className="p-4">
                                        <div className="flex flex-wrap gap-2">
                                            {page.deleted_at ? (
                                                <Button
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/admin/pages/${page.id}/restore`,
                                                        )
                                                    }
                                                >
                                                    Restore
                                                </Button>
                                            ) : (
                                                <>
                                                    <Button
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/admin/pages/${page.id}/edit`}
                                                        >
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                    {page.status ===
                                                        'published' && (
                                                        <Button
                                                            variant="outline"
                                                            asChild
                                                        >
                                                            <a
                                                                href={`/pages/${page.slug}`}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                View
                                                            </a>
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `/admin/pages/${page.id}/duplicate`,
                                                            )
                                                        }
                                                    >
                                                        Duplicate
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        onClick={() => {
                                                            if (
                                                                window.confirm(
                                                                    'Move this page to trash? Its menu links will be hidden until restored.',
                                                                )
                                                            )
                                                                router.delete(
                                                                    `/admin/pages/${page.id}`,
                                                                );
                                                        }}
                                                    >
                                                        Delete
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {visible.length === 0 && (
                        <p className="p-6 text-muted-foreground">
                            {pages.length === 0
                                ? 'No pages yet. Add your first page.'
                                : 'No pages match this search.'}
                        </p>
                    )}
                </div>
            </AdminPage>
        </>
    );
}
