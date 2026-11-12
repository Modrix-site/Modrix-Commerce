import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ShieldCheck, Star } from 'lucide-react';
import MarketplaceController from '@/actions/App/Http/Controllers/Marketplace/MarketplaceController';
import MarketplaceReviewController from '@/actions/App/Http/Controllers/Marketplace/MarketplaceReviewController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { login } from '@/routes';
import { portal } from '@/routes/marketplace';
import type { MarketplaceCard } from './index';

type Props = {
    product: MarketplaceCard & {
        description: string | null;
        documentation_url: string | null;
        publisher: string;
        support_email: string | null;
        support_url: string | null;
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
    reviews: Reviews;
};

type Reviews = {
    average: number | null;
    count: number;
    items: {
        id: number;
        author: string;
        rating: number;
        title: string | null;
        body: string;
        created_at: string | null;
    }[];
    mine: 'pending' | 'approved' | 'rejected' | null;
    canReview: boolean;
};

function Stars({ rating }: { rating: number }) {
    return (
        <span className="inline-flex" aria-label={`${rating} out of 5`}>
            {[1, 2, 3, 4, 5].map((n) => (
                <Star
                    key={n}
                    aria-hidden
                    className={`size-4 ${n <= rating ? 'fill-current text-amber-500' : 'text-muted-foreground'}`}
                />
            ))}
        </span>
    );
}

function ReviewsSection({ slug, reviews }: { slug: string; reviews: Reviews }) {
    return (
        <section className="grid gap-3 border-t pt-6">
            <h2 className="flex flex-wrap items-center gap-2 text-lg font-semibold">
                Reviews
                {reviews.average !== null && (
                    <span className="flex items-center gap-1 text-sm font-normal text-muted-foreground">
                        <Stars rating={Math.round(reviews.average)} />{' '}
                        {reviews.average} ({reviews.count})
                    </span>
                )}
            </h2>
            {reviews.items.length === 0 && (
                <p className="text-sm text-muted-foreground">No reviews yet.</p>
            )}
            <ul className="grid gap-4">
                {reviews.items.map((r) => (
                    <li key={r.id} className="grid gap-1">
                        <span className="flex flex-wrap items-center gap-2 text-sm">
                            <Stars rating={r.rating} />
                            <span className="font-medium">{r.title}</span>
                            <span className="text-muted-foreground">
                                {r.author} · {r.created_at}
                            </span>
                        </span>
                        <p className="text-sm whitespace-pre-line">{r.body}</p>
                    </li>
                ))}
            </ul>
            {reviews.mine === 'pending' && (
                <p className="text-sm text-muted-foreground">
                    Your review is waiting for moderation.
                </p>
            )}
            {reviews.canReview && (
                <Form
                    {...MarketplaceReviewController.store.form(slug)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-3 rounded-[var(--radius)] border bg-card p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <p className="font-medium">Review this product</p>
                            <label className="grid gap-1 text-sm">
                                Rating
                                <select
                                    name="rating"
                                    defaultValue="5"
                                    className="h-9 rounded-md border bg-transparent px-2"
                                >
                                    {[5, 4, 3, 2, 1].map((n) => (
                                        <option key={n} value={n}>
                                            {n} / 5
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="grid gap-1 text-sm">
                                Title
                                <Input name="title" maxLength={120} />
                            </label>
                            <label className="grid gap-1 text-sm">
                                Your experience
                                <Textarea
                                    name="body"
                                    rows={4}
                                    required
                                    minLength={20}
                                />
                            </label>
                            <InputError
                                message={errors.body ?? errors.rating}
                            />
                            <div>
                                <Button type="submit" disabled={processing}>
                                    Send review
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            )}
        </section>
    );
}

export default function MarketplaceProduct({ product, owned, reviews }: Props) {
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
                    <ReviewsSection slug={product.slug} reviews={reviews} />
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
                        <li>By {product.publisher}</li>
                        <li className="flex items-center gap-1">
                            <ShieldCheck className="size-4" aria-hidden />{' '}
                            Reviewed and signed by the marketplace
                        </li>
                        {product.support_url && (
                            <li>
                                <a
                                    href={product.support_url}
                                    className="underline"
                                    rel="noopener noreferrer"
                                >
                                    Support
                                </a>
                            </li>
                        )}
                        {product.support_email && (
                            <li>
                                Help:{' '}
                                <a
                                    href={`mailto:${product.support_email}`}
                                    className="underline"
                                >
                                    {product.support_email}
                                </a>
                            </li>
                        )}
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
