import { Head } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect } from 'react';

export default function EasebuzzHandoff({
    checkoutUrl,
    orderNumber,
}: {
    checkoutUrl: string;
    txnid: string;
    orderNumber: string | null;
}) {
    useEffect(() => {
        if (checkoutUrl) {
            window.location.href = checkoutUrl;
        }
    }, [checkoutUrl]);

    return (
        <>
            <Head title="Connecting to Easebuzz..." />
            <main className="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center gap-4 px-4 py-16 text-center">
                <Loader2 className="size-8 animate-spin text-primary" />
                <h1 className="text-xl font-semibold">
                    Redirecting to Easebuzz
                </h1>
                <p className="text-sm text-muted-foreground">
                    Order {orderNumber} is being forwarded. Please wait while we
                    transfer you securely to Easebuzz to complete your payment.
                </p>
                <a
                    href={checkoutUrl}
                    className="mt-4 inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Continue to Easebuzz
                </a>
            </main>
        </>
    );
}
