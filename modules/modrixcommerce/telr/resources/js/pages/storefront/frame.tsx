import { Head, Link } from '@inertiajs/react';

/**
 * Telr's payment form, framed inside the store. The iframe only loads
 * https://secure.telr.com. framed=3 lets Telr leave the frame when the
 * payment finishes and send the customer back to this store.
 */
export default function TelrFrame({
    orderNumber,
    frameUrl,
    cancelUrl,
}: {
    orderNumber: string | null;
    frameUrl: string;
    cancelUrl: string;
}) {
    return (
        <>
            <Head title="Pay securely" />
            <div className="mx-auto grid w-full max-w-3xl gap-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Pay securely
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {orderNumber ? `Order ${orderNumber}. ` : ''}
                        Card details are entered on Telr&apos;s secure form and
                        are not stored by this store.
                    </p>
                </div>
                <div className="overflow-x-auto rounded-lg border bg-card">
                    <iframe
                        id="telr"
                        title="Telr secure payment"
                        src={frameUrl}
                        className="h-[640px] min-h-[550px] w-full min-w-[600px] border-0"
                        sandbox="allow-forms allow-modals allow-popups-to-escape-sandbox allow-popups allow-scripts allow-top-navigation allow-same-origin"
                    />
                </div>
                <p className="text-sm text-muted-foreground">
                    <Link href={cancelUrl} className="underline">
                        Return to your order
                    </Link>
                </p>
            </div>
        </>
    );
}
