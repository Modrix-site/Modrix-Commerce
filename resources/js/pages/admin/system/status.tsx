import { Head } from '@inertiajs/react';
import { CircleCheck, CircleX, TriangleAlert } from 'lucide-react';
import { ChecksList } from '@/components/admin/checks-list';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { status } from '@/routes/admin/system';
import type { RequirementCheck } from '@/types';

type Props = {
    environment: { label: string; value: string }[];
    checks: RequirementCheck[];
    health: HealthItem[];
    warnings: string[];
};

type HealthItem = {
    key: string;
    label: string;
    status: 'ok' | 'warning' | 'failed';
    detail: string;
};

function HealthIcon({ status }: { status: HealthItem['status'] }) {
    if (status === 'ok') {
        return (
            <CircleCheck
                className="size-4 shrink-0 text-green-600"
                aria-label="OK"
            />
        );
    }

    if (status === 'warning') {
        return (
            <TriangleAlert
                className="size-4 shrink-0 text-amber-600"
                aria-label="Warning"
            />
        );
    }

    return (
        <CircleX
            className="size-4 shrink-0 text-destructive"
            aria-label="Failed"
        />
    );
}

export default function SystemStatus({
    environment,
    checks,
    health,
    warnings,
}: Props) {
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

                <Card>
                    <CardHeader>
                        <CardTitle>Health</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y text-sm">
                            {health.map((item) => (
                                <li
                                    key={item.key}
                                    className="flex flex-col gap-1 py-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
                                >
                                    <span className="flex items-center gap-2">
                                        <HealthIcon status={item.status} />
                                        {item.label}
                                    </span>
                                    <span className="text-muted-foreground sm:text-right">
                                        {item.detail}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Monitors can poll /health (and use
                            ECOMODULAR_HEALTH_TOKEN for details) or run php
                            artisan ecomodular:health.
                        </p>
                    </CardContent>
                </Card>

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
