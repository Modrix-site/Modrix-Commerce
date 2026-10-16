# Notifications — design

- **Date:** 2026-10-17
- **Status:** Approved
- **Answers:**
    - admin bell;
    - real-time alerts;
    - per-staff settings;
    - customer notifications.

## Goal

Staff see what needs attention (new orders, payments, low stock…) in a bell in the admin, with pop-up alerts while they work, and choose what they get. Signed-in customers see their order updates in a bell on the storefront.

## 1. Foundation

- **Storage:** Laravel database notifications (`notifications` table, `User` is already `Notifiable`). Each notification is `App\Notifications\StoreNotification`, stored with:
    - `data = { kind, params, url }`;
    - `kind`: e.g. `order.placed`;
    - `params`: e.g. `{number: "EM-1001", total: "$50.00"}`;
    - `url`: an internal path such as `/admin/orders/EM-1001`.
- **Text:** title and body are produced when shown, in the reader's language, from `NotificationKinds` (kind → title template, body template, icon, audience, permission). Templates use `:placeholders` from `params`.
- **Sending:** `StoreNotifier::toStaff(string $kind, array $params, string $url, ?string $dedupeKey = null)` and `StoreNotifier::toCustomer(User $customer, …)`.
    - **Staff recipients:** every staff user with the kind's permission (owners always) whose settings keep the kind on.
    - **Channels:** `database` when the bell is on (the default), plus `mail` when the user turned email on (default off).
    - **De-duplication:** a repeated `dedupeKey` (e.g. `order.paid:{orderId}`) for the same user is skipped, so a re-delivered event never notifies twice.
- **Modules:**
    - **Registration:** they add kinds with `NotificationKinds::register(...)` (permission required) and send with `StoreNotifier`.
    - **Product reviews:** the product-reviews module is a later follow-up using this API, not part of this work.
- **Clean-up:** a daily scheduled command deletes read notifications older than 90 days and any older than 365 days.

## 2. Staff kinds

| Kind              | Trigger                                                 | Permission       | Link              |
| ----------------- | ------------------------------------------------------- | ---------------- | ----------------- |
| `order.placed`    | `OrderPlaced`                                           | `orders.view`    | admin order       |
| `order.paid`      | `OrderPaid`                                             | `orders.view`    | admin order       |
| `payment.failed`  | `OrderStatusChanged` kind `payment_failed`              | `orders.view`    | admin order       |
| `order.cancelled` | `OrderCancelled`                                        | `orders.view`    | admin order       |
| `order.refunded`  | `OrderRefunded`                                         | `orders.view`    | admin order       |
| `stock.low`       | `InventoryLow`                                          | `inventory.view` | admin product     |
| `customer.new`    | `CustomerRegistered`                                    | `customers.view` | admin customer    |

The staff member who caused an event (e.g. recorded a refund) is not notified about it.

## 3. Customer kinds

The order must belong to a customer account; guests keep their emails.

| Kind                       | Trigger                                                              | Link             |
| -------------------------- | -------------------------------------------------------------------- | ---------------- |
| `customer.order_confirmed` | `OrderStatusChanged` `confirmed`, or `OrderPlaced` when it is confirmed at once | account order |
| `customer.order_shipped`   | `OrderShipped` (carrier and tracking in params)                      | account order    |
| `customer.out_for_delivery`| `OrderStatusChanged` `out_for_delivery`                              | account order    |
| `customer.delivered`       | `OrderDelivered`                                                     | account order    |
| `customer.cancelled`       | `OrderCancelled`                                                     | account order    |
| `customer.refunded`        | `OrderRefunded`                                                      | account order    |
| `customer.payment_failed`  | `OrderStatusChanged` `payment_failed`                                | account order    |
| `customer.downloads_ready` | `OrderPaid` when the order has download links                        | account order    |

Customers have no settings in this version; these are their own order updates.

## 4. Admin UI

- **Bell** in `app-sidebar-header.tsx`:
    - the unread count (from a shared prop `notifications.unread` for staff, read lazily);
    - a dropdown with the latest 10 (icon, title, body, time ago, unread dot) and "Mark all as read";
    - "View all" opens `/admin/notifications`.
- **Opening a notification** marks it read and goes to its link (`POST admin/notifications/{id}/open` redirects).
- **Page `admin/notifications`:**
    - unread/all filter, 25 per page;
    - mark read, mark all read, delete;
    - a "Settings" tab.
- **Settings `admin/notifications/settings`:** a table of the kinds this user may receive (permission filter), each with "In the bell" and "By email" switches.
    - Saved per user (`notification_preferences` JSON column on `users`).
- **Real-time:**
    - while the admin tab is visible, `GET admin/notifications/feed?after={lastId}` runs every 30 s and returns new notifications plus the unread count;
    - new items appear as toasts (at most 3, then "+N more notifications"), each linking to its target;
    - the badge updates;
    - polling pauses while the tab is hidden and resumes on focus.

## 5. Storefront UI

- **Bell:** signed-in customers see a bell with a dot or count next to the account icon in the shared header (`site-header.tsx`, desktop compact bar and mobile) and on desktop theme headers through the same shared component.
    - The dropdown lists the latest 10, with "Mark all as read" and "View all".
- **Account → Notifications** (`/account/notifications`): a list of all, with mark read.
- **Sharing:** shared storefront data adds `notifications.unread` for signed-in customers (lazy).
- **Updates:** no polling on the storefront; counts refresh on navigation.

## 6. Security and limits

- **Ownership:** every endpoint only touches the signed-in user's own notifications (404 otherwise).
- **Links:** stored URLs must be internal paths; anything else is dropped.
- **Feed:** rate-limited (`throttle:20,1`) and returns at most 20 items.
- **Content:** no customer personal data beyond the order number, name and total in staff notifications. Customer notifications show only their own order data.

## Testing

- **Kinds and recipients:**
    - every staff kind reaches the users with the permission and not others;
    - the owner always gets them;
    - the actor is excluded;
    - settings turn bell or email off;
    - email is sent only when on.
- **De-duplication:** the same event twice gives one notification.
- **Customer kinds:**
    - notified for their own orders;
    - guest orders give none;
    - downloads-ready only for orders with links.
- **Endpoints:**
    - the bell prop count;
    - the feed after id, with its limit;
    - open marks read and redirects;
    - mark all;
    - delete;
    - another user's notification gives 404;
    - external URLs are dropped.
- **Clean-up command:** removes old read ones and keeps unread ones under 365 days.
- **Translations:** templates exist in en/es/fr/de.
- **Browser:**
    - place an order as a customer and see the admin bell, toast and page;
    - ship it and see the customer bell.

## Out of scope

- push notifications to phones or the desktop;
- SMS;
- WebSockets;
- digest emails;
- review notifications (follow-up for the reviews module);
- customer settings.
