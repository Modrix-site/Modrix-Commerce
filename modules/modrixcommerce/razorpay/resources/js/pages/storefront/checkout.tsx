import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type CheckoutOptions = Record<string, unknown> & {
    modal?: { confirm_close?: boolean; ondismiss?: () => void };
};
type CheckoutInstance = { open: () => void; close: () => void };
type CheckoutConstructor = new (options: CheckoutOptions) => CheckoutInstance;

declare global {
    interface Window {
        Razorpay?: CheckoutConstructor;
    }
}

let sdkPromise: Promise<CheckoutConstructor> | null = null;
function loadCheckout(): Promise<CheckoutConstructor> {
    if (window.Razorpay) return Promise.resolve(window.Razorpay);
    if (sdkPromise) return sdkPromise;
    sdkPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.async = true;
        const fail = () => {
            clearTimeout(timer);
            sdkPromise = null;
            script.remove();
            reject(new Error('Checkout unavailable'));
        };
        const timer = window.setTimeout(fail, 15000);
        script.onload = () => {
            clearTimeout(timer);
            if (window.Razorpay) resolve(window.Razorpay);
            else fail();
        };
        script.onerror = fail;
        document.head.appendChild(script);
    });
    return sdkPromise;
}

export default function RazorpayCheckout({
    options,
    orderNumber,
    amount,
    autoOpen,
    orderUrl,
}: {
    options: CheckoutOptions;
    orderNumber: string;
    amount: string;
    autoOpen: boolean;
    orderUrl: string;
}) {
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState('');
    const busy = useRef(false);
    const mounted = useRef(true);
    const instance = useRef<CheckoutInstance | null>(null);
    const open = useCallback(async () => {
        if (busy.current) return;
        busy.current = true;
        setLoading(true);
        setMessage('');
        try {
            const Checkout = await loadCheckout();
            if (!mounted.current) return;
            instance.current = new Checkout({
                ...options,
                modal: {
                    ...options.modal,
                    ondismiss: () => {
                        busy.current = false;
                        if (mounted.current)
                            setMessage(
                                'The payment window closed. You can reopen it or return to your order.',
                            );
                    },
                },
            });
            instance.current.open();
        } catch {
            busy.current = false;
            if (mounted.current)
                setMessage(
                    'Razorpay could not open. Please try again or contact the store.',
                );
        } finally {
            if (mounted.current) setLoading(false);
        }
    }, [options]);
    useEffect(() => {
        mounted.current = true;
        return () => {
            mounted.current = false;
            instance.current?.close();
        };
    }, []);
    useEffect(() => {
        if (!autoOpen) return;
        const timer = window.setTimeout(() => void open(), 0);
        return () => window.clearTimeout(timer);
    }, [autoOpen, open]);

    return (
        <>
            <Head title="Pay with Razorpay" />
            <main className="mx-auto grid max-w-md gap-5 px-4 py-16 text-center">
                <h1 className="text-2xl font-semibold">Pay with Razorpay</h1>
                <p className="text-sm text-muted-foreground">
                    Order {orderNumber}
                </p>
                <p className="text-2xl font-semibold">{amount}</p>
                <p className="text-sm text-muted-foreground">
                    Enter your payment details in Razorpay’s secure checkout.
                    Your order will be confirmed after payment is captured.
                </p>
                <button
                    type="button"
                    disabled={loading}
                    onClick={() => void open()}
                    className="rounded-md bg-primary px-4 py-3 font-medium text-primary-foreground disabled:opacity-50"
                >
                    {loading ? 'Opening payment…' : 'Pay securely'}
                </button>
                {message && (
                    <p role="status" className="text-sm text-muted-foreground">
                        {message}
                    </p>
                )}
                <a href={orderUrl} className="text-sm underline">
                    Return to order
                </a>
            </main>
        </>
    );
}
