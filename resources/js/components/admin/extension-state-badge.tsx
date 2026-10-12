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

export function ExtensionStateBadge({
    state,
    label,
}: {
    state: string;
    label: string;
}) {
    return <Badge variant={variants[state] ?? 'outline'}>{label}</Badge>;
}
