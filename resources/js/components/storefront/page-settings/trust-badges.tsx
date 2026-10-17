import { Headset, LockKeyhole, RotateCcw } from 'lucide-react';
import { useT } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** Secure payment, easy returns and support, under the checkout button. */
export function TrustBadges({ className }: { className?: string }) {
    const t = useT();
    const badges = [
        { icon: LockKeyhole, label: t('Secure payment') },
        { icon: RotateCcw, label: t('Easy returns') },
        { icon: Headset, label: t('Friendly support') },
    ];

    return (
        <ul
            className={cn(
                'store-trust-badges grid grid-cols-3 gap-2 text-center text-xs text-muted-foreground',
                className,
            )}
        >
            {badges.map(({ icon: Icon, label }) => (
                <li key={label} className="grid justify-items-center gap-1">
                    <Icon className="size-4" aria-hidden />
                    {label}
                </li>
            ))}
        </ul>
    );
}
