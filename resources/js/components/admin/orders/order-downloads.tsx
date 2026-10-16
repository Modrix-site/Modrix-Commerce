import { router } from '@inertiajs/react';
import DownloadGrantController from '@/actions/App/Http/Controllers/Admin/DownloadGrantController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/components/storefront/order-view';
import type { DownloadLink } from '@/types';

/**
 * Order page: the customer's download links, with help for links that ran
 * out (reset the count, extend by 30 days) or must stop (revoke).
 */
export function OrderDownloads({
    orderNumber,
    downloads,
}: {
    orderNumber: string;
    downloads?: DownloadLink[];
}) {
    if (!downloads || downloads.length === 0) {
        return null;
    }

    const act = (action: 'reset' | 'extend' | 'revoke', grantId: number) =>
        router.post(
            DownloadGrantController[action].url([orderNumber, grantId]),
            {},
            { preserveScroll: true },
        );

    return (
        <Card>
            <CardHeader>
                <CardTitle>Downloads</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3 text-sm">
                {downloads.map((d) => (
                    <div key={d.id} className="grid gap-1.5">
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-medium">{d.file}</span>
                            <Badge variant={d.usable ? 'secondary' : 'outline'}>
                                {d.usable ? 'Active' : 'Inactive'}
                            </Badge>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {d.downloads}
                            {d.max_downloads !== null
                                ? ` / ${d.max_downloads}`
                                : ''}{' '}
                            downloads
                            {d.expires_at &&
                                ` · until ${formatDate(d.expires_at)}`}
                            {d.last_downloaded_at &&
                                ` · last ${formatDate(d.last_downloaded_at)}`}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => act('reset', d.id)}
                            >
                                Reset count
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => act('extend', d.id)}
                            >
                                Extend 30 days
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                className="text-destructive"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            `Revoke the link for “${d.file}”? The customer can no longer download it.`,
                                        )
                                    ) {
                                        act('revoke', d.id);
                                    }
                                }}
                            >
                                Revoke
                            </Button>
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
