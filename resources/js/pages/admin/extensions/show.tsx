import { Form, Head } from '@inertiajs/react';
import { AlertTriangle, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import ExtensionController from '@/actions/App/Http/Controllers/Admin/ExtensionController';
import { ExtensionStateBadge } from '@/components/admin/extension-state-badge';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
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
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { index } from '@/routes/admin/extensions';
import type { ExtensionSettingField, ExtensionSummary } from '@/types';

type Extension = ExtensionSummary & {
    license: string | null;
    platform_version: string | null;
    permissions: { name: string; label: string; group: string }[];
    routes: string[];
    has_migrations: boolean;
    dependents: { name: string; state: string }[];
    installed_at: string | null;
    enabled_at: string | null;
};

type LifecycleEvent = {
    id: number;
    action: string;
    from_state: string | null;
    to_state: string | null;
    version: string | null;
    success: boolean;
    message: string | null;
    actor: string | null;
    created_at: string;
};

type Props = {
    extension: Extension;
    settings: {
        fields: ExtensionSettingField[];
        values: Record<string, unknown>;
    } | null;
    events: LifecycleEvent[];
};

export default function ExtensionShow({ extension, settings, events }: Props) {
    const can = useCan();
    const installed = extension.version !== null;
    const state = extension.state;
    const sameVersion = extension.version === extension.available_version;

    const canInstall =
        !installed && (state === 'discovered' || state === 'failed');
    const canEnable =
        installed &&
        sameVersion &&
        ['installed', 'disabled', 'failed'].includes(state);
    const canDisable = ['enabled', 'failed', 'update_pending'].includes(state);
    const canUpdate = installed && extension.update_available;
    const canUninstall =
        installed &&
        ['installed', 'disabled', 'failed', 'update_pending'].includes(state);

    return (
        <>
            <Head title={extension.display_name} />
            <AdminPage>
                <PageHeader
                    title={extension.display_name}
                    description={extension.description ?? extension.name}
                    actions={
                        <ExtensionStateBadge
                            state={extension.state}
                            label={extension.state_label}
                        />
                    }
                />

                {extension.errors.length > 0 && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>The package is invalid</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-4">
                                {extension.errors.map((error, i) => (
                                    <li key={i}>{error}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                {extension.failure && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>This module is not running</AlertTitle>
                        <AlertDescription>{extension.failure}</AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap gap-2">
                    {canInstall && can('extensions.install') && (
                        <Form
                            {...ExtensionController.install.form(extension.id)}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Button type="submit" disabled={processing}>
                                        Install
                                    </Button>
                                    <InputError message={errors.module} />
                                </>
                            )}
                        </Form>
                    )}
                    {canEnable && can('extensions.enable') && (
                        <EnableDialog extension={extension} />
                    )}
                    {canUpdate && can('extensions.update') && (
                        <Form
                            {...ExtensionController.update.form(extension.id)}
                        >
                            {({ processing }) => (
                                <Button type="submit" disabled={processing}>
                                    Update to v{extension.available_version}
                                </Button>
                            )}
                        </Form>
                    )}
                    {canDisable && can('extensions.disable') && (
                        <Form
                            {...ExtensionController.disable.form(extension.id)}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Disable
                                </Button>
                            )}
                        </Form>
                    )}
                    {canUninstall && can('extensions.install') && (
                        <UninstallDialog extension={extension} />
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Package</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2 text-sm">
                            <Row label="Name" value={extension.name} mono />
                            <Row
                                label="Installed version"
                                value={extension.version ?? 'Not installed'}
                            />
                            <Row
                                label="Version on disk"
                                value={extension.available_version ?? '—'}
                            />
                            <Row
                                label="Platform"
                                value={extension.platform_version ?? '—'}
                            />
                            <Row
                                label="Author"
                                value={extension.author ?? '—'}
                            />
                            <Row
                                label="License"
                                value={extension.license ?? '—'}
                            />
                            <Row
                                label="Requires"
                                value={
                                    Object.keys(extension.requires).length > 0
                                        ? Object.entries(extension.requires)
                                              .map(([n, c]) => `${n} ${c}`)
                                              .join(', ')
                                        : 'Nothing'
                                }
                            />
                            <Row
                                label="Required by"
                                value={
                                    extension.dependents.length > 0
                                        ? extension.dependents
                                              .map((d) => d.name)
                                              .join(', ')
                                        : 'Nothing'
                                }
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>What it can do</CardTitle>
                            <CardDescription>
                                Declared by the package. Its PHP code runs with
                                the same privileges as the store.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            {extension.capabilities.length === 0 ? (
                                <p className="text-muted-foreground">
                                    No capabilities declared.
                                </p>
                            ) : (
                                <ul className="grid gap-1">
                                    {extension.capabilities.map((c) => (
                                        <li key={c.key}>
                                            {c.label}{' '}
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {c.key}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {extension.permissions.length > 0 && (
                                <div className="grid gap-1">
                                    <span className="font-medium">
                                        Staff permissions
                                    </span>
                                    <div className="flex flex-wrap gap-1">
                                        {extension.permissions.map((p) => (
                                            <Badge
                                                key={p.name}
                                                variant="outline"
                                                title={p.name}
                                            >
                                                {p.label}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {settings &&
                    settings.fields.length > 0 &&
                    can('extensions.configure') && (
                        <SettingsCard
                            extensionId={extension.id}
                            fields={settings.fields}
                            values={settings.values}
                        />
                    )}

                <Card>
                    <CardHeader>
                        <CardTitle>Lifecycle log</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {events.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing recorded yet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>When</TableHead>
                                        <TableHead>Action</TableHead>
                                        <TableHead>By</TableHead>
                                        <TableHead>Details</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {events.map((e) => (
                                        <TableRow key={e.id}>
                                            <TableCell className="whitespace-nowrap">
                                                {new Date(
                                                    e.created_at,
                                                ).toLocaleString()}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        e.success
                                                            ? 'secondary'
                                                            : 'destructive'
                                                    }
                                                >
                                                    {e.action.replace('_', ' ')}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {e.actor ?? 'System'}
                                            </TableCell>
                                            <TableCell className="max-w-md text-xs whitespace-normal text-muted-foreground">
                                                {e.version && `v${e.version} `}
                                                {e.from_state &&
                                                    e.to_state &&
                                                    e.from_state !==
                                                        e.to_state &&
                                                    `${e.from_state} → ${e.to_state}. `}
                                                {e.message}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}

function Row({
    label,
    value,
    mono = false,
}: {
    label: string;
    value: string;
    mono?: boolean;
}) {
    return (
        <div className="flex justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className={mono ? 'font-mono text-xs' : undefined}>
                {value}
            </span>
        </div>
    );
}

function EnableDialog({ extension }: { extension: Extension }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>Enable</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Enable {extension.display_name}?</DialogTitle>
                <DialogDescription>
                    Enabling runs this module's PHP code with the same access as
                    the store itself: your database, files, customers and
                    orders. Only enable modules from sources you trust.
                </DialogDescription>
                {extension.capabilities.length > 0 && (
                    <ul className="list-disc pl-5 text-sm">
                        {extension.capabilities.map((c) => (
                            <li key={c.key}>{c.label}</li>
                        ))}
                    </ul>
                )}
                <Form
                    {...ExtensionController.enable.form(extension.id)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <label className="flex items-start gap-2 text-sm">
                                <Checkbox
                                    name="trusted"
                                    value="1"
                                    className="mt-0.5"
                                />
                                I trust the author of this module and want to
                                run its code.
                            </label>
                            <InputError
                                message={errors.trusted ?? errors.module}
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Enable module
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function UninstallDialog({ extension }: { extension: Extension }) {
    const [typed, setTyped] = useState('');

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive">Uninstall</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Uninstall {extension.display_name}?</DialogTitle>
                <DialogDescription>
                    This permanently deletes the module's database tables,
                    settings and staff permissions. Disable the module instead
                    if you want to keep its data.
                </DialogDescription>
                <Alert variant="destructive">
                    <ShieldAlert />
                    <AlertTitle>This cannot be undone</AlertTitle>
                    <AlertDescription>
                        Back up your database first if you might need this data
                        again.
                    </AlertDescription>
                </Alert>
                <Form
                    {...ExtensionController.uninstall.form(extension.id)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="confirmation">
                                    Type{' '}
                                    <span className="font-mono">
                                        {extension.name}
                                    </span>{' '}
                                    to confirm
                                </Label>
                                <Input
                                    id="confirmation"
                                    name="confirmation"
                                    autoComplete="off"
                                    value={typed}
                                    onChange={(e) => setTyped(e.target.value)}
                                />
                                <InputError
                                    message={
                                        errors.confirmation ?? errors.module
                                    }
                                />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={
                                        processing || typed !== extension.name
                                    }
                                >
                                    Uninstall and delete data
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function SettingsCard({
    extensionId,
    fields,
    values,
}: {
    extensionId: number;
    fields: ExtensionSettingField[];
    values: Record<string, unknown>;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Settings</CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    {...ExtensionController.configure.form(extensionId)}
                    options={{ preserveScroll: true }}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {fields.map((field) => (
                                <SettingInput
                                    key={field.key}
                                    field={field}
                                    value={values[field.key]}
                                    error={errors[field.key]}
                                />
                            ))}
                            <InputError message={errors.module} />
                            <div>
                                <Button type="submit" disabled={processing}>
                                    Save settings
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function SettingInput({
    field,
    value,
    error,
}: {
    field: ExtensionSettingField;
    value: unknown;
    error?: string;
}) {
    const id = `setting-${field.key}`;
    const name = `settings[${field.key}]`;
    // Setting values are always scalars (the server coerces them by type).
    const text =
        typeof value === 'string' || typeof value === 'number'
            ? String(value)
            : '';

    if (field.type === 'boolean') {
        return (
            <div className="grid gap-1">
                <input type="hidden" name={name} value="0" />
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        id={id}
                        name={name}
                        value="1"
                        defaultChecked={value === true}
                    />
                    {field.label}
                </label>
                {field.help && (
                    <p className="text-xs text-muted-foreground">
                        {field.help}
                    </p>
                )}
                <InputError message={error} />
            </div>
        );
    }

    return (
        <FormField id={id} label={field.label} hint={field.help} error={error}>
            {field.type === 'text' ? (
                <Textarea
                    id={id}
                    name={name}
                    rows={4}
                    defaultValue={text}
                    maxLength={field.max_length}
                    required={field.required}
                />
            ) : field.type === 'select' ? (
                <NativeSelect id={id} name={name} defaultValue={text}>
                    {!field.required && <option value="">—</option>}
                    {field.options?.map((o) => (
                        <option key={String(o.value)} value={String(o.value)}>
                            {o.label}
                        </option>
                    ))}
                </NativeSelect>
            ) : (
                <Input
                    id={id}
                    name={name}
                    type={
                        field.type === 'integer'
                            ? 'number'
                            : field.type === 'email'
                              ? 'email'
                              : 'text'
                    }
                    min={field.min}
                    max={field.max}
                    maxLength={field.max_length}
                    defaultValue={text}
                    required={field.required}
                />
            )}
        </FormField>
    );
}

ExtensionShow.layout = {
    breadcrumbs: [{ title: 'Extensions', href: index() }],
};
