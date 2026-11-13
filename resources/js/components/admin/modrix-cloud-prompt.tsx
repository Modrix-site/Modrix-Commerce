import { Link } from '@inertiajs/react';
import { Cloud } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type ModrixCloudPromptData = {
    state: 'disabled' | 'disconnected';
    url: string;
};

/**
 * Invites the store owner to connect to Modrix Cloud, where every theme and
 * module, free and paid, is listed. The server sends nothing once connected.
 */
export function ModrixCloudPrompt({
    prompt,
    className,
}: {
    prompt: ModrixCloudPromptData | null | undefined;
    className?: string;
}) {
    if (!prompt) {
        return null;
    }

    const disabled = prompt.state === 'disabled';

    return (
        <section
            aria-labelledby="modrix-cloud-prompt-title"
            className={cn(
                'flex flex-col gap-4 rounded-xl border bg-card p-5 sm:flex-row sm:items-center',
                className,
            )}
        >
            <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Cloud className="size-5" aria-hidden />
            </span>
            <div className="grid flex-1 gap-1">
                <h2
                    id="modrix-cloud-prompt-title"
                    className="text-base font-semibold"
                >
                    See every theme and module on Modrix Cloud
                </h2>
                <p className="text-sm text-muted-foreground">
                    {disabled
                        ? 'Enable the Modrix Cloud Connector, then connect your store to browse the full catalogue of themes and modules, free and paid.'
                        : 'Connect your store to Modrix Cloud to browse the full catalogue of themes and modules, free and paid. Install free ones in one click, buy premium ones, and get license keys and updates handled for you.'}
                </p>
            </div>
            <Button asChild className="shrink-0">
                <Link href={prompt.url}>
                    {disabled
                        ? 'Enable Modrix Cloud Connector'
                        : 'Connect to Modrix Cloud'}
                </Link>
            </Button>
        </section>
    );
}
