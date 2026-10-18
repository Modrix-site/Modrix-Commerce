import { Form } from '@inertiajs/react';
import { Check, Copy, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

type Props = {
    headline: string;
    body: string;
    ctaLabel: string;
    mode: 'email' | 'coupon' | 'both';
    couponCode: string | null;
    couponNote: string;
    consentText: string;
    delaySeconds: number;
    frequency: 'session' | 'once' | 'always';
    page: string;
    submitUrl: string;
};

const SEEN_KEY = 'promo-popup.seen';

const isSuppressed = (frequency: Props['frequency']): boolean =>
    frequency === 'once'
        ? window.localStorage.getItem(SEEN_KEY) === '1'
        : frequency === 'session' &&
          window.sessionStorage.getItem(SEEN_KEY) === '1';

const remember = (frequency: Props['frequency']) => {
    if (frequency === 'once') {
        window.localStorage.setItem(SEEN_KEY, '1');
    } else if (frequency === 'session') {
        window.sessionStorage.setItem(SEEN_KEY, '1');
    }
};

/**
 * A dismissible marketing pop-up: an offer headline with a e-mail sign-up and
 * a coupon code. Rendered on every storefront page from the global
 * "storefront.overlay" slot; how often it appears is stored in the browser.
 */
export default function PromoPopup({
    headline,
    body,
    ctaLabel,
    mode,
    couponCode,
    couponNote,
    consentText,
    delaySeconds,
    frequency,
    page,
    submitUrl,
}: Props) {
    const [open, setOpen] = useState(false);
    const [revealed, setRevealed] = useState(mode === 'coupon');
    const [copied, setCopied] = useState(false);
    const rtl =
        typeof document !== 'undefined' &&
        document.documentElement.dir === 'rtl';

    const close = useCallback(() => {
        remember(frequency);
        setOpen(false);
    }, [frequency]);

    useEffect(() => {
        if (isSuppressed(frequency)) {
            return;
        }

        const timer = window.setTimeout(
            () => setOpen(true),
            delaySeconds * 1000,
        );

        return () => window.clearTimeout(timer);
    }, [delaySeconds, frequency]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close();
            }
        };
        const overflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', onKey);

        return () => {
            document.body.style.overflow = overflow;
            document.removeEventListener('keydown', onKey);
        };
    }, [open, close]);

    const copy = async () => {
        if (!couponCode) {
            return;
        }

        try {
            await navigator.clipboard.writeText(couponCode);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    };

    if (!open) {
        return null;
    }

    const showsCoupon = revealed || mode === 'coupon';

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-6"
            dir={rtl ? 'rtl' : undefined}
            onClick={(event) => {
                if (event.target === event.currentTarget) {
                    close();
                }
            }}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="promo-popup-title"
                className="relative flex w-full max-w-md flex-col gap-4 rounded-t-[var(--radius)] border bg-background p-6 text-foreground shadow-xl sm:rounded-[var(--radius)]"
            >
                <button
                    type="button"
                    onClick={close}
                    aria-label="Close"
                    className="absolute end-3 top-3 rounded p-1 text-muted-foreground hover:text-foreground"
                >
                    <X className="size-4" />
                </button>

                <div className="grid gap-2 pe-6">
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Offer
                    </span>
                    <h2
                        id="promo-popup-title"
                        className="text-xl font-semibold tracking-tight"
                    >
                        {headline}
                    </h2>
                    {body && (
                        <p className="text-sm text-muted-foreground">{body}</p>
                    )}
                </div>

                {showsCoupon ? (
                    <Coupon
                        code={couponCode}
                        note={couponNote}
                        copied={copied}
                        onCopy={copy}
                    />
                ) : (
                    <Form
                        action={submitUrl}
                        method="post"
                        options={{ preserveScroll: true }}
                        onSuccess={() => setRevealed(true)}
                        className="grid gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input type="hidden" name="page" value={page} />
                                <Input
                                    name="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    aria-label="Email address"
                                    placeholder="you@example.com"
                                />
                                <label className="flex items-start gap-2 text-xs text-muted-foreground">
                                    <Checkbox name="consent" value="1" />
                                    <span>{consentText}</span>
                                </label>
                                <InputError
                                    message={errors.email ?? errors.consent}
                                />
                                <Button type="submit" disabled={processing}>
                                    {ctaLabel}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </div>
    );
}

function Coupon({
    code,
    note,
    copied,
    onCopy,
}: {
    code: string | null;
    note: string;
    copied: boolean;
    onCopy: () => void;
}) {
    if (!code) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <button
                type="button"
                onClick={onCopy}
                className="flex items-center justify-between gap-3 rounded-[var(--radius)] border border-dashed px-4 py-3 text-start hover:bg-muted"
            >
                <span className="font-mono text-lg font-semibold tracking-wider">
                    {code}
                </span>
                <span className="flex items-center gap-1 text-xs text-muted-foreground">
                    {copied ? (
                        <>
                            <Check className="size-4" /> Copied
                        </>
                    ) : (
                        <>
                            <Copy className="size-4" /> Copy
                        </>
                    )}
                </span>
            </button>
            {note && <p className="text-xs text-muted-foreground">{note}</p>}
        </div>
    );
}
