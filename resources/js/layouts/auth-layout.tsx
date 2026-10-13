import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { useT } from '@/lib/i18n';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const t = useT();

    return (
        <AuthLayoutTemplate
            title={title ? t(title) : ''}
            description={description ? t(description) : ''}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
