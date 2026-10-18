import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

declare global {
    interface Window {
        dataLayer?: unknown[];
        gtag?: (...args: unknown[]) => void;
    }
}

type Props = {
    measurementId: string;
    trackPageViews: boolean;
    anonymizeIp: boolean;
    debugMode: boolean;
};

/**
 * Loads the Google tag (gtag.js) into the page head and records page views.
 * The overlay slot mounts once per visit, so the tag is loaded once and each
 * Inertia navigation sends one page_view. No UI is rendered.
 */
export default function GoogleAnalytics({
    measurementId,
    trackPageViews,
    anonymizeIp,
    debugMode,
}: Props) {
    const { url } = usePage();

    useEffect(() => {
        window.dataLayer = window.dataLayer || [];
        window.gtag =
            window.gtag ||
            function gtag(...args: unknown[]) {
                window.dataLayer?.push(args);
            };
        window.gtag('js', new Date());
        window.gtag('config', measurementId, {
            // Page views are sent from the effect below so Inertia navigations are counted.
            send_page_view: false,
            ...(anonymizeIp ? { anonymize_ip: true } : {}),
            ...(debugMode ? { debug_mode: true } : {}),
        });

        if (!document.querySelector(`script[data-ga="${measurementId}"]`)) {
            const script = document.createElement('script');
            script.async = true;
            script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
            script.dataset.ga = measurementId;
            document.head.appendChild(script);
        }
    }, [measurementId, anonymizeIp, debugMode]);

    useEffect(() => {
        if (!trackPageViews) {
            return;
        }

        window.gtag?.('event', 'page_view', {
            page_path: url,
            page_location: window.location.href,
            page_title: document.title,
        });
    }, [url, trackPageViews, measurementId]);

    return null;
}
