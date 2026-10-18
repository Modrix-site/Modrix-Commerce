import { Form, Head } from '@inertiajs/react';
import {
    CircleAlert,
    CircleCheck,
    ExternalLink,
    RefreshCw,
} from 'lucide-react';
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
        url: string;
        counts: {
            home: number;
            products: number;
            categories: number;
            brands: number;
            pages: number;
        };
        total: number;
        include_images: boolean;
        changefreq: string;
    };
    settingsUrl: string;
    regenerateUrl: string;
};

export default function SitemapAdmin({
    state,
    settingsUrl,
    regenerateUrl,
}: Props) {
    const rows: [string, number, boolean][] = [
        ['Home page', state.counts.home, true],
        ['Products', state.counts.products, state.counts.products > 0],
        ['Categories', state.counts.categories, state.counts.categories > 0],
        ['Brands', state.counts.brands, state.counts.brands > 0],
        ['Content pages', state.counts.pages, state.counts.pages > 0],
    ];

    return (
        <>
            <Head title="XML Sitemap" />
            <AdminPage>
                <PageHeader
                    title="XML Sitemap"
                    description="A machine-readable list of your storefront pages for search engines. Google and Bing read it from your robots.txt and from Search Console."
                    actions={
                        <>
                            <Badge
                                variant={
                                    state.enabled ? 'default' : 'secondary'
                                }
                            >
                                {state.enabled ? 'Published' : 'Off'}
                            </Badge>
                            <Button variant="outline" asChild>
                                <a
                                    href={state.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink /> Open sitemap
                                </a>
                            </Button>
                        </>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {state.enabled
                                ? `${state.total} URLs in the sitemap`
                                : 'The sitemap is turned off'}
                        </CardTitle>
                        <CardDescription className="font-mono text-xs">
                            {state.url}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-2 text-sm">
                            {rows.map(([label, count, on]) => (
                                <li
                                    key={label}
                                    className="flex items-center justify-between gap-3"
                                >
                                    <span className="flex items-center gap-2">
                                        {on ? (
                                            <CircleCheck className="size-4 text-primary" />
                                        ) : (
                                            <CircleAlert className="size-4 text-muted-foreground" />
                                        )}
                                        {label}
                                    </span>
                                    <span className="text-muted-foreground tabular-nums">
                                        {count}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Options</CardTitle>
                        <CardDescription>
                            What to include, images and the change-frequency
                            hint are set in Extensions → XML Sitemap.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:flex sm:items-center sm:justify-between">
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <Badge variant="secondary">
                                changefreq: {state.changefreq}
                            </Badge>
                            <Badge
                                variant={
                                    state.include_images
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {state.include_images
                                    ? 'Images included'
                                    : 'Images off'}
                            </Badge>
                            <Button variant="link" size="sm" asChild>
                                <a href={settingsUrl}>Edit settings</a>
                            </Button>
                        </div>
                        <Form
                            action={regenerateUrl}
                            method="post"
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    <RefreshCw /> Regenerate
                                </Button>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}
