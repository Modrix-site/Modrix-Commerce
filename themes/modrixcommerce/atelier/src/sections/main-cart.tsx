import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import type { CartProps } from '@/pages/storefront/cart';
import { PlatformCartPage } from '@/pages/storefront/cart';
import '../style.css';

export default function MainCart({
    section,
    page,
}: ThemeSectionProps<Record<string, unknown>, CartProps>) {
    const _s = section.settings;

    if (!page) return null;

    return (
        <section className="at-frame at-section">
            <PlatformCartPage {...page} />
        </section>
    );
}
