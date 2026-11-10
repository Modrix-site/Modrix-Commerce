import { Form, Head } from '@inertiajs/react';
import { Download, Trash2 } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    counts: { total: number; accepted: number; rejected: number; customised: number };
    records: {
        id: number;
        decision: string;
        analytics_allowed: boolean;
        marketing_allowed: boolean;
        country_code: string | null;
        page: string | null;
        decided_at: string;
        delete_url: string;
    }[];
    exportUrl: string;
};

const decisionLabel = (decision: string): string =>
    decision === 'accept' ? 'Accepted' : decision === 'reject' ? 'Rejected' : 'Custom';

export default function ConsentAdmin({ counts, records, exportUrl }: Props) {
    return (
        <>
            <Head title="Cookie Consent" />
            <AdminPage>
                <PageHeader
                    title="Cookie Consent"
                    description={`${counts.total} decisions recorded — ${counts.accepted} accepted, ${counts.rejected} rejected, ${counts.customised} customised. The banner's wording and audience are set in Extensions → GDPR Cookie Consent.`}
                    actions={
                        <Button variant="outline" asChild>
                            <a href={exportUrl}>
                                <Download /> Export CSV
                            </a>
                        </Button>
                    }
                />
                {records.length === 0 ? (
                    <EmptyState
                        title="No consent records yet"
                        description="Decisions appear here when visitors answer the cookie banner on your storefront."
                    />
                ) : (
                    <ul className="divide-y rounded-lg border text-sm">
                        {records.map((record) => (
                            <li
                                key={record.id}
                                className="flex flex-wrap items-center gap-2 px-4 py-2"
                            >
                                <span className="font-medium">
                                    {decisionLabel(record.decision)}
                                </span>
                                {record.decision !== 'accept' && (
                                    <>
                                        <Badge variant="secondary">
                                            analytics{' '}
                                            {record.analytics_allowed ? 'on' : 'off'}
                                        </Badge>
                                        <Badge variant="secondary">
                                            marketing{' '}
                                            {record.marketing_allowed ? 'on' : 'off'}
                                        </Badge>
                                    </>
                                )}
                                <span className="text-muted-foreground">
                                    {record.decided_at}
                                    {record.country_code && ` · ${record.country_code}`}
                                    {record.page && ` · ${record.page}`}
                                </span>
                                <Form
                                    action={record.delete_url}
                                    method="delete"
                                    options={{ preserveScroll: true }}
                                    className="ms-auto"
                                >
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={`Erase consent record ${record.id}`}
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
