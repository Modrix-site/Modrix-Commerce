import { EmptyState } from '@/components/admin/empty-state';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AuditLogEntry } from '@/types';

const dateFormat = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

export function AuditTable({
    entries,
    compact = false,
}: {
    entries: AuditLogEntry[];
    compact?: boolean;
}) {
    if (entries.length === 0) {
        return (
            <EmptyState
                title="No activity recorded"
                description="Security-relevant actions such as sign-ins and role changes are logged here."
            />
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>When</TableHead>
                    <TableHead>Action</TableHead>
                    <TableHead>Actor</TableHead>
                    <TableHead>Subject</TableHead>
                    {!compact && <TableHead>IP address</TableHead>}
                    {!compact && <TableHead>Details</TableHead>}
                </TableRow>
            </TableHeader>
            <TableBody>
                {entries.map((entry) => (
                    <TableRow key={entry.id}>
                        <TableCell className="whitespace-nowrap text-muted-foreground">
                            <time dateTime={entry.created_at}>
                                {dateFormat.format(new Date(entry.created_at))}
                            </time>
                        </TableCell>
                        <TableCell>
                            <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                {entry.action}
                            </code>
                        </TableCell>
                        <TableCell>{entry.actor ?? 'System / guest'}</TableCell>
                        <TableCell className="text-muted-foreground">
                            {entry.subject_type
                                ? `${entry.subject_type} #${entry.subject_id}`
                                : '—'}
                        </TableCell>
                        {!compact && (
                            <TableCell className="text-muted-foreground">
                                {entry.ip_address ?? '—'}
                            </TableCell>
                        )}
                        {!compact && (
                            <TableCell className="max-w-sm">
                                {entry.metadata ? (
                                    <details>
                                        <summary className="cursor-pointer text-xs text-muted-foreground">
                                            Show
                                        </summary>
                                        <pre className="mt-1 overflow-x-auto rounded bg-muted p-2 text-xs">
                                            {JSON.stringify(
                                                entry.metadata,
                                                null,
                                                2,
                                            )}
                                        </pre>
                                    </details>
                                ) : (
                                    '—'
                                )}
                            </TableCell>
                        )}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
