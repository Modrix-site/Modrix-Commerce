import type { NotificationItem } from '@/components/notifications/notification-bell';
import type { Replacements } from '@/lib/i18n';

type Translate = (key: string, replace?: Replacements) => string;

/**
 * Customer notifications in the shopper's language. The server stores the
 * English text; these literal templates (the same as NotificationKinds)
 * are in the storefront catalogue. Unknown kinds keep the stored text.
 */
export function customerNotificationText(
    t: Translate,
): (item: NotificationItem) => NotificationItem {
    return (item) => {
        const raw = item.params.number;
        const number =
            typeof raw === 'string' || typeof raw === 'number'
                ? String(raw)
                : '';
        const text = (title: string, body: string) => ({
            ...item,
            title,
            body,
        });

        switch (item.kind) {
            case 'customer.order_confirmed':
                return text(
                    t('Your order :number is confirmed', { number }),
                    t('We are preparing it now.'),
                );
            case 'customer.order_shipped':
                return text(
                    t('Your order :number has shipped', { number }),
                    t('It is on its way.'),
                );
            case 'customer.out_for_delivery':
                return text(
                    t('Your order :number is out for delivery', { number }),
                    t('It arrives today.'),
                );
            case 'customer.delivered':
                return text(
                    t('Your order :number was delivered', { number }),
                    t('Enjoy your purchase.'),
                );
            case 'customer.cancelled':
                return text(
                    t('Your order :number was cancelled', { number }),
                    t('Contact us if you have questions.'),
                );
            case 'customer.refunded':
                return text(
                    t('Refund for order :number', { number }),
                    t('Your refund is on its way.'),
                );
            case 'customer.payment_failed':
                return text(
                    t('Payment for order :number failed', { number }),
                    t('Please try again or choose another payment method.'),
                );
            case 'customer.downloads_ready':
                return text(
                    t('Your downloads for :number are ready', { number }),
                    t('Open your order to download your files.'),
                );
            default:
                return item;
        }
    };
}
