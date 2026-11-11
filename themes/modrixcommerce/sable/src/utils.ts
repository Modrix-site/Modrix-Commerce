export const str = (value: unknown): string =>
    typeof value === 'string' || typeof value === 'number' ? String(value) : '';
