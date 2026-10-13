import { Check } from 'lucide-react';
import { formatDate } from '@/components/storefront/order-view';
import { cn } from '@/lib/utils';
import type { OrderStep } from './types';

/**
 * Placed → Paid → Packed → Shipped → Delivered. The first step not done
 * is the current one.
 */
export function OrderStepper({ steps }: { steps: OrderStep[] }) {
    const current = steps.findIndex((s) => !s.done);

    return (
        <ol className="grid grid-cols-5 gap-1" aria-label="Order progress">
            {steps.map((step, i) => {
                const isCurrent = i === current;

                return (
                    <li
                        key={step.key}
                        className="grid justify-items-center gap-1.5 text-center"
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
                                    'flex size-7 shrink-0 items-center justify-center rounded-full border-2 text-xs font-medium',
                                    step.done &&
                                        'border-primary bg-primary text-primary-foreground',
                                    isCurrent && 'border-primary text-primary',
                                    !step.done &&
                                        !isCurrent &&
                                        'border-border text-muted-foreground',
                                )}
                            >
                                {step.done ? (
                                    <Check className="size-4" aria-hidden />
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
                                'text-xs font-medium sm:text-sm',
                                !step.done &&
                                    !isCurrent &&
                                    'text-muted-foreground',
                            )}
                        >
                            {step.label}
                            <span className="sr-only">
                                {step.done
                                    ? ' (done)'
                                    : isCurrent
                                      ? ' (next)'
                                      : ''}
                            </span>
                        </span>
                        {step.at && (
                            <span className="hidden text-xs text-muted-foreground sm:block">
                                {formatDate(step.at)}
                            </span>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
