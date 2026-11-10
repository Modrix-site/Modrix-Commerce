import { Head } from '@inertiajs/react';

type Props = {
    title: string;
    description: string;
    canonical: string | null;
    robots: string | null;
    image: string | null;
    openGraph: boolean;
    jsonLd: string | null;
    verification: string | null;
};

/**
 * Canonical, social and structured-data tags for the current page.
 * Title and description text are applied through the props each theme reads.
 */
export default function SeoTags({
    title,
    description,
    canonical,
    robots,
    image,
    openGraph,
    jsonLd,
    verification,
}: Props) {
    return (
        <Head title={title || undefined}>
            {robots && (
                <meta name="robots" head-key="robots" content={robots} />
            )}
            {canonical && (
                <link
                    rel="canonical"
                    head-key="canonical"
                    href={canonical}
                />
            )}
            {verification && (
                <meta
                    name="google-site-verification"
                    head-key="google-site-verification"
                    content={verification}
                />
            )}
            {openGraph && title && (
                <meta property="og:title" head-key="og:title" content={title} />
            )}
            {openGraph && description && (
                <meta
                    property="og:description"
                    head-key="og:description"
                    content={description}
                />
            )}
            {openGraph && canonical && (
                <meta property="og:url" head-key="og:url" content={canonical} />
            )}
            {openGraph && (
                <meta
                    property="og:type"
                    head-key="og:type"
                    content={jsonLd?.includes('"@type":"Product"') ? 'product' : 'website'}
                />
            )}
            {openGraph && image && (
                <meta property="og:image" head-key="og:image" content={image} />
            )}
            {openGraph && (
                <meta
                    name="twitter:card"
                    head-key="twitter:card"
                    content={image ? 'summary_large_image' : 'summary'}
                />
            )}
            {openGraph && title && (
                <meta
                    name="twitter:title"
                    head-key="twitter:title"
                    content={title}
                />
            )}
            {openGraph && description && (
                <meta
                    name="twitter:description"
                    head-key="twitter:description"
                    content={description}
                />
            )}
            {jsonLd && (
                <script
                    type="application/ld+json"
                    head-key="jsonld"
                    dangerouslySetInnerHTML={{ __html: jsonLd }}
                />
            )}
        </Head>
    );
}
