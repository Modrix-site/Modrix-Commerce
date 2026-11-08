import { Head, Link, router, usePage } from '@inertiajs/react';
import { CircleCheck, CircleX, TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
import CoreUpdateController from '@/actions/App/Http/Controllers/Admin/CoreUpdateController';
import { ChecksList } from '@/components/admin/checks-list';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { status } from '@/routes/admin/system';
import type { RequirementCheck } from '@/types';

type CoreRun = {
    id: number;
    from_version: string;
    to_version: string;
    status: string;
    message: string | null;
    needs_attention: boolean;
    created_at: string | null;
};

type CoreUpdate = {
    current: string;
    available: {
        version: string;
        notes: string;
        min_php: string;
        published_at: string | null;
    } | null;
    checked_at: string | null;
    last_error: string | null;
    connected: boolean;
    can_install: boolean;
    connect_url: string | null;
    active_run: {
        id: number;
        status: string;
        hint: string | null;
        bypass_url: string | null;
    } | null;
    runs: CoreRun[];
};

type Props = {
    coreUpdate: CoreUpdate;
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

const RUN_LABELS: Record<string, string> = {
    queued: 'Queued',
    downloading: 'Downloading',
    verifying: 'Verifying',
    backing_up: 'Backing up',
    installing: 'Installing',
    migrating: 'Migrating',
    completed: 'Completed',
    failed: 'Failed',
    rolled_back: 'Rolled back',
};

const TERMINAL = ['completed', 'failed', 'rolled_back'];

function CorePlatformCard({ coreUpdate }: { coreUpdate: CoreUpdate }) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const { available, active_run: activeRun } = coreUpdate;
    const [liveStatus, setLiveStatus] = useState<string | null>(null);
    const [liveHint, setLiveHint] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const activeRunId = activeRun?.id ?? null;
    const bypassUrl = activeRun?.bypass_url ?? null;

    // While an update runs, follow it and reload the page data once it ends.
    useEffect(() => {
        if (activeRunId === null) {
            return;
        }

        let stopped = false;
        let bypassed = false;
        const timer = window.setInterval(async () => {
            try {
                const response = await fetch(
                    CoreUpdateController.show.url({ run: activeRunId }),
                    { headers: { Accept: 'application/json' } },
                );

                // Maintenance mode: the bypass link sets a cookie that lets this page keep polling.
                if (response.status === 503 && bypassUrl && !bypassed) {
                    bypassed = true;
                    await fetch(bypassUrl, { credentials: 'same-origin' });

                    return;
                }

                if (!response.ok || stopped) {
                    return;
                }

                const data = (await response.json()) as {
                    status: string;
                    hint: string | null;
                };
                setLiveStatus(data.status);
                setLiveHint(data.hint);

                if (TERMINAL.includes(data.status)) {
                    stopped = true;
                    window.clearInterval(timer);
                    router.reload();
                }
            } catch {
                // The site is briefly in maintenance mode during an install; keep polling.
            }
        }, 3000);

        return () => {
            stopped = true;
            window.clearInterval(timer);
        };
    }, [activeRunId, bypassUrl]);

    const post = (url: string) =>
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );

    const install = () => {
        if (
            available &&
            window.confirm(
                `Install Modrix Commerce ${available.version}? The store goes into maintenance mode for a short while. A database and code backup is taken first.`,
            )
        ) {
            post(CoreUpdateController.start.url());
        }
    };

    const shownStatus = activeRun ? (liveStatus ?? activeRun.status) : null;
    const shownHint = activeRun
        ? liveStatus === null
            ? activeRun.hint
            : liveHint
        : null;
    const lastRun = coreUpdate.runs[0] ?? null;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Core platform</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
                <dl className="divide-y">
                    <div className="flex justify-between gap-4 py-2">
                        <dt className="text-muted-foreground">
                            Installed version
                        </dt>
                        <dd className="font-medium">{coreUpdate.current}</dd>
                    </div>
                    <div className="flex justify-between gap-4 py-2">
                        <dt className="text-muted-foreground">Last checked</dt>
                        <dd className="text-right">
                            {coreUpdate.checked_at
                                ? new Date(
                                      coreUpdate.checked_at,
                                  ).toLocaleString()
                                : 'Never'}
                        </dd>
                    </div>
                </dl>

                {errors.core_update && (
                    <Alert variant="destructive">
                        <TriangleAlert />
                        <AlertDescription>
                            {errors.core_update}
                        </AlertDescription>
                    </Alert>
                )}

                {coreUpdate.last_error && (
                    <p className="text-destructive">
                        Last check failed: {coreUpdate.last_error}
                    </p>
                )}

                {lastRun?.needs_attention && (
                    <Alert variant="destructive">
                        <CircleX />
                        <AlertTitle>
                            The last core update could not be fully undone
                        </AlertTitle>
                        <AlertDescription>
                            <p>{lastRun.message}</p>
                            <p>
                                Restore the code and database backups named in
                                the message by following Manual recovery in the
                                core updates guide
                                (docs/operations/core-updates.md).
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                {shownStatus && (
                    <div className="space-y-1">
                        <p className="font-medium">
                            Update in progress:{' '}
                            {RUN_LABELS[shownStatus] ?? shownStatus}. This page
                            refreshes when the update ends.
                        </p>
                        {shownHint && (
                            <p className="text-destructive">{shownHint}</p>
                        )}
                        {bypassUrl && (
                            <p className="text-xs text-muted-foreground">
                                The store is in maintenance mode while the
                                update installs. If this page stops loading,
                                open the{' '}
                                <a
                                    href={bypassUrl}
                                    className="underline underline-offset-4"
                                >
                                    maintenance bypass link
                                </a>{' '}
                                (it lets you, and only this browser, in).
                            </p>
                        )}
                    </div>
                )}

                {available ? (
                    <div className="space-y-2 rounded-md border p-3">
                        <p className="font-medium">
                            Modrix Commerce {available.version} is available
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Requires PHP {available.min_php} or newer
                            {available.published_at
                                ? `, published ${new Date(available.published_at).toLocaleDateString()}`
                                : ''}
                            .
                        </p>
                        {available.notes && (
                            <p className="whitespace-pre-line text-muted-foreground">
                                {available.notes}
                            </p>
                        )}
                    </div>
                ) : (
                    <p className="text-muted-foreground">
                        Modrix Commerce is up to date.
                    </p>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        onClick={() => post(CoreUpdateController.check.url())}
                        disabled={busy || activeRun !== null}
                    >
                        Check now
                    </Button>
                    {available &&
                        (coreUpdate.connected ? (
                            <Button
                                onClick={install}
                                disabled={busy || !coreUpdate.can_install}
                            >
                                Install update
                            </Button>
                        ) : coreUpdate.connect_url ? (
                            <Button variant="secondary" asChild>
                                <Link href={coreUpdate.connect_url}>
                                    Connect to Modrix Cloud to download
                                </Link>
                            </Button>
                        ) : (
                            <span className="text-muted-foreground">
                                Connect this store to Modrix Cloud to download
                                updates.
                            </span>
                        ))}
                </div>

                {coreUpdate.runs.length > 0 && (
                    <div>
                        <h3 className="mb-1 font-medium">Recent updates</h3>
                        <ul className="divide-y">
                            {coreUpdate.runs.map((run) => (
                                <li
                                    key={run.id}
                                    className="flex flex-col gap-1 py-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
                                >
                                    <span>
                                        {run.from_version} to {run.to_version}
                                        {run.created_at && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                on{' '}
                                                {new Date(
                                                    run.created_at,
                                                ).toLocaleString()}
                                            </span>
                                        )}
                                        {run.message && (
                                            <span className="mt-1 block text-xs break-words text-muted-foreground">
                                                {run.message}
                                            </span>
                                        )}
                                    </span>
                                    <Badge
                                        variant={
                                            run.status === 'completed'
                                                ? 'secondary'
                                                : TERMINAL.includes(run.status)
                                                  ? 'destructive'
                                                  : 'outline'
                                        }
                                    >
                                        {RUN_LABELS[run.status] ?? run.status}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function SystemStatus({
    coreUpdate,
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

                <CorePlatformCard coreUpdate={coreUpdate} />

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
