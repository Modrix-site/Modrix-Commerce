import { Head, Link, router } from '@inertiajs/react';
import { CircleCheck, Clock } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, portal } from '@/routes/marketplace';
import type { MoneyValue } from '@/types';

type Props = {
    order: {
        number: string;
        status: 'pending' | 'paid' | 'refunded' | 'cancelled';
        total: MoneyValue;
        paid_at: string | null;
        items: { name: string; type: string }[];
    };
};

export default function MarketplaceOrder({ order }: Props) {
    const [polls, setPolls] = useState(0);

    // Payment is confirmed by Stripe's webhook, usually within seconds.
    useEffect(() => {
        if (order.status !== 'pending' || polls >= 10) {
            return;
        }

        const timer = window.setTimeout(
            () =>
                router.reload({
                    only: ['order'],
                    onFinish: () => setPolls((n) => n + 1),
                }),
            3000,
        );

        return () => window.clearTimeout(timer);
    }, [order.status, polls]);

    return (
        <>
            <Head title={`Order ${order.number}`} />
            <div className="mx-auto grid max-w-xl gap-4">
                {order.status === 'paid' ? (
                    <CircleCheck
                        className="size-8 text-green-600"
                        aria-hidden
                    />
                ) : polls < 10 && order.status === 'pending' ? (
                    <Spinner className="size-8" />
                ) : (
                    <Clock
                        className="size-8 text-muted-foreground"
                        aria-hidden
                    />
                )}
                <h1 className="text-2xl font-semibold tracking-tight">
                    {order.status === 'paid'
                        ? 'Thank you! Your license is ready.'
                        : order.status === 'pending'
                          ? 'Confirming your payment…'
                          : `Order ${order.status}`}
                </h1>
                <p className="text-muted-foreground">
                    Order {order.number} · {order.total.formatted} ·{' '}
                    {order.items.map((i) => i.name).join(', ')}
                </p>
                {order.status === 'paid' && (
                    <p className="text-sm">
                        Copy your license key from your account and enter it in
                        your store under Admin → Extensions → Marketplace.
                    </p>
                )}
                <div className="flex gap-2">
                    <Button asChild>
                        <Link href={portal()}>My licenses</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={index()}>Back to the marketplace</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
