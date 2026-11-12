import { Form, Head, Link, router } from '@inertiajs/react';
import { Copy, ImagePlus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Admin/MediaController';
import { EmptyState } from '@/components/admin/empty-state';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { edit as productEdit } from '@/routes/admin/products';
import { index, show } from '@/routes/admin/media';
import type { Paginated } from '@/types';

type MediaItem = {
    id: number;
    url: string;
    filename: string;
    alt: string | null;
    collection: 'library' | 'products' | 'themes';
    mime: string;
    size: number;
    width: number | null;
    height: number | null;
    products: number;
    created_at: string | null;
};

type Usage = { products: { id: number; name: string }[]; themes: boolean };

type Props = {
    media: Paginated<MediaItem>;
    filters: { search: string; collection: string; unused: boolean };
    maxUploadMb: number;
};

const COLLECTIONS: Record<MediaItem['collection'], string> = {
    library: 'Library',
    products: 'Product upload',
    themes: 'Theme upload',
};

const size = (bytes: number) =>
    bytes === 0
        ? '—'
        : bytes > 1024 * 1024
          ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
          : `${Math.ceil(bytes / 1024)} KB`;

export default function MediaIndex({ media, filters, maxUploadMb }: Props) {
    const [selected, setSelected] = useState<MediaItem | null>(null);
    const filtered =
        filters.search !== '' || filters.collection !== '' || filters.unused;

    return (
        <>
            <Head title="Media library" />
            <AdminPage>
                <PageHeader
                    title="Media library"
                    description="Every image of the store in one place: upload once, use it on products and in theme sections. Images are re-encoded on upload, which removes hidden metadata such as GPS location."
                />

                <Form
                    {...MediaController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-3 rounded-lg border border-dashed p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="files">Images</Label>
                                <Input
                                    id="files"
                                    type="file"
                                    name="files[]"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    JPEG, PNG or WebP, up to {maxUploadMb} MB
                                    each, 20 at a time.
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="alt">
                                    Description (alt text, optional)
                                </Label>
                                <Input
                                    id="alt"
                                    name="alt"
                                    placeholder="What the image shows"
                                />
                            </div>
                            <Button type="submit" disabled={processing}>
                                <ImagePlus /> Upload
                            </Button>
                            <div className="sm:col-span-3">
                                <InputError
                                    message={
                                        errors.files ??
                                        errors['files.0'] ??
                                        Object.entries(errors).find(([k]) =>
                                            k.startsWith('files'),
                                        )?.[1]
                                    }
                                />
                            </div>
                        </>
                    )}
                </Form>

                <Form
                    {...index.form()}
                    className="flex flex-wrap items-end gap-3"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            type="search"
                            defaultValue={filters.search}
                            placeholder="File name or description"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="collection">Source</Label>
                        <NativeSelect
                            id="collection"
                            name="collection"
                            defaultValue={filters.collection}
                        >
                            <option value="">All</option>
                            {Object.entries(COLLECTIONS).map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <label className="flex h-9 items-center gap-2 text-sm">
                        <Checkbox
                            name="unused"
                            value="1"
                            defaultChecked={filters.unused}
                        />
                        Not used by products
                    </label>
                    <Button type="submit" variant="secondary">
                        Filter
                    </Button>
                    {filtered && (
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Clear</Link>
                        </Button>
                    )}
                </Form>

                {media.data.length === 0 ? (
                    <EmptyState
                        title={filtered ? 'No images match' : 'No images yet'}
                        description={
                            filtered
                                ? 'Try other filters.'
                                : 'Upload images above, or add them on a product.'
                        }
                    />
                ) : (
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                        {media.data.map((item) => (
                            <li key={item.id}>
                                <button
                                    type="button"
                                    onClick={() => setSelected(item)}
                                    className="grid w-full gap-1 rounded-lg border p-2 text-left text-xs hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <img
                                        src={item.url}
                                        alt={item.alt ?? ''}
                                        loading="lazy"
                                        className="aspect-square w-full rounded object-cover"
                                    />
                                    <span className="truncate font-medium">
                                        {item.alt || item.filename}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {item.products > 0
                                            ? `${item.products} product${item.products > 1 ? 's' : ''}`
                                            : COLLECTIONS[item.collection]}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination
                    from={media.from}
                    to={media.to}
                    total={media.total}
                    prevUrl={media.prev_page_url}
                    nextUrl={media.next_page_url}
                />
            </AdminPage>

            <MediaDetails item={selected} onClose={() => setSelected(null)} />
        </>
    );
}

function MediaDetails({
    item,
    onClose,
}: {
    item: MediaItem | null;
    onClose: () => void;
}) {
    const [usage, setUsage] = useState<Usage | null>(null);
    const [copied, copy] = useClipboard();

    useEffect(() => {
        setUsage(null);

        if (!item) {
            return;
        }

        const controller = new AbortController();
        fetch(show.url(item.id), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((r) => r.json() as Promise<{ usage: Usage }>)
            .then((data) => setUsage(data.usage))
            .catch(() => undefined);

        return () => controller.abort();
    }, [item]);

    const used = usage !== null && (usage.products.length > 0 || usage.themes);

    const remove = () => {
        if (
            item &&
            window.confirm(`Delete ${item.filename}? This cannot be undone.`)
        ) {
            router.visit(MediaController.destroy(item.id), {
                preserveScroll: true,
                onSuccess: onClose,
            });
        }
    };

    return (
        <Dialog
            open={item !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-2xl">
                {item && (
                    <>
                        <DialogTitle className="truncate">
                            {item.filename}
                        </DialogTitle>
                        <DialogDescription>
                            {COLLECTIONS[item.collection]} ·{' '}
                            {item.width && item.height
                                ? `${item.width}×${item.height} px · `
                                : ''}
                            {size(item.size)} · added {item.created_at}
                        </DialogDescription>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <img
                                src={item.url}
                                alt={item.alt ?? ''}
                                className="max-h-72 w-full rounded-md object-contain"
                            />
                            <div className="grid content-start gap-3 text-sm">
                                <Form
                                    {...MediaController.update.form(item.id)}
                                    options={{ preserveScroll: true }}
                                    className="grid gap-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <Label htmlFor="media-alt">
                                                Description (alt text)
                                            </Label>
                                            <Input
                                                id="media-alt"
                                                name="alt"
                                                defaultValue={item.alt ?? ''}
                                            />
                                            <InputError message={errors.alt} />
                                            <div>
                                                <Button
                                                    size="sm"
                                                    type="submit"
                                                    disabled={processing}
                                                >
                                                    Save
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    type="button"
                                    onClick={() => copy(item.url)}
                                >
                                    <Copy />
                                    {copied === item.url
                                        ? 'Copied'
                                        : 'Copy URL'}
                                </Button>
                                <div className="grid gap-1">
                                    <span className="font-medium">Used by</span>
                                    {usage === null ? (
                                        <span className="text-muted-foreground">
                                            Checking…
                                        </span>
                                    ) : !used ? (
                                        <span className="text-muted-foreground">
                                            Not used anywhere.
                                        </span>
                                    ) : (
                                        <ul className="grid gap-1">
                                            {usage.products.map((p) => (
                                                <li key={p.id}>
                                                    <Link
                                                        href={productEdit(p.id)}
                                                        className="hover:underline"
                                                    >
                                                        {p.name}
                                                    </Link>
                                                </li>
                                            ))}
                                            {usage.themes && (
                                                <li>
                                                    <Badge variant="outline">
                                                        Theme settings
                                                    </Badge>
                                                </li>
                                            )}
                                        </ul>
                                    )}
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    type="button"
                                    className="text-destructive"
                                    disabled={usage === null || used}
                                    onClick={remove}
                                    title={
                                        used
                                            ? 'Remove it from products and theme settings first'
                                            : undefined
                                    }
                                >
                                    <Trash2 /> Delete
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

MediaIndex.layout = {
    breadcrumbs: [{ title: 'Media library', href: index() }],
};
