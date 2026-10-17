# Page settings (Header, Catalog, Product, Cart, Checkout) — design

- **Date:** 2026-10-17
- **Status:** Approved in conversation; awaiting review of this document
- **Sub-project:** 1 of 2. Sub-project 2 (extension platform: more storefront slots, extension settings in customizer tabs, choosing where extension blocks show) gets its own spec.

## Goal

Merchants open a customizer tab and find settings for that page:

- Header, Catalog, Product, Cart and a new Checkout tab.
- The settings work the same in all 11 themes, so they survive a theme switch.
- Every setting starts on "Theme default", so no store changes until a merchant sets one.

## 1. Fields

Core design fields in `ThemeSchema`, one prefix per tab. `CustomizerTabs` maps `header.`, `catalog.`, `product.`, `cart.` and `checkout.` keys to their tab, and gains a `checkout` tab (label "Checkout") between Cart and Footer. Each tab shows its fields in one group named after the page (for example "Catalog page"), after "Fonts and buttons".

"Default" below is the theme's behaviour when the merchant leaves the setting alone; a theme manifest may set its own default.

### Header (`header.*`)

| Key | Type | Options / range | Default |
|---|---|---|---|
| `header.logo_height` | integer (px) | 24–96 | theme |
| `header.background` | color | | theme |
| `header.text` | color | | theme |
| `header.search` | select | `bar`, `icon` | theme |
| `header.menu_align` | select | `left`, `center`, `right` | theme |
| `header.cart_icon` | select | `bag`, `cart` | theme |
| `header.cart_count` | boolean | | on |
| `header.account_link` | boolean | | on |

### Catalog (`catalog.*`)

| Key | Type | Options / range | Default |
|---|---|---|---|
| `catalog.columns_desktop` | select | `2`–`5` | theme (falls back to `layout.grid_columns`) |
| `catalog.columns_mobile` | select | `1`, `2` | `2` |
| `catalog.per_page` | integer | 12–48, step 4 | 24 |
| `catalog.default_sort` | select | `StorefrontCatalog::SORTS` | `newest` |
| `catalog.filters` | boolean | | on |
| `catalog.image_ratio` | select | `square`, `portrait`, `landscape` | theme |
| `catalog.hover_image` | boolean | | off |
| `catalog.quick_add` | boolean | | off |
| `catalog.badges` | boolean | | on |
| `catalog.show_brand` | boolean | | off |

### Product (`product.*`)

| Key | Type | Options / range | Default |
|---|---|---|---|
| `product.thumbnails` | select | `side`, `bottom` | theme |
| `product.zoom` | boolean | | off |
| `product.show_sku` | boolean | | off |
| `product.stock_note` | select | `off`, `low` (≤ 5 left), `always` | `off` |
| `product.description` | select | `open`, `tabs`, `accordion` | `open` |
| `product.related` | boolean | | on |
| `product.related_count` | integer | 2–12 | 4 |
| `product.upsells` | boolean | | on |
| `product.quantity` | boolean | | on |

### Cart (`cart.*`)

| Key | Type | Options / range | Default |
|---|---|---|---|
| `cart.free_shipping_bar` | boolean | | off |
| `cart.cross_sells` | boolean | | off |
| `cart.coupon` | boolean | | on |
| `cart.trust_badges` | boolean | | off |
| `cart.continue_link` | boolean | | on |

`cart.coupon` replaces the theme-level `cart_show_coupon` the shared cart page reads today; when both exist, the core field wins.

### Checkout (`checkout.*`)

| Key | Type | Options / range | Default |
|---|---|---|---|
| `checkout.summary_side` | select | `right`, `left` | `right` |
| `checkout.sticky_summary` | boolean | | on |
| `checkout.company` | boolean | | on |
| `checkout.address_line2` | boolean | | on |
| `checkout.phone` | select | `hidden`, `optional`, `required` | `optional` |
| `checkout.order_note` | boolean | | on |
| `checkout.terms` | boolean | | off |
| `checkout.trust_badges` | boolean | | off |
| `checkout.heading` | string (≤ 60) | | "Checkout" |
| `checkout.button_label` | string (≤ 40) | | "Place order" |
| `checkout.summary_images` | boolean | | on |
| `checkout.coupon` | boolean | | on |

Checkout fonts and buttons keep following the Cart tab's "Fonts and buttons" set.

## 2. Server behaviour

- **Catalog:**
    - category, brand and search listings paginate by `catalog.per_page`;
    - with no `sort` in the request, they use `catalog.default_sort`;
    - a visitor's own sort choice still wins.
- **Checkout validation (`PlaceOrder` request rules):**
    - `checkout.phone = required` makes the phone required;
    - `hidden` drops any submitted phone;
    - a hidden company, address line 2 or order note is dropped, never stored;
    - `checkout.terms` on requires an accepted `terms` checkbox, with the message "Please accept the terms to place your order.";
    - `checkout.coupon` off rejects a coupon submitted at checkout (a coupon already applied in the cart stays).
- **Free-shipping bar:**
    - the cart page gets `freeShipping: {threshold, remaining}` (Money, display currency): the lowest `free_over` among active shipping methods that ship to the cart's country (or any country when none is known yet);
    - absent when no method has a threshold.
- **Cross-sells:** the cart page gets up to 4 products related to the cart's items (the related-products feature), excluding items already in the cart and unavailable products.
- **Terms link:** the published page with slug `terms`; when there is none, the checkbox text has no link.

## 3. Storefront

- **`useStoreSettings()`** (`resources/js/hooks/use-store-settings.ts`): typed access to these keys with their defaults applied, from `storefront.theme.settings`.
- **Shared components** in `resources/js/components/storefront/`:
    - `FreeShippingBar`: "You're :amount away from free shipping", then "You've got free shipping!";
    - `ProductGallery`: thumbnails side or bottom; hover zoom on desktop, tap to open on phones;
    - `DescriptionPanels`: open, tabs or accordion over description and specifications;
    - `QuickAddButton`: products without options add one; products with options go to the product page;
    - `CartCrossSells`;
    - `StockNote`;
    - `TrustBadges`: secure payment, easy returns, support; icons and translated text;
    - `TermsCheckbox`.
- **Visual toggles** become attributes on the storefront root, so each theme's CSS can style them its own way:
    - `data-catalog-columns`, `data-catalog-columns-mobile`, `data-image-ratio`, `data-hover-image`;
    - `data-header-search`, `data-menu-align`;
    - CSS variables `--header-logo-height`, `--header-bg`, `--header-fg`.
- **Shared rules** in `resources/css/app.css` give the 7 shared-page themes the column, ratio and header behaviour.
- **Texts:** every new storefront text is in the storefront catalogue, translated into es, fr and de.

## 4. Themes

- **Shared pages:** the 7 themes that use them (Aurora, Bloom, Essence, Harvest, Marketplace, Noir, Prism) get everything through `listing.tsx`, `product.tsx`, `cart.tsx`, `checkout.tsx`, `product-card.tsx`, `site-header.tsx` and `storefront-layout.tsx`.
- **Own pages:** Nord, Voltora, Vellaro and Hearth, plus the Atelier and Electronics chrome, use the same hook and components in their own files, styled to match each theme.
- **Ownership:** those files are another developer's uncommitted work; edits to them stay in the working tree and are listed in the final report.
- **Hidden fields:** a theme can hide a field it cannot support through `customizer.hide_core_fields`, as today.

## 5. Out of scope

- Extension slots, extension settings in tabs and block placement (sub-project 2).
- A multi-step checkout.
- Express checkout buttons.
- Separate checkout fonts.

## Testing

- **Schema:**
    - every new field validates;
    - out-of-range values are rejected;
    - each prefix lands in its tab, including the new Checkout tab.
- **Catalog:** `per_page` and `default_sort` apply; a request `sort` overrides them.
- **Checkout:**
    - phone required, optional and hidden;
    - terms required;
    - hidden company, line 2 and order note are not stored;
    - coupon off at checkout.
- **Cart:**
    - free-shipping threshold and remaining amount, with and without a threshold;
    - cross-sells exclude cart items.
- **Translations:** new texts exist in en, es, fr and de.
- **Browser:**
    - each tab's settings on one shared-page theme (Aurora) and one own-page theme (Nord);
    - the catalog and product page at phone width.
