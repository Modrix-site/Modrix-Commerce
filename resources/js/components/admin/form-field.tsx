import type { ComponentProps, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export function FormField({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

/** Native select styled to match the input component (accessible and keyboard friendly). */
/**
 * Styled native select. Works uncontrolled (name + defaultValue, for forms)
 * or controlled (value + onChange).
 */
export function NativeSelect({
    className,
    children,
    ...props
}: ComponentProps<'select'>) {
    return (
        <select
            {...props}
            className={cn(
                'h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30',
                className,
            )}
        >
            {children}
        </select>
    );
}
