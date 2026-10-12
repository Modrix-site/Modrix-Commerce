import { Badge } from '@/components/ui/badge';

type Variant = 'default' | 'secondary' | 'destructive' | 'outline';

const variants: Record<string, Variant> = {
    published: 'default',
    draft: 'outline',
    archived: 'secondary',
    pending: 'outline',
    processing: 'default',
    completed: 'secondary',
    cancelled: 'destructive',
    unpaid: 'outline',
    paid: 'default',
    partially_refunded: 'secondary',
    refunded: 'secondary',
    unfulfilled: 'outline',
    shipped: 'default',
    delivered: 'secondary',
    active: 'default',
    inactive: 'outline',
};

/**
 * Badge for a lifecycle state. Labels are derived from the state value.
 */
export function StatusBadge({ status }: { status: string }) {
    const label = status
        .replace(/_/g, ' ')
        .replace(/^./, (c) => c.toUpperCase());

    return <Badge variant={variants[status] ?? 'outline'}>{label}</Badge>;
}
