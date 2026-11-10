import { Leaf, Truck, Heart, Recycle, HandHeart } from 'lucide-react';
import {
    blockAttributes,
    type ThemeSectionProps,
} from '@/components/storefront/theme-sections';
import { str } from '../components';
const icons = {
    leaf: Leaf,
    delivery: Truck,
    heart: Heart,
    recycle: Recycle,
    care: HandHeart,
};
export default function Values({ section }: ThemeSectionProps) {
    return (
        <section className="lv-frame lv-values">
            {section.blocks.map((b) => {
                const Icon =
                    icons[str(b.settings.icon) as keyof typeof icons] ?? Leaf;
                return (
                    <div
                        key={b.id}
                        {...blockAttributes(b)}
                        style={{ background: str(b.settings.background) }}
                    >
                        <Icon size={30} strokeWidth={1} />
                        <h3>{str(b.settings.heading)}</h3>
                        <p>{str(b.settings.body)}</p>
                    </div>
                );
            })}
        </section>
    );
}
