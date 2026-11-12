import { Badge } from '@/components/ui/badge';
import { useT } from '@/lib/i18n';

type Variant = 'default' | 'secondary' | 'destructive' | 'outline';

const variants: Record<string, Variant> = {
    published: 'default',
    draft: 'outline',
    archived: 'secondary',
    pending: 'outline',
    approved: 'default',
    rejected: 'destructive',
    pending_payment: 'outline',
    payment_failed: 'destructive',
    partially_paid: 'outline',
    failed: 'destructive',
    confirmed: 'default',
    on_hold: 'secondary',
    processing: 'default',
    picking: 'secondary',
    packing: 'secondary',
    packed: 'secondary',
    ready_for_dispatch: 'secondary',
    in_transit: 'default',
    out_for_delivery: 'default',
    delivery_failed: 'destructive',
    returned: 'secondary',
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
    ready_for_pickup: 'default',
    picked_up: 'secondary',
};

/**
 * Badge for a lifecycle state. Labels are derived from the state value.
 */
export function StatusBadge({ status }: { status: string }) {
    const t = useT();
    // Order states are translatable on storefront pages.
    const labels: Record<string, string> = {
        pending_payment: t('Pending payment'),
        payment_failed: t('Payment failed'),
        failed: t('Payment failed'),
        partially_paid: t('Partially paid'),
        pending: t('Pending'),
        approved: t('Approved'),
        rejected: t('Rejected'),
        confirmed: t('Confirmed'),
        on_hold: t('On hold'),
        processing: t('Processing'),
        picking: t('Picking'),
        packing: t('Packing'),
        packed: t('Packed'),
        ready_for_dispatch: t('Ready for dispatch'),
        shipped: t('Shipped'),
        in_transit: t('In transit'),
        out_for_delivery: t('Out for delivery'),
        delivered: t('Delivered'),
        delivery_failed: t('Delivery failed'),
        returned: t('Returned'),
        completed: t('Completed'),
        cancelled: t('Cancelled'),
        unpaid: t('Unpaid'),
        paid: t('Paid'),
        partially_refunded: t('Partially refunded'),
        refunded: t('Refunded'),
        unfulfilled: t('Unfulfilled'),
        ready_for_pickup: t('Ready for pickup'),
        picked_up: t('Picked up'),
    };
    const label =
        labels[status] ??
        status.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());

    const tone = [
        'completed',
        'delivered',
        'paid',
        'approved',
        'published',
        'active',
        'ready_for_pickup',
        'picked_up',
    ].includes(status)
        ? 'success'
        : [
                'cancelled',
                'failed',
                'payment_failed',
                'delivery_failed',
                'rejected',
            ].includes(status)
          ? 'danger'
          : [
                  'pending',
                  'pending_payment',
                  'unpaid',
                  'partially_paid',
                  'on_hold',
              ].includes(status)
            ? 'warning'
            : [
                    'confirmed',
                    'processing',
                    'shipped',
                    'in_transit',
                    'out_for_delivery',
                ].includes(status)
              ? 'progress'
              : 'neutral';

    return (
        <Badge
            className="status-badge"
            data-tone={tone}
            data-status={status}
            variant={variants[status] ?? 'outline'}
        >
            {label}
        </Badge>
    );
}
