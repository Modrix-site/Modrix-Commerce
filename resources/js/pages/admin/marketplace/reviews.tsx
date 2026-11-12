import { Form, Head } from '@inertiajs/react';
import MarketplaceReviewController from '@/actions/App/Http/Controllers/Marketplace/MarketplaceReviewController';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/admin/marketplace/reviews';

type Props = {
    reviews: {
        id: number;
        product: string;
        author: string;
        email: string;
        rating: number;
        title: string | null;
        body: string;
        created_at: string | null;
    }[];
};

export default function MarketplaceReviews({ reviews }: Props) {
    return (
        <>
            <Head title="Marketplace reviews" />
            <AdminPage>
                <PageHeader
                    title="Marketplace reviews"
                    description="Reviews from license holders wait here until you approve them."
                />
                {reviews.length === 0 ? (
                    <EmptyState title="No reviews waiting" />
                ) : (
                    <ul className="grid gap-3">
                        {reviews.map((r) => (
                            <li key={r.id}>
                                <Card className="py-4">
                                    <CardContent className="grid gap-2 text-sm">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {r.product}: {r.rating}/5
                                                {r.title && ` · ${r.title}`}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {r.author} ({r.email}) ·{' '}
                                                {r.created_at}
                                            </span>
                                            <div className="ml-auto flex gap-2">
                                                {(
                                                    [
                                                        'approve',
                                                        'reject',
                                                    ] as const
                                                ).map((decision) => (
                                                    <Form
                                                        key={decision}
                                                        {...MarketplaceReviewController.moderate.form(
                                                            r.id,
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="decision"
                                                            value={decision}
                                                        />
                                                        <Button
                                                            size="sm"
                                                            variant={
                                                                decision ===
                                                                'approve'
                                                                    ? 'default'
                                                                    : 'outline'
                                                            }
                                                        >
                                                            {decision ===
                                                            'approve'
                                                                ? 'Approve'
                                                                : 'Reject'}
                                                        </Button>
                                                    </Form>
                                                ))}
                                            </div>
                                        </div>
                                        <p className="whitespace-pre-line">
                                            {r.body}
                                        </p>
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </AdminPage>
        </>
    );
}

MarketplaceReviews.layout = {
    breadcrumbs: [{ title: 'Marketplace reviews', href: index() }],
};
