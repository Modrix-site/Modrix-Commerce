# Theme customizer tabs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** a tabbed theme customizer (General, Header, Homepage, Catalog, Product, Cart, Footer) with store-wide and per-page font, price and button styles.

**Architecture:**

- `CustomizerTabs` (PHP) puts every design field in a tab, and the customize controller sends it along.
- `ThemeStyleFields` adds the `style.{area}.{prop}` fields to the shared schema and writes CSS rules only for values a merchant set.
    - General rules are scoped to `.storefront`; page rules to `.storefront[data-page="{area}"]`.
    - Rules target core classes (`store-button`, `store-price`, `store-price-sale`) and the known theme classes.
    - So themes need no stylesheet edits, and an unset store looks exactly as before.
- The storefront layout marks `data-page`.
- The customizer page shows one tab at a time over a single form.

**Tech Stack:** Laravel 13 / PHP 8.4, Inertia React + TypeScript, PHPUnit (SQLite).

**Spec:** `docs/superpowers/specs/2026-10-16-theme-customizer-tabs-design.md`

## Global Constraints

- **Environment and gates:**
    - run `export PATH="/c/php84:$PATH"` first;
    - `vendor/bin/pint`, `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`, `npm run -s types:check`;
    - `npx vp check --fix` on new or committed-only files (never on the other developer's double-quoted files);
    - `php artisan wayfinder:generate --with-form` after route changes.
- **Commit only own changes:**
    - new files: `git add`;
    - shared tracked files: `scratchpad/pairs.py` (a separate HEAD pair set when HEAD's text differs);
    - untracked theme files are working tree only.
- **Tabs:** `general`, `header`, `home`, `catalog`, `product`, `cart`, `footer`. Labels: General, Header, Homepage, Catalog, Product, Cart, Footer.
- **Style areas:** `general`, `home`, `catalog`, `product`, `cart`.
    - **Props:** `heading_font`, `heading_size`, `heading_weight`, `heading_color`, `body_font`, `body_size`, `body_color`, `button_bg`, `button_text`, `button_hover_bg`, `button_font`, `button_size`, `button_radius`, `price_size`, `price_color`, `price_sale_color`.
    - **Size options (px, as strings):**
        - heading: 20 24 28 32 36 40 48 56 64;
        - body: 13 14 15 16 17 18;
        - button: 12 13 14 15 16 18;
        - price: 14 16 18 20 24 28.
    - **Weights:** 400 500 600 700 800. Radius uses `ThemeSchema::RADII`.
- **Group label:** "Fonts and buttons". A page area's fields go in that page's tab; `general` goes in General.
- **Tab by group name** (case-insensitive substring), in this order:
    1. hero, categor, promotion, collection, campaign, brand story, shortcut, assurance, homepage, home → home;
    2. catalog, listing, card → catalog;
    3. product → product;
    4. cart, checkout → cart;
    5. header, announcement, navigation, menu → header;
    6. footer, contact → footer;
    7. anything else → general.

    An explicit field `tab` (one of the seven) wins.

- **`data-page` mapping:**
    - `storefront/home` → home;
    - `storefront/listing` → catalog;
    - `storefront/product` → product;
    - `storefront/cart` and `storefront/checkout` → cart;
    - anything else → none.
- **Theme classes in the rules:**
    - buttons: `.store-button`, `.hearth-primary`, `.vellaro-primary`, `.vellaro-card-buy`, `.electronics-button`;
    - prices: `.store-price`, `.hearth-product-price`, `.vellaro-product-price`, `.electronics-purchase-price strong`, `.electronics-card-price strong`, `.ecom-price`;
    - sale prices: `.store-price-sale`.
- **Overrides:** merchant-set declarations use `!important` so they win over theme stylesheets; nothing is emitted for unset values.
- **Commits** end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Unset styles change nothing:** a store with no style values set must render byte-identical theme CSS (no new rules). Pinned in Task 2 (`test_no_style_values_emit_no_rules`).
2. **Page values win over General:** a catalog button colour applies on the listing even when General sets another. Pinned in Task 2 (`test_page_values_are_scoped_and_follow_general`).
3. **Injection through values:** only validated hex, listed options and font stacks reach the CSS; a crafted value is rejected on save. Pinned in Task 2 (`test_invalid_style_values_are_rejected`).
4. **Saving from one tab keeps the others' values:** the form holds every field, and the tab only filters the view. Pinned in Task 4 (types and a browser check of saving on the Catalog tab).
5. **Themes that hide core fields** (`hide_core_fields`) still get tabs and do not show the hidden fields. Pinned in Task 1 (`test_hidden_core_fields_are_not_sent`).

---

### Task 1: Tab assignment

**Files:**

- Create: `app/Core/Themes/CustomizerTabs.php`, `tests/Feature/Themes/CustomizerTabsTest.php`
- Modify (pair): `app/Http/Controllers/Admin/ThemeController.php`, adding `'tab' => CustomizerTabs::for($key, $field)` to each field in `customize()`.

**Interfaces:**

- **Produces:**
    - `CustomizerTabs::TABS` (key → label, in order);
    - `CustomizerTabs::for(string $key, array $field): string`. An explicit `$field['tab']` wins when valid. A `style.{area}.` key maps its area (`general` → general). Otherwise the group rules apply.

- [ ] **Step 1: Tests:**
    - `test_groups_map_to_tabs`, using the group names from Hearth, Vellaro and Marketplace;
    - `test_explicit_tab_wins`;
    - `test_style_fields_follow_their_area`;
    - `test_customize_page_sends_a_tab_per_field` (Inertia `design.fields.*.tab`);
    - `test_hidden_core_fields_are_not_sent`.
- [ ] **Step 2: RED.** **Step 3:** implement and pair the controller. **Step 4: GREEN** plus gates. **Step 5:** commit "Themes: customizer tabs for every setting".

### Task 2: Style fields and CSS

**Files:**

- Create: `app/Core/Themes/ThemeStyleFields.php`, `tests/Feature/Themes/ThemeStyleFieldsTest.php`
- Modify (pair): `app/Core/Themes/ThemeSchema.php`, so `designFields()` also returns `ThemeStyleFields::fields()`
- Modify (pair): `app/Core/Themes/ThemeCss.php`, so `build()` appends `ThemeStyleFields::css($design)`

**Interfaces:**

- **Produces:**
    - `ThemeStyleFields::AREAS`;
    - `ThemeStyleFields::fields(): array<string, array>`, with types font, select or color, group "Fonts and buttons", and a `tab` set from the area;
    - `ThemeStyleFields::css(array $design): string`, empty when no `style.*` value is set.
- **Rule order:** general first, then each page area, so pages override by specificity (`.storefront[data-page=x]` is more specific than `.storefront`).
- **Headings:** `h1` gets the size, `h2` gets `calc(size*0.75)`, `h3` gets `calc(size*0.6)`.
- **Fonts:** resolved through `ThemeSchema::FONT_STACKS` or `"custom:Family"` → `"Family"`, using the same helper logic as `ThemeCss::font`.

- [ ] **Step 1: Tests:**
    - `test_no_style_values_emit_no_rules`;
    - `test_general_values_apply_store_wide`;
    - `test_page_values_are_scoped_and_follow_general`;
    - `test_invalid_style_values_are_rejected` (through `ThemeCustomizations::saveDesign` errors);
    - `test_style_css_reaches_the_storefront` (the `ResolvedTheme::css()` output contains the rule).
- [ ] **Step 2: RED.** **Step 3:** implement and pair. **Step 4: GREEN** plus the Themes suite. **Step 5:** commit "Themes: per-page fonts, prices and buttons".

### Task 3: Storefront wiring

**Files:**

- Modify (pair, separate working-tree and HEAD sets): `resources/js/layouts/storefront-layout.tsx`, adding `data-page` on the `.storefront` root from `usePage().component`.
- Modify (committed): add `store-button` and `store-price` / `store-price-sale` classes to:
    - the default product page add-to-cart button and price;
    - the default product card price and compare-at;
    - `StickyAddToCart`, `BuyNowButton` and cart and checkout primary buttons where they live in committed files.

- [ ] **Step 1:** a test that the layout's `data-page` attribute follows the page. It is a TypeScript mapping, so it is checked by types plus the browser in Task 5; the ruling is noted in the ledger.
- [ ] **Step 2:** implement. **Step 3:** types, lint and build. **Step 4:** commit "Storefront: page markers and style classes for theme styles".

### Task 4: Tabbed customizer

**Files:**

- Create: `resources/js/components/admin/themes/customizer-tabs.tsx` (the tab bar with an error dot and a customised count per tab)
- Modify (pair, separate working-tree and HEAD sets): `resources/js/pages/admin/themes/customize.tsx`
    - **Tab state:** lives on the page, from `?tab=` (default `general`) and kept in the URL with `history.replaceState`.
    - **`DesignCard`:** shows only the active tab's groups. In a page tab the "Fonts and buttons" group comes first. The form still holds every field.
    - **`SectionsCard`:** only on `home`.
    - **`ImageLibrary` and `ResetCard`:** only on `general`.
    - **Empty tabs** are hidden.
    - **Tab marks:** a tab with an error from the last save shows a dot; customised counts per tab.

- [ ] **Step 1:** types plus a browser check (Task 5), since there are no React unit tests in this project. **Step 2:** implement. **Step 3:** gates. **Step 4:** commit "Themes: tabbed customizer".

### Task 5: Docs, browser check and review

- [ ] **ADR-049 and the CHANGELOG** ("Customizer tabs and per-page styles").
- [ ] **Browser:**
    - open the customizer for Hearth and Marketplace and step through each tab;
    - set a Catalog button colour and a Product heading size, then save;
    - check the listing and product pages show them, and that other pages do not;
    - clear them and check the theme is back to its original look.
- [ ] **Fresh reviewer** on the range, then one fix pass. Commit "Docs: customizer tabs (ADR-049)".
