import type { CSSProperties } from 'react';
import type { ThemeLayoutProps } from '@/lib/theme-runtime';
import { useStoreTheme } from '@/hooks/use-store-theme';
export default function Layout({ DefaultLayout, children }: ThemeLayoutProps) {
    const { theme } = useStoreTheme();
    return (
        <div
            className="sa-shell"
            style={
                {
                    '--sa-panel': String(
                        theme?.settings?.image_background ?? '#f3f2ef',
                    ),
                } as CSSProperties
            }
        >
            <DefaultLayout>{children}</DefaultLayout>
        </div>
    );
}
