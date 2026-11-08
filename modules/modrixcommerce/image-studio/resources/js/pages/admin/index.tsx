import { Head, router } from '@inertiajs/react';
import { Image as ImageIcon, KeyRound, RefreshCw, RotateCcw, Sparkles, Undo2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

const BASE = '/admin/m/modrixcommerce/image-studio';

type Stats = {
    pending: number;
    processing: number;
    done: number;
    failed: number;
    skipped: number;
    bytes_saved: number;
};

type StudioImage = {
    id: number;
    productName: string;
    originalUrl: string;
    variantUrl: string | null;
    status: string;
    error: string | null;
    bytesBefore: number | null;
    bytesAfter: number | null;
};

type Props = {
    stats: Stats;
    hasRemoveBgKey: boolean;
    driver: string;
    images: {
        data: StudioImage[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    filters: { status: string };
};

const FILTERS = ['unprocessed', 'pending', 'processing', 'done', 'failed', 'skipped'];

const STATUS_STYLES: Record<string, string> = {
    unprocessed: 'bg-gray-100 text-gray-600',
    pending: 'bg-amber-100 text-amber-800',
    processing: 'bg-blue-100 text-blue-800',
    done: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-rose-100 text-rose-800',
    skipped: 'bg-gray-100 text-gray-700',
};

const checkerboard: React.CSSProperties = {
    backgroundColor: '#fff',
    backgroundImage:
        'linear-gradient(45deg, #e5e7eb 25%, transparent 25%, transparent 75%, #e5e7eb 75%), linear-gradient(45deg, #e5e7eb 25%, transparent 25%, transparent 75%, #e5e7eb 75%)',
    backgroundSize: '16px 16px',
    backgroundPosition: '0 0, 8px 8px',
};

function formatBytes(bytes: number | null): string {
    if (bytes === null || bytes === undefined) {
        return '-';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function Thumb({ src, label }: { src: string | null; label: string }) {
    return (
        <div className="flex-1">
            <div
                className="aspect-square rounded-md border border-gray-200 overflow-hidden flex items-center justify-center"
                style={checkerboard}
            >
                {src ? (
                    <img src={src} alt={label} loading="lazy" className="max-h-full max-w-full object-contain" />
                ) : (
                    <span className="text-[10px] text-gray-400">-</span>
                )}
            </div>
            <p className="text-[10px] text-gray-500 mt-1 text-center uppercase tracking-wider">{label}</p>
        </div>
    );
}

export default function ImageStudioAdminIndex({ stats, hasRemoveBgKey, driver, images, filters }: Props) {
    const [revertAllOpen, setRevertAllOpen] = useState(false);
    const [keyInput, setKeyInput] = useState('');
    const [busy, setBusy] = useState(false);

    const active = stats.pending + stats.processing > 0;

    useEffect(() => {
        if (!active) {
            return;
        }
        const timer = window.setInterval(() => {
            router.reload({ only: ['stats', 'images'] });
        }, 5000);

        return () => window.clearInterval(timer);
    }, [active]);

    function post(path: string) {
        setBusy(true);
        router.post(`${BASE}/${path}`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    }

    function filterBy(status: string) {
        router.get(BASE, { status: status === filters.status ? '' : status }, { preserveState: true });
    }

    function saveKey(e: React.FormEvent) {
        e.preventDefault();
        router.put(
            `${BASE}/removebg-key`,
            { key: keyInput },
            { preserveScroll: true, onSuccess: () => setKeyInput('') }
        );
    }

    function clearKey() {
        router.put(`${BASE}/removebg-key`, { key: '' }, { preserveScroll: true });
    }

    const statCards: Array<[string, number]> = [
        ['Pending', stats.pending],
        ['Processing', stats.processing],
        ['Done', stats.done],
        ['Failed', stats.failed],
        ['Skipped', stats.skipped],
    ];

    return (
        <AdminPage>
            <Head title="Image Studio" />

            <PageHeader
                title="Image Studio"
                description="Transparent-background WebP versions of product images. Originals are never changed."
                actions={
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" disabled={busy} onClick={() => post('retry')} className="gap-1.5">
                            <RefreshCw className="w-3.5 h-3.5" />
                            <span>Retry failed/skipped</span>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={busy}
                            onClick={() => setRevertAllOpen(true)}
                            className="gap-1.5"
                        >
                            <Undo2 className="w-3.5 h-3.5" />
                            <span>Revert all</span>
                        </Button>
                        <Button size="sm" disabled={busy} onClick={() => post('process-all')} className="gap-1.5">
                            <Sparkles className="w-3.5 h-3.5" />
                            <span>Process all</span>
                        </Button>
                    </div>
                }
            />

            <div className="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-4">
                {statCards.map(([label, value]) => (
                    <Card key={label} className="border-gray-200/80 shadow-xs">
                        <CardContent className="p-4">
                            <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">{label}</p>
                            <h3 className="text-2xl font-bold text-gray-900 mt-1">{value}</h3>
                        </CardContent>
                    </Card>
                ))}
                <Card className="border-gray-200/80 shadow-xs">
                    <CardContent className="p-4">
                        <p className="text-xs font-medium text-gray-500 uppercase tracking-wider">Saved</p>
                        <h3 className="text-2xl font-bold text-gray-900 mt-1">{formatBytes(stats.bytes_saved)}</h3>
                    </CardContent>
                </Card>
            </div>

            <p className="text-xs text-gray-500 mb-5">
                Queued images are processed by the queue worker. Run <code>php artisan queue:work</code> or{' '}
                <code>php artisan image-studio:process --all --sync</code>.
            </p>

            <div className="bg-white rounded-xl border border-gray-200/80 p-4 mb-5 shadow-xs">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div className="flex items-center gap-2 text-sm">
                        <KeyRound className="w-4 h-4 text-gray-500" />
                        <span className="font-medium text-gray-900">remove.bg API key</span>
                        <Badge variant="outline">{hasRemoveBgKey ? 'Key set' : 'Not set'}</Badge>
                        <span className="text-xs text-gray-500">Engine in use: {driver}</span>
                    </div>
                    <form onSubmit={saveKey} className="flex items-center gap-2">
                        <Input
                            type="password"
                            autoComplete="off"
                            value={keyInput}
                            onChange={(e) => setKeyInput(e.target.value)}
                            placeholder={hasRemoveBgKey ? 'Replace key' : 'Paste key'}
                            maxLength={200}
                            className="h-8 text-xs w-56"
                        />
                        <Button type="submit" size="sm" disabled={keyInput.trim() === ''}>
                            {hasRemoveBgKey ? 'Replace' : 'Save'}
                        </Button>
                        {hasRemoveBgKey && (
                            <Button type="button" variant="ghost" size="sm" onClick={clearKey}>
                                Clear
                            </Button>
                        )}
                    </form>
                </div>
            </div>

            <div className="flex flex-wrap gap-2 mb-4">
                {FILTERS.map((status) => (
                    <button
                        key={status}
                        type="button"
                        onClick={() => filterBy(status)}
                        className={`px-3 py-1 rounded-full text-xs font-semibold capitalize transition-colors ${
                            filters.status === status
                                ? 'bg-gray-900 text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        }`}
                    >
                        {status}
                    </button>
                ))}
            </div>

            {images.data.length === 0 ? (
                <EmptyState
                    icon={ImageIcon}
                    title="No images"
                    description="No product images match this filter."
                />
            ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    {images.data.map((image) => (
                        <Card key={image.id} className="border-gray-200/80 shadow-xs">
                            <CardContent className="p-4 space-y-3">
                                <div className="flex gap-3">
                                    <Thumb src={image.originalUrl} label="Original" />
                                    <Thumb src={image.variantUrl} label="Studio" />
                                </div>
                                <div className="flex items-start justify-between gap-2">
                                    <p className="text-sm font-medium text-gray-900 truncate" title={image.productName}>
                                        {image.productName || `Image #${image.id}`}
                                    </p>
                                    <span
                                        className={`px-2 py-0.5 rounded-full text-[11px] font-semibold capitalize ${
                                            STATUS_STYLES[image.status] ?? STATUS_STYLES.unprocessed
                                        }`}
                                    >
                                        {image.status}
                                    </span>
                                </div>
                                {image.error && <p className="text-xs text-rose-600 break-words">{image.error}</p>}
                                {image.status === 'done' && image.bytesBefore !== null && image.bytesAfter !== null && (
                                    <p className="text-xs text-gray-500">
                                        {formatBytes(image.bytesBefore)} to {formatBytes(image.bytesAfter)} (saved{' '}
                                        {formatBytes(Math.max(0, image.bytesBefore - image.bytesAfter))})
                                    </p>
                                )}
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="gap-1.5"
                                        onClick={() =>
                                            router.post(`${BASE}/images/${image.id}/process`, {}, { preserveScroll: true })
                                        }
                                    >
                                        <RotateCcw className="w-3.5 h-3.5" />
                                        <span>{image.status === 'unprocessed' ? 'Process' : 'Reprocess'}</span>
                                    </Button>
                                    {image.status !== 'unprocessed' && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.delete(`${BASE}/images/${image.id}`, { preserveScroll: true })
                                            }
                                        >
                                            Revert
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {images.links.length > 3 && (
                <div className="flex flex-wrap gap-1 mt-5">
                    {images.links.map((link, i) => (
                        <button
                            key={i}
                            type="button"
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                            className={`px-3 py-1 rounded text-xs ${
                                link.active ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600'
                            } disabled:opacity-40`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}

            <Dialog open={revertAllOpen} onOpenChange={setRevertAllOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Revert all images?</DialogTitle>
                        <DialogDescription>
                            This deletes every processed version. The storefront goes back to the original images.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="outline" size="sm" onClick={() => setRevertAllOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => {
                                setRevertAllOpen(false);
                                post('revert-all');
                            }}
                        >
                            Revert all
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </AdminPage>
    );
}
