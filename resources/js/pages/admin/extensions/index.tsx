import { Head, Link } from '@inertiajs/react';
import { Puzzle, ShieldAlert } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { ExtensionStateBadge } from '@/components/admin/extension-state-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { index, show } from '@/routes/admin/extensions';
import type { ExtensionSummary } from '@/types';

export default function ExtensionsIndex({
    extensions,
    safeMode,
}: {
    extensions: ExtensionSummary[];
    safeMode: boolean;
}) {
    return (
        <>
            <Head title="Extensions" />
            <AdminPage>
                <PageHeader
                    title="Extensions"
                    description="Modules found in the modules directory. Nothing is installed or enabled until you do it here or with the ecomodular:modules command."
                />

                {safeMode && (
                    <Alert variant="destructive">
                        <ShieldAlert />
                        <AlertTitle>Safe mode is on</AlertTitle>
                        <AlertDescription>
                            No module is loaded. Turn off ECOMODULAR_SAFE_MODE
                            once the problem module is disabled.
                        </AlertDescription>
                    </Alert>
                )}

                {extensions.length === 0 ? (
                    <EmptyState
                        title="No modules found"
                        description="Add a package to modules/<vendor>/<name>/ with a module.json manifest, then reload this page."
                    />
                ) : (
                    <ul className="grid gap-3">
                        {extensions.map((extension) => (
                            <li key={extension.id}>
                                <Card className="py-4">
                                    <CardContent className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                        <Puzzle
                                            className="hidden size-8 shrink-0 text-muted-foreground sm:block"
                                            aria-hidden
                                        />
                                        <div className="grid min-w-0 flex-1 gap-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Link
                                                    href={show(extension.id)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {extension.display_name}
                                                </Link>
                                                <ExtensionStateBadge
                                                    state={extension.state}
                                                    label={
                                                        extension.state_label
                                                    }
                                                />
                                                {extension.update_available && (
                                                    <Badge variant="outline">
                                                        Update to v
                                                        {
                                                            extension.available_version
                                                        }
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="truncate font-mono text-xs text-muted-foreground">
                                                {extension.name} · v
                                                {extension.version ??
                                                    extension.available_version ??
                                                    '?'}
                                                {extension.author &&
                                                    ` · ${extension.author}`}
                                            </p>
                                            {extension.description && (
                                                <p className="text-sm text-muted-foreground">
                                                    {extension.description}
                                                </p>
                                            )}
                                            {(extension.failure ||
                                                extension.errors.length >
                                                    0) && (
                                                <p className="text-sm text-destructive">
                                                    {extension.failure ??
                                                        extension.errors[0]}
                                                </p>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </AdminPage>
        </>
    );
}

ExtensionsIndex.layout = {
    breadcrumbs: [{ title: 'Extensions', href: index() }],
};
