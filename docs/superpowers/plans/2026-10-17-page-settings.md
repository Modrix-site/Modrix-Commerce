# Page Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Header, Catalog, Product, Cart and a new Checkout customizer tab with page settings that every one of the 11 themes follows.

**Architecture:**

- The settings are core design fields defined in one new class, `PageSettingFields`.
- They are stored store-wide, so they survive theme switches.
- The storefront receives them as `theme.pages`, already resolved.
- Server rules apply them in the catalog, cart and checkout controllers.
- Shared React pieces render them; the four own-page themes reuse those pieces.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia React, TypeScript, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-17-page-settings-design.md`

## Global Constraints

- **Field keys, types, ranges and defaults:** exactly as listed in spec §1. "theme" defaults resolve to `null` (the theme decides).
- **Starting state:** every setting starts on "Theme default"; a store with no overrides renders exactly as before.
- **Storage:**
    - page settings (`header.*`, `catalog.*`, `product.*`, `cart.*`, `checkout.*`) are stored store-wide under the settings key `theme.pages.design`, not per theme;
    - fonts, colours and theme custom fields stay per theme.
- **Ownership of shared files:** about 250 changes by another developer are uncommitted in the working tree. Commit only this plan's changes:
    - new files with `git add`;
    - shared tracked files as recorded pairs (`scratchpad/pairs.py`), with separate HEAD pairs when HEAD text differs;
    - integration that cannot exist on HEAD (the anchor code is only in the working tree) stays in the working tree and is listed in the final report;
    - files of the 4 own-page themes (`themes/modrixcommerce/{nord,voltora,vellaro,hearth}`) and `atelier-chrome.tsx` / `electronics-chrome.tsx` are untracked other-developer files: working tree only.
- **Gates:**
    - `export PATH="/c/php84:$PATH"`;
    - `vendor/bin/pint` (own files);
    - `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`;
    - `npm run -s types:check`;
    - `npx vp check --fix` (own/new files only);
    - `npm run -s build`;
    - `php artisan wayfinder:generate --with-form`.
- **Texts:** new storefront texts go in `lang/storefront/{en,es,fr,de}.json` (en via `php artisan ecomodular:storefront-strings`). Components that hold texts live under `resources/js/components/storefront/` so the extractor finds them.
- **Commit trailer:** `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Rulings carried from planning

- **`checkout.coupon`:** coupons are applied only through `POST cart/coupon` today, and checkout has no coupon input. So `checkout.coupon` means "show a coupon field in the checkout summary" that posts to the existing cart coupon route; off hides it. The spec's "rejects a coupon submitted at checkout" has nothing to reject.
- **Storage:** design overrides are stored per theme (`{settingsPrefix}.design`), which would break the spec's "survive a theme switch". Page settings therefore get their own store-wide key (Task 1).

## Review Focus

1. **Second-page links with a merchant page size:** paging to page 2 with a merchant `catalog.per_page` and a visitor `sort` keeps both (`?page=2&sort=price_asc` gives 2nd page of 12, price ascending). Pinned in Task 2 (`test_page_two_keeps_sort_and_page_size`).
2. **Required phone missing:** with `checkout.phone=required`, a missing phone returns a validation error on `phone`; no order is created. Pinned in Task 3 (`test_required_phone`).
3. **Hidden fields still posted** (old tab, crafted request): the values are dropped, not stored. Pinned in Task 3 (`test_hidden_fields_are_not_stored`).
4. **Cart already over the free-shipping amount:** `remaining` is zero and the bar says free shipping, never a negative amount. Pinned in Task 4 (`test_free_shipping_reached`).
5. **Theme switch:** page settings saved under one theme still apply after another theme is activated. Pinned in Task 1 (`test_page_settings_survive_theme_switch`).

---

### Task 1: Fields, tabs and storage

**Files:**

- Create: `app/Core/Themes/PageSettingFields.php`
- Modify:
    - `app/Core/Themes/ThemeSchema.php` (pair): `designFields()` appends `+ PageSettingFields::fields()`; `builtinDesign()` appends `+ array_fill_keys(array_keys(PageSettingFields::fields()), '')`;
    - `app/Core/Themes/CustomizerTabs.php` (clean): `TABS` gains `'checkout' => 'Checkout'` after `cart`; `for()` maps `header.|catalog.|product.|cart.|checkout.` prefixes to their tab before the group keywords;
    - `resources/js/components/admin/themes/customizer-tabs.tsx` (clean): `CUSTOMIZER_TABS` gains `{ key: 'checkout', label: 'Checkout' }` after cart;
    - `app/Core/Themes/ThemeCustomizations.php` (pair): page keys are read from and written to `theme.pages.design`;
    - `app/Core/Themes/ResolvedTheme.php` (pair): `toProps()` adds `'pages' => PageSettingFields::resolve($this->design)`;
    - `docs/extensions/theme.schema.json` (pair; generate the entries): one `design.properties` entry per new key, with selects as `enum` and the integer `minimum`/`maximum`.
- Create: `resources/js/types/page-settings.ts`
- Test: `tests/Unit/PageSettingFieldsTest.php`, `tests/Feature/Themes/PageSettingsStorageTest.php`; `tests/Unit/ThemeSchemaTest.php` must stay green.

**Interfaces:**

- **Produces:**
    - `PageSettingFields::fields(): array<string, array>`: field definitions with `type`, `label`, `group` (`Header page`, `Catalog page`, `Product page`, `Cart page`, `Checkout page`), `options`/`min`/`max`/`max` length;
    - `PageSettingFields::isPageKey(string $key): bool`;
    - `PageSettingFields::resolve(array $design): array{header: array, catalog: array, product: array, cart: array, checkout: array}`: keys without prefix (`catalog['per_page']`), typed (int, bool, string), `''` replaced by the spec default, "theme" defaults as `null`;
    - TS type `PageSettings` in `resources/js/types/page-settings.ts` mirroring `resolve()`;
    - the storefront prop `storefront.theme.pages`.

- [ ] **Step 1: Write the failing tests**

```php
// tests/Unit/PageSettingFieldsTest.php
public function test_fields_and_tabs()
{
    $fields = PageSettingFields::fields();
    $this->assertSame(['2', '3', '4', '5'], $fields['catalog.columns_desktop']['options']);
    $this->assertSame([12, 48], [$fields['catalog.per_page']['min'], $fields['catalog.per_page']['max']]);
    $this->assertSame(['hidden', 'optional', 'required'], $fields['checkout.phone']['options']);
    $this->assertSame(StorefrontCatalog::SORTS, $fields['catalog.default_sort']['options']);
    foreach (['header', 'catalog', 'product', 'cart', 'checkout'] as $tab) {
        $key = array_key_first(array_filter($fields, fn ($f, $k) => str_starts_with($k, "{$tab}."), ARRAY_FILTER_USE_BOTH));
        $this->assertSame($tab, CustomizerTabs::for($key, $fields[$key]));
    }
}

public function test_resolve_applies_defaults_and_types()
{
    $pages = PageSettingFields::resolve(array_fill_keys(array_keys(PageSettingFields::fields()), '') + ['catalog.per_page' => 12, 'checkout.phone' => 'required']);
    $this->assertSame(12, $pages['catalog']['per_page']);
    $this->assertSame('newest', $pages['catalog']['default_sort']);
    $this->assertSame('required', $pages['checkout']['phone']);
    $this->assertTrue($pages['checkout']['company']);
    $this->assertFalse($pages['cart']['free_shipping_bar']);
    $this->assertNull($pages['header']['search']);           // theme decides
    $this->assertSame('Place order', $pages['checkout']['button_label']);
}

public function test_out_of_range_is_rejected()
{
    [, $error] = ThemeSchema::check(PageSettingFields::fields()['catalog.per_page'], 60, fn () => true);
    $this->assertNotNull($error);
}

// tests/Feature/Themes/PageSettingsStorageTest.php
public function test_page_settings_survive_theme_switch()  // save catalog.per_page=12 on the active theme, activate another installed theme, effectiveDesign of the new one has catalog.per_page === 12 and its colors unchanged
public function test_page_settings_reach_the_storefront()  // GET home: props storefront.theme.pages.catalog.per_page === 12
```

- [ ] **Step 2: Run them to verify they fail**

Run: `vendor/bin/phpunit --filter 'PageSettingFieldsTest|PageSettingsStorageTest'`
Expected: FAIL, "Class PageSettingFields not found".

- [ ] **Step 3: Implement `PageSettingFields`, the tab mapping, store-wide storage, `toProps` and the JSON schema entries** as listed under Files.

- **Storage:** `designOverrides()` returns the per-theme overrides without page keys, plus `theme.pages.design`. `saveDesign()` writes page keys there and everything else per theme.
- **Reset:** `reset()` keeps the store-wide page settings.

- [ ] **Step 4: Run them to verify they pass, plus the theme suites**

Run: `vendor/bin/phpunit --filter 'PageSettingFieldsTest|PageSettingsStorageTest|ThemeSchemaTest|Themes'`
Expected: PASS.

- [ ] **Step 5:** Check in the browser that `/admin/themes/{id}/customize?tab=checkout` shows "Checkout page" and Fonts and buttons is absent there (checkout fonts follow Cart). Then commit "Page settings: fields, tabs and store-wide storage".

---

### Task 2: Catalog page size and default sort

**Files:**

- Modify: `app/Http/Controllers/Storefront/CatalogController.php` (pairs; HEAD and working tree differ):
    - the three `listing(...)` calls pass `perPage: $pages['catalog']['per_page']`;
    - `sort()` falls back to `$pages['catalog']['default_sort']` instead of `'newest'`;
    - `$pages` comes from `PageSettingFields::resolve()` of the active theme's effective design (the same resolver `ShareStorefrontData` uses).
- Test: `tests/Feature/Storefront/CatalogPageSettingsTest.php`

**Interfaces:**

- **Consumes:** `PageSettingFields::resolve()`, `storefront.theme.pages` (Task 1).

- [ ] **Step 1: Write the failing tests**
    - `test_page_size_and_default_sort`: with 15 visible products and `catalog.per_page=12`, `catalog.default_sort=price_asc`, the search page gives `products.data` 12, `products.per_page` 12, `sort` `price_asc`, and the first item is the cheapest;
    - `test_visitor_sort_wins`: `?sort=name` gives `sort` `name`;
    - `test_page_two_keeps_sort_and_page_size`: `?page=2&sort=price_asc` gives 3 items, `current_page` 2.
- [ ] **Step 2: Run them:** `vendor/bin/phpunit --filter CatalogPageSettingsTest`. Expected: FAIL (24 per page, newest).
- [ ] **Step 3: Implement** as listed under Files.
- [ ] **Step 4: Run the tests and `vendor/bin/phpunit tests/Feature/Storefront`.** Expected: PASS.
- [ ] **Step 5: Commit** "Page settings: catalog page size and default sort".

---

### Task 3: Checkout field rules

**Files:**

- Create: `app/Domains/Checkout/CheckoutFieldRules.php`
- Modify: `app/Http/Controllers/Storefront/CheckoutController.php` (pair):
    - `store()` merges `CheckoutFieldRules::rules($pages['checkout'])` into the validation;
    - it passes the validated data through `CheckoutFieldRules::clean($validated, $pages['checkout'])` before building the order input;
    - `show()` passes `'pageCheckout' => $pages['checkout']` and `'termsUrl' => CheckoutFieldRules::termsUrl()`.
- Test: `tests/Feature/Checkout/CheckoutPageSettingsTest.php`

**Interfaces:**

- **Produces:**
    - `CheckoutFieldRules::rules(array $checkout): array`: `phone` becomes `['required', 'string', 'max:40']` when `required`; `terms` becomes `['accepted']` when `terms` is on;
    - `CheckoutFieldRules::clean(array $validated, array $checkout): array`: removes `phone` (hidden), `customer_note` (off), `shipping.company`/`billing.company` (off), `shipping.line2`/`billing.line2` (off);
    - `CheckoutFieldRules::termsUrl(): ?string`: the published page with slug `terms`, or null;
    - the terms error message is "Please accept the terms to place your order." (in `messages()` of the rules array).

- [ ] **Step 1: Write the failing tests** (use the existing checkout test helpers in `tests/Feature/Checkout` to build a cart and a valid payload):
    - `test_required_phone`: phone `required` + no phone gives a session error on `phone`, and the order count is unchanged;
    - `test_hidden_fields_are_not_stored`: phone `hidden`, company, line 2 and order note off, and the payload includes all four; the order has `phone` null, `customer_note` null, and the shipping address company and line2 empty;
    - `test_terms_must_be_accepted`: terms on + no `terms` gives an error with the exact message; with `terms=1` the order is placed;
    - `test_defaults_change_nothing`: no overrides, so the current checkout tests' payload still places an order with the phone stored.
- [ ] **Step 2: Run them:** `vendor/bin/phpunit --filter CheckoutPageSettingsTest`. Expected: FAIL.
- [ ] **Step 3: Implement** as listed under Files.
- [ ] **Step 4: Run the tests and `vendor/bin/phpunit tests/Feature/Checkout`.** Expected: PASS.
- [ ] **Step 5: Commit** "Page settings: checkout field rules".

---

### Task 4: Cart data (free shipping, cross-sells)

**Files:**

- Create: `app/Domains/Checkout/FreeShippingProgress.php`, `app/Domains/Catalog/CartCrossSells.php`
- Modify: `app/Http/Controllers/Storefront/CartController.php` (pair): `show()` adds `'freeShipping' => $pages['cart']['free_shipping_bar'] ? FreeShippingProgress::for(...) : null` and `'crossSells' => $pages['cart']['cross_sells'] ? CartCrossSells::for(...) : []`.
- Test: `tests/Feature/Storefront/CartPageSettingsTest.php`

**Interfaces:**

- **Produces:**
    - `FreeShippingProgress::for(PricedCart $cart, ?string $country): ?array{threshold: array{amount: string, formatted: string}, remaining: array{amount: string, formatted: string}}`:
        - uses the lowest `free_over` among active methods that ship to `$country` (any active method when `$country` is null);
        - remaining is never below zero;
        - null when there is no threshold;
        - amounts in the cart's display currency, using the converter the cart pricer uses;
    - `CartCrossSells::for(PricedCart $cart, int $limit = 4): list<array>`:
        - the related products of the cart lines' products, as product-card arrays (the same presenter the storefront listing uses);
        - excludes products already in the cart and products not visible;
        - de-duplicated, in relation order.

- [ ] **Step 1: Write the failing tests:**
    - `test_free_shipping_remaining`: a method with `free_over` 100 and a cart of 60 gives `freeShipping.remaining.amount` `"40.00"`;
    - `test_free_shipping_reached`: a cart of 120 gives remaining `"0.00"`;
    - `test_no_threshold_no_bar`: no method has `free_over`, so `freeShipping` is null;
    - `test_cross_sells_exclude_cart_items`: product A is related to B and C, the cart holds A and C, so `crossSells` lists only B;
    - `test_settings_off_send_nothing`: by default `freeShipping` is null and `crossSells` is `[]`.
- [ ] **Step 2: Run them:** `vendor/bin/phpunit --filter CartPageSettingsTest`. Expected: FAIL.
- [ ] **Step 3: Implement** as listed under Files.
- [ ] **Step 4: Run the tests and `vendor/bin/phpunit tests/Feature/Storefront tests/Feature/Checkout`.** Expected: PASS.
- [ ] **Step 5: Commit** "Page settings: free-shipping progress and cart cross-sells".

---

### Task 5: Storefront building blocks and the shared pages

**Files:**

- Create:
    - `resources/js/hooks/use-store-settings.ts`: `useStoreSettings(): PageSettings`, read from `storefront.theme.pages`, falling back to the defaults when the prop is missing;
    - `resources/js/components/storefront/page-settings/`:
        - `free-shipping-bar.tsx` (`FreeShippingBar({ data })`): texts "You're :amount away from free shipping" and "You've got free shipping!", plus a progress bar with `role="progressbar"`;
        - `product-gallery.tsx` (`ProductGallery({ images, thumbnails, zoom, alt })`): hover zoom on fine pointers, tap opens the image in a dialog on touch;
        - `description-panels.tsx` (`DescriptionPanels({ mode, sections })`): `open | tabs | accordion`; tabs use roles `tablist`/`tab`/`tabpanel` with arrow-key support;
        - `quick-add-button.tsx` (`QuickAddButton({ product })`): a single variant posts `cart.items.store` with quantity 1; otherwise it links to the product;
        - `cart-cross-sells.tsx`;
        - `stock-note.tsx` (`StockNote({ mode, quantity })`): "Only :count left" (low means at most 5) or "In stock";
        - `trust-badges.tsx`: "Secure payment", "Easy returns" and "Friendly support", with lucide icons;
        - `terms-checkbox.tsx` (`TermsCheckbox({ url, error })`): "I agree to the terms and conditions", linked when `url` is set;
    - `resources/css/page-settings.css`, imported from `resources/css/app.css` (one import line, pair):
        - rules for `[data-catalog-columns]`, `[data-catalog-columns-mobile]`, `[data-image-ratio]` and `[data-hover-image]` on `.storefront`;
        - header variables `--header-logo-height`, `--header-bg` and `--header-fg`, plus `[data-header-search]` and `[data-menu-align]`.
- Modify (pairs where HEAD has the anchor, working tree only otherwise; list which in the commit message):
    - `resources/js/layouts/storefront-layout.tsx`: data attributes and CSS variables on the `.storefront` root, from `useStoreSettings()`;
    - `resources/js/components/storefront/site-header.tsx` (shared compact and mobile bars): search bar or icon, cart icon `bag`/`cart` with count on or off, account link on or off;
    - `resources/js/pages/storefront/listing.tsx`: filters sidebar on or off;
    - `resources/js/components/storefront/product-card.tsx`: second image on hover (the product's 2nd image, when there is one), badges on or off, brand on or off, `QuickAddButton`;
    - `resources/js/pages/storefront/product.tsx`: `ProductGallery`, SKU, `StockNote`, `DescriptionPanels`, quantity selector on or off, related products on or off and limited to `related_count`, upsells on or off;
    - `resources/js/pages/storefront/cart.tsx`: `FreeShippingBar`, `CartCrossSells`, coupon from `cart.coupon` (the core field wins over theme `cart_show_coupon`), `TrustBadges`, continue-shopping link on or off;
    - `resources/js/pages/storefront/checkout.tsx`: summary side, sticky summary, field visibility, phone required marker, `TermsCheckbox`, `TrustBadges`, heading and button label, summary images, and a coupon field posting to `cart.coupon.apply` when `checkout.coupon` is on.
- Texts: run `php artisan ecomodular:storefront-strings`, then add es/fr/de for every new key (staged as HEAD plus only these keys).
- Test: `tests/Feature/Storefront/PageSettingsTranslationsTest.php`: each new key exists in es/fr/de. Browser checks on Aurora.

- [ ] **Step 1: Write the translations test.** Run it. Expected: FAIL (keys missing).
- [ ] **Step 2: Build the hook, components and CSS**, then wire the shared pages as listed under Files.
- [ ] **Step 3: Strings and translations.** Run the test. Expected: PASS.
- [ ] **Step 4: Gates** (types, vp check on new files, build).
- [ ] **Step 5: Browser on Aurora (activate it only through Preview; do not change the active theme).** For each tab, set two settings in the customizer, then check the storefront page:
    - catalog: columns 2 and 5, phone 1, portrait images, hover image, quick add;
    - product: thumbnails at the bottom, zoom, tabs, stock low;
    - cart: free-shipping bar, cross-sells;
    - checkout: phone required (error shown), terms, summary left;
    - header: search icon, menu centred;
    - then the catalog and product page at 375 px.

    Reset the settings afterwards.

- [ ] **Step 6: Commit** "Page settings: storefront building blocks and shared pages".

---

### Task 6: Own-page themes (working tree only)

**Files (working tree only):**

- `themes/modrixcommerce/{nord,voltora,vellaro,hearth}/src/{header,catalog,product,cart}.tsx` and `style.css`;
- `resources/js/components/storefront/atelier-chrome.tsx` and `electronics-chrome.tsx`.

**Interfaces:**

- **Consumes:** `useStoreSettings()` and the Task 5 components.

- [ ] **Step 1:** In each theme, use the hook and components for every setting:
    - header: search, menu alignment, cart icon and count, account link, logo height, colours;
    - catalog: columns, image ratio, hover image, quick add, badges, brand, filters;
    - product: gallery, zoom, SKU, stock, description mode, quantity, related and upsells;
    - cart: bar, cross-sells, coupon, trust, continue link.

    Each theme maps them onto its own class names in its `style.css`. Checkout is shared by all themes, so Task 5 already covers it.

- [ ] **Step 2: Gates** (types and build). The theme builds must pass.
- [ ] **Step 3: Browser:** in Nord and Voltora preview, check one setting per tab. Vellaro and Hearth share Nord's structure: check catalog columns and the product description mode there.
- [ ] **Step 4:** No commit; record the touched files for the final report.

---

### Task 7: Docs and review

- [ ] ADR-051 in `docs/architecture/decisions.md`:
    - store-wide page settings;
    - `theme.pages` resolved on the server;
    - data attributes for visual toggles;
    - own-page themes reuse the shared components;
    - the coupon ruling.
- [ ] CHANGELOG entry under `[Unreleased]`.
- [ ] Affected suites: `tests/Feature/Storefront`, `tests/Feature/Checkout`, `tests/Feature/Themes`, `tests/Unit`, `tests/Feature/Notifications`.
- [ ] Fresh reviewer on the most capable model, then one fix pass.
- [ ] Final report: the rulings, deferred minors, and the working-tree-only files.
