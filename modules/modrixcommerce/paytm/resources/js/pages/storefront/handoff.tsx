import { Head } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useRef } from 'react';

export default function PaytmHandoff({
    postUrl,
    mid,
    orderId,
    txnToken,
}: {
    postUrl: string;
    mid: string;
    orderId: string;
    txnToken: string;
}) {
    const form = useRef<HTMLFormElement>(null);

    useEffect(() => {
        form.current?.submit();
    }, []);

    return (
        <>
            <Head title="Connecting to Paytm..." />
            <main className="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center gap-4 px-4 py-16 text-center">
                <Loader2 className="size-8 animate-spin text-primary" />
                <h1 className="text-xl font-semibold">Redirecting to Paytm</h1>
                <p className="text-sm text-muted-foreground">
                    Order {orderId} is being securely forwarded. Please wait while we connect you to Paytm to complete your transaction.
                </p>
                <form ref={form} method="post" action={postUrl} className="w-full">
                    <input type="hidden" name="mid" value={mid} />
                    <input type="hidden" name="orderId" value={orderId} />
                    <input type="hidden" name="txnToken" value={txnToken} />
                    <noscript>
                        <button
                            type="submit"
                            className="mt-4 inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            Continue to Paytm
                        </button>
                    </noscript>
                </form>
            </main>
        </>
    );
}
