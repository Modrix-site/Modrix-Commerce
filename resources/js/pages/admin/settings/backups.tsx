import { Form, Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import BackupSettingsController from '@/actions/App/Http/Controllers/Admin/BackupSettingsController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/admin/settings/backups';

type Backup = {
    name: string;
    size: number;
    created_at: string;
    encrypted: boolean;
    readable: boolean;
    includes_files: boolean | null;
    platform_version: string | null;
};

type Operation = {
    id: string;
    type: 'backup' | 'restore';
    state: 'queued' | 'running' | 'done' | 'failed';
    backup: string | null;
    with_files: boolean;
    queued_at: string;
    started_at: string | null;
    finished_at: string | null;
    message: string | null;
    pre_restore?: string | null;
    secret?: string | null;
    recovery?: string[];
};

type Settings = { schedule: string; with_files: boolean; keep: number };

type Props = {
    backups: Backup[];
    settings: Settings;
    env_defaults: Settings;
    next_run: string | null;
    scheduler_alive: boolean;
    encryption_configured: boolean;
    operation: Operation | null;
};

const busy = (op: Operation | null) =>
    op !== null && (op.state === 'queued' || op.state === 'running');

const formatDate = (iso: string) =>
    new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

const formatSize = (bytes: number) =>
    bytes >= 1024 ** 3
        ? `${(bytes / 1024 ** 3).toFixed(2)} GB`
        : `${(bytes / 1024 ** 2).toFixed(2)} MB`;

/** Polls the status while an operation runs; keeps working through maintenance mode. */
function useOperation(initial: Operation | null, initialBackups: Backup[]) {
    const [operation, setOperation] = useState(initial);
    const [backups, setBackups] = useState(initialBackups);
    const [signedOut, setSignedOut] = useState(false);
    const bypassed = useRef<string | null>(null);

    useEffect(() => setOperation(initial), [initial]);
    useEffect(() => setBackups(initialBackups), [initialBackups]);

    useEffect(() => {
        if (!busy(operation) || signedOut) {
            return;
        }

        const timer = window.setTimeout(async () => {
            try {
                // During a restore the store is in maintenance: the bypass
                // link sets the cookie that lets these requests through.
                if (
                    operation?.secret &&
                    bypassed.current !== operation.secret
                ) {
                    await fetch(`/${operation.secret}`, {
                        credentials: 'same-origin',
                    });
                    bypassed.current = operation.secret;
                }

                const response = await fetch(
                    BackupSettingsController.status.url(),
                    {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                    },
                );

                if (response.status === 401 || response.redirected) {
                    // The restored database has its own sessions.
                    setSignedOut(true);

                    return;
                }

                if (!response.ok) {
                    setOperation((op) => (op ? { ...op } : op));

                    return;
                }

                const data = (await response.json()) as {
                    operation: Operation | null;
                    backups: Backup[];
                };
                setOperation(data.operation);
                setBackups(data.backups);

                if (!busy(data.operation)) {
                    router.reload();
                }
            } catch {
                setOperation((op) => (op ? { ...op } : op));
            }
        }, 3000);

        return () => window.clearTimeout(timer);
    }, [operation, signedOut]);

    return { operation, backups, signedOut };
}

export default function Backups(props: Props) {
    const { operation, backups, signedOut } = useOperation(
        props.operation,
        props.backups,
    );
    const [scheduleOn, setScheduleOn] = useState(
        props.settings.schedule !== '',
    );
    const [deleting, setDeleting] = useState<Backup | null>(null);
    const [restoring, setRestoring] = useState<Backup | null>(null);
    const [confirmName, setConfirmName] = useState('');
    const isBusy = busy(operation);
    const stuck =
        operation?.state === 'queued' &&
        Date.now() - new Date(operation.queued_at).getTime() > 2 * 60 * 1000;

    return (
        <>
            <Head title="Backups" />
            <AdminPage>
                <PageHeader
                    title="Backups"
                    description="Archives of the database and, optionally, media, uploads, modules and themes. Copy them off this server too."
                />

                {signedOut && (
                    <Alert>
                        <AlertTitle>
                            Restore finished or your session ended
                        </AlertTitle>
                        <AlertDescription>
                            The restored database has its own accounts and
                            sessions.{' '}
                            <a className="underline" href="/login">
                                Sign in again
                            </a>{' '}
                            with the credentials that existed when the backup
                            was made.
                        </AlertDescription>
                    </Alert>
                )}

                {!props.encryption_configured && (
                    <Alert>
                        <AlertTitle>Backups are not encrypted</AlertTitle>
                        <AlertDescription>
                            Set ECOMODULAR_BACKUP_PASSWORD in .env to AES-256
                            encrypt new archives. Keep the password outside this
                            server.
                        </AlertDescription>
                    </Alert>
                )}

                {!props.scheduler_alive && props.settings.schedule !== '' && (
                    <Alert>
                        <AlertTitle>The scheduler is not running</AlertTitle>
                        <AlertDescription>
                            Scheduled backups need the schedule:run cron entry
                            (see System status).
                        </AlertDescription>
                    </Alert>
                )}

                {operation && (
                    <OperationNotice operation={operation} stuck={stuck} />
                )}

                <div className="grid gap-5 lg:grid-cols-2">
                    <Card>
                        <CardContent>
                            <Form
                                {...BackupSettingsController.schedule.form()}
                                options={{ preserveScroll: true }}
                                transform={(data) => ({
                                    ...data,
                                    schedule: scheduleOn ? data.schedule : null,
                                    with_files: data.with_files === '1',
                                    keep: Number(data.keep),
                                })}
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <h2 className="font-medium">
                                            Schedule
                                        </h2>
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="schedule_on"
                                                checked={scheduleOn}
                                                onCheckedChange={(v) =>
                                                    setScheduleOn(v === true)
                                                }
                                            />
                                            <Label htmlFor="schedule_on">
                                                Back up every day
                                            </Label>
                                        </div>
                                        <FormField
                                            id="schedule"
                                            label="Time (server timezone)"
                                            error={errors.schedule}
                                            hint={`Default from .env: ${props.env_defaults.schedule || 'off'}`}
                                        >
                                            <Input
                                                id="schedule"
                                                name="schedule"
                                                type="time"
                                                disabled={!scheduleOn}
                                                defaultValue={
                                                    props.settings.schedule ||
                                                    props.env_defaults
                                                        .schedule ||
                                                    '02:30'
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id="with_files"
                                            label="Contents"
                                            error={errors.with_files}
                                        >
                                            <NativeSelect
                                                id="with_files"
                                                name="with_files"
                                                defaultValue={
                                                    props.settings.with_files
                                                        ? '1'
                                                        : '0'
                                                }
                                            >
                                                <option value="1">
                                                    Database and files
                                                </option>
                                                <option value="0">
                                                    Database only
                                                </option>
                                            </NativeSelect>
                                        </FormField>
                                        <FormField
                                            id="keep"
                                            label="Backups to keep"
                                            error={errors.keep}
                                            hint={`Older archives are deleted after each backup. Default from .env: ${props.env_defaults.keep}`}
                                        >
                                            <Input
                                                id="keep"
                                                name="keep"
                                                type="number"
                                                min={1}
                                                max={100}
                                                defaultValue={
                                                    props.settings.keep
                                                }
                                            />
                                        </FormField>
                                        <p className="text-sm text-muted-foreground">
                                            {props.next_run
                                                ? `Next backup: ${formatDate(props.next_run)}`
                                                : 'Scheduled backups are off.'}
                                        </p>
                                        <div>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Save schedule
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent>
                            <Form
                                {...BackupSettingsController.store.form()}
                                options={{ preserveScroll: true }}
                                transform={(data) => ({
                                    with_files: data.with_files === '1',
                                })}
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <h2 className="font-medium">
                                            Back up now
                                        </h2>
                                        <FormField
                                            id="now_with_files"
                                            label="Contents"
                                            error={errors.with_files}
                                        >
                                            <NativeSelect
                                                id="now_with_files"
                                                name="with_files"
                                                defaultValue="0"
                                            >
                                                <option value="0">
                                                    Database only
                                                </option>
                                                <option value="1">
                                                    Database and files
                                                </option>
                                            </NativeSelect>
                                        </FormField>
                                        <p className="text-sm text-muted-foreground">
                                            Runs on the queue worker; this page
                                            shows its progress.
                                        </p>
                                        <div>
                                            <Button
                                                type="submit"
                                                disabled={processing || isBusy}
                                            >
                                                {isBusy
                                                    ? 'Operation in progress…'
                                                    : 'Back up now'}
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardContent>
                        {backups.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No backups yet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Backup</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead>Size</TableHead>
                                        <TableHead>Encrypted</TableHead>
                                        <TableHead>Contents</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {backups.map((backup) => (
                                        <TableRow key={backup.name}>
                                            <TableCell className="font-mono text-xs break-all">
                                                {backup.name}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(backup.created_at)}
                                            </TableCell>
                                            <TableCell>
                                                {formatSize(backup.size)}
                                            </TableCell>
                                            <TableCell>
                                                {backup.encrypted
                                                    ? backup.readable
                                                        ? 'Yes'
                                                        : 'Yes — password mismatch'
                                                    : backup.readable
                                                      ? 'No'
                                                      : 'Unreadable'}
                                            </TableCell>
                                            <TableCell>
                                                {backup.includes_files === null
                                                    ? '—'
                                                    : backup.includes_files
                                                      ? 'Database + files'
                                                      : 'Database'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="secondary"
                                                    >
                                                        <a
                                                            href={BackupSettingsController.download.url(
                                                                backup.name,
                                                            )}
                                                        >
                                                            Download
                                                        </a>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        disabled={
                                                            isBusy ||
                                                            !backup.readable
                                                        }
                                                        onClick={() => {
                                                            setConfirmName('');
                                                            setRestoring(
                                                                backup,
                                                            );
                                                        }}
                                                    >
                                                        Restore
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        disabled={
                                                            isBusy &&
                                                            operation?.backup ===
                                                                backup.name
                                                        }
                                                        onClick={() =>
                                                            setDeleting(backup)
                                                        }
                                                    >
                                                        Delete
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </AdminPage>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete backup?</DialogTitle>
                    <DialogDescription>
                        {deleting?.name} is deleted from this server
                        permanently.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                deleting &&
                                router.delete(
                                    BackupSettingsController.destroy.url(
                                        deleting.name,
                                    ),
                                    {
                                        preserveScroll: true,
                                        onFinish: () => setDeleting(null),
                                    },
                                )
                            }
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={restoring !== null}
                onOpenChange={(open) => !open && setRestoring(null)}
            >
                <DialogContent>
                    <DialogTitle>Restore this backup?</DialogTitle>
                    <DialogDescription>
                        The database
                        {restoring?.includes_files
                            ? ', media, uploads, modules and themes are'
                            : ' is'}{' '}
                        replaced with the backup from{' '}
                        {restoring && formatDate(restoring.created_at)}. A
                        database-only backup of the current state is taken
                        first. The store is in maintenance mode while it runs,
                        and everyone is signed out: afterwards, sign in with the
                        accounts as they were in that backup.
                    </DialogDescription>
                    {restoring && (
                        <Form
                            key={restoring.name}
                            {...BackupSettingsController.restore.form(
                                restoring.name,
                            )}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setRestoring(null)}
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FormField
                                        id="confirm_name"
                                        label={`Type ${restoring.name} to confirm`}
                                        error={errors.confirm_name}
                                    >
                                        <Input
                                            id="confirm_name"
                                            name="confirm_name"
                                            autoComplete="off"
                                            value={confirmName}
                                            onChange={(e) =>
                                                setConfirmName(e.target.value)
                                            }
                                        />
                                    </FormField>
                                    <InputError message={errors.backup} />
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={
                                                processing ||
                                                confirmName !== restoring.name
                                            }
                                        >
                                            Restore
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function OperationNotice({
    operation,
    stuck,
}: {
    operation: Operation;
    stuck: boolean;
}) {
    const what =
        operation.type === 'restore'
            ? `Restore of ${operation.backup}`
            : 'Backup';

    if (operation.state === 'queued' || operation.state === 'running') {
        return (
            <Alert>
                <AlertTitle>
                    {what} {operation.state === 'queued' ? 'queued' : 'running'}
                    …
                </AlertTitle>
                <AlertDescription>
                    {operation.type === 'restore'
                        ? 'The store goes into maintenance mode while the restore runs. Keep this page open to follow it.'
                        : 'You can leave this page; the backup continues.'}
                    {stuck && (
                        <>
                            {' '}
                            It has not started after two minutes: no queue
                            worker seems to be running. Start one with{' '}
                            <code>php artisan queue:work</code>.
                        </>
                    )}
                </AlertDescription>
            </Alert>
        );
    }

    if (operation.state === 'failed') {
        return (
            <Alert variant="destructive">
                <AlertTitle>{what} failed</AlertTitle>
                <AlertDescription>
                    <p>{operation.message}</p>
                    {operation.recovery && operation.recovery.length > 0 && (
                        <>
                            <p>
                                The store stays in maintenance mode. To recover,
                                run on the server:
                            </p>
                            <pre className="mt-1 text-xs whitespace-pre-wrap">
                                {operation.recovery.join('\n')}
                            </pre>
                        </>
                    )}
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Alert>
            <AlertTitle>{what} finished</AlertTitle>
            <AlertDescription>{operation.message}</AlertDescription>
        </Alert>
    );
}

Backups.layout = {
    breadcrumbs: [{ title: 'Backups', href: index() }],
};
