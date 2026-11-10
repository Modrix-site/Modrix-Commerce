import { Form, Head, Link, usePage } from '@inertiajs/react';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import {
    AccountLayout,
    AccountSection,
} from '@/components/storefront/account-nav';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/lib/i18n';
import { update } from '@/routes/account/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

export default function AccountProfile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const t = useT();

    return (
        <>
            <Head title={t('Profile')} />
            <AccountLayout
                title={t('Profile')}
                description={t(
                    'Your name and the email address we send order updates to.',
                )}
            >
                <AccountSection title={t('Personal details')}>
                    <Form
                        {...update.form()}
                        options={{ preserveScroll: true }}
                        className="grid gap-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            {t('Name')}
                                        </Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={auth.user.name}
                                            required
                                            autoComplete="name"
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            {t('Email address')}
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            defaultValue={auth.user.email}
                                            required
                                            autoComplete="username"
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                </div>

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <div className="rounded-lg bg-muted p-3 text-sm">
                                            {t(
                                                'Your email address is not verified yet.',
                                            )}{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="font-medium underline underline-offset-4"
                                            >
                                                {t('Send the link again')}
                                            </Link>
                                            {status ===
                                                'verification-link-sent' && (
                                                <p className="mt-2 font-medium text-green-600">
                                                    {t(
                                                        'A new verification link has been sent to your email address.',
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                    )}

                                <div>
                                    <Button disabled={processing}>
                                        {t('Save changes')}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </AccountSection>

                <AccountSection>
                    <DeleteUser />
                </AccountSection>
            </AccountLayout>
        </>
    );
}
