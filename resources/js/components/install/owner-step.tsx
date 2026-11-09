import { Form } from "@inertiajs/react";
import InstallController from "@/actions/App/Http/Controllers/Install/InstallController";
import { FormField, NativeSelect } from "@/components/admin/form-field";
import PasswordInput from "@/components/password-input";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import type { CurrencyOption, InstallProof } from "@/types";

type Props = {
    proof: InstallProof;
    tokenLocation: string;
    currencies: CurrencyOption[];
    timezones: string[];
    defaultTimezone: string;
};

function ProofCard({
    proof,
    tokenLocation,
    errors,
}: {
    proof: InstallProof;
    tokenLocation: string;
    errors: Record<string, string>;
}) {
    if (proof === "session") {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Verify ownership</CardTitle>
                <CardDescription>
                    {proof === "database_password" ? (
                        <>
                            Enter the database password configured for this app
                            on your hosting platform (DB_PASSWORD). It is only
                            compared, never stored or shown.
                        </>
                    ) : (
                        <>
                            Paste the token from{" "}
                            <code className="rounded bg-muted px-1">
                                {tokenLocation}
                            </code>{" "}
                            (open it with your file manager) or run{" "}
                            <code className="rounded bg-muted px-1">
                                php artisan ecomodular:installer-token
                            </code>
                            .
                        </>
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {proof === "database_password" ? (
                    <FormField
                        id="database_password"
                        label="Database password (from your hosting environment)"
                        error={errors.database_password}
                    >
                        <PasswordInput
                            id="database_password"
                            name="database_password"
                            autoComplete="off"
                            required
                        />
                    </FormField>
                ) : (
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
                )}
            </CardContent>
        </Card>
    );
}

export function OwnerStep({
    proof,
    tokenLocation,
    currencies,
    timezones,
    defaultTimezone,
}: Props) {
    return (
        <Form
            {...InstallController.store.form()}
            className="grid gap-6"
            resetOnError={[
                "password",
                "password_confirmation",
                "database_password",
            ]}
        >
            {({ processing, errors }) => (
                <>
                    <ProofCard
                        proof={proof}
                        tokenLocation={tokenLocation}
                        errors={errors}
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle>Store</CardTitle>
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
                                        <option key={c.code} value={c.code}>
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
                            <CardTitle>Owner account</CardTitle>
                            <CardDescription>
                                The owner has full access, including granting
                                the owner role. Enable two-factor authentication
                                after signing in.
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
    );
}
