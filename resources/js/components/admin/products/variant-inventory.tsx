import { useForm } from '@inertiajs/react';
import { PackagePlus } from 'lucide-react';
import { useState } from 'react';
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
import { useCan } from '@/hooks/use-can';
import type { Option } from '@/types';
import type { VariantRow } from './types';

export function VariantInventory({
    variant,
    reasons,
}: {
    variant: VariantRow;
    reasons: Option[];
}) {
    const can = useCan();
    const [open, setOpen] = useState(false);
    const form = useForm({
        mode: 'add',
        quantity: '',
        reason: 'received',
        note: '',
    });

    return (
        <div className="grid gap-2">
            <span className="text-sm tabular-nums">
                {variant.track_inventory
                    ? `${variant.on_hand ?? 0} on hand`
                    : 'Not tracked'}
            </span>
            {variant.id !== null &&
                variant.track_inventory &&
                can('inventory.manage') && (
                    <Dialog
                        open={open}
                        onOpenChange={(value) => {
                            setOpen(value);
                            if (value) {
                                form.reset();
                                form.clearErrors();
                            }
                        }}
                    >
                        <DialogTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="w-fit"
                            >
                                <PackagePlus className="size-3.5" />
                                Add stock
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Update inventory</DialogTitle>
                            <DialogDescription>
                                {variant.sku}: currently {variant.on_hand ?? 0}{' '}
                                on hand. Stock updates are saved immediately and
                                recorded in inventory history.
                            </DialogDescription>
                            <form
                                className="grid gap-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    if (variant.id === null) return;
                                    form.post(
                                        InventoryController.adjust.url(
                                            variant.id,
                                        ),
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                            onSuccess: () => {
                                                setOpen(false);
                                                form.reset();
                                            },
                                        },
                                    );
                                }}
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        id={`stock-mode-${variant.id}`}
                                        label="Action"
                                        error={form.errors.mode}
                                    >
                                        <NativeSelect
                                            id={`stock-mode-${variant.id}`}
                                            value={form.data.mode}
                                            onChange={(event) =>
                                                form.setData(
                                                    'mode',
                                                    event.target.value,
                                                )
                                            }
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
                                        id={`stock-qty-${variant.id}`}
                                        label="Quantity"
                                        error={form.errors.quantity}
                                    >
                                        <Input
                                            id={`stock-qty-${variant.id}`}
                                            type="number"
                                            min={
                                                form.data.mode === 'set' ? 0 : 1
                                            }
                                            max={1000000}
                                            step={1}
                                            required
                                            value={form.data.quantity}
                                            onChange={(event) =>
                                                form.setData(
                                                    'quantity',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                </div>
                                <FormField
                                    id={`stock-reason-${variant.id}`}
                                    label="Reason"
                                    error={form.errors.reason}
                                >
                                    <NativeSelect
                                        id={`stock-reason-${variant.id}`}
                                        value={form.data.reason}
                                        onChange={(event) =>
                                            form.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {reasons.map((reason) => (
                                            <option
                                                key={reason.value}
                                                value={reason.value}
                                            >
                                                {reason.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    id={`stock-note-${variant.id}`}
                                    label="Note"
                                    error={form.errors.note}
                                >
                                    <Input
                                        id={`stock-note-${variant.id}`}
                                        maxLength={500}
                                        placeholder="Optional"
                                        value={form.data.note}
                                        onChange={(event) =>
                                            form.setData(
                                                'note',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            disabled={form.processing}
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing
                                            ? 'Updating…'
                                            : 'Update stock'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                )}
        </div>
    );
}
