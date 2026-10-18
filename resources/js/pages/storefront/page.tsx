import { Head } from '@inertiajs/react';
export default function ContentPage({
    page,
}: {
    page: {
        title: string;
        content: string | null;
        meta_title: string | null;
        meta_description: string | null;
    };
}) {
    return (
        <>
            <Head title={page.meta_title || page.title}>
                {page.meta_description && (
                    <meta name="description" content={page.meta_description} />
                )}
            </Head>
            <article className="mx-auto w-full max-w-5xl px-6 py-12">
                <h1 className="mb-8 text-3xl font-semibold">{page.title}</h1>
                <div className="leading-7 whitespace-pre-wrap">
                    {page.content}
                </div>
            </article>
        </>
    );
}
