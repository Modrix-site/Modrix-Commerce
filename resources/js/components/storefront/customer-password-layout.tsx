import type { ReactNode } from 'react';
import { KeyRound, ArrowLeft } from 'lucide-react';
import TextLink from '@/components/text-link';
import { useT } from '@/lib/i18n';

export function CustomerPasswordLayout({
    title,
    description,
    children,
    showBackLink = false,
}: {
    title: string;
    description: string;
    children: ReactNode;
    showBackLink?: boolean;
}) {
    const t = useT();
    return (
        <section
            className="mx-auto w-full max-w-md py-6 sm:py-12"
            aria-label={title}
        >
            <div className="rounded-xl border bg-card p-6 text-card-foreground shadow-xs sm:p-8">
                <div className="mb-6 grid justify-items-center gap-3 text-center">
                    <span
                        className="flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                        aria-hidden="true"
                    >
                        <KeyRound size={24} />
                    </span>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    <p className="text-sm leading-relaxed text-muted-foreground">
                        {description}
                    </p>
                </div>
                {children}
                {showBackLink && (
                    <div className="mt-6 text-center">
                        <TextLink
                            href="/login"
                            className="inline-flex items-center gap-2 text-sm"
                        >
                            <ArrowLeft size={15} />
                            {t('Back to sign in')}
                        </TextLink>
                    </div>
                )}
            </div>
        </section>
    );
}
