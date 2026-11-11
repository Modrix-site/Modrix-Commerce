import type { CSSProperties } from 'react';
import type { ThemeLayoutProps } from '@/lib/theme-runtime';
import { useStoreTheme } from '@/hooks/use-store-theme';
export default function Layout({ DefaultLayout, children }: ThemeLayoutProps) {
    const { theme } = useStoreTheme();
    return (
        <div
            className="lv-shell"
            style={
                {
                    '--lv-width': {
                        narrow: '1200px',
                        default: '1440px',
                        reference: '1680px',
                        wide: '1840px',
                    }[theme?.layout.container ?? 'wide'],
                    '--lv-body-size': `${theme?.settings?.body_size ?? 14}px`,
                    '--lv-card-size': `${theme?.settings?.card_size ?? 14}px`,
                    '--lv-heading-size': `${theme?.settings?.heading_size ?? 28}px`,
                    '--lv-product-title': `${theme?.settings?.product_title_size ?? 36}px`,
                    '--lv-footer-size': `${theme?.settings?.footer_size ?? 12}px`,
                    '--lv-nav-size': `${theme?.settings?.nav_size ?? 13}px`,
                    '--lv-button-size': `${theme?.settings?.button_size ?? 11}px`,
                    '--lv-catalog-title': `${theme?.settings?.catalog_title_size ?? 36}px`,
                    '--lv-card-ratio':
                        (
                            {
                                square: '1/1',
                                portrait: '1/1.12',
                                tall: '3/4',
                            } as Record<string, string>
                        )[String(theme?.settings?.card_ratio)] ?? '1/1.12',
                    '--lv-green': 'var(--primary,#004236)',
                    '--lv-muted': 'var(--muted-foreground,#777)',
                    '--lv-sale': String(
                        theme?.settings?.sale_color ?? '#c63842',
                    ),
                    '--lv-badge': String(
                        theme?.settings?.badge_color ?? '#ffda52',
                    ),
                } as CSSProperties
            }
        >
            <DefaultLayout>{children}</DefaultLayout>
        </div>
    );
}
