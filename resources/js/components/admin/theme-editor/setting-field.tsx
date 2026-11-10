import { ImagePlus, Trash2, Upload } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { NativeSelect } from '@/components/admin/form-field';
import { TagsInput } from '@/components/admin/products/tags-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { SettingDef } from './state';

export type ImageOption = { value: string; label: string; url: string };

/**
 * One setting control for a section, block or theme setting. The control is
 * chosen by the schema's type only; values are validated again on the server.
 */
export function SettingField({
    setting,
    value,
    onChange,
    images,
    onUpload,
    error,
}: {
    setting: SettingDef;
    value: unknown;
    onChange: (value: unknown) => void;
    images: ImageOption[];
    onUpload: (file: File) => Promise<ImageOption | null>;
    error?: string;
}) {
    const id = `setting-${setting.id}`;

    if (setting.type === 'header') {
        return (
            <p className="pt-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {setting.content}
            </p>
        );
    }

    const text =
        typeof value === 'string' || typeof value === 'number'
            ? String(value)
            : '';
    const label = (
        <Label htmlFor={id} className="text-xs font-medium">
            {setting.label}
        </Label>
    );
    const info = setting.info ? (
        <p className="text-xs text-muted-foreground">{setting.info}</p>
    ) : null;
    const problem = error ? (
        <p className="text-xs text-destructive">{error}</p>
    ) : null;

    switch (setting.type) {
        case 'checkbox':
            return (
                <div className="grid gap-1">
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            id={id}
                            checked={value === true}
                            onCheckedChange={(next) => onChange(next === true)}
                        />
                        {setting.label}
                    </label>
                    {info}
                    {problem}
                </div>
            );
        case 'textarea':
        case 'richtext':
            return (
                <div className="grid gap-1.5">
                    {label}
                    <Textarea
                        id={id}
                        rows={4}
                        value={text}
                        placeholder={setting.placeholder}
                        onChange={(e) => onChange(e.target.value)}
                    />
                    {info}
                    {problem}
                </div>
            );
        case 'range': {
            const number =
                typeof value === 'number'
                    ? value
                    : Number(text || setting.min || 0);

            return (
                <div className="grid gap-1.5">
                    {label}
                    <div className="flex items-center gap-3">
                        <input
                            id={id}
                            type="range"
                            className="flex-1 accent-primary"
                            min={setting.min}
                            max={setting.max}
                            step={setting.step ?? 1}
                            value={number}
                            onChange={(e) => onChange(Number(e.target.value))}
                        />
                        <Input
                            aria-label={setting.label}
                            className="h-8 w-20"
                            type="number"
                            min={setting.min}
                            max={setting.max}
                            step={setting.step ?? 1}
                            value={number}
                            onChange={(e) => onChange(Number(e.target.value))}
                        />
                        {setting.unit && (
                            <span className="text-xs text-muted-foreground">
                                {setting.unit}
                            </span>
                        )}
                    </div>
                    {info}
                    {problem}
                </div>
            );
        }
        case 'number':
            return (
                <div className="grid gap-1.5">
                    {label}
                    <Input
                        id={id}
                        type="number"
                        min={setting.min}
                        max={setting.max}
                        step={setting.step ?? 1}
                        value={text}
                        onChange={(e) =>
                            onChange(
                                e.target.value === ''
                                    ? ''
                                    : Number(e.target.value),
                            )
                        }
                    />
                    {info}
                    {problem}
                </div>
            );
        case 'select':
            return (
                <div className="grid gap-1.5">
                    {label}
                    <NativeSelect
                        id={id}
                        value={text}
                        onChange={(e) => onChange(e.target.value)}
                    >
                        {(setting.options ?? []).map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </NativeSelect>
                    {info}
                    {problem}
                </div>
            );
        case 'radio':
            return (
                <fieldset className="grid gap-1.5">
                    <legend className="mb-1 text-xs font-medium">
                        {setting.label}
                    </legend>
                    <div className="flex flex-wrap gap-1 rounded-md border p-1">
                        {(setting.options ?? []).map((option) => (
                            <label
                                key={option.value}
                                className="flex-1 cursor-pointer rounded px-2 py-1 text-center text-xs has-[:checked]:bg-primary has-[:checked]:text-primary-foreground"
                            >
                                <input
                                    type="radio"
                                    className="sr-only"
                                    name={id}
                                    checked={text === option.value}
                                    onChange={() => onChange(option.value)}
                                />
                                {option.label}
                            </label>
                        ))}
                    </div>
                    {info}
                    {problem}
                </fieldset>
            );
        case 'color':
            return (
                <div className="grid gap-1.5">
                    {label}
                    <div className="flex items-center gap-2">
                        <input
                            id={id}
                            type="color"
                            className="h-8 w-10 cursor-pointer rounded border bg-transparent"
                            value={
                                /^#[0-9a-f]{6}$/i.test(text) ? text : '#000000'
                            }
                            onChange={(e) => onChange(e.target.value)}
                        />
                        <Input
                            aria-label={`${setting.label} hex`}
                            className="h-8 font-mono text-xs"
                            value={text}
                            onChange={(e) => onChange(e.target.value)}
                        />
                    </div>
                    {info}
                    {problem}
                </div>
            );
        case 'image':
            return (
                <div className="grid gap-1.5">
                    {label}
                    <ImageSetting
                        id={id}
                        value={text}
                        onChange={onChange}
                        images={images}
                        onUpload={onUpload}
                    />
                    {info}
                    {problem}
                </div>
            );
        case 'url':
            return (
                <div className="grid gap-1.5">
                    {label}
                    <Input
                        id={id}
                        value={text}
                        placeholder="/search or https://…"
                        onChange={(e) => onChange(e.target.value)}
                    />
                    {info}
                    {problem}
                </div>
            );
        case 'tags':
            return (
                <div className="grid gap-1.5">
                    {label}
                    <TagSetting id={id} value={value} onChange={onChange} />
                    {info ?? (
                        <p className="text-xs text-muted-foreground">
                            Leave empty to show the most-used tags.
                        </p>
                    )}
                    {problem}
                </div>
            );
        case 'collection':
        case 'product':
            return (
                <div className="grid gap-1.5">
                    {label}
                    <Input
                        id={id}
                        value={text}
                        placeholder={
                            setting.type === 'collection'
                                ? 'Category handle, e.g. laptops'
                                : 'Product handle, e.g. blue-shirt'
                        }
                        onChange={(e) =>
                            onChange(e.target.value.trim().toLowerCase())
                        }
                    />
                    {info}
                    {problem}
                </div>
            );
        default:
            return (
                <div className="grid gap-1.5">
                    {label}
                    <Input
                        id={id}
                        value={text}
                        placeholder={setting.placeholder}
                        onChange={(e) => onChange(e.target.value)}
                    />
                    {info}
                    {problem}
                </div>
            );
    }
}

function TagSetting({
    id,
    value,
    onChange,
}: {
    id: string;
    value: unknown;
    onChange: (value: unknown) => void;
}) {
    const { tagSuggestions } = usePage<{ tagSuggestions?: string[] }>().props;
    const tags = Array.isArray(value)
        ? value.filter((t): t is string => typeof t === 'string')
        : typeof value === 'string' && value !== ''
          ? value.split(',').map((t) => t.trim().toLowerCase())
          : [];

    return (
        <TagsInput
            id={id}
            value={tags}
            onChange={onChange}
            suggestions={tagSuggestions ?? []}
            max={24}
        />
    );
}

function ImageSetting({
    id,
    value,
    onChange,
    images,
    onUpload,
}: {
    id: string;
    value: string;
    onChange: (value: unknown) => void;
    images: ImageOption[];
    onUpload: (file: File) => Promise<ImageOption | null>;
}) {
    const [open, setOpen] = useState(false);
    const [uploading, setUploading] = useState(false);
    const input = useRef<HTMLInputElement>(null);
    const current = images.find((image) => image.value === value);

    return (
        <div className="grid gap-2">
            <button
                id={id}
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex h-28 items-center justify-center overflow-hidden rounded-md border border-dashed bg-muted/40 text-xs text-muted-foreground hover:bg-muted"
            >
                {current ? (
                    <img
                        src={current.url}
                        alt=""
                        className="h-full w-full object-contain"
                    />
                ) : (
                    <span className="flex items-center gap-1">
                        <ImagePlus className="size-4" /> Select image
                    </span>
                )}
            </button>
            <div className="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={uploading}
                    onClick={() => input.current?.click()}
                >
                    <Upload /> {uploading ? 'Uploading…' : 'Upload'}
                </Button>
                {value && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => onChange('')}
                    >
                        <Trash2 /> Remove
                    </Button>
                )}
                <input
                    ref={input}
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    className="hidden"
                    onChange={async (e) => {
                        const file = e.target.files?.[0];
                        e.target.value = '';

                        if (!file) {
                            return;
                        }

                        setUploading(true);
                        const image = await onUpload(file);
                        setUploading(false);

                        if (image) {
                            onChange(image.value);
                        }
                    }}
                />
            </div>
            {open && (
                <div className="grid max-h-64 grid-cols-3 gap-2 overflow-y-auto rounded-md border p-2">
                    {images.length === 0 && (
                        <p className="col-span-3 text-xs text-muted-foreground">
                            No images yet. Upload one.
                        </p>
                    )}
                    {images.map((image) => (
                        <button
                            key={image.value}
                            type="button"
                            title={image.label}
                            onClick={() => {
                                onChange(image.value);
                                setOpen(false);
                            }}
                            className="aspect-square overflow-hidden rounded border hover:ring-2 hover:ring-primary data-[selected=true]:ring-2 data-[selected=true]:ring-primary"
                            data-selected={image.value === value}
                        >
                            <img
                                src={image.url}
                                alt={image.label}
                                className="h-full w-full object-cover"
                                loading="lazy"
                            />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
