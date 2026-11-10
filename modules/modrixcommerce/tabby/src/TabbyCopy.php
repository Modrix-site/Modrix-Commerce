<?php

namespace EcoModular\Tabby;

/**
 * Customer-facing wording required by Tabby (docs.tabby.ai, "Checkout Flow"),
 * in English and Arabic. Kept verbatim: Tabby reviews integrations for it.
 */
final class TabbyCopy
{
    private const TEXT = [
        'en' => [
            'method' => 'Pay later with Tabby',
            'description' => 'Split your purchase into interest-free payments. You will be redirected to Tabby to complete your purchase.',
            'not_available' => 'Sorry, Tabby is unable to approve this purchase. Please use an alternative payment method for your order.',
            'order_amount_too_high' => 'This purchase is above your current spending limit with Tabby, try a smaller cart or use another payment method',
            'order_amount_too_low' => 'The purchase amount is below the minimum amount required to use Tabby, try adding more items or use another payment method',
            'cancelled' => 'You aborted the payment. Please retry or choose another payment method.',
        ],
        'ar' => [
            'method' => 'ادفع لاحقًا مع تابي',
            'description' => 'قسّم مشترياتك إلى دفعات بدون فوائد. سيتم تحويلك إلى تابي لإتمام عملية الشراء.',
            'not_available' => 'نأسف، تابي غير قادرة على الموافقة على هذه العملية. الرجاء استخدام طريقة دفع أخرى.',
            'order_amount_too_high' => 'قيمة الطلب تفوق الحد الأقصى المسموح به حاليًا مع تابي. يُرجى تخفيض قيمة السلة أو استخدام وسيلة دفع أخرى.',
            'order_amount_too_low' => 'قيمة الطلب أقل من الحد الأدنى المطلوب لاستخدام خدمة تابي. يُرجى زيادة قيمة الطلب أو استخدام وسيلة دفع أخرى.',
            'cancelled' => 'لقد ألغيت الدفعة. فضلاً حاول مجددًا أو اختر طريقة دفع أخرى.',
        ],
    ];

    /** Tabby supports English and Arabic; anything else falls back to English. */
    public static function lang(?string $locale = null): string
    {
        return str_starts_with(strtolower($locale ?? app()->getLocale()), 'ar') ? 'ar' : 'en';
    }

    public static function get(string $key, ?string $locale = null): string
    {
        return self::TEXT[self::lang($locale)][$key] ?? self::TEXT['en'][$key];
    }

    /** The approved message for a Tabby rejection reason; unknown reasons use the general one. */
    public static function rejection(?string $reason, ?string $locale = null): string
    {
        return self::get(in_array($reason, ['order_amount_too_high', 'order_amount_too_low'], true) ? $reason : 'not_available', $locale);
    }
}
