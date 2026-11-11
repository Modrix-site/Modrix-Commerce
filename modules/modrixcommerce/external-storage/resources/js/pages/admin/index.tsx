import { Head, router } from '@inertiajs/react';
import {
    BookOpen,
    Check,
    CheckCircle2,
    Cloud,
    CloudUpload,
    Copy,
    ExternalLink,
    Film,
    HardDrive,
    HelpCircle,
    Image as ImageIcon,
    KeyRound,
    PlugZap,
    RefreshCw,
    Server,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

const BASE = '/admin/m/modrixcommerce/external-storage';

type SettingsProps = {
    enabled: boolean;
    provider: string;
    providerLabel: string;
    save_mode: 'external_only' | 'hybrid' | 'manual';
    auto_offload_photos: boolean;
    auto_offload_videos: boolean;
    delete_local_after_sync: boolean;
    show_product_videos: boolean;
    bucket: string;
    region: string;
    account_id: string;
    endpoint: string;
    cdn_url: string;
    path_prefix: string;
    isConfigured: boolean;
    hasAccessKey: boolean;
    hasSecretKey: boolean;
    hasApiToken: boolean;
    accessKeyHint: string | null;
    secretKeyHint: string | null;
    apiTokenHint: string | null;
};

type ProviderOption = {
    value: string;
    label: string;
};

type ProviderGuide = {
    provider: string;
    name: string;
    protocol: string;
    summary: string;
    console_url: string;
    docs_url: string;
    required_fields: string[];
    example_region: string;
    example_endpoint: string;
    example_cdn: string;
    steps: string[];
    policy_snippet: string | null;
    cors_note: string;
};

type Stats = {
    totalProductImages: number;
    syncedFiles: number;
    syncedPhotos: number;
    syncedVideos: number;
    failedFiles: number;
    unsyncedLocalImages: number;
    totalBytesOffloaded: number;
    localDeletedCount: number;
};

type StorageFileItem = {
    id: number;
    product_id: number | null;
    product_name: string | null;
    product_image_id: number | null;
    media_type: 'photo' | 'video' | 'document';
    title: string | null;
    provider: string;
    provider_label: string;
    bucket: string | null;
    remote_path: string;
    remote_url: string;
    filename: string;
    mime_type: string;
    file_size: number;
    local_deleted: boolean;
    status: 'synced' | 'pending' | 'failed';
    error_message: string | null;
    synced_at: string | null;
};

type ProductOption = {
    id: number;
    name: string;
};

type Props = {
    settings: SettingsProps;
    providers: ProviderOption[];
    providerGuides?: Record<string, ProviderGuide>;
    stats: Stats;
    files: {
        data: StorageFileItem[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
    products: ProductOption[];
    filters: {
        type: string;
        status: string;
        provider: string;
    };
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm dark:bg-input/30';

function formatBytes(bytes: number): string {
    if (!bytes || bytes <= 0) {
        return '0 B';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    if (bytes < 1024 * 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    }
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
}

export default function ExternalStorageAdminIndex({
    settings,
    providers,
    providerGuides = {},
    stats,
    files,
    products,
    filters,
}: Props) {
    // Configuration state
    const [enabled, setEnabled] = useState(settings.enabled);
    const [provider, setProvider] = useState(settings.provider);
    const [saveMode, setSaveMode] = useState<
        'external_only' | 'hybrid' | 'manual'
    >(settings.save_mode);
    const [autoOffloadPhotos, setAutoOffloadPhotos] = useState(
        settings.auto_offload_photos,
    );
    const [autoOffloadVideos, setAutoOffloadVideos] = useState(
        settings.auto_offload_videos,
    );
    const [deleteLocalAfterSync, setDeleteLocalAfterSync] = useState(
        settings.delete_local_after_sync,
    );
    const [showProductVideos, setShowProductVideos] = useState(
        settings.show_product_videos,
    );
    const [bucket, setBucket] = useState(settings.bucket);
    const [region, setRegion] = useState(settings.region);
    const [accountId, setAccountId] = useState(settings.account_id);
    const [endpoint, setEndpoint] = useState(settings.endpoint);
    const [cdnUrl, setCdnUrl] = useState(settings.cdn_url);
    const [pathPrefix, setPathPrefix] = useState(settings.path_prefix);

    const [accessKey, setAccessKey] = useState('');
    const [secretKey, setSecretKey] = useState('');
    const [apiToken, setApiToken] = useState('');

    // Documentation state
    const [showInlineGuide, setShowInlineGuide] = useState(true);
    const [docsModalOpen, setDocsModalOpen] = useState(false);
    const [docTabProvider, setDocTabProvider] = useState(settings.provider);
    const [copiedPolicy, setCopiedPolicy] = useState(false);

    // Direct upload state
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [uploadFile, setUploadFile] = useState<File | null>(null);
    const [uploadProductId, setUploadProductId] = useState<string>('');
    const [uploadTitle, setUploadTitle] = useState('');
    const [uploadMediaType, setUploadMediaType] = useState<
        '' | 'photo' | 'video'
    >('');

    const [busy, setBusy] = useState(false);

    const activeGuide: ProviderGuide | undefined = providerGuides[provider];
    const modalGuide: ProviderGuide | undefined =
        providerGuides[docTabProvider] ?? activeGuide;

    const needsCloudflareAccount =
        provider === 'cloudflare_r2' || provider === 'cloudflare_api';
    const usesTokenAuth =
        provider === 'cloudflare_api' ||
        provider === 'bunnycdn' ||
        provider === 'custom_api';

    const copyText = (text: string) => {
        if (navigator.clipboard) {
            void navigator.clipboard.writeText(text);
            setCopiedPolicy(true);
            window.setTimeout(() => setCopiedPolicy(false), 2000);
        }
    };

    const saveSettings = (e: React.FormEvent) => {
        e.preventDefault();
        setBusy(true);
        router.put(
            `${BASE}/settings`,
            {
                enabled,
                provider,
                save_mode: saveMode,
                auto_offload_photos: autoOffloadPhotos,
                auto_offload_videos: autoOffloadVideos,
                delete_local_after_sync: deleteLocalAfterSync,
                show_product_videos: showProductVideos,
                bucket,
                region,
                account_id: accountId,
                endpoint,
                cdn_url: cdnUrl,
                path_prefix: pathPrefix,
                access_key: accessKey || undefined,
                secret_key: secretKey || undefined,
                api_token: apiToken || undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAccessKey('');
                    setSecretKey('');
                    setApiToken('');
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    const testConnection = () => {
        setBusy(true);
        router.post(
            `${BASE}/test-connection`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
            },
        );
    };

    const syncAllLocal = () => {
        setBusy(true);
        router.post(
            `${BASE}/sync-all`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
            },
        );
    };

    const handleUploadSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!uploadFile) {
            return;
        }

        const formData = new FormData();
        formData.append('file', uploadFile);
        if (uploadProductId !== '') {
            formData.append('product_id', uploadProductId);
        }
        if (uploadTitle.trim() !== '') {
            formData.append('title', uploadTitle.trim());
        }
        if (uploadMediaType !== '') {
            formData.append('media_type', uploadMediaType);
        }

        setBusy(true);
        router.post(`${BASE}/upload`, formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setUploadFile(null);
                setUploadTitle('');
                if (fileInputRef.current) {
                    fileInputRef.current.value = '';
                }
            },
            onFinish: () => setBusy(false),
        });
    };

    const applyFilter = (next: Partial<typeof filters>) => {
        const merged = { ...filters, ...next };
        const query: Record<string, string> = {};
        if (merged.type) query.type = merged.type;
        if (merged.status) query.status = merged.status;
        if (merged.provider) query.provider = merged.provider;

        router.get(BASE, query, { preserveState: true, preserveScroll: true });
    };

    const deleteFile = (item: StorageFileItem) => {
        setBusy(true);
        router.delete(`${BASE}/files/${item.id}`, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };

    return (
        <AdminPage>
            <Head title="External Cloud Storage" />

            <PageHeader
                title="External Cloud Storage"
                description="Automatically save product photos, product videos, and media assets directly in Wasabi, AWS S3, Cloudflare Storage, DigitalOcean Spaces, Backblaze B2, BunnyCDN, or custom cloud storage instead of locally."
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setDocTabProvider(provider);
                                setDocsModalOpen(true);
                            }}
                        >
                            <BookOpen className="mr-1.5 h-4 w-4" />
                            Provider Setup Documentation
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={busy}
                            onClick={testConnection}
                        >
                            <PlugZap className="mr-1.5 h-4 w-4" />
                            Test Connection
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={busy || !settings.isConfigured}
                            onClick={syncAllLocal}
                        >
                            <RefreshCw className="mr-1.5 h-4 w-4" />
                            Sync Local Photos ({stats.unsyncedLocalImages})
                        </Button>
                    </div>
                }
            />

            {/* Status & Metrics */}
            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-5">
                <Card>
                    <CardContent className="p-4">
                        <p className="text-xs text-muted-foreground">
                            Active Provider
                        </p>
                        <div className="mt-1 flex items-center gap-2">
                            <Server className="h-4 w-4 shrink-0 text-sky-600" />
                            <p className="truncate text-sm font-semibold">
                                {settings.providerLabel}
                            </p>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            {settings.isConfigured ? (
                                <span className="font-medium text-emerald-600">
                                    Connected ({settings.bucket || 'API'})
                                </span>
                            ) : (
                                <span className="font-medium text-amber-600">
                                    Credentials required
                                </span>
                            )}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <p className="text-xs text-muted-foreground">
                            Storage Mode
                        </p>
                        <p className="mt-1 text-sm font-semibold">
                            {settings.save_mode === 'external_only'
                                ? 'External Only (No Local)'
                                : settings.save_mode === 'hybrid'
                                  ? 'External + Local Backup'
                                  : 'Manual Sync Only'}
                        </p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            {stats.localDeletedCount} files local-free
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <p className="text-xs text-muted-foreground">
                            Cloud Product Photos
                        </p>
                        <p className="mt-1 text-2xl font-semibold text-sky-700 dark:text-sky-400">
                            {stats.syncedPhotos}
                        </p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            {stats.unsyncedLocalImages} unsynced local
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <p className="text-xs text-muted-foreground">
                            Cloud Product Videos
                        </p>
                        <p className="mt-1 text-2xl font-semibold text-indigo-700 dark:text-indigo-400">
                            {stats.syncedVideos}
                        </p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Storefront video player ready
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <p className="text-xs text-muted-foreground">
                            Cloud Data Offloaded
                        </p>
                        <p className="mt-1 text-2xl font-semibold text-emerald-700 dark:text-emerald-400">
                            {formatBytes(stats.totalBytesOffloaded)}
                        </p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            {stats.syncedFiles} total objects synced
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Provider & Automatic Storage Settings */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between text-base">
                            <span className="flex items-center gap-2">
                                <HardDrive className="h-4 w-4 text-sky-600" />
                                External Storage Provider & Automatic Save
                                Settings
                            </span>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 text-xs text-sky-600"
                                    onClick={() =>
                                        setShowInlineGuide(!showInlineGuide)
                                    }
                                >
                                    <HelpCircle className="mr-1 h-3.5 w-3.5" />
                                    {showInlineGuide
                                        ? 'Hide Setup Guide'
                                        : 'Show Setup Guide'}
                                </Button>
                                <Badge
                                    variant={enabled ? 'default' : 'secondary'}
                                >
                                    {enabled ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {/* Contextual Provider Setup Guide Banner (auto-updates when provider changes) */}
                        {showInlineGuide && activeGuide && (
                            <div className="mb-5 space-y-3 rounded-xl border border-sky-200 bg-sky-50/60 p-4 dark:border-sky-900 dark:bg-sky-950/30">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <BookOpen className="h-4 w-4 shrink-0 text-sky-600" />
                                            <h4 className="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                                How to Configure{' '}
                                                {activeGuide.name}
                                            </h4>
                                            <Badge
                                                variant="outline"
                                                className="text-[10px]"
                                            >
                                                {activeGuide.protocol}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                            {activeGuide.summary}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-2 text-xs">
                                        {activeGuide.console_url && (
                                            <a
                                                href={activeGuide.console_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1 rounded-md border border-sky-300 bg-white px-2.5 py-1 font-medium text-sky-700 hover:bg-sky-50 dark:bg-slate-900 dark:text-sky-300"
                                            >
                                                Open Provider Console{' '}
                                                <ExternalLink className="h-3 w-3" />
                                            </a>
                                        )}
                                        {activeGuide.docs_url && (
                                            <a
                                                href={activeGuide.docs_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1 font-medium text-slate-700 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-300"
                                            >
                                                Official Docs{' '}
                                                <ExternalLink className="h-3 w-3" />
                                            </a>
                                        )}
                                    </div>
                                </div>

                                <ol className="list-decimal space-y-1 pl-5 text-xs text-slate-700 dark:text-slate-200">
                                    {activeGuide.steps.map((step, idx) => (
                                        <li key={idx}>{step}</li>
                                    ))}
                                </ol>

                                <div className="grid grid-cols-1 gap-2 pt-1 text-[11px] sm:grid-cols-3">
                                    <div className="rounded border bg-white/80 p-2 dark:bg-slate-900/60">
                                        <span className="block font-semibold text-slate-800 dark:text-slate-200">
                                            Example Region
                                        </span>
                                        <span className="font-mono text-slate-600 dark:text-slate-400">
                                            {activeGuide.example_region}
                                        </span>
                                    </div>
                                    <div className="rounded border bg-white/80 p-2 dark:bg-slate-900/60">
                                        <span className="block font-semibold text-slate-800 dark:text-slate-200">
                                            API / S3 Endpoint
                                        </span>
                                        <span className="font-mono break-all text-slate-600 dark:text-slate-400">
                                            {activeGuide.example_endpoint}
                                        </span>
                                    </div>
                                    <div className="rounded border bg-white/80 p-2 dark:bg-slate-900/60">
                                        <span className="block font-semibold text-slate-800 dark:text-slate-200">
                                            Public CDN / URL Format
                                        </span>
                                        <span className="font-mono break-all text-slate-600 dark:text-slate-400">
                                            {activeGuide.example_cdn}
                                        </span>
                                    </div>
                                </div>

                                {activeGuide.policy_snippet && (
                                    <div className="rounded-lg border bg-slate-900 p-3 text-xs text-slate-100">
                                        <div className="mb-1.5 flex items-center justify-between">
                                            <span className="font-medium text-sky-300">
                                                Recommended Public-Read Bucket
                                                Policy (JSON)
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    copyText(
                                                        activeGuide.policy_snippet ||
                                                            '',
                                                    )
                                                }
                                                className="inline-flex items-center gap-1 rounded bg-slate-800 px-2 py-0.5 text-[11px] hover:bg-slate-700"
                                            >
                                                {copiedPolicy ? (
                                                    <>
                                                        <Check className="h-3 w-3 text-emerald-400" />{' '}
                                                        Copied
                                                    </>
                                                ) : (
                                                    <>
                                                        <Copy className="h-3 w-3" />{' '}
                                                        Copy JSON
                                                    </>
                                                )}
                                            </button>
                                        </div>
                                        <pre className="overflow-x-auto font-mono text-[11px] leading-relaxed text-slate-200">
                                            {activeGuide.policy_snippet}
                                        </pre>
                                    </div>
                                )}

                                <div className="flex items-center justify-between pt-1 text-[11px] text-slate-500">
                                    <span>{activeGuide.cors_note}</span>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setDocTabProvider(provider);
                                            setDocsModalOpen(true);
                                        }}
                                        className="ml-2 shrink-0 font-medium text-sky-600 underline"
                                    >
                                        View all 11 provider guides →
                                    </button>
                                </div>
                            </div>
                        )}

                        <form onSubmit={saveSettings} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        Storage Provider
                                    </label>
                                    <select
                                        value={provider}
                                        onChange={(e) =>
                                            setProvider(e.target.value)
                                        }
                                        className={selectClass}
                                    >
                                        {providers.map((p) => (
                                            <option
                                                key={p.value}
                                                value={p.value}
                                            >
                                                {p.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        Automatic Save Behavior
                                    </label>
                                    <select
                                        value={saveMode}
                                        onChange={(e) =>
                                            setSaveMode(
                                                e.target.value as
                                                    | 'external_only'
                                                    | 'hybrid'
                                                    | 'manual',
                                            )
                                        }
                                        className={selectClass}
                                    >
                                        <option value="external_only">
                                            Save automatically in external
                                            storage instead of locally (No local
                                            copy)
                                        </option>
                                        <option value="hybrid">
                                            Save automatically in external
                                            storage + keep local backup
                                        </option>
                                        <option value="manual">
                                            Manual sync only (Keep uploads local
                                            until synced)
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        Bucket / Storage Zone Name
                                    </label>
                                    <Input
                                        value={bucket}
                                        onChange={(e) =>
                                            setBucket(e.target.value)
                                        }
                                        placeholder="e.g. my-store-media"
                                    />
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        Region
                                    </label>
                                    <Input
                                        value={region}
                                        onChange={(e) =>
                                            setRegion(e.target.value)
                                        }
                                        placeholder="us-east-1, eu-central-1, auto"
                                    />
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        Remote Folder Prefix
                                    </label>
                                    <Input
                                        value={pathPrefix}
                                        onChange={(e) =>
                                            setPathPrefix(e.target.value)
                                        }
                                        placeholder="catalog"
                                    />
                                </div>
                            </div>

                            {needsCloudflareAccount && (
                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        Cloudflare Account ID
                                    </label>
                                    <Input
                                        value={accountId}
                                        onChange={(e) =>
                                            setAccountId(e.target.value)
                                        }
                                        placeholder="32-character Cloudflare Account ID"
                                    />
                                </div>
                            )}

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        Custom API / S3 Endpoint URL (Optional)
                                    </label>
                                    <Input
                                        value={endpoint}
                                        onChange={(e) =>
                                            setEndpoint(e.target.value)
                                        }
                                        placeholder="https://s3.wasabisys.com or custom endpoint"
                                    />
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        Public CDN / Custom Domain URL
                                        (Optional)
                                    </label>
                                    <Input
                                        value={cdnUrl}
                                        onChange={(e) =>
                                            setCdnUrl(e.target.value)
                                        }
                                        placeholder="https://cdn.yourstore.com"
                                    />
                                </div>
                            </div>

                            {/* Credentials Section */}
                            <div className="space-y-3 rounded-lg border bg-muted/20 p-3.5">
                                <div className="flex items-center gap-2 text-xs font-semibold">
                                    <KeyRound className="h-3.5 w-3.5 text-sky-600" />
                                    API Credentials (Encrypted at rest)
                                </div>

                                {!usesTokenAuth ? (
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div>
                                            <label className="mb-1 block text-xs text-muted-foreground">
                                                Access Key ID{' '}
                                                {settings.accessKeyHint && (
                                                    <span className="font-medium text-emerald-600">
                                                        (Saved:{' '}
                                                        {settings.accessKeyHint}
                                                        )
                                                    </span>
                                                )}
                                            </label>
                                            <Input
                                                type="password"
                                                value={accessKey}
                                                onChange={(e) =>
                                                    setAccessKey(e.target.value)
                                                }
                                                placeholder={
                                                    settings.hasAccessKey
                                                        ? 'Leave blank to keep existing key'
                                                        : 'Enter Access Key ID'
                                                }
                                            />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs text-muted-foreground">
                                                Secret Access Key{' '}
                                                {settings.secretKeyHint && (
                                                    <span className="font-medium text-emerald-600">
                                                        (Saved:{' '}
                                                        {settings.secretKeyHint}
                                                        )
                                                    </span>
                                                )}
                                            </label>
                                            <Input
                                                type="password"
                                                value={secretKey}
                                                onChange={(e) =>
                                                    setSecretKey(e.target.value)
                                                }
                                                placeholder={
                                                    settings.hasSecretKey
                                                        ? 'Leave blank to keep existing secret'
                                                        : 'Enter Secret Access Key'
                                                }
                                            />
                                        </div>
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div>
                                            <label className="mb-1 block text-xs text-muted-foreground">
                                                API Token / Storage Access Key{' '}
                                                {settings.apiTokenHint && (
                                                    <span className="font-medium text-emerald-600">
                                                        (Saved:{' '}
                                                        {settings.apiTokenHint})
                                                    </span>
                                                )}
                                            </label>
                                            <Input
                                                type="password"
                                                value={apiToken}
                                                onChange={(e) =>
                                                    setApiToken(e.target.value)
                                                }
                                                placeholder={
                                                    settings.hasApiToken
                                                        ? 'Leave blank to keep existing token'
                                                        : 'Enter Bearer API Token or AccessKey'
                                                }
                                            />
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-xs text-muted-foreground">
                                                Optional Secondary Key{' '}
                                                {settings.accessKeyHint && (
                                                    <span className="font-medium text-emerald-600">
                                                        (Saved:{' '}
                                                        {settings.accessKeyHint}
                                                        )
                                                    </span>
                                                )}
                                            </label>
                                            <Input
                                                type="password"
                                                value={accessKey}
                                                onChange={(e) =>
                                                    setAccessKey(e.target.value)
                                                }
                                                placeholder="Optional Access Key ID"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Automatic Offload Toggles */}
                            <div className="grid grid-cols-1 gap-2.5 pt-1 text-sm sm:grid-cols-2">
                                <label className="flex items-center gap-2">
                                    <Checkbox
                                        checked={enabled}
                                        onCheckedChange={(v) =>
                                            setEnabled(v === true)
                                        }
                                    />
                                    <span>Enable External Cloud Storage</span>
                                </label>

                                <label className="flex items-center gap-2">
                                    <Checkbox
                                        checked={autoOffloadPhotos}
                                        onCheckedChange={(v) =>
                                            setAutoOffloadPhotos(v === true)
                                        }
                                    />
                                    <span>
                                        Automatically save new product photos to
                                        external storage
                                    </span>
                                </label>

                                <label className="flex items-center gap-2">
                                    <Checkbox
                                        checked={autoOffloadVideos}
                                        onCheckedChange={(v) =>
                                            setAutoOffloadVideos(v === true)
                                        }
                                    />
                                    <span>
                                        Automatically save product videos to
                                        external storage
                                    </span>
                                </label>

                                <label className="flex items-center gap-2">
                                    <Checkbox
                                        checked={deleteLocalAfterSync}
                                        onCheckedChange={(v) =>
                                            setDeleteLocalAfterSync(v === true)
                                        }
                                    />
                                    <span>
                                        Remove local file after cloud upload
                                        (Save local disk)
                                    </span>
                                </label>

                                <label className="flex items-center gap-2 sm:col-span-2">
                                    <Checkbox
                                        checked={showProductVideos}
                                        onCheckedChange={(v) =>
                                            setShowProductVideos(v === true)
                                        }
                                    />
                                    <span>
                                        Display external product videos gallery
                                        on storefront product pages
                                    </span>
                                </label>
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <Button type="submit" disabled={busy}>
                                    <ShieldCheck className="mr-1.5 h-4 w-4" />
                                    Save Storage Settings
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Direct Cloud Photo & Video Uploader */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <CloudUpload className="h-4 w-4 text-sky-600" />
                            Upload Product Photo or Video
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={handleUploadSubmit}
                            className="space-y-3.5"
                        >
                            <p className="text-xs text-muted-foreground">
                                Upload product photos (JPG, PNG, WebP, AVIF) or
                                product videos (MP4, WebM, MOV) directly to{' '}
                                <strong>{settings.providerLabel}</strong>{' '}
                                without storing locally when External Only mode
                                is active.
                            </p>

                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Attach to Product (Optional)
                                </label>
                                <select
                                    value={uploadProductId}
                                    onChange={(e) =>
                                        setUploadProductId(e.target.value)
                                    }
                                    className={selectClass}
                                >
                                    <option value="">
                                        — General Cloud Media —
                                    </option>
                                    {products.map((prod) => (
                                        <option key={prod.id} value={prod.id}>
                                            #{prod.id} — {prod.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Media Type
                                </label>
                                <select
                                    value={uploadMediaType}
                                    onChange={(e) =>
                                        setUploadMediaType(
                                            e.target.value as
                                                | ''
                                                | 'photo'
                                                | 'video',
                                        )
                                    }
                                    className={selectClass}
                                >
                                    <option value="">
                                        Auto-detect from file
                                    </option>
                                    <option value="photo">Product Photo</option>
                                    <option value="video">Product Video</option>
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Title / Alt Text (Optional)
                                </label>
                                <Input
                                    value={uploadTitle}
                                    onChange={(e) =>
                                        setUploadTitle(e.target.value)
                                    }
                                    placeholder="e.g. 360° Product Demo Video"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    Select Photo or Video File
                                </label>
                                <Input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/*,video/mp4,video/webm,video/quicktime,video/ogg"
                                    onChange={(e) =>
                                        setUploadFile(
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={
                                    busy ||
                                    !uploadFile ||
                                    !settings.isConfigured
                                }
                            >
                                <CloudUpload className="mr-1.5 h-4 w-4" />
                                Upload Directly to External Storage
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>

            {/* Filter Bar */}
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-1.5">
                    {[
                        { value: '', label: 'All Media' },
                        { value: 'photo', label: 'Photos' },
                        { value: 'video', label: 'Videos' },
                    ].map((tab) => (
                        <Button
                            key={tab.value}
                            type="button"
                            size="sm"
                            variant={
                                filters.type === tab.value
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => applyFilter({ type: tab.value })}
                        >
                            {tab.label}
                        </Button>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <select
                        value={filters.status}
                        onChange={(e) =>
                            applyFilter({ status: e.target.value })
                        }
                        className="h-8 rounded-md border border-input bg-transparent px-2.5 text-xs"
                    >
                        <option value="">All Statuses</option>
                        <option value="synced">Synced</option>
                        <option value="failed">Failed</option>
                    </select>

                    <select
                        value={filters.provider}
                        onChange={(e) =>
                            applyFilter({ provider: e.target.value })
                        }
                        className="h-8 rounded-md border border-input bg-transparent px-2.5 text-xs"
                    >
                        <option value="">All Providers</option>
                        {providers.map((p) => (
                            <option key={p.value} value={p.value}>
                                {p.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {/* Cloud Files Grid */}
            {files.data.length === 0 ? (
                <EmptyState
                    icon={Cloud}
                    title="No external cloud media files yet"
                    description="Upload product photos or videos above, or click 'Sync Local Photos' to offload existing product images to Wasabi, AWS S3, or Cloudflare Storage."
                />
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {files.data.map((item) => (
                        <Card key={item.id} className="overflow-hidden">
                            <div className="relative flex aspect-video items-center justify-center overflow-hidden border-b bg-muted/40">
                                {item.media_type === 'video' ? (
                                    <video
                                        src={item.remote_url}
                                        controls
                                        preload="metadata"
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <img
                                        src={item.remote_url}
                                        alt={item.title || item.filename}
                                        loading="lazy"
                                        className="max-h-full max-w-full object-contain"
                                    />
                                )}
                                <span className="absolute top-2 left-2 inline-flex items-center gap-1 rounded bg-black/70 px-2 py-0.5 text-[10px] font-medium text-white">
                                    {item.media_type === 'video' ? (
                                        <>
                                            <Film className="h-3 w-3" /> Video
                                        </>
                                    ) : (
                                        <>
                                            <ImageIcon className="h-3 w-3" />{' '}
                                            Photo
                                        </>
                                    )}
                                </span>
                                <span
                                    className={`absolute top-2 right-2 rounded px-2 py-0.5 text-[10px] font-medium ${
                                        item.status === 'synced'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-rose-100 text-rose-800'
                                    }`}
                                >
                                    {item.status}
                                </span>
                            </div>

                            <CardContent className="space-y-2 p-3.5">
                                <div>
                                    <p className="truncate text-sm font-medium">
                                        {item.title || item.filename}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {item.product_name
                                            ? `Product: ${item.product_name}`
                                            : 'Unattached Cloud Asset'}
                                    </p>
                                </div>

                                <div className="flex flex-wrap items-center gap-1.5 text-[11px]">
                                    <Badge variant="outline">
                                        {item.provider_label}
                                    </Badge>
                                    {item.local_deleted ? (
                                        <span className="inline-flex items-center gap-1 font-medium text-emerald-600">
                                            <CheckCircle2 className="h-3 w-3" />{' '}
                                            External Only
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Hybrid (Local + Cloud)
                                        </span>
                                    )}
                                </div>

                                <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                    <span>{formatBytes(item.file_size)}</span>
                                    <a
                                        href={item.remote_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex items-center gap-1 text-sky-600 hover:underline"
                                    >
                                        Open URL{' '}
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                </div>

                                {item.error_message && (
                                    <p className="line-clamp-2 text-xs text-rose-600">
                                        {item.error_message}
                                    </p>
                                )}

                                <div className="flex justify-end pt-1">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-7 px-2 text-xs text-rose-600 hover:text-rose-700"
                                        disabled={busy}
                                        onClick={() => deleteFile(item)}
                                    >
                                        <Trash2 className="mr-1 h-3.5 w-3.5" />
                                        Remove
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {/* Full Multi-Provider Setup Documentation Dialog */}
            <Dialog open={docsModalOpen} onOpenChange={setDocsModalOpen}>
                <DialogContent className="max-h-[85vh] max-w-3xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <BookOpen className="h-5 w-5 text-sky-600" />
                            External Storage Provider Setup & Configuration
                            Documentation
                        </DialogTitle>
                        <DialogDescription>
                            Step-by-step setup guide, required credentials,
                            endpoint formats, and bucket access policies for
                            every supported cloud storage provider.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-wrap gap-1.5 border-b pb-3">
                        {providers.map((p) => (
                            <Button
                                key={p.value}
                                type="button"
                                size="sm"
                                variant={
                                    docTabProvider === p.value
                                        ? 'default'
                                        : 'outline'
                                }
                                className="h-7 text-xs"
                                onClick={() => setDocTabProvider(p.value)}
                            >
                                {p.label}
                            </Button>
                        ))}
                    </div>

                    {modalGuide && (
                        <div className="space-y-4 pt-1 text-sm">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h3 className="text-base font-semibold">
                                        {modalGuide.name}
                                    </h3>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Protocol: {modalGuide.protocol}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        className="h-7 text-xs"
                                        onClick={() => {
                                            setProvider(modalGuide.provider);
                                            setDocsModalOpen(false);
                                        }}
                                    >
                                        Use This Provider
                                    </Button>
                                    {modalGuide.console_url && (
                                        <a
                                            href={modalGuide.console_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-medium text-sky-600 hover:bg-muted"
                                        >
                                            Provider Console{' '}
                                            <ExternalLink className="h-3 w-3" />
                                        </a>
                                    )}
                                    {modalGuide.docs_url && (
                                        <a
                                            href={modalGuide.docs_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-medium hover:bg-muted"
                                        >
                                            Official Docs{' '}
                                            <ExternalLink className="h-3 w-3" />
                                        </a>
                                    )}
                                </div>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                {modalGuide.summary}
                            </p>

                            <div>
                                <h4 className="mb-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Required Fields
                                </h4>
                                <div className="flex flex-wrap gap-1.5">
                                    {modalGuide.required_fields.map((field) => (
                                        <Badge key={field} variant="outline">
                                            {field}
                                        </Badge>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <h4 className="mb-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Step-by-Step Configuration
                                </h4>
                                <ol className="list-decimal space-y-1.5 pl-5 text-xs">
                                    {modalGuide.steps.map((step, i) => (
                                        <li key={i}>{step}</li>
                                    ))}
                                </ol>
                            </div>

                            <div className="grid grid-cols-1 gap-2.5 text-xs sm:grid-cols-3">
                                <div className="rounded-md border p-2.5">
                                    <span className="mb-0.5 block font-semibold">
                                        Example Region
                                    </span>
                                    <span className="font-mono text-muted-foreground">
                                        {modalGuide.example_region}
                                    </span>
                                </div>
                                <div className="rounded-md border p-2.5">
                                    <span className="mb-0.5 block font-semibold">
                                        Endpoint Format
                                    </span>
                                    <span className="font-mono break-all text-muted-foreground">
                                        {modalGuide.example_endpoint}
                                    </span>
                                </div>
                                <div className="rounded-md border p-2.5">
                                    <span className="mb-0.5 block font-semibold">
                                        Public / CDN URL
                                    </span>
                                    <span className="font-mono break-all text-muted-foreground">
                                        {modalGuide.example_cdn}
                                    </span>
                                </div>
                            </div>

                            {modalGuide.policy_snippet && (
                                <div className="rounded-lg border bg-slate-900 p-3 text-xs text-slate-100">
                                    <div className="mb-1.5 flex items-center justify-between">
                                        <span className="font-medium text-sky-300">
                                            Public-Read Bucket Policy (JSON)
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                copyText(
                                                    modalGuide.policy_snippet ||
                                                        '',
                                                )
                                            }
                                            className="inline-flex items-center gap-1 rounded bg-slate-800 px-2 py-0.5 text-[11px] hover:bg-slate-700"
                                        >
                                            {copiedPolicy ? (
                                                <>
                                                    <Check className="h-3 w-3 text-emerald-400" />{' '}
                                                    Copied
                                                </>
                                            ) : (
                                                <>
                                                    <Copy className="h-3 w-3" />{' '}
                                                    Copy JSON
                                                </>
                                            )}
                                        </button>
                                    </div>
                                    <pre className="overflow-x-auto font-mono text-[11px] leading-relaxed text-slate-200">
                                        {modalGuide.policy_snippet}
                                    </pre>
                                </div>
                            )}

                            <p className="border-t pt-2 text-xs text-muted-foreground">
                                <strong>
                                    Delivery & Video Streaming Note:
                                </strong>{' '}
                                {modalGuide.cors_note}
                            </p>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AdminPage>
    );
}
