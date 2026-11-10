# Nord

Responsive storefront theme based on the supplied [NORD MagicPath reference](https://designs.magicpath.ai/v1/wispy-shore-2966), published revision `459542603235475457`. The theme includes its 15 reference images and matching layout measurements. Theme source is MIT licensed; reference imagery retains its original rights.

## Install

The installable archive is `dist/ecomodular-nord-1.0.0.zip`. Upload it under **Appearance → Upload theme**, rebuild browser assets with `npm run build`, and preview or activate Nord. The package is also included in `themes/modrixcommerce/nord` for local discovery. Installing or discovering the theme does not activate it or import sample products.

The optional demo import adds six products and two categories using `nord-` slugs and `NORD-` SKUs. It does not overwrite existing catalog data. Prices are interpreted in the store's base currency. Demo content is separate from activation.

## Pages and customization

- Homepage: campaign hero, six-product rows, fall editorial, three style cards, runway campaign, designer cards, markdown campaign, and brand editorial.
- Catalog/category/brand/search: campaign banner, beauty editorial, product row, real server filters, sort order, pagination and empty results.
- Product: responsive image gallery, core variant selectors, real availability and prices, quantity, add to bag, optional Buy now, device-local saved products, description and recommendations.
- Bag and checkout: the platform forms, coupons, totals, shipping, payment methods and validation styled to match Nord. Order placement and pricing remain in the core.
- Header and footer: shared Navigation menus, live search, account, bag count, locale/currency support, and optional newsletter extension.

The independent `settings.json` exposes 105 controls, including direct image uploads and campaign visibility, content, destinations, widths, product and bag labels. Saved shared Navigation menus take precedence over the theme defaults. The platform supports twelve default header links for this reference.

At widths below 768px the header becomes a compact wordmark row with a full-width search field and collapsible navigation. Product rails and editorial cards scroll horizontally, catalog results use two columns, galleries and checkout stack, and the core sticky purchase bar remains available. The reference's floating Home/Catalog/Product demo switcher is replaced by real storefront navigation.

## Extension contract

Compatible extensions can register frontend components with the platform slot API. Nord does not whitelist module names or hide supplied slots:

| Slot                      | Context                                               | Placement                                                         |
| ------------------------- | ----------------------------------------------------- | ----------------------------------------------------------------- |
| `home.after_featured`     | none                                                  | After homepage content                                            |
| `catalog.after_products`  | `category`, `brand`, `query` (unused values are null) | Below catalog/search results                                      |
| `product.after_details`   | `product`                                             | After product description; live `variant` is also passed to React |
| `cart.after_totals`       | priced `cart`                                         | Core bag summary                                                  |
| `checkout.payment_method` | priced `cart`                                         | Selected core payment method, with live checkout callbacks        |
| `checkout.after_summary`  | priced `cart`                                         | Core checkout summary, refreshed with server quotes               |
| `storefront.footer`       | none                                                  | Footer extension column                                           |
| `storefront.overlay`      | none                                                  | Global overlays on every page                                     |

Missing or failing frontend modules use the core extension error boundary. Future modules using these contracts need no theme changes. Modules requiring a new placement still need a platform slot; rebuild frontend assets after adding module browser components.

The catalog and checkout summary slots require the accompanying platform updates in this workspace. The ZIP contains the theme source and assets; it does not replace platform controllers or commerce logic.

All theme CSS selectors are scoped to `[data-theme="modrixcommerce/nord"]`. Theme package validation, ZIP installation, demo idempotency, storefront commerce, filters, extension props and checkout quote refresh are covered by the Nord/compatibility test suites.
