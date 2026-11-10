import { PlatformCartPage, type CartProps } from '@/pages/storefront/cart';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str } from '../components';
export default function Cart({
    section,
    page,
}: ThemeSectionProps<Record<string, unknown>, CartProps>) {
    return page ? (
        <section className="vm-frame vm-page">
            <h1>{str(section.settings.heading)}</h1>
            <PlatformCartPage {...page} />
        </section>
    ) : null;
}
