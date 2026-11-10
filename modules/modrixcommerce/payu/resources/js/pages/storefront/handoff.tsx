import { Head } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect, useRef } from 'react';

export default function PayuHandoff({
    action,
    fields,
    orderNumber,
}: {
    action: string;
    fields: Record<string, string>;
    orderNumber: string | null;
}) {
    const form = useRef<HTMLFormElement>(null);

    useEffect(() => {
        form.current?.submit();
    }, []);

    return (
        <>
            <Head title="Connecting to PayU..." />
            <main className="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center gap-4 px-4 py-16 text-center">
                <Loader2 className="size-8 animate-spin text-primary" />
                <h1 className="text-xl font-semibold">Redirecting to PayU</h1>
                <p className="text-sm text-muted-foreground">
                    Order {orderNumber} is being forwarded. Please wait while we transfer you securely to PayU to complete your payment.
                </p>
                <form ref={form} method="post" action={action} className="w-full">
                    {Object.entries(fields).map(([name, value]) => (
                        <input key={name} type="hidden" name={name} value={value} />
                    ))}
                    <noscript>
                        <button
                            type="submit"
                            className="mt-4 inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            Continue to PayU
                        </button>
                    </noscript>
                </form>
            </main>
        </>
    );
}
