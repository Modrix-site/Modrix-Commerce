import type { Auth } from '@/types/auth';
import type { AdminNavGroup } from '@/types/navigation';
import type { StorefrontShared } from '@/types/storefront';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            adminNavigation: AdminNavGroup[];
            /** Present on storefront pages only. */
            storefront?: StorefrontShared;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
