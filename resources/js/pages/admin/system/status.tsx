import { Head } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { ChecksList } from '@/components/admin/checks-list';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { status } from '@/routes/admin/system';
import type { RequirementCheck } from '@/types';

type Props = {
    environment: { label: string; value: string }[];
    checks: RequirementCheck[];
    warnings: string[];
};

export default function SystemStatus({ environment, checks, warnings }: Props) {
    return (
        <>
            <Head title="System status" />
            <AdminPage>
                <PageHeader
                    title="System status"
                    description="Runtime configuration and health checks. Credentials and paths are never shown."
                />

                {warnings.map((warning) => (
                    <Alert key={warning} variant="destructive">
                        <TriangleAlert />
                        <AlertTitle>Configuration warning</AlertTitle>
                        <AlertDescription>{warning}</AlertDescription>
                    </Alert>
                ))}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Environment</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="divide-y text-sm">
                                {environment.map((row) => (
                                    <div
                                        key={row.label}
                                        className="flex justify-between gap-4 py-2"
                                    >
                                        <dt className="text-muted-foreground">
                                            {row.label}
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {row.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Requirements</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ChecksList checks={checks} />
                        </CardContent>
                    </Card>
                </div>
            </AdminPage>
        </>
    );
}

SystemStatus.layout = {
    breadcrumbs: [{ title: 'System status', href: status() }],
};
