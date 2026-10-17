import { ImageOff } from 'lucide-react';
import { useState } from 'react';
import type { MouseEvent, ReactNode } from 'react';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type GalleryImage = { id: number; url: string; alt: string | null };

/**
 * Product images with thumbnails at the side or below, and optional zoom:
 * hover zoom with a mouse, tap to open the image on touch screens.
 */
export function ProductGallery({
    images,
    index,
    onIndex,
    alt,
    thumbnails,
    zoom,
    overlay,
}: {
    images: GalleryImage[];
    index: number;
    onIndex: (index: number) => void;
    alt: string;
    /** null: below (the usual layout). */
    thumbnails: 'side' | 'bottom' | null;
    zoom: boolean;
    /** Shown over the main image (labels). */
    overlay?: ReactNode;
}) {
    const t = useT();
    const [origin, setOrigin] = useState<string | null>(null);
    const [open, setOpen] = useState(false);
    const image = images[index];
    const side = thumbnails === 'side' && images.length > 1;

    const onMove = (event: MouseEvent<HTMLDivElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();
        const x = ((event.clientX - rect.left) / rect.width) * 100;
        const y = ((event.clientY - rect.top) / rect.height) * 100;
        setOrigin(`${x}% ${y}%`);
    };
    const finePointer = () =>
        typeof window !== 'undefined' &&
        window.matchMedia('(pointer: fine)').matches;

    return (
        <div
            className={cn(
                'store-gallery grid content-start gap-3',
                side && 'sm:grid-cols-[4rem_1fr]',
            )}
            data-thumbnails={side ? 'side' : 'bottom'}
        >
            <div
                className={cn(
                    'relative aspect-square overflow-hidden rounded-lg border bg-muted',
                    side && 'sm:order-2',
                    zoom && image && 'cursor-zoom-in',
                )}
                onMouseMove={zoom ? onMove : undefined}
                onMouseLeave={zoom ? () => setOrigin(null) : undefined}
                onClick={
                    zoom && image && !finePointer()
                        ? () => setOpen(true)
                        : undefined
                }
            >
                {overlay}
                {image ? (
                    <img
                        src={image.url}
                        alt={image.alt ?? alt}
                        className="size-full object-cover transition-transform duration-200 motion-reduce:transition-none"
                        style={
                            zoom && origin
                                ? {
                                      transform: 'scale(2)',
                                      transformOrigin: origin,
                                  }
                                : undefined
                        }
                    />
                ) : (
                    <div className="flex size-full items-center justify-center text-muted-foreground">
                        <ImageOff className="size-8" aria-hidden />
                        <span className="sr-only">
                            {t('No image available')}
                        </span>
                    </div>
                )}
            </div>
            {images.length > 1 && (
                <ul
                    className={cn(
                        'flex gap-2 overflow-x-auto',
                        side && 'sm:order-1 sm:flex-col sm:overflow-x-visible',
                    )}
                    aria-label={t('Product images')}
                >
                    {images.map((img, i) => (
                        <li key={img.id}>
                            <button
                                type="button"
                                onClick={() => onIndex(i)}
                                aria-label={t('Show image :number', {
                                    number: i + 1,
                                })}
                                aria-current={i === index}
                                className={cn(
                                    'size-16 overflow-hidden rounded border',
                                    i === index && 'ring-2 ring-ring',
                                )}
                            >
                                <img
                                    src={img.url}
                                    alt=""
                                    className="size-full object-cover"
                                />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {zoom && image && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent className="max-w-3xl p-2">
                        <DialogTitle className="sr-only">
                            {t('Zoom image')}
                        </DialogTitle>
                        <img
                            src={image.url}
                            alt={image.alt ?? alt}
                            className="w-full rounded"
                        />
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}
