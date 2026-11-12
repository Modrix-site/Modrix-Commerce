import ConfirmPasswordForm from '@/pages/auth/confirm-password';
import { CustomerPasswordLayout } from '@/components/storefront/customer-password-layout';
import { useT } from '@/lib/i18n';

export default function CustomerConfirmPassword() {
    const t = useT();
    return (
        <CustomerPasswordLayout
            title={t('Confirm password')}
            description={t(
                'This is a secure area of the application. Please confirm your password before continuing.',
            )}
        >
            <ConfirmPasswordForm />
        </CustomerPasswordLayout>
    );
}
