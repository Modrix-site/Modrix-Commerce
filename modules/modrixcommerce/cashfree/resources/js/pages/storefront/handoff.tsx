import { Head } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

declare global {
    interface Window {
        Cashfree?: (config: { mode: 'sandbox' | 'production' }) => {
            checkout: (options: {
                paymentSessionId: string;
                redirectTarget: '_self' | '_blank' | 'modal';
            }) => Promise<{ error?: { message: string }; redirect?: boolean }>;
        };
    }
}

export default function CashfreeHandoff({
    paymentSessionId,
    mode,
    orderId,
    orderNumber,
    amount,
    currency,
}: {
    paymentSessionId: string;
    mode: 'sandbox' | 'production';
    orderId: string;
    orderNumber: string;
    amount: string;
    currency: string;
}) {
    const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const checkoutTriggered = useRef(false);

    const triggerCheckout = () => {
        if (!window.Cashfree) {
            setStatus('error');
            setErrorMessage('Cashfree payments SDK could not be loaded. Please refresh or check your internet connection.');
            return;
        }

        try {
            const cashfree = window.Cashfree({ mode });
            cashfree
                .checkout({
                    paymentSessionId,
                    redirectTarget: '_self',
                })
                .then((result) => {
                    if (result?.error) {
                        setStatus('error');
                        setErrorMessage(result.error.message || 'Payment initiation failed.');
                    }
                })
                .catch((err) => {
                    setStatus('error');
                    setErrorMessage(err?.message || 'Payment could not be started.');
                });
        } catch (e: any) {
            setStatus('error');
            setErrorMessage(e?.message || 'Failed to initialize Cashfree.');
        }
    };

    useEffect(() => {
        if (checkoutTriggered.current) return;

        // Check if script already loaded
        if (window.Cashfree) {
            checkoutTriggered.current = true;
            triggerCheckout();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://sdk.cashfree.com/js/v3/cashfree.js';
        script.async = true;
        script.onload = () => {
            checkoutTriggered.current = true;
            triggerCheckout();
        };
        script.onerror = () => {
            setStatus('error');
            setErrorMessage('Failed to load Cashfree payment SDK.');
        };

        document.body.appendChild(script);

        return () => {
            if (document.body.contains(script)) {
                document.body.removeChild(script);
            }
        };
    }, [paymentSessionId, mode]);

    return (
        <>
            <Head title="Connecting to Cashfree Payments" />
            <main className="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center gap-4 px-4 py-16 text-center">
                {status !== 'error' ? (
                    <>
                        <Loader2 className="size-8 animate-spin text-primary" />
                        <h1 className="text-xl font-semibold">Connecting to Cashfree Payments</h1>
                        <p className="text-sm text-muted-foreground">
                            Order {orderNumber} ({amount} {currency}) is ready. Launching secure payment via UPI, Cards, Net Banking, or Wallets...
                        </p>
                    </>
                ) : (
                    <>
                        <h1 className="text-xl font-semibold text-destructive">Payment Setup Error</h1>
                        <p className="text-sm text-muted-foreground">{errorMessage}</p>
                        <button
                            type="button"
                            onClick={triggerCheckout}
                            className="mt-4 inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            Try Again
                        </button>
                    </>
                )}
            </main>
        </>
    );
}
