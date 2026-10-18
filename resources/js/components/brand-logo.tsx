import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

/** Settings → General → Branding, shared with every page. */
export type Branding = {
    panel_name: string;
    accent: string | null;
    login_message: string;
    logo: string | null;
    logo_dark: string | null;
    icon: string | null;
    favicon: string | null;
    login_logo: string | null;
};

export function useBranding(): Branding | null {
    return usePage<{ branding?: Branding | null }>().props.branding ?? null;
}

/** The admin sidebar logo when the store uploaded one. */
export function BrandedAppLogo({ branding }: { branding: Branding }) {
    const mark = branding.icon ?? branding.logo;
    return (
        <>
            {mark && (
                <img
                    src={mark}
                    alt=""
                    className="hidden size-8 shrink-0 rounded-md object-contain group-data-[collapsible=icon]:block"
                />
            )}
            {branding.logo ? (
                <span className="flex min-w-0 items-center group-data-[collapsible=icon]:hidden">
                    <img
                        src={branding.logo}
                        alt={branding.panel_name}
                        className={`h-8 max-w-44 object-contain object-left ${branding.logo_dark ? 'dark:hidden' : ''}`}
                    />
                    {branding.logo_dark && (
                        <img
                            src={branding.logo_dark}
                            alt={branding.panel_name}
                            className="hidden h-8 max-w-44 object-contain object-left dark:block"
                        />
                    )}
                </span>
            ) : (
                <span className="ml-1 truncate text-sm font-semibold group-data-[collapsible=icon]:hidden">
                    {branding.panel_name}
                </span>
            )}
        </>
    );
}

/** The mark above the sign-in form, and the sign-in message. */
export function AuthBrandMark({ name }: { name?: string }) {
    const branding = useBranding();
    const logo = branding?.login_logo ?? branding?.logo ?? null;
    return logo ? (
        <img
            src={logo}
            alt={name ?? branding?.panel_name ?? ''}
            className="h-12 max-w-56 object-contain"
        />
    ) : (
        <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
            <AppLogoIcon className="size-9 fill-current text-[var(--foreground)] dark:text-white" />
        </div>
    );
}

export function AuthBrandMessage() {
    const message = useBranding()?.login_message;
    return message ? (
        <p className="rounded-md bg-muted px-3 py-2 text-center text-sm text-muted-foreground">
            {message}
        </p>
    ) : null;
}
