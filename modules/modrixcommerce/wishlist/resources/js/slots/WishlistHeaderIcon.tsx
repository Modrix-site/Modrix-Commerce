import { Link } from '@inertiajs/react';
import { Heart } from 'lucide-react';

type Props = {
    count: number;
    wishlist_url: string;
};

export default function WishlistHeaderIcon({ count, wishlist_url }: Props) {
    return (
        <Link
            href={wishlist_url}
            className="relative inline-flex items-center justify-center rounded-md p-2 text-foreground/80 transition-colors hover:text-foreground"
            aria-label={`Wishlist, ${count} items`}
        >
            <Heart className="size-5" />
            {count > 0 && (
                <span className="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-rose-600 text-[10px] font-bold text-white">
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </Link>
    );
}
