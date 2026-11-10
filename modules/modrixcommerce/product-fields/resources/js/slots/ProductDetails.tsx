type Field = { label: string; value: string; type: string };

/**
 * Product page: the custom fields switched to "Show on product page" that
 * this product has a value for, as a simple details list.
 */
export default function ProductDetails({
    heading,
    fields,
}: {
    heading: string;
    fields: Field[];
}) {
    if (fields.length === 0) {
        return null;
    }

    return (
        <section className="product-fields grid gap-3" aria-label={heading}>
            <h2 className="text-base font-semibold">{heading}</h2>
            <dl className="divide-y rounded-[var(--radius)] border">
                {fields.map((field) => (
                    <div
                        key={field.label}
                        className="grid gap-1 px-4 py-3 text-sm sm:grid-cols-[minmax(8rem,30%)_1fr] sm:gap-4"
                    >
                        <dt className="text-muted-foreground">{field.label}</dt>
                        <dd className="break-words whitespace-pre-line">
                            {field.type === 'url' ? (
                                <a
                                    href={field.value}
                                    target="_blank"
                                    rel="noopener noreferrer nofollow"
                                    className="underline underline-offset-2"
                                >
                                    {field.value}
                                </a>
                            ) : (
                                field.value
                            )}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
