import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type CheckoutWebComponents = (options: {
    publicKey: string;
    environment: 'sandbox' | 'production';
    paymentSession: Record<string, unknown>;
    onPaymentCompleted: () => void;
}) => Promise<{
    create: (component: 'flow') => { mount: (selector: string) => void };
}>;

/**
 * Checkout.com Flow, mounted on this store. The script and the payment form
 * come from Checkout.com. Card fields are entered there.
 */
export default function CheckoutComFlow({
    orderNumber,
    publicKey,
    environment,
    paymentSession,
    returnUrl,
    cancelUrl,
}: {
    orderNumber: string | null;
    publicKey: string;
    environment: 'sandbox' | 'production';
    paymentSession: Record<string, unknown>;
    returnUrl: string;
    cancelUrl: string;
}) {
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let cancelled = false;
        const script = document.createElement('script');
        script.src = 'https://checkout-web-components.checkout.com/index.js';
        script.async = true;
        script.onload = () => {
            const factory = (
                window as Window & {
                    CheckoutWebComponents?: CheckoutWebComponents;
                }
            ).CheckoutWebComponents;

            if (cancelled || !factory) {
                setFailed(true);

                return;
            }

            void factory({
                publicKey,
                environment,
                paymentSession,
                onPaymentCompleted: () => {
                    window.location.assign(returnUrl);
                },
            })
                .then((checkout) => {
                    if (!cancelled) {
                        checkout.create('flow').mount('#checkout-com-flow');
                    }
                })
                .catch(() => {
                    if (!cancelled) {
                        setFailed(true);
                    }
                });
        };
        script.onerror = () => setFailed(true);
        document.body.appendChild(script);

        return () => {
            cancelled = true;
            script.remove();
        };
    }, [environment, paymentSession, publicKey, returnUrl]);

    return (
        <>
            <Head title="Pay securely" />
            <div className="mx-auto grid w-full max-w-xl gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Pay securely
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {orderNumber ? `Order ${orderNumber}. ` : ''}
                        Card details are entered in Checkout.com&apos;s secure
                        form and are not stored by this store.
                    </p>
                </div>
                {failed ? (
                    <p className="text-sm text-destructive">
                        Checkout.com&apos;s payment form could not be loaded.
                        Try again, or return to your order.
                    </p>
                ) : (
                    <div
                        id="checkout-com-flow"
                        className="min-h-80 rounded-lg border bg-card p-4"
                    />
                )}
                <Link
                    href={cancelUrl}
                    className="text-sm text-muted-foreground underline"
                >
                    Cancel and return to your order
                </Link>
            </div>
        </>
    );
}
