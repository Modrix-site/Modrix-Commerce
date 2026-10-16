# Theme customizer: tabs and per-area typography and buttons — design

- **Date:** 2026-10-16
- **Status:** Draft for review
- **Answers:**
    - tabs: General, Header, Homepage, Catalog, Product, Cart and Footer;
    - styling is set per tab and inherits from General;
    - styles cover headings, body text, buttons and prices.

## Goal

The theme customizer (Admin → Themes → Customize) becomes a tabbed page instead of one long list of collapsible groups. The page tabs (Homepage, Catalog, Product and Cart) each start with a **Fonts and buttons** panel, so a merchant can style each part of the store:

- **Headings:** font family, size, colour and weight.
- **Body text:** font family, size and colour.
- **Buttons:** background, text colour, hover background, font family, size and corner radius.
- **Prices:** size, colour and sale colour.

General holds the store-wide values. A page tab only overrides what it sets, and "Theme default" means inherit.

## 1. Tabs

| Tab      | Contents                                                                                                   |
| -------- | ---------------------------------------------------------------------------------------------------------- |
| General  | Colours, Typography, Shape and spacing, Layout, Branding (logo, favicon), and **Fonts and buttons (store-wide)** |
| Header   | Header, announcement, Navigation (menu links, help link)                                                    |
| Homepage | Homepage sections editor; hero, categories, promotions, collections, brand story, shortcuts, assurances     |
| Catalog  | Catalog, listing and card settings; **Fonts and buttons (catalog)**                                        |
| Product  | Product page settings (Buy now, sticky bar, labels); **Fonts and buttons (product)**                       |
| Cart     | Cart settings; **Fonts and buttons (cart)**                                                                |
| Footer   | Footer, contact details and footer columns                                                                  |

- **Placing fields in tabs:**
    - A field goes in the tab its theme gives it (`"tab": "catalog"`).
    - Without one, its `group` is mapped by name, without regard to case:
        - hero, categor, promotion, collection, campaign, brand story, shortcut, assurance, homepage → Homepage;
        - catalog, listing, card → Catalog;
        - product → Product;
        - cart → Cart;
        - header, announcement, navigation, menu → Header;
        - footer, contact → Footer;
        - anything else → General.
- **Inside a tab:** groups stay as collapsible sections, in their current order. A tab with no fields is hidden.
- **Remembering the tab:** the active tab is kept in the URL (`?tab=catalog`) so a save returns to the same tab. It uses the existing `components/admin/tabs.tsx`.
- **Unchanged:** Preview, Save and Reset stay in the page header and apply to all tabs. Validation errors mark the tab that has them, as in the product editor's section menu.

## 2. New design fields (core, every theme)

Added to `ThemeSchema::designFields()` for each area in `general`, `home`, `catalog`, `product`, `cart`. Every field is nullable, and null inherits (General falls back to the theme's own CSS).

| Key (`style.{area}.…`) | Type   | Values                                               |
| ---------------------- | ------ | ---------------------------------------------------- |
| `heading_font`         | font   | font stacks and theme fonts                          |
| `heading_size`         | select | 20, 24, 28, 32, 36, 40, 48, 56, 64 (px, page titles) |
| `heading_weight`       | select | 400, 500, 600, 700, 800                              |
| `heading_color`        | color  |                                                      |
| `body_font`            | font   |                                                      |
| `body_size`            | select | 13, 14, 15, 16, 17, 18 (px)                          |
| `body_color`           | color  |                                                      |
| `button_bg`            | color  |                                                      |
| `button_text`          | color  |                                                      |
| `button_hover_bg`      | color  |                                                      |
| `button_font`          | font   |                                                      |
| `button_size`          | select | 12, 13, 14, 15, 16, 18 (px)                          |
| `button_radius`        | select | `ThemeSchema::RADII`                                 |
| `price_size`           | select | 14, 16, 18, 20, 24, 28 (px)                          |
| `price_color`          | color  |                                                      |
| `price_sale_color`     | color  |                                                      |

- **Grouping:** fields are grouped as "Fonts and buttons", and their tab comes from the area.
- **Hiding them:** a theme may hide some with the existing `hide_core_fields`.
- **Validation:** the existing ThemeSchema rules apply (colour hex, select options, fonts).

## 3. CSS output

- **Variables:** `ThemeCss::build` emits `--store-heading-font`, `--store-heading-size`, `--store-heading-weight`, `--store-heading-color`, `--store-body-font`, `--store-body-size`, `--store-body-color`, `--store-button-bg`, `--store-button-text`, `--store-button-hover-bg`, `--store-button-font`, `--store-button-size`, `--store-button-radius`, `--store-price-size`, `--store-price-color` and `--store-price-sale`. Only set values are emitted.
    - **General:** on the existing `.storefront[data-theme="…"]` rule.
    - **Each page area:** on `.storefront[data-theme="…"][data-page="{area}"]`, so page values override General through the cascade.
- **Marking the page:** the storefront layout sets `data-page` from the Inertia page:
    - `storefront/home` → home;
    - `storefront/listing` (and category, brand and search) → catalog;
    - `storefront/product` → product;
    - `storefront/cart` and `storefront/checkout` → cart.
- **Base rules:** emitted once, each with the theme's own value as the fallback (`var(--store-…, <current>)`), so nothing changes until a merchant sets a value:
    - `.storefront :is(h1,h2,h3)` takes the heading family, weight and colour. `h1` takes `--store-heading-size`; `h2` and `h3` scale from it (0.75 and 0.6).
    - `.storefront` takes the body family, size and colour.
    - **Store buttons** take the button variables: `.store-button`, the primary shadcn button inside `.storefront`, `BuyNowButton`, `StickyAddToCart` and `BuyButton`.
    - `.store-price` and `.store-price-sale` take the price variables.

## 4. Theme wiring

- **Core components** use the variables directly (`bg-[var(--store-button-bg,var(--primary))]` and so on):
    - the default product card, product page, cart and checkout buttons;
    - `BuyNowButton`, `StickyAddToCart`, `BuyButton`, `SaleNotice` and `SaleTag`;
    - prices.
- **Theme stylesheets** map their own primary buttons and prices to the variables, with the current value as fallback:
    - Hearth: `.hearth-primary`, `.hearth-add`, prices;
    - Vellaro: `.vellaro-primary`, `.vellaro-add`;
    - Marketplace: `.electronics-button`, prices;
    - Atelier (Prism): `.ecom-…` buttons.
    - These stylesheets belong to the other developer and are uncommitted, so these edits stay in the working tree like the earlier theme edits.
- **Classic themes** (Aurora, Bloom, Essence, Harvest, Noir) work through the core components.

## 5. Testing

- **Schema:** the new fields validate (colour, select and font), and null clears a value.
- **CSS:**
    - General values go on the theme rule and page values on the `[data-page]` rule;
    - unset values emit nothing;
    - values are escaped and invalid ones rejected.
- **Layout:** the `data-page` value is correct for home, listing, product and cart.
- **Customizer:**
    - every theme's fields land in the expected tab;
    - an explicit `tab` wins;
    - save keeps the tab;
    - errors mark their tab.
- **Browser:** set a catalog button colour and a product heading size in Hearth and Marketplace and check both pages. Unset values leave each theme unchanged.

## Out of scope

- Per-section (e.g. per hero) fonts.
- Uploading new font files (theme fonts and the font stacks only).
- Mobile-only sizes.
- Checkout as its own tab (it follows Cart).
