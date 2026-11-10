# Extension Blocks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:**

- Extensions show blocks in more storefront places.
- Merchants choose, per customizer tab, each block's on/off, place and order.
- Extensions put settings into customizer tabs.

**Architecture:**

- `StorefrontSlots` gains movable places and `block()` registrations; existing `register()` becomes a one-place block.
- A store-wide `BlockPlacements` setting decides where and whether each block renders.
- The customizer gets two cards per tab ("Extension blocks", "Extension settings") backed by two endpoints.
- Pages and themes render the new places with `ExtensionSlot`.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia React, TypeScript, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-17-extension-blocks-design.md`

## Global Constraints

- **Places:** keys, labels, tabs and contexts exactly as in spec §1. Fixed places are `storefront.overlay`, `checkout.payment_method`, `order.confirmation` and `admin.product.sidebar`; they are never movable.
- **Storage:**
    - placements in the setting `extensions.blocks`, shaped `{ "<module>:<key>": { place, order (0–999), enabled } }`, store-wide;
    - module tab settings are stored through `ModuleSettingsSchema::save()`.
- **Compatibility:**
    - `register($slot, $module, $component, $props, $order)` keeps its signature and behaviour;
    - with no saved placements, every page renders exactly as before.
- **Permission:** both new endpoints sit in the `can:themes.manage` route group.
- **Ownership:**
    - commit only this plan's changes: new files with `git add`; shared tracked files as recorded pairs (`scratchpad/pairs.py`), with separate HEAD pairs when HEAD differs;
    - anchors that exist only in the other developer's working-tree code stay working-tree only and are listed in the report. This includes untracked modules (Tabby, Tamara, Frequently bought together), the own-page themes, and the Atelier/Electronics pieces.
    - pair files use the `xb_` prefix.
- **Gates:**
    - `export PATH="/c/php84:$PATH"`;
    - pint;
    - `phpstan analyse --memory-limit=1G --no-progress` (no new errors in this plan's files);
    - `npm run -s types:check`;
    - `npx vp check --fix` on new files;
    - `npm run -s build`;
    - `php artisan wayfinder:generate --with-form`.
- **Texts:** new storefront texts go in the storefront catalogue with es/fr/de. Admin texts are English.
- **Commit trailer:** `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **A saved placement for a block whose module was then disabled or uninstalled:** no error, nothing renders, and the entry is kept. Pinned in Task 1 (`test_unknown_block_entries_are_ignored`).
2. **A saved place the block no longer lists** (module update): the block renders at its default. Pinned in Task 1 (`test_unsupported_saved_place_falls_back`).
3. **Saving blocks with a forged id or place, or an order out of range:** a validation error, and nothing is stored. Pinned in Task 2 (`test_save_rejects_unknown_block_or_place`).
4. **A tab setting saved from the customizer:** the module's other settings are unchanged. Pinned in Task 2 (`test_tab_settings_keep_other_module_settings`).
5. **A listing with 24 cards:** no per-card queries for `product.card`. Pinned in Task 4 (`test_card_blocks_are_shared_once`).

---

### Task 1: Places, blocks and placement

**Files:**
- Modify: `app/Core/Extensions/StorefrontSlots.php` (pairs; HEAD's `SLOTS` list is shorter, so it gets its own SLOTS pair):
    - new places in `SLOTS`;
    - `PLACES` (movable place → `['label' => …, 'tab' => …]`);
    - `block()`;
    - `blocks(): list<array{id, module, key, label, component, places, default}>`;
    - `render()` honours placements.
- Create: `app/Core/Extensions/BlockPlacements.php`:
    - `KEY = 'extensions.blocks'`;
    - `all(): array`;
    - `for(string $id): ?array`;
    - `save(array $input, StorefrontSlots $slots): void`, which throws `ValidationException`.
- Test: `tests/Feature/Extensions/StorefrontBlocksTest.php`

**Interfaces:**
- **Produces:**
    - `StorefrontSlots::block(string $module, string $key, string $label, string $component, array $places, string $default, Closure $props, int $order = 100): void`;
    - `StorefrontSlots::blocks(): list<array{id: string, module: string, key: string, label: string, component: string, places: list<string>, default: string}>`;
    - `StorefrontSlots::PLACES`;
    - `render(string $place, array $context)` (unchanged signature): passes `place` in the context, applies placements, and keeps fixed places as today.
    - Block id is `"{$module}:{$key}"`. `register()` creates a block with key = component name (plus `@{$slot}` when the same component is registered in several slots), label = component name, places = [$slot], default = $slot.

- [ ] **Step 1: Failing tests:**
    - `test_block_renders_at_its_default_place`;
    - `test_moved_block_renders_only_at_its_new_place`;
    - `test_disabled_block_does_not_render`;
    - `test_saved_order_wins`;
    - `test_unsupported_saved_place_falls_back`;
    - `test_unknown_block_entries_are_ignored`;
    - `test_block_rejects_unknown_or_fixed_places` (an unknown place, a fixed place, or a default not in the list throws `InvalidArgumentException`);
    - `test_register_still_works_and_is_listed_as_a_block`;
    - `test_failing_block_hides_alone`.
- [ ] **Step 2:** Run `vendor/bin/phpunit --filter StorefrontBlocksTest`. Expected: FAIL (`block()` undefined).
- [ ] **Step 3: Implement** as listed under Files.
- [ ] **Step 4:** Run the tests plus `tests/Feature/Extensions`. Expected: PASS.
- [ ] **Step 5: Commit** "Extension blocks: places, blocks and placement".

---

### Task 2: Endpoints and customizer data

**Files:**
- Create:
    - `app/Http/Controllers/Admin/ExtensionBlocksController.php`: `update(Request)`, at `PUT admin/themes/blocks`, named `themes.blocks.update`;
    - `app/Http/Controllers/Admin/ExtensionTabSettingsController.php`: `update(Request, string $module)`, at `PUT admin/themes/extension-settings/{module}`, where `module` is `vendor/name` with a `/` in it, named `themes.extension-settings.update`;
    - `app/Core/Extensions/TabSettings.php`: `forTab(string $tab): list<array{module: string, title: string, fields: list<array>, values: array}>` (enabled modules only), and `save(string $module, array $input): void`, which validates only that module's tab fields, merges them over the current values, then calls `ModuleSettingsSchema::save`.
- Modify:
    - `routes/admin.php` (pair) inside the `can:themes.manage` group;
    - `ThemeController::customize` (pair): props `blocks` (for every block: `{id, module, label, places: [{key, label, tab}], default, place, order, enabled}`) and `extensionSettings` (`{tab: TabSettings::forTab(tab)}` for each customizer tab);
    - `ManifestValidator` (pair): a setting's `tab`, when present, must be one of `CustomizerTabs::TABS`.
- Test: `tests/Feature/Extensions/ExtensionBlocksAdminTest.php`

- [ ] **Step 1: Failing tests:**
    - `test_customize_page_lists_blocks_and_tab_settings`;
    - `test_save_blocks_stores_store_wide`;
    - `test_save_rejects_unknown_block_or_place`;
    - `test_tab_settings_keep_other_module_settings`;
    - `test_invalid_tab_in_manifest_is_rejected`;
    - `test_customers_cannot_save_blocks` (403).
- [ ] **Step 2:** Run. Expected: FAIL.
- [ ] **Step 3: Implement**, then run wayfinder.
- [ ] **Step 4:** Run the tests plus `tests/Feature/Themes` and `tests/Feature/Extensions`. Expected: PASS.
- [ ] **Step 5: Commit** "Extension blocks: admin endpoints and customizer data".

---

### Task 3: Customizer UI

**Files:**
- Create:
    - `resources/js/components/admin/themes/extension-blocks-card.tsx`: rows with name and module, an on/off `Switch`, a place `Select` (labels, plus "(Homepage)"-style tab suffix for other pages), up/down buttons, and "Save blocks" (`useForm` PUT);
    - `resources/js/components/admin/themes/extension-settings-card.tsx`: groups by module; fields rendered by type (boolean, string, text, integer, select, email), with one save per module.
- Modify: `resources/js/pages/admin/themes/customize.tsx` (pair where HEAD has the anchor): render both cards for the active tab under the design card, kept mounted (hidden) like the others.
- [ ] **Steps:** build the two cards; check them in the browser (move a block, turn one off, save a tab setting, reload and see it persisted); run the gates; commit "Extension blocks: customizer cards".

---

### Task 4: Storefront places

**Files:**
- Modify (committed where HEAD has the anchor, otherwise working tree only):
    - `HandleInertiaRequests` or `ShareStorefrontData` (pair): shared `storefront.cardBlocks` = `render('product.card', [])` (lazy);
    - `ProductController::show`, `CatalogController` (listing), `CartController::show` and `CheckoutController::show` (pairs): props `placeSlots` = `{place: render(place, context)}` for that page's movable places;
    - shared pages:
        - `site-header.tsx` and the layout's account links: `header.actions`;
        - `listing.tsx`: `catalog.before_products` and `catalog.after_products` (the latter exists);
        - `product-card.tsx`: `product.card` with `extraProps={{ product }}`;
        - `product.tsx`: `product.after_price`, `product.after_add_to_cart` and `product.after_description`;
        - `cart.tsx`: `cart.after_lines`;
        - `checkout.tsx`: `checkout.before_place_order`;
    - own-page themes and the Atelier/Electronics pieces: the same places (working tree only).
- Test: `tests/Feature/Extensions/StorefrontPlacesTest.php`:
    - `test_product_page_sends_its_places`;
    - `test_card_blocks_are_shared_once` (query count independent of 24 vs 4 products);
    - `test_cart_and_checkout_send_their_places`.
- [ ] **Steps:** RED, implement, GREEN; browser check in Aurora and Voltora with a test block; gates; commit "Extension blocks: storefront places".

---

### Task 5: Bundled modules

**Files (each module's provider and `module.json`; committed only for files that are committed today, the rest working tree only):**
- Product reviews: `block('modrixcommerce/product-reviews', 'reviews', 'Product reviews', 'ProductReviews', ['product.after_details', 'product.after_description'], 'product.after_details', …)`, plus the setting `show_summary` (boolean, default true, `tab: product`).
- Tabby and Tamara: `promo` blocks for the product page `['product.after_price', 'product.after_add_to_cart']`, defaulting to their current place (`product.after_details` stays allowed and remains the default), plus `show_product_promo` (`tab: product`). The cart promo stays as registered.
- Frequently bought together: `['product.after_details', 'product.after_add_to_cart']`, plus `limit` (integer, `tab: product`) where a matching setting exists; otherwise add one.
- Newsletter: the sign-up block `['home.after_featured', 'storefront.footer']`. Today it is registered in both, so it becomes two blocks, keeping both behaviours, plus `show_signup` (`tab: home`).
- Flash sales: banner `['home.after_featured', 'catalog.before_products']`.
- Test: extend `StorefrontBlocksTest` with `test_bundled_modules_declare_their_blocks` (with the platform's modules enabled).
- [ ] **Steps:** RED, implement, GREEN; affected module suites; commit "Extension blocks: bundled modules".

---

### Task 6: Docs and review

- [ ] ADR-052, the CHANGELOG, and `docs/extensions` (module author docs: `block()`, places, and `tab` on settings).
- [ ] Affected suites:
    - `tests/Feature/Extensions`;
    - `Themes`;
    - `Storefront`;
    - `Checkout`;
    - the module tests.
- [ ] Fresh reviewer on the most capable model, then one fix pass; final report.
