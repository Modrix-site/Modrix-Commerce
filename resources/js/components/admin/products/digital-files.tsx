import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, FileDown, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import ProductFileController from '@/actions/App/Http/Controllers/Admin/Catalog/ProductFileController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type DigitalFile = {
    id: number;
    name: string;
    size: number;
    mime: string | null;
};

const MAX_FILES = 10;

function formatSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * Product editor: the files a digital product delivers. Saves on its own
 * (outside the product form), keeping unsaved form edits.
 */
export function DigitalFiles({
    productId,
    files,
}: {
    productId: number;
    files: DigitalFile[];
}) {
    const input = useRef<HTMLInputElement>(null);
    const [progress, setProgress] = useState<number | null>(null);
    const [error, setError] = useState<string | undefined>();
    const options = { preserveScroll: true, preserveState: true } as const;

    const upload = (file: File) => {
        setError(undefined);
        router.post(
            ProductFileController.store.url(productId),
            { file },
            {
                ...options,
                forceFormData: true,
                onProgress: (e) => setProgress(e?.percentage ?? null),
                onError: (errors) => setError(errors.file),
                onFinish: () => {
                    setProgress(null);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
            },
        );
    };

    const update = (
        file: DigitalFile,
        data: { name?: string; position?: number },
    ) =>
        router.put(
            ProductFileController.update.url([productId, file.id]),
            data,
            options,
        );

    return (
        <div className="grid gap-3">
            {files.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No files yet. Customers get a download link for each file
                    after paying.
                </p>
            ) : (
                <ul className="grid divide-y rounded-md border">
                    {files.map((file, index) => (
                        <li
                            key={file.id}
                            className="flex flex-wrap items-center gap-2 p-2 text-sm"
                        >
                            <FileDown className="size-4 shrink-0 text-muted-foreground" />
                            <Input
                                aria-label={`File name for ${file.name}`}
                                defaultValue={file.name}
                                className="h-8 min-w-40 flex-1"
                                onKeyDown={(e) => {
                                    // Enter renames; it must not submit the product form.
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        e.currentTarget.blur();
                                    }
                                }}
                                onBlur={(e) => {
                                    const name = e.target.value.trim();

                                    if (name !== '' && name !== file.name) {
                                        update(file, { name });
                                    }
                                }}
                            />
                            <span className="text-xs text-muted-foreground tabular-nums">
                                {formatSize(file.size)}
                            </span>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-7"
                                disabled={index === 0}
                                onClick={() =>
                                    update(file, { position: index - 1 })
                                }
                                aria-label={`Move ${file.name} up`}
                            >
                                <ArrowUp className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-7"
                                disabled={index === files.length - 1}
                                onClick={() =>
                                    update(file, { position: index + 1 })
                                }
                                aria-label={`Move ${file.name} down`}
                            >
                                <ArrowDown className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-7 text-destructive"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            `Delete “${file.name}”? Customers who bought it lose this download.`,
                                        )
                                    ) {
                                        router.delete(
                                            ProductFileController.destroy.url([
                                                productId,
                                                file.id,
                                            ]),
                                            options,
                                        );
                                    }
                                }}
                                aria-label={`Delete ${file.name}`}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            <input
                ref={input}
                type="file"
                className="hidden"
                onChange={(e) => {
                    const file = e.target.files?.[0];

                    if (file) {
                        upload(file);
                    }
                }}
            />
            <div className="flex items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={progress !== null || files.length >= MAX_FILES}
                    onClick={() => input.current?.click()}
                >
                    <Upload className="size-4" />
                    {progress !== null
                        ? `Uploading… ${progress}%`
                        : 'Upload file'}
                </Button>
                <span className="text-xs text-muted-foreground">
                    Up to {MAX_FILES} files, 500 MB each. Stored privately.
                </span>
            </div>
            <InputError message={error} />
        </div>
    );
}
