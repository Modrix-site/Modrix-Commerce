import { Head, Link, usePage } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { OrderView } from '@/components/storefront/order-view';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { orders } from '@/routes/account';
import type { OrderDetail } from '@/types';

export default function OrderComplete({
    order,
    paymentInstructions,
}: {
    order: OrderDetail;
    paymentInstructions: string | null;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={`Order ${order.number}`} />
            <div className="mb-8 grid gap-2">
                <CircleCheck className="size-8 text-green-600" aria-hidden />
                <h1 className="text-2xl font-semibold tracking-tight">
                    Thank you for your order
                </h1>
                <p className="text-muted-foreground">
                    Order{' '}
                    <strong className="text-foreground">{order.number}</strong>{' '}
                    has been placed. A confirmation was sent to {order.email}.
                </p>
            </div>
            {order.payment_status === 'unpaid' && paymentInstructions && (
                <Alert className="mb-8">
                    <AlertTitle>How to pay</AlertTitle>
                    <AlertDescription>{paymentInstructions}</AlertDescription>
                </Alert>
            )}
            <OrderView order={order} />
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
