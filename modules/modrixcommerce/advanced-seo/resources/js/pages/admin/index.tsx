import { Form, Head } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
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
        homeTitle: string;
        productTitle: string;
        sampleProduct: string | null;
        sitemapUrl: string;
        siteUrl: string;
        serviceAccount: string | null;
        verification: boolean;
        productCount: number;
    };
    settingsUrl: string;
    submitUrl: string;
    crawlUrl: string;
};

export default function AdvancedSeoAdmin({
    state,
    settingsUrl,
    submitUrl,
    crawlUrl,
}: Props) {
    const googleReady = state.serviceAccount !== null && state.siteUrl !== '';

    return (
        <>
            <Head title="Search SEO" />
            <AdminPage>
                <PageHeader
                    title="Search SEO"
                    description="Titles and descriptions are applied on every theme's home page, products, categories, brands and content pages."
                    actions={
                        <Badge variant={state.enabled ? 'default' : 'secondary'}>
                            {state.enabled ? 'On' : 'Off'}
                        </Badge>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Title patterns</CardTitle>
                        <CardDescription>
                            A title saved on a product, category or page is
                            kept when that option is on.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm">
                        <p>
                            <span className="text-muted-foreground">
                                Home page:{' '}
                            </span>
                            <span className="font-medium">{state.homeTitle}</span>
                        </p>
                        <p>
                            <span className="text-muted-foreground">
                                Products:{' '}
                            </span>
                            <span className="font-medium">
                                {state.productTitle}
                            </span>
                        </p>
                        {state.sampleProduct && (
                            <p className="text-muted-foreground">
                                Newest product: {state.sampleProduct}
                            </p>
                        )}
                        <Button variant="link" size="sm" className="w-fit px-0" asChild>
                            <a href={settingsUrl}>Edit title and description patterns</a>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Google Search Console</CardTitle>
                        <CardDescription>
                            Submit {state.sitemapUrl} and ask Google to crawl
                            the home page plus the {Math.min(state.productCount, 50)} newest
                            products. The service account must be an owner of
                            the property.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <ul className="grid gap-1 text-sm">
                            <li>
                                Property:{' '}
                                {state.siteUrl || 'the store URL will be used'}
                            </li>
                            <li>
                                Service account:{' '}
                                {state.serviceAccount || 'not saved'}
                            </li>
                            <li>
                                Site verification tag:{' '}
                                {state.verification ? 'saved' : 'not set'}
                            </li>
                        </ul>
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <a href={state.sitemapUrl} target="_blank" rel="noreferrer">
                                    <ExternalLink /> Open sitemap
                                </a>
                            </Button>
                            <Form action={submitUrl} method="post">
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing || !googleReady}>
                                        Submit sitemap to Google
                                    </Button>
                                )}
                            </Form>
                            <Form action={crawlUrl} method="post">
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing || !googleReady}
                                    >
                                        Request crawl
                                    </Button>
                                )}
                            </Form>
                        </div>
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}
