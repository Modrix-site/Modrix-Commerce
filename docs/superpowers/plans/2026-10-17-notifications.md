# Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** an admin notification bell with live toasts and per-staff settings, plus a customer notification bell for order updates.

**Architecture:**

- **Data:** Laravel database notifications on `User`. One `StoreNotification` class carries `{kind, params, url, dedupe}`, and `NotificationKinds` describes each kind (audience, permission, icon, title and body templates).
- **Delivery:** `StoreNotifier` picks the recipients (permission, preferences, actor excluded, de-duplicated) and channels (database and/or mail).
- **Triggers:** synchronous listeners on the existing domain events.
- **UI:** a shared bell component for the admin header and the storefront header; a feed endpoint polled every 30 s in the admin.

**Tech Stack:** Laravel 13 notifications, Inertia React, sonner toasts, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-17-notifications-design.md`

## Global Constraints

- **Environment and gates:**
    - run `export PATH="/c/php84:$PATH"` first;
    - pint, phpstan, `npm run -s types:check`;
    - `npx vp check --fix` on new files only;
    - wayfinder after routes;
    - `php artisan ecomodular:storefront-strings` plus es/fr/de translations for new storefront `t()` texts.
- **Commit only own changes:** pairs for shared tracked files (separate HEAD sets when HEAD differs); new files with `git add`.
- **Staff kinds:** `order.placed`, `order.paid`, `payment.failed`, `order.cancelled`, `order.refunded` (orders.view); `stock.low` (inventory.view); `customer.new` (customers.view).
- **Customer kinds:** `customer.order_confirmed`, `customer.order_shipped`, `customer.out_for_delivery`, `customer.delivered`, `customer.cancelled`, `customer.refunded`, `customer.payment_failed`, `customer.downloads_ready`.
- **Preferences:**
    - stored in `users.notification_preferences` JSON as `{kind: {bell: bool, email: bool}}`;
    - default `bell: true`, `email: false`;
    - owners receive every staff kind;
    - staff need the permission.
- **Actor exclusion:** the signed-in user at dispatch time (`auth()->id()`) is not notified of a staff kind.
- **De-duplication:** `dedupe` keys, e.g. `order.paid:{orderId}` and `customer.order_shipped:{orderId}:{shipmentCount}`. A user who already has a notification with the same `data->dedupe` is skipped.
- **Text:**
    - staff text is rendered server-side in English (the admin is English);
    - customer text is rendered client-side with `t()` from literal templates in `resources/js/lib/notification-text.ts`, so the strings catalogue picks them up;
    - the server also stores an English fallback `title`.
- **Limits:**
    - feed: at most 20 items, `throttle:20,1`;
    - dropdown: 10;
    - page: 25;
    - poll: 30 s, visible tab only;
    - toasts: 3, then "+N more notifications";
    - links: internal paths only (`^/(?!/)`);
    - clean-up: read notifications older than 90 days, all older than 365 days, daily.

## Review Focus

1. **Duplicate events** (queued retries, a webhook and a manual payment) must not double-notify. Pinned in Task 2 (`test_same_event_twice_notifies_once`).
2. **Staff without the permission** must never get order or customer details. Pinned in Task 2 (`test_only_staff_with_permission_receive`).
3. **Another user's notification id** in open, read or delete gives 404. Pinned in Task 3 (`test_cannot_touch_someone_elses_notification`).
4. **A long-open admin tab** must not flood: the feed is capped at 20 and toasts at 3. Pinned in Task 3 (`test_feed_returns_newer_items_capped`) plus the browser check.
5. **Guest orders** produce no customer notifications and no errors. Pinned in Task 4 (`test_guest_orders_give_no_customer_notifications`).

---

### Task 1: Foundation

**Files:**

- Create:
    - migration `2026_10_27_000001_create_notifications_table.php`: Laravel's notifications table plus `users.notification_preferences` JSON (nullable);
    - `app/Notifications/StoreNotification.php`;
    - `app/Domains/Notifications/NotificationKinds.php`;
    - `app/Domains/Notifications/StoreNotifier.php`;
    - `app/Console/Commands/PruneNotifications.php` (`notifications:prune`, scheduled daily).
- Modify: `app/Models/User.php` (pair: cast `notification_preferences` array, helper `wantsNotification(string $kind, string $channel): bool`); the console schedule (pair).
- Test: `tests/Feature/Notifications/StoreNotifierTest.php`

**Interfaces:**

- `NotificationKinds::all(): array<string, array{audience: 'staff'|'customer', permission: ?string, icon: string, title: string, body: string}>`
- `NotificationKinds::register(string $kind, array $definition)` (validates permission for staff kinds)
- `NotificationKinds::render(string $kind, array $params): array{title: string, body: string}`
- `StoreNotifier::toStaff(string $kind, array $params, string $url, string $dedupe): int` returns the number of recipients
- `StoreNotifier::toCustomer(User $user, string $kind, array $params, string $url, string $dedupe): bool`
- `StoreNotification(string $kind, array $params, string $url, string $dedupe, array $channels)`:
    - `via()` returns the channels;
    - `toArray()` returns `{kind, params, url, dedupe, title, body, icon}`;
    - `toMail()` uses a simple line mail with an action button to `url(...)`.

- [ ] **Tests:**
    - `test_only_staff_with_permission_receive`;
    - `test_owner_always_receives`;
    - `test_actor_is_not_notified`;
    - `test_preferences_turn_bell_and_email_on_or_off` (`Notification::fake` for mail);
    - `test_same_event_twice_notifies_once`;
    - `test_external_urls_are_dropped`;
    - `test_prune_removes_old_read_ones`.
- [ ] RED, then implement, then GREEN plus gates, then commit "Notifications: store notifications and recipients".

### Task 2: Event triggers

**Files:**

- Create: `app/Domains/Notifications/Listeners/NotifyStaff.php` and `NotifyCustomer.php` (subscribers)
- Modify (pair): `app/Providers/CommerceServiceProvider.php` (`Event::subscribe`)
- Test: `tests/Feature/Notifications/NotificationTriggersTest.php`

**Mapping:**

- **Staff:** OrderPlaced → `order.placed`; OrderPaid → `order.paid`; OrderStatusChanged(`payment_failed`) → `payment.failed`; OrderCancelled; OrderRefunded; InventoryLow → `stock.low` (url: admin product of the variant); CustomerRegistered → `customer.new`.
- **Customer** (only when `order.user_id` is set): OrderStatusChanged(`confirmed`) and OrderPlaced when the status is confirmed; OrderShipped (params: carrier, tracking); OrderStatusChanged(`out_for_delivery`); OrderDelivered; OrderCancelled; OrderRefunded; OrderStatusChanged(`payment_failed`); OrderPaid with grants → `downloads_ready`.
- **Params:** `number`, `total` (formatted), `customer` (name), `sku`, `on_hand`.
- **Links:** admin `/admin/orders/{number}`, `/admin/products/{id}/edit`, `/admin/customers/{id}`; customer `/account/orders/{number}`.

- [ ] **Tests:**
    - `test_order_flow_notifies_staff` (placed → paid → refunded through the real workflow);
    - `test_low_stock_and_new_customer`;
    - `test_customer_gets_order_updates` (confirmed, shipped with tracking, delivered);
    - `test_guest_orders_give_no_customer_notifications`;
    - `test_downloads_ready_only_with_links`.
- [ ] RED, then implement, then GREEN plus the Orders suite, then commit "Notifications: order, stock and customer triggers".

### Task 3: Admin bell, page, feed and settings

**Files:**

- Create:
    - `app/Http/Controllers/Admin/NotificationController.php`: index, feed, open, markRead, markAllRead, destroy, settings, updateSettings;
    - `resources/js/components/notifications/notification-bell.tsx` (shared bell and dropdown);
    - `resources/js/components/notifications/use-notification-poll.ts`;
    - `resources/js/pages/admin/notifications/index.tsx` and `settings.tsx`.
- Modify (pair):
    - `routes/admin.php` (auth staff group, no extra permission);
    - `HandleInertiaRequests.php` (lazy `notifications: {unread}` for signed-in users);
    - `resources/js/components/app-sidebar-header.tsx` (bell before the user menu; separate HEAD set).
- Test: `tests/Feature/Notifications/AdminNotificationsTest.php`

- [ ] **Tests:**
    - `test_bell_count_and_page`;
    - `test_feed_returns_newer_items_capped`;
    - `test_open_marks_read_and_redirects`;
    - `test_mark_all_and_delete`;
    - `test_cannot_touch_someone_elses_notification`;
    - `test_settings_list_only_allowed_kinds_and_save`.
- [ ] Implement, check the UI in the browser, then commit "Notifications: admin bell, live alerts and settings".

### Task 4: Customer bell

**Files:**

- Create: `app/Http/Controllers/Storefront/AccountNotificationController.php` (index, open, markAllRead) and `resources/js/pages/storefront/account/notifications.tsx`
- Modify:
    - `resources/js/components/storefront/site-header.tsx`: bell in the icons and a menu link;
    - `routes/web.php` (pair: account group);
    - `resources/js/lib/notification-text.ts` (customer templates);
    - the strings catalogue and translations.
- Test: `tests/Feature/Notifications/CustomerNotificationsTest.php`

- [ ] **Tests:**
    - `test_customer_sees_own_notifications`;
    - `test_open_marks_read`;
    - `test_guest_has_no_bell_prop`.
- [ ] Implement, check in the browser, then commit "Notifications: customer bell".

### Task 5: Docs and review

- [ ] ADR-050 and the CHANGELOG.
- [ ] Full affected suites.
- [ ] Fresh reviewer, then one fix pass.
