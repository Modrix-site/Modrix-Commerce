import { Form, Head, router, usePage } from '@inertiajs/react';
import { Eye, Globe, Search, User } from 'lucide-react';
import { pageSettingAttributes } from '@/components/storefront/page-settings/attributes';
import {
    CartIcon,
    SearchIconLink,
    useShowAccountLink,
    useShowCartCount,
} from '@/components/storefront/page-settings/header-parts';
import { useStoreSettings } from '@/hooks/use-store-settings';
import type { ReactNode } from 'react';
import { Suspense } from 'react';
import { themeHeader, themeFooter } from '@/lib/theme-pages';
import {
    ThemeEditorBridge,
    ThemeSectionList,
    useThemeSections,
} from '@/components/storefront/theme-sections';
import { ThemeStyles, themeLayout } from '@/lib/theme-runtime';
import {
    AtelierFooter,
    AtelierHeader,
    AtelierTrustBand,
} from '@/components/storefront/atelier-chrome';
import {
    ElectronicsFooter,
    ElectronicsHeader,
} from '@/components/storefront/electronics-chrome';
import { RecentlyViewed } from '@/components/storefront/recent-products';
import { ExtensionSlot } from '@/components/extensions/extension-slot';
import {
    StoreFooterMenus,
    StoreMenuLink as Link,
} from '@/components/storefront/footer-menus';
import '@/components/storefront/electronics.css';
import '@/components/storefront/atelier.css';
import { SearchToggle } from '@/components/storefront/search-toggle';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { PlaceSlot } from '@/components/extensions/place-slot';
import { CustomerNotificationBell } from '@/components/storefront/customer-notification-bell';
import { SiteHeader } from '@/components/storefront/site-header';
import { StoreMenuTree } from '@/components/storefront/store-menu';
import { StoreNavigationShell } from '@/components/storefront/sidebar-navigation';
import { useStoreTheme } from '@/hooks/use-store-theme';
import { storePageFor } from '@/lib/store-page';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { dashboard, home, login } from '@/routes';
import { show as cartShow } from '@/routes/cart';
import { search } from '@/routes/storefront';
import type { StorefrontShared } from '@/types';

/**
 * Resolves package-owned layout and CSS, with the platform chrome as fallback.
 * Commerce actions and validation remain on the server.
 */
export default function StorefrontLayout({
    children,
}: {
    children: ReactNode;
}) {
    const { theme } = useStoreTheme();
    const CustomLayout = themeLayout(theme ?? undefined);
    useFlashToast();

    return (
        <>
            <ThemeStyles theme={theme ?? undefined} />
            {CustomLayout ? (
                <Suspense
                    fallback={
                        <DefaultStorefrontLayout>
                            {children}
                        </DefaultStorefrontLayout>
                    }
                >
                    <CustomLayout DefaultLayout={DefaultStorefrontLayout}>
                        {children}
                    </CustomLayout>
                </Suspense>
            ) : (
                <DefaultStorefrontLayout>{children}</DefaultStorefrontLayout>
            )}
        </>
    );
}

function DefaultStorefrontLayout({ children }: { children: ReactNode }) {
    const page = usePage();
    const { storefront } = page.props;
    const { header, footer, theme } = useStoreTheme();
    const customPage = [
        'storefront/home',
        'storefront/listing',
        'storefront/product',
        'storefront/cart',
    ].includes(page.component);
    const t = useT();
    const sections = storefront?.themeSections ?? null;
    const CustomHeader = sections
        ? HeaderGroup
        : theme?.frontend?.header
          ? themeHeader(theme.name)
          : undefined;
    const CustomFooter = sections
        ? FooterGroup
        : theme?.frontend?.footer
          ? themeFooter(theme.name)
          : undefined;
    // Customizer styles can differ per page (Homepage, Catalog, Product, Cart).
    const storePage = storePageFor(usePage().component);
    // Header and Catalog page settings, for every theme's CSS.
    const pageSettings = useStoreSettings();

    return (
        <div
            data-theme={theme?.name}
            data-page={storePage}
            {...pageSettingAttributes(pageSettings)}
            data-component={page.component}
            dir={storefront?.direction ?? 'ltr'}
            lang={storefront?.locale}
            className={cn(
                'storefront flex min-h-screen flex-col bg-background text-foreground',
                header === 'electronics' && 'electronics-store',
                header === 'atelier' && 'ecom-atelier',
            )}
        >
            {storefront?.seoDescription && (
                <Head>
                    <meta
                        name="description"
                        content={storefront.seoDescription}
                    />
                </Head>
            )}
            {theme && (
                <>
                    {/* Generated by the server from validated values only. */}
                    <style>{theme.css}</style>
                    {theme.favicon && (
                        <Head>
                            <link rel="icon" href={theme.favicon} />
                        </Head>
                    )}
                </>
            )}
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded focus:bg-background focus:px-3 focus:py-2"
            >
                {t('Skip to content')}
            </a>
            {storefront?.themePreview && (
                <PreviewBanner preview={storefront.themePreview} />
            )}
            {!CustomHeader && theme?.announcement && (
                <div
                    className={
                        header === 'atelier'
                            ? 'ecom-announcement'
                            : 'bg-primary px-4 py-2 text-center text-sm text-primary-foreground'
                    }
                >
                    {theme.announcement}
                </div>
            )}
            {!CustomHeader && (storefront?.currencies?.length ?? 0) > 1 && (
                <div className="hidden border-b bg-muted/30 px-4 py-2 text-sm lg:block">
                    <div className="mx-auto flex max-w-[var(--store-container,72rem)] flex-wrap items-center justify-between gap-2">
                        <span className="text-xs text-muted-foreground">
                            {storefront?.currencyNotice}
                        </span>
                        <label className="flex items-center gap-2">
                            Currency
                            <select
                                aria-label="Store currency"
                                className="rounded border bg-background px-2 py-1"
                                disabled={!storefront?.canSwitchCurrency}
                                value={storefront?.displayCurrency}
                                onChange={(e) => {
                                    const params = new URLSearchParams(
                                        window.location.search,
                                    );
                                    params.set('currency', e.target.value);
                                    router.visit(
                                        `${window.location.pathname}?${params.toString()}`,
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                {storefront?.currencies?.map((c) => (
                                    <option key={c.code} value={c.code}>
                                        {c.code} — {c.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                </div>
            )}
            <SiteHeader>
                {CustomHeader ? (
                    <Suspense
                        fallback={
                            <div className="h-32" aria-label="Loading header" />
                        }
                    >
                        <CustomHeader />
                    </Suspense>
                ) : header === 'electronics' ? (
                    <ElectronicsHeader />
                ) : header === 'atelier' ? (
                    <AtelierHeader />
                ) : header === 'centered' ? (
                    <CenteredHeader />
                ) : header === 'minimal' ? (
                    <MinimalHeader />
                ) : (
                    <ClassicHeader />
                )}
            </SiteHeader>
            <StoreNavigationShell>
                <main
                    id="main"
                    className={cn(
                        'flex-1',
                        (!CustomHeader || !customPage) &&
                            header !== 'atelier' &&
                            'mx-auto w-full max-w-[var(--store-container,72rem)] px-4 py-8',
                    )}
                >
                    {children}
                </main>
            </StoreNavigationShell>
            {header === 'electronics' &&
                !page.component.startsWith('storefront/auth/') && (
                    <RecentlyViewed />
                )}
            {!CustomFooter &&
                footer === 'atelier' &&
                theme?.settings?.show_trust_band !== false && (
                    <AtelierTrustBand />
                )}
            {CustomFooter ? (
                <Suspense fallback={null}>
                    <CustomFooter />
                </Suspense>
            ) : footer === 'electronics' ? (
                <ElectronicsFooter />
            ) : footer === 'atelier' ? (
                <AtelierFooter />
            ) : footer === 'columns' ? (
                <ColumnsFooter />
            ) : (
                <SimpleFooter />
            )}
            {/* Global module overlays (marketing popups, cookie bars). */}
            <ExtensionSlot items={storefront?.overlays} />
            <ThemeEditorBridge />
        </div>
    );
}

/** Header and footer section groups of a theme built from sections. */
function HeaderGroup() {
    const sections = useThemeSections();

    return sections ? (
        <ThemeSectionList sections={sections.header} area="header" />
    ) : null;
}

function FooterGroup() {
    const sections = useThemeSections();

    return sections ? (
        <ThemeSectionList sections={sections.footer} area="footer" />
    ) : null;
}

function Brand({ className }: { className?: string }) {
    const { storefront } = usePage().props;
    const name = storefront?.storeName ?? '';
    const logo = storefront?.theme.logo;

    return (
        <Link
            href={home()}
            className={cn('text-lg font-semibold', className)}
            aria-label={logo ? name : undefined}
        >
            {logo ? (
                <img src={logo} alt="" className="h-8 w-auto max-w-48" />
            ) : (
                name
            )}
        </Link>
    );
}

function SearchForm({ className }: { className?: string }) {
    const t = useT();

    return (
        <Form
            {...search.form()}
            className={cn('flex items-center gap-2', className)}
            role="search"
        >
            <Input
                type="search"
                name="q"
                placeholder={t('Search products')}
                aria-label={t('Search products')}
            />
            <Button
                type="submit"
                variant="outline"
                size="icon"
                aria-label={t('Search')}
            >
                <Search />
            </Button>
        </Form>
    );
}

/** Shown when the store offers more than one language. */
function LanguageSwitcher() {
    const { storefront } = usePage().props;
    const t = useT();

    if (!storefront || storefront.locales.length < 2) {
        return null;
    }

    const change = (code: string) => {
        const params = new URLSearchParams(window.location.search);
        params.set('lang', code);
        router.visit(`${window.location.pathname}?${params.toString()}`, {
            preserveScroll: true,
        });
    };

    return (
        <label className="flex items-center gap-1 text-sm">
            <Globe className="size-4" aria-hidden />
            <span className="sr-only">{t('Language')}</span>
            <select
                value={storefront.locale}
                onChange={(e) => change(e.target.value)}
                className="h-8 rounded-md border bg-transparent px-1 text-sm"
            >
                {storefront.locales.map((l) => (
                    <option key={l.code} value={l.code} lang={l.code}>
                        {l.name}
                    </option>
                ))}
            </select>
        </label>
    );
}

function AccountLinks() {
    const { storefront, auth } = usePage().props;
    const cartCount = storefront?.cartCount ?? 0;
    const t = useT();
    const showAccount = useShowAccountLink();
    const showCount = useShowCartCount();

    return (
        <nav className="flex items-center gap-1" aria-label="Account">
            <LanguageSwitcher />
            <PlaceSlot
                place="header.actions"
                className="flex items-center gap-1"
            />
            <CustomerNotificationBell />
            <SearchIconLink />
            <Button
                variant="ghost"
                size="sm"
                asChild
                className={showAccount ? undefined : 'hidden'}
            >
                <Link href={auth.user ? dashboard() : login()}>
                    <User />
                    <span className="hidden sm:inline">
                        {auth.user
                            ? auth.isStaff
                                ? t('Admin')
                                : t('Account')
                            : t('Log in')}
                    </span>
                </Link>
            </Button>
            <Button variant="outline" size="sm" asChild>
                <Link
                    href={cartShow()}
                    aria-label={t('Cart, :count items', { count: cartCount })}
                >
                    <CartIcon />
                    {showCount && (
                        <span className="tabular-nums">{cartCount}</span>
                    )}
                </Link>
            </Button>
        </nav>
    );
}

function CategoryNav({ centered = false }: { centered?: boolean }) {
    const { storefront } = usePage().props;
    const t = useT();

    if (!storefront) {
        return null;
    }

    return (
        <nav aria-label={t('Categories')} className="border-t">
            <StoreMenuTree
                menu={storefront?.navigation?.header}
                className={cn(
                    'mx-auto max-w-[var(--store-container,72rem)] gap-5 px-4 text-sm',
                    centered && 'justify-center',
                )}
            />
        </nav>
    );
}

function ClassicHeader() {
    return (
        <header className="border-b">
            <div className="mx-auto flex max-w-[var(--store-container,72rem)] flex-wrap items-center gap-3 px-4 py-3">
                <Brand className="mr-auto" />
                <SearchForm className="order-last w-full sm:order-none sm:w-72" />
                <AccountLinks />
            </div>
            <CategoryNav />
        </header>
    );
}

function CenteredHeader() {
    return (
        <header className="border-b">
            <div className="mx-auto grid max-w-[var(--store-container,72rem)] grid-cols-[1fr_auto_1fr] items-center gap-3 px-4 py-4">
                <SearchForm className="hidden w-64 md:flex" />
                <Brand className="col-start-2 text-center text-xl tracking-wide" />
                <div className="col-start-3 flex justify-end">
                    <AccountLinks />
                </div>
            </div>
            <CategoryNav centered />
        </header>
    );
}

function MinimalHeader() {
    const { storefront } = usePage().props;
    const t = useT();

    return (
        <header className="border-b">
            <div className="mx-auto flex max-w-[var(--store-container,72rem)] items-center gap-6 px-4 py-3">
                <Brand />
                <nav
                    aria-label={t('Categories')}
                    className="mr-auto hidden gap-5 text-sm md:flex"
                >
                    <StoreMenuTree menu={storefront?.navigation?.header} />
                </nav>
                <SearchToggle
                    className={buttonVariants({
                        variant: 'ghost',
                        size: 'icon',
                        className: 'ml-auto md:ml-0',
                    })}
                >
                    <Search />
                </SearchToggle>
                <AccountLinks />
            </div>
        </header>
    );
}

function SimpleFooter() {
    const { storefront } = usePage().props;
    const t = useT();

    return (
        <footer className="grid gap-1 border-t px-4 py-6 text-center text-xs text-muted-foreground">
            <StoreFooterMenus />
            {storefront?.theme.footerText && (
                <p className="whitespace-pre-line">
                    {storefront.theme.footerText}
                </p>
            )}
            <p>
                © {new Date().getFullYear()} {storefront?.storeName}.{' '}
                {t('Powered by Modrix Commerce, open-source commerce (MIT).')}
            </p>
        </footer>
    );
}

function ColumnsFooter() {
    const { storefront } = usePage().props;
    return (
        <footer className="border-t bg-muted/40">
            <StoreFooterMenus />
            <p className="px-4 pb-6 text-center text-xs text-muted-foreground">
                {storefront?.theme.footerText ?? storefront?.storeName}
            </p>
        </footer>
    );
}

function PreviewBanner({
    preview,
}: {
    preview: NonNullable<StorefrontShared['themePreview']>;
}) {
    return (
        <div
            role="status"
            className="flex flex-wrap items-center justify-center gap-3 bg-amber-100 px-4 py-2 text-sm text-amber-950"
        >
            <Eye className="size-4" aria-hidden />
            <span>
                Previewing <strong>{preview.displayName}</strong>. Only you can
                see this.
            </span>
            <a href={preview.manageUrl} className="underline">
                Manage themes
            </a>
            <Link
                href={preview.exitUrl}
                method="post"
                as="button"
                className="underline"
            >
                Exit preview
            </Link>
        </div>
    );
}
