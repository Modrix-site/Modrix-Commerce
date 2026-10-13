import { Form, Head } from '@inertiajs/react';
import { Download, Trash2 } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    counts: { active: number; unsubscribed: number };
    subscribers: {
        id: number;
        email: string;
        source: string;
        consented_at: string;
        unsubscribed_at: string | null;
        delete_url: string;
    }[];
    exportUrl: string;
};

export default function NewsletterAdmin({
    counts,
    subscribers,
    exportUrl,
}: Props) {
    return (
        <>
            <Head title="Newsletter" />
            <AdminPage>
                <PageHeader
                    title="Newsletter"
                    description={`${counts.active} active subscribers, ${counts.unsubscribed} unsubscribed. Export the list to your email tool; each row carries the consent and a personal unsubscribe link.`}
                    actions={
                        <Button variant="outline" asChild>
                            <a href={exportUrl}>
                                <Download /> Export CSV
                            </a>
                        </Button>
                    }
                />
                {subscribers.length === 0 ? (
                    <EmptyState
                        title="No subscribers yet"
                        description="The sign-up form appears on the home page and the order confirmation."
                    />
                ) : (
                    <ul className="divide-y rounded-lg border text-sm">
                        {subscribers.map((s) => (
                            <li
                                key={s.id}
                                className="flex flex-wrap items-center gap-2 px-4 py-2"
                            >
                                <span className="font-medium">{s.email}</span>
                                {s.unsubscribed_at ? (
                                    <Badge variant="outline">
                                        unsubscribed {s.unsubscribed_at}
                                    </Badge>
                                ) : (
                                    <span className="text-muted-foreground">
                                        since {s.consented_at} · {s.source}
                                    </span>
                                )}
                                <Form
                                    action={s.delete_url}
                                    method="delete"
                                    options={{ preserveScroll: true }}
                                    className="ml-auto"
                                >
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={`Erase ${s.email}`}
                                    >
                                        <Trash2 /> Erase
                                    </Button>
                                </Form>
                            </li>
                        ))}
                    </ul>
                )}
            </AdminPage>
        </>
    );
}
