import { Form, Link } from '@inertiajs/react';
import { BadgeCheck, Star } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Review = {
    id: number;
    author: string;
    rating: number;
    title: string | null;
    body: string;
    verified: boolean;
    date: string | null;
};

type Props = {
    count: number;
    average: string | null;
    reviews: Review[];
    own: { status: string; rating: number } | null;
    canReview: boolean;
    reason: 'login' | 'purchase' | null;
    minLength: number;
    submitUrl: string;
    loginUrl: string;
};

export default function ProductReviews({
    count,
    average,
    reviews,
    own,
    canReview,
    reason,
    minLength,
    submitUrl,
    loginUrl,
}: Props) {
    return (
        <section
            className="grid gap-6 border-t pt-8"
            aria-labelledby="reviews-heading"
        >
            <div className="flex flex-wrap items-baseline gap-3">
                <h2 id="reviews-heading" className="text-xl font-semibold">
                    Customer reviews
                </h2>
                {average !== null && (
                    <span className="flex items-center gap-1 text-sm text-muted-foreground">
                        <Stars rating={Math.round(Number(average))} />
                        {average} out of 5 · {count}{' '}
                        {count === 1 ? 'review' : 'reviews'}
                    </span>
                )}
            </div>

            {reviews.length === 0 ? (
                <p className="text-muted-foreground">
                    No reviews yet. Be the first to share your thoughts.
                </p>
            ) : (
                <ul className="grid gap-5">
                    {reviews.map((review) => (
                        <li key={review.id} className="grid gap-1">
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <Stars rating={review.rating} />
                                {review.title && (
                                    <span className="font-medium">
                                        {review.title}
                                    </span>
                                )}
                            </div>
                            <p className="whitespace-pre-line">{review.body}</p>
                            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                {review.author}
                                {review.date && ` · ${review.date}`}
                                {review.verified && (
                                    <>
                                        {' · '}
                                        <BadgeCheck
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                        Verified purchase
                                    </>
                                )}
                            </p>
                        </li>
                    ))}
                </ul>
            )}

            {own && (
                <p className="text-sm text-muted-foreground">
                    {own.status === 'pending'
                        ? 'Thank you for your review. It will appear once it has been approved.'
                        : 'You have reviewed this product.'}
                </p>
            )}
            {reason === 'login' && (
                <p className="text-sm">
                    <Link href={loginUrl} className="underline">
                        Sign in
                    </Link>{' '}
                    to write a review.
                </p>
            )}
            {reason === 'purchase' && (
                <p className="text-sm text-muted-foreground">
                    Only customers who bought this product can review it.
                </p>
            )}
            {canReview && (
                <ReviewForm submitUrl={submitUrl} minLength={minLength} />
            )}
        </section>
    );
}

function Stars({ rating }: { rating: number }) {
    return (
        <span
            className="flex"
            role="img"
            aria-label={`${rating} out of 5 stars`}
        >
            {[1, 2, 3, 4, 5].map((n) => (
                <Star
                    key={n}
                    className={`size-4 ${n <= rating ? 'fill-current text-amber-500' : 'text-muted-foreground'}`}
                    aria-hidden
                />
            ))}
        </span>
    );
}

function ReviewForm({
    submitUrl,
    minLength,
}: {
    submitUrl: string;
    minLength: number;
}) {
    const [rating, setRating] = useState(5);

    return (
        <Form
            action={submitUrl}
            method="post"
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="grid max-w-xl gap-4 rounded-lg border p-4"
        >
            {({ processing, errors }) => (
                <>
                    <h3 className="font-medium">Write a review</h3>
                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">Rating</legend>
                        <div className="flex gap-1">
                            {[1, 2, 3, 4, 5].map((n) => (
                                <label key={n} className="cursor-pointer">
                                    <input
                                        type="radio"
                                        name="rating"
                                        value={n}
                                        checked={rating === n}
                                        onChange={() => setRating(n)}
                                        className="sr-only"
                                    />
                                    <Star
                                        className={`size-6 ${n <= rating ? 'fill-current text-amber-500' : 'text-muted-foreground'}`}
                                        aria-hidden
                                    />
                                    <span className="sr-only">
                                        {n} {n === 1 ? 'star' : 'stars'}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.rating} />
                    </fieldset>
                    <div className="grid gap-2">
                        <Label htmlFor="review-title">Title (optional)</Label>
                        <Input id="review-title" name="title" maxLength={120} />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="review-body">Your review</Label>
                        <Textarea
                            id="review-body"
                            name="body"
                            rows={4}
                            minLength={minLength}
                            maxLength={2000}
                            required
                        />
                        <InputError message={errors.body} />
                    </div>
                    <div>
                        <Button type="submit" disabled={processing}>
                            Submit review
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
