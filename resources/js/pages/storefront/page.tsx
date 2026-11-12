import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    ThemeTemplate,
    useThemeSections,
} from '@/components/storefront/theme-sections';

type Faq = {
    question: string;
    answer: string | null;
    answer_html: string | null;
};

export default function ContentPage({
    page,
}: {
    page: {
        title: string;
        kind?: string;
        content: string | null;
        content_html: string | null;
        faqs?: Faq[];
        meta_title: string | null;
        meta_description: string | null;
    };
}) {
    const sections = useThemeSections();
    const [open, setOpen] = useState(0);
    const faqs = page.kind === 'faq' ? (page.faqs ?? []) : [];

    if (sections)
        return (
            <>
                <Head title={page.meta_title || page.title}>
                    {page.meta_description && (
                        <meta
                            name="description"
                            content={page.meta_description}
                        />
                    )}
                </Head>
                <ThemeTemplate page={{ page }} />
            </>
        );

    return (
        <>
            <Head title={page.meta_title || page.title}>
                {page.meta_description && (
                    <meta name="description" content={page.meta_description} />
                )}
            </Head>
            <article className="flex min-h-[32rem] w-full flex-col py-6 sm:min-h-[40rem] sm:py-10">
                <h1 className="mb-8 text-4xl font-semibold tracking-tight sm:text-5xl">
                    {page.title}
                </h1>
                {page.content_html ? (
                    <div
                        className="rich-text text-lg leading-8 sm:text-xl sm:leading-9"
                        dangerouslySetInnerHTML={{
                            __html: page.content_html,
                        }}
                    />
                ) : (
                    page.content && (
                        <div className="rich-text text-lg leading-8 whitespace-pre-line sm:text-xl sm:leading-9">
                            {page.content}
                        </div>
                    )
                )}
                {faqs.length > 0 && (
                    <div className="mt-12 divide-y rounded-xl border">
                        {faqs.map((item, index) => (
                            <details
                                key={item.question}
                                className="group"
                                open={open === index}
                                onToggle={(event) => {
                                    const details = event.currentTarget;
                                    if (details.open) {
                                        setOpen(index);
                                    } else if (open === index) {
                                        setOpen(-1);
                                    }
                                }}
                            >
                                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-5 text-lg font-medium [&::-webkit-details-marker]:hidden">
                                    <span>{item.question}</span>
                                    <span
                                        aria-hidden
                                        className="text-lg leading-none text-muted-foreground group-open:hidden"
                                    >
                                        +
                                    </span>
                                    <span
                                        aria-hidden
                                        className="hidden text-lg leading-none text-muted-foreground group-open:inline"
                                    >
                                        −
                                    </span>
                                </summary>
                                {item.answer_html ? (
                                    <div
                                        className="rich-text px-5 pb-6 text-base leading-7 sm:text-lg"
                                        dangerouslySetInnerHTML={{
                                            __html: item.answer_html,
                                        }}
                                    />
                                ) : (
                                    item.answer && (
                                        <p className="px-5 pb-6 text-base leading-7 whitespace-pre-line sm:text-lg">
                                            {item.answer}
                                        </p>
                                    )
                                )}
                            </details>
                        ))}
                    </div>
                )}
            </article>
        </>
    );
}
