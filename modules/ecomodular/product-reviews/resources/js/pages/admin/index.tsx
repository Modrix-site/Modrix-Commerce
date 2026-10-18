import { Form, Head, Link } from '@inertiajs/react';
import { BadgeCheck, Star } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { Paginated } from '@/types';

type ReviewRow = {
    id: number;
    product: string;
    product_url: string;
    author: string;
    rating: number;
    title: string | null;
    body: string;
    verified: boolean;
    status: string;
    created_at: string | null;
    approve_url: string;
    reject_url: string;
    delete_url: string;
};

type Props = {
    reviews: Paginated<ReviewRow>;
    status: string;
    counts: Record<string, number>;
    indexUrl: string;
};

const TABS = [
    { status: 'pending', label: 'Waiting for approval' },
    { status: 'approved', label: 'Published' },
    { status: 'rejected', label: 'Rejected' },
];

export default function ReviewsAdmin({
    reviews,
    status,
    counts,
    indexUrl,
}: Props) {
    return (
        <>
            <Head title="Reviews" />
            <AdminPage>
                <PageHeader
                    title="Product reviews"
                    description="Approve reviews before they appear on product pages. Review text is shown exactly as the customer wrote it."
                />
                <nav
                    className="flex flex-wrap gap-2"
                    aria-label="Review status"
                >
                    {TABS.map((tab) => (
                        <Button
                            key={tab.status}
                            variant={
                                tab.status === status ? 'default' : 'outline'
                            }
                            size="sm"
                            asChild
                        >
                            <Link href={`${indexUrl}?status=${tab.status}`}>
                                {tab.label} ({counts[tab.status] ?? 0})
                            </Link>
                        </Button>
                    ))}
                </nav>

                {reviews.data.length === 0 ? (
                    <EmptyState
                        title="No reviews here"
                        description="New reviews appear here as customers write them."
                    />
                ) : (
                    <ul className="grid gap-3">
                        {reviews.data.map((review) => (
                            <li key={review.id}>
                                <Card className="py-4">
                                    <CardContent className="grid gap-2">
                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                            <span
                                                className="flex"
                                                aria-label={`${review.rating} out of 5`}
                                            >
                                                {[1, 2, 3, 4, 5].map((n) => (
                                                    <Star
                                                        key={n}
                                                        className={`size-4 ${n <= review.rating ? 'fill-current text-amber-500' : 'text-muted-foreground'}`}
                                                        aria-hidden
                                                    />
                                                ))}
                                            </span>
                                            <a
                                                href={review.product_url}
                                                className="font-medium hover:underline"
                                            >
                                                {review.product}
                                            </a>
                                            <span className="text-muted-foreground">
                                                by {review.author}
                                            </span>
                                            {review.verified && (
                                                <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                                    <BadgeCheck
                                                        className="size-3.5"
                                                        aria-hidden
                                                    />
                                                    Verified purchase
                                                </span>
                                            )}
                                            {!review.verified && (
                                                <span className="text-xs text-muted-foreground">
                                                    Not a verified purchase: not
                                                    shown in the store
                                                </span>
                                            )}
                                        </div>
                                        {review.title && (
                                            <p className="font-medium">
                                                {review.title}
                                            </p>
                                        )}
                                        <p className="text-sm whitespace-pre-line">
                                            {review.body}
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {review.status !== 'approved' && (
                                                <Form
                                                    action={review.approve_url}
                                                    method="post"
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    <Button size="sm">
                                                        Publish
                                                    </Button>
                                                </Form>
                                            )}
                                            {review.status !== 'rejected' && (
                                                <Form
                                                    action={review.reject_url}
                                                    method="post"
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        Reject
                                                    </Button>
                                                </Form>
                                            )}
                                            <Form
                                                action={review.delete_url}
                                                method="delete"
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    Delete
                                                </Button>
                                            </Form>
                                        </div>
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination
                    from={reviews.from}
                    to={reviews.to}
                    total={reviews.total}
                    prevUrl={reviews.prev_page_url}
                    nextUrl={reviews.next_page_url}
                />
            </AdminPage>
        </>
    );
}

ReviewsAdmin.layout = {
    breadcrumbs: [
        { title: 'Reviews', href: '/admin/m/ecomodular/product-reviews' },
    ],
};
