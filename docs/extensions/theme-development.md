# Theme Development

> Status: **design contract (Phase 5 not implemented yet).** The current storefront is a minimal placeholder page.

## Principles

- Themes control **presentation only**. Pricing, cart, checkout, payment and authorization rules stay in the core, so switching themes can never change a total or bypass a check.
- Theme packages contain **no executable PHP**. A theme is a data-only manifest plus a compiled React component bundle registered against stable component contracts.
- Theme defaults are kept separate from merchant overrides, so updating a theme never overwrites a merchant's settings or content.

## Planned manifest (`theme.json`)

```json
{
    "name": "eleydo/aurora",
    "display_name": "Aurora",
    "version": "1.0.0",
    "type": "theme",
    "platform_version": "^1.0",
    "entry": "dist/theme.js",
    "dependencies": {},
    "capabilities": [
        "home",
        "category",
        "search",
        "product",
        "cart",
        "checkout.presentation",
        "account"
    ],
    "assets": ["dist/", "screenshots/"],
    "settings_schema": "settings.schema.json",
    "changelog": "CHANGELOG.md"
}
```

Validation rejects unknown capabilities, incompatible platform versions, and asset paths that escape the package (path traversal).

## Planned component contracts

`Layout`, `Header`, `Footer`, `ProductCard`, `ProductGallery`, `ProductDetails`, `CategoryListing`, `SearchResults`, `CartView`, `CheckoutSteps` (presentation of core-provided state only), `AccountPages`, and content `Sections`.

## Settings

Colors, typography, spacing, logo and favicon are declared in `settings_schema` and stored as `theme.<name>.*` settings. Merchants preview a theme before activating it. Importing demo content requires explicit confirmation before it touches existing content.

A drag-and-drop page builder is deliberately out of scope until section and component contracts are stable.
