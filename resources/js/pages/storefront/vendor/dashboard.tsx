import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit } from '@/routes/vendor/products';
import type { MoneyValue } from '@/types';

type Props = {
    publisher: {
        name: string;
        slug: string;
        status: 'active' | 'suspended';
        commission_percent: string;
    };
    totals: {
        currency: string;
        gross: MoneyValue;
        commission: MoneyValue;
        net: MoneyValue;
    }[];
    products: {
        slug: string;
        name: string;
        status: string;
        price: MoneyValue;
        latest_version: string | null;
        pending_count: number;
    }[];
    ledger: {
        id: number;
        type: string;
        description: string;
        currency: string;
        gross: string;
        commission: string;
        net: string;
        created_at: string;
    }[];
    payouts: {
        id: number;
        amount: MoneyValue;
        reference: string;
        paid_at: string;
    }[];
};

export default function VendorDashboard({
    publisher,
    totals,
    products,
    ledger,
    payouts,
}: Props) {
    const active = publisher.status === 'active';

    return (
        <>
            <Head title={`${publisher.name}: seller`} />
            <div className="grid gap-8">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="grid gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {publisher.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Packages: <code>{publisher.slug}/…</code> · platform
                            commission {publisher.commission_percent}%
                        </p>
                    </div>
                    {active && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New product
                            </Link>
                        </Button>
                    )}
                </div>

                {!active && (
                    <p className="rounded-[var(--radius)] border border-destructive p-4 text-sm text-destructive">
                        Your seller account is suspended: your products are
                        hidden and uploads are paused. Existing customers keep
                        their licenses. Contact the marketplace team.
                    </p>
                )}

                <section className="grid gap-3 sm:grid-cols-3">
                    {totals.length === 0 ? (
                        <p className="text-sm text-muted-foreground sm:col-span-3">
                            No sales yet.
                        </p>
                    ) : (
                        totals.map((t) => (
                            <div
                                key={t.currency}
                                className="grid gap-1 rounded-[var(--radius)] border bg-card p-4 sm:col-span-3 sm:grid-cols-3"
                            >
                                <span>
                                    <span className="block text-xs text-muted-foreground">
                                        Sales
                                    </span>
                                    {t.gross.formatted}
                                </span>
                                <span>
                                    <span className="block text-xs text-muted-foreground">
                                        Commission
                                    </span>
                                    {t.commission.formatted}
                                </span>
                                <span>
                                    <span className="block text-xs text-muted-foreground">
                                        Balance owed to you
                                    </span>
                                    <strong>{t.net.formatted}</strong>
                                </span>
                            </div>
                        ))
                    )}
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-semibold">Products</h2>
                    {products.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Create a product, upload a release, and it is listed
                            once the marketplace team approves it.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-[var(--radius)] border">
                            {products.map((p) => (
                                <li
                                    key={p.slug}
                                    className="flex flex-wrap items-center gap-2 px-4 py-3"
                                >
                                    <Link
                                        href={edit(p.slug)}
                                        className="font-medium hover:underline"
                                    >
                                        {p.name}
                                    </Link>
                                    <Badge variant="outline">{p.status}</Badge>
                                    {p.pending_count > 0 && (
                                        <Badge>
                                            {p.pending_count} in review
                                        </Badge>
                                    )}
                                    <span className="ml-auto text-sm text-muted-foreground">
                                        {p.latest_version
                                            ? `v${p.latest_version} · `
                                            : ''}
                                        {p.price.formatted}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-semibold">Statement</h2>
                    {ledger.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No entries yet.
                        </p>
                    ) : (
                        <div
                            className="overflow-x-auto rounded-[var(--radius)] border"
                            tabIndex={0}
                        >
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="p-2 font-medium">
                                            Date
                                        </th>
                                        <th className="p-2 font-medium">
                                            Entry
                                        </th>
                                        <th className="p-2 text-right font-medium">
                                            Gross
                                        </th>
                                        <th className="p-2 text-right font-medium">
                                            Commission
                                        </th>
                                        <th className="p-2 text-right font-medium">
                                            Net
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ledger.map((e) => (
                                        <tr key={e.id} className="border-t">
                                            <td className="p-2 whitespace-nowrap">
                                                {e.created_at}
                                            </td>
                                            <td className="p-2">
                                                {e.description}
                                            </td>
                                            <td className="p-2 text-right font-mono">
                                                {Number(e.gross).toFixed(2)}
                                            </td>
                                            <td className="p-2 text-right font-mono">
                                                {Number(e.commission).toFixed(
                                                    2,
                                                )}
                                            </td>
                                            <td className="p-2 text-right font-mono">
                                                {Number(e.net).toFixed(2)}{' '}
                                                {e.currency}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {payouts.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="text-lg font-semibold">Payouts</h2>
                        <ul className="grid gap-1 text-sm">
                            {payouts.map((p) => (
                                <li key={p.id}>
                                    {p.paid_at} · {p.amount.formatted} ·{' '}
                                    {p.reference}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}
