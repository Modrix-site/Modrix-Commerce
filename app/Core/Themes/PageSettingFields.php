<?php

namespace App\Core\Themes;

use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\StorefrontCatalog;

/**
 * Page settings a merchant sets in the customizer's Header, Catalog,
 * Product, Cart and Checkout tabs. They are core design fields, stored for
 * the whole store (not per theme) so they survive a theme switch, and sent
 * to the storefront resolved (`theme.pages`). An unset value means the
 * default below; a null default means the theme decides.
 */
final class PageSettingFields
{
    /** Settings key of the store-wide values. */
    public const STORE_KEY = 'theme.pages.design';

    public const PAGES = ['header', 'catalog', 'product', 'cart', 'checkout'];

    /**
     * Defaults when the merchant has not set a value (null: the theme decides).
     * They match how the storefront already behaved, so an untouched store
     * looks the same.
     */
    public const DEFAULTS = [
        'header.logo_height' => null,
        'header.background' => null,
        'header.text' => null,
        'header.search' => null,
        'header.menu_align' => null,
        'header.cart_icon' => null,
        'header.cart_count' => true,
        'header.account_link' => true,
        'catalog.columns_desktop' => null,
        'catalog.columns_mobile' => 2,
        'catalog.per_page' => 24,
        'catalog.default_sort' => 'newest',
        'catalog.filters' => true,
        'catalog.image_ratio' => null,
        'catalog.hover_image' => false,
        'catalog.quick_add' => null,
        'catalog.badges' => true,
        'catalog.show_brand' => false,
        'product.thumbnails' => null,
        'product.zoom' => false,
        'product.show_sku' => true,
        'product.stock_note' => 'low',
        'product.description' => 'open',
        'product.related' => true,
        'product.related_count' => 12,
        'product.upsells' => true,
        'product.quantity' => true,
        'cart.free_shipping_bar' => false,
        'cart.cross_sells' => false,
        'cart.coupon' => true,
        'cart.trust_badges' => false,
        'cart.continue_link' => false,
        'checkout.summary_side' => 'right',
        'checkout.sticky_summary' => true,
        'checkout.company' => true,
        'checkout.address_line2' => true,
        'checkout.phone' => 'optional',
        'checkout.order_note' => true,
        'checkout.terms' => false,
        'checkout.trust_badges' => false,
        'checkout.heading' => 'Checkout',
        'checkout.button_label' => 'Place order',
        'checkout.summary_images' => true,
        'checkout.coupon' => true,
    ];

    /** Select values sent to the storefront as numbers. */
    private const NUMERIC = ['catalog.columns_desktop', 'catalog.columns_mobile'];

    private const GROUPS = [
        'header' => 'Header layout',
        'catalog' => 'Catalog page',
        'product' => 'Product page',
        'cart' => 'Cart page',
        'checkout' => 'Checkout page',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array
    {
        $definitions = [
            'header.logo_height' => ['type' => 'integer', 'label' => 'Logo height, px', 'min' => 24, 'max' => 96],
            'header.background' => ['type' => 'color', 'label' => 'Header background'],
            'header.text' => ['type' => 'color', 'label' => 'Header text colour'],
            'header.search' => ['type' => 'select', 'label' => 'Search', 'options' => ['bar', 'icon']],
            'header.menu_align' => ['type' => 'select', 'label' => 'Menu alignment', 'options' => ['left', 'center', 'right']],
            'header.cart_icon' => ['type' => 'select', 'label' => 'Cart icon', 'options' => ['bag', 'cart']],
            'header.cart_count' => ['type' => 'boolean', 'label' => 'Show cart count'],
            'header.account_link' => ['type' => 'boolean', 'label' => 'Show account link'],
            'catalog.columns_desktop' => ['type' => 'select', 'label' => 'Columns on desktop', 'options' => ['2', '3', '4', '5']],
            'catalog.columns_mobile' => ['type' => 'select', 'label' => 'Columns on phones', 'options' => ['1', '2']],
            'catalog.per_page' => ['type' => 'integer', 'label' => 'Products per page', 'min' => 12, 'max' => 48],
            'catalog.default_sort' => ['type' => 'select', 'label' => 'Default sort', 'options' => StorefrontCatalog::SORTS],
            'catalog.filters' => ['type' => 'boolean', 'label' => 'Show filters'],
            'catalog.image_ratio' => ['type' => 'select', 'label' => 'Image shape', 'options' => ['square', 'portrait', 'landscape']],
            'catalog.hover_image' => ['type' => 'boolean', 'label' => 'Second image on hover'],
            'catalog.quick_add' => ['type' => 'boolean', 'label' => 'Quick add to cart'],
            'catalog.badges' => ['type' => 'boolean', 'label' => 'Sale and label badges'],
            'catalog.show_brand' => ['type' => 'boolean', 'label' => 'Show brand on cards'],
            'product.thumbnails' => ['type' => 'select', 'label' => 'Gallery thumbnails', 'options' => ['side', 'bottom']],
            'product.zoom' => ['type' => 'boolean', 'label' => 'Zoom images on hover'],
            'product.show_sku' => ['type' => 'boolean', 'label' => 'Show SKU'],
            'product.stock_note' => ['type' => 'select', 'label' => 'Stock note', 'options' => ['off', 'low', 'always']],
            'product.description' => ['type' => 'select', 'label' => 'Description layout', 'options' => ['open', 'tabs', 'accordion']],
            'product.related' => ['type' => 'boolean', 'label' => 'Show related products'],
            'product.related_count' => ['type' => 'integer', 'label' => 'Related products shown', 'min' => 2, 'max' => 12],
            'product.upsells' => ['type' => 'boolean', 'label' => 'Show upsells'],
            'product.quantity' => ['type' => 'boolean', 'label' => 'Quantity selector'],
            'cart.free_shipping_bar' => ['type' => 'boolean', 'label' => 'Free-shipping progress bar'],
            'cart.cross_sells' => ['type' => 'boolean', 'label' => 'Cross-sells'],
            'cart.coupon' => ['type' => 'boolean', 'label' => 'Coupon field'],
            'cart.trust_badges' => ['type' => 'boolean', 'label' => 'Trust badges'],
            'cart.continue_link' => ['type' => 'boolean', 'label' => 'Continue shopping link'],
            'checkout.summary_side' => ['type' => 'select', 'label' => 'Order summary side', 'options' => ['right', 'left']],
            'checkout.sticky_summary' => ['type' => 'boolean', 'label' => 'Keep the summary in view'],
            'checkout.company' => ['type' => 'boolean', 'label' => 'Company field'],
            'checkout.address_line2' => ['type' => 'boolean', 'label' => 'Address line 2'],
            'checkout.phone' => ['type' => 'select', 'label' => 'Phone', 'options' => ['hidden', 'optional', 'required']],
            'checkout.order_note' => ['type' => 'boolean', 'label' => 'Order note'],
            'checkout.terms' => ['type' => 'boolean', 'label' => 'Require accepting the terms'],
            'checkout.trust_badges' => ['type' => 'boolean', 'label' => 'Trust badges'],
            'checkout.heading' => ['type' => 'string', 'label' => 'Heading', 'max' => 60],
            'checkout.button_label' => ['type' => 'string', 'label' => 'Place order button', 'max' => 40],
            'checkout.summary_images' => ['type' => 'boolean', 'label' => 'Product images in the summary'],
            'checkout.coupon' => ['type' => 'boolean', 'label' => 'Coupon field'],
        ];

        $fields = [];

        foreach ($definitions as $key => $field) {
            [$page] = explode('.', $key, 2);
            $fields[$key] = $field + ['group' => self::GROUPS[$page], 'tab' => $page];
        }

        return $fields;
    }

    /**
     * Defaults the customizer shows as "Theme default (…)": the ones the
     * platform fixes (null ones are the theme's own).
     *
     * @return array<string, mixed>
     */
    public static function displayDefaults(): array
    {
        return array_filter(self::DEFAULTS, fn ($value) => $value !== null);
    }

    /**
     * The merchant's saved page settings, whatever theme renders the store.
     *
     * @return array<string, mixed>
     */
    public static function storeValues(): array
    {
        return (array) (app(SettingsRepository::class)->get(self::STORE_KEY) ?? []);
    }

    public static function isPageKey(string $key): bool
    {
        return array_key_exists($key, self::DEFAULTS);
    }

    /**
     * Values per page for the storefront, keys without the page prefix.
     *
     * @param  array<string, mixed>  $design  effective design (unset values are '')
     * @return array<string, array<string, mixed>>
     */
    public static function resolve(array $design): array
    {
        $fields = self::fields();
        $pages = array_fill_keys(self::PAGES, []);

        foreach (self::DEFAULTS as $key => $default) {
            [$page, $name] = explode('.', $key, 2);
            $value = $design[$key] ?? '';

            if ($value === '') {
                $value = $default;
            } elseif ($fields[$key]['type'] === 'boolean') {
                $value = (bool) $value;
            } elseif ($fields[$key]['type'] === 'integer' || in_array($key, self::NUMERIC, true)) {
                $value = (int) $value;
            }

            $pages[$page][$name] = $value;
        }

        return $pages;
    }
}
