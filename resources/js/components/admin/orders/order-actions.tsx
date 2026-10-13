import { Form } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import type { AdminOrder } from './types';

const stepLabels: Record<string, string> = {
    picking: 'Picking',
    packing: 'Packing',
    packed: 'Packed',
    ready_for_dispatch: 'Ready for dispatch',
    shipped: 'Shipped',
    in_transit: 'In transit',
    out_for_delivery: 'Out for delivery',
    delivered: 'Delivered',
};

/** Steps the customer is emailed about by default (when the store setting is on). */
const emailedSteps = ['shipped', 'out_for_delivery', 'delivered'];

type Dialogs =
    | { kind: 'move'; target: string }
    | {
          kind:
              | 'record_payment'
              | 'confirm'
              | 'hold'
              | 'release'
              | 'delivery_failed'
              | 'returned'
              | 'refund'
              | 'cancel';
      };

/**
 * The next step as the main button, plus a menu of everything else staff
 * may do now. Only actions the server allows are passed in.
 */
export function OrderActions({
    order,
    actions,
    emailDefaults,
}: {
    order: AdminOrder;
    actions: string[];
    emailDefaults: Record<string, boolean>;
}) {
    const [open, setOpen] = useState<Dialogs | null>(null);
    const can = (a: string) => actions.includes(a);
    const targets = actions
        .filter((a) => a.startsWith('advance:'))
        .map((a) => a.slice('advance:'.length));

    type Item = { label: string; dialog: Dialogs; danger?: boolean };
    const primary: Item | null = can('release')
        ? { label: 'Release hold', dialog: { kind: 'release' } }
        : can('confirm')
          ? { label: 'Confirm order', dialog: { kind: 'confirm' } }
          : can('record_payment')
            ? { label: 'Record payment', dialog: { kind: 'record_payment' } }
            : targets.length > 0
              ? {
                    label: `Mark ${stepLabels[targets[0]].toLowerCase()}`,
                    dialog: { kind: 'move', target: targets[0] },
                }
              : null;

    const more: Item[] = [
        ...(targets.length > 0 && primary?.dialog.kind !== 'move'
            ? [
                  {
                      label: `Mark ${stepLabels[targets[0]].toLowerCase()}`,
                      dialog: { kind: 'move', target: targets[0] } as Dialogs,
                  },
              ]
            : []),
        ...(targets.length > 1
            ? [
                  {
                      label: 'Move to another step…',
                      dialog: {
                          kind: 'move',
                          target: targets[1],
                      } as Dialogs,
                  },
              ]
            : []),
        ...(can('record_payment') && primary?.dialog.kind !== 'record_payment'
            ? [
                  {
                      label: 'Record payment',
                      dialog: { kind: 'record_payment' } as Dialogs,
                  },
              ]
            : []),
        ...(can('delivery_failed')
            ? [
                  {
                      label: 'Delivery failed',
                      dialog: { kind: 'delivery_failed' } as Dialogs,
                  },
              ]
            : []),
        ...(can('returned')
            ? [
                  {
                      label: 'Mark returned',
                      dialog: { kind: 'returned' } as Dialogs,
                  },
              ]
            : []),
        ...(can('hold')
            ? [{ label: 'Put on hold', dialog: { kind: 'hold' } as Dialogs }]
            : []),
        ...(can('refund')
            ? [
                  {
                      label: 'Refund',
                      dialog: { kind: 'refund' } as Dialogs,
                      danger: true,
                  },
              ]
            : []),
        ...(can('cancel')
            ? [
                  {
                      label: 'Cancel order',
                      dialog: { kind: 'cancel' } as Dialogs,
                      danger: true,
                  },
              ]
            : []),
    ];

    if (!primary && more.length === 0) {
        return null;
    }

    const regular = more.filter((i) => !i.danger);
    const dangerous = more.filter((i) => i.danger);

    return (
        <div className="flex flex-wrap gap-2">
            {primary && (
                <Button onClick={() => setOpen(primary.dialog)}>
                    {primary.label}
                </Button>
            )}
            {more.length > 0 && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline">
                            More actions
                            <ChevronDown className="size-4" aria-hidden />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {regular.map((item) => (
                            <DropdownMenuItem
                                key={item.label}
                                onSelect={() => setOpen(item.dialog)}
                            >
                                {item.label}
                            </DropdownMenuItem>
                        ))}
                        {regular.length > 0 && dangerous.length > 0 && (
                            <DropdownMenuSeparator />
                        )}
                        {dangerous.map((item) => (
                            <DropdownMenuItem
                                key={item.label}
                                variant="destructive"
                                onSelect={() => setOpen(item.dialog)}
                            >
                                {item.label}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}

            {open?.kind === 'move' && (
                <MoveDialog
                    key={open.target}
                    order={order}
                    targets={targets}
                    initial={open.target}
                    emailDefaults={emailDefaults}
                    onClose={() => setOpen(null)}
                />
            )}
            <OtherDialogs
                order={order}
                open={open?.kind === 'move' ? null : (open?.kind ?? null)}
                emailDefaults={emailDefaults}
                onClose={() => setOpen(null)}
            />
        </div>
    );
}

function MoveDialog({
    order,
    targets,
    initial,
    emailDefaults,
    onClose,
}: {
    order: AdminOrder;
    targets: string[];
    initial: string;
    emailDefaults: Record<string, boolean>;
    onClose: () => void;
}) {
    const [target, setTarget] = useState(initial);
    const emailed = emailedSteps.includes(target);

    return (
        <ActionDialog
            open
            onOpenChange={(value) => !value && onClose()}
            onDone={onClose}
            title="Update fulfilment"
            description="Steps only move forward. You can skip steps your warehouse does not use."
            form={OrderController.advance.form(order.number)}
            submit={`Mark ${stepLabels[target].toLowerCase()}`}
        >
            <FormField id="status" label="New status">
                <NativeSelect
                    id="status"
                    name="status"
                    value={target}
                    onChange={(e) => setTarget(e.target.value)}
                >
                    {targets.map((t) => (
                        <option key={t} value={t}>
                            {stepLabels[t]}
                        </option>
                    ))}
                </NativeSelect>
            </FormField>
            {target === 'shipped' && (
                <>
                    <FormField id="carrier" label="Carrier (optional)">
                        <Input id="carrier" name="carrier" />
                    </FormField>
                    <FormField
                        id="tracking_number"
                        label="Tracking number (optional)"
                    >
                        <Input id="tracking_number" name="tracking_number" />
                    </FormField>
                    <FormField
                        id="tracking_url"
                        label="Tracking URL (optional)"
                    >
                        <Input
                            id="tracking_url"
                            name="tracking_url"
                            type="url"
                        />
                    </FormField>
                </>
            )}
            <NotifyField
                key={target}
                defaultChecked={emailed && (emailDefaults[target] ?? true)}
                hint={
                    emailed
                        ? undefined
                        : 'Warehouse steps are internal; ticking sends a short update.'
                }
            />
        </ActionDialog>
    );
}

function OtherDialogs({
    order,
    open,
    emailDefaults,
    onClose,
}: {
    order: AdminOrder;
    open: Exclude<Dialogs, { kind: 'move' }>['kind'] | null;
    emailDefaults: Record<string, boolean>;
    onClose: () => void;
}) {
    const props = (kind: typeof open) => ({
        open: open === kind,
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
                {...props('confirm')}
                title={`Confirm order ${order.number}?`}
                description="The order is accepted and can be picked, packed and shipped."
                form={OrderController.confirm.form(order.number)}
                submit="Confirm order"
            >
                <NotifyField defaultChecked={emailDefaults.confirmed ?? true} />
            </ActionDialog>

            <ActionDialog
                {...props('hold')}
                title={`Put order ${order.number} on hold?`}
                description="The order cannot be picked, packed or shipped until you release it. Payments, refunds and cancellation still work. Customers never see 'on hold'."
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
                description="The order goes back to its previous status."
                form={OrderController.release.form(order.number)}
                submit="Release hold"
            >
                <NotifyField />
            </ActionDialog>

            <ActionDialog
                {...props('delivery_failed')}
                title="Record a failed delivery"
                description="The carrier could not deliver the parcel. You can then retry (in transit or out for delivery), mark it delivered or returned."
                form={OrderController.deliveryFailed.form(order.number)}
                submit="Record failed delivery"
            >
                <FormField id="failed-reason" label="What happened">
                    <Input
                        id="failed-reason"
                        name="reason"
                        placeholder="e.g. Nobody at the address"
                        required
                    />
                </FormField>
                <NotifyField
                    defaultChecked={emailDefaults.delivery_failed ?? true}
                />
            </ActionDialog>

            <ActionDialog
                {...props('returned')}
                title="Mark as returned"
                description="Record that the goods came back. Refund the customer separately if needed."
                form={OrderController.returned.form(order.number)}
                submit="Mark returned"
            >
                <FormField id="return-reason" label="Reason">
                    <Input id="return-reason" name="reason" required />
                </FormField>
                <label className="flex items-center gap-2 text-sm">
                    <input type="hidden" name="restock" value="0" />
                    <Checkbox name="restock" value="1" defaultChecked />
                    Put the items back in stock
                </label>
                <NotifyField defaultChecked={emailDefaults.returned ?? true} />
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
                                    errors.status ??
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
