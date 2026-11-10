import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { icons, str } from '../components';
export default function Benefits({ section }: ThemeSectionProps) {
    return (
        <section className="re-benefits-band">
            <div className="re-frame re-services">
                {section.blocks.map((b) => {
                    const Icon =
                        icons[str(b.settings.icon) as keyof typeof icons] ??
                        icons.delivery;
                    return (
                        <div
                            key={b.id}
                            className="re-service"
                            {...blockAttributes(b)}
                        >
                            <Icon size={34} strokeWidth={1.3} />
                            <div>
                                <strong>{str(b.settings.heading)}</strong>
                                <p>{str(b.settings.body)}</p>
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
