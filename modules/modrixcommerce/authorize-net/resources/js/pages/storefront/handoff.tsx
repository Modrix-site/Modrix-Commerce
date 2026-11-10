import { Head } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/**
 * Posts the Accept Hosted token to Authorize.Net. The payment form itself
 * is on Authorize.Net, so card fields are never entered here.
 */
export default function AuthorizeNetHandoff({
    orderNumber,
    action,
    token,
}: {
    orderNumber: string | null;
    action: string;
    token: string;
}) {
    const form = useRef<HTMLFormElement>(null);

    useEffect(() => {
        form.current?.submit();
    }, []);

    return (
        <>
            <Head title="Authorize.Net" />
            <div className="mx-auto grid max-w-md gap-4 px-4 py-16 text-center">
                <h1 className="text-xl font-semibold">
                    Continue to Authorize.Net
                </h1>
                <p className="text-sm text-muted-foreground">
                    {orderNumber
                        ? `Order ${orderNumber} is waiting for payment. `
                        : ''}
                    Card details are entered on Authorize.Net and are never
                    stored by this store.
                </p>
                <form ref={form} method="post" action={action}>
                    <input type="hidden" name="token" value={token} />
                    <button
                        type="submit"
                        className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm text-primary-foreground"
                    >
                        Continue to payment
                    </button>
                </form>
            </div>
        </>
    );
}
