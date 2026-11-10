import { usePage } from '@inertiajs/react';
import { Check, Copy, ShoppingBag, Tag, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    headline: string;
    discountCode: string | null;
    captureUrl: string;
};

export default function ExitIntentModal({
    headline,
    discountCode,
    captureUrl,
}: Props) {
    const { storefront } = usePage().props as {
        storefront?: { cartCount?: number };
    };

    const cartCount = storefront?.cartCount ?? 0;

    const [open, setOpen] = useState(false);
    const [email, setEmail] = useState('');
    const [name, setName] = useState('');
    const [loading, setLoading] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [copied, setCopied] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');

    useEffect(() => {
        // Only trigger if cart has items
        if (cartCount <= 0) return;

        // Check if already shown in this session
        if (sessionStorage.getItem('cart_exit_intent_shown') === 'true') {
            return;
        }

        const handleMouseLeave = (e: MouseEvent) => {
            // Trigger when cursor leaves through the top of viewport
            if (e.clientY <= 10) {
                if (sessionStorage.getItem('cart_exit_intent_shown') !== 'true') {
                    setOpen(true);
                    sessionStorage.setItem('cart_exit_intent_shown', 'true');
                }
            }
        };

        document.addEventListener('mouseleave', handleMouseLeave);
        return () => {
            document.removeEventListener('mouseleave', handleMouseLeave);
        };
    }, [cartCount]);

    function handleClose() {
        setOpen(false);
    }

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!email || !email.includes('@')) {
            setErrorMessage('Please enter a valid email address.');
            return;
        }

        setLoading(true);
        setErrorMessage('');

        try {
            const csrfToken =
                (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ||
                '';

            const response = await fetch(captureUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ email, name }),
            });

            if (response.ok) {
                setSubmitted(true);
            } else {
                const data = await response.json();
                setErrorMessage(data.message || 'Unable to save cart. Please try again.');
            }
        } catch {
            setErrorMessage('Connection error. Please try again.');
        } finally {
            setLoading(false);
        }
    }

    function copyCode() {
        if (!discountCode) return;
        navigator.clipboard.writeText(discountCode);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
            <div
                className="relative w-full max-w-md overflow-hidden rounded-xl border bg-background p-6 shadow-2xl animate-in zoom-in-95 duration-200 text-foreground"
                role="dialog"
                aria-modal="true"
                aria-labelledby="exit-modal-title"
            >
                <button
                    onClick={handleClose}
                    className="absolute right-4 top-4 rounded-full p-1 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                    aria-label="Close"
                >
                    <X className="size-5" />
                </button>

                <div className="text-center">
                    <div className="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <ShoppingBag className="size-6" />
                    </div>

                    <h2 id="exit-modal-title" className="text-xl font-bold tracking-tight">
                        {headline}
                    </h2>

                    <p className="mt-2 text-sm text-muted-foreground">
                        You have {cartCount} {cartCount === 1 ? 'item' : 'items'} saved in your cart. Enter your email and we'll save your items so you won't lose them!
                    </p>

                    {discountCode && (
                        <div className="mt-3.5 flex items-center justify-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 dark:bg-amber-950/30 p-2.5 text-xs text-amber-900 dark:text-amber-200">
                            <Tag className="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
                            <span>
                                Use coupon code <strong className="font-mono font-bold text-amber-800 dark:text-amber-300">{discountCode}</strong> for an extra discount!
                            </span>
                            <button
                                type="button"
                                onClick={copyCode}
                                className="ml-1 rounded px-1.5 py-0.5 text-[11px] font-semibold bg-amber-200/80 hover:bg-amber-300 dark:bg-amber-800/80 transition-colors inline-flex items-center gap-1"
                            >
                                {copied ? <Check className="size-3" /> : <Copy className="size-3" />}
                                {copied ? 'Copied' : 'Copy'}
                            </button>
                        </div>
                    )}
                </div>

                {!submitted ? (
                    <form onSubmit={handleSubmit} className="mt-5 space-y-3">
                        <div>
                            <Input
                                type="text"
                                placeholder="Your name (optional)"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                className="h-10 text-sm"
                            />
                        </div>
                        <div>
                            <Input
                                type="email"
                                required
                                placeholder="Enter your email address"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                className="h-10 text-sm"
                            />
                        </div>

                        {errorMessage && (
                            <p className="text-xs text-destructive font-medium">
                                {errorMessage}
                            </p>
                        )}

                        <Button
                            type="submit"
                            disabled={loading}
                            className="w-full h-10 font-semibold"
                        >
                            {loading ? 'Saving your cart...' : 'Save My Cart & Send Link'}
                        </Button>

                        <button
                            type="button"
                            onClick={handleClose}
                            className="w-full text-center text-xs text-muted-foreground hover:underline mt-2"
                        >
                            No thanks, I'll continue browsing
                        </button>
                    </form>
                ) : (
                    <div className="mt-5 text-center space-y-3">
                        <div className="rounded-lg bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300 text-sm">
                            <p className="font-semibold flex items-center justify-center gap-1.5">
                                <Check className="size-4" /> Cart Saved Successfully!
                            </p>
                            <p className="mt-1 text-xs opacity-90">
                                We've recorded your cart. A recovery link has been sent to your email.
                            </p>
                        </div>

                        <div className="pt-2 flex flex-col gap-2">
                            <Button asChild className="w-full">
                                <a href="/checkout">Proceed to Checkout</a>
                            </Button>
                            <Button variant="outline" onClick={handleClose} className="w-full">
                                Continue Shopping
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
