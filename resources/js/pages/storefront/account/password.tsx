import { Head } from '@inertiajs/react';
import {
    AccountLayout,
    AccountSection,
} from '@/components/storefront/account-nav';
import { ChangePasswordForm } from '@/components/storefront/change-password-form';
import { useT } from '@/lib/i18n';

export default function CustomerPassword({
    passwordRules,
}: {
    passwordRules: string;
}) {
    const t = useT();
    return (
        <>
            <Head title={t('Change password')} />
            <AccountLayout
                title={t('Change password')}
                description={t('Keep your account safe.')}
            >
                <AccountSection
                    title={t('Change password')}
                    description={t(
                        'Use a long password that you do not use anywhere else.',
                    )}
                >
                    <ChangePasswordForm passwordRules={passwordRules} />
                </AccountSection>
            </AccountLayout>
        </>
    );
}
