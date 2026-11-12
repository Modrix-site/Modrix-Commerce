import { ChangePasswordForm } from '@/components/storefront/change-password-form';
import { Head } from '@inertiajs/react';

import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManageTwoFactor from '@/components/manage-two-factor';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';

import {
    AccountLayout,
    AccountSection,
} from '@/components/storefront/account-nav';

import { useT } from '@/lib/i18n';

type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function AccountSecurity(props: Props) {
    const t = useT();

    return (
        <>
            <Head title={t('Password & security')} />
            <AccountLayout
                title={t('Password & security')}
                description={t('Keep your account safe.')}
            >
                <AccountSection
                    title={t('Change password')}
                    description={t(
                        'Use a long password that you do not use anywhere else.',
                    )}
                >
                    <ChangePasswordForm passwordRules={props.passwordRules} />
                </AccountSection>

                {props.canManageTwoFactor && (
                    <AccountSection>
                        <ManageTwoFactor
                            canManageTwoFactor={props.canManageTwoFactor}
                            requiresConfirmation={props.requiresConfirmation}
                            twoFactorEnabled={props.twoFactorEnabled}
                        />
                    </AccountSection>
                )}

                {props.canManagePasskeys && (
                    <AccountSection>
                        <ManagePasskeys
                            canManagePasskeys={props.canManagePasskeys}
                            passkeys={props.passkeys}
                        />
                    </AccountSection>
                )}
            </AccountLayout>
        </>
    );
}
