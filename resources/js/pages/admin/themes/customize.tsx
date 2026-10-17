import { Form, Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    Eye,
    Plus,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ThemeController from '@/actions/App/Http/Controllers/Admin/ThemeController';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import {
    CUSTOMIZER_PANEL_ID,
    CustomizerTabs,
    groupsForTab,
    tabOf,
    useCustomizerTab,
} from '@/components/admin/themes/customizer-tabs';
import type { CustomizerTab } from '@/components/admin/themes/customizer-tabs';
import { ExtensionBlocksCard } from '@/components/admin/themes/extension-blocks-card';
import type { ExtensionBlock } from '@/components/admin/themes/extension-blocks-card';
import { ExtensionSettingsCard } from '@/components/admin/themes/extension-settings-card';
import type { ExtensionSettingsGroup } from '@/components/admin/themes/extension-settings-card';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/admin/themes';
import type {
    ThemeField,
    ThemeImageOption,
    ThemeSectionValue,
    ThemeSummary,
} from '@/types';

type Value = string | number | boolean | null;

type Props = {
    theme: ThemeSummary;
    design: {
        fields: ThemeField[];
        values: Record<string, Value>;
        defaults: Record<string, Value>;
        overridden: string[];
        ignored: string[];
    };
    sections: {
        types: { key: string; label: string; fields: ThemeField[] }[];
        home: ThemeSectionValue[];
        customized: boolean;
        max: number;
    };
    images: ThemeImageOption[];
    /** Extension blocks (placement is store-wide). */
    blocks?: ExtensionBlock[];
    /** Module settings shown in each tab. */
    extensionSettings?: Partial<
        Record<CustomizerTab, ExtensionSettingsGroup[]>
    >;
};

const FONT_LABELS: Record<string, string> = {
    sans: 'Instrument Sans',
    system: 'System UI',
    humanist: 'Humanist sans',
    geometric: 'Geometric sans',
    rounded: 'Rounded sans',
    serif: 'Serif',
    didone: 'Didone (high contrast serif)',
    mono: 'Monospace',
};

function optionLabel(field: ThemeField, option: string): string {
    if (field.type === 'font') {
        return option.startsWith('custom:')
            ? option.slice(7)
            : (FONT_LABELS[option] ?? option);
    }

    return option.charAt(0).toUpperCase() + option.slice(1).replace('_', ' ');
}

/** Tabs whose page settings are store-wide (PageSettingFields::PAGES). */
const PAGE_SETTING_TABS: string[] = [
    'header',
    'catalog',
    'product',
    'cart',
    'checkout',
];

export default function ThemeCustomize({
    theme,
    design,
    sections,
    images,
    blocks = [],
    extensionSettings = {},
}: Props) {
    const [tab, setTab] = useCustomizerTab();

    return (
        <>
            <Head title={`Customize ${theme.display_name}`} />
            <AdminPage>
                <PageHeader
                    title={`Customize ${theme.display_name}`}
                    description={
                        theme.state === 'active'
                            ? 'Changes appear on your storefront as soon as you save.'
                            : 'This theme is not active. Preview it to see your changes before activating.'
                    }
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Form {...ThemeController.preview.form(theme.id)}>
                                <Button variant="outline">
                                    <Eye /> Preview
                                </Button>
                            </Form>
                            <Button variant="ghost" asChild>
                                <Link href={index()}>All themes</Link>
                            </Button>
                        </div>
                    }
                />

                {design.ignored.length > 0 && (
                    <Alert>
                        <AlertTriangle />
                        <AlertTitle>
                            Some of your changes do not apply to this version
                        </AlertTitle>
                        <AlertDescription>
                            {design.ignored.join(', ')}. They are kept in case
                            you switch back, and the theme's defaults are used
                            instead.
                        </AlertDescription>
                    </Alert>
                )}

                <CustomizerTabs
                    fields={design.fields}
                    overridden={design.overridden}
                    value={tab}
                    onChange={setTab}
                />
                <div
                    role="tabpanel"
                    id={CUSTOMIZER_PANEL_ID}
                    aria-labelledby={`customizer-tab-${tab}`}
                    className="grid gap-5"
                >
                    {/* Cards with forms stay mounted (only hidden), so unsaved
                        edits survive switching tabs. */}
                    <div hidden={tab !== 'home'}>
                        <SectionsCard
                            themeId={theme.id}
                            sections={sections}
                            images={images}
                        />
                    </div>
                    <div hidden={groupsForTab(design.fields, tab).length === 0}>
                        {PAGE_SETTING_TABS.includes(tab) && (
                            <p className="mb-3 text-sm text-muted-foreground">
                                Page settings apply to your whole store,
                                whichever theme is active.
                            </p>
                        )}
                        <DesignCard
                            themeId={theme.id}
                            design={design}
                            images={images}
                            tab={tab}
                        />
                    </div>
                    {/* Uploads are used by settings (General) and sections (Homepage). */}
                    {(tab === 'general' || tab === 'home') && (
                        <ImageLibrary images={images} />
                    )}
                    {tab === 'general' && <ResetCard theme={theme} />}
                    {/* Keyed by tab: the block order shown follows the tab's page. */}
                    <ExtensionBlocksCard key={tab} blocks={blocks} tab={tab} />
                    <ExtensionSettingsCard groups={extensionSettings[tab]} />
                </div>
            </AdminPage>
        </>
    );
}

function DesignCard({
    themeId,
    design,
    images,
    tab,
}: {
    themeId: number;
    design: Props['design'];
    images: ThemeImageOption[];
    /** Only this tab's settings are shown; the form still holds all of them. */
    tab: CustomizerTab;
}) {
    // Only overridden keys are sent as values; everything else stays on the theme default.
    const initial: Record<string, Value> = {};
    for (const field of design.fields) {
        initial[field.key] = design.overridden.includes(field.key)
            ? design.values[field.key]
            : '';
    }

    const form = useForm<{ design: Record<string, Value> }>({
        design: initial,
    });
    const groups = groupsForTab(design.fields, tab);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(ThemeController.design.url(themeId), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Design</CardTitle>
                <CardDescription>
                    Leave a setting on "Theme default" to follow the theme,
                    including future updates of it.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-8">
                    {groups.map((group) => (
                        <fieldset key={group} className="grid gap-4">
                            <legend className="mb-2 text-sm font-semibold">
                                {group}
                            </legend>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {design.fields
                                    // A group ("Fonts and buttons") spans tabs: only this tab's fields.
                                    .filter(
                                        (f) =>
                                            (f.group ?? 'Other') === group &&
                                            tabOf(f) === tab,
                                    )
                                    .map((field) => (
                                        <DesignInput
                                            key={field.key}
                                            field={field}
                                            value={form.data.design[field.key]}
                                            fallback={
                                                design.defaults[field.key]
                                            }
                                            images={images}
                                            error={
                                                (
                                                    form.errors as Record<
                                                        string,
                                                        string
                                                    >
                                                )[`design.${field.key}`] ??
                                                (
                                                    form.errors as Record<
                                                        string,
                                                        string
                                                    >
                                                )[field.key]
                                            }
                                            onChange={(value) =>
                                                form.setData('design', {
                                                    ...form.data.design,
                                                    [field.key]: value,
                                                })
                                            }
                                        />
                                    ))}
                            </div>
                        </fieldset>
                    ))}
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            Save design
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function DesignInput({
    field,
    value,
    fallback,
    images,
    error,
    onChange,
}: {
    field: ThemeField;
    value: Value;
    fallback: Value;
    images: ThemeImageOption[];
    error?: string;
    onChange: (value: Value) => void;
}) {
    const id = `design-${field.key}`;
    const overridden = value !== '' && value !== null;
    const shown = overridden ? value : fallback;

    return (
        <div className="grid content-start gap-2">
            <div className="flex items-center justify-between gap-2">
                <Label htmlFor={id}>{field.label}</Label>
                {overridden && (
                    <button
                        type="button"
                        className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        onClick={() => onChange('')}
                    >
                        <RotateCcw className="size-3" /> Theme default
                    </button>
                )}
            </div>
            {field.type === 'color' ? (
                <div className="flex gap-2">
                    <input
                        type="color"
                        aria-label={`${field.label} color picker`}
                        value={String(shown ?? '#000000')}
                        onChange={(e) => onChange(e.target.value)}
                        className="h-9 w-12 cursor-pointer rounded border bg-transparent p-1"
                    />
                    <Input
                        id={id}
                        value={overridden ? String(value) : ''}
                        placeholder={String(fallback ?? '')}
                        onChange={(e) => onChange(e.target.value)}
                        className="font-mono"
                    />
                </div>
            ) : field.type === 'select' || field.type === 'font' ? (
                <NativeSelect
                    id={id}
                    value={overridden ? String(value) : ''}
                    onChange={(e) => onChange(e.target.value)}
                >
                    <option value="">
                        {String(fallback ?? '') === ''
                            ? 'Theme default'
                            : `Theme default (${optionLabel(field, String(fallback))})`}
                    </option>
                    {field.options?.map((option) => (
                        <option key={option} value={option}>
                            {optionLabel(field, option)}
                        </option>
                    ))}
                </NativeSelect>
            ) : field.type === 'image' ? (
                <ImagePicker
                    id={id}
                    value={overridden ? String(value) : ''}
                    fallbackLabel="Theme default"
                    images={images}
                    onChange={onChange}
                />
            ) : field.type === 'text' ? (
                <Textarea
                    id={id}
                    rows={3}
                    maxLength={field.max}
                    value={overridden ? String(value) : ''}
                    placeholder={String(fallback ?? '')}
                    onChange={(e) => onChange(e.target.value)}
                />
            ) : (
                <Input
                    id={id}
                    maxLength={field.max}
                    value={overridden ? String(value) : ''}
                    placeholder={String(fallback ?? '')}
                    onChange={(e) => onChange(e.target.value)}
                />
            )}
            <InputError message={error} />
        </div>
    );
}

function ImagePicker({
    id,
    value,
    fallbackLabel,
    images,
    onChange,
}: {
    id: string;
    value: string;
    fallbackLabel: string;
    images: ThemeImageOption[];
    onChange: (value: string) => void;
}) {
    const selected = images.find((i) => i.value === value);

    return (
        <div className="flex items-center gap-2">
            {selected ? (
                <img
                    src={selected.url}
                    alt=""
                    className="size-9 shrink-0 rounded border object-cover"
                />
            ) : (
                <span className="size-9 shrink-0 rounded border bg-muted" />
            )}
            <NativeSelect
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            >
                <option value="">{fallbackLabel}</option>
                {images.map((image) => (
                    <option key={image.value} value={image.value}>
                        {image.label}
                    </option>
                ))}
            </NativeSelect>
        </div>
    );
}

function ImageLibrary({ images }: { images: ThemeImageOption[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Images</CardTitle>
                <CardDescription>
                    Upload a logo, favicon or banner image once, then choose it
                    in any image setting. Images are re-encoded for safety.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {images.length > 0 && (
                    <ul className="flex flex-wrap gap-2">
                        {images.map((image) => (
                            <li key={image.value} title={image.label}>
                                <img
                                    src={image.url}
                                    alt={image.label}
                                    className="size-16 rounded border object-cover"
                                />
                            </li>
                        ))}
                    </ul>
                )}
                <Form
                    {...ThemeController.upload.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex flex-col gap-2 sm:flex-row sm:items-center"
                >
                    {({ processing, errors }) => (
                        <>
                            <Input
                                type="file"
                                name="file"
                                accept="image/jpeg,image/png,image/webp"
                                required
                                aria-label="Choose an image"
                                className="sm:w-80"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                Upload
                            </Button>
                            <InputError message={errors.file} />
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function SectionsCard({
    themeId,
    sections,
    images,
}: {
    themeId: number;
    sections: Props['sections'];
    images: ThemeImageOption[];
}) {
    const form = useForm<{ sections: ThemeSectionValue[] }>({
        sections: sections.home,
    });
    const [adding, setAdding] = useState(sections.types[0]?.key ?? '');
    const list = form.data.sections;
    const typeOf = (key: string) => sections.types.find((t) => t.key === key);
    const errors = form.errors as Record<string, string>;

    const update = (next: ThemeSectionValue[]) =>
        form.setData('sections', next);

    const move = (from: number, to: number) => {
        const next = [...list];
        const [item] = next.splice(from, 1);
        next.splice(to, 0, item);
        update(next);
    };

    const add = () => {
        const type = typeOf(adding);

        if (!type) {
            return;
        }

        const settings: ThemeSectionValue['settings'] = {};
        for (const field of type.fields) {
            settings[field.key] =
                field.default ?? (field.type === 'boolean' ? false : '');
        }

        update([...list, { type: type.key, settings }]);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(ThemeController.sections.url(themeId), {
            preserveScroll: true,
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    Home page
                    {sections.customized && (
                        <Badge variant="secondary">Customized</Badge>
                    )}
                </CardTitle>
                <CardDescription>
                    The home page is a list of sections. Reorder, edit, add or
                    remove them. Content from enabled modules appears after
                    these sections.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-4">
                    {list.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No sections. Your home page will only show content
                            from modules.
                        </p>
                    )}
                    <ol className="grid gap-3">
                        {list.map((section, i) => {
                            const type = typeOf(section.type);

                            return (
                                <li
                                    key={`${i}-${section.type}`}
                                    className="grid gap-3 rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {i + 1}.{' '}
                                            {type?.label ?? section.type}
                                        </span>
                                        <div className="ml-auto flex gap-1">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Move up"
                                                disabled={i === 0}
                                                onClick={() => move(i, i - 1)}
                                            >
                                                <ArrowUp />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Move down"
                                                disabled={i === list.length - 1}
                                                onClick={() => move(i, i + 1)}
                                            >
                                                <ArrowDown />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Remove section"
                                                onClick={() =>
                                                    update(
                                                        list.filter(
                                                            (_, j) => j !== i,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {type?.fields.map((field) => (
                                            <SectionInput
                                                key={field.key}
                                                id={`section-${i}-${field.key}`}
                                                field={field}
                                                value={
                                                    section.settings[
                                                        field.key
                                                    ] ?? ''
                                                }
                                                images={images}
                                                error={
                                                    errors[
                                                        `sections.${i}.${field.key}`
                                                    ]
                                                }
                                                onChange={(value) =>
                                                    update(
                                                        list.map((s, j) =>
                                                            j === i
                                                                ? {
                                                                      ...s,
                                                                      settings:
                                                                          {
                                                                              ...s.settings,
                                                                              [field.key]:
                                                                                  value,
                                                                          },
                                                                  }
                                                                : s,
                                                        ),
                                                    )
                                                }
                                            />
                                        ))}
                                    </div>
                                    <InputError
                                        message={
                                            errors[`sections.${i}.type`] ??
                                            errors[`sections.${i}.settings`]
                                        }
                                    />
                                </li>
                            );
                        })}
                    </ol>
                    <InputError message={errors['sections.sections']} />
                    <div className="flex flex-wrap items-center gap-2">
                        <NativeSelect
                            aria-label="Section type to add"
                            value={adding}
                            onChange={(e) => setAdding(e.target.value)}
                            className="w-56"
                        >
                            {sections.types.map((t) => (
                                <option key={t.key} value={t.key}>
                                    {t.label}
                                </option>
                            ))}
                        </NativeSelect>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={add}
                            disabled={list.length >= sections.max}
                        >
                            <Plus /> Add section
                        </Button>
                        <Button
                            type="submit"
                            className="ml-auto"
                            disabled={form.processing}
                        >
                            Save home page
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function SectionInput({
    id,
    field,
    value,
    images,
    error,
    onChange,
}: {
    id: string;
    field: ThemeField;
    value: Value;
    images: ThemeImageOption[];
    error?: string;
    onChange: (value: Value) => void;
}) {
    const text =
        typeof value === 'string' || typeof value === 'number'
            ? String(value)
            : '';

    return (
        <div
            className={
                field.type === 'text'
                    ? 'grid gap-2 sm:col-span-2'
                    : 'grid gap-2'
            }
        >
            {field.type === 'boolean' ? (
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        id={id}
                        checked={value === true}
                        onCheckedChange={(checked) =>
                            onChange(checked === true)
                        }
                    />
                    {field.label}
                </label>
            ) : (
                <Label htmlFor={id}>
                    {field.label}
                    {field.required && ' *'}
                </Label>
            )}
            {field.type === 'select' ? (
                <NativeSelect
                    id={id}
                    value={text}
                    onChange={(e) => onChange(e.target.value)}
                >
                    {field.options?.map((option) => (
                        <option key={option} value={option}>
                            {optionLabel(field, option)}
                        </option>
                    ))}
                </NativeSelect>
            ) : field.type === 'image' ? (
                <ImagePicker
                    id={id}
                    value={text}
                    fallbackLabel="No image"
                    images={images}
                    onChange={onChange}
                />
            ) : field.type === 'text' ? (
                <Textarea
                    id={id}
                    rows={3}
                    maxLength={field.max}
                    value={text}
                    onChange={(e) => onChange(e.target.value)}
                />
            ) : field.type === 'integer' ? (
                <Input
                    id={id}
                    type="number"
                    min={field.min}
                    max={field.max}
                    value={text}
                    onChange={(e) => onChange(e.target.value)}
                />
            ) : field.type === 'boolean' ? null : (
                <Input
                    id={id}
                    maxLength={field.type === 'link' ? 500 : field.max}
                    placeholder={
                        field.type === 'link'
                            ? '/search or https://…'
                            : undefined
                    }
                    value={text}
                    onChange={(e) => onChange(e.target.value)}
                />
            )}
            <InputError message={error} />
        </div>
    );
}

function ResetCard({ theme }: { theme: ThemeSummary }) {
    const [typed, setTyped] = useState('');

    return (
        <Card>
            <CardHeader>
                <CardTitle>Reset</CardTitle>
                <CardDescription>
                    Remove all of your design and home page changes to{' '}
                    {theme.display_name} and go back to the theme's defaults.
                    Uploaded images stay in the image library.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Dialog>
                    <DialogTrigger asChild>
                        <Button variant="outline" disabled={!theme.customized}>
                            <RotateCcw /> Reset to theme defaults
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Reset {theme.display_name}?</DialogTitle>
                        <DialogDescription>
                            All your changes to this theme are removed. This
                            cannot be undone.
                        </DialogDescription>
                        <Form
                            {...ThemeController.reset.form(theme.id)}
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="reset-confirmation">
                                            Type{' '}
                                            <span className="font-mono">
                                                {theme.name}
                                            </span>{' '}
                                            to confirm
                                        </Label>
                                        <Input
                                            id="reset-confirmation"
                                            name="confirmation"
                                            autoComplete="off"
                                            value={typed}
                                            onChange={(e) =>
                                                setTyped(e.target.value)
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors.confirmation ??
                                                errors.theme
                                            }
                                        />
                                    </div>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={
                                                processing ||
                                                typed !== theme.name
                                            }
                                        >
                                            Reset theme
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    );
}

ThemeCustomize.layout = {
    breadcrumbs: [{ title: 'Appearance', href: index() }],
};
