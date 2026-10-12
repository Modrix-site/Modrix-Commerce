import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import MarketplaceController from '@/actions/App/Http/Controllers/Marketplace/MarketplaceController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { portal } from '@/routes/marketplace';
import type { MarketplaceCard } from './index';

type Props = {
    product: MarketplaceCard & {
        description: string | null;
        documentation_url: string | null;
        activation_limit: number | null;
        updates_months: number;
        releases: {
            version: string;
            platform_version: string;
            changelog: string | null;
            published_at: string | null;
            sha256: string;
        }[];
    };
    owned: boolean;
};

export default function MarketplaceProduct({ product, owned }: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={product.name} />
            <div className="grid gap-10 md:grid-cols-[2fr_1fr]">
                <div className="grid content-start gap-4">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {product.name}
                    </h1>
                    {product.summary && (
                        <p className="text-lg text-muted-foreground">
                            {product.summary}
                        </p>
                    )}
                    {product.description && (
                        <p className="whitespace-pre-line">
                            {product.description}
                        </p>
                    )}
                    {product.documentation_url && (
                        <a
                            href={product.documentation_url}
                            className="text-sm underline"
                            rel="noopener noreferrer"
                        >
                            Documentation
                        </a>
                    )}
                    <section className="grid gap-3 border-t pt-6">
                        <h2 className="text-lg font-semibold">Releases</h2>
                        <ul className="grid gap-4">
                            {product.releases.map((r) => (
                                <li key={r.version} className="grid gap-1">
                                    <span className="font-medium">
                                        v{r.version}{' '}
                                        <span className="text-sm font-normal text-muted-foreground">
                                            {r.published_at} · platform{' '}
                                            {r.platform_version}
                                        </span>
                                    </span>
                                    {r.changelog && (
                                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                                            {r.changelog}
                                        </p>
                                    )}
                                    <code className="truncate text-xs text-muted-foreground">
                                        SHA-256 {r.sha256}
                                    </code>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
                <aside className="grid content-start gap-4 rounded-[var(--radius)] border bg-card p-5">
                    <span className="text-2xl font-semibold">
                        {product.free ? 'Free' : product.price.formatted}
                    </span>
                    <ul className="grid gap-1 text-sm text-muted-foreground">
                        <li>
                            {product.updates_months > 0
                                ? `${product.updates_months} months of updates`
                                : 'Updates for as long as the product is offered'}
                        </li>
                        <li>
                            {product.activation_limit
                                ? `Use on up to ${product.activation_limit} stores`
                                : 'Use on any number of stores'}
                        </li>
                        <li className="flex items-center gap-1">
                            <ShieldCheck className="size-4" aria-hidden />{' '}
                            Signed by the publisher
                        </li>
                    </ul>
                    {owned ? (
                        <Button variant="outline" asChild>
                            <Link href={portal()}>
                                You own this: view license
                            </Link>
                        </Button>
                    ) : auth.user ? (
                        <Form {...MarketplaceController.buy.form(product.slug)}>
                            {({ processing, errors }) => (
                                <>
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {product.free
                                            ? 'Get license'
                                            : 'Buy license'}
                                    </Button>
                                    <InputError message={errors.product} />
                                </>
                            )}
                        </Form>
                    ) : (
                        <Button asChild>
                            <Link href={login()}>Sign in to buy</Link>
                        </Button>
                    )}
                </aside>
            </div>
        </>
    );
}
