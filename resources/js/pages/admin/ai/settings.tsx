import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { SuggestedInput } from '@/components/admin/suggested-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { aiRequest } from '@/lib/ai-request';

type Provider = 'openai' | 'kimi' | 'deepseek';
const PROVIDERS: Record<Provider, string> = {
    openai: 'OpenAI',
    kimi: 'Kimi',
    deepseek: 'DeepSeek',
};
type Settings = {
    enabled: boolean;
    provider: Provider;
    openai_model: string;
    kimi_model: string;
    deepseek_model: string;
    openai_api: string;
    kimi_api: string;
    deepseek_api: string;
    kimi_region: string;
    json_mode: boolean;
    max_tokens: number;
    timeout: number;
    tone: string;
};
export default function AiSettings({
    values,
    keyHints,
    suggestions,
    updateUrl,
    testUrl,
    modelsUrl,
}: {
    values: Settings;
    keyHints: Record<Provider, string | null>;
    suggestions: Record<Provider, string[]>;
    updateUrl: string;
    testUrl: string;
    modelsUrl: string;
}) {
    const form = useForm({ ...values, api_key: '', remove_api_key: false });
    const [busy, setBusy] = useState<'test' | 'models' | null>(null);
    const [models, setModels] = useState<string[]>([]);
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const provider = form.data.provider;
    const modelKey = `${provider}_model` as const;
    const apiKey = `${provider}_api` as const;
    const name = PROVIDERS[provider];
    const modelOptions = [
        ...new Set([...(suggestions[provider] ?? []), ...models]),
    ];
    const request = async (operation: 'test' | 'models') => {
        setBusy(operation);
        setMessage('');
        setError('');
        try {
            if (operation === 'test') {
                const result = await aiRequest<{ message: string }>(
                    testUrl,
                    {},
                );
                setMessage(result.message);
            } else {
                const result = await aiRequest<{ models: string[] }>(
                    modelsUrl,
                    {},
                );
                setModels(result.models);
                setMessage(
                    `Loaded ${result.models.length} model IDs. Choose a text generation model supported by your account.`,
                );
            }
        } catch (failure) {
            setError(
                failure instanceof Error
                    ? failure.message
                    : 'Connection failed.',
            );
        } finally {
            setBusy(null);
        }
    };
    return (
        <>
            <Head title="AI Assistant" />
            <AdminPage>
                <PageHeader
                    title="AI Assistant"
                    description="Product writing and language translation with OpenAI, Kimi or DeepSeek."
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Connect your AI provider</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm">
                        <p>
                            Select a provider, save its API key, then load
                            available models or enter an exact model ID. Model
                            versions and snapshots are configurable.
                        </p>
                        <p>
                            API keys are encrypted on the server and kept
                            separately for each provider. Requests send the
                            catalog text and facts you choose to that provider.
                        </p>
                        <p className="text-muted-foreground">
                            The extension is free. API usage follows your
                            provider account’s pricing, balance and limits.
                        </p>
                        <div className="flex gap-4">
                            <a
                                href="https://platform.openai.com/api-keys"
                                target="_blank"
                                rel="noreferrer"
                                className="text-primary underline"
                            >
                                OpenAI API keys
                            </a>
                            <a
                                href="https://platform.moonshot.ai/console/api-keys"
                                target="_blank"
                                rel="noreferrer"
                                className="text-primary underline"
                            >
                                Kimi API keys
                            </a>
                            <a
                                href="https://platform.deepseek.com/api_keys"
                                target="_blank"
                                rel="noreferrer"
                                className="text-primary underline"
                            >
                                DeepSeek API keys
                            </a>
                        </div>
                    </CardContent>
                </Card>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setMessage('');
                        setError('');
                        form.put(updateUrl, {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.setDefaults({
                                    ...form.data,
                                    api_key: '',
                                    remove_api_key: false,
                                });
                                form.setData((current) => ({
                                    ...current,
                                    api_key: '',
                                    remove_api_key: false,
                                }));
                            },
                        });
                    }}
                >
                    <fieldset
                        disabled={Boolean(busy) || form.processing}
                        className="grid gap-4"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>Provider and credentials</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={form.data.enabled}
                                        onCheckedChange={(value) =>
                                            form.setData(
                                                'enabled',
                                                value === true,
                                            )
                                        }
                                    />
                                    Allow AI suggestions
                                </label>
                                <FormField
                                    id="ai-provider"
                                    label="Provider"
                                    error={form.errors.provider}
                                >
                                    <select
                                        id="ai-provider"
                                        className="h-9 rounded-md border bg-background px-3"
                                        value={provider}
                                        onChange={(event) => {
                                            form.setData((current) => ({
                                                ...current,
                                                provider: event.target
                                                    .value as Provider,
                                                api_key: '',
                                                remove_api_key: false,
                                            }));
                                            setModels([]);
                                            setError('');
                                            setMessage('');
                                        }}
                                    >
                                        <option value="openai">OpenAI</option>
                                        <option value="kimi">
                                            Kimi (Moonshot)
                                        </option>
                                        <option value="deepseek">
                                            DeepSeek
                                        </option>
                                    </select>
                                </FormField>
                                <FormField
                                    id="ai-api-key"
                                    label={`${name} API key`}
                                    error={form.errors.api_key}
                                    hint={
                                        keyHints[provider]
                                            ? `Saved key: ${keyHints[provider]}. Leave blank to keep it.`
                                            : 'No key saved for this provider.'
                                    }
                                >
                                    <Input
                                        id="ai-api-key"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.data.api_key}
                                        onChange={(event) =>
                                            form.setData(
                                                'api_key',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Enter your API key"
                                        maxLength={2048}
                                    />
                                </FormField>
                                {keyHints[provider] && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            checked={form.data.remove_api_key}
                                            onCheckedChange={(value) =>
                                                form.setData(
                                                    'remove_api_key',
                                                    value === true,
                                                )
                                            }
                                        />
                                        Remove this provider’s saved key when
                                        saving
                                    </label>
                                )}
                                {provider === 'kimi' && (
                                    <FormField
                                        id="ai-region"
                                        label="Kimi account region"
                                        error={form.errors.kimi_region}
                                    >
                                        <select
                                            id="ai-region"
                                            className="h-9 rounded-md border bg-background px-3"
                                            value={form.data.kimi_region}
                                            onChange={(event) => {
                                                form.setData(
                                                    'kimi_region',
                                                    event.target.value,
                                                );
                                                setModels([]);
                                            }}
                                        >
                                            <option value="international">
                                                International — api.moonshot.ai
                                            </option>
                                            <option value="china">
                                                China — api.moonshot.cn
                                            </option>
                                        </select>
                                    </FormField>
                                )}
                                <FormField
                                    id="ai-model"
                                    label={`${name} model ID`}
                                    error={form.errors[modelKey]}
                                    hint="Pick a suggested or loaded model, or choose Custom to enter any model ID your account supports, including version snapshots."
                                >
                                    <SuggestedInput
                                        key={provider}
                                        id="ai-model"
                                        value={form.data[modelKey]}
                                        onValueChange={(value) =>
                                            form.setData(modelKey, value)
                                        }
                                        suggestions={modelOptions}
                                        customLabel="Custom model ID…"
                                        required
                                        maxLength={200}
                                    />
                                </FormField>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Model compatibility and generation
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <FormField
                                    id="ai-mode"
                                    label="API mode"
                                    error={form.errors[apiKey]}
                                    hint="Responses is the default for OpenAI. Chat uses max_completion_tokens; Legacy Chat uses max_tokens for older model versions."
                                >
                                    <select
                                        id="ai-mode"
                                        className="h-9 rounded-md border bg-background px-3"
                                        value={form.data[apiKey]}
                                        onChange={(event) =>
                                            form.setData(
                                                apiKey,
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {provider === 'openai' && (
                                            <option value="responses">
                                                Responses API
                                            </option>
                                        )}
                                        <option value="chat">
                                            Chat Completions
                                        </option>
                                        <option value="legacy_chat">
                                            Legacy Chat Completions
                                        </option>
                                    </select>
                                </FormField>
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={form.data.json_mode}
                                        onCheckedChange={(value) =>
                                            form.setData(
                                                'json_mode',
                                                value === true,
                                            )
                                        }
                                    />
                                    Request JSON output (turn off for models
                                    without JSON mode)
                                </label>
                                <FormField
                                    id="ai-max-tokens"
                                    label="Maximum output tokens"
                                    error={form.errors.max_tokens}
                                    hint="Includes reasoning tokens on models that use them."
                                >
                                    <Input
                                        id="ai-max-tokens"
                                        type="number"
                                        min={256}
                                        max={32768}
                                        value={form.data.max_tokens}
                                        onChange={(event) =>
                                            form.setData(
                                                'max_tokens',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="ai-timeout"
                                    label="Timeout (seconds)"
                                    error={form.errors.timeout}
                                >
                                    <Input
                                        id="ai-timeout"
                                        type="number"
                                        min={10}
                                        max={120}
                                        value={form.data.timeout}
                                        onChange={(event) =>
                                            form.setData(
                                                'timeout',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="ai-tone"
                                    label="Default writing tone"
                                    error={form.errors.tone}
                                >
                                    <select
                                        id="ai-tone"
                                        className="h-9 rounded-md border bg-background px-3"
                                        value={form.data.tone}
                                        onChange={(event) =>
                                            form.setData(
                                                'tone',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="professional">
                                            Professional
                                        </option>
                                        <option value="friendly">
                                            Friendly
                                        </option>
                                        <option value="concise">Concise</option>
                                    </select>
                                </FormField>
                            </CardContent>
                        </Card>
                    </fieldset>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            disabled={Boolean(busy) || form.processing}
                        >
                            Save settings
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={
                                Boolean(busy) || form.processing || form.isDirty
                            }
                            onClick={() => request('models')}
                        >
                            {busy === 'models'
                                ? 'Loading…'
                                : 'Load available models'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={
                                Boolean(busy) || form.processing || form.isDirty
                            }
                            onClick={() => request('test')}
                        >
                            {busy === 'test' ? 'Testing…' : 'Test connection'}
                        </Button>
                    </div>
                    {form.isDirty && (
                        <p className="text-xs text-muted-foreground">
                            Save your changes before loading models or testing.
                        </p>
                    )}
                    {message && (
                        <p role="status" className="text-sm text-primary">
                            {message}
                        </p>
                    )}
                    <InputError message={error} />
                </form>
            </AdminPage>
        </>
    );
}
