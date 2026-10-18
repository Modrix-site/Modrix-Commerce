import { Form, Head } from '@inertiajs/react';
import { Download, Trash2 } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    counts: { leads: number; with_coupon: number };
    leads: {
        id: number;
        email: string;
        coupon_code: string | null;
        page: string | null;
        consented_at: string;
        delete_url: string;
    }[];
    exportUrl: string;
};

export default function PromoPopupAdmin({ counts, leads, exportUrl }: Props) {
    return (
        <>
            <Head title="Marketing Popup" />
            <AdminPage>
                <PageHeader
                    title="Marketing Popup"
                    description={`${counts.leads} leads collected, ${counts.with_coupon} promised a coupon. The pop-up's message, coupon and frequency are set in Extensions → Marketing Popup.`}
                    actions={
                        <Button variant="outline" asChild>
                            <a href={exportUrl}>
                                <Download /> Export CSV
                            </a>
                        </Button>
                    }
                />
                {leads.length === 0 ? (
                    <EmptyState
                        title="No leads yet"
                        description="Leads appear here when shoppers answer the pop-up on your storefront."
                    />
                ) : (
                    <ul className="divide-y rounded-lg border text-sm">
                        {leads.map((lead) => (
                            <li
                                key={lead.id}
                                className="flex flex-wrap items-center gap-2 px-4 py-2"
                            >
                                <span className="font-medium">
                                    {lead.email}
                                </span>
                                {lead.coupon_code && (
                                    <Badge variant="secondary">
                                        {lead.coupon_code}
                                    </Badge>
                                )}
                                <span className="text-muted-foreground">
                                    since {lead.consented_at}
                                    {lead.page && ` · ${lead.page}`}
                                </span>
                                <Form
                                    action={lead.delete_url}
                                    method="delete"
                                    options={{ preserveScroll: true }}
                                    className="ms-auto"
                                >
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={`Erase ${lead.email}`}
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
