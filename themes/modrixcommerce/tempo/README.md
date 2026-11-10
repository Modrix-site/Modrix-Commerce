# Tempo Outlet

Responsive storefront theme based on the supplied Tempo MagicPath reference:
https://designs.magicpath.ai/v1/richly-garden-5422

## Installation

Upload `dist/ecomodular-tempo-1.0.0.zip` at **Admin → Appearance → Upload theme**.
For a source installation, the package is in `themes/modrixcommerce/tempo`.
Run `npm run build` after installing the browser source, then preview or activate
**Tempo Outlet** in Appearance. Installation does not activate the theme or
import demonstration products.

## Customizer

- **General:** platform typography, palette presets and colors.
- **Header:** wordmark or uploaded logo, search, navigation colors and benefits.
- **Homepage:** theme controls plus 12 editable sections. The two hero
  sections form the carousel; each tile has an image upload, title, caption
  and validated destination. Reorder or remove sections and select real products
  for recommendations. Two adjacent wrapped tile sections with the second title
  empty form one collection grid.
- **Catalog, Product, Cart, Checkout:** platform page controls, alongside the
  theme's labels and settings. Filters, pagination, variant selection, gallery,
  stock checks, cart updates, coupon validation, shipping and payment methods
  use platform data and actions.
- **Footer:** shared Navigation menus, newsletter text and optional legal links.

The size shortcut destinations are editable links. Set each to the URL produced
by selecting the matching size in the catalog filter. Defaults search the
catalog for the size text; they do not assume attribute IDs in a merchant store.
Shared header and footer menus are managed in **Admin → Navigation** and survive
theme switches. Replace the initial campaign search links with your categories
or filtered catalog URLs as needed.

## Extensions

Existing home, catalog, product, cart and checkout slots remain connected.
Product cards and header actions support visual extension placements. Global
overlays and notifications remain in the platform layout. Newsletter submission
is provided by the optional `modrixcommerce/newsletter` extension through its footer
slot; the theme does not display an unconnected subscription form. Reviews,
product labels, sales, recommendations and payment modules use their normal
platform interfaces. Theme installation never enables an extension automatically.

Save buttons store product IDs and names on the shopper's device and synchronize
across open tabs. The header heart opens a drawer with product links and removal
controls. Prices and availability refresh from the platform when a product is
opened. This is a device-local list rather than an account wishlist.

## Demo content

The optional importer adds five demonstration products and one category and is
safe to repeat. Import only from the Appearance demo action when desired. The
reference uses the same five campaign images throughout; these are bundled in
`assets/` so storefront rendering does not depend on MagicPath availability.

## Validation

`php artisan test --compact tests/Feature/Themes/TempoThemeTest.php`

`npm run types:check` and `npm run build`
