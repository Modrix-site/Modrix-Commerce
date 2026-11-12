# Circuit theme: design

**Status:** draft for review · **Date:** 2026-10-18 · **Theme:** `modrixcommerce/circuit` ("Circuit Market")

## Goal

A new electronics storefront theme whose layout, spacing, type scale, colors, header, body and footer follow the
merchant's reference (three screenshots: homepage, collection, product page of a commercial Shopify demo). Every
text, image, color, menu and section is editable in the customizer, on desktop and phone.

## Ground rules

- **Original work.** Circuit copies the reference's _layout and look_, not its files. No images, logos, CSS, markup
  or scripts are taken from the reference store (it is a paid theme). Brand logos (Microsoft, Samsung, Sony, Dell,
  ASUS) and Apple product photos are not shipped.
- **Images:** free stock photos under a licence that allows redistribution (Unsplash or Pexels licence), one per
  demo slot, credited in `themes/modrixcommerce/circuit/assets/CREDITS.md` (photographer, source URL, licence).
  Downloads are listed for approval before they happen. The store's own products always show their own images.
- **Fonts:** an open-licensed (OFL) font that matches the reference's geometric sans as closely as possible,
  bundled as woff2 with its licence file (like Voltora's Nunito Sans). Candidate: _Instrument Sans_. If the
  merchant saves the reference page source into the project, the exact font family and color values are read from
  it; otherwise they are matched from the screenshots.
- **No invented signals.** The reference shows "17 sold in last 18 hours" and "39 people are viewing". Circuit shows
  only real data (real stock count for "Only N left"); invented counters are left out.
- **Platform first.** Commerce stays on the platform: cart, checkout, filters, prices, sale prices, page settings,
  extension blocks and places, newsletter extension, search toggle, rating stars. Circuit only lays them out.

## Look (from the screenshots)

| Token                            | Value (to confirm)                                                          | Used for                                                                 |
| -------------------------------- | --------------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| Header / announcement background | deep navy `#14102a`                                                         | announcement bar, main header                                            |
| Accent                           | coral red `#e5484d`                                                         | sale badges, prices on sale, Subscribe, "Buy Theme"-style highlight link |
| Success                          | green `#1f9d55`                                                             | "Limited stock" badge                                                    |
| Text                             | `#111111`, muted `#6b6b76`                                                  | body, meta                                                               |
| Surface                          | `#ffffff`, light panel `#f4f4f6`                                            | page, footer, filter panels                                              |
| Radius                           | pills 999px on search, buttons 999px (Add to cart, Subscribe), cards 8px    |                                                                          |
| Type                             | geometric sans; titles 600–700, small UI text 13–14px, section titles ~22px |                                                                          |

## Header (all pages)

1. **Announcement bar** (navy): ‹ › rotating messages left; Help Center · About Us · Our Store · currency/region
   selector right.
2. **Main header** (navy): logo (image or text), rounded search pill ("I'm looking for…"), account, wishlist with
   count, cart icon with count and cart total.
3. **Menu row** (white): store navigation with dropdowns, "Sale", one highlighted link (accent color), and
   "Recently viewed products" on the right (opens a panel of products viewed on this device).
4. **Category rail** (desktop, left edge): a vertical strip of category icons; the menu button opens all categories.
5. **Phone:** compact header with menu, logo, search toggle, account, cart; announcement bar stays.

## Homepage sections (in order, each one a customizer section)

1. Hero slider (large, left) + two promo tiles (middle tall, right stacked pair). → `hero` + `promotion_grid`
2. Category circles with labels (Daily Deals, Macbook/PCs, …), including highlighted circles. → `category_grid`
3. "Today's Best Deals" product row with a countdown ("Hurry up! Offer ends in") driven by a date setting. →
   `featured_products`
4. Voucher strip: dark green rounded banner, text, coupon code chip, "Shop Sale" button. → `promo_banner`
5. "Popular Picks": product list left and right, campaign card in the middle. → `featured_products` + campaign tile
6. Promo grid: two small tiles left, one tall tile below, large "Limited Edition" tile right. → `promotion_grid`
7. "Best Selling" product row with "View All". → `featured_products`
8. "Featured Brands": a row of the store's brands (logo or name). → `brand_strip` (store brands)
9. Image + product spotlight ("Small Size Big Sound") with a product card and dots. → `image_with_text`
10. Service features (three icons: free shipping, free returns, support). → theme settings
11. "Online Support 24/7": call / chat / email cards. → theme settings
    Extension places: `home.after_featured` after section 7.

## Catalog (collection, brand, search)

Centered breadcrumb and title; left sidebar: platform filters (category, availability, price range with slider,
options) as collapsible groups, then a promo card ("Here's your 15% coupon" style, editable). Toolbar: result count,
grid density switch (2/3/4/list), sort. Product cards: image on light square, discount badge (−10%), "Limited
stock" / labels, name (two lines, clickable), price + compare-at; hover second image and quick add. Round
pagination. Extension places: `catalog.before_products`, `product.card`.

## Product page

Left: vertical thumbnails + main image with zoom; right: discount badge, brand, title, rating stars place, price +
compare-at, short description, delivery info box (three editable lines with icons), variant buttons with
"Size guide" link (editable page link), Ask a question / Share, "Hurry up! Only N left" with a stock bar (real
stock, shown at or below an editable threshold), quantity + Add to cart (pill) + wishlist, Buy it now, meta
(SKU, availability, categories, brand), "Guarantee safe checkout" payment badges (editable list; drawn as simple
labels, no third-party logos), accordions (Description, Shipping and Returns, Return Policies — editable texts).
Below: scrolling benefits marquee (editable), related products ("People Also Bought"), recently viewed (this
device). Extension places: all product places. Sticky add to cart on phones.

## Cart

Lines with image, options, quantity, remove; summary with free-shipping bar, coupon, totals, checkout button,
payment badges; cross-sells row; empty state in the theme's style.

## Footer

Light grey: About the store (text, phone, email, social icons), three link columns from Navigation, "Sign up to
newsletter" (Newsletter extension block when enabled, otherwise hidden), bottom row: currency, copyright, payment
badges, back-to-top button.

## Customizer

Settings grouped by tab (General, Header, Homepage, Catalog, Product, Cart, Footer): colors above, logo, all texts
and links, announcement messages, rail categories, countdown date, service and support cards, delivery lines,
accordion texts, marquee items, payment badge labels, footer texts and socials. Store-wide page settings (columns,
badges, hover image, quick add, gallery, stock note, …) are honoured. Defaults reproduce the reference.

## Files

`themes/modrixcommerce/circuit/`: `theme.json`, `settings.json`, `demo.json` (categories and demo products using the
stock photos), `README.md`, `LICENSE`, `assets/` (photos, font + licence, `CREDITS.md`, screenshot),
`src/` (`header.tsx`, `footer.tsx`, `index.tsx`, `catalog.tsx`, `product.tsx`, `cart.tsx`, `components.tsx`,
`style.css`). New platform section types (`brand_strip`, countdown fields) only if an existing type cannot carry
the content; any such change is generic and tested.

## Testing

- `CircuitThemeTest`: manifest valid, every setting on a tab, demo import is idempotent, home/catalog/product/cart
  render with Circuit and pass their places and slots, countdown and stock bar use real data.
- Visual check: full-page screenshots at 1280px and 390px compared with the reference screenshots, slice by slice.
- Existing suites stay green; types, lint, build.

## Open points for the merchant

1. Font and exact colors: from the saved page source if provided, otherwise matched from screenshots.
2. Circuit, like the other bundled themes in this working copy, relies on platform features that are still
   uncommitted (filters, footer place, payment slots). It stays uncommitted alongside them, or is committed once
   they are.
