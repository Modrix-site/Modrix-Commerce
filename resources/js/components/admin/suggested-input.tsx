import { useState } from 'react';
import { NativeSelect } from '@/components/admin/form-field';
import { Input } from '@/components/ui/input';

const CUSTOM = '\u0000custom';

/**
 * A dropdown of suggested values with a "Custom…" choice that reveals a text
 * field, for settings like model IDs where any exact value is still allowed.
 * With `name`, the value is also submitted from a hidden input.
 */
export function SuggestedInput({
    id,
    name,
    value,
    onValueChange,
    suggestions,
    customLabel = 'Custom…',
    required,
    maxLength,
}: {
    id: string;
    name?: string;
    value: string;
    onValueChange: (value: string) => void;
    suggestions: string[];
    customLabel?: string;
    required?: boolean;
    maxLength?: number;
}) {
    const [custom, setCustom] = useState(
        value !== '' && !suggestions.includes(value),
    );
    const [picked, setPicked] = useState(false);
    const isCustom = custom || (value !== '' && !suggestions.includes(value));

    return (
        <div className="grid gap-2">
            <NativeSelect
                id={id}
                value={isCustom ? CUSTOM : value}
                required={required && !isCustom}
                onChange={(event) => {
                    if (event.target.value === CUSTOM) {
                        setCustom(true);
                        setPicked(true);

                        return;
                    }

                    setCustom(false);
                    onValueChange(event.target.value);
                }}
            >
                {!required && <option value="">—</option>}
                {suggestions.map((suggestion) => (
                    <option key={suggestion} value={suggestion}>
                        {suggestion}
                    </option>
                ))}
                <option value={CUSTOM}>{customLabel}</option>
            </NativeSelect>
            {isCustom && (
                <Input
                    id={`${id}-custom`}
                    aria-label={customLabel.replace(/…$/, '')}
                    value={value}
                    onChange={(event) => onValueChange(event.target.value)}
                    required={required}
                    maxLength={maxLength}
                    autoFocus={picked}
                />
            )}
            {name && <input type="hidden" name={name} value={value} />}
        </div>
    );
}
