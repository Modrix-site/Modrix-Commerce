import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str, icons } from '../components';
export default function Benefits({ section }: ThemeSectionProps) {
    if (!section.blocks.length) return null;
    return (
        <section className="vm-frame vm-services">
            {section.blocks.map((b) => {
                const Icon =
                    icons[str(b.settings.icon) as keyof typeof icons] ??
                    icons.delivery;
                return (
                    <div
                        className="vm-service"
                        key={b.id}
                        {...blockAttributes(b)}
                    >
                        <Icon size={32} aria-hidden />
                        <div>
                            <strong>{str(b.settings.heading)}</strong>
                            <p>{str(b.settings.body)}</p>
                        </div>
                    </div>
                );
            })}
        </section>
    );
}
