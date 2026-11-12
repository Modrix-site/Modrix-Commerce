import { ImagePlus, Upload, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

export function CategoryImage({
    imageUrl,
    error,
}: {
    imageUrl?: string | null;
    error?: string;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [removed, setRemoved] = useState(false);
    const [validation, setValidation] = useState('');

    useEffect(() => {
        if (!file) {
            setPreview(null);
            return;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    const displayed = preview ?? (removed ? null : imageUrl);
    return (
        <div className="grid gap-3">
            <input
                type="hidden"
                name="remove_image"
                value={removed ? '1' : '0'}
            />
            <input
                ref={input}
                type="file"
                name="image"
                aria-label="Choose category image"
                accept="image/jpeg,image/png,image/webp"
                className="sr-only"
                onChange={(event) => {
                    const selected = event.target.files?.[0];
                    if (!selected) return;
                    if (
                        !['image/jpeg', 'image/png', 'image/webp'].includes(
                            selected.type,
                        ) ||
                        selected.size > 8 * 1024 * 1024
                    ) {
                        setValidation(
                            'Choose a JPEG, PNG or WebP image up to 8 MB.',
                        );
                        event.target.value = '';
                        setFile(null);
                        return;
                    }
                    setValidation('');
                    setFile(selected);
                    setRemoved(false);
                }}
            />
            <div className="flex flex-col items-center gap-4 rounded-lg border border-dashed bg-muted/20 p-6 sm:flex-row">
                {displayed ? (
                    <img
                        src={displayed}
                        alt="Category image preview"
                        className="size-32 rounded-lg border bg-background object-contain"
                    />
                ) : (
                    <div className="flex size-24 items-center justify-center rounded-lg border bg-background text-muted-foreground">
                        <ImagePlus className="size-8" />
                    </div>
                )}
                <div className="grid gap-2 text-center sm:text-left">
                    <p className="text-sm font-medium">
                        {file?.name ?? 'Category image'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        JPEG, PNG or WebP · up to 8 MB. Each subcategory can
                        have its own image.
                    </p>
                    <div className="flex flex-wrap justify-center gap-2 sm:justify-start">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => input.current?.click()}
                        >
                            <Upload />{' '}
                            {displayed ? 'Replace image' : 'Upload image'}
                        </Button>
                        {displayed && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    setFile(null);
                                    setRemoved(true);
                                    setValidation('');
                                    if (input.current) input.current.value = '';
                                }}
                            >
                                <X /> Remove image
                            </Button>
                        )}
                        {removed && imageUrl && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setRemoved(false)}
                            >
                                Undo removal
                            </Button>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Changes apply when you save the category.
                    </p>
                </div>
            </div>
            <InputError message={validation || error} />
        </div>
    );
}
