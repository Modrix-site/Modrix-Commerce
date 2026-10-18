/** Same-origin JSON requests using Laravel's encrypted XSRF cookie. */
export async function aiRequest<T>(
    url: string,
    body: object,
    signal?: AbortSignal,
): Promise<T> {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        signal,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
        },
        body: JSON.stringify(body),
    });
    if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as {
            errors?: Record<string, string[]>;
        } | null;
        const message = Object.values(payload?.errors ?? {}).flat()[0];
        throw new Error(
            message ??
                (response.status === 429
                    ? 'Too many AI requests. Wait a minute and try again.'
                    : response.status === 419
                      ? 'Your session expired. Refresh the page and try again.'
                      : 'The AI request failed. Check your connection and settings.'),
        );
    }
    return (await response.json()) as T;
}
