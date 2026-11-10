import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { Frame } from '../section-adapters';

export default function RichTextSection({
    section,
}: ThemeSectionProps<{ heading: string; text: string; alignment: string }>) {
    const { heading, text, alignment } = section.settings;

    return (
        <Frame>
            <section
                className={`ci-section ci-rich-text ${alignment === 'left' ? 'text-left' : 'text-center'}`}
            >
                {heading && <h2 className="ci-rich-text-heading">{heading}</h2>}
                {text && (
                    <p className="ci-rich-text-body whitespace-pre-line">
                        {text}
                    </p>
                )}
            </section>
        </Frame>
    );
}
