import {
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
    MapPin,
    Phone,
    Store,
    User,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    branch_name: string;
    branch_address: string;
    branch_phone: string | null;
    pickup_hours: string | null;
    collector_name: string | null;
    collector_phone: string | null;
    pickup_date: string | null;
    pickup_time: string | null;
    status: string;
    badge: {
        label: string;
        variant: string;
        color: string;
    };
    ready_at: string | null;
    picked_up_at: string | null;
    instructions: string | null;
    customer_notes: string | null;
    payment_method_name?: string | null;
    payment_method_code?: string | null;
    payment_status?: string | null;
};

export default function StorePickupOrderSummary({
    branch_name,
    branch_address,
    branch_phone,
    pickup_hours,
    collector_name,
    collector_phone,
    pickup_date,
    pickup_time,
    status,
    badge,
    ready_at,
    instructions,
    payment_method_name,
    payment_method_code,
    payment_status,
}: Props) {
    const isReady = status === 'ready_for_pickup';
    const isPickedUp = status === 'picked_up';

    return (
        <Card
            className={`my-4 border shadow-xs ${isReady ? 'border-emerald-500/40 bg-emerald-500/5 dark:bg-emerald-950/10' : 'bg-card'}`}
        >
            <CardHeader className="pb-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <Store className="h-5 w-5 text-primary" />
                        <span>In-Store Pickup Details</span>
                    </CardTitle>
                    <Badge
                        variant="outline"
                        style={{
                            borderColor: `${badge.color}60`,
                            backgroundColor: `${badge.color}15`,
                            color: badge.color,
                        }}
                        className="px-2.5 py-0.5 text-xs font-semibold"
                    >
                        {isReady && (
                            <CheckCircle2 className="mr-1 inline h-3.5 w-3.5" />
                        )}
                        {badge.label}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
                {isReady && (
                    <div className="flex items-start gap-2 rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs text-emerald-800 dark:text-emerald-300">
                        <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
                        <div>
                            <strong>
                                Great news! Your order is ready for pickup.
                            </strong>
                            {ready_at && (
                                <span className="mt-0.5 block text-emerald-700/80 dark:text-emerald-400/80">
                                    Marked ready on{' '}
                                    {new Date(ready_at).toLocaleString()}
                                </span>
                            )}
                        </div>
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2">
                    {/* Location Info */}
                    <div className="space-y-1.5 rounded-lg border bg-muted/20 p-3">
                        <div className="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            <MapPin className="h-3.5 w-3.5" /> Pickup Location
                        </div>
                        <div className="font-semibold text-foreground">
                            {branch_name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {branch_address}
                        </div>
                        {branch_phone && (
                            <div className="flex items-center gap-1 pt-1 text-xs text-muted-foreground">
                                <Phone className="h-3 w-3" /> {branch_phone}
                            </div>
                        )}
                    </div>

                    {/* Schedule & Hours */}
                    <div className="space-y-1.5 rounded-lg border bg-muted/20 p-3">
                        <div className="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            <Clock className="h-3.5 w-3.5" /> Hours & Scheduling
                        </div>
                        <div className="text-xs text-muted-foreground">
                            <strong>Operating Hours:</strong>{' '}
                            {pickup_hours || 'Standard store hours'}
                        </div>
                        {pickup_date && (
                            <div className="flex items-center gap-1.5 pt-1 text-xs text-foreground">
                                <Calendar className="h-3.5 w-3.5 text-primary" />
                                <span>
                                    Requested Date:{' '}
                                    <strong>{pickup_date}</strong>{' '}
                                    {pickup_time ? `(${pickup_time})` : ''}
                                </span>
                            </div>
                        )}
                        {collector_name && (
                            <div className="flex items-center gap-1.5 pt-1 text-xs text-foreground">
                                <User className="h-3.5 w-3.5 text-primary" />
                                <span>
                                    Authorized Collector:{' '}
                                    <strong>{collector_name}</strong>{' '}
                                    {collector_phone
                                        ? `(${collector_phone})`
                                        : ''}
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                {/* Payment reminder */}
                {payment_method_name && (
                    <div className="flex items-center justify-between rounded-lg border bg-muted/20 px-3 py-2 text-xs">
                        <div className="flex items-center gap-1.5 text-muted-foreground">
                            <CreditCard className="h-3.5 w-3.5 text-primary" />
                            <span>Payment Method:</span>
                            <strong className="text-foreground">
                                {payment_method_name}
                            </strong>
                        </div>
                        {payment_status === 'pending' ||
                        payment_status === 'unpaid' ? (
                            <Badge
                                variant="outline"
                                className="border-amber-500/30 bg-amber-50/50 text-[10px] text-amber-600 dark:bg-amber-950/20 dark:text-amber-400"
                            >
                                Pay on Collection
                            </Badge>
                        ) : (
                            <Badge
                                variant="outline"
                                className="border-emerald-500/30 bg-emerald-50/50 text-[10px] text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400"
                            >
                                Paid
                            </Badge>
                        )}
                    </div>
                )}

                {/* Instructions */}
                {instructions && (
                    <div className="rounded-lg border bg-muted/40 p-3 text-xs text-muted-foreground">
                        <strong className="text-foreground">
                            Collection Instructions:
                        </strong>{' '}
                        {instructions}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
