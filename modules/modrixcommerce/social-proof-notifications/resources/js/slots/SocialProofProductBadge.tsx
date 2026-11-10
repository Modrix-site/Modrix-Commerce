import { Eye, Flame, Zap } from 'lucide-react';
import { useEffect, useState } from 'react';

type BadgeData = {
    recentOrdersCount: number;
    liveViewersCount: number;
    isLowStock: boolean;
    remainingStock: number | null;
};

type Props = {
    productId: number;
    badge: BadgeData;
    heartbeatUrl: string;
};

export default function SocialProofProductBadge({
    productId,
    badge,
    heartbeatUrl,
}: Props) {
    const [viewers, setViewers] = useState(badge.liveViewersCount);

    // Heartbeat effect to track and update live viewer counter
    useEffect(() => {
        if (!productId || !heartbeatUrl) return;

        function sendHeartbeat() {
            try {
                const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
                fetch(heartbeatUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token || '',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ product_id: productId }),
                    credentials: 'same-origin',
                })
                    .then((res) => (res.ok ? res.json() : null))
                    .then((data) => {
                        if (data?.viewers !== undefined && typeof data.viewers === 'number') {
                            setViewers(data.viewers);
                        }
                    })
                    .catch(() => {});
            } catch {
                // Non-blocking
            }
        }

        // Send immediately on mount
        sendHeartbeat();

        // Recurring heartbeat every 40 seconds
        const interval = setInterval(sendHeartbeat, 40000);
        return () => clearInterval(interval);
    }, [productId, heartbeatUrl]);

    const hasViewers = viewers > 1;
    const hasRecentOrders = badge.recentOrdersCount > 0;
    const isLowStock = badge.isLowStock;

    if (!hasViewers && !hasRecentOrders && !isLowStock) {
        return null;
    }

    return (
        <div className="my-3.5 flex flex-wrap items-center gap-2 text-xs">
            {/* Live Viewers Pill */}
            {hasViewers && (
                <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/50 font-medium shadow-xs">
                    <span className="relative flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
                    </span>
                    <Eye className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                    <span>
                        <strong className="font-semibold">{viewers}</strong> people viewing right now
                    </span>
                </div>
            )}

            {/* Recent Orders Pill */}
            {hasRecentOrders && (
                <div className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border border-amber-200/60 dark:border-amber-800/50 font-medium shadow-xs">
                    <Flame className="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 animate-pulse" />
                    <span>
                        <strong className="font-semibold">{badge.recentOrdersCount}</strong> purchased in the last 48h
                    </span>
                </div>
            )}

            {/* Low Stock Urgency Pill */}
            {isLowStock && (
                <div className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200/60 dark:border-rose-800/50 font-medium shadow-xs">
                    <Zap className="w-3.5 h-3.5 text-rose-600 dark:text-rose-400 fill-current" />
                    <span>
                        Only <strong className="font-semibold">{badge.remainingStock}</strong> units left — order soon
                    </span>
                </div>
            )}
        </div>
    );
}
