import { Form, Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    Copy,
    Eye,
    Plus,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { Component, Suspense, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
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
import { themeCustomizer } from '@/lib/theme-customizer';
import {
    ThemeTextTranslation,
    ThemeTextTranslationProvider,
} from '@/components/admin/themes/text-translation';
import type { TextTranslationConfig } from '@/components/admin/themes/text-translation';
import type {
    ThemeField,
    ThemeImageOption,
    ThemeSectionValue,
    ThemeSummary,
} from '@/types';

type Value = string | number | boolean | null;

export type ThemeCustomizerPageProps = {
    textTranslation?: TextTranslationConfig;
    theme: ThemeSummary;
    customizer?: {
        component: string | null;
        mode: 'extend' | 'replace';
        sections: boolean;
    };
    design: {
        title: string;
        description: string | null;
        fields: ThemeField[];
        values: Record<string, Value>;
        defaults: Record<string, Value>;
        overridden: string[];
        ignored: string[];
        presets: Record<string, Record<string, Value>>;
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

type Props = ThemeCustomizerPageProps;

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

export default function ThemeCustomize(props: Props) {
    const Customizer = themeCustomizer(
        props.theme.name,
        props.customizer?.component,
    );
    const editor = Customizer ? (
        <CustomizerBoundary
            key={props.theme.id}
            fallback={<DefaultThemeCustomizer {...props} />}
        >
            <Suspense
                fallback={
                    <DefaultThemeCustomizer key={props.theme.id} {...props} />
                }
            >
                <Customizer
                    key={props.theme.id}
                    {...props}
                    DefaultCustomizer={DefaultThemeCustomizer}
                    controls={customizerControls}
                />
            </Suspense>
        </CustomizerBoundary>
    ) : (
        <DefaultThemeCustomizer key={props.theme.id} {...props} />
    );
    return (
        <ThemeTextTranslationProvider config={props.textTranslation}>
            {editor}
        </ThemeTextTranslationProvider>
    );
}

class CustomizerBoundary extends Component<
    { children: ReactNode; fallback: ReactNode },
    { failed: boolean }
> {
    state = { failed: false };

    static getDerivedStateFromError() {
        return { failed: true };
    }

    render() {
        return this.state.failed ? (
            <>
                <Alert variant="destructive">
                    <AlertTitle>Theme editor unavailable</AlertTitle>
                    <AlertDescription>
                        The theme's custom editor could not load. You can
                        continue using the default controls below.
                    </AlertDescription>
                </Alert>
                {this.props.fallback}
            </>
        ) : (
            this.props.children
        );
    }
}

export function DefaultThemeCustomizer({
    theme,
    design,
    sections,
    images,
    blocks = [],
    extensionSettings = {},
    customizer,
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
                    <div
                        hidden={
                            tab !== 'home' || customizer?.sections === false
                        }
                    >
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
    const [collapsed, setCollapsed] = useState<Set<string>>(new Set());
    const toggleGroup = (group: string) =>
        setCollapsed((prev) => {
            const next = new Set(prev);
            if (next.has(group)) {
                next.delete(group);
            } else {
                next.add(group);
            }
            return next;
        });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(ThemeController.design.url(themeId), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <CardTitle>{design.title}</CardTitle>
                        <CardDescription>
                            {design.description ??
                                'Leave a setting on "Theme default" to follow the theme, including future updates of it.'}
                        </CardDescription>
                    </div>
                    {Object.keys(design.presets).length > 0 && (
                        <PaletteBar
                            presets={design.presets}
                            fields={design.fields}
                            onApply={(next) =>
                                form.setData('design', {
                                    ...form.data.design,
                                    ...next,
                                })
                            }
                        />
                    )}
                </div>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-8">
                    {groups.map((group) => {
                        // A group ("Fonts and buttons") spans tabs: only this tab's fields.
                        const groupFields = design.fields.filter(
                            (f) =>
                                (f.group ?? 'Other') === group &&
                                tabOf(f) === tab,
                        );
                        const isCollapsed = collapsed.has(group);
                        const overriddenInGroup = groupFields.filter((f) =>
                            design.overridden.includes(f.key),
                        ).length;

                        return (
                            <fieldset key={group} className="grid gap-4">
                                <div className="flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        onClick={() => toggleGroup(group)}
                                        className="flex items-center gap-1 text-sm font-semibold hover:text-foreground"
                                        aria-expanded={!isCollapsed}
                                    >
                                        {isCollapsed ? (
                                            <ArrowDown className="size-3" />
                                        ) : (
                                            <ArrowUp className="size-3" />
                                        )}
                                        {group}
                                    </button>
                                    {overriddenInGroup > 0 && (
                                        <Badge variant="secondary">
                                            {overriddenInGroup} customized
                                        </Badge>
                                    )}
                                </div>
                                {!isCollapsed && (
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        {groupFields.map((field) => (
                                            <DesignInput
                                                key={field.key}
                                                field={field}
                                                value={
                                                    form.data.design[field.key]
                                                }
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
                                )}
                            </fieldset>
                        );
                    })}
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

function PaletteBar({
    presets,
    fields,
    onApply,
}: {
    presets: Record<string, Record<string, Value>>;
    fields: ThemeField[];
    onApply: (next: Record<string, Value>) => void;
}) {
    const names = Object.keys(presets);
    const [open, setOpen] = useState(false);

    if (names.length === 0) {
        return null;
    }

    const apply = (name: string) => {
        const patch = presets[name];
        const next: Record<string, Value> = {};

        for (const [key, value] of Object.entries(patch)) {
            const field = fields.find((f) => f.key === key);
            if (!field) {
                continue;
            }
            // Only override fields that the palette actually sets; a value
            // matching the theme default is the same as leaving it alone.
            next[key] = value;
        }

        onApply(next);
        setOpen(false);
    };

    return (
        <div className="relative">
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setOpen((prev) => !prev)}
                aria-expanded={open}
            >
                Palettes
                <span className="ml-1 text-xs text-muted-foreground">
                    ({names.length})
                </span>
            </Button>
            {open && (
                <div className="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-md border bg-popover p-1 shadow-md">
                    {names.map((name) => (
                        <button
                            key={name}
                            type="button"
                            className="flex w-full items-center justify-between gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent"
                            onClick={() => apply(name)}
                        >
                            <span>{name}</span>
                            <span className="flex">
                                {Object.entries(presets[name])
                                    .slice(0, 4)
                                    .map(([key]) => {
                                        const field = fields.find(
                                            (f) => f.key === key,
                                        );
                                        return field?.type === 'color' ? (
                                            <span
                                                key={key}
                                                className="size-3.5 rounded border"
                                                style={{
                                                    backgroundColor: String(
                                                        presets[name][key],
                                                    ),
                                                }}
                                            />
                                        ) : null;
                                    })}
                            </span>
                        </button>
                    ))}
                </div>
            )}
        </div>
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
                {['string', 'text'].includes(field.type) &&
                    field.translatable === true && (
                        <ThemeTextTranslation
                            fieldKey={field.key}
                            label={field.label}
                            source={String(shown ?? '')}
                        />
                    )}
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
            {field.type === 'boolean' ? (
                <NativeSelect
                    id={id}
                    value={overridden ? String(value) : ''}
                    onChange={(event) =>
                        onChange(
                            event.target.value === ''
                                ? ''
                                : event.target.value === 'true',
                        )
                    }
                >
                    <option value="">
                        Theme default ({fallback ? 'On' : 'Off'})
                    </option>
                    <option value="true">On</option>
                    <option value="false">Off</option>
                </NativeSelect>
            ) : field.type === 'integer' ? (
                <Input
                    id={id}
                    type="number"
                    min={field.min}
                    max={field.max}
                    value={overridden ? String(value) : ''}
                    placeholder={String(fallback ?? '')}
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : field.type === 'color' ? (
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
    const [uploaded, setUploaded] = useState<ThemeImageOption[]>([]);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState('');
    const choices = Array.from(
        new Map(
            [...images, ...uploaded].map((image) => [image.value, image]),
        ).values(),
    );
    const selected = choices.find((i) => i.value === value);
    const upload = async (file: File) => {
        setUploadError('');
        if (
            !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
            file.size > 5 * 1024 * 1024
        ) {
            setUploadError('Choose a PNG, JPEG or WebP image up to 5 MB.');
            return;
        }
        setUploading(true);
        try {
            const body = new FormData();
            body.append('file', file);
            const token = document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.slice(11);
            const response = await fetch('/admin/themes/uploads', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    ...(token
                        ? { 'X-XSRF-TOKEN': decodeURIComponent(token) }
                        : {}),
                },
                body,
            });
            const data = await response.json();
            if (!response.ok)
                throw new Error(
                    (Object.values(data.errors ?? {}).flat()[0] as string) ||
                        'Image upload failed. Try again.',
                );
            setUploaded((previous) => [...previous, data.image]);
            onChange(data.image.value);
        } catch (error) {
            setUploadError(
                error instanceof Error ? error.message : 'Image upload failed.',
            );
        } finally {
            setUploading(false);
        }
    };

    return (
        <div className="grid gap-2">
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
                    {choices.map((image) => (
                        <option key={image.value} value={image.value}>
                            {image.label}
                        </option>
                    ))}
                </NativeSelect>
            </div>
            <Label
                htmlFor={`${id}-upload`}
                className="cursor-pointer text-xs text-primary"
            >
                {uploading ? 'Uploading…' : 'Upload image for this field'}
            </Label>
            <Input
                id={`${id}-upload`}
                aria-label={`Upload image for ${id}`}
                type="file"
                accept="image/png,image/jpeg,image/webp"
                disabled={uploading}
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) void upload(file);
                    event.target.value = '';
                }}
            />
            <InputError message={uploadError} />
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

    const duplicate = (index: number) => {
        const item = list[index];
        const next = [...list];
        next.splice(index + 1, 0, { ...item, settings: { ...item.settings } });
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
                                                aria-label="Duplicate section"
                                                onClick={() => duplicate(i)}
                                            >
                                                <Copy />
                                            </Button>
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
                                                sectionType={section.type}
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
    sectionType,
    field,
    value,
    images,
    error,
    onChange,
}: {
    id: string;
    sectionType: string;
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
                <div className="flex items-center justify-between gap-2">
                    <Label htmlFor={id}>
                        {field.label}
                        {field.required && ' *'}
                    </Label>
                    {field.translatable === true && (
                        <ThemeTextTranslation
                            fieldKey={field.key}
                            sectionType={sectionType}
                            source={text}
                            label={field.label}
                        />
                    )}
                </div>
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

const customizerControls = {
    Design: DesignCard,
    Sections: SectionsCard,
    Images: ImageLibrary,
    Reset: ResetCard,
    Field: DesignInput,
};

export type ThemeCustomizerControls = typeof customizerControls;

ThemeCustomize.layout = {
    breadcrumbs: [{ title: 'Appearance', href: index() }],
};
