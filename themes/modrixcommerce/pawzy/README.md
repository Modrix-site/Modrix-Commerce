# Pawzy

`modrixcommerce/pawzy` · **Modrix team** · Version 1.0.0 · Modrix Commerce ^1.0

A pet storefront based on the supplied Pawzy home, collection and product references. All theme components, styling, fonts, settings and demo media live in this folder. The theme uses new storefront code and downloaded unbranded imagery; it does not include Shopify theme code.

## Install and customize

1. Build the platform frontend with `npm run build` after adding or downloading this package.
2. Open **Appearance → Pawzy → Customize**. Pawzy supplies `src/customizer.tsx` and `settings.json`; the editor retains core save, preview, translation, media-upload and extension-block controls.
3. Preview Pawzy before activation. Import demo content from its Appearance actions when desired. Import adds missing categories, products, gallery images and information pages; existing slugs, SKUs and deleted pages are preserved. Prices use the store currency.
4. Activate when ready. Existing merchant customizations remain outside the package and survive updates.

The General and page tabs expose body/heading font family and size, button/price styling, colors, spacing, logos and announcements. Pawzy adds header color, search, navigation and category drawer switches; hero height/alignment/shade; collection banner, grid columns and toolbar/sidebar filters; product benefit, delivery, return and care text; homepage FAQs; and footer/newsletter controls. Home sections can be reordered, edited or removed, including hero campaigns, category circles, product grids, editorial tiles and split-image features.

Header/footer links can use Pawzy's own editable defaults or the shared **Navigation** menus by enabling **Use core Navigation menus**. The category drawer follows the live catalog and also renders the core sidebar menu. Filters and sorting use the platform query parameters; pagination preserves real server results. Collection link labels follow the actual store categories.

## Core and extension integration

- Checkout, account, cart, totals, stock, shipping, coupons, payment gateways and order history use the core pages and server actions.
- Product pages retain variant selection, stock validation, quantity limits, direct buy, sale notices, product labels, related products, upsells and recently viewed products.
- All standard product/card/header/catalog placements are retained. Fixed home, product and catalog extension content uses the core slot payloads. The default layout preserves sidebar navigation, theme preview, direction, notifications and global overlays.
- Newsletter footer content comes from the enabled Newsletter extension, with its actual consent form and subscription endpoint. Product Reviews, Wishlist, Cross-sell, product labels, popup and other extensions render when installed, enabled and assigned through the core customizer. No synthetic reviews, fake purchase notifications or nonfunctional wishlist forms are included.
- Payment gateways remain independent of the active theme, including Razorpay, PayTabs and CCAvenue when installed and enabled.

## Demo content and media

`demo.json` includes nine categories, fifteen products, a sample multi-image gallery and six editable information pages. Lifestyle photos illustrate collections; replace them with accurate photography for sellable merchandise. Review product specifications, demo policy text and currency before using them in a live store.

Twenty-two downloaded images are bundled for offline demo import. `assets/SOURCES.json` records every source URL and origin. Lifestyle photos come from Pexels; plain product photography comes from the user-provided public reference demo. Image rights remain with their respective owners; package code licensing does not relicense third-party photography. Manrope is bundled under its SIL Open Font License.

The importer supports optional `pages` entries (`title`, `slug`, `content`) and optional product `images` arrays (up to eight additional gallery images). Pages are cleaned through the core rich-text sanitizer; files remain restricted to the theme assets directory. Imports are transactional and additive.

## Publish to the local catalog

```text
php marketplace-catalog/bin/publish-package.php modrixcommerce/pawzy
```

Claim the marketplace license, activate it in the target core store, install/enable the theme and rebuild frontend assets. A source checkout already has the theme files; an empty target exercises the download path.

## Checks

```text
php vendor/phpunit/phpunit/phpunit tests/Feature/Themes/PawzyThemeTest.php tests/Feature/Themes/ThemeOwnedCustomizerTest.php tests/Feature/Themes/ThemeFrontendTest.php tests/Feature/Themes/ThemeCompatibilityTest.php
npm run types:check
npm run build
```
