import { Head, useForm } from '@inertiajs/react';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
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
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Field = {
    key: string;
    label: string;
    type: 'string' | 'secret' | 'select' | 'text';
    required?: boolean;
    help?: string;
    options?: { value: string; label: string }[];
    saved_hint: string | null;
};
type Provider = {
    code: string;
    label: string;
    fields: Field[];
    values: Record<string, string>;
};
type EventSetting = { enabled: boolean; template: string };
type Settings = {
    provider: string;
    default_country_code: string;
    require_at_registration: boolean;
    code_minutes: number;
    verification_template: string;
    events: Record<string, EventSetting>;
    providers: Provider[];
    problems: string[];
};
type Message = {
    id: number;
    to: string;
    purpose: string;
    event: string | null;
    body: string;
    provider: string;
    status: string;
    error: string | null;
    created_at: string | null;
};

const EVENT_LABELS: Record<string, string> = {
    order_placed: 'Order placed',
    order_paid: 'Payment confirmed',
    order_shipped: 'Order shipped',
    order_delivered: 'Order delivered',
    order_cancelled: 'Order cancelled',
    order_refunded: 'Refund issued',
};

export default function SmsAdmin({
    settings,
    placeholders,
    stats,
    messages,
    urls,
}: {
    settings: Settings;
    placeholders: string[];
    stats: {
        verified: number;
        awaiting: number;
        sent_30d: number;
        failed_30d: number;
    };
    messages: Message[];
    urls: { update: string; test: string };
}) {
    const configs: Record<string, Record<string, string>> = Object.fromEntries(
        settings.providers.map((p) => [p.code, { ...p.values }]),
    );
    const form = useForm({
        provider: settings.provider,
        default_country_code: settings.default_country_code,
        require_at_registration: settings.require_at_registration,
        code_minutes: settings.code_minutes,
        verification_template: settings.verification_template,
        events: settings.events,
        configs,
    });
    const test = useForm({ to: '' });
    const provider = settings.providers.find(
        (p) => p.code === form.data.provider,
    );
    const config = form.data.configs[form.data.provider] ?? {};
    const errors = form.errors as Record<string, string | undefined>;
    const ready = settings.problems.length === 0;

    const setConfig = (key: string, value: string) =>
        form.setData('configs', {
            ...form.data.configs,
            [form.data.provider]: { ...config, [key]: value },
        });

    return (
        <>
            <Head title="SMS" />
            <AdminPage>
                <PageHeader
                    title="SMS"
                    description="Confirm customers’ mobile numbers at sign-up and text them order updates."
                    actions={
                        <Badge variant={ready ? 'secondary' : 'destructive'}>
                            {ready ? 'Ready' : 'Setup required'}
                        </Badge>
                    }
                />

                {!ready && (
                    <div
                        role="alert"
                        className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
                    >
                        <p className="font-medium">
                            Texts are paused. Customers can register without a
                            mobile number until this is fixed:
                        </p>
                        <ul className="mt-1 list-disc pl-5">
                            {settings.problems.map((p) => (
                                <li key={p}>{p}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <dl className="grid gap-3 sm:grid-cols-4">
                    {[
                        ['Confirmed numbers', stats.verified],
                        ['Awaiting code', stats.awaiting],
                        ['Sent (30 days)', stats.sent_30d],
                        ['Failed (30 days)', stats.failed_30d],
                    ].map(([label, value]) => (
                        <div
                            key={label}
                            className="rounded-lg border bg-card p-4"
                        >
                            <dt className="text-xs text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="text-2xl font-semibold tabular-nums">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>

                <form
                    className="grid gap-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({
                            provider: data.provider,
                            default_country_code: data.default_country_code,
                            require_at_registration:
                                data.require_at_registration,
                            code_minutes: data.code_minutes,
                            verification_template: data.verification_template,
                            events: data.events,
                            config: data.configs[data.provider] ?? {},
                        }));
                        form.put(urls.update, { preserveScroll: true });
                    }}
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Provider</CardTitle>
                            <CardDescription>
                                Secrets are encrypted and never shown again;
                                leave a secret empty to keep the saved one.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="provider"
                                label="SMS provider"
                                error={errors.provider}
                            >
                                <NativeSelect
                                    id="provider"
                                    value={form.data.provider}
                                    onChange={(e) =>
                                        form.setData('provider', e.target.value)
                                    }
                                >
                                    {settings.providers.map((p) => (
                                        <option key={p.code} value={p.code}>
                                            {p.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            {provider?.fields.map((field) => {
                                const id = `config-${field.key}`;
                                const label =
                                    field.label + (field.required ? ' *' : '');
                                const error = errors[`config.${field.key}`];

                                return (
                                    <div
                                        key={`${provider.code}-${field.key}`}
                                        className={
                                            field.type === 'text'
                                                ? 'md:col-span-2'
                                                : undefined
                                        }
                                    >
                                        <FormField
                                            id={id}
                                            label={label}
                                            error={error}
                                            hint={
                                                field.type === 'secret' &&
                                                field.saved_hint
                                                    ? `Saved (${field.saved_hint}). ${field.help ?? ''}`
                                                    : field.help
                                            }
                                        >
                                            {field.type === 'select' ? (
                                                <NativeSelect
                                                    id={id}
                                                    value={
                                                        config[field.key] ?? ''
                                                    }
                                                    onChange={(e) =>
                                                        setConfig(
                                                            field.key,
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    {field.options?.map((o) => (
                                                        <option
                                                            key={o.value}
                                                            value={o.value}
                                                        >
                                                            {o.label}
                                                        </option>
                                                    ))}
                                                </NativeSelect>
                                            ) : field.type === 'text' ? (
                                                <Textarea
                                                    id={id}
                                                    rows={3}
                                                    className="font-mono text-xs"
                                                    value={
                                                        config[field.key] ?? ''
                                                    }
                                                    onChange={(e) =>
                                                        setConfig(
                                                            field.key,
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            ) : (
                                                <Input
                                                    id={id}
                                                    type={
                                                        field.type === 'secret'
                                                            ? 'password'
                                                            : 'text'
                                                    }
                                                    autoComplete="off"
                                                    value={
                                                        config[field.key] ?? ''
                                                    }
                                                    onChange={(e) =>
                                                        setConfig(
                                                            field.key,
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </FormField>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Registration</CardTitle>
                            <CardDescription>
                                New customers give a mobile number and enter the
                                code we text before they can use their account.
                                Existing customers can add a number from their
                                account.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <label className="flex items-start gap-2 text-sm md:col-span-2">
                                <Checkbox
                                    checked={form.data.require_at_registration}
                                    onCheckedChange={(c) =>
                                        form.setData(
                                            'require_at_registration',
                                            c === true,
                                        )
                                    }
                                />
                                Require a confirmed mobile number at
                                registration
                            </label>
                            <FormField
                                id="default_country_code"
                                label="Default country calling code"
                                hint="Used when a customer types a number without +, e.g. 1, 44, 63, 91."
                                error={errors.default_country_code}
                            >
                                <Input
                                    id="default_country_code"
                                    inputMode="numeric"
                                    value={form.data.default_country_code}
                                    onChange={(e) =>
                                        form.setData(
                                            'default_country_code',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="code_minutes"
                                label="Code valid for (minutes)"
                                error={errors.code_minutes}
                            >
                                <Input
                                    id="code_minutes"
                                    type="number"
                                    min={1}
                                    max={60}
                                    value={form.data.code_minutes}
                                    onChange={(e) =>
                                        form.setData(
                                            'code_minutes',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </FormField>
                            <div className="md:col-span-2">
                                <FormField
                                    id="verification_template"
                                    label="Verification message"
                                    hint="Must contain {code}. Also: {store}, {minutes}, {name}."
                                    error={errors.verification_template}
                                >
                                    <Textarea
                                        id="verification_template"
                                        rows={2}
                                        value={form.data.verification_template}
                                        onChange={(e) =>
                                            form.setData(
                                                'verification_template',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Order updates</CardTitle>
                            <CardDescription>
                                Sent to customers with a confirmed number who
                                kept order texts on. Placeholders:{' '}
                                {placeholders.map((p) => `{${p}}`).join(', ')}.
                                Long messages may be split and billed as several
                                texts.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            {Object.entries(form.data.events).map(
                                ([event, value]) => (
                                    <div key={event} className="grid gap-2">
                                        <label className="flex items-center gap-2 text-sm font-medium">
                                            <Checkbox
                                                checked={value.enabled}
                                                onCheckedChange={(c) =>
                                                    form.setData('events', {
                                                        ...form.data.events,
                                                        [event]: {
                                                            ...value,
                                                            enabled: c === true,
                                                        },
                                                    })
                                                }
                                            />
                                            {EVENT_LABELS[event] ?? event}
                                        </label>
                                        <Textarea
                                            aria-label={`${EVENT_LABELS[event] ?? event} message`}
                                            rows={2}
                                            disabled={!value.enabled}
                                            value={value.template}
                                            onChange={(e) =>
                                                form.setData('events', {
                                                    ...form.data.events,
                                                    [event]: {
                                                        ...value,
                                                        template:
                                                            e.target.value,
                                                    },
                                                })
                                            }
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {value.template.length} characters
                                        </p>
                                        {errors[`events.${event}.template`] && (
                                            <p className="text-sm text-destructive">
                                                {
                                                    errors[
                                                        `events.${event}.template`
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>
                                ),
                            )}
                        </CardContent>
                    </Card>

                    <div>
                        <Button type="submit" disabled={form.processing}>
                            Save SMS settings
                        </Button>
                    </div>
                </form>

                <Card>
                    <CardHeader>
                        <CardTitle>Send a test message</CardTitle>
                        <CardDescription>
                            Uses the saved settings. Save first after changing
                            the provider.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="flex flex-wrap items-start gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                test.post(urls.test, { preserveScroll: true });
                            }}
                        >
                            <div className="grid min-w-60 flex-1 gap-1 sm:max-w-sm">
                                <Input
                                    aria-label="Mobile number"
                                    type="tel"
                                    placeholder="+15551234567"
                                    value={test.data.to}
                                    onChange={(e) =>
                                        test.setData('to', e.target.value)
                                    }
                                />
                                {test.errors.to && (
                                    <p className="text-sm text-destructive">
                                        {test.errors.to}
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                variant="secondary"
                                disabled={test.processing}
                            >
                                Send test
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent messages</CardTitle>
                        <CardDescription>
                            The last 50 texts. Verification codes are never
                            stored.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {messages.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No messages yet.
                            </p>
                        ) : (
                            <table className="w-full text-left text-sm">
                                <thead className="text-xs text-muted-foreground">
                                    <tr>
                                        <th className="py-2 pr-3 font-medium">
                                            When
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            To
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Type
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Status
                                        </th>
                                        <th className="py-2 font-medium">
                                            Message
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {messages.map((m) => (
                                        <tr key={m.id} className="align-top">
                                            <td className="py-2 pr-3 whitespace-nowrap">
                                                {m.created_at
                                                    ? new Date(
                                                          m.created_at,
                                                      ).toLocaleString()
                                                    : ''}
                                            </td>
                                            <td className="py-2 pr-3 whitespace-nowrap tabular-nums">
                                                {m.to}
                                            </td>
                                            <td className="py-2 pr-3 whitespace-nowrap">
                                                {m.event
                                                    ? (EVENT_LABELS[m.event] ??
                                                      m.event)
                                                    : m.purpose}
                                            </td>
                                            <td className="py-2 pr-3">
                                                <Badge
                                                    variant={
                                                        m.status === 'failed'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {m.status}
                                                </Badge>
                                            </td>
                                            <td className="py-2">
                                                <p>{m.body}</p>
                                                {m.error && (
                                                    <p className="text-xs text-destructive">
                                                        {m.provider}: {m.error}
                                                    </p>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
            </AdminPage>
        </>
    );
}
