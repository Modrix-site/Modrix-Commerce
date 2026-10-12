import { usePage } from '@inertiajs/react';

/**
 * Permission check for showing or hiding UI. This is a convenience only:
 * the server authorizes every request independently.
 */
export function useCan(): (permission: string) => boolean {
    const { auth } = usePage().props;

    return (permission: string) =>
        auth.isOwner || auth.permissions.includes(permission);
}
