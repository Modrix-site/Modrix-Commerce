import { Form } from '@inertiajs/react';
import { Download, FileText, Package, Truck, X } from 'lucide-react';
import { useState } from 'react';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { exportMethod } from '@/routes/admin/orders';
import { documents, fulfillment } from '@/routes/admin/orders/bulk';

const steps = [
    ['picking', 'Picking'],
    ['packing', 'Packing'],
    ['packed', 'Packed'],
    ['ready_for_dispatch', 'Ready for dispatch'],
    ['shipped', 'Shipped'],
    ['in_transit', 'In transit'],
    ['out_for_delivery', 'Out for delivery'],
    ['delivered', 'Delivered'],
] as const;

/**
 * Actions on the selected orders. Each order is checked by the same rules
 * as a single change; the result lists any orders that were skipped.
 */
export function OrderBulkBar({
    numbers,
    canManage,
    onClear,
}: {
    numbers: string[];
    canManage: boolean;
    onClear: () => void;
}) {
    const [moving, setMoving] = useState(false);
    const query = { numbers };
    // Documents render while you wait, so PDFs are limited to 30 orders.
    const tooManyForPdf = numbers.length > 30;

    return (
        <div
            className="sticky bottom-4 z-10 flex flex-wrap items-center gap-2 rounded-lg border bg-background p-2 shadow-lg"
            role="region"
            aria-label="Bulk actions"
        >
            <span className="px-2 text-sm font-medium">
                {numbers.length} selected
            </span>
            {canManage && (
                <Button size="sm" onClick={() => setMoving(true)}>
                    <Truck className="size-4" aria-hidden />
                    Move to step
                </Button>
            )}
            <Button
                size="sm"
                variant="outline"
                asChild={!tooManyForPdf}
                disabled={tooManyForPdf}
                title={
                    tooManyForPdf
                        ? 'Select 30 orders or fewer to print'
                        : undefined
                }
            >
                <a
                    href={documents.url({
                        query: { ...query, type: 'packing-slips' },
                    })}
                    download
                >
                    <Package className="size-4" aria-hidden />
                    Packing slips
                </a>
            </Button>
            <Button
                size="sm"
                variant="outline"
                asChild={!tooManyForPdf}
                disabled={tooManyForPdf}
                title={
                    tooManyForPdf
                        ? 'Select 30 orders or fewer to print'
                        : undefined
                }
            >
                <a
                    href={documents.url({
                        query: { ...query, type: 'invoices' },
                    })}
                    download
                >
                    <FileText className="size-4" aria-hidden />
                    Invoices
                </a>
            </Button>
            <Button size="sm" variant="outline" asChild>
                <a href={exportMethod.url({ query })} download>
                    <Download className="size-4" aria-hidden />
                    Export CSV
                </a>
            </Button>
            <Button
                size="sm"
                variant="ghost"
                onClick={onClear}
                className="ml-auto"
            >
                <X className="size-4" aria-hidden />
                Clear
            </Button>

            <Dialog open={moving} onOpenChange={setMoving}>
                <DialogContent>
                    <DialogTitle>
                        Move {numbers.length} order
                        {numbers.length === 1 ? '' : 's'}
                    </DialogTitle>
                    <DialogDescription>
                        Orders that cannot make this move (not paid, on hold,
                        already further along) are skipped and listed. Shipping
                        in bulk records no tracking number.
                    </DialogDescription>
                    <Form
                        {...fulfillment.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            setMoving(false);
                            onClear();
                        }}
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                {numbers.map((n) => (
                                    <input
                                        key={n}
                                        type="hidden"
                                        name="numbers[]"
                                        value={n}
                                    />
                                ))}
                                <FormField id="bulk-status" label="New status">
                                    <NativeSelect
                                        id="bulk-status"
                                        name="status"
                                        defaultValue="packed"
                                    >
                                        {steps.map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <label className="flex items-start gap-2 text-sm">
                                    <input
                                        type="hidden"
                                        name="notify"
                                        value="0"
                                    />
                                    <Checkbox
                                        name="notify"
                                        value="1"
                                        className="mt-0.5"
                                    />
                                    <span>
                                        Email the customers
                                        <span className="block text-xs text-muted-foreground">
                                            For shipped, out for delivery and
                                            delivered.
                                        </span>
                                    </span>
                                </label>
                                <InputError
                                    message={errors.numbers ?? errors.status}
                                />
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            variant="secondary"
                                            type="button"
                                        >
                                            Close
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        Move orders
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
