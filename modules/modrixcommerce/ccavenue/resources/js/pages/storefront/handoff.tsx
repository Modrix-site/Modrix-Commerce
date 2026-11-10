import { Head } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export default function CCAvenueHandoff({
    action,
    access_code,
    encRequest,
    orderNumber,
}: {
    action: string;
    access_code: string;
    encRequest: string;
    orderNumber: string | null;
}) {
    const form = useRef<HTMLFormElement>(null);
    useEffect(() => {
        form.current?.submit();
    }, []);

    return (
        <>
            <Head title="Continue to CCAvenue" />
            <main className="mx-auto grid max-w-md gap-4 px-4 py-16 text-center">
                <h1 className="text-xl font-semibold">Continue to CCAvenue</h1>
                <p className="text-sm text-muted-foreground">
                    Order {orderNumber} is waiting for payment. Enter your
                    payment details on CCAvenue’s secure page.
                </p>
                <form ref={form} method="post" action={action}>
                    <input type="hidden" name="encRequest" value={encRequest} />
                    <input
                        type="hidden"
                        name="access_code"
                        value={access_code}
                    />
                    <button
                        type="submit"
                        className="rounded-md bg-primary px-4 py-2 text-primary-foreground"
                    >
                        Continue to payment
                    </button>
                </form>
            </main>
        </>
    );
}
