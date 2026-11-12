import { Badge } from '@/components/ui/badge';

const variants: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    enabled: 'default',
    installed: 'secondary',
    disabled: 'secondary',
    update_pending: 'outline',
    discovered: 'outline',
    failed: 'destructive',
    invalid: 'destructive',
};

/** Same pill colours as order and product statuses (see .status-badge). */
const tones: Record<string, string> = {
    enabled: 'success',
    update_pending: 'progress',
    failed: 'danger',
    invalid: 'danger',
};

export function ExtensionStateBadge({
    state,
    label,
}: {
    state: string;
    label: string;
}) {
    return (
        <Badge
            className="status-badge"
            data-tone={tones[state] ?? 'neutral'}
            variant={variants[state] ?? 'outline'}
        >
            {label}
        </Badge>
    );
}
