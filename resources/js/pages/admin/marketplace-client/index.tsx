import { Form, Head } from '@inertiajs/react';
import {
    CloudOff,
    History,
    KeyRound,
    RefreshCw,
    ShieldAlert,
    ShieldCheck,
} from 'lucide-react';
import MarketplaceClientController from '@/actions/App/Http/Controllers/Admin/MarketplaceClientController';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { useCan } from '@/hooks/use-can';
import { index } from '@/routes/admin/marketplace-client';
import type { Advisory, MoneyValue } from '@/types';

type CatalogItem = {
    slug: string;
    name: string;
    summary: string | null;
    type: string;
    extension: string;
    price: MoneyValue;
    free: boolean;
    latest_version: string;
    platform_version: string;
    url: string;
};

type StoreLicense = {
    id: number;
    product: string;
    product_slug: string;
    extension: string;
    type: string;
    key_hint: string;
    status: string;
    updates_until: string | null;
    installed_version: string | null;
    available: { version: string | null; changelog: string | null } | null;
    last_checked_at: string | null;
    last_error: string | null;
    needs_verification: boolean;
    advisories: Advisory[];
};

type HistoryEntry = {
    id: number;
    extension: string;
    action: 'install' | 'update' | 'rollback';
    from_version: string | null;
    to_version: string | null;
    success: boolean;
    message: string | null;
    can_rollback: boolean;
    actor: string | null;
    created_at: string;
};

type Props = {
    marketplaceUrl: string;
    trusted: boolean;
    catalog: CatalogItem[];
    catalogError: string | null;
    licenses: StoreLicense[];
    history: HistoryEntry[];
};

const dateTime = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString() : '—';

export default function MarketplaceClient({
    marketplaceUrl,
    trusted,
    catalog,
    catalogError,
    licenses,
    history,
}: Props) {
    const can = useCan();
    const manage = can('licenses.manage');
    const install = can('extensions.install');
    const owned = new Set(licenses.map((l) => l.product_slug));

    return (
        <>
            <Head title="Marketplace" />
            <AdminPage>
                <PageHeader
                    title="Marketplace"
                    description="Official modules and themes. Packages are verified against the publisher's signature before any file changes; installing here only places the files, and you install and enable them under Extensions or Appearance."
                />

                {!trusted && (
                    <Alert variant="destructive">
                        <ShieldAlert />
                        <AlertTitle>No trusted signing key</AlertTitle>
                        <AlertDescription>
                            Set ECOMODULAR_MARKETPLACE_TRUSTED_KEYS to the
                            publisher's public key. Without it nothing can be
                            verified or installed.
                        </AlertDescription>
                    </Alert>
                )}

                {catalogError && (
                    <Alert>
                        <CloudOff />
                        <AlertTitle>The marketplace is unavailable</AlertTitle>
                        <AlertDescription>
                            {catalogError} Installed extensions are not
                            affected; licenses are checked again daily.
                        </AlertDescription>
                    </Alert>
                )}

                <section className="grid gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-lg font-semibold">
                            Licenses on this store
                        </h2>
                        {manage && <ActivateDialog />}
                    </div>

                    {licenses.length === 0 ? (
                        <EmptyState
                            title="No licenses yet"
                            description="Buy or get a product on the marketplace, then enter its license key here."
                        />
                    ) : (
                        <ul className="grid gap-3">
                            {licenses.map((l) => (
                                <li key={l.id}>
                                    <LicenseCard
                                        license={l}
                                        manage={manage}
                                        install={install}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-semibold">Catalog</h2>
                    {catalog.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {catalogError
                                ? 'The catalog appears when the marketplace is reachable again.'
                                : 'No products are offered yet.'}
                        </p>
                    ) : (
                        <ul className="grid gap-3 sm:grid-cols-2">
                            {catalog.map((p) => (
                                <li key={p.slug}>
                                    <Card className="h-full py-4">
                                        <CardContent className="grid gap-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {p.name}
                                                </span>
                                                <Badge variant="outline">
                                                    {p.type}
                                                </Badge>
                                                <span className="ml-auto text-sm">
                                                    {p.free
                                                        ? 'Free'
                                                        : p.price.formatted}
                                                </span>
                                            </div>
                                            <p className="font-mono text-xs text-muted-foreground">
                                                {p.extension} · v
                                                {p.latest_version}
                                            </p>
                                            {p.summary && (
                                                <p className="text-sm text-muted-foreground">
                                                    {p.summary}
                                                </p>
                                            )}
                                            <div>
                                                {owned.has(p.slug) ? (
                                                    <Badge>Licensed</Badge>
                                                ) : (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <a
                                                            href={p.url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            View on the
                                                            marketplace
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="text-xs text-muted-foreground">
                        Connected to {marketplaceUrl}
                    </p>
                </section>

                <section className="grid gap-3">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <History className="size-5" aria-hidden /> Install
                        history
                    </h2>
                    {history.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nothing installed from the marketplace yet.
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>When</TableHead>
                                    <TableHead>Package</TableHead>
                                    <TableHead>Action</TableHead>
                                    <TableHead>Result</TableHead>
                                    <TableHead>By</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {history.map((h) => (
                                    <TableRow key={h.id}>
                                        <TableCell className="whitespace-nowrap">
                                            {dateTime(h.created_at)}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {h.extension}
                                        </TableCell>
                                        <TableCell>
                                            {h.action}{' '}
                                            {h.from_version &&
                                                `${h.from_version} → `}
                                            {h.to_version}
                                        </TableCell>
                                        <TableCell>
                                            {h.success ? (
                                                <Badge variant="outline">
                                                    OK
                                                </Badge>
                                            ) : (
                                                <span className="text-sm text-destructive">
                                                    {h.message ?? 'Failed'}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>{h.actor ?? '—'}</TableCell>
                                        <TableCell className="text-right">
                                            {install &&
                                                h.can_rollback &&
                                                h.action !== 'rollback' && (
                                                    <RollbackDialog entry={h} />
                                                )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </section>
            </AdminPage>
        </>
    );
}

function LicenseCard({
    license: l,
    manage,
    install,
}: {
    license: StoreLicense;
    manage: boolean;
    install: boolean;
}) {
    const canInstall =
        install &&
        l.status === 'active' &&
        l.available?.version &&
        l.available.version !== l.installed_version;

    return (
        <Card className="py-4">
            <CardContent className="grid gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <KeyRound
                        className="size-5 text-muted-foreground"
                        aria-hidden
                    />
                    <span className="font-medium">{l.product}</span>
                    <Badge
                        variant={
                            l.status === 'active' ? 'outline' : 'destructive'
                        }
                    >
                        {l.status}
                    </Badge>
                    <span className="font-mono text-xs text-muted-foreground">
                        {l.extension} · key …{l.key_hint}
                    </span>
                </div>

                <dl className="grid gap-1 text-sm sm:grid-cols-3">
                    <div>
                        <dt className="text-muted-foreground">Installed</dt>
                        <dd>
                            {l.installed_version
                                ? `v${l.installed_version}`
                                : 'Not installed'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Available</dt>
                        <dd>
                            {l.available?.version
                                ? `v${l.available.version}`
                                : l.status === 'active'
                                  ? 'None (updates period ended or no release)'
                                  : 'Blocked by license status'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Updates until</dt>
                        <dd>{l.updates_until ?? 'No end date'}</dd>
                    </div>
                </dl>

                {l.available?.changelog && canInstall && (
                    <p className="text-sm whitespace-pre-line text-muted-foreground">
                        {l.available.changelog}
                    </p>
                )}

                {l.advisories.map((a) => (
                    <Alert key={a.id} variant="destructive">
                        <ShieldAlert />
                        <AlertTitle>
                            Security advisory ({a.severity}): {a.title}
                        </AlertTitle>
                        <AlertDescription>
                            <p className="whitespace-pre-line">{a.body}</p>
                            <p>
                                Your version v{l.installed_version} is affected
                                ({a.affected_versions}).
                                {a.fixed_version
                                    ? ` Update to v${a.fixed_version} or later.`
                                    : ' No fix is available yet: consider disabling the extension under Extensions.'}
                            </p>
                        </AlertDescription>
                    </Alert>
                ))}
                {l.needs_verification && (
                    <p className="flex items-center gap-1 text-sm text-amber-700 dark:text-amber-400">
                        <ShieldAlert className="size-4" aria-hidden /> This
                        license could not be verified for a while. Installed
                        files keep working; check again when the marketplace is
                        reachable.
                    </p>
                )}
                {l.last_error && (
                    <p className="text-sm text-destructive">{l.last_error}</p>
                )}
                <p className="text-xs text-muted-foreground">
                    Last checked {dateTime(l.last_checked_at)}
                </p>

                <div className="flex flex-wrap gap-2">
                    {canInstall && (
                        <Form
                            {...MarketplaceClientController.install.form(l.id)}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button size="sm" disabled={processing}>
                                    <ShieldCheck />
                                    {l.installed_version
                                        ? `Update to v${l.available?.version}`
                                        : `Install v${l.available?.version}`}
                                </Button>
                            )}
                        </Form>
                    )}
                    {manage && (
                        <Form
                            {...MarketplaceClientController.check.form(l.id)}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    <RefreshCw /> Check now
                                </Button>
                            )}
                        </Form>
                    )}
                    {manage && <DeactivateDialog license={l} />}
                </div>
            </CardContent>
        </Card>
    );
}

function ActivateDialog() {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>
                    <KeyRound /> Add license
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add a license key</DialogTitle>
                <DialogDescription>
                    The key is checked with the marketplace and this store is
                    counted as one activation.
                </DialogDescription>
                <Form
                    {...MarketplaceClientController.activate.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="key">License key</Label>
                                <Input
                                    id="key"
                                    name="key"
                                    autoComplete="off"
                                    spellCheck={false}
                                    placeholder="EMK-XXXXX-XXXXX-XXXXX-XXXXX"
                                    className="font-mono"
                                    required
                                />
                                <InputError message={errors.key} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Activate
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeactivateDialog({ license }: { license: StoreLicense }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="ghost">
                    Remove from this store
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Remove {license.product}?</DialogTitle>
                <DialogDescription>
                    The activation is released so the key can be used on another
                    store. Installed files are not changed or disabled.
                </DialogDescription>
                <Form
                    {...MarketplaceClientController.deactivate.form(license.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.marketplace} />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Remove
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RollbackDialog({ entry }: { entry: HistoryEntry }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Restore previous files
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Restore {entry.extension}?</DialogTitle>
                <DialogDescription>
                    The files from before this {entry.action} are put back.
                    Database migrations that already ran are not undone; restore
                    a database backup for that.
                </DialogDescription>
                <Form
                    {...MarketplaceClientController.rollback.form(entry.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.marketplace} />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Restore
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

MarketplaceClient.layout = {
    breadcrumbs: [{ title: 'Marketplace', href: index() }],
};
