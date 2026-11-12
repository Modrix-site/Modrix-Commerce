import { Head, Link } from '@inertiajs/react';
import {
    ShieldAlert,
    Search,
    ArrowUpRight,
    Puzzle,
    CheckCircle2,
    RefreshCw,
} from 'lucide-react';
import { useState } from 'react';
import { ExtensionIcon } from '@/components/admin/extension-icon';
import {
    ModrixCloudPrompt,
    type ModrixCloudPromptData,
} from '@/components/admin/modrix-cloud-prompt';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { PackageUpload } from '@/components/admin/package-upload';
import { ExtensionStateBadge } from '@/components/admin/extension-state-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import {
    categoriesForExtension,
    extensionCategories,
} from '@/lib/extension-categories';
import { index, show } from '@/routes/admin/extensions';
import type { ExtensionSummary } from '@/types';
export default function ExtensionsIndex({
    extensions,
    safeMode,
    licensedExtensions = [],
    cloudPrompt = null,
}: {
    extensions: ExtensionSummary[];
    safeMode: boolean;
    licensedExtensions?: string[];
    cloudPrompt?: ModrixCloudPromptData | null;
}) {
    const can = useCan();
    const [category, setCategory] = useState('All extensions');
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('all');
    const visible = extensions.filter(
        (e) =>
            (category === 'All extensions' ||
                categoriesForExtension(e).includes(category)) &&
            (status === 'all' ||
                (status === 'updates'
                    ? e.update_available
                    : status === 'installed'
                      ? e.version !== null
                      : e.state === status)) &&
            `${e.display_name} ${e.name} ${e.description ?? ''} ${categoriesForExtension(e).join(' ')}`
                .toLowerCase()
                .includes(query.toLowerCase()),
    );
    const stats = [
        {
            label: 'Available extensions',
            value: extensions.length,
            Icon: Puzzle,
        },
        {
            label: 'Enabled',
            value: extensions.filter((e) => e.state === 'enabled').length,
            Icon: CheckCircle2,
        },
        {
            label: 'Updates available',
            value: extensions.filter((e) => e.update_available).length,
            Icon: RefreshCw,
        },
    ];
    return (
        <>
            <Head title="Extensions" />
            <AdminPage>
                <PageHeader
                    title="Extensions"
                    description="Discover and manage features for your store, organized by what they do."
                    actions={
                        can('extensions.install') && (
                            <PackageUpload type="extension" />
                        )
                    }
                />
                {safeMode && (
                    <Alert variant="destructive" className="mb-5">
                        <ShieldAlert />
                        <AlertTitle>Safe mode is on</AlertTitle>
                        <AlertDescription>
                            Extensions are temporarily paused. Disable the
                            problem extension before turning off safe mode.
                        </AlertDescription>
                    </Alert>
                )}
                <ModrixCloudPrompt prompt={cloudPrompt} className="mb-5" />
                <div className="mb-6 grid gap-3 sm:grid-cols-3">
                    {stats.map(({ label, value, Icon }) => (
                        <div
                            key={label}
                            className="flex items-center gap-4 rounded-xl border bg-card p-5"
                        >
                            <span className="rounded-lg bg-muted p-3">
                                <Icon className="size-5 text-muted-foreground" />
                            </span>
                            <div>
                                <p className="text-2xl font-semibold">
                                    {value}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {label}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
                <div className="grid items-start gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                    <aside
                        className="grid gap-1 rounded-xl border bg-card p-3"
                        aria-label="Extension categories"
                    >
                        <h2 className="px-3 py-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Features
                        </h2>
                        {['All extensions', ...extensionCategories].map(
                            (name) => {
                                const count =
                                    name === 'All extensions'
                                        ? extensions.length
                                        : extensions.filter((e) =>
                                              categoriesForExtension(
                                                  e,
                                              ).includes(name),
                                          ).length;
                                return (
                                    <button
                                        key={name}
                                        type="button"
                                        aria-pressed={category === name}
                                        onClick={() => setCategory(name)}
                                        className={`flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left text-sm ${category === name ? 'bg-primary/10 font-medium text-primary' : 'hover:bg-muted'}`}
                                    >
                                        <span>{name}</span>
                                        <span className="rounded bg-muted px-1.5 text-xs text-muted-foreground">
                                            {count}
                                        </span>
                                    </button>
                                );
                            },
                        )}
                    </aside>
                    <section className="grid min-w-0 gap-4">
                        <div className="flex flex-wrap gap-3">
                            <div className="relative min-w-48 flex-1">
                                <Search className="absolute top-3 left-3 size-4 text-muted-foreground" />
                                <Input
                                    aria-label="Search extensions"
                                    placeholder="Search extensions or features..."
                                    className="pl-9"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                />
                            </div>
                            <select
                                aria-label="Extension status"
                                className="rounded-md border bg-background px-3 text-sm"
                                value={status}
                                onChange={(e) => setStatus(e.target.value)}
                            >
                                <option value="all">All statuses</option>
                                <option value="enabled">Enabled</option>
                                <option value="installed">Installed</option>
                                <option value="disabled">Disabled</option>
                                <option value="discovered">
                                    Not installed
                                </option>
                                <option value="updates">
                                    Updates available
                                </option>
                                <option value="failed">Needs attention</option>
                            </select>
                        </div>
                        <div className="flex items-center justify-between">
                            <h2 className="font-semibold">{category}</h2>
                            <span className="text-sm text-muted-foreground">
                                {visible.length} results
                            </span>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                            {visible.map((extension) => (
                                <article
                                    key={extension.id}
                                    id={`extension-${extension.name.replaceAll('/', '-')}`}
                                    className="flex scroll-mt-6 flex-col gap-4 rounded-xl border bg-card p-5 shadow-sm transition-shadow target:ring-2 target:ring-primary hover:shadow-md"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <ExtensionIcon
                                            logo={extension.logo}
                                            icon={extension.icon}
                                            accent={extension.accent}
                                            className={
                                                extension.logo
                                                    ? 'h-14 w-28 rounded-xl border bg-white'
                                                    : 'size-14 rounded-xl'
                                            }
                                        />
                                        <ExtensionStateBadge
                                            state={extension.state}
                                            label={extension.state_label}
                                        />
                                    </div>
                                    <div>
                                        <Link
                                            href={show(extension.id)}
                                            className="text-base font-semibold hover:text-primary"
                                        >
                                            {extension.display_name}
                                        </Link>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            By{' '}
                                            {extension.author ||
                                                extension.name.split(
                                                    '/',
                                                )[0]}{' '}
                                            · v
                                            {extension.version ??
                                                extension.available_version ??
                                                '—'}
                                        </p>
                                    </div>
                                    <p className="line-clamp-3 text-sm leading-6 text-muted-foreground">
                                        {extension.description ||
                                            'Extend your store with additional features.'}
                                    </p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {licensedExtensions.includes(
                                            extension.name,
                                        ) && (
                                            <Badge variant="outline">
                                                Modrix Cloud licensed
                                            </Badge>
                                        )}
                                        {categoriesForExtension(extension).map(
                                            (c) => (
                                                <Badge
                                                    key={c}
                                                    variant="secondary"
                                                >
                                                    {c}
                                                </Badge>
                                            ),
                                        )}
                                        {extension.update_available && (
                                            <Badge variant="outline">
                                                Update v
                                                {extension.available_version}
                                            </Badge>
                                        )}
                                    </div>
                                    {(extension.failure ||
                                        extension.errors.length > 0) && (
                                        <p className="text-sm text-destructive">
                                            {extension.failure ||
                                                extension.errors[0]}
                                        </p>
                                    )}
                                    <div className="mt-auto flex items-center justify-between border-t pt-4">
                                        <span className="truncate text-xs text-muted-foreground">
                                            {extension.name}
                                        </span>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={show(extension.id)}>
                                                Manage{' '}
                                                <ArrowUpRight className="size-3.5" />
                                            </Link>
                                        </Button>
                                    </div>
                                </article>
                            ))}
                        </div>
                        {visible.length === 0 && (
                            <div className="rounded-xl border border-dashed p-12 text-center">
                                <Puzzle className="mx-auto mb-3 size-8 text-muted-foreground" />
                                <h3 className="font-medium">
                                    No extensions found
                                </h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Try another category, status or search term.
                                </p>
                                <Button
                                    variant="ghost"
                                    className="mt-4"
                                    onClick={() => {
                                        setCategory('All extensions');
                                        setQuery('');
                                        setStatus('all');
                                    }}
                                >
                                    Clear filters
                                </Button>
                            </div>
                        )}
                    </section>
                </div>
            </AdminPage>
        </>
    );
}
ExtensionsIndex.layout = {
    breadcrumbs: [{ title: 'Extensions', href: index() }],
};
