import { Star } from 'lucide-react';

type Props = {
    count: number;
    average: string | null;
};

/**
 * Rating stars near the product title (customizer → Product → Extension
 * blocks → "Rating stars"). Links to the reviews on the same page.
 */
export default function RatingStars({ count, average }: Props) {
    const rating = average === null ? 0 : Number(average);

    return (
        <a
            href="#reviews"
            className="product-rating-stars inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
            <span
                className="flex"
                role="img"
                aria-label={
                    count > 0 ? `Rated ${average} out of 5` : 'Not rated yet'
                }
            >
                {[1, 2, 3, 4, 5].map((n) => (
                    <Star
                        key={n}
                        className={`size-4 ${n <= Math.round(rating) ? 'fill-current text-amber-500' : 'text-muted-foreground/60'}`}
                        aria-hidden
                    />
                ))}
            </span>
            {count > 0 ? (
                <span>
                    {average} · {count} {count === 1 ? 'review' : 'reviews'}
                </span>
            ) : (
                <span className="underline-offset-4 hover:underline">
                    Be the first to review
                </span>
            )}
        </a>
    );
}
