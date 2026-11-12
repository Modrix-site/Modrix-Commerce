import {
    Ban,
    CircleDollarSign,
    FileText,
    Mail,
    MessageSquare,
    Package,
    PackageCheck,
    Pause,
    Play,
    RotateCcw,
    ShoppingBag,
    Truck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { formatDate } from '@/components/storefront/order-view';
import { Badge } from '@/components/ui/badge';
import type { TimelineEntry } from './types';

const icons: Record<string, LucideIcon> = {
    placed: ShoppingBag,
    payment: CircleDollarSign,
    refund: RotateCcw,
    fulfillment: Truck,
    packed: PackageCheck,
    held: Pause,
    released: Play,
    status: Ban,
    note: MessageSquare,
    email: Mail,
    invoice_issued: FileText,
};

/**
 * Everything that happened to the order, newest first.
 */
export function OrderTimeline({ entries }: { entries: TimelineEntry[] }) {
    if (entries.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">No activity yet.</p>
        );
    }

    return (
        <ol className="relative grid gap-4">
            {entries.map((entry, i) => {
                const Icon = icons[entry.kind] ?? Package;

                return (
                    <li key={entry.id} className="relative flex gap-3">
                        {i < entries.length - 1 && (
                            <span
                                className="absolute top-8 bottom-[-1rem] left-[0.9375rem] w-px bg-border"
                                aria-hidden
                            />
                        )}
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full border bg-background">
                            <Icon
                                className="size-4 text-muted-foreground"
                                aria-hidden
                            />
                        </span>
                        <div className="grid gap-0.5 pt-1 text-sm">
                            <span>{entry.message}</span>
                            <span className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                <time dateTime={entry.at}>
                                    {formatDate(entry.at)}
                                </time>
                                {entry.actor && <span>· {entry.actor}</span>}
                                {entry.customer_visible ? (
                                    <Badge variant="secondary">
                                        Visible to customer
                                    </Badge>
                                ) : (
                                    <span>· internal</span>
                                )}
                            </span>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
