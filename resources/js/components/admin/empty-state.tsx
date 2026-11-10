import { Inbox } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/** A card shown when a list has nothing in it yet. */
export function EmptyState({
    title,
    description,
    action,
    icon: Icon = Inbox,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
    icon?: LucideIcon;
}) {
    return (
        <div className="admin-empty-state flex flex-col items-center justify-center gap-2 rounded-xl border bg-card px-6 py-14 text-center">
            <span
                className="mb-2 flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
                aria-hidden
            >
                <Icon className="size-5" />
            </span>
            <p className="text-sm font-semibold">{title}</p>
            {description && (
                <p className="max-w-md text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            {action && <div className="mt-3">{action}</div>}
        </div>
    );
}
