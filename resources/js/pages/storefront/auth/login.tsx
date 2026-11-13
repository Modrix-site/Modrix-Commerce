import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    reason?: string | null;
    canResetPassword: boolean;
    canRegister: boolean;
};

export default function StorefrontLogin({
    status,
    reason,
    canResetPassword,
    canRegister,
}: Props) {
    const t = useT();

    return (
        <>
            <Head title={t('Sign in')} />
            <div className="mx-auto w-full max-w-md py-6 sm:py-12">
                <div className="rounded-xl border bg-card p-6 shadow-xs sm:p-8">
                    <div className="mb-6 grid gap-1 text-center">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('Sign in')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('Sign in to track orders and check out faster.')}
                        </p>
                    </div>

                    {reason === 'checkout' && (
                        <div
                            role="status"
                            className="mb-4 rounded-md bg-muted px-3 py-2 text-center text-sm font-medium"
                        >
                            {t('Sign in or create an account to check out.')}
                        </div>
                    )}

                    {status && (
                        <div className="mb-4 rounded-md bg-muted px-3 py-2 text-center text-sm font-medium">
                            {status}
                        </div>
                    )}

                    <Form
                        {...store.form()}
                        resetOnSuccess={['password']}
                        className="grid gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">
                                        {t('Email address')}
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        autoComplete="email"
                                        placeholder="email@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center">
                                        <Label htmlFor="password">
                                            {t('Password')}
                                        </Label>
                                        {canResetPassword && (
                                            <TextLink
                                                href={request()}
                                                className="ml-auto text-sm"
                                            >
                                                {t('Forgot your password?')}
                                            </TextLink>
                                        )}
                                    </div>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="current-password"
                                        placeholder={t('Password')}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Checkbox id="remember" name="remember" />
                                    <Label htmlFor="remember">
                                        {t('Remember me')}
                                    </Label>
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                    data-test="login-button"
                                >
                                    {processing && <Spinner />}
                                    {t('Sign in')}
                                </Button>
                            </>
                        )}
                    </Form>

                    {canRegister && (
                        <p className="mt-6 text-center text-sm text-muted-foreground">
                            {t("Don't have an account?")}{' '}
                            <TextLink href={register()}>
                                {t('Create an account')}
                            </TextLink>
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}
