import ForgotPasswordForm from '@/pages/auth/forgot-password';
import { CustomerPasswordLayout } from '@/components/storefront/customer-password-layout';
import { useT } from '@/lib/i18n';

export default function CustomerForgotPassword({
    status,
}: {
    status?: string;
}) {
    const t = useT();
    return (
        <CustomerPasswordLayout
            title={t('Forgot password')}
            description={t('Enter your email to receive a password reset link')}
        >
            <ForgotPasswordForm status={status} loginUrl="/login" />
        </CustomerPasswordLayout>
    );
}
