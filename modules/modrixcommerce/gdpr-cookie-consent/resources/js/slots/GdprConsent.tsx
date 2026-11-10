import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Button } from '@/components/ui/button';

type Props = {
    title: string;
    body: string;
    privacyUrl: string | null;
    acceptLabel: string;
    rejectLabel: string;
    customizeLabel: string;
    allowCustomize: boolean;
    essentialText: string;
    analyticsText: string;
    marketingText: string;
    expiryDays: number;
    page: string;
    storeUrl: string;
};

const COOKIE_NAME = 'gdpr_consent';

const readCookie = (): string | null => {
    const match = document.cookie
        .split('; ')
        .find((part) => part.startsWith(`${COOKIE_NAME}=`));
    if (!match) {
        return null;
    }
    try {
        return decodeURIComponent(match.slice(COOKIE_NAME.length + 1));
    } catch {
        return null;
    }
};

const writeCookie = (token: string, expiryDays: number) => {
    const date = new Date();
    date.setTime(date.getTime() + expiryDays * 24 * 60 * 60 * 1000);
    document.cookie = `${COOKIE_NAME}=${encodeURIComponent(token)}; expires=${date.toUTCString()}; path=/; SameSite=Lax`;
};

const randomToken = (): string => {
    const bytes = new Uint8Array(20);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
};

type Decision = 'accept' | 'reject' | 'customize';

/**
 * A GDPR cookie-consent banner. Shown only when the server decided the visitor
 * is in scope (country + audience settings); the choice is posted back and
 * remembered in a cookie so it is not asked again until it expires.
 */
export default function GdprConsent({
    title,
    body,
    privacyUrl,
    acceptLabel,
    rejectLabel,
    customizeLabel,
    allowCustomize,
    essentialText,
    analyticsText,
    marketingText,
    expiryDays,
    page,
    storeUrl,
}: Props) {
    const [visible, setVisible] = useState(() => readCookie() === null);
    const [customizing, setCustomizing] = useState(false);
    const [analytics, setAnalytics] = useState(false);
    const [marketing, setMarketing] = useState(false);

    const submit = (decision: Decision, extra?: { analytics: boolean; marketing: boolean }) => {
        const token = randomToken();
        const form = new FormData();
        form.set('decision', decision);
        form.set('token', token);
        form.set('page', page);
        if (extra) {
            form.set('analytics', extra.analytics ? '1' : '0');
            form.set('marketing', extra.marketing ? '1' : '0');
        }

        fetch(storeUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            body: form,
        })
            .then((response) => (response.ok ? response.json() : null))
            .catch(() => null)
            .finally(() => {
                writeCookie(token, expiryDays);
                setVisible(false);
                setCustomizing(false);
            });
    };

    if (!visible) {
        return null;
    }

    return (
        <div className="fixed inset-x-0 bottom-0 z-50 p-4 sm:p-6">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="gdpr-consent-title"
                className="mx-auto flex w-full max-w-3xl flex-col gap-4 rounded-[var(--radius)] border bg-background p-6 text-foreground shadow-xl"
            >
                {customizing ? (
                    <>
                        <h2 id="gdpr-consent-title" className="text-lg font-semibold tracking-tight">
                            {title}
                        </h2>
                        <div className="grid gap-3 text-sm">
                            <label className="flex items-start gap-2 text-muted-foreground">
                                <Checkbox checked disabled />
                                <span>{essentialText || 'Essential — always on.'}</span>
                            </label>
                            <label className="flex items-start gap-2">
                                <Checkbox
                                    checked={analytics}
                                    onCheckedChange={(v) => setAnalytics(v === true)}
                                />
                                <span>{analyticsText || 'Analytics'}</span>
                            </label>
                            <label className="flex items-start gap-2">
                                <Checkbox
                                    checked={marketing}
                                    onCheckedChange={(v) => setMarketing(v === true)}
                                />
                                <span>{marketingText || 'Marketing'}</span>
                            </label>
                        </div>
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button variant="outline" onClick={() => setCustomizing(false)}>
                                Back
                            </Button>
                            <Button onClick={() => submit('customize', { analytics, marketing })}>
                                {customizeLabel || 'Save choices'}
                            </Button>
                        </div>
                    </>
                ) : (
                    <>
                        <h2 id="gdpr-consent-title" className="text-lg font-semibold tracking-tight">
                            {title}
                        </h2>
                        {body && <p className="text-sm text-muted-foreground">{body}</p>}
                        {privacyUrl && (
                            <a
                                href={privacyUrl}
                                className="text-sm font-medium underline underline-offset-2 text-foreground/80 hover:text-foreground"
                            >
                                Privacy policy
                            </a>
                        )}
                        <div className="flex flex-wrap justify-end gap-2">
                            {allowCustomize && (
                                <Button variant="outline" onClick={() => setCustomizing(true)}>
                                    {customizeLabel || 'Customize'}
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                onClick={() => submit('reject')}
                            >
                                {rejectLabel || 'Reject'}
                            </Button>
                            <Button onClick={() => submit('accept')}>{acceptLabel || 'Accept all'}</Button>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
