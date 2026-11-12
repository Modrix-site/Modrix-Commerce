import { Head, Link } from '@inertiajs/react';
import { Palette, Puzzle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { product as productRoute } from '@/routes/marketplace';
import type { MoneyValue } from '@/types';

export type MarketplaceCard = {
    slug: string;
    name: string;
    summary: string | null;
    type: 'module' | 'theme';
    price: MoneyValue;
    free: boolean;
    latest_version: string;
    platform_version: string;
};

export default function MarketplaceIndex({
    products,
}: {
    products: MarketplaceCard[];
}) {
    return (
        <>
            <Head title="Marketplace" />
            <section className="mb-8 grid gap-2">
                <h1 className="text-3xl font-semibold tracking-tight">
                    Extensions for Modrix Commerce
                </h1>
                <p className="max-w-2xl text-muted-foreground">
                    Official modules and themes, reviewed and signed by the
                    publisher. Licenses include updates for the stated period;
                    the open-source core never depends on them.
                </p>
            </section>
            {products.length === 0 ? (
                <p className="text-muted-foreground">
                    No products are available yet.
                </p>
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {products.map((p) => (
                        <li key={p.slug}>
                            <Link
                                href={productRoute(p.slug)}
                                className="grid h-full content-start gap-2 rounded-[var(--radius)] border bg-card p-5 transition-colors hover:bg-muted/50"
                            >
                                <div className="flex items-center gap-2">
                                    {p.type === 'theme' ? (
                                        <Palette
                                            className="size-5 text-muted-foreground"
                                            aria-hidden
                                        />
                                    ) : (
                                        <Puzzle
                                            className="size-5 text-muted-foreground"
                                            aria-hidden
                                        />
                                    )}
                                    <span className="font-medium">
                                        {p.name}
                                    </span>
                                    <Badge
                                        variant="outline"
                                        className="ml-auto"
                                    >
                                        {p.free ? 'Free' : p.price.formatted}
                                    </Badge>
                                </div>
                                {p.summary && (
                                    <p className="text-sm text-muted-foreground">
                                        {p.summary}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    v{p.latest_version} · {p.type} · platform{' '}
                                    {p.platform_version}
                                </p>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </>
    );
}
