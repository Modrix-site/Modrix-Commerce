# Order documents and status updates — design

- **Date:** 2026-10-13
- **Status:** Approved design, awaiting spec review
- **Sub-project:** 1 of 4 in "Enhance product and order management"
  - Later sub-projects:
    - 2: payments panel and bulk order actions
    - 3: product editor redesign and options
    - 4: product bulk actions, duplicate, related and digital products
  - Each gets its own spec.

## Goal

Merchants run orders end to end from the admin:

- move an order through clear, guarded statuses, with a visible timeline;
- produce professional PDF documents (invoice, payment receipt, packing slip) that staff and customers can download, with the invoice attached to the payment email.

Success means:

- every paid order has an invoice with a unique, sequential, gap-free number that never changes after issue;
- staff can hold, release and pack orders without breaking the stock and payment rules;
- a customer can download their own invoice and receipts from their account, and nobody else can.

## Decisions

- **PDF engine:** `dompdf/dompdf` (LGPL-2.1, used as a dependency of an MIT project).
  - Remote resources are disabled (`isRemoteEnabled = false`); images are embedded from local storage only.
  - DejaVu Sans is the font, for accented and non-Latin text.
- **Invoices are stored snapshots.** Receipts and packing slips are rendered from existing records:
  - payments and refunds never change once recorded;
  - packing slips show no prices.
- **Status changes are guarded workflow moves**, not a free-form status dropdown, so stock and payment rules cannot be bypassed.
- **Out of scope:** credit notes for refunds. Refunds appear on receipts and in the timeline.

## Data

### `invoices` table

| column | type | notes |
|---|---|---|
| id | id | |
| order_id | foreignId, unique | one invoice per order |
| number | string(40), unique | e.g. `INV-2026-00042` |
| sequence | unsignedBigInteger | the numeric part |
| issued_at | timestamp | |
| currency | string(3) | |
| seller | json | store name, address, email, tax ID at issue time |
| buyer | json | name, email, billing address at issue time |
| lines | json | name, SKU, quantity, unit price, tax, line total per item |
| totals | json | subtotal, discount, shipping, tax, grand total |
| notes | text, nullable | footer text at issue time |
| issued_by | foreignId users, nullable | null when automatic |
| timestamps | | |

Rows are never updated after insert: the model blocks updates and deletes.

### `document_counters` table

| column | type |
|---|---|
| name | string, primary (`invoice`) |
| next | unsignedBigInteger |

A number is taken inside the order transaction:

- `SELECT … FOR UPDATE` on the counter row, then increment.
- No gaps, because the counter only advances when the invoice row commits in the same transaction.
- SQLite serialises writes, so the same code is safe there.

### Settings (Admin → Settings → Documents)

- `documents.invoice_prefix`:
  - default `INV-{year}-`;
  - `{year}` is replaced at issue time.
- `documents.invoice_padding`: default 5.
- `documents.next_invoice_number`:
  - editable only upwards, so merchants can continue an existing series;
  - stored in the counter row.
- `documents.tax_id`: the store's VAT or tax number, optional.
- `documents.footer`: free text, optional (payment terms, registration details).
- `documents.logo_media_id`: an image from the media library, optional.

## Order status changes

`OrderStatus` gains `OnHold = 'on_hold'`. `FulfillmentStatus` gains `Packed = 'packed'`.

### `orders` table

New column `hold_previous_status` (string, nullable), so Release can restore the status the order had before it was held.

### New `OrderWorkflow` moves

| move | allowed when | effect |
|---|---|---|
| `hold(order, actor, reason)` | status is pending or processing, and fulfillment is unfulfilled or packed | stores the previous status, sets on_hold |
| `release(order, actor)` | status is on_hold | restores the previous status |
| `markPacked(order, actor)` | `canShip()` (now false while on hold) and fulfillment is unfulfilled | fulfillment becomes packed |

Changes to existing rules:

- `canShip` also accepts `packed` and rejects `on_hold`.
- Record payment, cancel and refund stay allowed while on hold.
- Payment confirmation while on hold keeps the order on hold but records the payment, so `hold_previous_status` becomes processing.

`allowedActions` gains `hold`, `release` and `mark_packed`, all under `orders.manage`.

Each move:

- writes an `OrderEvent` (type `held`, `released` or `packed`) with the optional reason;
- writes an audit entry.

### Customer notification

The hold, packed and ship forms carry a `notify` checkbox. When ticked, the customer gets an `OrderMail` status update with the reason as a customer-visible note. Held orders are never shown to customers as "on hold"; the storefront shows "processing".

## Documents service

`App\Domains\Orders\Documents\OrderDocuments`:

- `issueInvoice(Order, ?User): Invoice`
  - idempotent: returns the existing invoice if one exists;
  - builds the snapshot and takes a number.
- `invoicePdf(Invoice): string`
- `receiptPdf(Payment): string`:
  - only for payments with status succeeded;
  - shows the amount, method, reference, date, the order total, paid to date, refunds to date and the balance.
- `packingSlipPdf(Order): string`

Templates live in `resources/views/documents/{invoice,receipt,packing-slip}.blade.php` and share a layout:

- They use money and date formatting from the order's currency and the store timezone.
- Wording comes from `__()` with English keys, in the locale stored on the order if one exists, else the store default.
- An `OrderPaid` listener issues the invoice automatically; staff can also issue one on unpaid orders ("Issue invoice").

## Routes and access

Admin routes:

| route | permission |
|---|---|
| `GET admin/orders/{order}/invoice.pdf` | `orders.view` |
| `GET admin/orders/{order}/payments/{payment}/receipt.pdf` | `orders.view`; payment scoped to the order |
| `GET admin/orders/{order}/packing-slip.pdf` | `orders.view` |
| `POST admin/orders/{order}/invoice` | `orders.manage` (issue) |
| `POST admin/orders/{order}/hold` | `orders.manage` |
| `POST admin/orders/{order}/release` | `orders.manage` |
| `POST admin/orders/{order}/pack` | `orders.manage` |

The invoice download returns 404 until an invoice exists.

Customer routes (signed-in, own orders only, through the existing account order policy):

| route | rule |
|---|---|
| `GET account/orders/{number}/invoice.pdf` | |
| `GET account/orders/{number}/payments/{payment}/receipt.pdf` | guests are redirected to sign-in; other customers' orders give 404 |

PDF responses carry:

- `Content-Disposition: attachment`;
- `Cache-Control: private, no-store`;
- `X-Content-Type-Options: nosniff`.

## Email

The payment-received `OrderMail` attaches `invoice-{number}.pdf` when an invoice exists. A rendering failure is logged and the mail is sent without the attachment.

## Admin order page

Redesign of `resources/js/pages/admin/orders/show.tsx`:

- **Header:** order number, placed date and status chips (order, payment, fulfillment).
- **Progress stepper:** Placed → Paid → Packed → Shipped → Delivered. Cancelled and on-hold states are shown as a banner.
- **Action bar:**
  - primary next step, plus a "More" menu for hold/release, cancel, refund, issue invoice;
  - each action opens the existing dialog pattern, with the notify checkbox where relevant.
- **Main column:** items, totals.
- **Side column:** customer, addresses, shipping, Documents card.
- **Documents card:**
  - invoice: Issue or Download, with its number;
  - one receipt per succeeded payment;
  - packing slip.
- **Timeline:** order events, payments, refunds and shipments merged, newest first, with the actor and an icon per type.

The customer's account order page gets a Documents section with the invoice and receipts.

## Testing

Feature tests:

- **Invoice numbers:**
  - sequential;
  - `{year}` prefix;
  - the next number can only move up;
  - one invoice per order, and issuing again is idempotent;
  - the snapshot does not change after the product, the address or the settings change.
- **Auto-issue:** happens on payment, both manual and gateway.
- **Hold and release:**
  - shipping is blocked while on hold;
  - release restores the previous status;
  - payment while on hold;
  - cancel while on hold restocks.
- **Packed:** pack, then ship works; pack is not allowed when cancelled or on hold.
- **Access:**
  - staff without `orders.view` get 403;
  - another customer's invoice gives 404;
  - a guest is redirected to sign-in (the account routes require auth);
  - a receipt for a payment on another order gives 404.
- **PDFs:** each is a valid PDF (`%PDF` header) and its extracted text includes the number and grand total.
- **Email:** the payment mail has the attachment.
- **Performance:** the admin order page stays within its query budget.

Existing workflow tests keep passing.

## Docs

- ADR-043: order documents and guarded hold/packed statuses.
- CHANGELOG.
- Schema notes.
- The user guide section on orders.
