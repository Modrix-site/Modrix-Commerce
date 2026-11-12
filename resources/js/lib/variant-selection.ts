type Option = { id: number; values: { id: number }[] };
type Variant = { option_value_ids: number[]; purchasable: boolean };

export function selectionForVariant(
    options: Option[],
    variant?: Variant,
): Record<number, number> {
    return Object.fromEntries(
        options.flatMap((option) => {
            const value = option.values.find((value) =>
                variant?.option_value_ids.includes(value.id),
            );
            return value ? [[option.id, value.id]] : [];
        }),
    );
}

/** Keep the clicked value, preserving as many other selections as possible. */
export function selectVariant<T extends Variant>(
    options: Option[],
    variants: T[],
    selected: Record<number, number>,
    attributeId: number,
    valueId: number,
): T | undefined {
    if (
        !options
            .find((option) => option.id === attributeId)
            ?.values.some((value) => value.id === valueId)
    )
        return undefined;
    const next = { ...selected, [attributeId]: valueId };
    const score = (variant: T) =>
        Object.values(next).filter((id) =>
            variant.option_value_ids.includes(id),
        ).length;
    return variants
        .filter((variant) => variant.option_value_ids.includes(valueId))
        .sort(
            (a, b) =>
                score(b) - score(a) ||
                Number(b.purchasable) - Number(a.purchasable),
        )[0];
}
