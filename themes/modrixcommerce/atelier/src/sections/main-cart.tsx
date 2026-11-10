import { Link, usePage } from '@inertiajs/react';
import { ShoppingBag, ArrowRight } from 'lucide-react';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import type { CartProps } from '@/pages/storefront/cart';
import { PlatformCartPage } from '@/pages/storefront/cart';
import { str } from '../components';
import '../style.css';

export default function MainCart({
    section,
    page,
}: ThemeSectionProps<Record<string, unknown>, CartProps>) {
    const s = section.settings;

    if (!page) return null;

    return (
        <section className="at-frame at-section">
            <PlatformCartPage {...page} />
        </section>
    );
}
