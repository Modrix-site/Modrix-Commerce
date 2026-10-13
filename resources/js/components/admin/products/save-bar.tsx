import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const LEAVE_MESSAGE =
    'You have unsaved changes. Leave this page and lose them?';

/**
 * Warns before leaving the page (closing the tab or following a link) while
 * the form has unsaved changes. Saving (a non-GET visit) is never blocked.
 */
export function useUnsavedChangesGuard(dirty: boolean): void {
    useEffect(() => {
        if (!dirty) {
            return;
        }

        const onBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
        };
        window.addEventListener('beforeunload', onBeforeUnload);

        const removeRouterGuard = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (visit.method === 'get' && !window.confirm(LEAVE_MESSAGE)) {
                event.preventDefault();
            }
        });

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            removeRouterGuard();
        };
    }, [dirty]);
}

/**
 * Sticky footer with the save button; shows when there are unsaved changes.
 */
export function SaveBar({
    dirty,
    processing,
    isNew,
    onDiscard,
    errorCount,
}: {
    dirty: boolean;
    processing: boolean;
    isNew: boolean;
    onDiscard: () => void;
    errorCount: number;
}) {
    return (
        <div
            className={cn(
                'sticky bottom-0 z-20 -mx-4 mt-2 flex flex-wrap items-center gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:-mx-6 md:px-6',
            )}
        >
            <span className="text-sm" aria-live="polite">
                {errorCount > 0 ? (
                    <span className="font-medium text-destructive">
                        {errorCount === 1
                            ? '1 field needs attention'
                            : `${errorCount} fields need attention`}
                    </span>
                ) : dirty ? (
                    <span className="font-medium">Unsaved changes</span>
                ) : (
                    <span className="text-muted-foreground">
                        {isNew ? 'New product' : 'All changes saved'}
                    </span>
                )}
            </span>
            <div className="ml-auto flex gap-2">
                {dirty && !isNew && (
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={onDiscard}
                        disabled={processing}
                    >
                        Discard
                    </Button>
                )}
                <Button type="submit" disabled={processing}>
                    {processing
                        ? 'Saving…'
                        : isNew
                          ? 'Create product'
                          : 'Save product'}
                </Button>
            </div>
        </div>
    );
}
