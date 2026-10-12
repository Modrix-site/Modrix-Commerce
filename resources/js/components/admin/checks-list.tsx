import { CircleCheck, CircleX } from 'lucide-react';
import type { RequirementCheck } from '@/types';

export function ChecksList({ checks }: { checks: RequirementCheck[] }) {
    return (
        <ul className="divide-y text-sm">
            {checks.map((check) => (
                <li
                    key={check.key}
                    className="flex items-start justify-between gap-4 py-2"
                >
                    <span className="flex items-center gap-2">
                        {check.passed ? (
                            <CircleCheck
                                className="size-4 text-green-600"
                                aria-label="Passed"
                            />
                        ) : (
                            <CircleX
                                className="size-4 text-destructive"
                                aria-label="Failed"
                            />
                        )}
                        {check.label}
                    </span>
                    <span className="text-right text-muted-foreground">
                        {check.detail}
                    </span>
                </li>
            ))}
        </ul>
    );
}
