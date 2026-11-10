import type { CSSProperties } from 'react';
import type { ThemeLayoutProps } from '@/lib/theme-runtime';
import { useStoreTheme } from '@/hooks/use-store-theme';
export default function Layout({ DefaultLayout, children }: ThemeLayoutProps) {
    const { theme } = useStoreTheme();
    return (
        <div
            className="vm-shell"
            style={
                {
                    '--vm-container': {
                        narrow: '960px',
                        default: '1280px',
                        reference: '1440px',
                        wide: '1680px',
                    }[theme?.layout.container ?? 'wide'],
                    '--vm-yellow': 'var(--primary,#fcb800)',
                    '--vm-ink': 'var(--foreground,#222)',
                    '--vm-panel': 'var(--muted,#f5f5f5)',
                    '--vm-line': 'var(--border,#e7e7e7)',
                    '--vm-muted': 'var(--muted-foreground,#777)',
                    '--vm-blue': String(
                        theme?.settings?.product_link_color ?? '#0068ad',
                    ),
                    '--vm-sale': String(
                        theme?.settings?.sale_color ?? '#df3b00',
                    ),
                } as CSSProperties
            }
        >
            <DefaultLayout>{children}</DefaultLayout>
        </div>
    );
}
