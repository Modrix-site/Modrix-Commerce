import { Form, Head, Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import MarketplaceAdminController from '@/actions/App/Http/Controllers/Admin/MarketplaceAdminController';
import { EmptyState } from '@/components/admin/empty-state';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/admin/marketplace/licenses';
import type { Paginated } from '@/types';

type LicenseRow = {
    id: number;
    product: string;
    owner: string;
    key_hint: string;
    status: string;
    status_reason: string | null;
    updates_until: string | null;
    activations: number;
    activation_limit: number | null;
    events: {
        id: number;
        action: string;
        actor: string | null;
        instance_id: string | null;
        details: Record<string, unknown> | null;
        created_at: string;
    }[];
};

type Action = 'suspend' | 'revoke' | 'reinstate' | 'extend';

type Props = {
    licenses: Paginated<LicenseRow>;
    filters: { status: string; search: string };
    statuses: string[];
};

export default function MarketplaceLicenses({
    licenses,
    filters,
    statuses,
}: Props) {
    const filtered = filters.status !== '' || filters.search !== '';

    return (
        <>
            <Head title="Licenses" />
            <AdminPage>
                <PageHeader
                    title="Licenses"
                    description="Every change is recorded in the license history. Suspending or revoking stops activations and downloads; stores keep what they already installed."
                />

                <Form
                    {...index.form()}
                    className="grid gap-3 sm:grid-cols-[2fr_1fr_auto] sm:items-end"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="search">Owner email</Label>
                        <Input
                            id="search"
                            name="search"
                            type="search"
                            defaultValue={filters.search}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <NativeSelect
                            id="status"
                            name="status"
                            defaultValue={filters.status}
                        >
                            <option value="">Any</option>
                            {statuses.map((s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" variant="secondary">
                            Filter
                        </Button>
                        {filtered && (
                            <Button variant="ghost" asChild>
                                <Link href={index()}>Clear</Link>
                            </Button>
                        )}
                    </div>
                </Form>

                {licenses.data.length === 0 ? (
                    <EmptyState
                        title={
                            filtered ? 'No licenses match' : 'No licenses yet'
                        }
                    />
                ) : (
                    <ul className="grid gap-3">
                        {licenses.data.map((l) => (
                            <li key={l.id}>
                                <LicenseCard license={l} />
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination
                    from={licenses.from}
                    to={licenses.to}
                    total={licenses.total}
                    prevUrl={licenses.prev_page_url}
                    nextUrl={licenses.next_page_url}
                />
            </AdminPage>
        </>
    );
}

function LicenseCard({ license: l }: { license: LicenseRow }) {
    const actions: Action[] =
        l.status === 'active'
            ? ['extend', 'suspend', 'revoke']
            : l.status === 'suspended'
              ? ['reinstate', 'revoke']
              : ['extend'];

    return (
        <Card className="py-4">
            <CardContent className="grid gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{l.product}</span>
                    <Badge
                        variant={
                            l.status === 'active' ? 'outline' : 'destructive'
                        }
                    >
                        {l.status}
                    </Badge>
                    <span className="font-mono text-xs text-muted-foreground">
                        …{l.key_hint}
                    </span>
                    <span className="text-sm text-muted-foreground">
                        {l.owner}
                    </span>
                    <div className="ml-auto flex flex-wrap gap-2">
                        {actions.map((a) => (
                            <StatusDialog key={a} license={l} action={a} />
                        ))}
                    </div>
                </div>
                <p className="text-sm text-muted-foreground">
                    {l.activations}
                    {l.activation_limit ? ` of ${l.activation_limit}` : ''}{' '}
                    activations · updates until{' '}
                    {l.updates_until ?? 'no end date'}
                    {l.status_reason && ` · ${l.status_reason}`}
                </p>
                <Collapsible>
                    <CollapsibleTrigger asChild>
                        <Button size="sm" variant="ghost" className="-ml-2">
                            <ChevronDown /> History ({l.events.length})
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <ol className="grid gap-1 border-l pl-4 text-sm">
                            {l.events.map((e) => (
                                <li key={e.id}>
                                    <span className="text-muted-foreground">
                                        {new Date(
                                            e.created_at,
                                        ).toLocaleString()}
                                    </span>{' '}
                                    <span className="font-medium">
                                        {e.action}
                                    </span>
                                    {e.actor && ` by ${e.actor}`}
                                    {e.instance_id && (
                                        <span className="font-mono text-xs text-muted-foreground">
                                            {' '}
                                            store {e.instance_id.slice(0, 8)}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </CollapsibleContent>
                </Collapsible>
            </CardContent>
        </Card>
    );
}

const copy: Record<Action, { label: string; description: string }> = {
    extend: {
        label: 'Extend updates',
        description: 'Adds months to the update period.',
    },
    suspend: {
        label: 'Suspend',
        description:
            'Activations and downloads stop until the license is reinstated.',
    },
    revoke: {
        label: 'Revoke',
        description:
            'Permanently stops activations and downloads for this license.',
    },
    reinstate: {
        label: 'Reinstate',
        description: 'The license becomes active again.',
    },
};

function StatusDialog({
    license,
    action,
}: {
    license: LicenseRow;
    action: Action;
}) {
    const destructive = action === 'suspend' || action === 'revoke';

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    {copy[action].label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {copy[action].label}: {license.product} …{license.key_hint}
                </DialogTitle>
                <DialogDescription>
                    {copy[action].description}
                </DialogDescription>
                <Form
                    {...MarketplaceAdminController.licenseStatus.form(
                        license.id,
                    )}
                    options={{ preserveScroll: true }}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="action" value={action} />
                            {action === 'extend' ? (
                                <FormField
                                    id="months"
                                    label="Months"
                                    error={errors.months}
                                >
                                    <Input
                                        id="months"
                                        name="months"
                                        type="number"
                                        min={1}
                                        max={60}
                                        defaultValue={12}
                                        required
                                    />
                                </FormField>
                            ) : (
                                <FormField
                                    id="reason"
                                    label="Reason"
                                    error={errors.reason}
                                >
                                    <Textarea
                                        id="reason"
                                        name="reason"
                                        rows={3}
                                        required
                                    />
                                </FormField>
                            )}
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant={
                                        destructive ? 'destructive' : 'default'
                                    }
                                    disabled={processing}
                                >
                                    {copy[action].label}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

MarketplaceLicenses.layout = {
    breadcrumbs: [{ title: 'Licenses', href: index() }],
};
