# Cross-sell & Upsell extension — design

Date: 2026-10-18. Status: approved by the user ("go ahead and build").

## Goal

A bundled extension (`modrixcommerce/cross-sell-upsell`) that lets the merchant
create **offers** — rules that suggest products — shown on the product page,
in an add-to-cart popup, in the cart and as a checkout add-on ("order bump"),
optionally at a discounted price, with per-offer stats. It works in every
theme because it only uses shared storefront places and the global overlay.

The built-in hand-picked "related" and "upsell" lists stay as they are.

## What the merchant sets up (Admin → Marketing → Cross-sell & Upsell)

An offer has:

- **Name** (admin only) and **heading** (shown to shoppers; a default per type).
- **Type**: `upsell` (a better or pricier alternative) or `cross_sell` (an add-on).
- **Trigger**: `any` product, specific `products`, or `categories`.
- **Suggestions**: `manual` (picked products, in order) or `automatic`
  (same category as the trigger product; for upsells only products priced
  higher than it). At most 12 manual products.
- **Where it shows**: product page, add-to-cart popup, cart, checkout (any mix).
- **Discount** (optional): percent (1–90) or a fixed amount per unit.
- **Priority** (higher first), optional **start/end** dates, **active** switch.
- **Stats**: views, adds, orders, revenue (per currency).

## What shoppers see

- **Product page** block (default under "add to cart"; movable to the other
  product places): the best matching offer's products, each with Add (single
  variant) or Choose options (several variants), offer price shown with the
  regular price crossed out.
- **Add-to-cart popup** (global overlay): after a regular add to cart,
  "Added to your cart" with the product, then up to 3 suggestions from the
  best matching popup offer, and "View cart" / "Continue shopping".
- **Cart** block (default under the items): "Complete your order" from offers
  matching any cart product.
- **Checkout** block (default above "Place order"): one tick box per bump
  product (max 2); ticking adds it, unticking removes it.

Never suggested: the product itself, products already in the cart, hidden,
priceless or sold-out products.

## Platform changes (small, general)

1. **Pricing step order.** `CartPricer::addStep($step, $priority)`; core steps
   are DISCOUNTS=20, SHIPPING=30, TAX=40; modules adjusting line prices use
   `CartPricer::LINE_ADJUSTMENTS` (10). Default stays "after tax" (50).
2. **Offer discounts kept apart from coupons.** `PricedLine::$offerDiscount`
   and `$offerLabel`; `PricedCart::$offerDiscountTotal` and
   `$couponDiscountTotal`. `$discount` / `$discountTotal` remain the totals,
   so tax, order lines and grand totals need no change. Coupons are computed
   on what is left after offer discounts and add to the line discount.
   The order's coupon code and the coupon redemption use the coupon part only.
   Cart/checkout show "Offer savings" and "Discount (CODE)" as separate rows.
3. **`CartCheckedOut(cartId, orderId)`** event, dispatched inside the order
   transaction before the cart is emptied (for attribution).
4. **"Just added" signal.** The cart add action flashes
   `cart.just_added = {variant_id, quantity}` to the session.
5. The electronics-style product page gains the four product places.

## Pricing safety

- Prices always come from the server. The add endpoint re-checks that the
  offer is live, shows in that place, the product is one of its suggestions
  and the variant is purchasable.
- A line's offer discount applies only while: the line was added from that
  offer, the offer is live and still suggests the product, and a trigger
  product is in the cart. Discounted units ≤ trigger units in the cart.
- Fixed discounts never exceed the unit price.

## Data (module tables)

- `cross_sell_offers` — the offer fields above plus `views`, `adds` counters.
- `cross_sell_cart_lines` — (cart_id, variant_id) → offer_id; deleted with
  the cart, cleaned up when the line leaves the cart.
- `cross_sell_conversions` — (offer_id, order_id, product_id, quantity,
  revenue, currency), written from `CartCheckedOut`.

Views are counted once per session per offer and place.

## Known limits

- Signing in merges a guest cart into the account cart; offer attribution of
  the guest cart is not carried over (the items stay, at regular price).
- Order pages show one combined discount row (orders store one total).

## Testing

Feature tests in `tests/Feature/Extensions/CrossSellUpsellTest.php` covering
matching, exclusions, the add endpoint's checks, discount pricing with and
without coupons, trigger removal, order totals/coupon redemption, stats and
conversions, the popup flash, the checkout bump add/remove, and admin CRUD
validation. Core pricing tests keep passing.
