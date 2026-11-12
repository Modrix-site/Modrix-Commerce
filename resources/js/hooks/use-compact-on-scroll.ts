import { useEffect, useState } from 'react';

/**
 * True once the page is scrolled past `enter` pixels, false again below
 * `leave`. The gap stops the header flickering when shrinking it changes the
 * page height near the threshold.
 */
export function useCompactOnScroll(enter = 120, leave = 40): boolean {
    const [compact, setCompact] = useState(false);

    useEffect(() => {
        let frame = 0;

        const update = () => {
            frame = 0;
            const y = window.scrollY;
            setCompact((current) => (current ? y > leave : y > enter));
        };

        const onScroll = () => {
            if (frame === 0) {
                frame = window.requestAnimationFrame(update);
            }
        };

        update();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => {
            window.removeEventListener('scroll', onScroll);

            if (frame !== 0) {
                window.cancelAnimationFrame(frame);
            }
        };
    }, [enter, leave]);

    return compact;
}
