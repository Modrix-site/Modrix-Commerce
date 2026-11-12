import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function StorefrontRegister({ passwordRules }: Props) {
    const t = useT();

    return (
        <>
            <Head title={t('Create an account')} />
            <div className="mx-auto w-full max-w-md py-6 sm:py-12">
                <div className="rounded-xl border bg-card p-6 shadow-xs sm:p-8">
                    <div className="mb-6 grid gap-1 text-center">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('Create an account')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('Save your addresses and follow every order.')}
                        </p>
                    </div>

                    <Form
                        {...store.form()}
                        resetOnSuccess={['password', 'password_confirmation']}
                        disableWhileProcessing
                        className="grid gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">{t('Name')}</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        type="text"
                                        required
                                        autoFocus
                                        autoComplete="name"
                                        placeholder={t('Full name')}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">
                                        {t('Email address')}
                                    </Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        autoComplete="email"
                                        placeholder="email@example.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">
                                        {t('Password')}
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="new-password"
                                        placeholder={t('Password')}
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        {t('Confirm password')}
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        required
                                        autoComplete="new-password"
                                        placeholder={t('Confirm password')}
                                        passwordrules={passwordRules}
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    data-test="register-user-button"
                                >
                                    {processing && <Spinner />}
                                    {t('Create account')}
                                </Button>
                            </>
                        )}
                    </Form>

                    <p className="mt-6 text-center text-sm text-muted-foreground">
                        {t('Already have an account?')}{' '}
                        <TextLink href={login()}>{t('Sign in')}</TextLink>
                    </p>
                </div>
            </div>
        </>
    );
}
