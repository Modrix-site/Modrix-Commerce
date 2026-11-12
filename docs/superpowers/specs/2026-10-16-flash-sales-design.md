# Flash Sales extension — design

- **Date:** 2026-10-16
- **Status:** Approved (answers: build on top of the uncommitted tree and commit only these changes; coupons apply on top; optional per-product override; countdown plus banner)

## Goal

Merchants run time-limited sales:

- pick the products in a sale, or switch "Include in flash sale" on in a product's editor;
- give each sale a **percentage or fixed-amount** discount;
- shoppers see the reduced price, the original price crossed out, a "−20%" or "−$5" badge, and a countdown;
- the cart and checkout charge exactly the price shown.

## Core extension points (new, general purpose)

1. **Catalog sale prices**:
    - **Contracts:**
        - `App\Domains\Catalog\Pricing\SalePriceProvider::offers(list<int> $productIds, CarbonInterface $now): array<int, SaleOffer>`;
        - `SaleOffer` (`type` percent|fixed, `value`, `name`, `endsAt`, `currency` for fixed amounts) with `apply(Money): Money` (never below zero) and `badge(): string`.
    - **`SalePrices`:** a singleton registry.
        - It runs every provider, keeps the offer that gives the lowest price per product, and memoises per request (`preload()` for listings).
        - `forVariant(ProductVariant, currency): ?array{price, original, offer}`.
        - A fixed-amount offer applies only to prices in its own currency.
    - **Used by:**
        - `StorefrontCatalog::card` (price, compare-at = original, `sale` {badge, ends_at});
        - the storefront product page (per variant);
        - `CartPricer` (line unit price).

        So the displayed price and the charged price always agree. Coupons then apply to the sale price.

    - **Capability:** `catalog.sale_prices` in `Manifest::CAPABILITIES`.
2. **Admin product-editor slot** `admin.product.sidebar` (context: product). The editor renders module panels in its sidebar (edit page only).

## Module `modules/modrixcommerce/flash-sales`

- **Tables:**
    - `flash_sales`: name, discount_type (percent|fixed), discount_value, starts_at, ends_at, is_active, show_banner, timestamps;
    - `flash_sale_products`: flash_sale_id, product_id, discount_type and discount_value both nullable (an override), unique (sale, product).
- **Offer rules:** a sale gives an offer while `is_active` and `starts_at ≤ now < ends_at`. A product's override replaces the sale default.
- **Admin pages:**
    - Marketing → Flash Sales: a list with status (Scheduled, Live, Ended, Off) and product count;
    - create and edit: name, dates, type and value, active, home banner;
    - products: search and add, per-product override, remove.
- **Product editor panel:** "Flash sale" with a toggle and a sale select (upcoming or live sales) plus an optional override. It saves through the module's own route (`products.update` + `flash_sales.manage`).
- **Storefront:**
    - **Product page:** under the price, a notice: "⚡ −20% · Weekend Flash Sale · Ends in 02:14:09" (core `SaleNotice`, every theme). The countdown can be switched off with the module setting `show_countdown`.
    - **Cards:** a "⚡ Flash sale −20%" tag next to the price (core `SaleTag`, every theme card).
    - **Home banner:** via `home.after_featured`, for live sales with `show_banner`: the sale name, a countdown and up to 8 product cards.
- **Validation:**
    - percentage: 1–90;
    - fixed amount: more than 0, in money format;
    - `ends_at` after `starts_at`;
    - name: 1–120 characters.
- **Permission:** `flash_sales.manage` (Marketing).
- **Audit:** `flash_sale.created`, `flash_sale.updated`, `flash_sale.deleted` and `flash_sale.products_changed`.

## Testing

- **Core:** with a fake provider:
    - card, product page and cart unit price;
    - a coupon on top;
    - fixed amounts are ignored for other currencies;
    - no providers leaves prices unchanged.
- **Module:**
    - create and update validation;
    - adding products and overrides;
    - scheduled or ended sales give no discount;
    - the best of overlapping sales wins;
    - percent and fixed;
    - the price never goes below zero;
    - the product toggle endpoint;
    - countdown and banner props;
    - permissions;
    - an order placed at the sale price.

## Constraints

- **The tree has 248 uncommitted changes that aren't mine.** Shared files are snapshotted before editing, and only the snapshot→edit diff is committed (`git apply --cached`).
- **Known limitation:** listings sort by the regular price (the SQL `min_price`), not the sale price.
