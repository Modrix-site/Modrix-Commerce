<?php

namespace Tests\Unit;

use App\Core\Themes\CustomizerTabs;
use App\Core\Themes\PageSettingFields;
use App\Core\Themes\ThemeSchema;
use App\Domains\Catalog\StorefrontCatalog;
use PHPUnit\Framework\TestCase;

class PageSettingFieldsTest extends TestCase
{
    public function test_fields_and_tabs()
    {
        $fields = PageSettingFields::fields();

        $this->assertSame(['2', '3', '4', '5'], $fields['catalog.columns_desktop']['options']);
        $this->assertSame([12, 48], [$fields['catalog.per_page']['min'], $fields['catalog.per_page']['max']]);
        $this->assertSame(['hidden', 'optional', 'required'], $fields['checkout.phone']['options']);
        $this->assertSame(StorefrontCatalog::SORTS, $fields['catalog.default_sort']['options']);

        foreach (['header', 'catalog', 'product', 'cart', 'checkout'] as $tab) {
            $keys = array_filter(array_keys($fields), fn (string $key) => str_starts_with($key, "{$tab}."));
            $this->assertNotEmpty($keys, $tab);

            foreach ($keys as $key) {
                $this->assertSame($tab, CustomizerTabs::for($key, $fields[$key]), $key);
            }
        }
    }

    public function test_resolve_applies_defaults_and_types()
    {
        $design = array_fill_keys(array_keys(PageSettingFields::fields()), '');
        $pages = PageSettingFields::resolve(['catalog.per_page' => 12, 'checkout.phone' => 'required', 'product.zoom' => true] + $design);

        $this->assertSame(12, $pages['catalog']['per_page']);
        $this->assertSame('newest', $pages['catalog']['default_sort']);
        $this->assertSame('required', $pages['checkout']['phone']);
        $this->assertTrue($pages['checkout']['company']);
        $this->assertTrue($pages['product']['zoom']);
        $this->assertFalse($pages['cart']['free_shipping_bar']);
        $this->assertNull($pages['header']['search']);
        $this->assertSame('Place order', $pages['checkout']['button_label']);
        // Every merchant-picked related product still shows (up to 12).
        $this->assertSame(12, $pages['product']['related_count']);
        // Untouched stores keep today's product page and card buttons.
        $this->assertTrue($pages['product']['show_sku']);
        $this->assertSame('low', $pages['product']['stock_note']);
        $this->assertNull($pages['catalog']['quick_add']);
    }

    public function test_out_of_range_is_rejected()
    {
        [, $error] = ThemeSchema::check(PageSettingFields::fields()['catalog.per_page'], 60, fn () => true);
        $this->assertNotNull($error);

        [, $error] = ThemeSchema::check(PageSettingFields::fields()['checkout.phone'], 'sometimes', fn () => true);
        $this->assertNotNull($error);
    }

    public function test_page_keys()
    {
        $this->assertTrue(PageSettingFields::isPageKey('catalog.per_page'));
        $this->assertFalse(PageSettingFields::isPageKey('color.primary'));
        $this->assertFalse(PageSettingFields::isPageKey('style.cart.price_color'));
    }
}
