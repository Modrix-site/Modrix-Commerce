import { Form, Head } from '@inertiajs/react';
import InstallController from '@/actions/App/Http/Controllers/Install/InstallController';
import { ChecksList } from '@/components/admin/checks-list';
import { FormField, NativeSelect } from '@/components/admin/form-field';
import PasswordInput from '@/components/password-input';
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
import type { CurrencyOption, RequirementCheck } from '@/types';

type Props = {
    checks: RequirementCheck[];
    ready: boolean;
    currencies: CurrencyOption[];
    timezones: string[];
    defaultTimezone: string;
    tokenLocation: string;
};

export default function Install({
    checks,
    ready,
    currencies,
    timezones,
    defaultTimezone,
    tokenLocation,
}: Props) {
    return (
        <>
            <Head title="Install" />
            <main className="mx-auto grid w-full max-w-3xl gap-6 px-4 py-10">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Install EcoModular
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Verify the server, then create the owner account. You
                        can also install from the command line with{' '}
                        <code className="rounded bg-muted px-1">
                            php artisan ecomodular:install
                        </code>
                        .
                    </p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle>1. Server requirements</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ChecksList checks={checks} />
                    </CardContent>
                </Card>

                {!ready ? (
                    <Alert variant="destructive">
                        <AlertTitle>Installation blocked</AlertTitle>
                        <AlertDescription>
                            Fix the failing requirements above and reload this
                            page. Database credentials are configured in the{' '}
                            <code>.env</code> file, never from the browser.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Form
                        {...InstallController.store.form()}
                        className="grid gap-6"
                        resetOnError={['password', 'password_confirmation']}
                    >
                        {({ processing, errors }) => (
                            <>
                                <Card>
                                    <CardHeader>
                                        <CardTitle>2. Setup token</CardTitle>
                                        <CardDescription>
                                            To prove you control this server,
                                            paste the token from{' '}
                                            <code className="rounded bg-muted px-1">
                                                {tokenLocation}
                                            </code>{' '}
                                            or run{' '}
                                            <code className="rounded bg-muted px-1">
                                                php artisan
                                                ecomodular:installer-token
                                            </code>
                                            .
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <FormField
                                            id="setup_token"
                                            label="Setup token"
                                            error={errors.setup_token}
                                        >
                                            <Input
                                                id="setup_token"
                                                name="setup_token"
                                                autoComplete="off"
                                                required
                                            />
                                        </FormField>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>3. Store</CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-3">
                                        <FormField
                                            id="store_name"
                                            label="Store name"
                                            error={errors.store_name}
                                        >
                                            <Input
                                                id="store_name"
                                                name="store_name"
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            id="default_currency"
                                            label="Currency"
                                            error={errors.default_currency}
                                        >
                                            <NativeSelect
                                                id="default_currency"
                                                name="default_currency"
                                                defaultValue="USD"
                                                required
                                            >
                                                {currencies.map((c) => (
                                                    <option
                                                        key={c.code}
                                                        value={c.code}
                                                    >
                                                        {c.code} — {c.name}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </FormField>
                                        <FormField
                                            id="timezone"
                                            label="Timezone"
                                            error={errors.timezone}
                                        >
                                            <NativeSelect
                                                id="timezone"
                                                name="timezone"
                                                defaultValue={defaultTimezone}
                                                required
                                            >
                                                {timezones.map((tz) => (
                                                    <option key={tz} value={tz}>
                                                        {tz}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </FormField>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>4. Owner account</CardTitle>
                                        <CardDescription>
                                            The owner has full access, including
                                            granting the owner role. Enable
                                            two-factor authentication after
                                            signing in.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        <FormField
                                            id="name"
                                            label="Full name"
                                            error={errors.name}
                                        >
                                            <Input
                                                id="name"
                                                name="name"
                                                autoComplete="name"
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            id="email"
                                            label="Email address"
                                            error={errors.email}
                                        >
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                autoComplete="username"
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            id="password"
                                            label="Password"
                                            error={errors.password}
                                        >
                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                autoComplete="new-password"
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            id="password_confirmation"
                                            label="Confirm password"
                                            error={errors.password_confirmation}
                                        >
                                            <PasswordInput
                                                id="password_confirmation"
                                                name="password_confirmation"
                                                autoComplete="new-password"
                                                required
                                            />
                                        </FormField>
                                    </CardContent>
                                </Card>

                                <div>
                                    <Button type="submit" disabled={processing}>
                                        Install
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </main>
        </>
    );
}
