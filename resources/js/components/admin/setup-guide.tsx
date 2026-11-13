import { Link, router } from '@inertiajs/react';
import { CheckCircle2, Circle, CircleSlash } from 'lucide-react';
import SetupGuideController from '@/actions/App/Http/Controllers/Admin/SetupGuideController';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type SetupGuideStep = {
    key: string;
    title: string;
    description: string;
    action: string;
    url: string;
    done: boolean;
    skipped: boolean;
    optional: boolean;
};

export type SetupGuideData = {
    steps: SetupGuideStep[];
    completed: number;
    total: number;
    hidden: boolean;
};

const options = { preserveScroll: true, preserveState: true } as const;

export function setSetupGuideHidden(hidden: boolean) {
    router.put(SetupGuideController.visibility.url(), { hidden }, options);
}

/**
 * First-use checklist on the dashboard: store details, theme, Modrix Cloud,
 * modules, products, payments, shipping, taxes and email. Steps tick
 * themselves off from the store's state; optional ones can be skipped.
 */
export function SetupGuide({ guide }: { guide: SetupGuideData | null }) {
    if (!guide || guide.hidden || guide.total === 0) {
        return null;
    }

    const finished = guide.completed >= guide.total;
    const next = guide.steps.find((step) => !step.done && !step.skipped);
    const percent = Math.round((guide.completed / guide.total) * 100);

    return (
        <section
            aria-labelledby="setup-guide-title"
            className="rounded-xl border bg-card"
        >
            <div className="flex flex-wrap items-start justify-between gap-3 border-b p-5">
                <div className="grid gap-1">
                    <h2
                        id="setup-guide-title"
                        className="text-base font-semibold"
                    >
                        {finished
                            ? 'Your store is set up'
                            : 'Set up your store'}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {finished
                            ? 'Every step is done. You can hide this guide and show it again from the dashboard.'
                            : 'Follow these steps to get your store ready for customers. They tick themselves off as you go.'}
                    </p>
                </div>
                <Button
                    variant={finished ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => setSetupGuideHidden(true)}
                >
                    Hide guide
                </Button>
                <div className="grid w-full gap-1.5">
                    <div className="flex justify-between text-xs text-muted-foreground">
                        <span>
                            {guide.completed} of {guide.total} steps done
                        </span>
                        <span>{percent}%</span>
                    </div>
                    <div
                        role="progressbar"
                        aria-label="Setup progress"
                        aria-valuemin={0}
                        aria-valuemax={guide.total}
                        aria-valuenow={guide.completed}
                        className="h-2 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            className="h-full rounded-full bg-primary transition-[width]"
                            style={{ width: `${percent}%` }}
                        />
                    </div>
                </div>
            </div>
            <ol className="divide-y">
                {guide.steps.map((step) => {
                    const isNext = step.key === next?.key;
                    const Icon = step.done
                        ? CheckCircle2
                        : step.skipped
                          ? CircleSlash
                          : Circle;

                    return (
                        <li
                            key={step.key}
                            className={cn(
                                'flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:px-5',
                                isNext && 'bg-muted/50',
                            )}
                        >
                            <Icon
                                aria-hidden
                                className={cn(
                                    'size-5 shrink-0',
                                    step.done
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-muted-foreground',
                                )}
                            />
                            <div className="grid flex-1 gap-0.5">
                                <p
                                    className={cn(
                                        'text-sm font-medium',
                                        (step.done || step.skipped) &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {step.title}
                                    <span className="sr-only">
                                        {step.done
                                            ? ' (done)'
                                            : step.skipped
                                              ? ' (skipped)'
                                              : ''}
                                    </span>
                                    {step.optional && !step.done && (
                                        <span className="ml-2 text-xs font-normal text-muted-foreground">
                                            Optional
                                        </span>
                                    )}
                                </p>
                                {!step.done && !step.skipped && (
                                    <p className="text-sm text-muted-foreground">
                                        {step.description}
                                    </p>
                                )}
                            </div>
                            <div className="flex shrink-0 gap-2">
                                {step.skipped ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            router.put(
                                                SetupGuideController.skip.url(
                                                    step.key,
                                                ),
                                                { skipped: false },
                                                options,
                                            )
                                        }
                                    >
                                        Undo skip
                                    </Button>
                                ) : (
                                    !step.done && (
                                        <>
                                            {step.optional && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.put(
                                                            SetupGuideController.skip.url(
                                                                step.key,
                                                            ),
                                                            { skipped: true },
                                                            options,
                                                        )
                                                    }
                                                >
                                                    Skip
                                                </Button>
                                            )}
                                            <Button
                                                size="sm"
                                                variant={
                                                    isNext
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                asChild
                                            >
                                                <Link href={step.url}>
                                                    {step.action}
                                                </Link>
                                            </Button>
                                        </>
                                    )
                                )}
                            </div>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}
