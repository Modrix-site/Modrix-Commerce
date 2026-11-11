import { ProductDescription } from '@/components/storefront/product-description';
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { str } from '../components';

type PageContent = {
    title: string;
    content: string | null;
    content_html: string | null;
    kind?: string;
    faqs?: {
        question: string;
        answer: string | null;
        answer_html: string | null;
    }[];
};

export default function MainPage({
    section,
    page,
}: ThemeSectionProps<Record<string, unknown>, { page?: PageContent }>) {
    const s = section.settings;
    const p = page?.page;
    if (!p) return null;

    const width = str(s.content_width, 'medium');
    const maxW =
        width === 'narrow'
            ? 'max-w-2xl'
            : width === 'wide'
              ? 'max-w-5xl'
              : 'max-w-3xl';

    return (
        <article className="at-frame at-section">
            <div className={`mx-auto ${maxW}`}>
                {s.show_title !== false && (
                    <h1 className="mb-8 border-b border-neutral-200 pb-4 text-3xl font-normal tracking-wide uppercase">
                        {p.title}
                    </h1>
                )}

                <ProductDescription
                    product={{
                        description_html: p.content_html,
                        description: p.content,
                    }}
                    className="prose prose-neutral max-w-none space-y-4 leading-relaxed text-neutral-700"
                />

                {p.kind === 'faq' && p.faqs && (
                    <div className="at-accordions mt-8">
                        {p.faqs.map((f, i) => (
                            <details className="at-accordion" key={i}>
                                <summary>{f.question}</summary>
                                <div className="at-accordion-content">
                                    <ProductDescription
                                        product={{
                                            description_html: f.answer_html,
                                            description: f.answer,
                                        }}
                                    />
                                </div>
                            </details>
                        ))}
                    </div>
                )}
            </div>
        </article>
    );
}
