import { Form, Head, Link } from '@inertiajs/react';
import { AuditTable } from '@/components/admin/audit-table';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/audit-logs';
import type { AuditLogEntry, ResourcePaginated } from '@/types';

type Props = {
    logs: ResourcePaginated<AuditLogEntry>;
    filters: { action: string; actor: string };
    actions: string[];
};

export default function AuditLogIndex({ logs, filters, actions }: Props) {
    return (
        <>
            <Head title="Audit log" />
            <AdminPage>
                <PageHeader
                    title="Audit log"
                    description="Append-only record of security-relevant actions. Secrets are redacted before storage."
                />

                <Form
                    {...index.form()}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    <div className="grid gap-2 sm:w-64">
                        <Label htmlFor="action">Action</Label>
                        <NativeSelect
                            id="action"
                            name="action"
                            defaultValue={filters.action}
                        >
                            <option value="">All actions</option>
                            {actions.map((action) => (
                                <option key={action} value={action}>
                                    {action}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-2 sm:w-64">
                        <Label htmlFor="actor">Actor email</Label>
                        <Input
                            id="actor"
                            name="actor"
                            defaultValue={filters.actor}
                            placeholder="Search by email"
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" variant="secondary">
                            Filter
                        </Button>
                        {(filters.action || filters.actor) && (
                            <Button variant="ghost" asChild>
                                <Link href={index()}>Clear</Link>
                            </Button>
                        )}
                    </div>
                </Form>

                <AuditTable entries={logs.data} />

                <Pagination
                    from={logs.meta.from}
                    to={logs.meta.to}
                    total={logs.meta.total}
                    prevUrl={logs.links.prev}
                    nextUrl={logs.links.next}
                />
            </AdminPage>
        </>
    );
}

AuditLogIndex.layout = {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
};
