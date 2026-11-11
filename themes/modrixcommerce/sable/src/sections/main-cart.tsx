import { PlatformCartPage, type CartProps } from '@/pages/storefront/cart';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str } from '../utils';
export default function MainCart({
    section,
    page,
}: ThemeSectionProps<Record<string, unknown>, CartProps>) {
    return page ? (
        <div className="sa-frame sa-cart-page">
            <h1>{str(section.settings.heading)}</h1>
            <PlatformCartPage {...page} />
        </div>
    ) : null;
}
