import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';
import { BrandedAppLogo, useBranding } from '@/components/brand-logo';

export default function AppLogo() {
    const branding = useBranding();

    // Settings → General → Branding: the store's own logo when uploaded.
    if (branding?.logo || branding?.icon) {
        return <BrandedAppLogo branding={branding} />;
    }

    return <DefaultAppLogo panelName={branding?.panel_name} />;
}

function DefaultAppLogo({ panelName }: { panelName?: string }) {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                <AppLogoIcon className="size-5 fill-current text-white" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold text-[var(--sidebar-brand)]">
                    {panelName ?? name}
                </span>
                <span className="text-[10px] font-medium tracking-wider text-sidebar-foreground/70 uppercase">
                    Dashboard
                </span>
            </div>
        </>
    );
}
