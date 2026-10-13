# Detailed order statuses — design

- **Date:** 2026-10-13
- **Status:** Approved ("Approve and build")
- **Builds on:** `2026-10-13-order-documents-design.md`, which added `on_hold` and `packed`.

## Goal

Merchants track every order through the 19 statuses they asked for:

| # | Status | # | Status |
|---|---|---|---|
| 1 | Pending Payment | 11 | Shipped |
| 2 | Payment Failed | 12 | In Transit |
| 3 | Pending | 13 | Out for Delivery |
| 4 | Confirmed | 14 | Delivered |
| 5 | On Hold | 15 | Delivery Failed |
| 6 | Processing | 16 | Cancelled |
| 7 | Picking | 17 | Returned |
| 8 | Packing | 18 | Refunded |
| 9 | Packed | 19 | Partially Refunded |
| 10 | Ready for Dispatch | | |

Payment, stock and refund rules must keep holding. Success means:

- every order shows exactly one of the 19 as its headline status;
- staff move orders only through allowed steps (optional steps may be skipped);
- customers never see internal states;
- customers get emails for the changes the merchant chose.

## Decisions (approved)

- **Three tracks plus a stored headline.** Order, payment and fulfilment stay independent state machines, extended below. The headline is derived from them, stored in `orders.display_status`, and indexed for filtering.
- **Skipping:** fulfilment steps move forward only; optional steps may be skipped.
- **Confirmation is automatic.** An order becomes Confirmed when it is paid, or at placement when its payment method allows fulfilment before payment (cash on delivery).
- **Optional review step:** the setting `orders.review_before_confirm` (default off) sends those orders to Pending instead, until staff click Confirm.
- **Emails:** confirmed, shipped, out_for_delivery, delivered, delivery_failed, payment_failed, returned.
  - Each is switchable in the setting `orders.notify.{event}`, all on by default.
  - The per-action "Email the customer" checkbox overrides the setting for that one change.
  - "Something else" from the answers is interpreted as this switchable setting.

## Tracks

### `OrderStatus`

| value | meaning | entered by |
|---|---|---|
| `pending_payment` | awaiting payment (was `pending`) | placement, online and manual methods |
| `pending` | received, awaiting review | placement or payment, only when review is on |
| `confirmed` | accepted by the store | payment, COD placement, staff Confirm |
| `on_hold` | paused | hold (unchanged) |
| `processing` | being fulfilled | first fulfilment step after confirmed |
| `completed` | paid and delivered | delivered and paid (unchanged) |
| `cancelled` | cancelled | cancel (unchanged) |

### `PaymentStatus`

Adds `failed`: the latest attempt failed and nothing has been paid. It is set by `failGatewayPayment` when `paid_total` is zero, and replaced by `paid` on a later success.

`PaymentStatus::isOutstanding()` is true for `unpaid` and `failed`. It replaces every `=== Unpaid` check:

- `PaymentStarter`;
- `CheckoutController`;
- `canRecordPayment`, `canShip` (COD path), `canCancel` and `expireGatewayPayment`;
- `OrderMail::attachments`;
- the front-end checks.

### `FulfillmentStatus`

Sequence: `unfulfilled`, `picking`, `packing`, `packed`, `ready_for_dispatch`, `shipped`, `in_transit`, `out_for_delivery`, `delivered`.

Side states:

- `delivery_failed`: entered from shipped, in transit or out for delivery. It can go on to in transit, out for delivery or delivered.
- `returned`: entered from shipped, in transit, out for delivery, delivered or delivery failed.

`rank()` gives each status's position in the sequence.

## Workflow moves (`OrderWorkflow`)

All moves are locked, audited, logged as `OrderEvent`s, and refuse anything not allowed (`ValidationException` on `order`).

- **`confirm(order, actor, notify)`:** status pending → confirmed. The payment must not be outstanding, unless the method allows fulfilment before payment.
- **`advanceFulfillment(order, actor, FulfillmentStatus target, notify, ?carrier, ?tracking, ?trackingUrl)`.** Allowed when:
  - the target is a pre-ship step (picking, packing, packed, ready for dispatch) with rank above the current one, and `canShip()` holds (paid or COD; not on hold or cancelled; currently before shipped);
  - the target is `shipped`, and `canShip()` holds (this is the existing ship logic, with the shipment record);
  - the target is in transit or out for delivery, with rank above the current one and current between shipped and out for delivery, or current is delivery failed;
  - the target is `delivered`, and the current status is shipped, in transit, out for delivery or delivery failed (this is the existing `markDelivered` logic).

  Effects:
  - The first pre-ship step on a confirmed order also sets the order status to processing.
  - `ship()`, `markPacked()` and `markDelivered()` remain as thin wrappers, so existing callers and tests keep working.
- **`markDeliveryFailed(order, actor, reason, notify)`:** from shipped, in transit or out for delivery.
- **`markReturned(order, actor, reason, restock: bool, notify)`:** from shipped or later (including delivery failed).
  - When `restock` is set, it restocks the remaining quantities (`MovementReason::Return` if it exists, otherwise `Cancellation`).
  - The order status stays as is, so a refund remains a separate action.
- **`cancel`:** allowed while fulfilment is unfulfilled, picking, packing, packed or ready for dispatch (still before shipped).
- **`hold`:** allowed while the status is pending payment, pending, confirmed or processing, and fulfilment is before shipped.
- **`release`:** restores the previous status (unchanged).
- **Payment on hold:** `setPaidStatus` stores confirmed, or pending with review on, as the previous status.

`allowedActions` returns:

- `confirm`;
- `advance:{status}` for each allowed target;
- `delivery_failed`, `returned`;
- plus the existing actions.

## Headline status

`App\Domains\Orders\OrderDisplayStatus::for(Order): DisplayStatus` (an enum of the 19 values with labels) applies these rules, first match wins:

1. order cancelled → `cancelled`
2. payment refunded → `refunded`
3. fulfilment returned → `returned`
4. payment partially refunded → `partially_refunded`
5. order on hold → `on_hold`
6. fulfilment delivery failed → `delivery_failed`
7. fulfilment delivered (or order completed) → `delivered`
8. fulfilment out for delivery, in transit, shipped, ready for dispatch, packed, packing or picking → same value
9. payment failed → `payment_failed`
10. order pending payment → `pending_payment`
11. order pending → `pending`
12. order confirmed → `confirmed`
13. otherwise → `processing`

Storage and display:

- **Stored:** `orders.display_status` (string(24), indexed) is recomputed by a model `saving` hook, so every write path stays in sync.
- **Customers** see `customerStatus()`:
  - `on_hold` is replaced by the headline the order would have had before the hold;
  - picking, packing, packed and ready for dispatch show as processing.

## Data migration

1. Add `display_status`.
2. `status = 'pending'` becomes `pending_payment`.
3. `status = 'processing'` with fulfilment `unfulfilled` becomes `confirmed`.
4. `hold_previous_status` is mapped the same way.
5. `display_status` is computed for every order (in chunks).
6. `down()` reverses the status renames (`pending_payment` back to `pending`; confirmed or pending back to processing or pending) and drops the column.

## Emails

New `OrderMail` kinds:

- `confirmed`
- `out_for_delivery`
- `delivered`
- `delivery_failed`
- `payment_failed`
- `returned`

Existing kinds `shipped` and `paid` stay.

Sending:

- Each status move fires `OrderStatusChanged(orderId, kind, notify ?bool)`. `SendOrderEmails` sends when `notify` is true, or when `notify` is null and the setting `orders.notify.{kind}` is on (default on).
- Mail failures are reported, never thrown.
- Settings live on the Admin → Invoices & documents page, renamed **Orders & documents**, in a new "Customer emails" card.

## Admin UI

**Order page:**

- **Stepper:** Placed, Paid, Confirmed, Picking, Packing, Packed, Ready for dispatch, Shipped, In transit, Out for delivery, Delivered.
  - Skipped steps show as skipped (greyed out with no date).
  - Side states (on hold, delivery failed, returned, cancelled) show as a banner.
- **Action bar:**
  - The primary button is the next logical step.
  - "More actions" lists the "Move to…" targets (skip-ahead), Confirm, Delivery failed, Returned, Hold or Release, Refund and Cancel.
  - Each status dialog has a notify checkbox, defaulting to the setting for that event.

**Order list:**

- the headline badge replaces the three badges in the main column, and the payment badge stays small;
- a filter by headline status with counts.

**Dashboard:** "Ready to fulfil" counts confirmed or processing orders before shipped, paid or COD, not on hold.

## Storefront

- The account order list and order page show the customer headline.
- The confirmation page shows payment instructions while payment is outstanding.
- The status-badge labels for all 19 values are translatable (es, fr, de).

## Testing

- Every allowed and refused move, including skipping.
- Confirm only with review on.
- Payment failed, then retry and pay.
- Delivery failed, then retry, then delivered.
- Returned with and without restock.
- Cancel refused after shipped.
- The headline table: one assertion per rule.
- Customers never see internal states (on hold, picking to ready for dispatch).
- The migration maps old rows.
- Email settings and the override; mail failure doesn't break moves.
- The query budget holds.
- The existing suites stay green.
