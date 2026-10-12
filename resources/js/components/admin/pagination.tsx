import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export function Pagination({
    from,
    to,
    total,
    prevUrl,
    nextUrl,
}: {
    from: number | null;
    to: number | null;
    total: number;
    prevUrl: string | null;
    nextUrl: string | null;
}) {
    if (total === 0) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="flex items-center justify-between gap-4 text-sm text-muted-foreground"
        >
            <p>
                Showing {from}–{to} of {total}
            </p>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    asChild={!!prevUrl}
                    disabled={!prevUrl}
                >
                    {prevUrl ? (
                        <Link href={prevUrl} preserveScroll>
                            Previous
                        </Link>
                    ) : (
                        <span>Previous</span>
                    )}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    asChild={!!nextUrl}
                    disabled={!nextUrl}
                >
                    {nextUrl ? (
                        <Link href={nextUrl} preserveScroll>
                            Next
                        </Link>
                    ) : (
                        <span>Next</span>
                    )}
                </Button>
            </div>
        </nav>
    );
}
