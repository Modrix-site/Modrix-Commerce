# Product editor redesign and new product options — design

- **Date:** 2026-10-13
- **Status:** Approved ("Approve and build")
- **Sub-project:** 3 of 4

## Goal

Merchants edit everything about a product on one clear page and manage the details they asked for:

- cost and margin;
- dimensions;
- brand;
- tags;
- a featured flag.

Compare-at price, weight, low-stock threshold and scheduled publishing already exist; they move into the new layout.

## Decisions (approved)

- **Layout:** one page with a sticky section menu and a sticky save bar. The save bar shows unsaved changes and warns before leaving the page.
- **Brands:** a managed list (Admin → Catalog → Brands: name, slug, description, logo).
    - One brand per product.
    - The product list filters by brand.
    - The product page shows the brand, linking to a brand page `/b/{slug}` that lists the brand's products.
- **Tags:** free tags with autocomplete, used for the admin filter and storefront search. They are not displayed on the storefront.

## Data

- **`brands` table:**
    - `id`, `name` string(120), `slug` string(140) unique, `description` text nullable;
    - `logo_media_id` FK media nullable, nullOnDelete;
    - timestamps.
    - Deleting a brand sets its products' `brand_id` to null.
- **`products` gains:**
    - `brand_id` (nullable FK, nullOnDelete, indexed);
    - `is_featured` (bool, default false, indexed).
- **`product_tags` table:** `product_id` (FK cascade) and `tag` string(60), with primary key (`product_id`, `tag`) and an index on `tag`.
    - Tags are trimmed, whitespace is collapsed, and they are stored lowercase.
    - At most 20 per product.
- **`product_variants` gains:**
    - `cost` decimal(19,4), nullable, in the store currency, admin-only;
    - `length`, `width`, `height` decimal(10,2), nullable, in the store dimension unit.

## Rules

- **Cost:** ≥ 0, money format. Margin is shown as `(price − cost) / price`, and only when both are set and the price is above 0.
- **Dimensions:** ≥ 0 and ≤ 100000.
- **Brand:** must exist.
- **Tags:** each 1–60 characters after normalising, at most 20, duplicates removed.
- **Permissions:** brand management needs `products.update`, and so do editing a product's brand, tags and featured flag.

## Admin

- **Product editor** (`resources/js/pages/admin/products/form.tsx` rewritten, split into components under `components/admin/products/`).
    - **Section menu:** General, Media, Pricing & inventory or Variants, Shipping, Search listing.
    - **Sidebar:** Status & visibility (status, publish at, featured, type on create) and Organisation (categories, brand, tags).
    - **Variants:** a compact table (options, SKU, price, stock, active) with an expandable details row (compare-at price, cost and margin, barcode, low-stock alert, inventory flags).
    - **Shipping section:** weight and dimensions for each variant.
    - **Media:** the existing `ProductImages` component, moved inside the page. On create the section says images can be added after saving.
    - **Save bar:**
        - fixed to the bottom of the content;
        - shows "Unsaved changes" when the form is dirty;
        - has Save and Discard;
        - warns on `beforeunload` and on Inertia navigation while dirty.
    - **Header:** View on store (published products), Translations and Delete.
- **Product list:** brand, tag and featured filters, plus a brand column.
- **Brands page** (`admin/brands`): a list with product counts; create and edit in a dialog (name, slug, description, logo from the media library); delete with confirmation.

## Storefront

- **Product page:** shows "by {brand}" linking to `/b/{slug}`.
- **Brand page:** `/b/{slug}` is a listing like a category page (heading, description, logo), with 404 for an unknown slug.
- **Search:** matches tags exactly (normalised lowercase), as well as name, description and SKU.
- **Home "Featured products" section:** the `source` option gains `featured` (products with `is_featured`, newest first).

## Testing

- **Saving products:**
    - brand, tags (normalised, deduplicated, limit), featured, cost, dimensions;
    - validation errors;
    - edit props include brand options, tag suggestions, cost and dimensions.
- **Brands:** create, update and delete (products keep existing with no brand); slug unique; permission.
- **Product list:** brand, tag and featured filters.
- **Storefront:**
    - the product page shows the brand;
    - the brand page lists only visible products, and an unknown slug gives 404;
    - search finds a product by tag;
    - the home featured source.
- **Browser check:** edit a product (dirty warning, margin, save).
