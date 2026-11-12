import { Download } from 'lucide-react';
import { formatDate } from '@/components/storefront/order-view';
import { useT } from '@/lib/i18n';
import type { DownloadLink } from '@/types';

/**
 * Customer order pages: "Your downloads", one link per purchased file.
 */
export function DownloadList({ downloads }: { downloads?: DownloadLink[] }) {
    const t = useT();

    if (!downloads || downloads.length === 0) {
        return null;
    }

    return (
        <section className="grid gap-3 rounded-lg border p-4">
            <h2 className="font-semibold">{t('Your downloads')}</h2>
            <ul className="grid gap-2">
                {downloads.map((d) => (
                    <li
                        key={d.id}
                        className="flex flex-wrap items-center justify-between gap-2 text-sm"
                    >
                        <span>
                            <span className="font-medium">{d.file}</span>
                            <span className="block text-xs text-muted-foreground">
                                {d.product}
                                {d.max_downloads !== null &&
                                    ` · ${t(':left of :max downloads left', {
                                        left: String(
                                            Math.max(
                                                0,
                                                d.max_downloads - d.downloads,
                                            ),
                                        ),
                                        max: String(d.max_downloads),
                                    })}`}
                                {d.expires_at &&
                                    ` · ${t('until :date', { date: formatDate(d.expires_at) })}`}
                            </span>
                        </span>
                        {d.url ? (
                            <a
                                href={d.url}
                                className="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 font-medium hover:bg-muted"
                            >
                                <Download className="size-4" aria-hidden />
                                {t('Download')}
                            </a>
                        ) : (
                            <span className="text-xs text-muted-foreground">
                                {t('No longer available')}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}
