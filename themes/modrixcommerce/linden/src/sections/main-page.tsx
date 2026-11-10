import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
type Content = {
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
export default function Page({
    page,
}: ThemeSectionProps<Record<string, unknown>, { page?: Content }>) {
    const p = page?.page;
    return p ? (
        <article className="lv-frame lv-page">
            <h1>{p.title}</h1>
            {p.content_html ? (
                <div
                    className="rich-text"
                    dangerouslySetInnerHTML={{ __html: p.content_html }}
                />
            ) : (
                <p>{p.content}</p>
            )}
            {p.kind === 'faq' &&
                p.faqs?.map((f) => (
                    <details key={f.question}>
                        <summary>{f.question}</summary>
                        {f.answer_html ? (
                            <div
                                className="rich-text"
                                dangerouslySetInnerHTML={{
                                    __html: f.answer_html,
                                }}
                            />
                        ) : (
                            <p>{f.answer}</p>
                        )}
                    </details>
                ))}
        </article>
    ) : null;
}
