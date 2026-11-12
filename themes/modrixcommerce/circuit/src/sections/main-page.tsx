import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { Frame } from '../section-adapters';

type Page = {
    title: string;
    content: string | null;
    content_html: string | null;
};

export default function MainPageSection({
    page,
}: ThemeSectionProps<Record<string, unknown>, { page?: Page }>) {
    const content = page?.page;

    if (!content) {
        return null;
    }

    return (
        <Frame>
            <article className="ci-section ci-page py-6">
                <h1 className="mb-6 text-3xl font-semibold tracking-tight">
                    {content.title}
                </h1>
                {content.content_html ? (
                    <div
                        className="rich-text leading-7"
                        dangerouslySetInnerHTML={{
                            __html: content.content_html,
                        }}
                    />
                ) : (
                    content.content && (
                        <div className="rich-text leading-7 whitespace-pre-line">
                            {content.content}
                        </div>
                    )
                )}
            </article>
        </Frame>
    );
}
