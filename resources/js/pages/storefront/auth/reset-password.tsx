import ResetPasswordForm from '@/pages/auth/reset-password';
import { CustomerPasswordLayout } from '@/components/storefront/customer-password-layout';
import { useT } from '@/lib/i18n';

export default function CustomerResetPassword(props: {
    token: string;
    email: string;
    passwordRules: string;
}) {
    const t = useT();
    return (
        <CustomerPasswordLayout
            title={t('Reset password')}
            description={t('Please enter your new password below')}
            showBackLink
        >
            <ResetPasswordForm {...props} />
        </CustomerPasswordLayout>
    );
}
