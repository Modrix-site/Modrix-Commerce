import { Link, usePage } from '@inertiajs/react';
import { Languages, LoaderCircle, Sparkles } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { aiRequest } from '@/lib/ai-request';

export type AiFields =
    | 'name'
    | 'short_description'
    | 'description'
    | 'meta_title'
    | 'meta_description';
type Values = Partial<Record<AiFields, string | null>>;
type Config = {
    enabled: boolean;
    url: string;
    settingsUrl: string | null;
    locale: string;
    tone: string;
    locales: { code: string; name: string }[];
};
const labels: Record<AiFields, string> = {
    name: 'Title',
    short_description: 'Short description',
    description: 'Description',
    meta_title: 'SEO title',
    meta_description: 'SEO description',
};

export function AiDraft({
    source,
    onApply,
    operation = 'generate',
    locale,
    kind = 'product',
    fields = ['name', 'short_description', 'description'],
}: {
    source: Values;
    onApply: (values: Partial<Record<AiFields, string>>) => void;
    operation?: 'generate' | 'translate';
    locale?: string;
    kind?: 'product' | 'category';
    fields?: AiFields[];
}) {
    const config = usePage<{ aiAssistant?: Config | null }>().props.aiAssistant;
    const [open, setOpen] = useState(false);
    const [facts, setFacts] = useState('');
    const [selected, setSelected] = useState<AiFields[]>(fields);
    const [draft, setDraft] = useState<Partial<
        Record<AiFields, string>
    > | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [tone, setTone] = useState(config?.tone ?? 'professional');
    const pending = useRef<AbortController | null>(null);
    useEffect(() => () => pending.current?.abort(), []);
    if (!config) return null;
    const target = locale ?? config.locale;
    const targetName =
        config.locales.find((l) => l.code === target)?.name ?? target;
    const generate = async () => {
        pending.current?.abort();
        const controller = new AbortController();
        pending.current = controller;
        setBusy(true);
        setError('');
        setDraft(null);
        try {
            const result = await aiRequest<{
                draft: Partial<Record<AiFields, string>>;
            }>(
                config.url,
                {
                    operation,
                    kind,
                    target_locale: target,
                    fields: selected,
                    source,
                    facts,
                    tone,
                },
                controller.signal,
            );
            if (!controller.signal.aborted) setDraft(result.draft);
        } catch (failure) {
            if (!controller.signal.aborted)
                setError(
                    failure instanceof Error
                        ? failure.message
                        : 'AI request failed.',
                );
        } finally {
            if (!controller.signal.aborted) setBusy(false);
        }
    };
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                if (!value) {
                    pending.current?.abort();
                    setBusy(false);
                }
                setOpen(value);
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    {operation === 'translate' ? <Languages /> : <Sparkles />}
                    {operation === 'translate'
                        ? 'Translate with AI'
                        : 'Write with AI'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>
                    {operation === 'translate'
                        ? `Translate to ${targetName}`
                        : 'AI product copy'}
                </DialogTitle>
                <DialogDescription>
                    Generate a draft with your configured AI provider. Review
                    the text, apply it to the form, then save when ready.
                </DialogDescription>
                {!config.enabled ? (
                    <div className="grid gap-3 text-sm">
                        <p>AI suggestions are switched off.</p>
                        {config.settingsUrl && (
                            <Link
                                href={config.settingsUrl}
                                className="text-primary underline"
                            >
                                Configure AI Assistant
                            </Link>
                        )}
                    </div>
                ) : (
                    <>
                        <fieldset disabled={busy} className="grid gap-2">
                            <legend className="mb-2 text-sm font-medium">
                                Fields
                            </legend>
                            <div className="flex flex-wrap gap-4">
                                {fields.map((field) => (
                                    <label
                                        key={field}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={selected.includes(field)}
                                            onCheckedChange={(checked) => {
                                                setSelected((values) =>
                                                    checked
                                                        ? [...values, field]
                                                        : values.filter(
                                                              (value) =>
                                                                  value !==
                                                                  field,
                                                          ),
                                                );
                                                setDraft(null);
                                            }}
                                        />
                                        {labels[field]}
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                        {operation === 'generate' && (
                            <>
                                <label className="grid gap-2 text-sm">
                                    Product facts and instructions
                                    <Textarea
                                        value={facts}
                                        onChange={(e) =>
                                            setFacts(e.target.value)
                                        }
                                        placeholder="Materials, size, features, audience… Include only facts you know."
                                        maxLength={4000}
                                        rows={3}
                                        disabled={busy}
                                    />
                                </label>
                                <label className="grid gap-2 text-sm">
                                    Writing tone
                                    <select
                                        value={tone}
                                        onChange={(e) =>
                                            setTone(e.target.value)
                                        }
                                        className="h-9 rounded-md border bg-background px-3"
                                        disabled={busy}
                                    >
                                        <option value="professional">
                                            Professional
                                        </option>
                                        <option value="friendly">
                                            Friendly
                                        </option>
                                        <option value="concise">Concise</option>
                                    </select>
                                </label>
                            </>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Output language: {targetName}. Your current fields
                            are used as context.
                        </p>
                        <InputError message={error} />
                        {error && config.settingsUrl && (
                            <Link
                                href={config.settingsUrl}
                                className="text-sm text-primary underline"
                            >
                                Check AI settings
                            </Link>
                        )}
                        {draft && (
                            <div className="grid gap-4 rounded-lg border p-4">
                                <p className="text-sm font-medium">
                                    Review and edit the suggested text
                                </p>
                                {Object.entries(draft).map(([field, value]) => (
                                    <label
                                        key={field}
                                        className="grid gap-2 text-sm"
                                    >
                                        {labels[field as AiFields]}
                                        <Textarea
                                            value={value}
                                            rows={
                                                field === 'description' ? 6 : 2
                                            }
                                            onChange={(e) =>
                                                setDraft((current) => ({
                                                    ...current,
                                                    [field]: e.target.value,
                                                }))
                                            }
                                        />
                                    </label>
                                ))}
                            </div>
                        )}
                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                                disabled={busy}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                variant={draft ? 'outline' : 'default'}
                                onClick={generate}
                                disabled={busy || selected.length === 0}
                            >
                                {busy && (
                                    <LoaderCircle className="animate-spin" />
                                )}
                                {busy
                                    ? 'Generating…'
                                    : draft
                                      ? 'Generate again'
                                      : operation === 'translate'
                                        ? 'Translate'
                                        : 'Generate draft'}
                            </Button>
                            {draft && (
                                <Button
                                    type="button"
                                    onClick={() => {
                                        onApply(draft);
                                        setOpen(false);
                                        setDraft(null);
                                    }}
                                    disabled={busy}
                                >
                                    Apply to form
                                </Button>
                            )}
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
