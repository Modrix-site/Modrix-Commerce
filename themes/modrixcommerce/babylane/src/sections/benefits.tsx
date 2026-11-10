import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { icons, str } from '../components';
export default function Benefits({ section }: ThemeSectionProps) {
    return (
        <section className="bl-benefits-band">
            <div className="bl-frame bl-services">
                {section.blocks.map((b) => {
                    const Icon =
                        icons[str(b.settings.icon) as keyof typeof icons] ??
                        icons.delivery;
                    return (
                        <div
                            key={b.id}
                            className="bl-service"
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
