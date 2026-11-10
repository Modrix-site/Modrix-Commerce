import { Check, Mail, Send } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    submitUrl: string;
};

export default function SaveCartWidget({ submitUrl }: Props) {
    const [email, setEmail] = useState('');
    const [loading, setLoading] = useState(false);
    const [successMessage, setSuccessMessage] = useState('');
    const [errorMessage, setErrorMessage] = useState('');

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        if (!email || !email.includes('@')) {
            setErrorMessage('Please provide a valid email address.');
            return;
        }

        setLoading(true);
        setErrorMessage('');
        setSuccessMessage('');

        try {
            const csrfToken =
                (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ||
                '';

            const response = await fetch(submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ email }),
            });

            const data = await response.json();

            if (response.ok && data.success) {
                setSuccessMessage(data.message || 'Cart link sent to your email!');
                setEmail('');
            } else {
                setErrorMessage(data.message || 'Failed to save cart. Please try again.');
            }
        } catch {
            setErrorMessage('Connection error. Please try again.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="rounded-xl border bg-card p-4 shadow-sm text-card-foreground">
            <div className="flex items-start gap-3">
                <div className="rounded-full bg-primary/10 p-2 text-primary shrink-0 mt-0.5">
                    <Mail className="size-4" />
                </div>
                <div className="space-y-1">
                    <h3 className="text-sm font-semibold text-foreground">
                        Save Your Cart For Later
                    </h3>
                    <p className="text-xs text-muted-foreground leading-relaxed">
                        Need time to decide? Enter your email and we'll send you a link to pick up your order right where you left off.
                    </p>
                </div>
            </div>

            {successMessage ? (
                <div className="mt-3 flex items-center gap-2 rounded-lg bg-emerald-500/15 p-2.5 text-xs font-medium text-emerald-800 dark:text-emerald-300">
                    <Check className="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <span>{successMessage}</span>
                </div>
            ) : (
                <form onSubmit={handleSubmit} className="mt-3 flex gap-2">
                    <Input
                        type="email"
                        required
                        placeholder="your.email@example.com"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        className="h-9 text-xs"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        disabled={loading}
                        className="h-9 px-3 text-xs font-semibold shrink-0"
                    >
                        {loading ? 'Sending...' : (
                            <>
                                <Send className="size-3 mr-1.5" /> Send Cart
                            </>
                        )}
                    </Button>
                </form>
            )}

            {errorMessage && (
                <p className="mt-2 text-xs text-destructive font-medium">
                    {errorMessage}
                </p>
            )}
        </div>
    );
}
