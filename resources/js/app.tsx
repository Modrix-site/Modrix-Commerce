import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { initializeAdminAccent } from '@/hooks/use-admin-accent';
import { initializeSidebarAppearance } from '@/hooks/use-sidebar-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import StorefrontLayout from '@/layouts/storefront-layout';
import { isModulePage, modulePagePath, resolveModulePage } from '@/lib/modules';
import { withThemePage } from '@/lib/theme-runtime';

const appName = import.meta.env.VITE_APP_NAME || 'Modrix Commerce';

type TitlePage = {
    props?: {
        branding?: { panel_name?: string | null } | null;
        storefront?: { storeName?: string | null } | null;
        [key: string]: unknown;
    };
};

function storeTitleName(page?: TitlePage): string {
    const store =
        page?.props?.branding?.panel_name?.trim() ||
        page?.props?.storefront?.storeName?.trim() ||
        '';

    return store !== '' ? store : appName;
}

function documentTitle(title: string, page?: TitlePage): string {
    const store = storeTitleName(page);
    const pageTitle = title.trim();

    if (pageTitle === '') {
        return store;
    }

    // Advanced SEO titles already include the store ("Name | Store", "{store} …").
    if (pageTitle.includes(store)) {
        return pageTitle;
    }

    return `${pageTitle} - ${store}`;
}

const corePages = import.meta.glob<{ default: ComponentType }>(
    './pages/**/*.tsx',
);

void createInertiaApp({
    title: (title, page) => documentTitle(title, page),
    resolve: (name) => {
        if (isModulePage(name)) {
            return resolveModulePage(name).then((module) => module.default);
        }

        const page = corePages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return page().then((module) => withThemePage(name, module.default));
    },
    layout: (pageName) => {
        // Module pages pick their layout from their path inside the module.
        const name = isModulePage(pageName)
            ? modulePagePath(pageName)
            : pageName;

        switch (true) {
            case name.startsWith('install/'):
            // The theme editor is a full-screen workspace, like Shopify's.
            case name === 'admin/themes/editor':
                return null;
            case name.startsWith('storefront/'):
                return StorefrontLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
initializeAdminAccent();
initializeSidebarAppearance();
