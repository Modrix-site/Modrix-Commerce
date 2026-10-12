import { Form, Head, Link } from '@inertiajs/react';
import { Copy, Download } from 'lucide-react';
import MarketplaceController from '@/actions/App/Http/Controllers/Marketplace/MarketplaceController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { index } from '@/routes/marketplace';
import orders from '@/routes/marketplace/orders';
import type { MoneyValue } from '@/types';

type Props = {
    licenses: {
        id: number;
        product: string;
        product_slug: string;
        type: string;
        key: string | null;
        key_hint: string;
        status: string;
        updates_until: string | null;
        activation_limit: number | null;
        activations: {
            id: number;
            domain: string | null;
            activated_at: string;
            last_seen_at: string | null;
        }[];
    }[];
    orders: {
        number: string;
        status: string;
        total: MoneyValue;
        created_at: string | null;
    }[];
};

export default function MarketplacePortal({ licenses, orders: list }: Props) {
    const [copied, copy] = useClipboard();

    return (
        <>
            <Head title="My licenses" />
            <div className="grid gap-8">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        My licenses
                    </h1>
                    <Button variant="outline" asChild>
                        <Link href={index()}>Browse the marketplace</Link>
                    </Button>
                </div>

                {licenses.length === 0 && (
                    <p className="text-muted-foreground">
                        You have no licenses yet.
                    </p>
                )}

                <ul className="grid gap-4">
                    {licenses.map((l) => (
                        <li
                            key={l.id}
                            className="grid gap-3 rounded-[var(--radius)] border bg-card p-5"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-medium">{l.product}</span>
                                <Badge
                                    variant={
                                        l.status === 'active'
                                            ? 'default'
                                            : 'destructive'
                                    }
                                >
                                    {l.status}
                                </Badge>
                                <span className="ml-auto text-sm text-muted-foreground">
                                    {l.updates_until
                                        ? `Updates until ${l.updates_until}`
                                        : 'Updates included'}
                                </span>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <code className="rounded bg-muted px-2 py-1 text-sm">
                                    {l.key ?? `…${l.key_hint}`}
                                </code>
                                {l.key && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => copy(l.key ?? '')}
                                    >
                                        <Copy />
                                        {copied === l.key
                                            ? 'Copied'
                                            : 'Copy key'}
                                    </Button>
                                )}
                                {l.status === 'active' && (
                                    <Form
                                        {...MarketplaceController.download.form(
                                            l.id,
                                        )}
                                    >
                                        <Button size="sm" variant="outline">
                                            <Download /> Download latest
                                        </Button>
                                    </Form>
                                )}
                            </div>
                            <div className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">
                                    Active on {l.activations.length}
                                    {l.activation_limit
                                        ? ` of ${l.activation_limit}`
                                        : ''}{' '}
                                    {l.activations.length === 1
                                        ? 'store'
                                        : 'stores'}
                                </span>
                                <ul className="grid gap-1">
                                    {l.activations.map((a) => (
                                        <li
                                            key={a.id}
                                            className="flex items-center gap-2"
                                        >
                                            <span>
                                                {a.domain ?? 'Unnamed store'}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                since {a.activated_at}
                                            </span>
                                            <Form
                                                {...MarketplaceController.deactivate.form(
                                                    {
                                                        license: l.id,
                                                        activation: a.id,
                                                    },
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    Deactivate
                                                </Button>
                                            </Form>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </li>
                    ))}
                </ul>

                {list.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="text-lg font-semibold">Orders</h2>
                        <ul className="grid gap-1 text-sm">
                            {list.map((o) => (
                                <li key={o.number}>
                                    <Link
                                        href={orders.show(o.number)}
                                        className="hover:underline"
                                    >
                                        {o.number}
                                    </Link>{' '}
                                    · {o.total.formatted} · {o.status} ·{' '}
                                    {o.created_at}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}
