# Product tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Merchants can duplicate products, change many products at once, pick related products and upsells, and sell downloadable files without shipping.

**Architecture:**

- Everything is core: there is no module.
- **Related products:** a `product_relations` table and a `ProductRelations` service.
- **Digital products:**
    - a product flag plus `product_files` on the private disk;
    - `PricedCart::requiresShipping()` turns off shipping for all-digital carts;
    - an `OrderPaid` listener creates `download_grants`, which a token route (`/downloads/{token}`) streams.
- **Bulk actions:** one `ProductBulkActions` service behind one endpoint.
- **Duplicate:** a `DuplicateProduct` action that reuses all of the above.

**Tech Stack:** Laravel 13, PHP 8.4, Inertia + React (TypeScript), PHPUnit feature tests (SQLite), Wayfinder routes.

**Spec:** `docs/superpowers/specs/2026-10-16-product-tools-design.md`

## Global Constraints

- **Environment:** run `export PATH="/c/php84:$PATH"` first.
- **Gates for every task:**
    - `vendor/bin/pint`;
    - `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`;
    - `npm run -s types:check`;
    - `npx vp check --fix <changed js files>`, which uses single quotes;
    - `php artisan wayfinder:generate --with-form` after route changes;
    - the task's `vendor/bin/phpunit --filter`.
- **Commit only your own changes:** the working tree has ~250 uncommitted changes by another developer.
    - New files: `git add`.
    - Shared tracked files: record edits as old→new pairs with `scratchpad/pairs.py . apply <pairfile>`, then `python scratchpad/pairs.py . stage`.
    - Start a fresh `pairs-ledger.json` per commit: move the old one aside after committing.
    - Untracked files from the other developer (e.g. `themes/modrixcommerce/hearth/src/*`, `electronics-*.tsx`, `atelier-card.tsx`) cannot be committed. Only edit them if needed, and report it.
- **Private files:** use the `local` disk (root `storage/app/private`) under `downloads/{product_id}/`, never `public`.
- **Limits from the spec:**
    - at most 200 products per bulk action;
    - at most 12 related products and 12 upsells;
    - at most 10 files per product, 500 MB (512000 KB) each;
    - percent adjustments 0.01–1000, with a decrease at most 99;
    - the price floor is 0.01;
    - stock 0–1 000 000;
    - at most 20 tags per product;
    - download limit default 5 (0 = unlimited);
    - expiry default 30 days (0 = never);
    - the download token is 40 random characters;
    - at most 30 downloads per minute per IP;
    - "Extend" adds 30 days.
- **Copy:**
    - duplicates are named "Copy of {name}";
    - slugs `{slug}-copy`, `-copy-2`, …; SKUs `{SKU}-COPY`, `-COPY-2`, …;
    - the shipping option label is "No shipping needed" with code `digital`;
    - section headings are "Customers also bought" (upsells) and "You may also like" (related).
- **Audit actions:**
    - `product.duplicated`;
    - `product.bulk_<action>`;
    - `download.reset`, `download.extended`, `download.revoked`.
- **Commits** end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Duplicate SKU collisions:** a product whose `-COPY` SKU already exists, e.g. duplicating twice, must get `-COPY-2` and never hit a unique-constraint 500. Pinned in Task 7 (`test_duplicating_twice_gets_unique_slug_and_skus`).
2. **A product file deleted after purchase:** the customer's link shows the "no longer available" 410 page, not a 500. Pinned in Task 4 (`test_a_deleted_file_gives_410_not_500`).
3. **Mixed cart after removing the physical item:** the checkout must switch to "No shipping needed" without stale shipping costs. Pinned in Task 3 (`test_removing_the_last_physical_item_drops_shipping`).
4. **A bulk price decrease below the floor in a zero-decimal currency** (e.g. JPY): it must floor at the currency's minimum and be listed, not crash on rounding. Pinned in Task 6 (`test_price_decrease_floors_and_lists_products`, run with a second currency).
5. **Download by the wrong customer while signed in:** the token alone grants access (by design, so guests can download), but a revoked or refunded order must never stream. Pinned in Task 4 (`test_refund_revokes_links`).

---

### Task 1: Related products and upsells

**Files:**

- Create: `database/migrations/2026_10_26_000001_create_product_relations_table.php`
- Create: `app/Domains/Catalog/ProductRelations.php`
- Modify:
    - `app/Domains/Catalog/Models/Product.php` (relations);
    - `app/Http/Requests/Admin/SaveProductRequest.php` (rules);
    - `app/Domains/Catalog/Actions/SaveProduct.php` (sync);
    - `app/Http/Controllers/Admin/Catalog/ProductController.php` (`edit` props and a `relatedSearch` JSON action);
    - `routes/admin.php`;
    - `resources/js/pages/admin/products/form.tsx` (new section "Related products");
    - `app/Http/Controllers/Storefront/ProductController.php` (`upsells` and `related` props);
    - `resources/js/pages/storefront/product.tsx` (render upsells above related).
- Create: `resources/js/components/admin/products/product-picker.tsx`
- Test: `tests/Feature/Catalog/ProductRelationsTest.php`

**Interfaces:**

- **Produces:**
    - table `product_relations(id, product_id FK cascade, related_product_id FK cascade, type string(16), position unsigned small int, timestamps, unique(product_id, related_product_id, type))`;
    - `Product::relatedProducts(): BelongsToMany` and `Product::upsellProducts(): BelongsToMany`, both on `product_relations`, filtered by type and ordered by `position`;
    - `ProductRelations::sync(Product $product, list<int> $relatedIds, list<int> $upsellIds): void` drops self-ids and duplicates and keeps the given order;
    - `ProductRelations::TYPES = ['related', 'upsell']`, `ProductRelations::MAX = 12`;
    - request fields `related_ids` and `upsell_ids` (arrays of existing product ids, max 12);
    - admin route `GET admin/products/related-search?q=` named `admin.products.related-search` (`products.update`), returning `[{id, name, sku, image}]` (max 20);
    - storefront props `upsells: ProductCard[]` and `related: ProductCard[]`, from `StorefrontCatalog::cardsFor(ids, currency, 12)`.
- **Consumes:** `StorefrontCatalog::cardsFor(list<int>, string, int)` (it exists) and the existing category fallback in the storefront `ProductController`.

- [ ] **Step 1: Write the failing tests** in `ProductRelationsTest`:
    - `test_editor_saves_related_and_upsells_in_order`: PUT the product with `related_ids=[c,b]` and `upsell_ids=[d]`; assert `relatedProducts->pluck('id') == [c,b]` and `upsellProducts == [d]`.
    - `test_self_and_limits_are_rejected`: 13 ids fails with an error on `related_ids`; the product's own id is silently dropped.
    - `test_product_page_shows_upsells_then_related_and_hides_invisible`: a draft related product is not in `related`; `upsells.0.id == d`.
    - `test_related_falls_back_to_category_when_none_chosen`: there is no relation and the same-category product appears in `related`.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter ProductRelationsTest`. Expected: FAIL (table missing).
- [ ] **Step 3: Implement** the migration, model relations, `ProductRelations::sync` (delete and insert in a transaction), request rules (`related_ids.*` and `upsell_ids.*`: `integer|exists:products,id`), and the `SaveProduct::handle` call when the keys are present.
    - The edit props: `related` and `upsells` as `[{id, name, sku, image}]`.
    - The search action.
    - Storefront props: upsells and related come from the chosen ids. Related falls back to the existing category query when empty.
- [ ] **Step 4: UI.** `ProductPicker` props: `{label, value: PickerItem[], onChange, searchUrl, max}`. It is a debounced search plus a removable, re-orderable chip list using up and down buttons.
    - Add a "Related products" section to the editor's section nav with two pickers that write `related_ids` and `upsell_ids` into the form.
    - On the storefront product page, render "Customers also bought" (upsells) above the existing related grid, now titled "You may also like".
- [ ] **Step 5: Run** the tests and the gates. Expected: PASS.
- [ ] **Step 6: Commit** "Catalog: related products and upsells".

### Task 2: Digital product flag and files (admin)

**Files:**

- Create:
    - `database/migrations/2026_10_26_000002_add_digital_products.php`, which adds `products.is_digital` bool default false and creates `product_files`;
    - `app/Domains/Catalog/Models/ProductFile.php`;
    - `app/Http/Controllers/Admin/Catalog/ProductFileController.php`;
    - `resources/js/components/admin/products/digital-files.tsx`.
- Modify:
    - `Product.php` (`is_digital` cast and `files(): HasMany` ordered by position);
    - `SaveProductRequest` and `SaveProduct` (`is_digital` boolean);
    - admin `ProductController::edit` (props `product.is_digital` and `files`);
    - `routes/admin.php`;
    - `form.tsx` (a "Digital product (no shipping)" switch; hide weight and dimensions when it is on; a "Digital files" section when it is on).
- Test: `tests/Feature/Catalog/ProductFilesTest.php`

**Interfaces:**

- **Produces:**
    - `product_files(id, product_id FK cascade, name string(160), disk string(32), path string, size unsigned big int, mime string(120) nullable, position unsigned small int, timestamps)`;
    - `ProductFile` with `deleting` hook that removes the stored file;
    - routes, all `products.update`:
        - `POST admin/products/{product}/files` (`admin.products.files.store`, multipart `file`, `max:512000`);
        - `PUT admin/products/{product}/files/{file}` (`.update`: `name`, `position`);
        - `DELETE admin/products/{product}/files/{file}` (`.destroy`), `scopeBindings`;
    - `ProductFile::MAX_PER_PRODUCT = 10`.
    - Files are stored with `Storage::disk('local')->putFile("downloads/{$product->id}", $upload)`.
- **Consumes:** nothing new.

- [ ] **Step 1: Failing tests:**
    - `test_files_upload_to_private_disk_only`: `Storage::fake('local')`; upload; `Storage::disk('local')->assertExists($file->path)`, the path starts with `downloads/`, and `public_path()` has no such file.
    - `test_eleventh_file_is_rejected`: errors on `file`.
    - `test_rename_reorder_delete_removes_stored_file`.
    - `test_files_need_products_update`: a role without it gets 403.
    - `test_digital_flag_saves`.
- [ ] **Step 2: Run** the tests. Expected: FAIL.
- [ ] **Step 3: Implement** the migration, model, controller and request changes. A digital product saved without `track_inventory` input defaults its variants to untracked.
- [ ] **Step 4: UI:** `DigitalFiles` props `{productId, files: {id,name,size,mime}[]}`.
    - Upload with progress uses `router.post` with `forceFormData`.
    - Rename inline, reorder with up and down, delete with confirm.
    - It sits outside the main `<form>` (no nested forms, like the image uploader) and uses `preserveState`.
- [ ] **Step 5: Run** the tests and gates. **Step 6: Commit** "Catalog: digital products and private product files".

### Task 3: Checkout without shipping for digital carts

**Files:**

- Modify:
    - `app/Domains/Checkout/Pricing/PricedCart.php` (`requiresShipping(): bool`, included in `toArray()` as `requires_shipping`);
    - `app/Domains/Checkout/Pricing/Steps/ShippingStep.php`;
    - `app/Http/Controllers/Storefront/CheckoutController.php` (rules lines ~65 and ~117);
    - `app/Domains/Checkout/PlaceOrder.php` (~line 157);
    - `resources/js/pages/storefront/checkout.tsx`.
- Test: `tests/Feature/Checkout/DigitalCheckoutTest.php`

**Interfaces:**

- **Produces:**
    - `PricedCart::requiresShipping(): bool` is true if any `purchasableLines()` line has `$line->variant->product->is_digital === false`.
    - When it is false, `ShippingStep::apply` sets `shippingOptions = [['code' => 'digital', 'label' => 'No shipping needed', 'amount' => zero]]`, `shipping` to that option and `shippingTotal` to zero, whatever the destination.
    - In `CheckoutController`, `shipping_method` becomes `required_if` the cart requires shipping (otherwise nullable, and forced to `'digital'`).
    - The order stores `shipping_method_code = 'digital'` and `shipping_method_name = 'No shipping needed'`.
- **Consumes:** `products.is_digital` from Task 2.

- [ ] **Step 1: Failing tests:**
    - `test_digital_only_cart_checks_out_without_shipping_method`: the order has `shipping_total` 0 and code `digital`.
    - `test_mixed_cart_still_requires_shipping_method`: an error on `shipping_method`.
    - `test_removing_the_last_physical_item_drops_shipping`: price the cart after removal; `requires_shipping` is false and the shipping total is 0.
- [ ] **Step 2: Run** the tests. Expected: FAIL.
- [ ] **Step 3: Implement** the changes above. Keep the address fields required, since they are needed for tax.
- [ ] **Step 4: UI.** In `checkout.tsx`, when `cart.requires_shipping` is false, hide the shipping-method chooser and show "No shipping needed — your downloads are available after payment." Label the address block "Billing address".
- [ ] **Step 5: Run** the tests, gates and the Checkout suite (`--filter Checkout`). **Step 6: Commit** "Checkout: digital-only carts skip shipping".

### Task 4: Download grants and the download route

**Files:**

- Create:
    - migration `2026_10_26_000003_create_download_grants.php` (`download_grants` and `download_logs`);
    - `app/Domains/Downloads/Models/DownloadGrant.php` and `DownloadLog.php`;
    - `app/Domains/Downloads/DownloadSettings.php`;
    - `app/Domains/Downloads/Listeners/GrantDownloads.php` and `RevokeDownloads.php`;
    - `app/Http/Controllers/Storefront/DownloadController.php`;
    - `resources/views/downloads/unavailable.blade.php`.
- Modify:
    - `app/Providers/CommerceServiceProvider.php` (listeners: `OrderPaid` → `GrantDownloads`; `OrderCancelled` and `OrderRefunded` → `RevokeDownloads`);
    - `app/Domains/Orders/OrderWorkflow.php` (new `deliverDigital`);
    - `routes/web.php`;
    - `app/Http/Controllers/Admin/StoreSettingsController.php` and `resources/js/pages/admin/settings/store.tsx` (a "Digital downloads" card).
- Test: `tests/Feature/Downloads/DownloadGrantsTest.php`

**Interfaces:**

- **Produces:**
    - `download_grants(id, order_id FK cascade, order_item_id FK cascade, product_file_id FK cascade, token char(40) unique, downloads uint default 0, max_downloads uint nullable, expires_at timestamp nullable, revoked_at timestamp nullable, timestamps, unique(order_item_id, product_file_id))`;
    - `download_logs(id, download_grant_id FK cascade, ip string(45), user_agent string(255) nullable, created_at)`.
    - `DownloadGrant`:
        - `isUsable(CarbonInterface $now): bool` (not revoked, not expired, under the limit);
        - `remaining(): ?int`.
    - `DownloadSettings`:
        - `limit(): int` (setting `downloads.limit`, default 5);
        - `expiryDays(): int` (`downloads.expiry_days`, default 30);
        - `save(int $limit, int $expiryDays): void`.
    - `GrantDownloads::handle(OrderPaid $event)`:
        - idempotent through `firstOrCreate` on (item, file);
        - when the order is fully paid and every item's product is digital, it calls `OrderWorkflow::deliverDigital($order)`.
    - `OrderWorkflow::deliverDigital(Order $order): Order`:
        - locks the order, then sets fulfillment `Delivered` and status `Completed` when paid;
        - history "Delivered digitally"; audit `order.delivered_digitally`;
        - no actor, no shipment; dispatches `OrderDelivered`.
    - `RevokeDownloads` sets `revoked_at` for all the order's grants on a cancel, or on a refund that leaves the order fully refunded.
    - Route `GET /downloads/{token}` (`storefront.downloads.show`, `throttle:30,1`, `where token [A-Za-z0-9]{40}`):
        - an unknown token gives 404;
        - an unusable grant, a missing file, or an order no longer paid gives a 410 view;
        - otherwise it increments `downloads`, logs the download, and returns `Storage::disk($file->disk)->download($file->path, $file->name)`.
- **Consumes:** `ProductFile` (Task 2) and `OrderItem.product_id`.

- [ ] **Step 1: Failing tests:**
    - `test_grants_created_only_after_payment_and_once`: none at placement; one per item and file after `recordManualPayment`; dispatching `OrderPaid` twice keeps the count.
    - `test_digital_only_order_is_delivered_automatically` (status `completed`, fulfillment `delivered`); a mixed order stays unfulfilled.
    - `test_download_streams_counts_and_logs`.
    - `test_limit_and_expiry_return_410`: travel 31 days; and 6th download.
    - `test_unknown_token_is_404`.
    - `test_refund_revokes_links` (full refund gives 410) and `test_cancel_revokes_links`.
    - `test_a_deleted_file_gives_410_not_500`.
    - `test_settings_change_limit_and_expiry_for_new_grants`: 0 means unlimited and null expiry.
- [ ] **Step 2: Run** the tests. Expected: FAIL.
- [ ] **Step 3: Implement** the files above. The 410 view uses the storefront layout's plain Blade page, "This download link has expired or is no longer available", with a link to the account orders.
- [ ] **Step 4: Run** the tests, gates and `--filter '(Orders|Payments)'`. **Step 5: Commit** "Downloads: grants on payment, secure download links, revocation".

### Task 5: Showing downloads (email, order pages, admin)

**Files:**

- Modify:
    - `app/Http/Presenters/OrderPresenter.php` (`forCustomer` adds `downloads`; `forAdmin` adds `downloads`);
    - `resources/views/mail/orders/order.blade.php` (a downloads list for the `paid` kind);
    - `app/Http/Controllers/Storefront/CheckoutController.php` (order-complete props `downloads`);
    - `resources/js/pages/storefront/order-complete.tsx` and `resources/js/pages/storefront/account/order.tsx` (a "Your downloads" list);
    - `resources/js/pages/admin/orders/show.tsx` (a "Downloads" card);
    - `routes/admin.php`.
- Create: `app/Http/Controllers/Admin/DownloadGrantController.php`
- Test: `tests/Feature/Downloads/DownloadDeliveryTest.php`

**Interfaces:**

- **Produces:**
    - presenter item shape `{id, file: string, product: string, url: string|null, downloads: int, max_downloads: int|null, expires_at: string|null, usable: bool}` (`url` is null when not usable);
    - admin routes, all `orders.update`, `scopeBindings`:
        - `POST admin/orders/{order}/downloads/{grant}/reset` (`admin.orders.downloads.reset`), which sets downloads to 0;
        - `.../extend` adds 30 days from `max(now, expires_at)`;
        - `.../revoke`.

        Each is audited (`download.reset`, `download.extended`, `download.revoked`).
- **Consumes:** `DownloadGrant` (Task 4).

- [ ] **Step 1: Failing tests:**
    - `test_paid_email_lists_download_links` (render the `OrderMail` for `paid`; assert it contains `route('storefront.downloads.show', $token)`);
    - `test_customer_order_page_lists_downloads`;
    - `test_admin_can_reset_extend_revoke_and_needs_permission`.
- [ ] **Step 2: Run** the tests. Expected: FAIL.
- [ ] **Step 3: Implement** the presenter, mail, pages, card and controller.
- [ ] **Step 4: Run** the tests and gates. **Step 5: Commit** "Downloads: links in emails, order pages and admin".

### Task 6: Product bulk actions

**Files:**

- Create:
    - `app/Domains/Catalog/Actions/ProductBulkActions.php`;
    - `app/Domains/Catalog/BulkResult.php`;
    - `app/Http/Controllers/Admin/Catalog/ProductBulkController.php`;
    - `resources/js/components/admin/products/product-bulk-bar.tsx`.
- Modify:
    - `routes/admin.php`;
    - `app/Domains/Catalog/Csv/ProductCsv.php` (`export(string $currency, ?array $ids = null)`);
    - `resources/js/pages/admin/products/index.tsx` (a checkbox column, select all on the page, and the bulk bar).
- Test: `tests/Feature/Catalog/ProductBulkActionsTest.php`

**Interfaces:**

- **Produces:**
    - `BulkResult { int $updated; list<array{id:int, name:string, reason:string}> $skipped }`;
    - `ProductBulkActions::run(string $action, list<int> $ids, array $input, User $actor): BulkResult`, with the actions exactly as in the spec table: `publish`, `draft`, `archive`, `add_category`, `remove_category`, `set_brand`, `add_tags`, `remove_tags`, `feature`, `unfeature`, `adjust_prices`, `set_stock`, `delete`.
    - Route `POST admin/products/bulk` (`admin.products.bulk`), with the permission per action checked in the controller:
        - `delete` needs `products.delete`;
        - `export` needs `products.view` and streams a CSV with the ids;
        - everything else needs `products.update`.
    - Validation: `ids` required, an array, max 200, each an existing product id.
    - Flash result: `{type: 'success', message: "Updated N products.", skipped: [...]}`.
    - Prices: every `product_prices` row of the products' variants:
        - `new = old ± (percent ? old × v/100 : v)`, rounded to the currency's precision, via `Money::rounded()`;
        - a price under the currency minimum is set to the minimum and the product is listed in skipped as "Price floored at {min}".
- **Consumes:**
    - `DeleteProduct::handle`, which throws if the product cannot be deleted;
    - `InventoryService::setOnHand($variant, $qty, MovementReason::Adjustment, $actor)`;
    - `ProductTag::normalise`.

- [ ] **Step 1: Failing tests:**
    - `test_publish_draft_archive`, with publishing skipping a product with no priced active variant;
    - `test_categories_brand_tags_featured` (with the 20-tag cap skip);
    - `test_price_increase_and_decrease_percent_and_fixed`;
    - `test_price_decrease_floors_and_lists_products`: USD and a zero-decimal currency, e.g. JPY, configured as an extra currency;
    - `test_set_stock_skips_untracked_and_digital`;
    - `test_delete_skips_products_with_orders`;
    - `test_export_only_selected`;
    - `test_permissions_per_action` (`catalog-manager` without `products.delete` gets 403 on delete);
    - `test_more_than_200_ids_rejected`.
- [ ] **Step 2: Run** the tests. Expected: FAIL.
- [ ] **Step 3: Implement** the service (each action runs in a transaction, then `product.bulk_<action>` is audited with ids), the controller and the CSV ids filter.
- [ ] **Step 4: UI:**
    - `ProductBulkBar` props `{selected: number[], onClear, categories, brands}` is a sticky bar like `OrderBulkBar`;
    - the action menu groups are Status, Organise, Prices & stock, Delete & export;
    - small inline forms per action;
    - a confirm dialog for delete;
    - the result toast lists skipped products.
- [ ] **Step 5: Run** the tests and gates. **Step 6: Commit** "Catalog: product bulk actions".

### Task 7: Duplicate product

**Files:**

- Create: `app/Domains/Catalog/Actions/DuplicateProduct.php`
- Modify:
    - `app/Http/Controllers/Admin/Catalog/ProductController.php` (`duplicate`);
    - `routes/admin.php` (`POST admin/products/{product}/duplicate`, `admin.products.duplicate`, `products.create`);
    - `form.tsx` (a "Duplicate" header button, edit only);
    - `index.tsx` (a "Duplicate" row action).
- Test: `tests/Feature/Catalog/DuplicateProductTest.php`

**Interfaces:**

- **Produces:**
    - `DuplicateProduct::handle(Product $source, User $actor): Product`, which copies everything listed in spec §1. Files are copied with `Storage::disk('local')->copy()` to the new product's `downloads/{newId}/` folder.
    - `DuplicateProduct::uniqueSlug(string $base): string` and `uniqueSku(string $sku): string`, both static and used only here.
    - The controller redirects to `admin.products.edit` of the copy with the toast "Product duplicated. The copy is a hidden draft."
- **Consumes:**
    - `ProductRelations::sync` (Task 1);
    - `ProductFile` (Task 2);
    - the product's existing relations: categories, tags, translations, images, variants with options and prices.

- [ ] **Step 1: Failing tests:**
    - `test_copy_is_a_full_hidden_draft`: name "Copy of X", status draft, the same categories, tags, brand, images, variant prices in every currency, options and translations, related and upsells, stock 0, null barcodes.
    - `test_duplicating_twice_gets_unique_slug_and_skus` (`-copy-2` and `-COPY-2`).
    - `test_files_are_copied_on_private_disk` (`Storage::fake('local')`).
    - `test_reviews_and_flash_sale_memberships_not_copied` (only when those tables exist; skip otherwise).
    - `test_duplicate_needs_products_create`.
- [ ] **Step 2: Run** the tests. Expected: FAIL.
- [ ] **Step 3: Implement** the action (one transaction; audit `product.duplicated` with `source_id` and `copy_id`), controller, route and buttons.
- [ ] **Step 4: Run** the tests and gates. **Step 5: Commit** "Catalog: duplicate product".

### Task 8: Docs, full check and review

**Files:**

- Modify: `docs/architecture/decisions.md` (ADR-048, "Product tools and digital products"), `CHANGELOG.md` (an "Added (product tools)" section).

- [ ] **Step 1: ADR-048** records the decisions:
    - digital is set per product (not per variant);
    - the token is the secret, with no signed URLs and no sign-in required, so guests can download;
    - grants are created on `OrderPaid` and revoked on cancel or full refund;
    - digital-only orders are auto-delivered;
    - bulk actions are capped at 200 selected products;
    - prices are floored at 0.01.
- [ ] **Step 2: Run** the affected suites with `vendor/bin/phpunit --filter '(Catalog|Storefront|Checkout|Orders|Extensions|Payments|Cart|Downloads)'`. Expected: all pass, except the known baseline failure `DeveloperToolsTest::test_package_command_builds_the_archive_the_marketplace_accepts`.
- [ ] **Step 3: Browser check** on the dev store:
    - duplicate a product;
    - bulk-publish and adjust prices;
    - add related products and see them on the product page;
    - make a digital product with a file, check out with no shipping step, record the payment in admin, and download from the order page.
- [ ] **Step 4: Fresh reviewer** on the whole branch. Fix Critical and Important findings, then rerun the gates.
- [ ] **Step 5: Commit** "Docs: product tools (ADR-048)".
