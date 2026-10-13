# Detailed Order Statuses Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Track orders through the merchant's 19 statuses while keeping the three state machines (order, payment, fulfilment) consistent.

**Architecture:**
- **State machines:** extend the `OrderStatus`, `PaymentStatus` and `FulfillmentStatus` enums.
- **Workflow:** add guarded moves to `OrderWorkflow`.
- **Headline status:** derive one `DisplayStatus` (19 cases), stored in `orders.display_status` through a model `saving` hook.
- **Emails:** go through a new `OrderStatusChanged` event and per-event settings.

**Tech Stack:** Laravel 13, Inertia 3, React 19 + TypeScript, PHPUnit on SQLite.

**Spec:** `docs/superpowers/specs/2026-10-13-order-statuses-design.md`

## Global Constraints

- **Environment:** run `export PATH="/c/php84:$PATH"`.
- **Gates for each task:**
  - pint
  - phpstan (0 errors)
  - `npm run -s check:fix`
  - `types:check`
  - `build`
  - `php artisan test`
- **Moves:** every move is locked through `transition()`, audited, logged as an `OrderEvent`, and refusals throw `ValidationException` on `order`.
- **Customers** never see `on_hold`, `picking`, `packing`, `packed` or `ready_for_dispatch`.
- **Mail failures** are reported, never thrown.
- **Existing callers keep working:** `ship()`, `markPacked()` and `markDelivered()` stay as wrappers.
- **New storefront wording** goes through `t()`, followed by `php artisan ecomodular:storefront-strings` and es/fr/de entries.

## Review Focus

1. **A webhook payment arriving after "Payment Failed"** must move the order to paid and confirmed, not leave it failed. Test in Task 2.
2. **Cancelling after the order has shipped** must be refused, even through a direct POST. Test in Task 4.
3. **Returned with restock twice** must not restock twice: use the item `restocked_quantity`. Test in Task 4.
4. **Old rows after migration:** an old `pending` order must still be payable, and an old `processing` + `unfulfilled` order must still be shippable. Test in Task 1.
5. **Review setting on, plus a COD order:** the order stays `pending` until Confirm, and fulfilment is refused before Confirm. Test in Task 3.

---

### Task 1: Enums, headline status and data migration

- **Files:**
  - **Enums:**
    - `OrderStatus`: cases `PendingPayment='pending_payment'`, `Pending='pending'`, `Confirmed`, `OnHold`, `Processing`, `Completed`, `Cancelled`, with labels.
    - `FulfillmentStatus`: the 11 cases, plus `rank(): int` (0–8 for the sequence; `delivery_failed` = 6.5 handled as a side state) and `isShipped(): bool`.
    - `PaymentStatus`: the `Failed` case and `isOutstanding()`.
  - **Create:**
    - `app/Domains/Orders/Enums/DisplayStatus.php`: 19 cases, `label()`, `customerFacing()`.
    - `app/Domains/Orders/OrderDisplayStatus.php`: `static for(Order): DisplayStatus` and `static forCustomer(Order): DisplayStatus`.
    - Migration `2026_10_21_000001_detailed_order_statuses.php`.
  - **Modify:**
    - `Order::booted` saving hook sets `display_status`.
    - Rename every `OrderStatus::Pending` reference (meaning awaiting payment) to `PendingPayment`.
- **Tests:** `tests/Feature/Orders/OrderDisplayStatusTest.php`
  - `test_headline_rules`: one case per spec rule, building unsaved `Order` models with the three statuses set.
  - `test_customer_never_sees_internal_states`.
  - `test_display_status_is_stored_on_save`.
  - `test_migration_maps_existing_orders`: insert old rows (`status` pending/processing), run the migration's `up()` through `require`, then assert the mapping and `display_status`; Review Focus 4.

### Task 2: Payment failed

- **Changes:**
  - `failGatewayPayment` sets `payment_status = failed` when `paid_total` is zero.
  - Switch to `isOutstanding()` everywhere listed in the spec.
  - `paymentStatusFor` keeps `Failed` only when zero is paid and the current status is failed.
- **Tests:** `tests/Feature/Orders/PaymentFailedTest.php`
  - failed, then retry, then pay;
  - Review Focus 1;
  - a manual payment can still be recorded on a failed order.

### Task 3: Confirmation and the review setting

- **Changes:**
  - `PlaceOrder` sets the initial status: `pending_payment`, or `confirmed` for COD (`pending` if `orders.review_before_confirm` is on).
  - `setPaidStatus` sets `confirmed`, or `pending` with review on.
  - `OrderWorkflow::confirm()`.
  - Route `POST admin/orders/{order}/confirm` (`orders.manage`).
  - `allowedActions` includes `confirm`.
- **Tests:** `tests/Feature/Orders/OrderConfirmationTest.php`
  - auto-confirm on payment;
  - COD confirmed at placement;
  - review on: pending, fulfilment refused, then confirm, then picking allowed;
  - confirm refused when not pending.

### Task 4: Fulfilment moves

- **Changes:**
  - `advanceFulfillment()`, `markDeliveryFailed()`, `markReturned()`.
  - Update `canShip`, `canCancel` and `canHold` to the spec.
  - Routes `POST orders/{order}/fulfillment` (`status`, `notify`, tracking fields), `orders/{order}/delivery-failed` and `orders/{order}/returned`.
  - `allowedActions` gives `advance:{status}` entries.
- **Tests:** `tests/Feature/Orders/FulfillmentStepsTest.php`
  - full path;
  - skip from confirmed to shipped;
  - backwards refused;
  - in transit before shipped refused;
  - delivery failed, then out for delivery, then delivered;
  - returned with restock, and again without double restock (Review Focus 3);
  - cancel refused after shipped (Review Focus 2);
  - the first pre-ship step sets processing;
  - completed on delivery when paid.

### Task 5: Status emails and settings

- **Changes:**
  - `OrderStatusChanged` event.
  - `OrderMail` kinds and template cases.
  - `SendOrderEmails::statusChanged` with per-kind idempotency keys (kind plus event id).
  - Settings `orders.notify.*` on the documents settings page, renamed "Orders & documents".
- **Tests:** `tests/Feature/Orders/StatusEmailTest.php`
  - default on;
  - setting off suppresses;
  - notify override both ways;
  - payment failed email.

### Task 6: Admin UI, list filter, dashboard and storefront

- **Changes:**
  - Presenter: `display_status` (admin headline) and the customer headline.
  - Steps list with `skipped`.
  - Admin `allowedActions` exposure for advance targets.
  - Order index: filter by `display_status`, with counts.
  - Dashboard counts.
  - React: stepper (11 steps, skipped state), action bar ("Move to…" dialog with a status select plus tracking fields when shipping), banners, list badge, filter.
  - Storefront: status badge labels for the 19 values, through `t()`, with es, fr and de.
- **Tests:**
  - presenter props;
  - list filter counts;
  - the account page shows the customer headline;
  - the query budget.
- **Browser check:** walk one order through the steps.

### Task 7: Docs

- ADR-044.
- CHANGELOG.
- Schema notes.
