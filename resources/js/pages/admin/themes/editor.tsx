import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Laptop,
    LayoutPanelLeft,
    Loader2,
    Monitor,
    Paintbrush,
    Redo2,
    Smartphone,
    Trash2,
    Undo2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    itemTitle,
    SectionTree,
} from '@/components/admin/theme-editor/section-tree';
import { SettingField } from '@/components/admin/theme-editor/setting-field';
import type { ImageOption } from '@/components/admin/theme-editor/setting-field';
import { useEditorState } from '@/components/admin/theme-editor/state';
import type {
    SectionSchema,
    Selection,
    SettingDef,
    TemplateData,
    TemplateKey,
} from '@/components/admin/theme-editor/state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ThemeSummary } from '@/types';

type DesignField = SettingDef & {
    key: string;
    group?: string;
    options?: never;
} & {
    options?: string[] | { value: string; label: string }[];
};

type Props = {
    theme: ThemeSummary;
    schemas: Record<string, SectionSchema>;
    templates: Record<TemplateKey, TemplateData>;
    defaults: Record<TemplateKey, TemplateData>;
    settings: { fields: DesignField[]; values: Record<string, unknown> };
    images: ImageOption[];
    previews: Record<string, string | null>;
    urls: {
        draft: string;
        save: string;
        publish: string;
        upload: string;
        exit: string;
        customize: string;
    };
};

const TEMPLATES: { name: string; label: string }[] = [
    { name: 'index', label: 'Home page' },
    { name: 'product', label: 'Products' },
    { name: 'collection', label: 'Collections' },
    { name: 'search', label: 'Search' },
    { name: 'cart', label: 'Cart' },
    { name: 'page', label: 'Pages' },
];

type Errors = Record<TemplateKey, Record<string, string>>;

export default function ThemeEditor({
    theme,
    schemas,
    templates,
    settings,
    images: initialImages,
    previews,
    urls,
}: Props) {
    const editor = useEditorState(templates, settings.values, schemas);
    const { actions } = editor;
    const [template, setTemplate] = useState('index');
    const [rail, setRail] = useState<'sections' | 'settings'>('sections');
    const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
    const [selection, setSelection] = useState<Selection>(null);
    const [adding, setAdding] = useState<TemplateKey | null>(null);
    const [images, setImages] = useState(initialImages);
    const [saving, setSaving] = useState<'save' | 'publish' | null>(null);
    const [errors, setErrors] = useState<Errors>({});
    const [notice, setNotice] = useState<{
        tone: 'ok' | 'error';
        text: string;
    } | null>(null);
    const frame = useRef<HTMLIFrameElement>(null);
    const live = theme.state === 'active';
    const templateKey = `template:${template}`;
    const groups = useMemo(
        () => [
            { key: 'group:header', title: 'Header', kind: 'group' as const },
            { key: templateKey, title: 'Template', kind: 'template' as const },
            { key: 'group:footer', title: 'Footer', kind: 'group' as const },
        ],
        [templateKey],
    );

    // Preview: push unsaved changes to the server draft, then refresh the frame.
    const message = useCallback((data: Record<string, unknown>) => {
        frame.current?.contentWindow?.postMessage(
            { source: 'theme-editor', ...data },
            window.location.origin,
        );
    }, []);
    const firstSync = useRef(true);
    useEffect(() => {
        if (firstSync.current) {
            firstSync.current = false;

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            const response = await json(
                urls.draft,
                'PUT',
                { templates: editor.templates, design: editor.design },
                controller.signal,
            ).catch(() => null);

            if (response) {
                setErrors((response.errors ?? {}) as Errors);
                message({ type: 'refresh' });
            }
        }, 350);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [editor.templates, editor.design, urls.draft, message]);

    // Selection from the preview, and highlight of the selection in the preview.
    useEffect(() => {
        const onMessage = (event: MessageEvent) => {
            const data = event.data as {
                source?: string;
                type?: string;
                area?: string;
                section?: string;
                block?: string | null;
            };

            if (
                event.origin !== window.location.origin ||
                data?.source !== 'theme-editor-preview'
            ) {
                return;
            }

            if (data.type === 'select' && data.section) {
                const key =
                    data.area === 'header'
                        ? 'group:header'
                        : data.area === 'footer'
                          ? 'group:footer'
                          : templateKey;
                setRail('sections');
                setSelection(
                    data.block
                        ? {
                              kind: 'block',
                              key,
                              section: data.section,
                              block: data.block,
                          }
                        : { kind: 'section', key, section: data.section },
                );
            }
        };
        window.addEventListener('message', onMessage);

        return () => window.removeEventListener('message', onMessage);
    }, [templateKey]);
    useEffect(() => {
        message({
            type: 'highlight',
            section: selection?.section ?? null,
            block: selection?.kind === 'block' ? selection.block : null,
        });
    }, [selection, message]);

    // Keyboard: undo / redo.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement;

            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 'z' &&
                !['INPUT', 'TEXTAREA'].includes(target.tagName)
            ) {
                event.preventDefault();
                if (event.shiftKey) {
                    actions.redo();
                } else {
                    actions.undo();
                }
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [actions]);

    // Unsaved changes guard.
    useEffect(() => {
        if (!editor.dirty) {
            return;
        }

        const onBeforeUnload = (event: BeforeUnloadEvent) =>
            event.preventDefault();
        window.addEventListener('beforeunload', onBeforeUnload);

        return () => window.removeEventListener('beforeunload', onBeforeUnload);
    }, [editor.dirty]);

    const save = async (): Promise<boolean> => {
        setSaving('save');
        setNotice(null);
        const payload: Record<string, unknown> = {
            templates: Object.fromEntries(
                editor.dirtyTemplates.map((key) => [
                    key,
                    editor.templates[key],
                ]),
            ),
        };
        if (editor.dirtyDesign) {
            payload.design = editor.design;
        }
        const response = await json(urls.save, 'PUT', payload).catch(
            (
                error: Error & { body?: { errors?: Errors; message?: string } },
            ) => {
                setErrors((error.body?.errors ?? {}) as Errors);
                setNotice({
                    tone: 'error',
                    text:
                        error.body?.message ??
                        'Could not save. Check your connection and try again.',
                });

                return null;
            },
        );
        setSaving(null);

        if (!response) {
            return false;
        }

        setErrors({});
        actions.markSaved({
            templates: response.templates as Record<TemplateKey, TemplateData>,
            design: editor.design,
        });
        setNotice({
            tone: 'ok',
            text: live ? 'Saved. Your changes are live.' : 'Saved.',
        });

        return true;
    };

    const publish = async () => {
        if (editor.dirty && !(await save())) {
            return;
        }

        setSaving('publish');
        router.post(
            urls.publish,
            {},
            {
                onFinish: () => setSaving(null),
                onSuccess: () => router.reload(),
            },
        );
    };

    const upload = async (file: File): Promise<ImageOption | null> => {
        const body = new FormData();
        body.append('file', file);
        const response = await fetch(urls.upload, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...xsrf() },
            body,
        });

        if (!response.ok) {
            setNotice({
                tone: 'error',
                text: 'The image could not be uploaded. Use a PNG, JPEG or WebP up to 5 MB.',
            });

            return null;
        }

        const image = ((await response.json()) as { image: ImageOption }).image;
        setImages((current) => [image, ...current]);

        return image;
    };

    const previewUrl = previews[template] ?? null;

    return (
        <>
            <Head title={`${theme.display_name} · Editor`} />
            <div className="fixed inset-0 flex flex-col bg-muted/40 text-foreground">
                {/* Top bar */}
                <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-background px-3">
                    <Button
                        variant="ghost"
                        size="icon"
                        asChild
                        aria-label="Exit editor"
                    >
                        <Link
                            href={urls.exit}
                            onClick={(event) => {
                                if (
                                    editor.dirty &&
                                    !window.confirm(
                                        'Leave the editor? Unsaved changes will be lost.',
                                    )
                                ) {
                                    event.preventDefault();
                                }
                            }}
                        >
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <div className="flex min-w-0 flex-1 items-center justify-center gap-4">
                        <div className="flex min-w-0 items-center gap-2 text-sm font-medium">
                            <LayoutPanelLeft className="size-4 shrink-0 text-muted-foreground" />
                            <span className="truncate">
                                {theme.display_name}
                            </span>
                            <Badge variant={live ? 'default' : 'secondary'}>
                                {live ? 'Live' : 'Draft'}
                            </Badge>
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <span className="sr-only">Page</span>
                            <select
                                className="h-9 rounded-md border bg-background px-2 text-sm"
                                value={template}
                                onChange={(e) => {
                                    setTemplate(e.target.value);
                                    setSelection(null);
                                }}
                            >
                                {TEMPLATES.map((t) => (
                                    <option key={t.name} value={t.name}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                    <div className="flex items-center gap-1">
                        <ToggleIcon
                            active={device === 'desktop'}
                            label="Desktop"
                            onClick={() => setDevice('desktop')}
                        >
                            <Monitor />
                        </ToggleIcon>
                        <ToggleIcon
                            active={device === 'mobile'}
                            label="Mobile"
                            onClick={() => setDevice('mobile')}
                        >
                            <Smartphone />
                        </ToggleIcon>
                        <span className="mx-1 h-6 w-px bg-border" />
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Undo"
                            disabled={!editor.canUndo}
                            onClick={() => actions.undo()}
                        >
                            <Undo2 />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Redo"
                            disabled={!editor.canRedo}
                            onClick={() => actions.redo()}
                        >
                            <Redo2 />
                        </Button>
                        {!live && (
                            <Button
                                variant="outline"
                                disabled={saving !== null}
                                onClick={publish}
                            >
                                {saving === 'publish' && (
                                    <Loader2 className="animate-spin" />
                                )}{' '}
                                Publish
                            </Button>
                        )}
                        <Button
                            disabled={!editor.dirty || saving !== null}
                            onClick={save}
                        >
                            {saving === 'save' && (
                                <Loader2 className="animate-spin" />
                            )}{' '}
                            Save
                        </Button>
                    </div>
                </header>

                <div className="flex min-h-0 flex-1">
                    {/* Rail */}
                    <nav
                        className="flex w-14 shrink-0 flex-col items-center gap-1 border-r bg-background py-2"
                        aria-label="Editor panels"
                    >
                        <ToggleIcon
                            active={rail === 'sections'}
                            label="Sections"
                            onClick={() => setRail('sections')}
                        >
                            <Laptop />
                        </ToggleIcon>
                        <ToggleIcon
                            active={rail === 'settings'}
                            label="Theme settings"
                            onClick={() => setRail('settings')}
                        >
                            <Paintbrush />
                        </ToggleIcon>
                    </nav>

                    {/* Left panel */}
                    <aside className="flex w-80 shrink-0 flex-col border-r bg-background">
                        <div className="flex h-12 items-center border-b px-4 text-sm font-semibold">
                            {rail === 'sections'
                                ? TEMPLATES.find((t) => t.name === template)
                                      ?.label
                                : 'Theme settings'}
                        </div>
                        <div className="min-h-0 flex-1 overflow-y-auto">
                            {rail === 'sections' ? (
                                <SectionTree
                                    groups={groups}
                                    templates={editor.templates}
                                    schemas={schemas}
                                    selection={selection}
                                    onSelect={setSelection}
                                    onAddSection={setAdding}
                                    actions={actions}
                                />
                            ) : (
                                <ThemeSettingsPanel
                                    fields={settings.fields}
                                    values={editor.design}
                                    onChange={(key, value) =>
                                        actions.setDesign(key, value)
                                    }
                                    images={images}
                                    onUpload={upload}
                                />
                            )}
                        </div>
                    </aside>

                    {/* Preview */}
                    <main className="relative flex min-w-0 flex-1 justify-center overflow-auto p-3">
                        {previewUrl ? (
                            <iframe
                                ref={frame}
                                key={previewUrl}
                                title="Storefront preview"
                                src={previewUrl}
                                className={cn(
                                    'h-full rounded-md border bg-white shadow-sm transition-[width]',
                                    device === 'mobile'
                                        ? 'w-[390px]'
                                        : 'w-full',
                                )}
                            />
                        ) : (
                            <p className="m-auto max-w-sm text-center text-sm text-muted-foreground">
                                Add a{' '}
                                {template === 'product'
                                    ? 'product'
                                    : template === 'collection'
                                      ? 'category'
                                      : 'page'}{' '}
                                to your store to preview this template.
                            </p>
                        )}
                        {notice && (
                            <div
                                role="status"
                                className={cn(
                                    'absolute bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-3 rounded-md px-4 py-2 text-sm shadow-lg',
                                    notice.tone === 'ok'
                                        ? 'bg-foreground text-background'
                                        : 'bg-destructive text-white',
                                )}
                            >
                                {notice.text}
                                <button
                                    type="button"
                                    aria-label="Dismiss"
                                    onClick={() => setNotice(null)}
                                >
                                    <X className="size-4" />
                                </button>
                            </div>
                        )}
                    </main>

                    {/* Settings of the selection */}
                    {rail === 'sections' && selection && (
                        <SelectionPanel
                            selection={selection}
                            templates={editor.templates}
                            schemas={schemas}
                            errors={errors}
                            images={images}
                            onUpload={upload}
                            onClose={() => setSelection(null)}
                            actions={actions}
                        />
                    )}
                </div>

                {adding && (
                    <AddSectionDialog
                        templateKey={adding}
                        schemas={schemas}
                        template={
                            editor.templates[adding] ?? {
                                sections: {},
                                order: [],
                            }
                        }
                        onClose={() => setAdding(null)}
                        onAdd={(type, preset) => {
                            const id = actions.addSection(adding, type, preset);
                            setSelection({
                                kind: 'section',
                                key: adding,
                                section: id,
                            });
                            setAdding(null);
                        }}
                    />
                )}
            </div>
        </>
    );
}

function SelectionPanel({
    selection,
    templates,
    schemas,
    errors,
    images,
    onUpload,
    onClose,
    actions,
}: {
    selection: NonNullable<Selection>;
    templates: Record<TemplateKey, TemplateData>;
    schemas: Record<string, SectionSchema>;
    errors: Errors;
    images: ImageOption[];
    onUpload: (file: File) => Promise<ImageOption | null>;
    onClose: () => void;
    actions: ReturnType<typeof useEditorState>['actions'];
}) {
    const section = templates[selection.key]?.sections[selection.section];
    const schema = section ? schemas[section.type] : undefined;

    if (!section || !schema) {
        return null;
    }

    const block =
        selection.kind === 'block'
            ? section.blocks[selection.block]
            : undefined;
    const blockSchema = block
        ? schema.blocks?.find((b) => b.type === block.type)
        : undefined;

    if (selection.kind === 'block' && (!block || !blockSchema)) {
        return null;
    }

    const definitions = (blockSchema ?? schema).settings ?? [];
    const values = (block ?? section).settings;
    const prefix =
        selection.kind === 'block'
            ? `sections.${selection.section}.blocks.${selection.block}.settings.`
            : `sections.${selection.section}.settings.`;
    const keyErrors = errors[selection.key] ?? {};
    const title = itemTitle((blockSchema ?? schema).name, values);

    return (
        <aside className="flex w-80 shrink-0 flex-col border-l bg-background">
            <div className="flex h-12 items-center gap-2 border-b px-4">
                <h2 className="min-w-0 flex-1 truncate text-sm font-semibold">
                    {title.name}
                </h2>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Close settings"
                    onClick={onClose}
                >
                    <X />
                </Button>
            </div>
            <div className="grid min-h-0 flex-1 content-start gap-4 overflow-y-auto p-4">
                {definitions.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        This {selection.kind} has no settings.
                    </p>
                )}
                {definitions.map((setting, i) => (
                    <SettingField
                        key={setting.id ?? `header-${i}`}
                        setting={setting}
                        value={setting.id ? values[setting.id] : undefined}
                        error={
                            setting.id
                                ? keyErrors[prefix + setting.id]
                                : undefined
                        }
                        images={images}
                        onUpload={onUpload}
                        onChange={(value) =>
                            setting.id &&
                            (selection.kind === 'block'
                                ? actions.setBlockSetting(
                                      selection.key,
                                      selection.section,
                                      selection.block,
                                      setting.id,
                                      value,
                                  )
                                : actions.setSectionSetting(
                                      selection.key,
                                      selection.section,
                                      setting.id,
                                      value,
                                  ))
                        }
                    />
                ))}
            </div>
            <div className="border-t p-3">
                <Button
                    variant="ghost"
                    className="w-full justify-start text-destructive hover:text-destructive"
                    onClick={() => {
                        if (selection.kind === 'block') {
                            actions.removeBlock(
                                selection.key,
                                selection.section,
                                selection.block,
                            );
                        } else {
                            actions.removeSection(
                                selection.key,
                                selection.section,
                            );
                        }
                        onClose();
                    }}
                >
                    <Trash2 /> Remove {selection.kind}
                </Button>
            </div>
        </aside>
    );
}

function ThemeSettingsPanel({
    fields,
    values,
    onChange,
    images,
    onUpload,
}: {
    fields: DesignField[];
    values: Record<string, unknown>;
    onChange: (key: string, value: unknown) => void;
    images: ImageOption[];
    onUpload: (file: File) => Promise<ImageOption | null>;
}) {
    const groups = useMemo(() => {
        const map = new Map<string, DesignField[]>();
        for (const field of fields) {
            const group = field.group ?? 'General';
            map.set(group, [...(map.get(group) ?? []), field]);
        }

        return [...map.entries()];
    }, [fields]);
    const [open, setOpen] = useState<string | null>(groups[0]?.[0] ?? null);

    return (
        <div className="grid">
            {groups.map(([group, items]) => (
                <section key={group} className="border-b">
                    <button
                        type="button"
                        className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium hover:bg-muted"
                        aria-expanded={open === group}
                        onClick={() => setOpen(open === group ? null : group)}
                    >
                        {group}
                    </button>
                    {open === group && (
                        <div className="grid gap-4 px-4 pb-4">
                            {items.map((field) => (
                                <SettingField
                                    key={field.key}
                                    setting={designSetting(field)}
                                    value={values[field.key]}
                                    images={images}
                                    onUpload={onUpload}
                                    onChange={(value) =>
                                        onChange(field.key, value)
                                    }
                                />
                            ))}
                        </div>
                    )}
                </section>
            ))}
        </div>
    );
}

/** Theme design fields use the platform's design types; map them to editor controls. */
function designSetting(field: DesignField): SettingDef {
    const options = (field.options ?? []).map((o) =>
        typeof o === 'string' ? { value: o, label: o } : o,
    );
    const type =
        field.type === 'boolean'
            ? 'checkbox'
            : field.type === 'integer'
              ? 'number'
              : field.type === 'string'
                ? 'text'
                : field.type === 'link'
                  ? 'url'
                  : field.type === 'font'
                    ? 'select'
                    : field.type;

    return { ...field, id: field.key, type, options };
}

function AddSectionDialog({
    templateKey,
    schemas,
    template,
    onClose,
    onAdd,
}: {
    templateKey: TemplateKey;
    schemas: Record<string, SectionSchema>;
    template: TemplateData;
    onClose: () => void;
    onAdd: (type: string, preset: number) => void;
}) {
    const [kind, name] = templateKey.split(':') as [
        'group' | 'template',
        string,
    ];
    const [query, setQuery] = useState('');
    const counts = Object.values(template.sections).reduce<
        Record<string, number>
    >((acc, s) => ({ ...acc, [s.type]: (acc[s.type] ?? 0) + 1 }), {});
    const options = Object.entries(schemas)
        .filter(([, schema]) => {
            const list = schema.enabled_on
                ? kind === 'group'
                    ? schema.enabled_on.groups
                    : schema.enabled_on.templates
                : kind === 'template'
                  ? ['*']
                  : [];

            return (list ?? []).includes('*') || (list ?? []).includes(name);
        })
        .flatMap(([type, schema]) =>
            (schema.presets?.length
                ? schema.presets
                : [{ name: schema.name }]
            ).map((preset, index) => ({ type, schema, preset, index })),
        )
        .filter(({ preset }) =>
            preset.name.toLowerCase().includes(query.toLowerCase()),
        );

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center bg-black/30 pt-24"
            onClick={onClose}
        >
            <div
                role="dialog"
                aria-label="Add section"
                className="w-[28rem] rounded-lg border bg-background shadow-xl"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="border-b p-3">
                    <input
                        autoFocus
                        className="h-9 w-full rounded-md border px-3 text-sm"
                        placeholder="Search sections"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                </div>
                <div className="grid max-h-96 overflow-y-auto p-1">
                    {options.length === 0 && (
                        <p className="p-4 text-sm text-muted-foreground">
                            No sections can be added here.
                        </p>
                    )}
                    {options.map(({ type, schema, preset, index }) => {
                        const full =
                            schema.limit !== undefined &&
                            (counts[type] ?? 0) >= schema.limit;

                        return (
                            <button
                                key={`${type}-${index}`}
                                type="button"
                                disabled={full}
                                onClick={() => onAdd(type, index)}
                                className="flex items-center justify-between rounded px-3 py-2 text-left text-sm hover:bg-muted disabled:opacity-50"
                            >
                                {preset.name}
                                {full && (
                                    <span className="text-xs text-muted-foreground">
                                        Already added
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

function ToggleIcon({
    active,
    label,
    onClick,
    children,
}: {
    active: boolean;
    label: string;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <Button
            variant={active ? 'secondary' : 'ghost'}
            size="icon"
            aria-label={label}
            aria-pressed={active}
            title={label}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

function xsrf(): Record<string, string> {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    return token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {};
}

async function json(
    url: string,
    method: string,
    body: unknown,
    signal?: AbortSignal,
): Promise<Record<string, unknown>> {
    const response = await fetch(url, {
        method,
        signal,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...xsrf(),
        },
        body: JSON.stringify(body),
    });
    const data = (await response.json().catch(() => ({}))) as Record<
        string,
        unknown
    >;

    if (!response.ok) {
        throw Object.assign(new Error('Request failed'), { body: data });
    }

    return data;
}
