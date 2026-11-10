import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { CircuitSection } from '../components';
import { Voucher } from '../index';
import { Frame, legacy } from '../section-adapters';
export default function VoucherBannerSection({ section }: ThemeSectionProps) {
    return (
        <CircuitSection section={section}>
            <Frame>
                <Voucher section={legacy(section, 'promo_banner')} />
            </Frame>
        </CircuitSection>
    );
}
