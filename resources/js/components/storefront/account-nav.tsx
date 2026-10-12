import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { logout } from '@/routes';
import { addresses, show } from '@/routes/account';
import { edit as profileEdit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';

export function AccountNav() {
    const { isCurrentUrl } = useCurrentUrl();
    const items = [
        { title: 'Overview', href: show() },
        { title: 'Addresses', href: addresses() },
        { title: 'Profile', href: profileEdit() },
        { title: 'Password & security', href: securityEdit() },
    ];

    return (
        <nav aria-label="Account" className="grid content-start gap-1 text-sm">
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
                Log out
            </Link>
        </nav>
    );
}
