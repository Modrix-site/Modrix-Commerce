import { CheckCircle2, Flame, ShoppingBag, Sparkles, X, Zap } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export type ToastItem = {
    id: number;
    type: string;
    headline: string;
    subtitle: string;
    productName: string;
    productImageUrl?: string | null;
    productUrl?: string | null;
    timeAgo: string;
    isVerified: boolean;
};

type Props = {
    config?: {
        enabled?: boolean;
        position?: 'bottom-left' | 'bottom-right' | 'top-left' | 'top-right';
        themeStyle?: 'modern_white' | 'dark_luxury' | 'minimal_glass';
        initialDelay?: number;
        displayDuration?: number;
        interval?: number;
        maxPerSession?: number;
        hideOnMobile?: boolean;
        allowDismiss?: boolean;
    };
    feedUrl: string;
    heartbeatUrl: string;
    initialFeed?: ToastItem[];
};

export default function SocialProofToast({
    config,
    feedUrl,
    initialFeed = [],
}: Props) {
    const isEnabled = config?.enabled ?? true;
    const position = config?.position ?? 'bottom-left';
    const themeStyle = config?.themeStyle ?? 'modern_white';
    const initialDelay = (config?.initialDelay ?? 3) * 1000;
    const displayDuration = (config?.displayDuration ?? 5) * 1000;
    const interval = (config?.interval ?? 8) * 1000;
    const maxPerSession = config?.maxPerSession ?? 8;
    const hideOnMobile = config?.hideOnMobile ?? false;
    const allowDismiss = config?.allowDismiss ?? true;

    const [feed, setFeed] = useState<ToastItem[]>(initialFeed);
    const [currentIndex, setCurrentIndex] = useState(0);
    const [visible, setVisible] = useState(false);
    const [dismissed, setDismissed] = useState(false);
    const shownCountRef = useRef(0);
    const timerRef = useRef<NodeJS.Timeout | null>(null);

    // Fetch dynamic feed if initialFeed was empty
    useEffect(() => {
        if (!isEnabled || initialFeed.length > 0) return;

        let isMounted = true;
        fetch(feedUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (isMounted && data?.feed && Array.isArray(data.feed)) {
                    setFeed(data.feed);
                }
            })
            .catch(() => {});

        return () => {
            isMounted = false;
        };
    }, [isEnabled, feedUrl, initialFeed.length]);

    // Check session dismissal state
    useEffect(() => {
        if (typeof window !== 'undefined') {
            const isDismissed = sessionStorage.getItem('modrix_social_proof_dismissed') === 'true';
            if (isDismissed) {
                setDismissed(true);
            }
        }
    }, []);

    // Main notification cycle loop
    useEffect(() => {
        if (!isEnabled || dismissed || feed.length === 0) return;

        // Start after initial delay
        const initialTimer = setTimeout(() => {
            showNextNotification();
        }, initialDelay);

        return () => {
            clearTimeout(initialTimer);
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, [isEnabled, dismissed, feed]);

    function showNextNotification() {
        if (dismissed || shownCountRef.current >= maxPerSession || feed.length === 0) {
            return;
        }

        setVisible(true);
        shownCountRef.current += 1;

        // Record impression
        const currentItem = feed[currentIndex % feed.length];
        if (currentItem && currentItem.id < 800000) {
            try {
                const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
                fetch(`/m/modrixcommerce/social-proof-notifications/track/impression/${currentItem.id}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token || '',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                }).catch(() => {});
            } catch {
                // Non-blocking telemetry
            }
        }

        // Hide after displayDuration
        timerRef.current = setTimeout(() => {
            setVisible(false);

            // Wait interval before showing next item
            timerRef.current = setTimeout(() => {
                setCurrentIndex((prev) => (prev + 1) % feed.length);
                showNextNotification();
            }, interval);
        }, displayDuration);
    }

    function handleDismiss(e: React.MouseEvent) {
        e.stopPropagation();
        e.preventDefault();
        setVisible(false);
        setDismissed(true);
        if (typeof window !== 'undefined') {
            sessionStorage.setItem('modrix_social_proof_dismissed', 'true');
        }
    }

    function handleClickNotification(item: ToastItem) {
        // Record click
        if (item.id < 800000) {
            try {
                const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
                fetch(`/m/modrixcommerce/social-proof-notifications/track/click/${item.id}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token || '',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                }).catch(() => {});
            } catch {
                // Non-blocking telemetry
            }
        }

        if (item.productUrl) {
            window.location.href = item.productUrl;
        }
    }

    if (!isEnabled || dismissed || feed.length === 0) {
        return null;
    }

    const currentItem = feed[currentIndex % feed.length];
    if (!currentItem) return null;

    // Position styling
    const positionClasses = {
        'bottom-left': 'bottom-4 left-4 sm:bottom-6 sm:left-6',
        'bottom-right': 'bottom-4 right-4 sm:bottom-6 sm:right-6',
        'top-left': 'top-20 left-4 sm:top-24 sm:left-6',
        'top-right': 'top-20 right-4 sm:top-24 sm:right-6',
    }[position];

    // Theme container styling
    const themeClasses = {
        modern_white: 'bg-white text-gray-900 border border-gray-200/80 shadow-2xl shadow-gray-950/10',
        dark_luxury: 'bg-gray-900/95 text-white border border-gray-800 shadow-2xl shadow-black/40 backdrop-blur-md',
        minimal_glass: 'bg-white/85 dark:bg-gray-900/85 backdrop-blur-xl text-gray-900 dark:text-white border border-white/40 dark:border-gray-800/60 shadow-2xl',
    }[themeStyle];

    const isUrgency = currentItem.type === 'low_stock' || currentItem.type === 'product_viewers';

    return (
        <div
            className={`fixed z-50 pointer-events-none transition-all duration-500 ease-out ${positionClasses} ${
                hideOnMobile ? 'hidden sm:block' : 'block'
            } ${
                visible
                    ? 'opacity-100 translate-y-0 scale-100'
                    : 'opacity-0 translate-y-3 scale-95 pointer-events-none'
            }`}
            style={{ maxWidth: '380px' }}
        >
            <div
                onClick={() => handleClickNotification(currentItem)}
                className={`pointer-events-auto group relative flex items-center gap-3.5 p-3 sm:p-3.5 rounded-2xl cursor-pointer select-none transition-transform duration-200 hover:scale-[1.02] active:scale-[0.99] ${themeClasses}`}
                role="alert"
                aria-live="polite"
            >
                {/* Product Image / Icon Thumbnail */}
                <div className="relative shrink-0 w-13 h-13 sm:w-14 sm:h-14 rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-800 border border-gray-200/60 dark:border-gray-700/60 flex items-center justify-center">
                    {currentItem.productImageUrl ? (
                        <img
                            src={currentItem.productImageUrl}
                            alt={currentItem.productName}
                            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        />
                    ) : isUrgency ? (
                        <div className="w-full h-full flex items-center justify-center bg-amber-500/10 text-amber-600">
                            <Flame className="w-6 h-6 animate-pulse" />
                        </div>
                    ) : (
                        <div className="w-full h-full flex items-center justify-center bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400">
                            <ShoppingBag className="w-6 h-6" />
                        </div>
                    )}

                    {/* Live Pulse Badge Dot */}
                    <span className="absolute bottom-1 right-1 flex h-2.5 w-2.5">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                        <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500" />
                    </span>
                </div>

                {/* Content Details */}
                <div className="flex-1 min-w-0 pr-4">
                    <div className="flex items-center gap-1.5 text-xs font-semibold leading-tight mb-0.5">
                        <span className="truncate">{currentItem.headline}</span>
                        {currentItem.isVerified && (
                            <span className="inline-flex items-center text-emerald-600 dark:text-emerald-400 shrink-0" title="Verified Customer Purchase">
                                <CheckCircle2 className="w-3.5 h-3.5" />
                            </span>
                        )}
                        {isUrgency && (
                            <span className="inline-flex items-center text-amber-500 shrink-0">
                                <Zap className="w-3.5 h-3.5 fill-current" />
                            </span>
                        )}
                    </div>

                    <p className="text-xs text-gray-600 dark:text-gray-300 line-clamp-2 leading-relaxed">
                        {currentItem.subtitle}
                    </p>

                    <div className="flex items-center gap-2 mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                        <span>{currentItem.timeAgo}</span>
                        <span>•</span>
                        <span className="inline-flex items-center gap-0.5 text-indigo-600 dark:text-indigo-400 font-medium group-hover:underline">
                            <Sparkles className="w-3 h-3" />
                            <span>View product</span>
                        </span>
                    </div>
                </div>

                {/* Dismiss Button */}
                {allowDismiss && (
                    <button
                        type="button"
                        onClick={handleDismiss}
                        aria-label="Dismiss notification"
                        className="absolute top-2 right-2 p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                )}
            </div>
        </div>
    );
}
