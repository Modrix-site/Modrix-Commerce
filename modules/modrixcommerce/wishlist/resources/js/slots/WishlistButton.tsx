import { router } from '@inertiajs/react';
import { Heart, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    product_id: number;
    is_wishlisted: boolean;
    button_label: string;
    toggle_url: string;
    wishlist_url: string;
};

export default function WishlistButton({
    product_id,
    is_wishlisted,
    button_label,
    toggle_url,
}: Props) {
    const [submitting, setSubmitting] = useState(false);

    const handleToggle = () => {
        if (submitting) {
            return;
        }

        setSubmitting(true);
        router.post(
            toggle_url,
            { product_id },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <div className="my-3">
            <Button
                type="button"
                variant={is_wishlisted ? 'secondary' : 'outline'}
                size="sm"
                onClick={handleToggle}
                disabled={submitting}
                className={cn(
                    'w-full cursor-pointer transition-colors sm:w-auto',
                    is_wishlisted
                        ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300'
                        : 'hover:border-rose-300 hover:text-rose-600',
                )}
            >
                {submitting ? (
                    <Loader2 className="mr-2 size-4 animate-spin text-rose-600" />
                ) : (
                    <Heart
                        className={cn(
                            'mr-2 size-4 transition-transform',
                            is_wishlisted
                                ? 'scale-110 fill-rose-600 text-rose-600'
                                : 'text-muted-foreground',
                        )}
                    />
                )}
                {is_wishlisted ? 'Saved to Wishlist' : button_label}
            </Button>
        </div>
    );
}
