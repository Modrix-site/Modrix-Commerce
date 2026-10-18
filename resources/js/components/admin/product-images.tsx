import { router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    ChevronLeft,
    ChevronRight,
    Ellipsis,
    Expand,
    GripVertical,
    Images,
    LayoutGrid,
    List,
    Pencil,
    Search,
    Star,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import ProductImageController from '@/actions/App/Http/Controllers/Admin/Catalog/ProductImageController';
import { MediaPicker } from '@/components/admin/media-picker';
import { ProductImageUploader } from '@/components/admin/products/image-uploader';
import type {
    ProductData,
    VariantData,
} from '@/components/admin/products/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';
import { fromLibrary } from '@/routes/admin/products/images';

type ProductImage = ProductData['images'][number];

export function ProductImages({
    productId,
    images,
    variants = [],
}: {
    productId: number;
    images: ProductData['images'];
    variants?: VariantData[];
}) {
    const can = useCan();
    const [query, setQuery] = useState('');
    const [view, setView] = useState<'grid' | 'list'>('grid');
    const [previewId, setPreviewId] = useState<number | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [dragId, setDragId] = useState<number | null>(null);
    const [dropId, setDropId] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const assigned = (id: number) =>
        variants.filter((variant) => variant.image_id === id);
    const matching = images.filter((image) =>
        `${image.alt ?? ''} ${assigned(image.id)
            .map((variant) => variant.sku)
            .join(' ')}`
            .toLowerCase()
            .includes(query.trim().toLowerCase()),
    );
    const editing = images.find((image) => image.id === editingId);
    const previewIndex = images.findIndex((image) => image.id === previewId);
    const preview = images[previewIndex];

    const saveOrder = (order: number[]) => {
        if (busy || order.every((id, index) => id === images[index]?.id))
            return;
        setError('');
        setBusy(true);
        router.visit(ProductImageController.reorder(productId), {
            data: { order },
            preserveScroll: true,
            preserveState: true,
            onError: (errors) =>
                setError(
                    errors.images ??
                        Object.values(errors)[0] ??
                        'Unable to reorder images.',
                ),
            onFinish: () => setBusy(false),
        });
    };
    const move = (id: number, target: number) => {
        const order = images.map((image) => image.id);
        const source = order.indexOf(id);
        if (source < 0 || target < 0 || target >= order.length) return;
        order.splice(source, 1);
        order.splice(target, 0, id);
        saveOrder(order);
    };
    const remove = (image: ProductImage) => {
        const used = assigned(image.id).length;
        if (
            !window.confirm(
                `Remove this image from the product?${used ? ` ${used} variant(s) will use the main product image instead.` : ''}`,
            )
        )
            return;
        setBusy(true);
        setError('');
        router.visit(
            ProductImageController.destroy({
                product: productId,
                image: image.id,
            }),
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ?? 'Unable to remove image.',
                    ),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <div className="grid gap-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-semibold">Product media</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Build a gallery that shows every detail.
                    </p>
                </div>
                {can('media.manage') && (
                    <MediaPicker
                        action={fromLibrary.url(productId)}
                        label="Media library"
                    />
                )}
            </div>
            <ProductImageUploader productId={productId} images={images} />
            <div className="grid gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <Images className="size-4 text-muted-foreground" />
                        <h3 className="text-sm font-semibold">Gallery</h3>
                        <span className="rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                            {images.length}
                        </span>
                    </div>
                    {images.length > 0 && (
                        <div className="flex items-center gap-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-2.5 left-2.5 size-3.5 text-muted-foreground" />
                                <Input
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Find an image…"
                                    aria-label="Search product gallery"
                                    className="h-8 w-40 pl-8 sm:w-48"
                                />
                            </div>
                            <div
                                className="flex rounded-lg border bg-muted/30 p-0.5"
                                role="group"
                                aria-label="Gallery layout"
                            >
                                <Button
                                    type="button"
                                    variant={
                                        view === 'grid' ? 'secondary' : 'ghost'
                                    }
                                    size="icon"
                                    className="size-7"
                                    aria-label="Grid view"
                                    aria-pressed={view === 'grid'}
                                    onClick={() => setView('grid')}
                                >
                                    <LayoutGrid className="size-3.5" />
                                </Button>
                                <Button
                                    type="button"
                                    variant={
                                        view === 'list' ? 'secondary' : 'ghost'
                                    }
                                    size="icon"
                                    className="size-7"
                                    aria-label="List view"
                                    aria-pressed={view === 'list'}
                                    onClick={() => setView('list')}
                                >
                                    <List className="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
                {images.length > 0 && (
                    <p className="text-xs text-muted-foreground">
                        Drag the handle to reorder, or use the image menu. The
                        cover appears first on your storefront.
                    </p>
                )}
                {error && (
                    <p role="alert" className="text-xs text-destructive">
                        {error}
                    </p>
                )}
                {matching.length === 0 ? (
                    <div className="flex flex-col items-center rounded-xl border bg-muted/20 px-4 py-8 text-center">
                        <Images className="mb-3 size-7 text-muted-foreground" />
                        <p className="text-sm font-medium">
                            {images.length
                                ? 'No matching images'
                                : 'Your gallery starts here'}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {images.length
                                ? 'Search by alternative text or variant SKU.'
                                : 'Upload product photos or choose from the media library.'}
                        </p>
                    </div>
                ) : (
                    <ul
                        className={cn(
                            'grid gap-3',
                            view === 'grid'
                                ? 'grid-cols-2 xl:grid-cols-3'
                                : 'grid-cols-1',
                        )}
                        aria-label="Product image gallery"
                        aria-busy={busy}
                    >
                        {matching.map((image) => {
                            const index = images.findIndex(
                                (item) => item.id === image.id,
                            );
                            const cover = index === 0;
                            const uses = assigned(image.id);
                            return (
                                <li
                                    key={image.id}
                                    onDragOver={(event) => {
                                        if (dragId !== null && !busy) {
                                            event.preventDefault();
                                            setDropId(image.id);
                                        }
                                    }}
                                    onDrop={(event) => {
                                        if (dragId === null) return;
                                        event.preventDefault();
                                        move(dragId, index);
                                        setDragId(null);
                                        setDropId(null);
                                    }}
                                    className={cn(
                                        'group overflow-hidden rounded-xl border bg-card transition-colors',
                                        view === 'list' &&
                                            'flex items-center gap-3 p-2',
                                        dragId === image.id && 'opacity-50',
                                        dropId === image.id &&
                                            dragId !== image.id &&
                                            'border-primary ring-2 ring-primary/20',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'relative overflow-hidden bg-muted/30',
                                            view === 'grid'
                                                ? 'aspect-square'
                                                : 'size-20 shrink-0 rounded-lg',
                                        )}
                                    >
                                        <button
                                            type="button"
                                            className="size-full focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                            aria-label={`Preview image ${index + 1}`}
                                            onClick={() =>
                                                setPreviewId(image.id)
                                            }
                                        >
                                            <img
                                                src={image.url}
                                                alt={image.alt ?? ''}
                                                loading="lazy"
                                                className="size-full object-contain p-2 transition-transform group-hover:scale-[1.03]"
                                            />
                                        </button>
                                        {view === 'grid' && (
                                            <span
                                                className={cn(
                                                    'pointer-events-none absolute top-2 left-2 inline-flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-semibold shadow-xs',
                                                    cover
                                                        ? 'bg-primary text-white'
                                                        : 'bg-card/95 text-muted-foreground',
                                                )}
                                            >
                                                {cover ? (
                                                    <>
                                                        <Star className="size-3" />
                                                        Cover
                                                    </>
                                                ) : (
                                                    `${index + 1}`
                                                )}
                                            </span>
                                        )}
                                        {view === 'grid' && (
                                            <button
                                                type="button"
                                                aria-label={`Expand image ${index + 1}`}
                                                className="absolute right-2 bottom-2 flex size-7 items-center justify-center rounded-lg border bg-card/95 text-muted-foreground shadow-xs hover:text-foreground"
                                                onClick={() =>
                                                    setPreviewId(image.id)
                                                }
                                            >
                                                <Expand className="size-3.5" />
                                            </button>
                                        )}
                                    </div>
                                    <div
                                        className={cn(
                                            'min-w-0 flex-1',
                                            view === 'grid' && 'border-t p-3',
                                        )}
                                    >
                                        <div className="flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                draggable={!busy && !query}
                                                disabled={busy || !!query}
                                                aria-label={`Drag image ${index + 1} to reorder`}
                                                title="Drag to reorder"
                                                onDragStart={(event) => {
                                                    setDragId(image.id);
                                                    event.dataTransfer.effectAllowed =
                                                        'move';
                                                    event.dataTransfer.setData(
                                                        'text/plain',
                                                        String(image.id),
                                                    );
                                                }}
                                                onDragEnd={() => {
                                                    setDragId(null);
                                                    setDropId(null);
                                                }}
                                                className="cursor-grab rounded p-1 text-muted-foreground active:cursor-grabbing disabled:cursor-default disabled:opacity-40"
                                            >
                                                <GripVertical className="size-3.5" />
                                            </button>
                                            <p
                                                className="min-w-0 flex-1 truncate text-xs font-medium"
                                                title={image.alt ?? ''}
                                            >
                                                {image.alt ||
                                                    `Product image ${index + 1}`}
                                            </p>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-7"
                                                        disabled={busy}
                                                        aria-label={`Actions for image ${index + 1}`}
                                                    >
                                                        <Ellipsis className="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent
                                                    align="end"
                                                    className="w-44"
                                                >
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setPreviewId(
                                                                image.id,
                                                            )
                                                        }
                                                    >
                                                        <Expand />
                                                        Preview image
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onSelect={() =>
                                                            setEditingId(
                                                                image.id,
                                                            )
                                                        }
                                                    >
                                                        <Pencil />
                                                        Edit details
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        disabled={cover}
                                                        onSelect={() =>
                                                            move(image.id, 0)
                                                        }
                                                    >
                                                        <Star />
                                                        Make cover
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        disabled={index === 0}
                                                        onSelect={() =>
                                                            move(
                                                                image.id,
                                                                index - 1,
                                                            )
                                                        }
                                                    >
                                                        <ArrowLeft />
                                                        Move earlier
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        disabled={
                                                            index ===
                                                            images.length - 1
                                                        }
                                                        onSelect={() =>
                                                            move(
                                                                image.id,
                                                                index + 1,
                                                            )
                                                        }
                                                    >
                                                        <ArrowRight />
                                                        Move later
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onSelect={() =>
                                                            remove(image)
                                                        }
                                                    >
                                                        <Trash2 />
                                                        Remove image
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                        <div className="mt-1 flex flex-wrap items-center gap-1.5 text-[10px] text-muted-foreground">
                                            {cover && view === 'list' && (
                                                <span className="inline-flex items-center gap-1 text-primary">
                                                    <Star className="size-3" />
                                                    Cover
                                                </span>
                                            )}
                                            {image.width && image.height ? (
                                                <span>
                                                    {image.width} ×{' '}
                                                    {image.height}
                                                </span>
                                            ) : (
                                                <span>Image {index + 1}</span>
                                            )}
                                            {uses.length > 0 && (
                                                <span
                                                    className="rounded bg-accent px-1.5 py-0.5 text-accent-foreground"
                                                    title={uses
                                                        .map(
                                                            (variant) =>
                                                                variant.sku,
                                                        )
                                                        .join(', ')}
                                                >
                                                    {uses.length}{' '}
                                                    {uses.length === 1
                                                        ? 'variant'
                                                        : 'variants'}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
            <Dialog
                open={!!preview}
                onOpenChange={(open) => {
                    if (!open) setPreviewId(null);
                }}
            >
                {preview && (
                    <DialogContent
                        className="max-h-[90svh] overflow-y-auto sm:max-w-4xl"
                        onKeyDown={(event) => {
                            if (event.key === 'ArrowLeft') {
                                event.preventDefault();
                                setPreviewId(
                                    images[
                                        (previewIndex + images.length - 1) %
                                            images.length
                                    ].id,
                                );
                            }
                            if (event.key === 'ArrowRight') {
                                event.preventDefault();
                                setPreviewId(
                                    images[(previewIndex + 1) % images.length]
                                        .id,
                                );
                            }
                        }}
                    >
                        <DialogTitle>Image preview</DialogTitle>
                        <DialogDescription>
                            {preview.alt || 'Product image'} ·{' '}
                            {previewIndex + 1} of {images.length}
                        </DialogDescription>
                        <div className="flex min-h-40 items-center justify-center rounded-xl border bg-muted/30 p-4">
                            <img
                                src={preview.url}
                                alt={preview.alt ?? ''}
                                className="max-h-[60svh] max-w-full object-contain"
                            />
                        </div>
                        <div className="flex items-center justify-between gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={images.length < 2}
                                onClick={() =>
                                    setPreviewId(
                                        images[
                                            (previewIndex + images.length - 1) %
                                                images.length
                                        ].id,
                                    )
                                }
                            >
                                <ChevronLeft className="size-4" />
                                Previous
                            </Button>
                            <span className="text-xs text-muted-foreground">
                                {preview.width && preview.height
                                    ? `${preview.width} × ${preview.height} px`
                                    : `Image ${previewIndex + 1}`}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={images.length < 2}
                                onClick={() =>
                                    setPreviewId(
                                        images[
                                            (previewIndex + 1) % images.length
                                        ].id,
                                    )
                                }
                            >
                                Next
                                <ChevronRight className="size-4" />
                            </Button>
                        </div>
                    </DialogContent>
                )}
            </Dialog>
            {editing && (
                <ImageDetails
                    key={`${editing.id}:${editing.alt}`}
                    productId={productId}
                    image={editing}
                    onClose={() => setEditingId(null)}
                />
            )}
        </div>
    );
}

function ImageDetails({
    productId,
    image,
    onClose,
}: {
    productId: number;
    image: ProductImage;
    onClose: () => void;
}) {
    const form = useForm({ alt: image.alt ?? '' });
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
        >
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogTitle>Image details</DialogTitle>
                <DialogDescription>
                    Describe the product photo for shoppers using screen
                    readers.
                </DialogDescription>
                <img
                    src={image.url}
                    alt={image.alt ?? ''}
                    className="h-52 w-full rounded-xl border bg-muted/30 object-contain p-3"
                />
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(
                            ProductImageController.update.url({
                                product: productId,
                                image: image.id,
                            }),
                            {
                                preserveState: true,
                                preserveScroll: true,
                                onSuccess: onClose,
                            },
                        );
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor={`image-alt-${image.id}`}>
                            Alternative text
                        </Label>
                        <Input
                            id={`image-alt-${image.id}`}
                            value={form.data.alt}
                            maxLength={191}
                            placeholder="e.g. Front view of the emerald cotton shirt"
                            onChange={(event) =>
                                form.setData('alt', event.target.value)
                            }
                        />
                        <div className="flex justify-between gap-2">
                            <InputError message={form.errors.alt} />
                            <span className="ml-auto text-[10px] text-muted-foreground">
                                {form.data.alt.length}/191
                            </span>
                        </div>
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            <Check className="size-4" />
                            {form.processing ? 'Saving…' : 'Save details'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
