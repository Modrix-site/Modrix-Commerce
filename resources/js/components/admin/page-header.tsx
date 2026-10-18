import type { ReactNode } from 'react';

export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <div className="admin-page-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="space-y-1">
                <h1 className="text-2xl leading-8 font-semibold tracking-tight">
                    {title}
                </h1>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}

export function AdminPage({ children }: { children: ReactNode }) {
    return (
        <div className="admin-page flex w-full min-w-0 flex-1 flex-col gap-5 p-4 pt-2 md:px-6 md:pt-2 md:pb-6">
            {children}
        </div>
    );
}
