# Hearth Home 1.0.0

Furniture storefront adapted from the user-provided MagicPath Hearth design:
https://designs.magicpath.ai/v1/lively-peak-7457
Project 457462583792922624, component 457462658682200064, revision 457480733833986048.

## Install

Upload ecomodular-hearth-1.0.0.zip under Admin > Appearance > Upload theme.
This version uses the platform's theme-owned header, footer and product entries.
Review browser source and run `npm run build` after installation on another checkout.
Preview before activating; installing does not change the active theme or catalog.

## Customize

Appearance > Hearth Home > Customize > Hearth storefront contains the separate
settings.json controls. Image fields accept direct PNG/JPEG/WebP uploads through
the platform's sanitized image uploader. Save design to publish changes.
The 110 settings cover header, hero, categories, shortcuts, stories, promotions,
assurances, catalog, product services, cart and footer. Core colors/type remain
editable. Header/footer menu links are managed through Admin > Navigation.
Homepage product sources and counts remain in the home-page section editor.
The footer newsletter uses the Newsletter extension when enabled; it hides when
the extension is disabled. Its consent and subscription storage remain in that extension.

## Files

- theme.json: manifest, defaults, frontend entries and homepage product sources
- settings.json: independent customizer schema
- src/header.tsx, footer.tsx, index.tsx, catalog.tsx, product.tsx, cart.tsx
- src/components.tsx: theme cards and reusable helpers
- src/style.css: scoped responsive styles
- assets/: the four reference design images, bundled for installation
- demo.json: optional sample furniture catalog, imported only when requested

Commerce uses platform product/variant IDs, stock checks, prices and cart actions.
Currency/language selectors use the platform preferences. Product selectors use
the shared attribute selector. No mock payment or checkout implementation exists.
Saved-for-later hearts are stored on the current browser/device.

The reference's preview-only Home/Catalog/Product tabs are omitted from the live store.
Typography uses portable system and Georgia stacks instead of hotlinking third-party fonts.

