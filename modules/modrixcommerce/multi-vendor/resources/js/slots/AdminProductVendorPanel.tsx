import { Link, router } from '@inertiajs/react';
import { BadgeCheck, ExternalLink, Store } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface VendorOption {
    id: number;
    store_name: string;
    status: string;
    is_verified: boolean;
    commission_rate: number | null;
}

interface Props {
    productId?: number;
    defaultCommissionRate?: number;
    assignment?: {
        id: number;
        vendor_id: number;
        vendor_name: string | null;
        vendor_sku: string | null;
        approval_status: string;
        commission_override: number | null;
    } | null;
    vendors?: VendorOption[];
    assignUrl?: string;
    dashboardUrl?: string;
}

export default function AdminProductVendorPanel({
    productId,
    defaultCommissionRate = 10,
    assignment = null,
    vendors = [],
    assignUrl = '/admin/m/modrixcommerce/multi-vendor/products/assign',
    dashboardUrl = '/admin/m/modrixcommerce/multi-vendor',
}: Props) {
    const [vendorId, setVendorId] = useState<string>(
        assignment?.vendor_id ? String(assignment.vendor_id) : 'none',
    );
    const [commissionOverride, setCommissionOverride] = useState<string>(
        assignment?.commission_override !== null &&
            assignment?.commission_override !== undefined
            ? String(assignment.commission_override)
            : '',
    );
    const [vendorSku, setVendorSku] = useState<string>(
        assignment?.vendor_sku ?? '',
    );
    const [saving, setSaving] = useState(false);

    if (!productId) {
        return null;
    }

    const saveAssignment = () => {
        setSaving(true);
        router.post(
            assignUrl,
            {
                product_id: productId,
                vendor_id: vendorId === 'none' ? null : Number(vendorId),
                commission_override:
                    commissionOverride !== ''
                        ? Number(commissionOverride)
                        : null,
                vendor_sku: vendorSku || null,
                approval_status: 'approved',
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2 text-sm">
                        <Store className="size-4 text-primary" />
                        Marketplace Vendor
                    </CardTitle>
                    {assignment?.vendor_name && (
                        <Badge variant="default" className="text-[10px]">
                            {assignment.vendor_name}
                        </Badge>
                    )}
                </div>
                <CardDescription className="text-xs">
                    Assign this product to a verified marketplace seller for
                    commission splitting and seller payouts.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="space-y-1.5">
                    <Label className="text-xs">Assigned Seller</Label>
                    <Select value={vendorId} onValueChange={setVendorId}>
                        <SelectTrigger className="h-8 text-xs">
                            <SelectValue placeholder="Store In-House (No Vendor)" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                Store In-House (No Vendor)
                            </SelectItem>
                            {vendors.map((v) => (
                                <SelectItem key={v.id} value={String(v.id)}>
                                    {v.store_name}{' '}
                                    {v.is_verified ? '✓' : `(${v.status})`}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {vendorId !== 'none' && (
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-1">
                            <Label className="text-xs">
                                Commission % (Def {defaultCommissionRate}%)
                            </Label>
                            <Input
                                className="h-8 text-xs"
                                type="number"
                                min={0}
                                max={90}
                                step="0.5"
                                placeholder={`${defaultCommissionRate}`}
                                value={commissionOverride}
                                onChange={(e) =>
                                    setCommissionOverride(e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">Vendor SKU</Label>
                            <Input
                                className="h-8 text-xs"
                                placeholder="Optional"
                                value={vendorSku}
                                onChange={(e) => setVendorSku(e.target.value)}
                            />
                        </div>
                    </div>
                )}

                <div className="flex items-center justify-between pt-1">
                    <Link
                        href={dashboardUrl}
                        className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    >
                        Manage Vendors
                        <ExternalLink className="size-3" />
                    </Link>
                    <Button
                        type="button"
                        size="sm"
                        className="h-7 text-xs"
                        disabled={saving}
                        onClick={saveAssignment}
                    >
                        <BadgeCheck className="mr-1 size-3.5" />
                        Save Vendor
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
