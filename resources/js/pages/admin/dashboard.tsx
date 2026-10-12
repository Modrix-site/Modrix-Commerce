import { Head, Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { AuditTable } from '@/components/admin/audit-table';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import { index as auditIndex } from '@/routes/admin/audit-logs';
import type { AuditLogEntry } from '@/types';

type Props = {
    store: {
        store_name: string;
        default_currency: string;
        timezone: string;
    };
    stats: { staff: number; active_staff: number; customers: number };
    recentActivity: AuditLogEntry[] | null;
    warnings: string[];
};

export default function AdminDashboard({
    store,
    stats,
    recentActivity,
    warnings,
}: Props) {
    return (
        <>
            <Head title="Dashboard" />
            <AdminPage>
                <PageHeader
                    title={store.store_name}
                    description={`Currency ${store.default_currency} · Timezone ${store.timezone}`}
                />

                {warnings.map((warning) => (
                    <Alert key={warning}>
                        <TriangleAlert />
                        <AlertTitle>Action recommended</AlertTitle>
                        <AlertDescription>{warning}</AlertDescription>
                    </Alert>
                ))}

                <div className="grid gap-4 sm:grid-cols-3">
                    <Stat label="Staff accounts" value={stats.staff} />
                    <Stat label="Active staff" value={stats.active_staff} />
                    <Stat label="Customer accounts" value={stats.customers} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Commerce metrics</CardTitle>
                        <CardDescription>
                            Sales, orders, fulfilment and low-stock metrics will
                            appear here once the commerce core (Phase 2) is
                            installed. Nothing is estimated or simulated.
                        </CardDescription>
                    </CardHeader>
                </Card>

                {recentActivity !== null && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div className="space-y-1.5">
                                <CardTitle>Recent activity</CardTitle>
                                <CardDescription>
                                    Latest entries from the audit log.
                                </CardDescription>
                            </div>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={auditIndex()}>View all</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <AuditTable entries={recentActivity} compact />
                        </CardContent>
                    </Card>
                )}
            </AdminPage>
        </>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl tabular-nums">
                    {value.toLocaleString()}
                </CardTitle>
            </CardHeader>
        </Card>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
