import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

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
export function NativeSelect({
    id,
    name,
    defaultValue,
    children,
    required,
}: {
    id: string;
    name: string;
    defaultValue?: string;
    children: ReactNode;
    required?: boolean;
}) {
    return (
        <select
            id={id}
            name={name}
            defaultValue={defaultValue}
            required={required}
            className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
        >
            {children}
        </select>
    );
}
