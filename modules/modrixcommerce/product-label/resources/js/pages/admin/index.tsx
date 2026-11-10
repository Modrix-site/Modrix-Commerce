import { Head } from '@inertiajs/react';
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
import { ProductLabels } from '@/components/storefront/product-labels';
import type { ProductLabel } from '@/types';

type Props = {
    state: {
        enabled: boolean;
        style: 'solid' | 'soft' | 'outline';
        position: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';
        max_labels: number;
        rules: {
            new: boolean;
            new_days: number;
            sale: boolean;
            low_stock: boolean;
            low_stock_threshold: number;
            featured: boolean;
            sold_out: boolean;
        };
        custom_labels: number;
        products: number;
    };
    preview: {
        name: string;
        image: { url: string; alt: string | null } | null;
        labels: { text: string; tone: ProductLabel['tone'] }[];
    }[];
    settingsUrl: string;
};

export default function ProductLabelAdmin({
    state,
    preview,
    settingsUrl,
}: Props) {
    const active: string[] = [];

    if (state.rules.sold_out) active.push('Sold out');
    if (state.rules.sale) active.push('Sale');
    if (state.rules.new) active.push(`New (${state.rules.new_days}d)`);
    if (state.rules.low_stock)
        active.push(`Low stock (≤${state.rules.low_stock_threshold})`);
    if (state.rules.featured) active.push('Featured');
    if (state.custom_labels > 0) active.push(`${state.custom_labels} custom`);

    return (
        <>
            <Head title="Product Label" />
            <AdminPage>
                <PageHeader
                    title="Product Label"
                    description="Highlights products with attractive labels — New, Sale, Low stock, Featured and your own custom badges — on every product card and product page."
                    actions={
                        <Badge
                            variant={state.enabled ? 'default' : 'secondary'}
                        >
                            {state.enabled ? 'On' : 'Off'}
                        </Badge>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Active labels</CardTitle>
                        <CardDescription>
                            {state.enabled
                                ? `Each product shows up to ${state.max_labels} labels, positioned ${state.position.replace('-', ' ')} on the image, in the “${state.style}” style.`
                                : 'Labels are switched off. Turn them on in the extension settings.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-2 text-sm">
                        {active.length === 0 ? (
                            <span className="text-muted-foreground">
                                No label rules are enabled.
                            </span>
                        ) : (
                            active.map((label) => (
                                <Badge key={label} variant="outline">
                                    {label}
                                </Badge>
                            ))
                        )}
                        <Button variant="link" size="sm" asChild>
                            <a href={settingsUrl}>Edit settings</a>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Live preview</CardTitle>
                        <CardDescription>
                            {state.products > 0
                                ? 'Up to three labelled products from your catalogue.'
                                : 'The preview appears once your catalogue has labelled products.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {preview.length === 0 ? (
                            <EmptyState
                                title="Nothing to preview yet"
                                description="Add products, put some on sale or mark them featured, and the badges will show here."
                            />
                        ) : (
                            <ul className="grid gap-4 sm:grid-cols-3">
                                {preview.map((item) => (
                                    <li key={item.name} className="grid gap-2">
                                        <div className="relative aspect-square overflow-hidden rounded-lg border bg-muted">
                                            <ProductLabels
                                                labels={item.labels.map(
                                                    (label, i) => ({
                                                        key: String(i),
                                                        text: label.text,
                                                        tone: label.tone,
                                                        position:
                                                            state.position,
                                                        style: state.style,
                                                    }),
                                                )}
                                            />
                                            {item.image ? (
                                                <img
                                                    src={item.image.url}
                                                    alt={
                                                        item.image.alt ??
                                                        item.name
                                                    }
                                                    className="size-full object-cover"
                                                />
                                            ) : null}
                                        </div>
                                        <span className="text-sm font-medium">
                                            {item.name}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}

ProductLabelAdmin.layout = {
    breadcrumbs: [
        {
            title: 'Product Label',
            href: '/admin/m/modrixcommerce/product-label',
        },
    ],
};
