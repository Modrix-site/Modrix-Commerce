import { Form } from '@inertiajs/react';
import InventoryController from '@/actions/App/Http/Controllers/Admin/InventoryController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { Option } from '@/types';

/** Add, remove or set a variant's stock; recorded in the stock history. */
export function AdjustStockDialog({
    row,
    reasons,
    label = 'Adjust',
}: {
    row: { id: number; name: string; sku: string; on_hand: number };
    reasons: Option[];
    label?: string;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Adjust stock</DialogTitle>
                <DialogDescription>
                    {row.name} ({row.sku}): currently {row.on_hand} on hand.
                </DialogDescription>
                <Form
                    {...InventoryController.adjust.form(row.id)}
                    options={{ preserveScroll: true }}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id={`mode-${row.id}`}
                                    label="Action"
                                    error={errors.mode}
                                >
                                    <NativeSelect
                                        id={`mode-${row.id}`}
                                        name="mode"
                                        defaultValue="add"
                                    >
                                        <option value="add">
                                            Add to stock
                                        </option>
                                        <option value="remove">
                                            Remove from stock
                                        </option>
                                        <option value="set">
                                            Set stock count to
                                        </option>
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    id={`qty-${row.id}`}
                                    label="Quantity"
                                    error={errors.quantity}
                                >
                                    <Input
                                        id={`qty-${row.id}`}
                                        name="quantity"
                                        type="number"
                                        min={0}
                                        required
                                    />
                                </FormField>
                            </div>
                            <FormField
                                id={`reason-${row.id}`}
                                label="Reason"
                                error={errors.reason}
                            >
                                <NativeSelect
                                    id={`reason-${row.id}`}
                                    name="reason"
                                    defaultValue={reasons[0]?.value}
                                >
                                    {reasons.map((r) => (
                                        <option key={r.value} value={r.value}>
                                            {r.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id={`note-${row.id}`}
                                label="Note"
                                error={errors.note}
                            >
                                <Input
                                    id={`note-${row.id}`}
                                    name="note"
                                    placeholder="Optional"
                                />
                            </FormField>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Save adjustment
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
