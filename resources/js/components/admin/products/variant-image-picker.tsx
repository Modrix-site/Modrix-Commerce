import { Check, ImagePlus, Search, X } from 'lucide-react';
import { useState } from 'react';
import { ProductImageUploader } from '@/components/admin/products/image-uploader';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { ProductData } from './types';

export type VariantImageProps = {
    productId?: number;
    images: ProductData['images'];
};

export function VariantImagePicker({
    productId,
    images,
    imageId,
    label,
    onChange,
}: VariantImageProps & {
    imageId: number | null;
    label: string;
    onChange: (id: number | null) => void;
}) {
    const [open, setOpen] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [query, setQuery] = useState('');
    const image = images.find((item) => item.id === imageId);
    const matching = images.filter((item) =>
        (item.alt ?? '').toLowerCase().includes(query.trim().toLowerCase()),
    );
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (!uploading) {
                    setOpen(value);
                    if (!value) setQuery('');
                }
            }}
        >
            <DialogTrigger asChild>
                <button
                    type="button"
                    aria-label={label}
                    className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-card text-muted-foreground hover:border-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {image ? (
                        <img
                            src={image.url}
                            alt={image.alt ?? ''}
                            className="size-full object-cover"
                        />
                    ) : (
                        <ImagePlus className="size-5" />
                    )}
                </button>
            </DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>{label}</DialogTitle>
                <DialogDescription>
                    Choose or upload an image for this SKU. Save the product to
                    apply your selection.
                </DialogDescription>
                <ProductImageUploader
                    productId={productId}
                    images={images}
                    maxFiles={1}
                    compact
                    onBusyChange={setUploading}
                    onUploaded={(added) => {
                        if (added[0]) {
                            onChange(added[0].id);
                            setOpen(false);
                            setQuery('');
                        }
                    }}
                />
                {images.length > 0 && (
                    <>
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Search product images…"
                                aria-label="Search variant image gallery"
                                className="pl-9"
                            />
                        </div>
                        <div className="grid max-h-64 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-5">
                            {matching.map((item) => (
                                <button
                                    type="button"
                                    key={item.id}
                                    disabled={uploading}
                                    aria-label={item.alt ?? `Image ${item.id}`}
                                    aria-pressed={imageId === item.id}
                                    onClick={() => {
                                        onChange(item.id);
                                        setOpen(false);
                                        setQuery('');
                                    }}
                                    className={cn(
                                        'relative aspect-square overflow-hidden rounded-lg border-2 bg-muted/20 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        imageId === item.id
                                            ? 'border-primary'
                                            : 'border-transparent',
                                    )}
                                >
                                    <img
                                        src={item.url}
                                        alt={item.alt ?? ''}
                                        className="size-full object-contain p-1"
                                    />
                                    {imageId === item.id && (
                                        <span className="absolute top-1 right-1 rounded-full bg-primary p-1 text-white">
                                            <Check className="size-3" />
                                        </span>
                                    )}
                                </button>
                            ))}
                            {matching.length === 0 && (
                                <p className="col-span-full py-3 text-center text-xs text-muted-foreground">
                                    No matching images.
                                </p>
                            )}
                        </div>
                    </>
                )}
                {imageId !== null && (
                    <Button
                        type="button"
                        variant="outline"
                        className="w-fit"
                        disabled={uploading}
                        onClick={() => {
                            onChange(null);
                            setOpen(false);
                        }}
                    >
                        <X className="size-4" />
                        Use main product image
                    </Button>
                )}
            </DialogContent>
        </Dialog>
    );
}
