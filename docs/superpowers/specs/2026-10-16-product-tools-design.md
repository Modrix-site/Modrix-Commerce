# Product tools: duplicate, bulk actions, related products, digital products — design

- **Date:** 2026-10-16
- **Status:** Approved (sub-project 4 of the product and order management work)
- **Answers:**
    - digital products are files with secure links;
    - all four bulk groups;
    - related products are manual, with an automatic fallback;
    - duplicate copies everything, as a draft.

## Goal

Merchants can:

- duplicate a product;
- change many products at once;
- choose related products and upsells;
- sell downloadable files without shipping.

## 1. Duplicate

- **Where:** a "Duplicate" action in the product editor header and in the product list's row actions (`products.create`).
- **`DuplicateProduct` action:** runs in one transaction and returns the new product.
- **What is copied:**
    - name ("Copy of …"), descriptions, SEO fields, type, brand, featured flag, `is_digital`;
    - categories, tags and translations (names prefixed the same way);
    - images (same media, same order and alt text);
    - variants with option values, prices in every currency, and cost, weight and dimensions;
    - related products and upsells, and digital files (each file copied on the private disk).
- **The copy is a hidden draft:**
    - status is draft;
    - the slug is unique: `<slug>-copy`, `-copy-2`, …;
    - SKUs are unique: `<sku>-COPY`, `-COPY-2`, …, and an empty SKU stays empty;
    - stock is 0 (no stock rows);
    - barcodes are cleared, since they are unique per item.
- **Not copied:** reviews, flash-sale memberships, order history and stock movements.
- **Audit:** `product.duplicated` with the source and copy ids. The browser goes to the copy's editor.

## 2. Bulk actions (product list)

- **Selection:**
    - checkbox column with select-all on the page;
    - a sticky bulk bar like the orders list;
    - at most 200 products per action.
- **Endpoint:** `POST admin/products/bulk` (`ProductBulkController`) with `ids[]` and `action`, plus action-specific fields. One `ProductBulkActions` service does the work, and each action runs in a transaction.
- **Audit:** one `product.bulk_<action>` audit entry listing the ids.
- **Result flash:** "Updated N products." plus a list of skipped products with reasons.

| Action                         | Fields                                                           | Permission        | Rules                                                                                                                                                                                              |
| ------------------------------ | ---------------------------------------------------------------- | ----------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| publish / draft / archive      | none                                                             | `products.update` | Publishing skips products with no active priced variant (in the result).                                                                                                                           |
| add_category / remove_category | `category_id`                                                    | `products.update` |                                                                                                                                                                                                    |
| set_brand                      | `brand_id`, nullable to clear it                                 | `products.update` |                                                                                                                                                                                                    |
| add_tags / remove_tags         | `tags[]`                                                         | `products.update` | Tags are normalised; at most 20 per product, and products that would go over are skipped.                                                                                                          |
| feature / unfeature            | none                                                             | `products.update` |                                                                                                                                                                                                    |
| adjust_prices                  | `direction` (increase/decrease), `mode` (percent/fixed), `value` | `products.update` | Applies to every variant price in every currency, rounded to the currency. Never below 0.01; floored prices are listed. Percent: 0.01–1000, and a decrease is at most 99. Compare-at is unchanged. |
| set_stock                      | `quantity` (0–1 000 000)                                         | `products.update` | Tracked variants at the default location, via `InventoryService::setOnHand` (reason Adjustment). Digital and untracked variants are skipped.                                                       |
| delete                         | none                                                             | `products.delete` | Same rules as `DeleteProduct`. A product that cannot be deleted is listed with the error.                                                                                                          |
| export                         | none                                                             | `products.view`   | The existing product CSV export, limited to the ids.                                                                                                                                               |

## 3. Related products and upsells

- **Table `product_relations`:** `product_id`, `related_product_id`, `type` (`related` or `upsell`), `position`, timestamps.
    - Unique on (product, related, type).
    - Rows are deleted when either product is deleted.
    - A product cannot be related to itself.
- **Editor:** a "Related products" section with two searchable multi-pickers, Upsells and Related, at most 12 each, in order. They save with the product form (`related_ids[]`, `upsell_ids[]`).
- **Storefront product page:**
    - `upsells`: "Customers also bought", shown first;
    - `related`: "You may also like".
    - Both are cards for visible products only, in the chosen order.
    - When no related products are chosen, `related` falls back to same-category products, as today.

## 4. Digital products

- **Data:**
    - `products.is_digital` (bool, default false);
    - `product_files`: `product_id`, `name`, `disk` (`private`), `path`, `size`, `mime`, `position`, timestamps;
    - `download_grants`: `order_id`, `order_item_id`, `product_file_id`, `token` (40 random chars, unique), `downloads`, `max_downloads` (nullable = unlimited), `expires_at` (nullable), `revoked_at`, timestamps;
    - `download_logs`: `grant_id`, `ip`, `user_agent`, `created_at`.
- **Settings (Store → Digital downloads):**
    - `download_limit`: default 5, where 0 means unlimited;
    - `download_expiry_days`: default 30, where 0 means never.
- **Files:**
    - stored on a `private` disk (`storage/app/private/downloads`, never web-served);
    - at most 10 per product, 500 MB each;
    - uploaded from the editor's "Digital files" section, which shows only when `is_digital` is on;
    - each file can be renamed, deleted or reordered;
    - deleting a file removes its grants (customers lose that link).
- **Editor:** a "Digital product (no shipping)" switch. When it is on, weight and dimensions are hidden and stock tracking defaults to off.
- **Cart and checkout:**
    - `PricedCart::requiresShipping()` is true when any purchasable line's product is not digital;
    - when it is false, `ShippingStep` sets free "No shipping needed" (`digital` code, zero amount), and the checkout hides the shipping method choice;
    - the address form stays, for billing and tax;
    - `CheckoutController` makes `shipping_method` optional in that case, and `PlaceOrder` accepts it.
- **On payment (`OrderPaid` listener `GrantDownloads`, idempotent):**
    - one grant per digital order item and file, with limit and expiry from the settings at that moment;
    - a digital-only order then moves to Delivered through `OrderWorkflow` (no shipment).
- **Delivery:**
    - **Download links:** the paid email lists the links, and the order-complete page and Account → Order page list them with downloads left and expiry.
    - **Link:** `GET /downloads/{token}`, where the 40-char random token is the secret.
    - **Checks:** grant not revoked, not expired, under the limit, order still paid. Otherwise a friendly "link expired" page with status 410.
    - **Serving:** a successful download increments the count, writes a log entry, and streams the file with `Content-Disposition: attachment`.
    - **Rate limit:** 30 downloads per minute per IP.
- **Revocation:** cancelling or fully refunding an order sets `revoked_at` on its grants.
- **Admin order page:** a "Downloads" card lists each grant (file, used/limit, expiry, last download) with:
    - "Reset count";
    - "Extend 30 days";
    - "Revoke".

    These need `orders.update` and are audited.

## Testing

- **Duplicate:** a full copy, unique slug and SKU suffixes, draft status, no stock, private file copies.
- **Bulk actions:** each action, the permission per action, skipped products (publish without price, tag cap, delete with orders, stock on untracked), rounding and the price floor, the 200 cap.
- **Related products:** saving, limits, no self-relation, hidden products filtered out, category fallback.
- **Digital products:**
    - a digital-only checkout with no shipping method, and a mixed cart still needing one;
    - grants only after payment, with idempotent creation;
    - the digital-only order delivered automatically;
    - a download streams and is counted;
    - the limit, expiry and revoked cases return 410;
    - an unknown token returns 404;
    - refund and cancel revoke the grants;
    - the file is not under `public/`;
    - admin reset, extend and revoke, and their permissions.

## Out of scope

- license keys;
- file versioning and update notices;
- bundles;
- bulk actions on "all matching the filter";
- digital variants (digital is set per product).
