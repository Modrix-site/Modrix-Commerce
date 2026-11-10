import type { CSSProperties } from 'react';
import type { ThemeLayoutProps } from '@/lib/theme-runtime';
import { useStoreTheme } from '@/hooks/use-store-theme';
export default function Layout({ DefaultLayout, children }: ThemeLayoutProps) {
    const { theme } = useStoreTheme();
    return (
        <div
            className="bl-shell"
            style={
                {
                    '--bl-body-size':
                        String(theme?.settings?.body_size ?? '14') + 'px',
                    '--bl-card-size':
                        String(theme?.settings?.card_size ?? '14') + 'px',
                    '--bl-title-size':
                        String(theme?.settings?.page_title_size ?? '32') + 'px',
                    '--bl-product-title-size':
                        String(theme?.settings?.product_title_size ?? '24') +
                        'px',
                    '--bl-home-pattern':
                        theme?.settings?.show_pattern === false
                            ? 'none'
                            : 'url("/theme-assets/modrixcommerce/babylane/assets/doodles.webp")',
                    '--bl-container': {
                        narrow: '960px',
                        default: '1140px',
                        reference: '1280px',
                        wide: '1320px',
                    }[theme?.layout.container ?? 'wide'],
                    '--bl-yellow': 'var(--accent,#fcb800)',
                    '--bl-ink': 'var(--foreground,#222)',
                    '--bl-panel': 'var(--muted,#f5f5f5)',
                    '--bl-line': 'var(--border,#e7e7e7)',
                    '--bl-muted': 'var(--muted-foreground,#777)',
                    '--bl-blue': String(
                        theme?.settings?.product_link_color ?? '#0068ad',
                    ),
                    '--bl-sale': String(
                        theme?.settings?.sale_color ?? '#df3b00',
                    ),
                } as CSSProperties
            }
        >
            <DefaultLayout>{children}</DefaultLayout>
        </div>
    );
}
