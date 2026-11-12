import { useId } from 'react';
import { cn } from '@/lib/utils';

export type VariantOption = {
    id: number;
    name: string;
    display_type?: 'buttons' | 'circles' | 'swatches' | 'dropdown' | 'radio';
    values: { id: number; value: string; color?: string | null }[];
};

export function VariantSelector({
    option,
    value,
    onChange,
}: {
    option: VariantOption;
    value?: number;
    onChange: (value: number) => void;
}) {
    const group = useId();
    const selected = option.values.find((item) => item.id === value);
    const style = option.display_type ?? 'buttons';
    return (
        <fieldset className="grid min-w-0 gap-2">
            <legend className="mb-2 text-sm font-medium">
                {option.name}
                {selected && (
                    <span className="font-normal text-muted-foreground">
                        : {selected.value}
                    </span>
                )}
            </legend>
            {style === 'dropdown' ? (
                <select
                    aria-label={option.name}
                    value={value ?? ''}
                    onChange={(event) => onChange(Number(event.target.value))}
                    className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="" disabled>
                        Choose {option.name}
                    </option>
                    {option.values.map((item) => (
                        <option key={item.id} value={item.id}>
                            {item.value}
                        </option>
                    ))}
                </select>
            ) : (
                <div
                    className={cn(
                        'flex flex-wrap gap-3',
                        style === 'radio' && 'flex-col items-start',
                    )}
                >
                    {option.values.map((item) => {
                        const active = item.id === value;
                        const swatch = style === 'swatches';
                        const circular = style === 'circles' || swatch;
                        const color = /^#[0-9a-fA-F]{6}$/.test(item.color ?? '')
                            ? item.color!
                            : undefined;
                        return (
                            <label
                                key={item.id}
                                className="cursor-pointer"
                                title={item.value}
                                style={{
                                    display: 'inline-flex',
                                    flexDirection:
                                        style === 'radio' ? 'row' : 'column',
                                    alignItems: 'center',
                                }}
                            >
                                <input
                                    type="radio"
                                    name={group}
                                    value={item.id}
                                    checked={active}
                                    onChange={() => onChange(item.id)}
                                    className={
                                        style === 'radio'
                                            ? 'me-2 accent-current'
                                            : 'peer sr-only'
                                    }
                                    aria-label={`${option.name}: ${item.value}`}
                                />
                                {style === 'radio' ? (
                                    <span className="text-sm">
                                        {item.value}
                                    </span>
                                ) : (
                                    <span
                                        className={cn(
                                            'inline-flex items-center justify-center border text-sm transition-colors peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-primary',
                                            circular
                                                ? 'size-11 rounded-full'
                                                : 'min-h-10 min-w-12 rounded-md px-3',
                                            active
                                                ? 'border-primary ring-2 ring-primary ring-offset-2 ring-offset-background'
                                                : 'border-input hover:border-foreground',
                                            !swatch &&
                                                active &&
                                                'bg-primary text-primary-foreground',
                                            !swatch &&
                                                !active &&
                                                'bg-background',
                                        )}
                                        style={
                                            swatch && color
                                                ? { backgroundColor: color }
                                                : undefined
                                        }
                                    >
                                        {swatch ? (
                                            <span className="sr-only">
                                                {item.value}
                                            </span>
                                        ) : (
                                            <span
                                                className={cn(
                                                    circular &&
                                                        'max-w-9 truncate',
                                                )}
                                                dir="auto"
                                            >
                                                {item.value}
                                            </span>
                                        )}
                                        {swatch && !color && (
                                            <span aria-hidden="true">?</span>
                                        )}
                                    </span>
                                )}
                                {swatch && (
                                    <span className="mt-1 block max-w-16 text-center text-xs">
                                        {item.value}
                                    </span>
                                )}
                            </label>
                        );
                    })}
                </div>
            )}
        </fieldset>
    );
}
