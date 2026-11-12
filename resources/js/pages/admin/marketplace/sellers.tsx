import { Form, Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import MarketplaceSellerController from '@/actions/App/Http/Controllers/Admin/MarketplaceSellerController';
import { EmptyState } from '@/components/admin/empty-state';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/admin/marketplace/sellers';
import type { MoneyValue } from '@/types';

type Application = {
    id: number;
    publisher_name: string;
    vendor_slug: string;
    legal_name: string;
    country: string;
    website: string | null;
    payout_email: string;
    support_email: string;
    description: string;
    email: string;
    created_at: string | null;
};

type Seller = {
    id: number;
    name: string;
    slug: string;
    status: 'active' | 'suspended';
    commission_percent: string;
    products_count: number;
    payout_email: string | null;
    balances: MoneyValue[];
    payouts: {
        id: number;
        amount: MoneyValue;
        reference: string;
        paid_at: string;
    }[];
};

type Props = { applications: Application[]; publishers: Seller[] };

export default function MarketplaceSellers({
    applications,
    publishers,
}: Props) {
    return (
        <>
            <Head title="Sellers" />
            <AdminPage>
                <PageHeader
                    title="Sellers"
                    description="Third-party sellers publish under their own vendor name. Their releases go through the same review and signing as yours; the commission is kept on each sale and the rest is owed to them."
                />

                <section className="grid gap-3">
                    <h2 className="text-lg font-semibold">Applications</h2>
                    {applications.length === 0 ? (
                        <EmptyState title="No applications waiting" />
                    ) : (
                        applications.map((a) => (
                            <Card key={a.id} className="py-4">
                                <CardContent className="grid gap-2 text-sm">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-base font-medium">
                                            {a.publisher_name}
                                        </span>
                                        <code>{a.vendor_slug}/…</code>
                                        <span className="text-muted-foreground">
                                            {a.legal_name} · {a.country} ·{' '}
                                            {a.email}
                                        </span>
                                        <div className="ml-auto flex gap-2">
                                            <ActionDialog
                                                trigger="Approve"
                                                title={`Approve ${a.publisher_name}?`}
                                                description="The applicant becomes a seller and can submit releases under this vendor name."
                                                form={MarketplaceSellerController.approve.form(
                                                    a.id,
                                                )}
                                                submit="Approve"
                                            >
                                                {(errors) => (
                                                    <>
                                                        <FormField
                                                            id={`commission-${a.id}`}
                                                            label="Platform commission (%)"
                                                            error={
                                                                errors.commission_percent
                                                            }
                                                        >
                                                            <Input
                                                                id={`commission-${a.id}`}
                                                                name="commission_percent"
                                                                type="number"
                                                                min={0}
                                                                max={100}
                                                                step="0.01"
                                                                defaultValue={
                                                                    30
                                                                }
                                                                required
                                                            />
                                                        </FormField>
                                                        <FormField
                                                            id={`notes-${a.id}`}
                                                            label="Notes to the seller"
                                                            error={errors.notes}
                                                        >
                                                            <Textarea
                                                                id={`notes-${a.id}`}
                                                                name="notes"
                                                                rows={2}
                                                            />
                                                        </FormField>
                                                    </>
                                                )}
                                            </ActionDialog>
                                            <ActionDialog
                                                trigger="Reject"
                                                title={`Reject ${a.publisher_name}?`}
                                                description="The applicant sees your notes and can apply again."
                                                form={MarketplaceSellerController.reject.form(
                                                    a.id,
                                                )}
                                                submit="Reject"
                                                destructive
                                            >
                                                {(errors) => (
                                                    <FormField
                                                        id={`reject-${a.id}`}
                                                        label="Reason"
                                                        error={errors.notes}
                                                    >
                                                        <Textarea
                                                            id={`reject-${a.id}`}
                                                            name="notes"
                                                            rows={3}
                                                            required
                                                        />
                                                    </FormField>
                                                )}
                                            </ActionDialog>
                                        </div>
                                    </div>
                                    <p className="whitespace-pre-line">
                                        {a.description}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Payouts to {a.payout_email} · support{' '}
                                        {a.support_email}
                                        {a.website && ` · ${a.website}`} ·
                                        applied {a.created_at}
                                    </p>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-semibold">Sellers</h2>
                    {publishers.length === 0 ? (
                        <EmptyState title="No sellers yet" />
                    ) : (
                        publishers.map((p) => (
                            <Card key={p.id} className="py-4">
                                <CardContent className="grid gap-2 text-sm">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-base font-medium">
                                            {p.name}
                                        </span>
                                        <code>{p.slug}/…</code>
                                        <Badge
                                            variant={
                                                p.status === 'active'
                                                    ? 'outline'
                                                    : 'destructive'
                                            }
                                        >
                                            {p.status}
                                        </Badge>
                                        <span className="text-muted-foreground">
                                            {p.products_count} products ·
                                            commission {p.commission_percent}%
                                        </span>
                                        <div className="ml-auto flex flex-wrap gap-2">
                                            <ActionDialog
                                                trigger="Commission"
                                                title={`Commission for ${p.name}`}
                                                description="Applies to new sales only; recorded sales keep their commission."
                                                form={MarketplaceSellerController.update.form(
                                                    p.id,
                                                )}
                                                submit="Save"
                                            >
                                                {(errors) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="commission"
                                                        />
                                                        <FormField
                                                            id={`rate-${p.id}`}
                                                            label="Platform commission (%)"
                                                            error={
                                                                errors.commission_percent
                                                            }
                                                        >
                                                            <Input
                                                                id={`rate-${p.id}`}
                                                                name="commission_percent"
                                                                type="number"
                                                                min={0}
                                                                max={100}
                                                                step="0.01"
                                                                defaultValue={
                                                                    p.commission_percent
                                                                }
                                                                required
                                                            />
                                                        </FormField>
                                                    </>
                                                )}
                                            </ActionDialog>
                                            {p.balances
                                                .filter(
                                                    (b) => Number(b.amount) > 0,
                                                )
                                                .map((b) => (
                                                    <ActionDialog
                                                        key={b.currency}
                                                        trigger={`Record payout (${b.currency})`}
                                                        title={`Payout to ${p.name}`}
                                                        description={`Record a transfer you made to ${p.payout_email ?? 'the seller'}. Balance owed: ${b.formatted}.`}
                                                        form={MarketplaceSellerController.payout.form(
                                                            p.id,
                                                        )}
                                                        submit="Record payout"
                                                    >
                                                        {(errors) => (
                                                            <>
                                                                <input
                                                                    type="hidden"
                                                                    name="currency"
                                                                    value={
                                                                        b.currency
                                                                    }
                                                                />
                                                                <FormField
                                                                    id={`amount-${p.id}-${b.currency}`}
                                                                    label="Amount"
                                                                    error={
                                                                        errors.amount
                                                                    }
                                                                >
                                                                    <Input
                                                                        id={`amount-${p.id}-${b.currency}`}
                                                                        name="amount"
                                                                        inputMode="decimal"
                                                                        defaultValue={
                                                                            b.amount
                                                                        }
                                                                        required
                                                                    />
                                                                </FormField>
                                                                <FormField
                                                                    id={`ref-${p.id}-${b.currency}`}
                                                                    label="Transfer reference"
                                                                    error={
                                                                        errors.reference
                                                                    }
                                                                >
                                                                    <Input
                                                                        id={`ref-${p.id}-${b.currency}`}
                                                                        name="reference"
                                                                        required
                                                                    />
                                                                </FormField>
                                                            </>
                                                        )}
                                                    </ActionDialog>
                                                ))}
                                            <ActionDialog
                                                trigger={
                                                    p.status === 'active'
                                                        ? 'Suspend'
                                                        : 'Reactivate'
                                                }
                                                title={
                                                    p.status === 'active'
                                                        ? `Suspend ${p.name}?`
                                                        : `Reactivate ${p.name}?`
                                                }
                                                description={
                                                    p.status === 'active'
                                                        ? 'Their products are hidden and uploads paused. Existing licenses keep working and the balance stays owed.'
                                                        : 'Their published products are listed again.'
                                                }
                                                form={MarketplaceSellerController.update.form(
                                                    p.id,
                                                )}
                                                submit={
                                                    p.status === 'active'
                                                        ? 'Suspend'
                                                        : 'Reactivate'
                                                }
                                                destructive={
                                                    p.status === 'active'
                                                }
                                            >
                                                {(errors) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value={
                                                                p.status ===
                                                                'active'
                                                                    ? 'suspend'
                                                                    : 'reactivate'
                                                            }
                                                        />
                                                        {p.status ===
                                                            'active' && (
                                                            <FormField
                                                                id={`reason-${p.id}`}
                                                                label="Reason"
                                                                error={
                                                                    errors.reason
                                                                }
                                                            >
                                                                <Textarea
                                                                    id={`reason-${p.id}`}
                                                                    name="reason"
                                                                    rows={2}
                                                                    required
                                                                />
                                                            </FormField>
                                                        )}
                                                    </>
                                                )}
                                            </ActionDialog>
                                        </div>
                                    </div>
                                    <p>
                                        Balance owed:{' '}
                                        {p.balances.length === 0
                                            ? 'nothing yet'
                                            : p.balances
                                                  .map((b) => b.formatted)
                                                  .join(', ')}
                                    </p>
                                    {p.payouts.length > 0 && (
                                        <p className="text-xs text-muted-foreground">
                                            Recent payouts:{' '}
                                            {p.payouts
                                                .map(
                                                    (o) =>
                                                        `${o.paid_at} ${o.amount.formatted} (${o.reference})`,
                                                )
                                                .join(' · ')}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        ))
                    )}
                </section>
            </AdminPage>
        </>
    );
}

type FormDef = ReturnType<typeof MarketplaceSellerController.approve.form>;

function ActionDialog({
    trigger,
    title,
    description,
    form,
    submit,
    destructive = false,
    children,
}: {
    trigger: string;
    title: string;
    description: string;
    form: FormDef;
    submit: string;
    destructive?: boolean;
    children: (errors: Record<string, string>) => ReactNode;
}) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    {trigger}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    className="grid gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            {children(errors)}
                            <InputError
                                message={errors.application ?? errors.status}
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
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

MarketplaceSellers.layout = {
    breadcrumbs: [{ title: 'Sellers', href: index() }],
};
