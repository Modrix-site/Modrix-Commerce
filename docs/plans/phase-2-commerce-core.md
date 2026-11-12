# Phase 2 Plan: Commerce Core

Branch: `feature/phase-2-commerce-core`. Each slice is implemented with migrations, validation, authorization, UI, tests and docs, and committed separately once the quality gates pass.

## Slices

| #   | Slice                            | Key areas                                                                                                                                                                                                                                                                                                         |
| --- | -------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2.1 | Money and catalog                | `Money` value object (brick/math), categories (tree), products, variants (SKU), attributes/options, prices per currency, images with ordering, draft/published, slugs, SEO, admin CRUD with search/filter/sort/pagination                                                                                         |
| 2.2 | Inventory                        | Locations (single default), per-variant stock, movement ledger, adjustments, low-stock threshold + `InventoryLow` event, overselling policy (`allow_backorder`), `StockAllocator` using `SELECT … FOR UPDATE`                                                                                                     |
| 2.3 | Customers and storefront catalog | `customers` + `customer_addresses`, customer created on registration/guest checkout, storefront home, category, search and product pages, account address book                                                                                                                                                    |
| 2.4 | Cart and pricing                 | Guest (session token) and user carts, merge on login, `CartPricer` that reprices everything from the database, changed-price and unavailable notices                                                                                                                                                              |
| 2.5 | Discounts                        | Coupons (% / fixed, dates, global and per-customer limits, minimum subtotal, product/category eligibility), explicit rejection reasons, redemptions, audit                                                                                                                                                        |
| 2.6 | Shipping and tax                 | `ShippingMethod` and `TaxProvider` contracts + registries, configurable flat-rate/free-over shipping methods with country restrictions, rate-table tax with inclusive/exclusive prices                                                                                                                            |
| 2.7 | Checkout and orders              | `PlaceOrder` action in one transaction (reprice, validate coupon, allocate stock, snapshot items/addresses/money), human-readable numbers, separate order/payment/fulfillment states with validated transitions, manual payment method, payments table, shipments, notes/history, cancellation and refund records |
| 2.8 | Notifications and reports        | Domain events, queued customer emails, dashboard metrics, reports (sales over time, orders by status, top products, AOV, low stock, refunds) in the store timezone                                                                                                                                                |
| 2.9 | CSV import/export                | Product/variant export, import by SKU with per-row validation and error report                                                                                                                                                                                                                                    |

## Acceptance criteria (from the roadmap)

- No price, discount, tax, shipping cost or total is taken from the browser. The checkout test posts tampered values and asserts that they are ignored.
- Order item, address and money snapshots survive later product, price and address changes (tested).
- Stock cannot go negative unless backorders are allowed. Insufficient stock and changed prices are reported clearly. Concurrency tests are in the `mysql` group.
- An end-to-end storefront purchase succeeds using the explicitly configured manual payment method. The order is never marked paid automatically.
- Every admin route has permitted and forbidden tests.
- Admin and storefront key flows are verified in the browser.
