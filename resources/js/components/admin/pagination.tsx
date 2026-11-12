import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export function Pagination({
    from,
    to,
    total,
    prevUrl,
    nextUrl,
    currentPage,
    lastPage,
    pageUrl,
}: {
    from: number | null;
    to: number | null;
    total: number;
    prevUrl: string | null;
    nextUrl: string | null;
    currentPage?: number;
    lastPage?: number;
    pageUrl?: (page: number) => string;
}) {
    if (total === 0) {
        return null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
        >
            <p>
                Showing {from}–{to} of {total} results
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
                {currentPage !== undefined &&
                    lastPage !== undefined &&
                    pageUrl && (
                        <div className="flex items-center gap-1">
                            {Array.from(
                                new Set([
                                    1,
                                    currentPage - 1,
                                    currentPage,
                                    currentPage + 1,
                                    lastPage,
                                ]),
                            )
                                .filter((page) => page > 0 && page <= lastPage)
                                .sort((a, b) => a - b)
                                .map((page, position, pages) => (
                                    <span
                                        key={page}
                                        className="flex items-center gap-1"
                                    >
                                        {position > 0 &&
                                            page - pages[position - 1] > 1 && (
                                                <span className="px-1">…</span>
                                            )}
                                        <Button
                                            asChild
                                            size="sm"
                                            variant={
                                                page === currentPage
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className="min-w-8 px-2"
                                        >
                                            <Link
                                                href={pageUrl(page)}
                                                preserveScroll
                                                aria-label={`Page ${page}`}
                                                aria-current={
                                                    page === currentPage
                                                        ? 'page'
                                                        : undefined
                                                }
                                            >
                                                {page}
                                            </Link>
                                        </Button>
                                    </span>
                                ))}
                        </div>
                    )}
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
