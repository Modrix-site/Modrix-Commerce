import { Head } from '@inertiajs/react';
import { ExternalLink, PackagePlus } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Props = {
    state: {
        enabled: boolean;
        heading: string;
        max_products: number;
        min_orders: number;
        fallback_related: boolean;
        show_savings: boolean;
        products: number;
    };
    sample: { name: string; url: string } | null;
    preview: {
        heading: string;
        current: { name: string };
        items: { name: string; url: string }[];
    } | null;
    settingsUrl: string;
};

export default function FrequentlyBoughtTogetherAdmin({
    state,
    sample,
    preview,
    settingsUrl,
}: Props) {
    return (
        <>
            <Head title="Frequently Bought Together" />
            <AdminPage>
                <PageHeader
                    title="Frequently Bought Together"
                    description="Suggests products customers often buy with the one being viewed, learned from your order history. Shoppers tick the extras and add the whole set to the cart at once."
                    actions={
                        <Badge
                            variant={state.enabled ? 'default' : 'secondary'}
                        >
                            {state.enabled ? 'Shown' : 'Off'}
                        </Badge>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>How it decides</CardTitle>
                        <CardDescription>
                            A product is suggested once it has appeared in at
                            least {state.min_orders}{' '}
                            {state.min_orders === 1 ? 'order' : 'orders'}{' '}
                            together with the viewed product. Up to{' '}
                            {state.max_products} suggestions are shown.
                            {state.fallback_related
                                ? ' When history is thin, products from the same category fill the gap.'
                                : ' Products are only ever suggested from real order history.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-2 text-sm">
                        <Badge variant="secondary">
                            Heading: {state.heading}
                        </Badge>
                        <Badge variant="outline">
                            {state.show_savings
                                ? 'Combined price shown'
                                : 'Combined price hidden'}
                        </Badge>
                        <Badge variant="outline">
                            {state.products}{' '}
                            {state.products === 1 ? 'product' : 'products'} in
                            the catalogue
                        </Badge>
                        <Button variant="link" size="sm" asChild>
                            <a href={settingsUrl}>Edit settings</a>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Live preview</CardTitle>
                        <CardDescription>
                            {sample
                                ? `What a shopper sees on “${sample.name}”.`
                                : 'The preview appears once your catalogue has a visible product.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {preview === null ? (
                            <EmptyState
                                title="Nothing to preview yet"
                                description="Add products and orders, and this panel will show a real bundle."
                            />
                        ) : (
                            <div className="grid gap-3">
                                <p className="text-sm font-medium">
                                    {preview.heading}
                                </p>
                                <ul className="flex flex-wrap items-center gap-2 text-sm">
                                    <li className="rounded-[var(--radius)] border bg-muted px-3 py-2">
                                        {preview.current.name}{' '}
                                        <span className="text-xs text-muted-foreground">
                                            (this item)
                                        </span>
                                    </li>
                                    {preview.items.map((item) => (
                                        <li key={item.url}>
                                            <a
                                                href={item.url}
                                                className="flex items-center gap-1 rounded-[var(--radius)] border px-3 py-2 hover:bg-muted"
                                            >
                                                {item.name}
                                                <ExternalLink className="size-3.5 text-muted-foreground" />
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                                {sample && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                        className="w-fit"
                                    >
                                        <a
                                            href={sample.url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <PackagePlus /> View on the
                                            storefront
                                        </a>
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}

FrequentlyBoughtTogetherAdmin.layout = {
    breadcrumbs: [
        {
            title: 'Frequently Bought Together',
            href: '/admin/m/modrixcommerce/frequently-bought-together',
        },
    ],
};
