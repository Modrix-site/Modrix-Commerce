import type { CSSProperties } from 'react';
import type { ThemeLayoutProps } from '@/lib/theme-runtime';
import { useStoreTheme } from '@/hooks/use-store-theme';
export default function Layout({ DefaultLayout, children }: ThemeLayoutProps) {
    const { theme } = useStoreTheme();
    return (
        <div
            className="re-shell"
            style={
                {
                    '--re-container': {
                        narrow: '960px',
                        default: '1140px',
                        reference: '1280px',
                        wide: '1320px',
                    }[theme?.layout.container ?? 'wide'],
                    '--re-body-size':
                        String(theme?.settings?.body_size ?? '14') + 'px',
                    '--re-card-size':
                        String(theme?.settings?.card_size ?? '14') + 'px',
                    '--re-title-size':
                        String(theme?.settings?.page_title_size ?? '32') + 'px',
                    '--re-product-title-size':
                        String(theme?.settings?.product_title_size ?? '24') +
                        'px',
                    '--re-yellow': 'var(--primary,#006fe6)',
                    '--re-ink': 'var(--foreground,#222)',
                    '--re-panel': 'var(--muted,#f5f5f5)',
                    '--re-line': 'var(--border,#e5e5e5)',
                    '--re-muted': 'var(--muted-foreground,#888)',
                    '--re-blue': String(
                        theme?.settings?.product_link_color ?? '#1681dc',
                    ),
                    '--re-sale': String(
                        theme?.settings?.sale_color ?? '#ef451c',
                    ),
                    '--re-deal': String(
                        theme?.settings?.deal_price_color ?? '#6b980d',
                    ),
                } as CSSProperties
            }
        >
            <DefaultLayout>{children}</DefaultLayout>
        </div>
    );
}
