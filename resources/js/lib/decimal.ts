/** "15.5000" → "15.5" without passing through a float. */
export function trimDecimal(value: string | null | undefined): string {
    if (value === null || value === undefined) {
        return '';
    }

    return value.includes('.')
        ? value.replace(/0+$/, '').replace(/\.$/, '')
        : value;
}
