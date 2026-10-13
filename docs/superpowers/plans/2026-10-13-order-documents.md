# Order Documents and Status Updates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Staff can hold, release and pack orders with an optional customer email. Every paid order gets a frozen, sequentially numbered invoice. Invoices, receipts and packing slips download as PDFs (staff: all; customers: their own invoice and receipts), and the invoice is attached to the payment email.

**Architecture:**
- **Statuses:** new guarded moves in `OrderWorkflow`.
- **Invoices:** stored snapshots (`invoices`), numbered from a locked counter row (`document_counters`).
- **Documents:** `App\Domains\Orders\Documents\OrderDocuments` builds the snapshots and renders Blade templates through dompdf.
- **Admin order page:** redesigned around a stepper, an action bar, a Documents card and a merged timeline.

**Tech Stack:** Laravel 13, dompdf/dompdf ^3, Inertia 3 + React 19 + TypeScript, PHPUnit feature tests on SQLite.

**Spec:** `docs/superpowers/specs/2026-10-13-order-documents-design.md`

## Global Constraints

- **Shell setup:** run `export PATH="/c/php84:$PATH"` before any `php` or `composer` command.
- **Gates for every task:**
  - `vendor/bin/pint`
  - `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`
  - `npm run -s check:fix`
  - `npm run -s types:check`
  - `npm run -s build` (feature tests need the build)
  - `php artisan test`
- After adding routes, run `php artisan wayfinder:generate --with-form`.
- **dompdf options:**
  - `isRemoteEnabled = false`;
  - default font `DejaVu Sans`;
  - images are embedded only as `data:` URIs read from local storage.
- **PDF responses:** `Content-Disposition: attachment`, `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`.
- **Invoices are immutable:** the model throws `LogicException` on updating and deleting (same pattern as `OrderEvent`).
- **Invoice prefix:** default `INV-{year}-`, padding 5, so the first invoice in 2026 is `INV-2026-00001`.
- **The next invoice number can only increase.**
- **Holds stay internal:** customers never see "On hold". Customer-facing presenters map `on_hold` to the Processing label.
- **Access:**
  - every new admin download route needs `orders.view`;
  - the issue, hold, release and pack routes need `orders.manage`.
- **Customer downloads:** the order must have `user_id` equal to the signed-in user, otherwise 404 (same check as `AccountOrderController::show`).
- **Seller address:** the spec's seller block needs an address, and no store address setting exists. Add a multiline setting `documents.seller_address` on the Documents settings page.
- **Customer docs** must be updated with each change, and the ADR log gets ADR-043.

## Review Focus

1. **Two orders paid at the same moment must get distinct consecutive numbers.** Test: issue in two separate transactions against the same counter, then assert numbers `00001` and `00002`. Owner: Task 2.
2. **A product rename or price change after issue must not change the invoice PDF.** Test: change the product name and the billing address after issue, re-render, assert the old name. Owner: Task 2 (snapshot) and Task 3 (render).
3. **A receipt URL for a payment that belongs to another order must 404**, not leak that payment. Test in Task 4.
4. **Payment confirmed while the order is on hold** must record the payment and stay on hold, and release must then go to processing, not pending. Test in Task 1.
5. **Names with accents and non-Latin characters** (e.g. "Ünïcødé Café", "東京") must render in the PDF without crashing. Test: an order with such a name, and the PDF text contains it. Owner: Task 3.

---

### Task 1: Hold, release and packed statuses

**Files:**
- Modify:
  - `app/Domains/Orders/Enums/OrderStatus.php`: add `OnHold = 'on_hold'`, label `On hold`.
  - `app/Domains/Orders/Enums/FulfillmentStatus.php`: add `Packed = 'packed'`, label `Packed`.
  - `app/Domains/Orders/Models/Order.php`: `@property string|null $hold_previous_status`.
  - `app/Domains/Orders/OrderWorkflow.php`.
  - `app/Http/Controllers/Admin/OrderController.php`: `hold`, `release`, `pack` actions; `notify` on hold, pack and ship.
  - `app/Domains/Orders/Mail/OrderMail.php`: kind `updated` => `Update on your order`, with an optional `note`.
  - `app/Http/Presenters/OrderPresenter.php`: customer status maps `on_hold` to the Processing label and value `processing`.
  - `routes/admin.php`: new routes under `orders.manage`:
    - `orders.hold` (`POST orders/{order}/hold`);
    - `orders.release` (`POST orders/{order}/release`);
    - `orders.pack` (`POST orders/{order}/pack`).
- Create: migration `database/migrations/2026_10_20_000001_add_hold_previous_status_to_orders.php` (`string('hold_previous_status', 20)->nullable()`).
- Test: `tests/Feature/Orders/OrderHoldAndPackTest.php`.

**Interfaces:**
- Produces:
  - `OrderWorkflow::hold(Order $order, User $actor, ?string $reason = null, bool $notify = false): Order`
  - `OrderWorkflow::release(Order $order, User $actor, bool $notify = false): Order`
  - `OrderWorkflow::markPacked(Order $order, User $actor, bool $notify = false): Order`
  - `allowedActions()` can include `'hold'`, `'release'` and `'mark_packed'`.
  - `OrderMail::__construct(Order $order, string $kind, ?string $paymentInstructions = null, ?string $note = null)`

**Rules:**
- **Hold** is allowed when the status is pending or processing and fulfillment is unfulfilled or packed.
  - It stores the old status in `hold_previous_status` and logs event `held` (internal, with metadata reason).
- **Release** restores `hold_previous_status`, clears it, and logs `released`.
- **markPacked** is allowed when `canShip()` and fulfillment is unfulfilled. It logs `packed`.
- **`canShip`:**
  - returns false when on hold;
  - accepts unfulfilled or packed fulfillment.
- **Payment confirmation while on hold** (both `recordManualPayment` and `confirmGatewayPayment`): keep status `on_hold` and set `hold_previous_status = processing`.
- **`canCancel`** includes on_hold.
- **Notify:** when true, send `OrderMail` kind `updated` after commit.
  - For pack: note "Your order is packed and will ship soon."
  - For hold: the customer-visible note is the reason, or "Your order is being reviewed." when the reason is empty.
  - Ship's notify defaults true, so the existing shipped email keeps sending. When false, the shipped email is suppressed by passing a flag through the `OrderShipped` event (`public readonly bool $notify = true`).

- [ ] **Step 1: Write failing tests**
  - `test_hold_blocks_shipping_and_release_restores_status`: a processing paid order is held, so `allowedActions` lacks `ship` and has `release`, and `POST ship` → session error or 422. After release the status is processing.
  - `test_payment_while_on_hold_stays_on_hold_and_release_goes_to_processing`: pending manual order, hold, record payment → status `on_hold`, payment_status `paid`; release → `processing`.
  - `test_pack_then_ship`: processing order, pack → fulfillment `packed`, ship → `shipped`.
  - `test_pack_not_allowed_when_on_hold_or_cancelled`: `allowedActions` lacks `mark_packed`, and the route returns an error.
  - `test_cancel_while_on_hold_restocks`: stock returns.
  - `test_notify_sends_update_mail_only_when_ticked`: `Mail::fake`; hold with notify=1 → `OrderMail` kind `updated` sent once; pack with notify=0 → none.
  - `test_customer_never_sees_on_hold`: the account order page shows status `processing`.
  - `test_hold_requires_orders_manage`: a role with only `orders.view` gets 403.
- [ ] **Step 2:** Run `php artisan test --filter=OrderHoldAndPackTest` → FAIL.
- [ ] **Step 3:** Implement the enums, migration, workflow moves, controller actions, routes, mail kind and presenter mapping.
- [ ] **Step 4:** Run the filter, then `--filter=OrderWorkflowTest` → PASS.
- [ ] **Step 5:** Commit `Orders: hold, release and packed statuses`.

### Task 2: Invoice snapshots and numbering

**Files:**
- Create:
  - `database/migrations/2026_10_20_000002_create_invoices_tables.php`:
    - `invoices` as in the spec, with `sequence` unsignedBigInteger and `issued_by` nullable FK to users, nullOnDelete;
    - `document_counters` (`name` string(32) primary, `next` unsignedBigInteger), seeded with row `invoice` = 1.
  - `app/Domains/Orders/Models/Invoice.php`:
    - guarded; immutable;
    - casts: seller, buyer, lines and totals as `array`; `issued_at` as datetime;
    - `order()` BelongsTo.
  - `app/Domains/Orders/Documents/DocumentSettings.php`, which reads and writes through `SettingsRepository`:
    - `documents.invoice_prefix`;
    - `documents.invoice_padding`;
    - `documents.tax_id`;
    - `documents.seller_address`;
    - `documents.footer`;
    - `documents.logo_media_id`;
    - the next number lives in the counter row.
  - `app/Domains/Orders/Documents/InvoiceNumbers.php`.
  - `app/Domains/Orders/Documents/OrderDocuments.php`, rendering methods added in Task 3.
  - `app/Domains/Orders/Listeners/IssueInvoiceOnPayment.php`.
- Modify:
  - `app/Domains/Orders/Models/Order.php`: `invoice(): HasOne`.
  - The event registration, where `SendOrderEmails` is subscribed: subscribe the listener to `OrderPaid`.
- Test: `tests/Feature/Orders/InvoiceTest.php`.

**Interfaces:**
- Produces:
  - `DocumentSettings::toArray(): array{invoice_prefix: string, invoice_padding: int, next_invoice_number: int, tax_id: ?string, seller_address: ?string, footer: ?string, logo_media_id: ?int}`
  - `DocumentSettings::update(array $values): void`: throws `ValidationException` on `next_invoice_number` if lower than the current value.
  - `InvoiceNumbers::take(): array{sequence: int, number: string}`
    - must be called inside a transaction;
    - locks the `invoice` counter row with `lockForUpdate`, increments it, and formats `str_replace('{year}', now(store timezone)->format('Y'), prefix) . str_pad(sequence, padding, '0', STR_PAD_LEFT)`.
  - `InvoiceNumbers::next(): int`
  - `InvoiceNumbers::setNext(int $next): void`: the "only up" check lives in `DocumentSettings`.
  - `OrderDocuments::issueInvoice(Order $order, ?User $actor = null): Invoice`:
    - runs in `DB::transaction`;
    - returns the existing invoice if present; otherwise locks the order row and re-checks first;
    - snapshot sources:
      - **seller:** `name`, `email` (StoreSettings), `address` (seller_address), `tax_id`;
      - **buyer:** `name` (billing, else shipping first and last name, else email), `email`, `address` (billing or shipping `toPresentable()`);
      - **lines:** each item's name, variant label, sku, quantity, unit price, tax and line total, as decimal strings;
      - **totals:** subtotal, discount_total, shipping_total, tax_total and grand_total strings, plus `prices_include_tax`;
      - **notes:** the footer;
    - writes `OrderEvent` type `invoice_issued` (internal) and an audit record `orders.invoice_issued`.
  - `IssueInvoiceOnPayment::handle(OrderPaid $event): void`: calls `issueInvoice` with a null actor.

- [ ] **Step 1: Write failing tests**
  - `test_paid_orders_get_sequential_invoices`: two orders paid → `INV-2026-00001`, `INV-2026-00002` (use `travelTo('2026-10-20')`).
  - `test_issuing_twice_returns_the_same_invoice`.
  - `test_snapshot_does_not_change_after_issue`: rename the product, change the billing address and the footer → the invoice `lines[0].name` and `buyer.address` are unchanged.
  - `test_invoices_are_immutable`: `$invoice->update(...)` throws `LogicException`.
  - `test_counter_is_safe_across_transactions`: two `DB::transaction(fn () => $numbers->take())` calls → sequences 1 and 2, and `next()` is 3.
  - `test_next_number_can_only_move_up`: `update(['next_invoice_number' => 0])` throws `ValidationException`; setting 100 → the next invoice is `…00100`.
  - `test_custom_prefix_and_padding`: `FAC-` with padding 3 → `FAC-001`.
  - `test_staff_can_issue_invoice_for_unpaid_order`: `POST admin.orders.invoice.store` with `orders.manage` → an invoice exists. Without the permission → 403.
- [ ] **Step 2:** Run `--filter=InvoiceTest` → FAIL.
- [ ] **Step 3:** Implement the migration, models, `DocumentSettings`, `InvoiceNumbers`, `issueInvoice`, the listener and the `orders.invoice.store` route (`POST orders/{order}/invoice`, `orders.manage`, `OrderController::issueInvoice`).
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `Orders: invoice snapshots and gap-free numbering`.

### Task 3: PDF rendering

**Files:**
- Run `composer require dompdf/dompdf:^3.1`.
- Create:
  - `resources/views/documents/layout.blade.php`:
    - inline CSS, A4, DejaVu Sans;
    - header with the logo as a data URI, seller block, document title and number;
    - footer text.
  - `resources/views/documents/invoice.blade.php`
  - `resources/views/documents/receipt.blade.php`
  - `resources/views/documents/packing-slip.blade.php`
  - `app/Domains/Orders/Documents/PdfRenderer.php`
- Modify: `app/Domains/Orders/Documents/OrderDocuments.php`.
- Test: `tests/Feature/Orders/OrderDocumentsPdfTest.php`.

**Interfaces:**
- Produces:
  - `PdfRenderer::render(string $view, array $data): string`: the PDF bytes, using the constraint options.
  - `OrderDocuments::invoicePdf(Invoice $invoice): string`: renders from the snapshot only.
  - `OrderDocuments::receiptPdf(Payment $payment): string`:
    - throws `InvalidArgumentException` unless the status is `Payment::STATUS_PAID`;
    - shows the payment amount, method name, reference, paid_at and the order number;
    - the balance block shows the grand total, paid to date (sum of the order's paid payments up to and including this one, by `paid_at` then id), refunded to date (`refunded_total`), and the balance due (grand total − paid to date, never below zero).
  - `OrderDocuments::packingSlipPdf(Order $order): string`: the shipping address, items (name, variant, sku, qty), customer note and shipping method; no prices.
  - Logo: `DocumentSettings` `logo_media_id` → `Media` file read from its disk → `data:{mime};base64,...`. Skip it when missing or not an image.

**Formatting:**
- Money uses the existing `Money` formatting with the order currency.
- Dates are in the store timezone, format `j M Y`.
- Wording comes from `__('…')` under the store default locale (`Locales` default), set with `app()->setLocale()` around the render and restored in `finally`.

- [ ] **Step 1: Write failing tests.** Extract text with `strpos` on the raw PDF bytes after setting dompdf `compress = false` in tests via `config('ecomodular.documents.compress', true)`, and add that config key.
  - `test_invoice_pdf_contains_number_and_total`: starts with `%PDF`, contains `INV-2026-00001` and the formatted grand total.
  - `test_invoice_pdf_uses_snapshot_after_rename`: Review Focus 2.
  - `test_receipt_shows_balance_for_partial_history`: an order with one paid payment → contains the reference and a balance of `0.00`.
  - `test_receipt_rejects_unpaid_payment`: throws `InvalidArgumentException`.
  - `test_packing_slip_has_items_without_prices`: contains the SKU, not the price.
  - `test_unicode_names_render`: product "Ünïcødé Café 東京" → the PDF renders. Assert `%PDF` and no exception. dompdf encodes text, so assert the ASCII part `Caf` plus a successful render.
  - `test_remote_images_are_never_fetched`: the footer contains `<img src="http://example.test/x.png">` as text → escaped, and `Http::fake()` records no request. Footer text is escaped with `{{ }}`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `Orders: invoice, receipt and packing slip PDFs`.

### Task 4: Download routes and email attachment

**Files:**
- Create:
  - `app/Http/Controllers/Admin/OrderDocumentController.php`: `invoice(Order)`, `receipt(Order, Payment)`, `packingSlip(Order)`.
  - `app/Http/Controllers/Storefront/AccountOrderDocumentController.php`: `invoice(Request, Order)`, `receipt(Request, Order, Payment)`.
  - `app/Http/Responses/PdfDownload.php`: `static make(string $bytes, string $filename): Response` with the constraint headers.
- Modify:
  - `routes/admin.php`, inside the `orders.view` group:
    - `orders.documents.invoice` (`GET orders/{order}/invoice.pdf`);
    - `orders.documents.receipt` (`GET orders/{order}/payments/{payment}/receipt.pdf`, `->scopeBindings()`);
    - `orders.documents.packing-slip` (`GET orders/{order}/packing-slip.pdf`).
  - `routes/web.php`, inside the account group:
    - `account.orders.invoice` (`GET orders/{order}/invoice.pdf`);
    - `account.orders.receipt` (`GET orders/{order}/payments/{payment}/receipt.pdf`, scoped);
    - add `throttle:30,1`.
  - `app/Domains/Orders/Mail/OrderMail.php`: `attachments()`.
    - When the kind is `paid`, or `placed` and the order's payment_status is paid: `OrderDocuments::issueInvoice($order)` (idempotent), then `Attachment::fromData(fn () => invoicePdf, "invoice-{number}.pdf")->withMime('application/pdf')`.
    - Catch `Throwable` → `report()` and return `[]`.
- Test: `tests/Feature/Orders/OrderDocumentAccessTest.php`.

**Rules:**
- **Filenames:** `invoice-{invoice number}.pdf`, `receipt-{order number}-{payment id}.pdf`, `packing-slip-{order number}.pdf`.
- **Invoice download 404s** when no invoice exists.
- **Receipt** 404s when the payment is not paid.
- **Customer routes:** `abort_unless($order->user_id !== null && $order->user_id === auth id, 404)`.

- [ ] **Step 1: Write failing tests**
  - `test_staff_with_orders_view_download_all_documents`: 200, `application/pdf`, attachment disposition, `no-store`.
  - `test_staff_without_orders_view_get_403`.
  - `test_invoice_404_before_issue`.
  - `test_receipt_for_payment_of_another_order_404`: Review Focus 3, for both admin and account routes.
  - `test_customer_downloads_own_invoice_and_receipt`.
  - `test_customer_cannot_download_other_customers_documents`: 404.
  - `test_guest_is_redirected_to_login`.
  - `test_customer_cannot_download_packing_slip`: there is no account route; assert the route name doesn't exist via `Route::has`.
  - `test_payment_email_has_invoice_attachment`: `Mail::fake`, pay a manual order → the `OrderMail` kind `paid` has `hasAttachedData` or checks `attachments()` for one PDF named `invoice-INV-…pdf`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement, then run `php artisan wayfinder:generate --with-form`.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit `Orders: document downloads for staff and customers, invoice on payment email`.

### Task 5: Documents settings page

**Files:**
- Create:
  - `app/Http/Controllers/Admin/DocumentSettingsController.php`: `edit`, `update`, under `settings.manage`.
  - `resources/js/pages/admin/settings/documents.tsx`:
    - fields: prefix (with `{year}` help), padding (3–8), next invoice number (min = current), tax ID, seller address (textarea), footer (textarea), logo (`media-picker`);
    - a preview line "Next invoice: INV-2026-00042".
- Modify:
  - `routes/admin.php`: `settings.documents.edit` (`GET settings/documents`) and `settings.documents.update` (`PUT settings/documents`).
  - Admin navigation, where Store settings is linked: add "Documents".
- Test: add to `tests/Feature/Orders/InvoiceTest.php`.
  - `test_documents_settings_page_saves_and_validates`:
    - prefix max 20, regex `/^[A-Za-z0-9\-\/_{}]*$/`;
    - padding 3–8;
    - logo must be an image media id;
    - a lower next number → error `next_invoice_number`;
    - the audit entry is `settings.documents_updated`;
    - `catalog-manager` → 403.

- [ ] **Step 1:** Write the failing test.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement the controller, page, route and nav, then run wayfinder.
- [ ] **Step 4:** Run → PASS. Run the type check and build.
- [ ] **Step 5:** Commit `Settings: invoice numbering and document details`.

### Task 6: Admin order page redesign and customer documents

**Files:**
- Modify:
  - `app/Http/Presenters/OrderPresenter.php`:
    - `forAdmin` adds `invoice` (`{number, issued_at}|null`), `timeline` and `documents`;
    - `forCustomer` adds `documents`: `{invoice_url: ?string, receipts: list<{label, url}>}`.
  - `app/Http/Controllers/Admin/OrderController.php`: `show` passes `canIssueInvoice` (`orders.manage` and no invoice).
  - `resources/js/pages/admin/orders/show.tsx`: rewrite. Split into components under `resources/js/components/admin/orders/`:
    - `order-stepper.tsx`
    - `order-actions.tsx`: primary next action and a More menu; the hold, pack and ship dialogs carry a "Email the customer" checkbox;
    - `order-documents.tsx`
    - `order-timeline.tsx`
  - `resources/js/pages/storefront/account/order.tsx`: Documents section, with wording through `t()`.
  - Then run `php artisan ecomodular:storefront-strings` and add es, fr and de translations for the new keys.
  - `status-badge` labels: add `on_hold` and `packed`.
- Test: extend `tests/Feature/Orders/OrderDocumentAccessTest.php`.

**Presenter shapes:**
- **timeline:** a list of `{at: ISO string, kind: 'event'|'payment'|'refund'|'shipment', title: string, detail: ?string, actor: ?string}`, newest first.
  - Built from events, payments with a paid or failed status, refunds and shipments.
  - Loaded with eager loads; no per-row queries.
- **documents:** `{invoice: {number, url}|null, receipts: list<{label: "Receipt — {amount} on {date}", url}>, packing_slip_url}`.
- **Stepper steps:**
  - placed, paid, packed, shipped, delivered;
  - each `{key, label, done: bool, at: ?string}`;
  - packed counts as done when fulfillment is packed, shipped or delivered;
  - a banner state `cancelled` or `on_hold` sits outside the steps.

- [ ] **Step 1: Write failing tests**
  - `test_admin_order_page_shows_documents_and_timeline`: Inertia asserts `order.documents.invoice.number` and that `order.timeline` contains a `payment` entry.
  - `test_admin_order_page_query_budget`: reuse the existing query-budget helper; the limit is unchanged, or set at the measured count plus a margin of at most 3.
  - `test_customer_order_page_lists_invoice_and_receipts`.
  - `test_customer_order_page_has_no_documents_for_unpaid_order`: the invoice is null and receipts are empty.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement the presenter and pages.
- [ ] **Step 4:** Run the full gates. Check in the browser:
  - an admin order: hold → release → pack → ship;
  - download the invoice;
  - account documents.
- [ ] **Step 5:** Commit `Orders: redesigned admin order page with documents and timeline`.

### Task 7: Docs

- Modify:
  - `docs/architecture/decisions.md`: ADR-043, order documents and guarded hold/packed statuses (dompdf with remote resources off, snapshots, locked counter, holds hidden from customers).
  - `CHANGELOG.md`.
  - `docs/database/schema.md`: invoices, document_counters, `hold_previous_status`.
  - The user guide's orders section, if it exists under `docs/`.
- [ ] Commit `Docs: order documents and statuses`.
