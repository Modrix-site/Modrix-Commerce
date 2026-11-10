import { usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import type { ThemeLayoutProps } from '@/lib/theme-runtime';

/** Compose the core shell to retain previews, sidebar, overlays and extension hooks. */
export default function PawzyLayout({
    children,
    DefaultLayout,
}: ThemeLayoutProps) {
    const s = usePage().props.storefront?.theme.settings ?? {};
    return (
        <div
            className="pawzy-theme"
            data-sidebar={s.show_sidebar !== false}
            style={
                {
                    '--pawzy-navy': s.header_background ?? '#38495f',
                    '--pawzy-footer': s.footer_background ?? '#f7f3f7',
                    '--pawzy-gap': `${Number(s.home_gap ?? 72)}px`,
                    '--pawzy-hero-height': `${Number(s.hero_height ?? 440)}px`,
                    '--pawzy-hero-mobile-height': `${Number(s.hero_mobile_height ?? 420)}px`,
                    '--pawzy-hero-title': `${Number(s.hero_title_size ?? 58)}px`,
                    '--pawzy-hero-title-mobile': `${Number(s.hero_mobile_title_size ?? 34)}px`,
                    '--pawzy-hero-image-width': `${Number(s.hero_image_width ?? 52)}%`,
                    '--pawzy-hero-image-fit': String(s.hero_image_fit ?? 'cover'),
                    '--pawzy-hero-content-width': `${Number(s.hero_content_width ?? 43)}%`,
                    '--pawzy-hero-radius': `${Number(s.hero_radius ?? 22)}px`,
                    '--pawzy-hero-shade': String(s.hero_shade ?? '#000000'),
                    '--pawzy-overlay': Number(s.hero_overlay ?? 0) / 100,
                } as CSSProperties
            }
        >
            <DefaultLayout>{children}</DefaultLayout>
        </div>
    );
}
