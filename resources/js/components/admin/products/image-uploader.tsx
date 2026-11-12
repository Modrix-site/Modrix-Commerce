import { useForm } from '@inertiajs/react';
import { ImagePlus, LoaderCircle, UploadCloud, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import ProductImageController from '@/actions/App/Http/Controllers/Admin/Catalog/ProductImageController';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ProductData } from './types';

export function ProductImageUploader({
    productId,
    images = [],
    maxFiles = 10,
    compact = false,
    onUploaded,
    onBusyChange,
}: {
    productId?: number;
    images?: ProductData['images'];
    maxFiles?: number;
    compact?: boolean;
    onUploaded?: (images: ProductData['images']) => void;
    onBusyChange?: (busy: boolean) => void;
}) {
    const form = useForm<{ images: File[] }>({ images: [] });
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [message, setMessage] = useState('');
    const inputId = useId();
    const errors = [...new Set(Object.values(form.errors))];
    const addFiles = (files: File[]) => {
        if (!productId || form.processing) return;
        const valid: File[] = [];
        const rejected: string[] = [];
        for (const file of files) {
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type))
                rejected.push(`${file.name}: choose JPEG, PNG or WebP.`);
            else if (file.size > 8 * 1024 * 1024)
                rejected.push(`${file.name}: larger than 8 MB.`);
            else if (
                !form.data.images.some(
                    (queued) =>
                        queued.name === file.name &&
                        queued.size === file.size &&
                        queued.lastModified === file.lastModified,
                )
            )
                valid.push(file);
        }
        if (form.data.images.length + valid.length > maxFiles)
            rejected.push(
                `Choose up to ${maxFiles} ${maxFiles === 1 ? 'image' : 'images'} per upload.`,
            );
        form.setData(
            'images',
            [...form.data.images, ...valid].slice(0, maxFiles),
        );
        form.clearErrors();
        setMessage(rejected.join(' '));
    };
    const upload = () => {
        if (!productId || form.data.images.length === 0) return;
        const existing = new Set(images.map((image) => image.id));
        form.post(ProductImageController.store.url(productId), {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onStart: () => onBusyChange?.(true),
            onSuccess: (page) => {
                const saved = page.props.product as ProductData;
                form.reset();
                setMessage('');
                onUploaded?.(
                    saved.images.filter((image) => !existing.has(image.id)),
                );
            },
            onFinish: () => onBusyChange?.(false),
        });
    };

    return (
        <div className="grid gap-3">
            <input
                ref={input}
                id={inputId}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple={maxFiles > 1}
                className="sr-only"
                tabIndex={-1}
                disabled={!productId || form.processing}
                aria-label="Choose product images"
                onChange={(event) => {
                    addFiles(Array.from(event.target.files ?? []));
                    event.target.value = '';
                }}
            />
            <div
                onDragOver={(event) => {
                    if (event.dataTransfer.types.includes('Files')) {
                        event.preventDefault();
                        if (productId && !form.processing) setDragging(true);
                    }
                }}
                onDragLeave={(event) => {
                    if (
                        !event.currentTarget.contains(
                            event.relatedTarget as Node | null,
                        )
                    )
                        setDragging(false);
                }}
                onDrop={(event) => {
                    event.preventDefault();
                    setDragging(false);
                    addFiles(Array.from(event.dataTransfer.files));
                }}
                className={cn(
                    'flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed px-5 text-center transition-colors',
                    compact ? 'py-5' : 'py-9',
                    dragging
                        ? 'border-primary bg-primary/5'
                        : 'border-border bg-muted/20',
                    !productId && 'opacity-60',
                )}
            >
                <div className="flex size-11 items-center justify-center rounded-xl border bg-card text-primary shadow-xs">
                    <UploadCloud className="size-5" aria-hidden />
                </div>
                <div>
                    <p className="text-sm font-semibold">
                        {dragging
                            ? 'Drop images here'
                            : 'Drag & drop product images'}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {productId
                            ? `JPEG, PNG or WebP · 8 MB each · up to ${maxFiles} ${maxFiles === 1 ? 'image' : 'images'}`
                            : 'Create the product first, then add its images.'}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={!productId || form.processing}
                    onClick={() => input.current?.click()}
                >
                    <ImagePlus className="size-4" />
                    Browse files
                </Button>
            </div>
            {(message || errors.length > 0) && (
                <div
                    role="alert"
                    className="rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-xs text-destructive"
                >
                    {message}
                    {errors.map((error) => (
                        <p key={error}>{error}</p>
                    ))}
                </div>
            )}
            {form.data.images.length > 0 && (
                <div className="rounded-xl border bg-card p-3">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <p className="text-xs font-medium">
                            Ready to upload · {form.data.images.length}
                        </p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={form.processing}
                            onClick={() => {
                                form.reset();
                                form.clearErrors();
                                setMessage('');
                            }}
                        >
                            Clear queue
                        </Button>
                    </div>
                    <ul className="grid max-h-60 gap-2 overflow-y-auto">
                        {form.data.images.map((file, index) => (
                            <QueuedImage
                                key={`${file.name}:${file.size}:${file.lastModified}:${index}`}
                                file={file}
                                disabled={form.processing}
                                onRemove={() =>
                                    form.setData(
                                        'images',
                                        form.data.images.filter(
                                            (_, i) => i !== index,
                                        ),
                                    )
                                }
                            />
                        ))}
                    </ul>
                    {form.processing && (
                        <div
                            className="mt-3"
                            role="progressbar"
                            aria-label="Image upload progress"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={form.progress?.percentage ?? 0}
                        >
                            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full bg-primary transition-all"
                                    style={{
                                        width: `${form.progress?.percentage ?? 0}%`,
                                    }}
                                />
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {form.progress?.percentage === 100
                                    ? 'Processing images…'
                                    : `Uploading… ${form.progress?.percentage ?? 0}%`}
                            </p>
                        </div>
                    )}
                    <Button
                        type="button"
                        className="mt-3 w-full"
                        disabled={form.processing}
                        onClick={upload}
                    >
                        {form.processing ? (
                            <LoaderCircle className="size-4 animate-spin" />
                        ) : (
                            <UploadCloud className="size-4" />
                        )}
                        {form.processing
                            ? 'Uploading…'
                            : `Upload ${form.data.images.length} ${form.data.images.length === 1 ? 'image' : 'images'}`}
                    </Button>
                </div>
            )}
        </div>
    );
}

function QueuedImage({
    file,
    disabled,
    onRemove,
}: {
    file: File;
    disabled: boolean;
    onRemove: () => void;
}) {
    const [url, setUrl] = useState('');
    useEffect(() => {
        const preview = URL.createObjectURL(file);
        setUrl(preview);
        return () => URL.revokeObjectURL(preview);
    }, [file]);
    return (
        <li className="flex min-w-0 items-center gap-3 rounded-lg bg-muted/40 p-2">
            <img
                src={url || undefined}
                alt=""
                className="size-11 rounded-md bg-card object-cover"
            />
            <div className="min-w-0 flex-1">
                <p className="truncate text-xs font-medium">{file.name}</p>
                <p className="mt-0.5 text-[11px] text-muted-foreground">
                    {(file.size / 1024 / 1024).toFixed(2)} MB
                </p>
            </div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-7"
                disabled={disabled}
                aria-label={`Remove ${file.name} from upload queue`}
                onClick={onRemove}
            >
                <X className="size-3.5" />
            </Button>
        </li>
    );
}
