# Payments panel, partial payments and bulk order actions — design

- **Date:** 2026-10-13
- **Status:** Approved ("Approve and build")
- **Sub-project:** 2 of 4

## Goal

Staff see and record every payment on an order, including partial payments in any manual method, with a clear balance due. They can act on many orders at once:

- move them to a fulfilment step;
- print packing slips or invoices as one PDF;
- export a CSV.

## Decisions (approved)

### Partially paid

- New `PaymentStatus::PartiallyPaid` (`partially_paid`) and a 20th headline status, **Partially paid**.
- A partly paid order cannot be fulfilled until it is fully paid, except pay-on-delivery orders.

### Manual payments

Staff can record a manual payment on **any order with a balance due**, including online-payment orders.

- **Method:** `cash`, `bank_transfer`, `card_terminal` or `other`, stored in `payments.method_code` with `provider = manual`.
- **Amount:** greater than 0 and at most the balance due.
- **Date:** today or earlier.
- **Optional:** reference and note.
- **Open online sessions** for the order are expired, the same way cancellation does it, so the customer cannot pay twice.

### CSV export

One row per order:

- number, placed at (store timezone), customer name, email, phone, country;
- headline status, payment status, fulfilment status;
- currency, subtotal, discount, shipping, tax, total, paid, refunded, balance due;
- payment method, shipping method, carrier, tracking number, coupon.

The export covers the **selected orders**, or **all orders matching the current filters**. It is capped at 10 000 rows, streamed, and protected against formula injection (`ProductCsv` rule).

## Domain

- **`Order::balanceDue(): Money`** is `grand_total - paid_total`, never negative. Refunds don't reopen the balance.
- **`paymentStatusFor`:**
  - paid zero → Unpaid (or keep Failed);
  - paid < grand and no refunds → PartiallyPaid;
  - otherwise the existing refund rules, then Paid.
- **`recordManualPayment(Order, User, Money $amount, string $method, ?CarbonInterface $paidAt, ?string $reference, ?string $note)`:**
  - Validation errors on `amount`, `method` and `paid_at`.
  - Fully paid now: `setPaidStatus`, `paid_at`, then `OrderPaid` (invoice and email, as today).
  - Partial: status unchanged. The log reads "Partial payment of X recorded (Cash). Balance due Y." and is customer-visible.
  - `canRecordPayment` is true when not cancelled and the balance is positive.
- **`canShip`:** Paid, or pay-on-delivery with Unpaid, Failed or PartiallyPaid.
- **`canCancel`:** unchanged (outstanding or refunded only). A partly paid order must be refunded first.
- **Online payment start:** only while outstanding (unchanged), so a partly paid order is settled manually.
- **Headline:** a new rule after "current fulfilment step" and before "payment failed": payment partially paid → `partially_paid`. Customers see it too.
- **Existing caller:** `OrderController::recordPayment` validates `amount` (decimal regex), `method` (in the list), `paid_at` (date, before or equal to today) and reference/note. `amount` defaults to the balance when it is missing, for backwards compatibility with existing tests.

## Payments panel (admin order page)

- **Summary:** Total, Paid, Refunded and **Balance due**, highlighted when positive.
- **Table:** one row per payment and refund, newest first:
  - date, type (payment or refund), method label, amount, status, reference, who recorded it;
  - a receipt download for paid payments;
  - failure reasons shown in red.
- **"Record payment"** opens the dialog with amount (prefilled with the balance), method, date (today), reference and note.

## Bulk actions (order list)

- **Selection:** a checkbox per row and a "select page" checkbox, up to 100 at once.
- **Action bar:** shows the number selected.
  - **Move to step:** a select of fulfilment steps, plus an "Email customers" tick. Calls `POST admin/orders/bulk/fulfillment` (`numbers[]`, `status`, `notify`; `orders.manage`).
    - Each order goes through `advanceFulfillment`.
    - Refusals are collected, never forced.
    - The flash reads "Moved 7 orders. Skipped 2: EM-000012 (not paid), …".
    - Shipping in bulk records no tracking.
  - **Print packing slips** and **Print invoices**: `GET admin/orders/bulk/documents?type=packing-slips|invoices&numbers[]=` (`orders.view`) returns one PDF.
    - Each document starts on a new page and has its own header.
    - Orders without an invoice are skipped.
    - Limit 100.
  - **Export selected:** `GET admin/orders/export?numbers[]=`.
- **"Export CSV"** in the page header exports everything matching the current filters (`orders.view`).

## Documents refactor

The document bodies move to `resources/views/documents/bodies/{invoice,receipt,packing-slip}.blade.php`, each with its own header block in the page flow.

- The single-document views and a new `documents/bundle.blade.php` both include the bodies.
- A bundle puts a page break between documents.
- The footer stays fixed with the seller's details.

## Testing

- **Partial payments:**
  - partial, then partial, then fully paid;
  - headline and status change at each step;
  - the invoice and email only on full payment;
  - over-balance refused;
  - a future date refused;
  - an unknown method refused;
  - allowed on an online-method order, which expires the open session;
  - not shippable while partly paid unless pay on delivery;
  - cancel refused while partly paid.
- **Panel props:** balance, method labels, receipt URLs.
- **Bulk move:** mixed eligible and ineligible orders, the summary, permission, the 100 limit.
- **Bulk documents:** a PDF containing each order number; orders without an invoice skipped; permission.
- **CSV:** headers, a row's values, filters respected, selected numbers respected, formula escaping, permission.
- **Performance:** the query budget for the list with selection props stays flat.
