import { Badge } from '@/components/ui/badge';
import { useT } from '@/lib/i18n';

type Variant = 'default' | 'secondary' | 'destructive' | 'outline';

const variants: Record<string, Variant> = {
    published: 'default',
    draft: 'outline',
    archived: 'secondary',
    pending: 'outline',
    processing: 'default',
    on_hold: 'secondary',
    completed: 'secondary',
    cancelled: 'destructive',
    unpaid: 'outline',
    paid: 'default',
    partially_refunded: 'secondary',
    refunded: 'secondary',
    unfulfilled: 'outline',
    packed: 'secondary',
    shipped: 'default',
    delivered: 'secondary',
    active: 'default',
    inactive: 'outline',
};

/**
 * Badge for a lifecycle state. Labels are derived from the state value.
 */
export function StatusBadge({ status }: { status: string }) {
    const t = useT();
    // Customer-facing states are translatable on storefront pages.
    const labels: Record<string, string> = {
        pending: t('Pending'),
        processing: t('Processing'),
        completed: t('Completed'),
        cancelled: t('Cancelled'),
        unpaid: t('Unpaid'),
        paid: t('Paid'),
        partially_refunded: t('Partially refunded'),
        refunded: t('Refunded'),
        unfulfilled: t('Unfulfilled'),
        shipped: t('Shipped'),
        delivered: t('Delivered'),
        // Staff-only states (customers never see them).
        on_hold: 'On hold',
        packed: 'Packed',
    };
    const label =
        labels[status] ??
        status.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());

    return <Badge variant={variants[status] ?? 'outline'}>{label}</Badge>;
}
