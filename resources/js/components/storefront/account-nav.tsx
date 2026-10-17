import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useT } from '@/lib/i18n';
import { logout } from '@/routes';
import { addresses, notifications, orders, show } from '@/routes/account';
import { edit as profileEdit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';

export function AccountNav() {
    const { isCurrentUrl } = useCurrentUrl();
    const t = useT();
    const items = [
        { title: t('Overview'), href: show() },
        { title: t('Orders'), href: orders() },
        { title: t('Notifications'), href: notifications() },
        { title: t('Addresses'), href: addresses() },
        { title: t('Profile'), href: profileEdit() },
        { title: t('Password & security'), href: securityEdit() },
    ];

    return (
        <nav
            aria-label={t('Account')}
            className="grid content-start gap-1 text-sm"
        >
            {items.map((item) => (
                <Link
                    key={item.title}
                    href={item.href}
                    aria-current={isCurrentUrl(item.href) ? 'page' : undefined}
                    className="rounded px-2 py-1.5 hover:bg-muted aria-[current=page]:bg-muted aria-[current=page]:font-medium"
                >
                    {item.title}
                </Link>
            ))}
            <Link
                href={logout()}
                as="button"
                className="rounded px-2 py-1.5 text-left text-muted-foreground hover:bg-muted"
            >
                {t('Log out')}
            </Link>
        </nav>
    );
}
