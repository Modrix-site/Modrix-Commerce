import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/lib/i18n';

export function ChangePasswordForm({
    passwordRules,
}: {
    passwordRules: string;
}) {
    const t = useT();
    const currentInput = useRef<HTMLInputElement>(null);
    const passwordInput = useRef<HTMLInputElement>(null);
    return (
        <Form
            action="/account/password"
            method="put"
            options={{ preserveScroll: true }}
            resetOnSuccess
            resetOnError={[
                'current_password',
                'password',
                'password_confirmation',
            ]}
            onError={(errors) => {
                if (errors.current_password) currentInput.current?.focus();
                else passwordInput.current?.focus();
            }}
            className="grid max-w-md gap-5"
        >
            {({ errors, processing, wasSuccessful }) => (
                <>
                    {wasSuccessful && (
                        <p
                            role="status"
                            className="rounded-md bg-muted p-3 text-sm"
                        >
                            {t('Password updated.')}
                        </p>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="current_password">
                            {t('Current password')}
                        </Label>
                        <PasswordInput
                            ref={currentInput}
                            id="current_password"
                            name="current_password"
                            required
                            autoComplete="current-password"
                            aria-invalid={!!errors.current_password}
                        />
                        <InputError message={errors.current_password} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">{t('New password')}</Label>
                        <PasswordInput
                            ref={passwordInput}
                            id="password"
                            name="password"
                            required
                            autoComplete="new-password"
                            passwordrules={passwordRules}
                            aria-invalid={!!errors.password}
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
                            passwordrules={passwordRules}
                            aria-invalid={!!errors.password_confirmation}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full sm:w-auto sm:justify-self-start"
                    >
                        {processing && <Spinner />}
                        {t('Update password')}
                    </Button>
                </>
            )}
        </Form>
    );
}
