import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';

export function StoreMenuLink({
    href,
    children,
    className,
    onClick,
    ...props
}: ComponentProps<typeof Link>) {
    return typeof href === 'string' && href.startsWith('https://') ? (
        <a
            href={href}
            className={className}
            onClick={onClick}
            aria-label={props['aria-label']}
        >
            {children}
        </a>
    ) : (
        <Link {...props} href={href} className={className} onClick={onClick}>
            {children}
        </Link>
    );
}
