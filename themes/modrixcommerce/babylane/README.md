# Baby Lane

Baby Lane is a standalone baby-store theme with a blue scalloped header, yellow campaign strip, patterned homepage, campaign mosaic, collection wordmarks, trending collection tabs, four department shelves, hierarchical catalog filtering, product gallery and customer storefront.

## Install and edit

Install the package from Appearance and preview it before activation. Customize opens the platform’s latest live section editor. Choose Home, Collection, Search, Product, Cart, Page, Header or Footer; add, duplicate, disable, delete or reorder sections and blocks. Edit all campaign text, photographs, destinations, collection/product sources and labels there. Global settings control colors, fonts (including bundled Work Sans), body/card/title sizes, container width, buttons and background pattern. Header navigation can use your real store menu; footer links, social accounts and payment marks are editable blocks. Collection filters come from actual categories, brands, variant options, price, availability and compare-at pricing.

Optional demo import adds 8 categories and 24 products once. It skips existing slugs and SKUs. Product names, descriptions, prices, stock, variants and photography are maintained in Products; category structure is maintained in Categories. The customizer chooses which real collections and products appear. No sample catalog is imported automatically.

Cart, Buy now, checkout, account, password and order pages use platform actions. Reviews, bundles, recommendations and newsletter use installed module slots. The footer newsletter appears when Newsletter is enabled; configure it in the module. There are no invented ratings, sellers or payment integrations. Saved products, comparisons and recently viewed items are stored on the shopper’s device.

Campaign and product imagery is original generated demonstration content. See assets/ASSETS.md for prompts and filenames. Confirm product details and store policies before publishing.

## Design provenance

Designed and built with a MagicPath canvas containing Homepage, Baby Hygiene and Product details previews, then integrated with the native live section runtime. The supplied reference informed layout, proportions, blue/yellow palette and typography. No reference-site brand identity or photos are bundled.

Module and hook coverage is documented in MODULES.md; the current package inventory is in module-compatibility.json.

## Validation

The storefront module contract is covered by six Baby Lane React render tests plus the core theme-runtime checks. Module-specific behavior uses the native backend and UI slots. Gateway credentials, module configuration and live external services are merchant-owned.

Sticky header: enabled by default on desktop and mobile. In the live customizer, open the Header section and toggle Sticky header. The theme’s own logo, search, navigation and module actions remain visible; announcement banners scroll away.
