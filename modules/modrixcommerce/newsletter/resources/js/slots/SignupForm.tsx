import { Form } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useId } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

type Props = {
    heading: string;
    consentText: string;
    submitUrl: string;
    source: 'home' | 'order' | 'footer';
};

export default function SignupForm({
    heading,
    consentText,
    submitUrl,
    source,
}: Props) {
    // Unique even when the form shows twice on a page (home and footer).
    const headingId = useId();

    return (
        <section
            className="my-10 rounded-[var(--radius)] border bg-muted p-6"
            aria-labelledby={headingId}
        >
            <h2
                id={headingId}
                className="mb-3 flex items-center gap-2 text-lg font-semibold"
            >
                <Mail className="size-5" aria-hidden /> {heading}
            </h2>
            <Form
                action={submitUrl}
                method="post"
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="grid gap-3 sm:max-w-xl"
            >
                {({ processing, errors, wasSuccessful }) => (
                    <>
                        <input type="hidden" name="source" value={source} />
                        <div className="flex flex-wrap gap-2">
                            <Input
                                name="email"
                                type="email"
                                required
                                autoComplete="email"
                                aria-label="Email address"
                                placeholder="you@example.com"
                                className="min-w-0 flex-1 bg-background"
                            />
                            <Button type="submit" disabled={processing}>
                                Subscribe
                            </Button>
                        </div>
                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox name="consent" value="1" />
                            <span>{consentText}</span>
                        </label>
                        <InputError message={errors.email ?? errors.consent} />
                        {wasSuccessful && (
                            <p className="text-sm">
                                Thank you for subscribing.
                            </p>
                        )}
                    </>
                )}
            </Form>
        </section>
    );
}
