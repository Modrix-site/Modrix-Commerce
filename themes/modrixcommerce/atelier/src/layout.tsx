import type { CSSProperties } from 'react';
import type { ThemeLayoutProps } from '@/lib/theme-runtime';
import { useStoreTheme } from '@/hooks/use-store-theme';
import './style.css';

export default function Layout({ DefaultLayout, children }: ThemeLayoutProps) {
    const { theme } = useStoreTheme();
    return (
        <div
            className="at-shell"
            style={
                {
                    '--at-body-size': `${theme?.settings?.body_size ?? 14}px`,
                    '--at-heading-size': `${theme?.settings?.heading_size ?? 26}px`,
                    '--at-button-size': `${theme?.settings?.button_size ?? 12}px`,
                    '--at-nav-size': `${theme?.settings?.nav_size ?? 11}px`,
                    '--at-card-title-size': `${theme?.settings?.card_title_size ?? 13}px`,
                    '--at-card-price-size': `${theme?.settings?.card_price_size ?? 13}px`,
                    '--at-footer-size': `${theme?.settings?.footer_size ?? 12}px`,
                    '--at-button-radius': `${theme?.settings?.button_radius ?? 0}px`,
                    '--at-button-height': `${theme?.settings?.button_height ?? 46}px`,
                    '--at-surface-sunken': String(
                        theme?.settings?.surface_color ?? '#f1f0ed',
                    ),
                    '--at-accent': String(
                        theme?.settings?.accent_color ?? '#79513d',
                    ),
                    '--at-brand': '#111111',
                    '--at-ink': '#171717',
                    '--at-ink-muted': '#666666',
                    '--at-ink-subtle': '#929292',
                    '--at-line': '#d8d8d8',
                } as CSSProperties
            }
        >
            <DefaultLayout>{children}</DefaultLayout>
        </div>
    );
}
