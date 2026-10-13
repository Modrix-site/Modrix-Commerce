# Product Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:**
- Brands, tags, a featured flag, cost and dimensions on products.
- A redesigned single-page product editor.
- A brand page and tag search on the storefront.

**Architecture:**
- **Data:** a migration adds the brands and product_tags tables and the new columns.
- **Saving:** `SaveProduct` and `SaveProductRequest` are extended.
- **Brands:** a `Brand` model and `BrandController`.
- **Storefront:** `StorefrontCatalog` gains a brand listing and tag search.
- **Admin UI:** React components under `components/admin/products/`.

**Spec:** `docs/superpowers/specs/2026-10-13-product-editor-design.md`

## Global Constraints

- **Gates:**
  - pint
  - phpstan (0 errors)
  - `check:fix`
  - `types:check`
  - `build` (PHP 8.4 on PATH)
  - full `php artisan test`
- **Tags:** stored lowercase and trimmed with whitespace collapsed; max 20 per product, max 60 characters each.
- **Permissions:** brand and product edits need `products.update`; the brand page is public.
- **Cost is never exposed on the storefront** (presenters and JSON).

## Review Focus

1. **Cost leaking to the storefront** (product page props, cards, JSON): test asserts no `cost` key. Owner: Task 4.
2. **A deleted brand** must leave its products editable and listed. Owner: Task 2.
3. **Tags containing SQL wildcards** (`%`, `_`) must match literally in search and filter. Owner: Task 4.
4. **A draft product of a brand** must not be listed on the brand page. Owner: Task 4.
5. **The dirty-form warning** must not fire after a successful save. Owner: Task 5 (browser check).

---

### Task 1: Data and saving

- **Code:**
  - migration `2026_10_22_000001_add_brands_tags_and_product_details.php`;
  - `Brand` model;
  - `Product::brand()` and `Product::tags()` (a HasMany of `ProductTag`), `tagNames()`;
  - casts;
  - `SaveProduct` handles `brand_id`, `is_featured`, `tags` and variant `cost`, `length`, `width`, `height`;
  - `SaveProductRequest` rules;
  - the controller edit props expose the new fields, plus `brands` options and `tagSuggestions` (the 200 most used).
- **Tests:** `tests/Feature/Catalog/ProductDetailsTest.php`.

### Task 2: Brands admin

- **Code:**
  - `BrandController` (index, store, update, destroy) under `products.update`;
  - routes `admin/brands`;
  - page `admin/brands/index.tsx`;
  - a navigation entry (Catalog);
  - audit entries `brand.created`, `brand.updated` and `brand.deleted`.
- **Tests:** `tests/Feature/Catalog/BrandAdminTest.php`.

### Task 3: Product list filters

- **Changes:** brand, tag and featured filters, plus a brand column, in `ProductController::index` and the list page.
- **Tests:** add to `ProductDetailsTest`.

### Task 4: Storefront

- **Code:**
  - brand on the product page;
  - route `/b/{slug}` → `CatalogController::brand` using `StorefrontCatalog::listing(... brand)`;
  - the search provider matches tags;
  - the home featured source;
  - storefront strings and translations.
- **Tests:** `tests/Feature/Storefront/BrandAndTagsTest.php`, covering Review Focus 1, 3 and 4.

### Task 5: Editor redesign

- **Changes:** rewrite `form.tsx` into components (section nav, sidebar, variants table, shipping, save bar with dirty guard).
- **Browser check:** create, edit, margin, dirty warning, save.

### Task 6: Docs

- ADR-046.
- CHANGELOG.
- Schema notes.
