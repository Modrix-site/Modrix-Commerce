import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { AuthBrandMessage, useBranding } from '@/components/brand-logo';
import { useT } from '@/lib/i18n';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const t = useT();
    const branding = useBranding();
    const { name } = usePage().props;
    const panel = branding?.panel_name || name;
    const logo = branding?.login_logo ?? branding?.logo ?? null;

    return (
        <div className="admin-shell flex min-h-svh bg-[#f8f9fb] dark:bg-background">
            <aside className="relative hidden w-[46%] max-w-3xl shrink-0 flex-col justify-between bg-[#090909] px-12 py-10 text-white lg:flex">
                <Link
                    href={home()}
                    className="flex w-fit items-center gap-3"
                >
                    <BrandTile logo={logo} name={panel} />
                    <span className="grid">
                        <span className="text-sm font-semibold text-[#e4e7ec]">
                            {panel}
                        </span>
                        <span className="text-[10px] font-medium tracking-wider text-[#858b94] uppercase">
                            {t('Dashboard')}
                        </span>
                    </span>
                </Link>

                <div className="max-w-md">
                    <p className="text-sm font-medium text-[#00ad68]">
                        {t('Store admin')}
                    </p>
                    <h2 className="mt-3 text-4xl leading-tight font-semibold tracking-tight">
                        {t(
                            'Sign in to manage orders, products and payments.',
                        )}
                    </h2>
                </div>

                <Link
                    href={home()}
                    className="w-fit text-sm text-[#858b94] hover:text-white"
                >
                    {t('Back to the store')}
                </Link>
            </aside>

            <main className="flex min-w-0 flex-1 flex-col justify-center px-6 py-10 sm:px-10">
                <div className="mx-auto flex w-full max-w-sm flex-col gap-8">
                    <Link
                        href={home()}
                        className="flex items-center gap-3 lg:hidden"
                    >
                        <BrandTile logo={logo} name={panel} />
                        <span className="text-sm font-semibold">{panel}</span>
                    </Link>

                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    <AuthBrandMessage />
                    {children}
                </div>
            </main>
        </div>
    );
}

function BrandTile({ logo, name }: { logo: string | null; name: string }) {
    if (logo) {
        return (
            <img
                src={logo}
                alt={name}
                className="h-10 max-w-40 object-contain"
            />
        );
    }

    return (
        <span className="flex size-10 items-center justify-center rounded-md bg-[#00995b] text-white">
            <AppLogoIcon className="size-5 fill-current" />
        </span>
    );
}
