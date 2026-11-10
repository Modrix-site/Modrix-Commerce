import { Link } from '@inertiajs/react';
import { Languages, LoaderCircle, Sparkles } from 'lucide-react';
import { createContext, useContext, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { aiRequest } from '@/lib/ai-request';

export type TextTranslationConfig = {
    url: string;
    sourceLocale: string;
    locales: { code: string; name: string }[];
    aiAvailable: boolean;
};

const TranslationContext = createContext<TextTranslationConfig | undefined>(
    undefined,
);

export function ThemeTextTranslationProvider({
    config,
    children,
}: {
    config: TextTranslationConfig | undefined;
    children: ReactNode;
}) {
    return (
        <TranslationContext.Provider value={config}>
            {children}
        </TranslationContext.Provider>
    );
}

export function ThemeTextTranslation({
    fieldKey,
    source,
    label,
    sectionType,
}: {
    fieldKey: string;
    source: string;
    label: string;
    sectionType?: string;
}) {
    const config = useContext(TranslationContext);
    const [open, setOpen] = useState(false);
    const [locale, setLocale] = useState(config?.locales[0]?.code ?? '');
    const [value, setValue] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [saved, setSaved] = useState(false);
    const pending = useRef<AbortController | null>(null);

    useEffect(() => {
        if (!open || !config || !locale) return;
        const controller = new AbortController();
        pending.current = controller;
        setBusy(true);
        setError('');
        setSaved(false);
        setValue('');
        void aiRequest<{ value: string }>(
            config.url,
            {
                operation: 'read',
                key: fieldKey,
                section_type: sectionType,
                source,
                locale,
            },
            controller.signal,
        )
            .then((result) => {
                if (!controller.signal.aborted) setValue(result.value);
            })
            .catch((reason) => {
                if (!controller.signal.aborted)
                    setError(
                        reason instanceof Error
                            ? reason.message
                            : 'Could not load translation.',
                    );
            })
            .finally(() => {
                if (!controller.signal.aborted) setBusy(false);
            });
        return () => {
            controller.abort();
            pending.current?.abort();
        };
    }, [open, config, locale, fieldKey, sectionType, source]);

    if (!config || !source.trim()) return null;

    const request = async (operation: 'draft' | 'save') => {
        pending.current?.abort();
        const controller = new AbortController();
        pending.current = controller;
        setBusy(true);
        setError('');
        setSaved(false);
        try {
            const result = await aiRequest<{ value: string }>(
                config.url,
                {
                    operation,
                    key: fieldKey,
                    section_type: sectionType,
                    source,
                    locale,
                    value,
                },
                controller.signal,
            );
            if (controller.signal.aborted) return;
            setValue(result.value);
            setSaved(operation === 'save');
        } catch (reason) {
            if (!controller.signal.aborted)
                setError(
                    reason instanceof Error
                        ? reason.message
                        : 'Translation request failed.',
                );
        } finally {
            if (!controller.signal.aborted) setBusy(false);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) pending.current?.abort();
                setOpen(next);
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2 text-xs"
                    aria-label={`Translate ${label}`}
                >
                    <Languages className="size-3" /> Translate
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-xl">
                <DialogTitle>Translate {label}</DialogTitle>
                <DialogDescription>
                    Review the translation, then save it for the selected
                    storefront language. The original text stays unchanged.
                </DialogDescription>
                {config.locales.length === 0 ? (
                    <p className="text-sm">
                        Enable another storefront language in{' '}
                        <Link
                            href="/admin/settings/languages"
                            className="underline"
                        >
                            Languages
                        </Link>{' '}
                        first.
                    </p>
                ) : (
                    <div className="grid gap-4">
                        <label className="grid gap-2 text-sm">
                            Storefront language
                            <select
                                value={locale}
                                disabled={busy}
                                onChange={(event) =>
                                    setLocale(event.target.value)
                                }
                                className="h-9 rounded-md border bg-background px-3"
                            >
                                {config.locales.map((language) => (
                                    <option
                                        key={language.code}
                                        value={language.code}
                                    >
                                        {language.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="grid gap-2">
                            <Label>Original text ({config.sourceLocale})</Label>
                            <p className="rounded-md bg-muted p-3 text-sm whitespace-pre-wrap">
                                {source}
                            </p>
                        </div>
                        <label className="grid gap-2 text-sm">
                            Translation
                            <Textarea
                                rows={5}
                                maxLength={10000}
                                value={value}
                                disabled={busy}
                                onChange={(event) => {
                                    setValue(event.target.value);
                                    setSaved(false);
                                }}
                            />
                        </label>
                        <p className="text-xs text-muted-foreground">
                            Saved translations are used in automatic and manual
                            language modes. Clear and save to restore the usual
                            language fallback. If you changed the original, save
                            the design or homepage too.
                        </p>
                        {!config.aiAvailable && (
                            <p className="text-sm text-muted-foreground">
                                AI translation is unavailable. Enable and
                                configure the AI Assistant extension, or enter a
                                translation here.
                            </p>
                        )}
                        {error && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {error}
                            </p>
                        )}
                        {saved && (
                            <p role="status" className="text-sm">
                                Translation saved.
                            </p>
                        )}
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy || !config.aiAvailable}
                                onClick={() => void request('draft')}
                            >
                                {busy ? (
                                    <LoaderCircle className="animate-spin" />
                                ) : (
                                    <Sparkles />
                                )}{' '}
                                Translate with AI
                            </Button>
                            <Button
                                type="button"
                                disabled={busy}
                                onClick={() => void request('save')}
                            >
                                Save translation
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
