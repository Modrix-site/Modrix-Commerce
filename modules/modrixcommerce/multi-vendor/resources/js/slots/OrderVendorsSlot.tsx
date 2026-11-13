import { Link } from '@inertiajs/react';
import { BadgeCheck, Store, Truck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';

interface OrderVendorItem {
    id: number;
    product_name: string;
    quantity: number;
    vendor_name: string;
    vendor_verified: boolean;
    vendor_url: string | null;
    fulfillment_status: string;
    tracking_number: string | null;
}

interface Props {
    items?: OrderVendorItem[];
}

export default function OrderVendorsSlot({ items = [] }: Props) {
    if (items.length === 0) {
        return null;
    }

    return (
        <div className="my-4 space-y-3 rounded-xl border bg-card p-4">
            <div className="flex items-center gap-2 text-sm font-semibold">
                <Store className="size-4 text-primary" />
                Marketplace Seller Fulfillment Status
            </div>

            <div className="divide-y text-xs">
                {items.map((item) => (
                    <div
                        key={item.id}
                        className="flex flex-col justify-between gap-2 py-2.5 sm:flex-row sm:items-center"
                    >
                        <div>
                            <span className="font-medium text-foreground">
                                {item.product_name} × {item.quantity}
                            </span>
                            <div className="mt-0.5 flex items-center gap-1.5 text-muted-foreground">
                                <span>Sold & fulfilled by:</span>
                                {item.vendor_url ? (
                                    <Link
                                        href={item.vendor_url}
                                        className="font-medium text-primary hover:underline"
                                    >
                                        {item.vendor_name}
                                    </Link>
                                ) : (
                                    <strong className="text-foreground">
                                        {item.vendor_name}
                                    </strong>
                                )}
                                {item.vendor_verified && (
                                    <BadgeCheck className="size-3.5 text-emerald-600" />
                                )}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">
                                <Truck className="mr-1 size-3" />
                                {item.fulfillment_status}
                            </Badge>
                            {item.tracking_number && (
                                <Badge variant="outline">
                                    Tracking: {item.tracking_number}
                                </Badge>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
