import { router } from '@inertiajs/react';
import { Cloud, CloudUpload, ExternalLink, Film, Image as ImageIcon, RefreshCw } from 'lucide-react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type CloudFileItem = {
    id: number;
    media_type: 'photo' | 'video' | 'document';
    title: string;
    url: string;
    provider_label: string;
    local_deleted: boolean;
    status: string;
};

type Props = {
    productId: number;
    enabled: boolean;
    isConfigured: boolean;
    providerLabel: string;
    saveMode: string;
    files: CloudFileItem[];
    uploadUrl: string;
    syncProductUrl: string;
    dashboardUrl: string;
};

export default function AdminProductStoragePanel({
    productId,
    enabled,
    isConfigured,
    providerLabel,
    saveMode,
    files,
    uploadUrl,
    syncProductUrl,
    dashboardUrl,
}: Props) {
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [title, setTitle] = useState('');
    const [busy, setBusy] = useState(false);

    const photosCount = files.filter((f) => f.media_type === 'photo').length;
    const videosCount = files.filter((f) => f.media_type === 'video').length;

    const uploadToCloud = () => {
        if (!selectedFile) {
            return;
        }

        const formData = new FormData();
        formData.append('file', selectedFile);
        formData.append('product_id', String(productId));
        if (title.trim() !== '') {
            formData.append('title', title.trim());
        }

        setBusy(true);
        router.post(uploadUrl, formData, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setSelectedFile(null);
                setTitle('');
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
            onFinish: () => setBusy(false),
        });
    };

    const syncProductPhotos = () => {
        setBusy(true);
        router.post(
            syncProductUrl,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center justify-between gap-2 text-sm">
                    <span className="flex items-center gap-2">
                        <Cloud className="size-4 text-sky-600" aria-hidden />
                        External Cloud Storage
                    </span>
                    <Badge variant={enabled && isConfigured ? 'default' : 'secondary'}>
                        {saveMode === 'external_only' ? 'External Only' : 'Hybrid'}
                    </Badge>
                </CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3 text-xs">
                <div className="flex items-center justify-between text-muted-foreground">
                    <span>Provider: {providerLabel}</span>
                    <a href={dashboardUrl} className="text-sky-600 underline">
                        Settings
                    </a>
                </div>

                <div className="grid grid-cols-2 gap-2 rounded-md border p-2 bg-muted/20">
                    <div>
                        <span className="text-muted-foreground block">Cloud Photos</span>
                        <span className="text-sm font-semibold">{photosCount}</span>
                    </div>
                    <div>
                        <span className="text-muted-foreground block">Cloud Videos</span>
                        <span className="text-sm font-semibold">{videosCount}</span>
                    </div>
                </div>

                {files.length > 0 && (
                    <div className="max-h-36 overflow-y-auto space-y-1.5 border rounded-md p-2">
                        {files.map((f) => (
                            <div
                                key={f.id}
                                className="flex items-center justify-between gap-2 text-[11px]"
                            >
                                <span className="flex items-center gap-1.5 truncate">
                                    {f.media_type === 'video' ? (
                                        <Film className="h-3 w-3 text-indigo-600 shrink-0" />
                                    ) : (
                                        <ImageIcon className="h-3 w-3 text-sky-600 shrink-0" />
                                    )}
                                    <span className="truncate">{f.title}</span>
                                </span>
                                <a
                                    href={f.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-sky-600 hover:underline shrink-0 inline-flex items-center gap-0.5"
                                >
                                    View <ExternalLink className="h-2.5 w-2.5" />
                                </a>
                            </div>
                        ))}
                    </div>
                )}

                <div className="space-y-2 pt-1 border-t">
                    <p className="font-medium">Upload Photo or Video to Cloud</p>
                    <Input
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        placeholder="Optional video/photo title"
                        className="h-8 text-xs"
                    />
                    <Input
                        ref={fileInputRef}
                        type="file"
                        accept="image/*,video/mp4,video/webm,video/quicktime"
                        onChange={(e) => setSelectedFile(e.target.files?.[0] ?? null)}
                        className="h-8 text-xs"
                    />
                    <div className="flex flex-wrap gap-1.5">
                        <Button
                            type="button"
                            size="sm"
                            className="flex-1 h-8 text-xs"
                            disabled={busy || !selectedFile || !isConfigured}
                            onClick={uploadToCloud}
                        >
                            <CloudUpload className="h-3.5 w-3.5 mr-1" />
                            Upload to Cloud
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-8 text-xs"
                            disabled={busy || !isConfigured}
                            onClick={syncProductPhotos}
                        >
                            <RefreshCw className="h-3.5 w-3.5 mr-1" />
                            Sync
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
