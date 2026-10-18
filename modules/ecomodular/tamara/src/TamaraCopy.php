<?php

namespace EcoModular\Tamara;

/**
 * Customer-facing wording for the Tamara integration, in English and Arabic.
 * Kept close to Tamara's approved phrasing: Tamara reviews integrations for it.
 */
final class TamaraCopy
{
    private const TEXT = [
        'en' => [
            'method' => 'Pay in 4 with Tamara',
            'description' => 'Split your purchase into 4 interest-free payments. You will be redirected to Tamara to complete your purchase.',
            'not_available' => 'Sorry, Tamara is unable to approve this purchase. Please use an alternative payment method for your order.',
            'order_amount_too_high' => 'This purchase is above your current spending limit with Tamara, try a smaller cart or use another payment method',
            'order_amount_too_low' => 'The purchase amount is below the minimum amount required to use Tamara, try adding more items or use another payment method',
            'cancelled' => 'You aborted the payment. Please retry or choose another payment method.',
        ],
        'ar' => [
            'method' => 'ادفع على 4 دفعات مع تمارا',
            'description' => 'قسّم مشترياتك إلى 4 دفعات بدون فوائد. سيتم تحويلك إلى تمارا لإتمام عملية الشراء.',
            'not_available' => 'نأسف، تمارا غير قادرة على الموافقة على هذه العملية. الرجاء استخدام طريقة دفع أخرى.',
            'order_amount_too_high' => 'قيمة الطلب تفوق الحد الأقصى المسموح به حاليًا مع تمارا. يُرجى تخفيض قيمة السلة أو استخدام وسيلة دفع أخرى.',
            'order_amount_too_low' => 'قيمة الطلب أقل من الحد الأدنى المطلوب لاستخدام خدمة تمارا. يُرجى زيادة قيمة الطلب أو استخدام وسيلة دفع أخرى.',
            'cancelled' => 'لقد ألغيت الدفعة. فضلاً حاول مجددًا أو اختر طريقة دفع أخرى.',
        ],
    ];

    /** Tamara supports English and Arabic; anything else falls back to English. */
    public static function lang(?string $locale = null): string
    {
        return str_starts_with(strtolower($locale ?? app()->getLocale()), 'ar') ? 'ar' : 'en';
    }

    /** Tamara's locale string, e.g. "ar_SA" or "en_AE"; the market country wins when known. */
    public static function locale(?string $locale = null, ?string $country = null): string
    {
        $lang = self::lang($locale);
        $country ??= $lang === 'ar' ? 'SA' : 'AE';

        return $lang.'_'.strtoupper($country);
    }

    public static function get(string $key, ?string $locale = null): string
    {
        return self::TEXT[self::lang($locale)][$key] ?? self::TEXT['en'][$key];
    }

    /** The approved message for a Tamara rejection reason; unknown reasons use the general one. */
    public static function rejection(?string $reason, ?string $locale = null): string
    {
        return self::get(in_array($reason, ['order_amount_too_high', 'order_amount_too_low'], true) ? $reason : 'not_available', $locale);
    }
}
