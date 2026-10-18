import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
type Page = {
    id: number;
    title: string;
    slug: string;
    status: string;
    deleted_at: string | null;
};
export default function Pages({ pages }: { pages: Page[] }) {
    const [search, setSearch] = useState('');
    return (
        <>
            <Head title="Pages" />
            <AdminPage>
                <PageHeader
                    title="Pages"
                    description="Create store information, policies and landing pages. Connect published pages to header and footer navigation."
                />
                <div className="mb-5 flex gap-3">
                    <Input
                        aria-label="Search pages"
                        placeholder="Search pages�"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Button asChild>
                        <Link href="/admin/pages/create">Add page</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/admin/navigation">Navigation</Link>
                    </Button>
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="p-4">Title</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pages
                                .filter((p) =>
                                    `${p.title} ${p.slug}`
                                        .toLowerCase()
                                        .includes(search.toLowerCase()),
                                )
                                .map((p) => (
                                    <tr key={p.id} className="border-b">
                                        <td className="p-4 font-medium">
                                            {p.title}
                                        </td>
                                        <td>/pages/{p.slug}</td>
                                        <td>
                                            {p.deleted_at
                                                ? 'Deleted'
                                                : p.status}
                                        </td>
                                        <td className="flex gap-2 p-3">
                                            {p.deleted_at ? (
                                                <Button
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/admin/pages/${p.id}/restore`,
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
                                                            href={`/admin/pages/${p.id}/edit`}
                                                        >
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                    {p.status ===
                                                        'published' && (
                                                        <Button
                                                            variant="outline"
                                                            asChild
                                                        >
                                                            <a
                                                                href={`/pages/${p.slug}`}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                View
                                                            </a>
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        onClick={() => {
                                                            if (
                                                                window.confirm(
                                                                    'Move this page to trash? Its menu links will be hidden until restored.',
                                                                )
                                                            )
                                                                router.delete(
                                                                    `/admin/pages/${p.id}`,
                                                                );
                                                        }}
                                                    >
                                                        Delete
                                                    </Button>
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                        </tbody>
                    </table>
                    {pages.length === 0 && (
                        <p className="p-6 text-muted-foreground">
                            No pages yet. Add your first page.
                        </p>
                    )}
                </div>
            </AdminPage>
        </>
    );
}
