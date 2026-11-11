import { Head, router, useForm } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Copy, RefreshCw } from 'lucide-react';
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
import { useClipboard } from '@/hooks/use-clipboard';

type ApsSettings = {
    enabled: boolean;
    merchant_identifier: string;
    test_mode: boolean;
    sha_type: 'sha256' | 'sha512';
    command: 'PURCHASE' | 'AUTHORIZATION';
    language: 'en' | 'ar';
    label: string;
    currencies: string[];
    access_code_hint: string | null;
    sha_request_phrase_hint: string | null;
    sha_response_phrase_hint: string | null;
    ready: boolean;
};

const COMMON_CURRENCIES = [
    { code: 'AED', label: 'AED (United Arab Emirates Dirham)' },
    { code: 'SAR', label: 'SAR (Saudi Riyal)' },
    { code: 'USD', label: 'USD (US Dollar)' },
    { code: 'EUR', label: 'EUR (Euro)' },
    { code: 'GBP', label: 'GBP (British Pound)' },
    { code: 'EGP', label: 'EGP (Egyptian Pound)' },
    { code: 'QAR', label: 'QAR (Qatari Riyal)' },
    { code: 'KWD', label: 'KWD (Kuwaiti Dinar)' },
    { code: 'BHD', label: 'BHD (Bahraini Dinar)' },
    { code: 'OMR', label: 'OMR (Omani Rial)' },
];

export default function AmazonPaymentServicesSettingsPage({
    settings,
    webhookUrl,
    responseUrl,
    publicWebhookUrl,
    updateUrl,
    testUrl,
}: {
    settings: ApsSettings;
    webhookUrl: string;
    responseUrl: string;
    publicWebhookUrl: boolean;
    updateUrl: string;
    testUrl: string;
}) {
    const [, copyWebhook] = useClipboard();
    const [, copyResponse] = useClipboard();

    const form = useForm({
        enabled: settings.enabled,
        merchant_identifier: settings.merchant_identifier,
        test_mode: settings.test_mode,
        sha_type: settings.sha_type,
        command: settings.command,
        language: settings.language,
        label: settings.label,
        currencies: settings.currencies,
        access_code: '',
        sha_request_phrase: '',
        sha_response_phrase: '',
    });

    const checks: [boolean, string][] = [
        [settings.merchant_identifier !== '', 'Merchant Identifier configured'],
        [settings.access_code_hint !== null, 'Access Code saved encrypted'],
        [
            settings.sha_request_phrase_hint !== null,
            'SHA Request Phrase saved encrypted',
        ],
        [
            settings.sha_response_phrase_hint !== null,
            'SHA Response Phrase saved encrypted',
        ],
        [publicWebhookUrl, 'Store address is served over public HTTPS'],
        [settings.enabled, 'Amazon Payment Services enabled for checkout'],
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(updateUrl, { preserveScroll: true });
    };

    const handleTest = () => {
        router.post(testUrl, {}, { preserveScroll: true });
    };

    const toggleCurrency = (currency: string) => {
        const current = form.data.currencies;
        if (current.includes(currency)) {
            if (current.length > 1) {
                form.setData(
                    'currencies',
                    current.filter((c) => c !== currency),
                );
            }
        } else {
            form.setData('currencies', [...current, currency]);
        }
    };

    return (
        <>
            <Head title="Amazon Payment Services" />
            <AdminPage>
                <PageHeader
                    title="Amazon Payment Services"
                    description="Official payment gateway for the UAE, Saudi Arabia, Egypt, and MENA (formerly PayFort). Accepts cards, Apple Pay, Mada, and KNET with secure hosted checkout."
                    actions={
                        <div className="flex items-center gap-2">
                            <Badge
                                variant={
                                    settings.test_mode ? 'secondary' : 'default'
                                }
                            >
                                {settings.test_mode
                                    ? 'Sandbox / Test mode'
                                    : 'Live mode'}
                            </Badge>
                            {settings.ready && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleTest}
                                    disabled={form.processing}
                                >
                                    <RefreshCw className="mr-1.5 size-4" />
                                    Test Connection
                                </Button>
                            )}
                        </div>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {settings.ready
                                ? 'Amazon Payment Services is taking payments'
                                : 'Set up Amazon Payment Services'}
                        </CardTitle>
                        <CardDescription>
                            Credentials and endpoints required to accept
                            payments across the UAE and GCC.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid gap-2 text-sm">
                            {checks.map(([done, label]) => (
                                <li
                                    key={label}
                                    className="flex items-center gap-2"
                                >
                                    {done ? (
                                        <CircleCheck className="size-4 shrink-0 text-primary" />
                                    ) : (
                                        <CircleAlert className="size-4 shrink-0 text-muted-foreground" />
                                    )}
                                    <span>{label}</span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <form onSubmit={handleSubmit} className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Account Credentials</CardTitle>
                            <CardDescription>
                                Found in your Amazon Payment Services
                                back-office under Integration Settings →
                                Security Settings.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="merchant_identifier"
                                label="Merchant Identifier"
                                error={form.errors.merchant_identifier}
                                hint="Your APS account merchant ID (e.g. M123456)."
                            >
                                <Input
                                    id="merchant_identifier"
                                    value={form.data.merchant_identifier}
                                    onChange={(e) =>
                                        form.setData(
                                            'merchant_identifier',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. your_merchant_id"
                                />
                            </FormField>

                            <FormField
                                id="access_code"
                                label="Access Code"
                                error={form.errors.access_code}
                                hint={
                                    settings.access_code_hint
                                        ? 'Access code saved encrypted. Leave blank to keep current.'
                                        : 'Required to authenticate checkout requests.'
                                }
                            >
                                <Input
                                    id="access_code"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.access_code}
                                    onChange={(e) =>
                                        form.setData(
                                            'access_code',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        settings.access_code_hint ??
                                        'Access code from APS dashboard'
                                    }
                                />
                            </FormField>

                            <FormField
                                id="sha_request_phrase"
                                label="SHA Request Phrase"
                                error={form.errors.sha_request_phrase}
                                hint={
                                    settings.sha_request_phrase_hint
                                        ? 'Request passphrase saved encrypted. Leave blank to keep current.'
                                        : 'Used to sign outbound checkout requests.'
                                }
                            >
                                <Input
                                    id="sha_request_phrase"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.sha_request_phrase}
                                    onChange={(e) =>
                                        form.setData(
                                            'sha_request_phrase',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        settings.sha_request_phrase_hint ??
                                        'SHA Request Phrase'
                                    }
                                />
                            </FormField>

                            <FormField
                                id="sha_response_phrase"
                                label="SHA Response Phrase"
                                error={form.errors.sha_response_phrase}
                                hint={
                                    settings.sha_response_phrase_hint
                                        ? 'Response passphrase saved encrypted. Leave blank to keep current.'
                                        : 'Used to verify incoming return and webhook signatures.'
                                }
                            >
                                <Input
                                    id="sha_response_phrase"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.sha_response_phrase}
                                    onChange={(e) =>
                                        form.setData(
                                            'sha_response_phrase',
                                            e.target.value,
                                        )
                                    }
                                    placeholder={
                                        settings.sha_response_phrase_hint ??
                                        'SHA Response Phrase'
                                    }
                                />
                            </FormField>

                            <FormField
                                id="sha_type"
                                label="SHA Algorithm"
                                error={form.errors.sha_type}
                                hint="Must match the SHA type configured in your APS security settings."
                            >
                                <NativeSelect
                                    id="sha_type"
                                    value={form.data.sha_type}
                                    onChange={(e) =>
                                        form.setData(
                                            'sha_type',
                                            e.target.value as
                                                | 'sha256'
                                                | 'sha512',
                                        )
                                    }
                                >
                                    <option value="sha256">
                                        SHA-256 (Recommended)
                                    </option>
                                    <option value="sha512">SHA-512</option>
                                </NativeSelect>
                            </FormField>

                            <FormField
                                id="command"
                                label="Payment Command"
                                error={form.errors.command}
                                hint="PURCHASE authorizes and captures immediately; AUTHORIZATION holds funds."
                            >
                                <NativeSelect
                                    id="command"
                                    value={form.data.command}
                                    onChange={(e) =>
                                        form.setData(
                                            'command',
                                            e.target.value as
                                                | 'PURCHASE'
                                                | 'AUTHORIZATION',
                                        )
                                    }
                                >
                                    <option value="PURCHASE">
                                        PURCHASE (Direct Capture)
                                    </option>
                                    <option value="AUTHORIZATION">
                                        AUTHORIZATION (Authorize Only)
                                    </option>
                                </NativeSelect>
                            </FormField>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Checkout & Display</CardTitle>
                            <CardDescription>
                                Configure how Amazon Payment Services appears to
                                shoppers.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="label"
                                label="Checkout Label"
                                error={form.errors.label}
                                hint="Method name displayed to customers in the payment step."
                            >
                                <Input
                                    id="label"
                                    value={form.data.label}
                                    onChange={(e) =>
                                        form.setData('label', e.target.value)
                                    }
                                />
                            </FormField>

                            <FormField
                                id="language"
                                label="Payment Page Language"
                                error={form.errors.language}
                                hint="Default language presented on the APS checkout page."
                            >
                                <NativeSelect
                                    id="language"
                                    value={form.data.language}
                                    onChange={(e) =>
                                        form.setData(
                                            'language',
                                            e.target.value as 'en' | 'ar',
                                        )
                                    }
                                >
                                    <option value="en">English</option>
                                    <option value="ar">Arabic (العربية)</option>
                                </NativeSelect>
                            </FormField>

                            <div className="flex items-center space-x-2 pt-2 md:col-span-2">
                                <Checkbox
                                    id="test_mode"
                                    checked={form.data.test_mode}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'test_mode',
                                            Boolean(checked),
                                        )
                                    }
                                />
                                <label
                                    htmlFor="test_mode"
                                    className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    Sandbox / Test Mode (uses
                                    sbcheckout.payfort.com and test card
                                    numbers)
                                </label>
                            </div>

                            <div className="flex items-center space-x-2 md:col-span-2">
                                <Checkbox
                                    id="enabled"
                                    checked={form.data.enabled}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'enabled',
                                            Boolean(checked),
                                        )
                                    }
                                />
                                <label
                                    htmlFor="enabled"
                                    className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    Enable Amazon Payment Services at checkout
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Supported Currencies</CardTitle>
                            <CardDescription>
                                Enable currencies supported by your merchant
                                account. Amounts will be correctly converted to
                                minor currency units.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                            {COMMON_CURRENCIES.map((c) => {
                                const active = form.data.currencies.includes(
                                    c.code,
                                );
                                return (
                                    <label
                                        key={c.code}
                                        className="flex cursor-pointer items-center space-x-2 rounded border p-3 hover:bg-muted/50"
                                    >
                                        <Checkbox
                                            checked={active}
                                            onCheckedChange={() =>
                                                toggleCurrency(c.code)
                                            }
                                        />
                                        <span className="text-sm font-medium">
                                            {c.label}
                                        </span>
                                    </label>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Webhook & Return URLs</CardTitle>
                            <CardDescription>
                                Copy these URLs into your Amazon Payment
                                Services back-office.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div>
                                <label className="text-xs font-semibold text-muted-foreground uppercase">
                                    Return / Response URL (Feedback URL)
                                </label>
                                <div className="mt-1 flex items-center gap-2">
                                    <Input
                                        readOnly
                                        value={responseUrl}
                                        className="font-mono text-xs"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={() =>
                                            copyResponse(responseUrl)
                                        }
                                    >
                                        <Copy className="size-4" />
                                    </Button>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Set this as the{' '}
                                    <strong>Redirection Return URL</strong> in
                                    your APS Technical Settings.
                                </p>
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-muted-foreground uppercase">
                                    Direct Transaction Feedback URL (Webhook)
                                </label>
                                <div className="mt-1 flex items-center gap-2">
                                    <Input
                                        readOnly
                                        value={webhookUrl}
                                        className="font-mono text-xs"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={() => copyWebhook(webhookUrl)}
                                    >
                                        <Copy className="size-4" />
                                    </Button>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Set this as the{' '}
                                    <strong>
                                        Direct Transaction Feedback
                                        (Server-to-Server) URL
                                    </strong>{' '}
                                    in your APS Notification Settings.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-end gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Save Settings
                        </Button>
                    </div>
                </form>
            </AdminPage>
        </>
    );
}
