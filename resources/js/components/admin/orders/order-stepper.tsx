import { Check, Minus } from 'lucide-react';
import { formatDate } from '@/components/storefront/order-view';
import { cn } from '@/lib/utils';
import type { OrderStep } from './types';

/**
 * The order's path from placed to delivered. Skipped steps are shown as
 * passed but without a date; the first step not done is the current one.
 */
export function OrderStepper({ steps }: { steps: OrderStep[] }) {
    const current = steps.findIndex((s) => !s.done);

    return (
        <div className="-mx-2 overflow-x-auto px-2 pb-1">
            <ol
                className="grid min-w-[44rem] gap-1"
                style={{
                    gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))`,
                }}
                aria-label="Order progress"
            >
                {steps.map((step, i) => {
                    const isCurrent = i === current;

                    return (
                        <li
                            key={step.key}
                            className="grid content-start justify-items-center gap-1.5 text-center"
                            aria-current={isCurrent ? 'step' : undefined}
                        >
                            <div className="flex w-full items-center">
                                <span
                                    className={cn(
                                        'h-0.5 flex-1',
                                        i === 0
                                            ? 'bg-transparent'
                                            : step.done
                                              ? 'bg-primary'
                                              : 'bg-border',
                                    )}
                                />
                                <span
                                    className={cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full border-2 text-[0.65rem] font-medium',
                                        step.done &&
                                            !step.skipped &&
                                            'border-primary bg-primary text-primary-foreground',
                                        step.skipped &&
                                            'border-primary/40 bg-primary/10 text-primary/60',
                                        isCurrent &&
                                            'border-primary text-primary',
                                        !step.done &&
                                            !isCurrent &&
                                            'border-border text-muted-foreground',
                                    )}
                                >
                                    {step.skipped ? (
                                        <Minus className="size-3" aria-hidden />
                                    ) : step.done ? (
                                        <Check
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                    ) : (
                                        i + 1
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'h-0.5 flex-1',
                                        i === steps.length - 1
                                            ? 'bg-transparent'
                                            : steps[i + 1]?.done
                                              ? 'bg-primary'
                                              : 'bg-border',
                                    )}
                                />
                            </div>
                            <span
                                className={cn(
                                    'text-[0.7rem] leading-tight font-medium',
                                    (step.skipped ||
                                        (!step.done && !isCurrent)) &&
                                        'text-muted-foreground',
                                )}
                            >
                                {step.label}
                                <span className="sr-only">
                                    {step.skipped
                                        ? ' (skipped)'
                                        : step.done
                                          ? ' (done)'
                                          : isCurrent
                                            ? ' (next)'
                                            : ''}
                                </span>
                            </span>
                            {step.at && (
                                <span className="text-[0.65rem] leading-tight text-muted-foreground">
                                    {formatDate(step.at)}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
