import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { StoreMenuTree } from './store-menu';

export function StoreNavigationShell({ children }: { children: ReactNode }) {
    const menu = usePage().props.storefront?.navigation?.sidebar;
    const enabled = menu?.enabled !== false && Boolean(menu?.links.length);
    return (
        <div className="store-navigation-shell" data-sidebar={enabled}>
            {enabled && menu && (
                <>
                    <aside
                        className="store-sidebar-navigation"
                        data-layout={menu.layout ?? 'vertical'}
                        aria-label={menu.title}
                    >
                        <h2>{menu.title}</h2>
                        <StoreMenuTree
                            menu={menu}
                            orientation={menu.layout ?? 'vertical'}
                        />
                    </aside>
                    <details className="store-sidebar-mobile">
                        <summary>{menu.title}</summary>
                        <nav aria-label={menu.title}>
                            <StoreMenuTree menu={menu} orientation="vertical" />
                        </nav>
                    </details>
                </>
            )}
            {children}
        </div>
    );
}
