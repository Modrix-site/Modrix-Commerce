import { useEffect, useState } from 'react';

/** No rolling or invented urgency: a merchant must set a real end date. */
export function Countdown({ end }: { end: string }) {
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(timer);
    }, []);
    const deadline = Date.parse(end);
    if (!Number.isFinite(deadline)) return null;
    const seconds = Math.max(0, Math.floor((deadline - now) / 1000));
    if (!seconds)
        return <p className="pawzy-countdown">This promotion has ended.</p>;
    const parts = [
        Math.floor(seconds / 86400),
        Math.floor(seconds / 3600) % 24,
        Math.floor(seconds / 60) % 60,
        seconds % 60,
    ];
    return (
        <div className="pawzy-countdown" aria-label="Promotion time remaining">
            {parts.map((v, i) => (
                <span key={i}>
                    <strong>{String(v).padStart(2, '0')}</strong>
                    <small>{['Days', 'Hours', 'Minutes', 'Seconds'][i]}</small>
                </span>
            ))}
        </div>
    );
}
