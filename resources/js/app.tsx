import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import StorefrontLayout from '@/layouts/storefront-layout';
import { isModulePage, modulePagePath, resolveModulePage } from '@/lib/modules';

const appName = import.meta.env.VITE_APP_NAME || 'EcoModular';

const corePages = import.meta.glob<{ default: ComponentType }>(
    './pages/**/*.tsx',
);

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        if (isModulePage(name)) {
            return resolveModulePage(name).then((module) => module.default);
        }

        const page = corePages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return page().then((module) => module.default);
    },
    layout: (pageName) => {
        // Module pages pick their layout from their path inside the module.
        const name = isModulePage(pageName)
            ? modulePagePath(pageName)
            : pageName;

        switch (true) {
            case name.startsWith('install/'):
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
