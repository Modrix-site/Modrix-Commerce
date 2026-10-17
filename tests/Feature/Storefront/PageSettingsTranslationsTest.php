<?php

namespace Tests\Feature\Storefront;

use Tests\TestCase;

class PageSettingsTranslationsTest extends TestCase
{
    /** Storefront texts added by the page settings. */
    public const KEYS = [
        'Brand',
        'Details',
        'Easy returns',
        'Free-shipping progress',
        'Friendly support',
        'I agree to the terms and conditions',
        'In stock',
        'Phone',
        'Secure payment',
        'Terms and conditions',
        'You may also need',
        "You're :amount away from free shipping",
        "You've got free shipping!",
        'Zoom image',
    ];

    public function test_every_text_is_translated()
    {
        $source = json_decode((string) file_get_contents(lang_path('storefront/en.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach (['es', 'fr', 'de'] as $locale) {
            $messages = json_decode((string) file_get_contents(lang_path("storefront/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

            foreach (self::KEYS as $key) {
                $this->assertArrayHasKey($key, $source, "en: {$key}");
                $this->assertNotSame('', $messages[$key] ?? '', "{$locale}: {$key}");
            }
        }
    }
}
