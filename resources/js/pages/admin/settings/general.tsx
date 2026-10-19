import { Head, useForm } from '@inertiajs/react';
import { Lock, ShieldAlert } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import GeneralSettingsController from '@/actions/App/Http/Controllers/Admin/GeneralSettingsController';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Tabs } from '@/components/admin/tabs';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { edit } from '@/routes/admin/settings/general';

type ImageKey = 'logo' | 'logo_dark' | 'icon' | 'favicon' | 'login_logo';

type Props = {
    branding: {
        panel_name: string;
        accent: string | null;
        login_message: string;
        urls: Record<ImageKey, string | null> & { panel_name: string };
    };
    mail: {
        driver: 'env' | 'smtp';
        host: string;
        port: number;
        encryption: 'tls' | 'ssl' | 'none';
        username: string;
        from_address: string;
        from_name: string;
        reply_to: string;
        has_password: boolean;
        password_unreadable: boolean;
        env_from: string;
    };
    maintenance: {
        enabled: boolean;
        headline: string;
        message: string;
        reopens_at: string;
        allowed_ips: string[];
    };
    security: {
        require_two_factor: boolean;
        session_lifetime: number | null;
        login_attempts: number;
        password_min: number | null;
    };
    social: Record<string, string>;
    scripts: {
        head_enabled: boolean;
        head: string;
        body_enabled: boolean;
        body: string;
        on_checkout: boolean;
        hosts: string[];
    };
    canAdvanced: boolean;
    hasTwoFactor: boolean;
    currentIp: string;
    defaultSessionLifetime: number;
};

const IMAGES: {
    key: ImageKey;
    label: string;
    hint: string;
    favicon?: boolean;
}[] = [
    {
        key: 'logo',
        label: 'Admin logo',
        hint: 'Shown in the sidebar. PNG, JPEG or WebP, up to 1 MB.',
    },
    {
        key: 'logo_dark',
        label: 'Admin logo (dark mode)',
        hint: 'Optional; the light logo is used otherwise.',
    },
    {
        key: 'icon',
        label: 'Admin icon',
        hint: 'Square mark for the collapsed sidebar.',
    },
    {
        key: 'favicon',
        label: 'Favicon',
        hint: 'PNG or ICO, up to 256 KB. A theme’s own favicon wins on the storefront.',
        favicon: true,
    },
    {
        key: 'login_logo',
        label: 'Sign-in page logo',
        hint: 'Optional; the admin logo is used otherwise.',
    },
];

const SOCIAL_LABELS: Record<string, string> = {
    facebook: 'Facebook',
    instagram: 'Instagram',
    x: 'X (Twitter)',
    tiktok: 'TikTok',
    youtube: 'YouTube',
    linkedin: 'LinkedIn',
    pinterest: 'Pinterest',
    whatsapp: 'WhatsApp',
};

const lines = (value: string) =>
    value
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

function Toggle({
    id,
    label,
    checked,
    onChange,
    disabled,
    hint,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
    hint?: string;
}) {
    return (
        <div className="grid gap-1">
            <label
                htmlFor={id}
                className="flex items-center gap-3 text-sm font-medium"
            >
                <input
                    id={id}
                    type="checkbox"
                    className="size-4 accent-[var(--primary)]"
                    checked={checked}
                    disabled={disabled}
                    onChange={(e) => onChange(e.target.checked)}
                />
                {label}
            </label>
            {hint && (
                <p className="ps-7 text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}

function SaveBar({
    processing,
    label = 'Save',
    disabled,
}: {
    processing: boolean;
    label?: string;
    disabled?: boolean;
}) {
    return (
        <div className="flex justify-end">
            <Button type="submit" disabled={processing || disabled}>
                {processing ? 'Saving…' : label}
            </Button>
        </div>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description && (
                    <CardDescription>{description}</CardDescription>
                )}
            </CardHeader>
            <CardContent className="grid gap-5">{children}</CardContent>
        </Card>
    );
}

function ReadOnlyNote() {
    return (
        <Alert>
            <Lock />
            <AlertTitle>View only</AlertTitle>
            <AlertDescription>
                Changing these needs the “Manage security settings and custom
                code” permission.
            </AlertDescription>
        </Alert>
    );
}

function BrandingTab({ branding }: { branding: Props['branding'] }) {
    const form = useForm<
        {
            panel_name: string;
            accent: string;
            login_message: string;
            remove: ImageKey[];
        } & Partial<Record<ImageKey, File | null>>
    >({
        panel_name: branding.panel_name,
        accent: branding.accent ?? '',
        login_message: branding.login_message,
        remove: [],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            accent: data.accent === '' ? null : data.accent,
        }));
        form.post(GeneralSettingsController.branding.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.setData('remove', []),
        });
    };
    return (
        <form onSubmit={submit} className="grid gap-6">
            <Section
                title="Admin branding"
                description="Your name and logo in the admin, on the sign-in page and as the browser icon."
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="panel_name"
                        label="Panel name"
                        error={errors.panel_name}
                        hint="Only in the admin, next to the logo; empty uses the store name. The shop's own name (storefront, emails, maintenance page) is set in Settings → Store."
                    >
                        <Input
                            id="panel_name"
                            value={form.data.panel_name}
                            maxLength={60}
                            onChange={(e) =>
                                form.setData('panel_name', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        id="accent"
                        label="Accent colour"
                        error={errors.accent}
                        hint="Sidebar brand colour. Empty keeps the default."
                    >
                        <div className="flex gap-2">
                            <input
                                type="color"
                                aria-label="Pick the accent colour"
                                value={form.data.accent || '#16a34a'}
                                onChange={(e) =>
                                    form.setData('accent', e.target.value)
                                }
                                className="h-9 w-12 cursor-pointer rounded border bg-transparent p-1"
                            />
                            <Input
                                id="accent"
                                value={form.data.accent}
                                placeholder="#16a34a"
                                onChange={(e) =>
                                    form.setData('accent', e.target.value)
                                }
                                className="font-mono"
                            />
                        </div>
                    </FormField>
                </div>
                <div className="grid gap-5 sm:grid-cols-2">
                    {IMAGES.map(({ key, label, hint, favicon }) => {
                        const current = branding.urls[key];
                        const removing = form.data.remove.includes(key);
                        return (
                            <FormField
                                key={key}
                                id={`image-${key}`}
                                label={label}
                                hint={hint}
                                error={errors[key]}
                            >
                                <div className="grid gap-2">
                                    {current && !removing && (
                                        <div className="flex items-center gap-3">
                                            <img
                                                src={current}
                                                alt=""
                                                className="h-12 max-w-40 rounded border bg-muted object-contain p-1"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    form.setData('remove', [
                                                        ...form.data.remove,
                                                        key,
                                                    ])
                                                }
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                    )}
                                    {removing && (
                                        <p className="text-xs text-muted-foreground">
                                            Will be removed when you save.
                                        </p>
                                    )}
                                    <Input
                                        id={`image-${key}`}
                                        type="file"
                                        accept={
                                            favicon
                                                ? 'image/png,image/x-icon,.ico'
                                                : 'image/png,image/jpeg,image/webp'
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                key,
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </div>
                            </FormField>
                        );
                    })}
                </div>
                <FormField
                    id="login_message"
                    label="Sign-in page message"
                    error={errors.login_message}
                    hint="For example “Staff sign-in only”."
                >
                    <Input
                        id="login_message"
                        value={form.data.login_message}
                        maxLength={200}
                        onChange={(e) =>
                            form.setData('login_message', e.target.value)
                        }
                    />
                </FormField>
            </Section>
            <SaveBar processing={form.processing} label="Save branding" />
        </form>
    );
}

function EmailTab({ mail }: { mail: Props['mail'] }) {
    const form = useForm({
        driver: mail.driver,
        host: mail.host,
        port: mail.port,
        encryption: mail.encryption,
        username: mail.username,
        password: '',
        remove_password: false,
        from_address: mail.from_address,
        from_name: mail.from_name,
        reply_to: mail.reply_to,
    });
    const test = useForm({ to: '' });
    const errors = form.errors as Record<string, string | undefined>;
    const smtp = form.data.driver === 'smtp';
    return (
        <div className="grid gap-6">
            <form
                className="grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(GeneralSettingsController.mail.url(), {
                        preserveScroll: true,
                        onSuccess: () =>
                            form.setData({
                                ...form.data,
                                password: '',
                                remove_password: false,
                            }),
                    });
                }}
            >
                <Section
                    title="Outgoing email"
                    description="How the store sends order emails, password resets and notifications."
                >
                    {mail.password_unreadable && (
                        <Alert variant="destructive">
                            <ShieldAlert />
                            <AlertTitle>Re-enter the SMTP password</AlertTitle>
                            <AlertDescription>
                                The saved password can no longer be read (the
                                app key changed). Email uses the server default
                                until you save it again.
                            </AlertDescription>
                        </Alert>
                    )}
                    <FormField
                        id="driver"
                        label="Mail delivery"
                        error={errors.driver}
                    >
                        <NativeSelect
                            id="driver"
                            value={form.data.driver}
                            onChange={(e) =>
                                form.setData(
                                    'driver',
                                    e.target.value as 'env' | 'smtp',
                                )
                            }
                        >
                            <option value="env">
                                Use the server default (.env file)
                            </option>
                            <option value="smtp">SMTP server</option>
                        </NativeSelect>
                    </FormField>
                    {smtp && (
                        <>
                            <div className="grid gap-4 sm:grid-cols-[1fr_8rem_10rem]">
                                <FormField
                                    id="host"
                                    label="SMTP host"
                                    error={errors.host}
                                >
                                    <Input
                                        id="host"
                                        value={form.data.host}
                                        placeholder="smtp.example.com"
                                        onChange={(e) =>
                                            form.setData('host', e.target.value)
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="port"
                                    label="Port"
                                    error={errors.port}
                                >
                                    <Input
                                        id="port"
                                        type="number"
                                        min={1}
                                        max={65535}
                                        value={form.data.port}
                                        onChange={(e) =>
                                            form.setData(
                                                'port',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="encryption"
                                    label="Encryption"
                                    error={errors.encryption}
                                >
                                    <NativeSelect
                                        id="encryption"
                                        value={form.data.encryption}
                                        onChange={(e) =>
                                            form.setData(
                                                'encryption',
                                                e.target.value as
                                                    | 'tls'
                                                    | 'ssl'
                                                    | 'none',
                                            )
                                        }
                                    >
                                        <option value="tls">
                                            TLS (STARTTLS, port 587)
                                        </option>
                                        <option value="ssl">
                                            SSL (port 465)
                                        </option>
                                        <option value="none">None</option>
                                    </NativeSelect>
                                </FormField>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <FormField
                                    id="username"
                                    label="Username"
                                    error={errors.username}
                                >
                                    <Input
                                        id="username"
                                        autoComplete="off"
                                        value={form.data.username}
                                        onChange={(e) =>
                                            form.setData(
                                                'username',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="password"
                                    label="Password"
                                    error={errors.password}
                                    hint={
                                        mail.has_password &&
                                        !form.data.remove_password
                                            ? 'A password is saved. Leave empty to keep it.'
                                            : 'Stored encrypted; never shown again.'
                                    }
                                >
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.data.password}
                                        disabled={form.data.remove_password}
                                        onChange={(e) =>
                                            form.setData(
                                                'password',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                            {mail.has_password && (
                                <Toggle
                                    id="remove_password"
                                    label="Remove the saved password"
                                    checked={form.data.remove_password}
                                    onChange={(value) =>
                                        form.setData('remove_password', value)
                                    }
                                />
                            )}
                        </>
                    )}
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            id="from_address"
                            label="From address"
                            error={errors.from_address}
                            hint={
                                smtp
                                    ? undefined
                                    : `Empty uses ${mail.env_from || 'the server default'}.`
                            }
                        >
                            <Input
                                id="from_address"
                                type="email"
                                value={form.data.from_address}
                                onChange={(e) =>
                                    form.setData('from_address', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="from_name"
                            label="From name"
                            error={errors.from_name}
                        >
                            <Input
                                id="from_name"
                                value={form.data.from_name}
                                onChange={(e) =>
                                    form.setData('from_name', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="reply_to"
                            label="Reply-to address"
                            error={errors.reply_to}
                        >
                            <Input
                                id="reply_to"
                                type="email"
                                value={form.data.reply_to}
                                onChange={(e) =>
                                    form.setData('reply_to', e.target.value)
                                }
                            />
                        </FormField>
                    </div>
                </Section>
                <SaveBar
                    processing={form.processing}
                    label="Save email settings"
                />
            </form>
            <form
                className="grid gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    test.post(GeneralSettingsController.testMail.url(), {
                        preserveScroll: true,
                    });
                }}
            >
                <Section
                    title="Send a test email"
                    description="Uses the saved settings. Save first if you changed anything above."
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="min-w-64 flex-1">
                            <FormField
                                id="test_to"
                                label="Send to"
                                error={test.errors.to}
                            >
                                <Input
                                    id="test_to"
                                    type="email"
                                    required
                                    value={test.data.to}
                                    onChange={(e) =>
                                        test.setData('to', e.target.value)
                                    }
                                />
                            </FormField>
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={test.processing}
                        >
                            {test.processing ? 'Sending…' : 'Send test email'}
                        </Button>
                    </div>
                </Section>
            </form>
        </div>
    );
}

function MaintenanceTab({
    maintenance,
    currentIp,
}: {
    maintenance: Props['maintenance'];
    currentIp: string;
}) {
    const form = useForm({
        enabled: maintenance.enabled,
        headline: maintenance.headline,
        message: maintenance.message,
        reopens_at: maintenance.reopens_at,
        allowed_ips: maintenance.allowed_ips.join('\n'),
    });
    const errors = form.errors as Record<string, string | undefined>;
    const ipError = Object.entries(errors).find(([key]) =>
        key.startsWith('allowed_ips'),
    )?.[1];
    return (
        <form
            className="grid gap-6"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    reopens_at: data.reopens_at.replace('T', ' ') || null,
                    allowed_ips: lines(data.allowed_ips),
                }));
                form.put(GeneralSettingsController.maintenance.url(), {
                    preserveScroll: true,
                });
            }}
        >
            <Section
                title="Maintenance mode"
                description="Close the storefront while you work on it. The admin keeps working, and staff still see the store."
            >
                <Toggle
                    id="enabled"
                    label="Storefront closed for maintenance"
                    checked={form.data.enabled}
                    onChange={(value) => form.setData('enabled', value)}
                />
                <FormField
                    id="headline"
                    label="Headline"
                    error={errors.headline}
                >
                    <Input
                        id="headline"
                        value={form.data.headline}
                        maxLength={120}
                        onChange={(e) =>
                            form.setData('headline', e.target.value)
                        }
                    />
                </FormField>
                <FormField id="message" label="Message" error={errors.message}>
                    <Textarea
                        id="message"
                        rows={3}
                        value={form.data.message}
                        maxLength={1000}
                        onChange={(e) =>
                            form.setData('message', e.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="reopens_at"
                    label="Reopens at (optional)"
                    error={errors.reopens_at}
                    hint="Shown to shoppers, in the store's time zone."
                >
                    <Input
                        id="reopens_at"
                        type="datetime-local"
                        value={form.data.reopens_at.replace(' ', 'T')}
                        onChange={(e) =>
                            form.setData('reopens_at', e.target.value)
                        }
                    />
                </FormField>
                <FormField
                    id="allowed_ips"
                    label="Allowed IP addresses"
                    error={ipError}
                    hint={`One per line. These visitors see the store normally. Your address: ${currentIp}`}
                >
                    <Textarea
                        id="allowed_ips"
                        rows={3}
                        className="font-mono"
                        value={form.data.allowed_ips}
                        onChange={(e) =>
                            form.setData('allowed_ips', e.target.value)
                        }
                    />
                </FormField>
            </Section>
            <SaveBar
                processing={form.processing}
                label="Save maintenance settings"
            />
        </form>
    );
}

function SecurityTab({
    security,
    canAdvanced,
    hasTwoFactor,
    defaultSessionLifetime,
}: Pick<
    Props,
    'security' | 'canAdvanced' | 'hasTwoFactor' | 'defaultSessionLifetime'
>) {
    const form = useForm({
        require_two_factor: security.require_two_factor,
        session_lifetime:
            security.session_lifetime === null
                ? ''
                : String(security.session_lifetime),
        login_attempts: security.login_attempts,
        password_min:
            security.password_min === null ? '' : String(security.password_min),
    });
    const errors = form.errors as Record<string, string | undefined>;
    return (
        <form
            className="grid gap-6"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    session_lifetime:
                        data.session_lifetime === ''
                            ? null
                            : Number(data.session_lifetime),
                    password_min:
                        data.password_min === ''
                            ? null
                            : Number(data.password_min),
                }));
                form.put(GeneralSettingsController.security.url(), {
                    preserveScroll: true,
                });
            }}
        >
            {!canAdvanced && <ReadOnlyNote />}
            <Section
                title="Security"
                description="Sign-in and session rules for everyone who uses the store."
            >
                <fieldset disabled={!canAdvanced} className="grid gap-5">
                    <Toggle
                        id="require_two_factor"
                        label="Require two-factor authentication for staff"
                        checked={form.data.require_two_factor}
                        onChange={(value) =>
                            form.setData('require_two_factor', value)
                        }
                        hint={
                            hasTwoFactor
                                ? 'Staff without it are asked to turn it on before using the admin.'
                                : 'Turn on two-factor for your own account first (Settings → Security), or you would be locked out.'
                        }
                    />
                    <InputError message={errors.require_two_factor} />
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            id="session_lifetime"
                            label="Session lifetime (minutes)"
                            error={errors.session_lifetime}
                            hint={`15–1440. Empty uses ${defaultSessionLifetime}.`}
                        >
                            <Input
                                id="session_lifetime"
                                type="number"
                                min={15}
                                max={1440}
                                value={form.data.session_lifetime}
                                onChange={(e) =>
                                    form.setData(
                                        'session_lifetime',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="login_attempts"
                            label="Sign-in attempts per minute"
                            error={errors.login_attempts}
                            hint="3–20, then a short lockout."
                        >
                            <Input
                                id="login_attempts"
                                type="number"
                                min={3}
                                max={20}
                                value={form.data.login_attempts}
                                onChange={(e) =>
                                    form.setData(
                                        'login_attempts',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="password_min"
                            label="Minimum password length"
                            error={errors.password_min}
                            hint="8–64. Empty keeps the default."
                        >
                            <Input
                                id="password_min"
                                type="number"
                                min={8}
                                max={64}
                                value={form.data.password_min}
                                onChange={(e) =>
                                    form.setData('password_min', e.target.value)
                                }
                            />
                        </FormField>
                    </div>
                </fieldset>
            </Section>
            {canAdvanced && (
                <SaveBar
                    processing={form.processing}
                    label="Save security settings"
                />
            )}
        </form>
    );
}

function SocialTab({
    social,
    scripts,
    canAdvanced,
}: Pick<Props, 'social' | 'scripts' | 'canAdvanced'>) {
    const links = useForm(social);
    const code = useForm({ ...scripts, hosts: scripts.hosts.join('\n') });
    const codeErrors = code.errors as Record<string, string | undefined>;
    const hostError = Object.entries(codeErrors).find(([key]) =>
        key.startsWith('hosts'),
    )?.[1];
    return (
        <div className="grid gap-6">
            <form
                className="grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    links.put(GeneralSettingsController.social.url(), {
                        preserveScroll: true,
                    });
                }}
            >
                <Section
                    title="Social links"
                    description="Your profiles; themes show them in the footer. Full https:// addresses."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        {Object.keys(SOCIAL_LABELS).map((key) => (
                            <FormField
                                key={key}
                                id={`social-${key}`}
                                label={SOCIAL_LABELS[key]}
                                error={
                                    (
                                        links.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    )[key]
                                }
                            >
                                <Input
                                    id={`social-${key}`}
                                    type="url"
                                    placeholder="https://"
                                    value={links.data[key] ?? ''}
                                    onChange={(e) =>
                                        links.setData(key, e.target.value)
                                    }
                                />
                            </FormField>
                        ))}
                    </div>
                </Section>
                <SaveBar
                    processing={links.processing}
                    label="Save social links"
                />
            </form>
            <form
                className="grid gap-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    code.transform((data) => ({
                        ...data,
                        hosts: lines(data.hosts),
                    }));
                    code.put(GeneralSettingsController.scripts.url(), {
                        preserveScroll: true,
                    });
                }}
            >
                {!canAdvanced && <ReadOnlyNote />}
                <Section
                    title="Custom code"
                    description="Analytics tags or chat widgets added to storefront pages. Not shown in the admin."
                >
                    <Alert variant="destructive">
                        <ShieldAlert />
                        <AlertTitle>
                            Code here runs for every shopper
                        </AlertTitle>
                        <AlertDescription>
                            Only paste code from providers you trust: it can
                            read everything shoppers type, including at checkout
                            if you allow it.
                        </AlertDescription>
                    </Alert>
                    <fieldset disabled={!canAdvanced} className="grid gap-5">
                        <Toggle
                            id="head_enabled"
                            label="Add to the page head"
                            checked={code.data.head_enabled}
                            onChange={(value) =>
                                code.setData('head_enabled', value)
                            }
                        />
                        <FormField
                            id="head"
                            label="Head code"
                            error={codeErrors.head}
                        >
                            <Textarea
                                id="head"
                                rows={5}
                                className="font-mono text-xs"
                                maxLength={10000}
                                value={code.data.head}
                                onChange={(e) =>
                                    code.setData('head', e.target.value)
                                }
                            />
                        </FormField>
                        <Toggle
                            id="body_enabled"
                            label="Add before the end of the page"
                            checked={code.data.body_enabled}
                            onChange={(value) =>
                                code.setData('body_enabled', value)
                            }
                        />
                        <FormField
                            id="body"
                            label="Body code"
                            error={codeErrors.body}
                        >
                            <Textarea
                                id="body"
                                rows={5}
                                className="font-mono text-xs"
                                maxLength={10000}
                                value={code.data.body}
                                onChange={(e) =>
                                    code.setData('body', e.target.value)
                                }
                            />
                        </FormField>
                        <Toggle
                            id="on_checkout"
                            label="Also on the checkout page"
                            checked={code.data.on_checkout}
                            onChange={(value) =>
                                code.setData('on_checkout', value)
                            }
                        />
                        <FormField
                            id="hosts"
                            label="Allowed script hosts"
                            error={hostError}
                            hint="One https:// address per line, e.g. https://www.googletagmanager.com. Added to the store’s Content-Security-Policy while code is on."
                        >
                            <Textarea
                                id="hosts"
                                rows={3}
                                className="font-mono text-xs"
                                value={code.data.hosts}
                                onChange={(e) =>
                                    code.setData('hosts', e.target.value)
                                }
                            />
                        </FormField>
                    </fieldset>
                </Section>
                {canAdvanced && (
                    <SaveBar
                        processing={code.processing}
                        label="Save custom code"
                    />
                )}
            </form>
        </div>
    );
}

export default function GeneralSettings(props: Props) {
    return (
        <>
            <Head title="General settings" />
            <AdminPage>
                <PageHeader
                    title="General settings"
                    description="Branding, email, maintenance, security and storefront code. Changes are recorded in the audit log."
                />
                <Tabs
                    label="General settings sections"
                    items={[
                        {
                            id: 'branding',
                            label: 'Branding',
                            content: <BrandingTab branding={props.branding} />,
                        },
                        {
                            id: 'email',
                            label: 'Email',
                            content: <EmailTab mail={props.mail} />,
                        },
                        {
                            id: 'maintenance',
                            label: 'Maintenance',
                            content: (
                                <MaintenanceTab
                                    maintenance={props.maintenance}
                                    currentIp={props.currentIp}
                                />
                            ),
                        },
                        {
                            id: 'security',
                            label: 'Security',
                            content: <SecurityTab {...props} />,
                        },
                        {
                            id: 'social',
                            label: 'Social & scripts',
                            content: <SocialTab {...props} />,
                        },
                    ]}
                />
            </AdminPage>
        </>
    );
}

GeneralSettings.layout = {
    breadcrumbs: [{ title: 'General settings', href: edit() }],
};
