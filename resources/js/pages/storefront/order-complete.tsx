import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Clock } from 'lucide-react';
import { useEffect, useState } from 'react';
import CheckoutController from '@/actions/App/Http/Controllers/Storefront/CheckoutController';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import type { SlotItem } from '@/components/extensions/extension-slot';
import { OrderView } from '@/components/storefront/order-view';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { orders } from '@/routes/account';
import type { OrderDetail } from '@/types';

type OnlinePayment = {
    state: 'paid' | 'confirming' | 'processing' | 'awaiting' | 'cancelled';
    method: string;
};

/** How long to keep checking for the provider's confirmation (webhook) before giving up. */
const MAX_POLLS = 10;

export default function OrderComplete({
    order,
    paymentInstructions,
    onlinePayment,
    slots,
}: {
    order: OrderDetail;
    paymentInstructions: string | null;
    onlinePayment: OnlinePayment | null;
    slots: SlotItem[];
}) {
    const { auth } = usePage().props;
    const [polls, setPolls] = useState(0);
    const confirming = onlinePayment?.state === 'confirming';

    // The provider confirms payments by webhook, usually within seconds.
    useEffect(() => {
        if (!confirming || polls >= MAX_POLLS) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.reload({
                only: ['order', 'onlinePayment'],
                onFinish: () => setPolls((n) => n + 1),
            });
        }, 3000);

        return () => window.clearTimeout(timer);
    }, [confirming, polls]);

    const unpaidOnline =
        onlinePayment !== null && onlinePayment.state !== 'paid';

    return (
        <>
            <Head title={`Order ${order.number}`} />
            {unpaidOnline ? (
                <OnlinePaymentStatus
                    order={order}
                    payment={onlinePayment}
                    gaveUp={polls >= MAX_POLLS}
                />
            ) : (
                <div className="mb-8 grid gap-2">
                    <CircleCheck
                        className="size-8 text-green-600"
                        aria-hidden
                    />
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Thank you for your order
                    </h1>
                    <p className="text-muted-foreground">
                        Order{' '}
                        <strong className="text-foreground">
                            {order.number}
                        </strong>{' '}
                        has been placed. A confirmation was sent to{' '}
                        {order.email}.
                    </p>
                </div>
            )}
            {order.payment_status === 'unpaid' && paymentInstructions && (
                <Alert className="mb-8">
                    <AlertTitle>How to pay</AlertTitle>
                    <AlertDescription>{paymentInstructions}</AlertDescription>
                </Alert>
            )}
            <OrderView order={order} />
            <ExtensionSlot items={slots} className="mt-8 grid gap-6" />
            <div className="mt-8 flex gap-2">
                <Button asChild>
                    <Link href={home()}>Continue shopping</Link>
                </Button>
                {auth.user && (
                    <Button variant="outline" asChild>
                        <Link href={orders()}>View my orders</Link>
                    </Button>
                )}
            </div>
        </>
    );
}

function OnlinePaymentStatus({
    order,
    payment,
    gaveUp,
}: {
    order: OrderDetail;
    payment: OnlinePayment;
    gaveUp: boolean;
}) {
    if (payment.state === 'confirming' && !gaveUp) {
        return (
            <div className="mb-8 grid gap-2" role="status">
                <Spinner className="size-8" />
                <h1 className="text-2xl font-semibold tracking-tight">
                    Confirming your payment…
                </h1>
                <p className="text-muted-foreground">
                    We are waiting for {payment.method} to confirm the payment
                    for order {order.number}. This usually takes a few seconds.
                </p>
            </div>
        );
    }

    if (payment.state === 'processing' || payment.state === 'confirming') {
        return (
            <div className="mb-8 grid gap-2" role="status">
                <Clock className="size-8 text-muted-foreground" aria-hidden />
                <h1 className="text-2xl font-semibold tracking-tight">
                    Your payment is being processed
                </h1>
                <p className="text-muted-foreground">
                    We will email {order.email} as soon as the payment for order{' '}
                    {order.number} is confirmed. You do not need to pay again.
                </p>
            </div>
        );
    }

    if (payment.state === 'cancelled') {
        return (
            <div className="mb-8 grid gap-2">
                <CircleAlert
                    className="size-8 text-muted-foreground"
                    aria-hidden
                />
                <h1 className="text-2xl font-semibold tracking-tight">
                    This order was cancelled
                </h1>
                <p className="text-muted-foreground">
                    Order {order.number} was cancelled because the payment was
                    not completed. You have not been charged.
                </p>
            </div>
        );
    }

    return (
        <div className="mb-8 grid gap-3">
            <CircleAlert className="size-8 text-amber-600" aria-hidden />
            <h1 className="text-2xl font-semibold tracking-tight">
                Complete your payment
            </h1>
            <p className="text-muted-foreground">
                Order {order.number} is reserved, but the payment has not been
                completed yet. Your items are held for a limited time.
            </p>
            <Form {...CheckoutController.pay.form(order.number)}>
                {({ processing }) => (
                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        Pay {order.grand_total.formatted} with {payment.method}
                    </Button>
                )}
            </Form>
        </div>
    );
}
