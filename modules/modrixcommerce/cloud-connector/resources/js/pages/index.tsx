import { Form, Head, Link } from '@inertiajs/react';
import {
    Cloud,
    CloudOff,
    Download,
    ExternalLink,
    History,
    KeyRound,
    Palette,
    Puzzle,
    RefreshCw,
    Search,
    ShieldAlert,
    ShieldCheck,
    Unplug,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { NativeSelect } from '@/components/admin/form-field';
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
import type { Advisory } from '@/types';

type CatalogItem = {
    identifier: string;
    type: 'theme' | 'module';
    name: string;
    summary: string;
    price: string | null;
    is_free: boolean;
    featured: boolean;
    thumbnail_url: string | null;
    demo_url: string | null;
    url: string | null;
    latest_version: string | null;
    installed_version: string | null;
    on_disk: boolean;
    state: string | null;
    state_label: string | null;
    manage_url: string | null;
    update_available: boolean;
    licensed_here: boolean;
    license_update_url: string | null;
    owned: boolean;
    in_use_elsewhere: boolean;
};

type StoreLicense = {
    id: number;
    urls: { check: string; deactivate: string; install: string };
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
    rollback_url: string;
    actor: string | null;
    created_at: string;
};

type Urls = {
    index: string;
    connect: string;
    disconnect: string;
    sync: string;
    activate: string;
    install: string;
    checkUpdates: string;
    appearance: string;
    extensions: string;
};

type Props = {
    cloudUrl: string;
    urls: Urls;
    trusted: boolean;
    connection: {
        name?: string;
        email?: string;
        store_name?: string;
        connected_at?: string;
    } | null;
    catalog: CatalogItem[];
    catalogError: string | null;
    licenses: StoreLicense[];
    history: HistoryEntry[];
};

const dateTime = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString() : '—';

export default function ModrixCloud({
    cloudUrl,
    urls,
    trusted,
    connection,
    catalog,
    catalogError,
    licenses,
    history,
}: Props) {
    const can = useCan();
    const manage = can('licenses.manage');
    const install = can('extensions.install');

    return (
        <>
            <Head title="Modrix Cloud" />
            <AdminPage>
                <PageHeader
                    title="Modrix Cloud"
                    description="Official themes and extensions. Every package is verified against the publisher's signature before any file changes; installing here places the files, then you enable them under Extensions or Appearance."
                    actions={
                        install && (
                            <Form
                                action={urls.checkUpdates}
                                method="post"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        <RefreshCw
                                            className={
                                                processing
                                                    ? 'animate-spin'
                                                    : undefined
                                            }
                                        />
                                        {processing
                                            ? 'Checking…'
                                            : 'Check for updates'}
                                    </Button>
                                )}
                            </Form>
                        )
                    }
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
                        <AlertTitle>Modrix Cloud is unavailable</AlertTitle>
                        <AlertDescription>
                            {catalogError} Installed extensions are not
                            affected; licenses are checked again daily.
                        </AlertDescription>
                    </Alert>
                )}

                <ConnectionCard
                    connection={connection}
                    urls={urls}
                    cloudUrl={cloudUrl}
                    canConnect={install}
                    canSync={manage}
                />

                <CatalogSection
                    catalog={catalog}
                    catalogError={catalogError}
                    connected={connection !== null}
                    urls={urls}
                    canInstall={install}
                />

                <section className="grid gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-lg font-semibold">
                            Licenses on this store
                        </h2>
                        {manage && <ActivateDialog action={urls.activate} />}
                    </div>

                    {licenses.length === 0 ? (
                        <EmptyState
                            title="No licenses yet"
                            description={
                                connection
                                    ? 'Install a free or owned package above, or add a license key.'
                                    : 'Connect to Modrix Cloud to install your packages, or add a license key.'
                            }
                        />
                    ) : (
                        <ul className="grid gap-3">
                            {licenses.map((l) => (
                                <li
                                    key={l.id}
                                    id={`license-${l.extension.replaceAll('/', '-')}`}
                                >
                                    <LicenseCard
                                        license={l}
                                        urls={urls}
                                        manage={manage}
                                        install={install}
                                        openPackage={can(
                                            l.type === 'theme'
                                                ? 'themes.manage'
                                                : 'extensions.view',
                                        )}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="grid gap-3">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <History className="size-5" aria-hidden /> Install
                        history
                    </h2>
                    {history.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Nothing installed from Modrix Cloud yet.
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

function ConnectionCard({
    connection,
    urls,
    cloudUrl,
    canConnect,
    canSync,
}: {
    connection: Props['connection'];
    urls: Urls;
    cloudUrl: string;
    canConnect: boolean;
    canSync: boolean;
}) {
    return (
        <Card className="py-4">
            <CardContent className="flex flex-wrap items-center gap-4">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Cloud className="size-5" aria-hidden />
                </span>
                <div className="grid min-w-0 flex-1 gap-0.5">
                    {connection ? (
                        <>
                            <p className="font-medium">
                                Connected
                                {connection.email
                                    ? ` as ${connection.email}`
                                    : ''}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Your free and purchased packages install in one
                                click, and their licenses are added to this
                                store automatically.
                            </p>
                        </>
                    ) : (
                        <>
                            <p className="font-medium">
                                Connect this store to Modrix Cloud
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Sign in to your Modrix Cloud account to install
                                your free and purchased themes and extensions in
                                one click, without copying license keys.
                            </p>
                        </>
                    )}
                    <p className="text-xs text-muted-foreground">{cloudUrl}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {connection ? (
                        <>
                            {canSync && (
                                <Form
                                    action={urls.sync}
                                    method="post"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            <RefreshCw /> Sync licenses
                                        </Button>
                                    )}
                                </Form>
                            )}
                            {canConnect && (
                                <DisconnectDialog action={urls.disconnect} />
                            )}
                        </>
                    ) : (
                        canConnect && (
                            <Button asChild>
                                <a href={urls.connect}>
                                    <Cloud /> Connect
                                </a>
                            </Button>
                        )
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function CatalogSection({
    catalog,
    catalogError,
    connected,
    urls,
    canInstall,
}: {
    catalog: CatalogItem[];
    catalogError: string | null;
    connected: boolean;
    urls: Urls;
    canInstall: boolean;
}) {
    const [query, setQuery] = useState('');
    const [type, setType] = useState('all');
    const [show, setShow] = useState('all');
    const items = useMemo(
        () =>
            catalog.filter(
                (p) =>
                    (type === 'all' || p.type === type) &&
                    (show === 'all' ||
                        (show === 'free' && p.is_free) ||
                        (show === 'owned' && (p.owned || p.licensed_here)) ||
                        (show === 'installed' &&
                            p.installed_version !== null)) &&
                    (query === '' ||
                        `${p.name} ${p.summary} ${p.identifier}`
                            .toLowerCase()
                            .includes(query.toLowerCase())),
            ),
        [catalog, type, show, query],
    );

    return (
        <section className="grid gap-3">
            <div className="flex flex-wrap items-end justify-between gap-3">
                <h2 className="text-lg font-semibold">Themes and extensions</h2>
                <div className="flex flex-wrap gap-2">
                    <label className="relative">
                        <span className="sr-only">Search</span>
                        <Search
                            className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden
                        />
                        <Input
                            className="w-56 pl-9"
                            placeholder="Search Modrix Cloud"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                    </label>
                    <NativeSelect
                        aria-label="Type"
                        className="w-auto"
                        value={type}
                        onChange={(e) => setType(e.target.value)}
                    >
                        <option value="all">Themes and extensions</option>
                        <option value="theme">Themes</option>
                        <option value="module">Extensions</option>
                    </NativeSelect>
                    <NativeSelect
                        aria-label="Show"
                        className="w-auto"
                        value={show}
                        onChange={(e) => setShow(e.target.value)}
                    >
                        <option value="all">All</option>
                        <option value="free">Free</option>
                        {connected && <option value="owned">Owned</option>}
                        <option value="installed">Installed</option>
                    </NativeSelect>
                </div>
            </div>

            {catalog.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {catalogError
                        ? 'The catalog appears when Modrix Cloud is reachable again.'
                        : 'Nothing is published yet.'}
                </p>
            ) : items.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Nothing matches these filters.
                </p>
            ) : (
                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {items.map((p) => (
                        <li key={p.identifier}>
                            <CatalogCard
                                item={p}
                                connected={connected}
                                urls={urls}
                                canInstall={canInstall}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function CatalogCard({
    item: p,
    connected,
    urls,
    canInstall,
}: {
    item: CatalogItem;
    connected: boolean;
    urls: Urls;
    canInstall: boolean;
}) {
    const available = p.is_free || p.owned;
    // Falls back to the placeholder if the image cannot load (offline, or a
    // development cloud whose host the Content-Security-Policy does not allow).
    const [thumbnailFailed, setThumbnailFailed] = useState(false);

    return (
        <Card className="h-full gap-0 overflow-hidden py-0">
            <div className="aspect-[16/9] bg-muted">
                {p.thumbnail_url && !thumbnailFailed ? (
                    <img
                        src={p.thumbnail_url}
                        alt={`${p.name} preview`}
                        className="size-full object-cover"
                        loading="lazy"
                        onError={() => setThumbnailFailed(true)}
                    />
                ) : (
                    <span className="flex size-full items-center justify-center text-muted-foreground">
                        {p.type === 'theme' ? (
                            <Palette className="size-8" aria-hidden />
                        ) : (
                            <Puzzle className="size-8" aria-hidden />
                        )}
                    </span>
                )}
            </div>
            <CardContent className="grid flex-1 content-start gap-2 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{p.name}</span>
                    <Badge variant="outline">
                        {p.type === 'theme' ? 'Theme' : 'Extension'}
                    </Badge>
                    <span className="ml-auto text-sm font-medium">
                        {p.licensed_here || p.owned ? (
                            <Badge>Owned</Badge>
                        ) : p.is_free ? (
                            'Free'
                        ) : (
                            (p.price ?? '')
                        )}
                    </span>
                </div>
                <p className="font-mono text-xs text-muted-foreground">
                    {p.identifier}
                    {p.latest_version && ` · v${p.latest_version}`}
                </p>
                {p.summary && (
                    <p className="line-clamp-3 text-sm text-muted-foreground">
                        {p.summary}
                    </p>
                )}
                <div className="mt-auto flex flex-wrap items-center gap-2 pt-2">
                    {p.installed_version ? (
                        <Badge variant="secondary">
                            Installed v{p.installed_version}
                            {p.state &&
                                p.state !== 'enabled' &&
                                ` · ${p.state_label}`}
                            {p.update_available && ' · update available'}
                        </Badge>
                    ) : (
                        p.on_disk &&
                        p.state_label && (
                            <Badge variant="outline">{p.state_label}</Badge>
                        )
                    )}
                    {p.update_available &&
                    canInstall &&
                    p.license_update_url ? (
                        // Licensed here: update with the store's license.
                        <UpdateButton item={p} action={p.license_update_url} />
                    ) : p.update_available &&
                      canInstall &&
                      connected &&
                      available ? (
                        // Bundled or uploaded, free or owned: the account's license brings the update.
                        <UpdateButton
                            item={p}
                            action={urls.install}
                            identifier
                        />
                    ) : p.licensed_here ? (
                        <Button size="sm" variant="outline" asChild>
                            <a
                                href={`#license-${p.identifier.replaceAll('/', '-')}`}
                            >
                                Manage
                            </a>
                        </Button>
                    ) : p.on_disk ? (
                        // Files already in this store (bundled or uploaded): install, enable or
                        // manage it in Extensions/Appearance, nothing to download.
                        <Button
                            size="sm"
                            variant={
                                p.type === 'theme' || p.state === 'enabled'
                                    ? 'outline'
                                    : 'default'
                            }
                            asChild
                        >
                            <Link
                                href={
                                    p.manage_url ??
                                    `${p.type === 'theme' ? urls.appearance : urls.extensions}#${p.type === 'theme' ? 'theme' : 'extension'}-${p.identifier.replaceAll('/', '-')}`
                                }
                            >
                                {p.type === 'theme' || p.state === 'enabled'
                                    ? 'Manage'
                                    : p.installed_version
                                      ? 'Enable'
                                      : 'Install'}
                            </Link>
                        </Button>
                    ) : connected && available && canInstall ? (
                        <InstallButton item={p} action={urls.install} />
                    ) : connected && !available && p.url ? (
                        <Button size="sm" asChild>
                            <a
                                href={p.url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Buy on Modrix Cloud <ExternalLink />
                            </a>
                        </Button>
                    ) : null}
                    {!connected && p.url && (
                        <Button size="sm" variant="outline" asChild>
                            <a
                                href={p.url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                View on Modrix Cloud <ExternalLink />
                            </a>
                        </Button>
                    )}
                    {p.demo_url && (
                        <Button size="sm" variant="ghost" asChild>
                            <a
                                href={p.demo_url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Demo
                            </a>
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function UpdateButton({
    item,
    action,
    identifier = false,
}: {
    item: CatalogItem;
    action: string;
    identifier?: boolean;
}) {
    return (
        <Form action={action} method="post" options={{ preserveScroll: true }}>
            {({ processing, errors }) => (
                <>
                    {identifier && (
                        <input
                            type="hidden"
                            name="identifier"
                            value={item.identifier}
                        />
                    )}
                    <Button size="sm" disabled={processing}>
                        <RefreshCw
                            className={processing ? 'animate-spin' : undefined}
                        />
                        {processing
                            ? 'Updating…'
                            : `Update to v${item.latest_version}`}
                    </Button>
                    <InputError message={errors.identifier ?? errors.cloud} />
                </>
            )}
        </Form>
    );
}

function InstallButton({
    item,
    action,
}: {
    item: CatalogItem;
    action: string;
}) {
    return (
        <Form action={action} method="post" options={{ preserveScroll: true }}>
            {({ processing, errors }) => (
                <>
                    <input
                        type="hidden"
                        name="identifier"
                        value={item.identifier}
                    />
                    {item.in_use_elsewhere && (
                        <input type="hidden" name="move_here" value="1" />
                    )}
                    <Button size="sm" disabled={processing}>
                        <Download />
                        {processing
                            ? 'Installing…'
                            : item.in_use_elsewhere
                              ? 'Move here and install'
                              : 'Install'}
                    </Button>
                    {item.in_use_elsewhere && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            Active on another store now.
                        </p>
                    )}
                    <InputError message={errors.identifier} />
                </>
            )}
        </Form>
    );
}

function LicenseCard({
    license: l,
    urls,
    manage,
    install,
    openPackage,
}: {
    license: StoreLicense;
    urls: Urls;
    manage: boolean;
    install: boolean;
    openPackage: boolean;
}) {
    const isTheme = l.type === 'theme';
    const destination = isTheme ? 'Appearance' : 'Extensions';
    const packageUrl = `${isTheme ? urls.appearance : urls.extensions}#${isTheme ? 'theme' : 'extension'}-${l.extension.replaceAll('/', '-')}`;
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
                        files keep working; check again when Modrix Cloud is
                        reachable.
                    </p>
                )}
                {l.last_error && (
                    <p className="text-sm text-destructive">{l.last_error}</p>
                )}
                <p className="text-xs text-muted-foreground">
                    Last checked {dateTime(l.last_checked_at)}
                </p>

                {l.status === 'active' && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/40 p-4">
                        <div className="flex items-start gap-3">
                            {isTheme ? (
                                <Palette
                                    className="mt-0.5 size-5 shrink-0 text-primary"
                                    aria-hidden
                                />
                            ) : (
                                <Puzzle
                                    className="mt-0.5 size-5 shrink-0 text-primary"
                                    aria-hidden
                                />
                            )}
                            <div className="grid gap-1">
                                <p className="text-sm font-medium">
                                    {isTheme
                                        ? 'Activate your theme in Appearance'
                                        : 'Enable your extension in Extensions'}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {l.installed_version
                                        ? isTheme
                                            ? 'Your licensed theme is listed in Appearance. Activate it there to use it on your storefront.'
                                            : 'Your licensed extension is listed in Extensions. Install it if needed, then enable it to start using its features.'
                                        : `Install the package first. It will then appear in ${destination}, where you can ${isTheme ? 'activate the theme' : 'install and enable the extension'}.`}
                                </p>
                            </div>
                        </div>
                        {openPackage && (
                            <Button size="sm" variant="outline" asChild>
                                <Link href={packageUrl}>
                                    {isTheme
                                        ? 'Activate in Appearance'
                                        : 'Manage in Extensions'}
                                </Link>
                            </Button>
                        )}
                    </div>
                )}

                <div className="flex flex-wrap gap-2">
                    {canInstall && (
                        <Form
                            action={l.urls.install}
                            method="post"
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
                            action={l.urls.check}
                            method="post"
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

function ActivateDialog({ action }: { action: string }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <KeyRound /> Add license key
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add a license key</DialogTitle>
                <DialogDescription>
                    For keys bought without an account. The key is checked with
                    Modrix Cloud and this store is counted as one activation.
                </DialogDescription>
                <Form
                    action={action}
                    method="post"
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

function DisconnectDialog({ action }: { action: string }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost">
                    <Unplug /> Disconnect
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Disconnect from Modrix Cloud?</DialogTitle>
                <DialogDescription>
                    The store stops seeing your account's packages. Licenses
                    already on this store, and the themes and extensions they
                    cover, keep working.
                </DialogDescription>
                <Form action={action} method="post">
                    {({ processing }) => (
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
                                Disconnect
                            </Button>
                        </DialogFooter>
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
                    action={license.urls.deactivate}
                    method="delete"
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.cloud} />
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
                    action={entry.rollback_url}
                    method="post"
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <>
                            <InputError message={errors.cloud} />
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
