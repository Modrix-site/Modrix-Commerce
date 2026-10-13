# Payments Panel and Bulk Orders Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:**
- Partial and manual payments on any order, with a balance due.
- A payments panel on the order page.
- Bulk order actions: move to a step, combined PDFs, CSV export.

**Architecture:**
- **Workflow:** extend `PaymentStatus` and `OrderWorkflow::recordManualPayment`.
- **Presenter:** gains a payments ledger.
- **Bulk actions:** `OrderBulkController` reuses the workflow per order.
- **PDFs:** `OrderDocuments` renders bundles from shared body partials.
- **CSV:** a new `OrderCsv` exporter.

**Tech Stack:** Laravel 13, dompdf, Inertia/React, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-13-payments-and-bulk-orders-design.md`

## Global Constraints

- **Gates for every task:**
  - pint
  - phpstan (0 errors)
  - `check:fix`
  - `types:check`
  - `build`
  - full `php artisan test`
- **Workflow rules apply everywhere:** bulk actions go through `OrderWorkflow`, nothing is forced, and the audit log records every change.
- **CSV cells** starting with `= + - @ \t \r` are prefixed with `'`.
- **Limits:** 100 orders per bulk action or PDF; 10 000 rows per CSV.

## Review Focus

1. **A payment recorded at the same moment as an online webhook** must not overpay silently. Open sessions are expired, and an overpayment is flagged by the existing duplicate-payment logic. Test: record a manual payment that leaves a balance, then confirm the earlier gateway payment for the full total, and the duplicate is flagged. Owner: Task 1.
2. **Rounding:** a balance of 0.01 must be payable. A JPY order (0 decimals) must refuse 0.5. Owner: Task 1.
3. **Bulk move with orders from different stages:** each order gets only legal moves, and the summary names the skipped orders. Owner: Task 3.
4. **CSV with a malicious customer name** (`=HYPERLINK(...)`) must be escaped. Owner: Task 5.
5. **A bundle PDF of 100 orders** must not exceed the time budget: render under 30 s locally. Measure once; a test uses 3 orders. Owner: Task 4.

---

### Task 1: Partial and manual payments

- **Changes:**
  - `PaymentStatus::PartiallyPaid`.
  - `DisplayStatus::PartiallyPaid` and its rule.
  - `Order::balanceDue()`.
  - `paymentStatusFor`, `recordManualPayment` (new signature), `canRecordPayment`, `canShip`.
  - Expire open gateway sessions.
  - The controller validates the new fields.
  - `OrderWorkflow::MANUAL_METHODS` (`cash` Cash, `bank_transfer` Bank transfer, `card_terminal` Card terminal, `other` Other).
- **Tests:** `tests/Feature/Orders/PartialPaymentTest.php`, covering the spec cases plus Review Focus 1 and 2.

### Task 2: Payments panel

- **Presenter:**
  - `balance_due`;
  - `payments[]` gains `method_label`, `recorded_by` (name), `receipt_url` and `note`;
  - `refunds[]` gains `recorded_by`.
- **React:** `components/admin/orders/order-payments.tsx` replaces the Payment card. The Record payment dialog gets amount, method, date, reference and note.
- **Tests:** panel props; a query budget for the admin order page.

### Task 3: Bulk move to step

- **Route:** `POST admin/orders/bulk/fulfillment` → `OrderBulkController::fulfillment`.
- **Validation:**
  - `numbers` is required, an array, max 100, each a string;
  - `status` is an advanceable step;
  - `notify` is nullable boolean.
- **Behaviour:** each order runs in its own try/catch on `ValidationException`. The flash is a summary.
- **Tests:** `tests/Feature/Orders/BulkOrderActionsTest.php`.

### Task 4: Combined PDFs

- **Templates:** refactor into bodies, `single` and `bundle`.
- **Service:** `OrderDocuments::packingSlipsPdf(Collection<Order>)` and `invoicesPdf(Collection<Invoice>)`.
- **Route:** `GET admin/orders/bulk/documents` → `OrderBulkController::documents`.
- **Tests:**
  - bundle text contains every number;
  - orders without an invoice are skipped, and 404 when nothing is left;
  - permission;
  - existing single-document tests still pass.

### Task 5: CSV export

- **Code:**
  - `App\Domains\Orders\Csv\OrderCsv::write(resource, Builder)`;
  - route `GET admin/orders/export` → `OrderBulkController::export` (the index filters, or `numbers[]`);
  - an audit entry `orders.exported`.
- **Tests:** headers and one row; filters; selection; escaping; permission.

### Task 6: Order list selection UI

- **List page:** checkboxes, a bulk bar (Move to step dialog, Print packing slips, Print invoices, Export selected), and an Export CSV header button.
- **Browser check:** partial payment, bulk move, bulk PDF, CSV.

### Task 7: Docs

- ADR-045.
- CHANGELOG.
- Schema notes.
