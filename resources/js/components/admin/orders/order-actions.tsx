import { Form } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import { FormField } from '@/components/admin/form-field';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import type { AdminOrder } from './types';

type Action =
    | 'record_payment'
    | 'mark_packed'
    | 'ship'
    | 'mark_delivered'
    | 'release'
    | 'hold'
    | 'refund'
    | 'cancel';

const labels: Record<Action, string> = {
    record_payment: 'Record payment',
    mark_packed: 'Mark packed',
    ship: 'Mark shipped',
    mark_delivered: 'Mark delivered',
    release: 'Release hold',
    hold: 'Put on hold',
    refund: 'Refund',
    cancel: 'Cancel order',
};

/** The next step in the order's life, offered as the main button. */
const primaryOrder: Action[] = [
    'release',
    'record_payment',
    'mark_packed',
    'ship',
    'mark_delivered',
];

const destructive: Action[] = ['refund', 'cancel'];

/**
 * The main next step plus a menu of everything else staff may do now.
 * Only actions the server allows (and the user may perform) are passed in.
 */
export function OrderActions({
    order,
    actions,
}: {
    order: AdminOrder;
    actions: string[];
}) {
    const [open, setOpen] = useState<Action | null>(null);
    const allowed = (Object.keys(labels) as Action[]).filter((a) =>
        actions.includes(a),
    );
    const primary = primaryOrder.find((a) => allowed.includes(a)) ?? null;
    const rest = allowed.filter((a) => a !== primary);
    const regular = rest.filter((a) => !destructive.includes(a));
    const dangerous = rest.filter((a) => destructive.includes(a));

    if (allowed.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {primary && (
                <Button onClick={() => setOpen(primary)}>
                    {labels[primary]}
                </Button>
            )}
            {rest.length > 0 && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline">
                            More actions
                            <ChevronDown className="size-4" aria-hidden />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {regular.map((a) => (
                            <DropdownMenuItem
                                key={a}
                                onSelect={() => setOpen(a)}
                            >
                                {labels[a]}
                            </DropdownMenuItem>
                        ))}
                        {regular.length > 0 && dangerous.length > 0 && (
                            <DropdownMenuSeparator />
                        )}
                        {dangerous.map((a) => (
                            <DropdownMenuItem
                                key={a}
                                variant="destructive"
                                onSelect={() => setOpen(a)}
                            >
                                {labels[a]}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}

            <ActionDialogs
                order={order}
                open={open}
                onClose={() => setOpen(null)}
            />
        </div>
    );
}

function ActionDialogs({
    order,
    open,
    onClose,
}: {
    order: AdminOrder;
    open: Action | null;
    onClose: () => void;
}) {
    const props = (action: Action) => ({
        open: open === action,
        onOpenChange: (value: boolean) => !value && onClose(),
        onDone: onClose,
    });

    return (
        <>
            <ActionDialog
                {...props('record_payment')}
                title="Record payment"
                description={`Confirm that ${order.grand_total.formatted} was received via ${order.payment_method}. Only record payments you have verified in your bank account or cash register.`}
                form={OrderController.recordPayment.form(order.number)}
                submit="Record payment"
            >
                <FormField id="reference" label="Reference (optional)">
                    <Input
                        id="reference"
                        name="reference"
                        placeholder="Bank transaction ID"
                    />
                </FormField>
                <FormField id="pay-note" label="Note (optional)">
                    <Input id="pay-note" name="note" />
                </FormField>
            </ActionDialog>

            <ActionDialog
                {...props('mark_packed')}
                title="Mark as packed"
                description="The order is packed and ready to hand to the carrier."
                form={OrderController.pack.form(order.number)}
                submit="Mark packed"
            >
                <NotifyField />
            </ActionDialog>

            <ActionDialog
                {...props('ship')}
                title="Mark as shipped"
                description="Add the tracking details the customer should receive."
                form={OrderController.ship.form(order.number)}
                submit="Mark shipped"
            >
                <FormField id="carrier" label="Carrier (optional)">
                    <Input id="carrier" name="carrier" />
                </FormField>
                <FormField
                    id="tracking_number"
                    label="Tracking number (optional)"
                >
                    <Input id="tracking_number" name="tracking_number" />
                </FormField>
                <FormField id="tracking_url" label="Tracking URL (optional)">
                    <Input id="tracking_url" name="tracking_url" type="url" />
                </FormField>
                <NotifyField defaultChecked />
            </ActionDialog>

            <ActionDialog
                {...props('mark_delivered')}
                title="Mark this order as delivered?"
                description="The customer's order will show as delivered. Paid orders become completed."
                form={OrderController.deliver.form(order.number)}
                submit="Mark delivered"
            />

            <ActionDialog
                {...props('hold')}
                title={`Put order ${order.number} on hold?`}
                description="The order cannot be packed or shipped until you release it. Payments, refunds and cancellation still work. Customers never see 'on hold'."
                form={OrderController.hold.form(order.number)}
                submit="Put on hold"
            >
                <FormField id="hold-reason" label="Reason (optional)">
                    <Input
                        id="hold-reason"
                        name="reason"
                        placeholder="e.g. Verifying the delivery address"
                    />
                </FormField>
                <NotifyField hint="The reason is sent to the customer." />
            </ActionDialog>

            <ActionDialog
                {...props('release')}
                title="Release the hold?"
                description="The order goes back to its previous status and can be packed and shipped again."
                form={OrderController.release.form(order.number)}
                submit="Release hold"
            >
                <NotifyField />
            </ActionDialog>

            <ActionDialog
                {...props('cancel')}
                title={`Cancel order ${order.number}?`}
                description="Stock is returned to inventory and the customer is notified. This cannot be undone."
                form={OrderController.cancel.form(order.number)}
                submit="Cancel order"
                destructive
            >
                <FormField id="reason" label="Reason">
                    <Input id="reason" name="reason" required />
                </FormField>
            </ActionDialog>

            <ActionDialog
                {...props('refund')}
                title="Record a refund"
                description={`Up to ${order.refundable.formatted} can be refunded. For ${order.payment_method}, return the money outside the store, then record it here.`}
                form={OrderController.refund.form(order.number)}
                submit="Record refund"
                destructive
            >
                <FormField id="amount" label={`Amount (${order.currency})`}>
                    <Input
                        id="amount"
                        name="amount"
                        inputMode="decimal"
                        defaultValue={order.refundable.amount}
                        required
                    />
                </FormField>
                <FormField id="refund-reason" label="Reason">
                    <Input id="refund-reason" name="reason" required />
                </FormField>
                <fieldset className="grid gap-2">
                    <legend className="mb-1 text-sm font-medium">
                        Return items to stock
                    </legend>
                    {order.items.map((item) => {
                        const max = item.quantity - item.restocked_quantity;

                        return (
                            <label
                                key={item.id}
                                className="flex items-center justify-between gap-3 text-sm"
                            >
                                <span>
                                    {item.product_name}{' '}
                                    {item.variant_name &&
                                        `(${item.variant_name})`}
                                </span>
                                <Input
                                    name={`restock[${item.id}]`}
                                    type="number"
                                    min={0}
                                    max={max}
                                    defaultValue={0}
                                    disabled={max === 0}
                                    className="w-20"
                                    aria-label={`Restock quantity for ${item.sku}`}
                                />
                            </label>
                        );
                    })}
                </fieldset>
            </ActionDialog>
        </>
    );
}

function NotifyField({
    defaultChecked = false,
    hint,
}: {
    defaultChecked?: boolean;
    hint?: string;
}) {
    return (
        <label className="flex items-start gap-2 text-sm">
            {/* Unticked checkboxes are not submitted: send an explicit 0. */}
            <input type="hidden" name="notify" value="0" />
            <Checkbox
                name="notify"
                value="1"
                defaultChecked={defaultChecked}
                className="mt-0.5"
            />
            <span>
                Email the customer
                {hint && (
                    <span className="block text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </span>
        </label>
    );
}

type FormDef = ReturnType<typeof OrderController.deliver.form>;

function ActionDialog({
    open,
    onOpenChange,
    onDone,
    title,
    description,
    form,
    submit,
    destructive = false,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onDone: () => void;
    title: string;
    description: string;
    form: FormDef;
    submit: string;
    destructive?: boolean;
    children?: ReactNode;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={onDone}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {children}
                            <InputError
                                message={
                                    errors.order ??
                                    errors.amount ??
                                    errors.restock ??
                                    errors.reason
                                }
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Close
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant={
                                        destructive ? 'destructive' : 'default'
                                    }
                                    disabled={processing}
                                >
                                    {submit}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
