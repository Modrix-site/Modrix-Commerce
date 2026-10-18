# Vellaro Fashion 1.0.0

Based on your MagicPath reference sharp-creek-3741.
Project 457377130226003968; component 457377712647077888;
pinned revision 457377712647077889.

Upload the ZIP in Admin > Appearance > Upload theme. Preview before activating.
On another platform checkout, review the theme source and run npm run build after installation.
This package requires the platform theme frontend entries and extension footer slots.

The independent settings.json contains 86 settings: header and logo, colors and
spacing, six campaigns with direct image uploads, four collection image tiles,
catalog columns/filter/image controls, product service copy, cart and footer.
Core palette and font controls remain available. Homepage product sources and
row titles are managed in the homepage section editor. Global Navigation menus
take precedence over theme default links.

Own files: src/index.tsx, catalog.tsx, product.tsx, cart.tsx, header.tsx, footer.tsx,
components.tsx and scoped style.css. Four MagicPath reference images are bundled.
Optional demo.json imports five simple fashion products and three categories.
It is additive, never automatic, and does not modify existing products.

Real products, active variants, attribute selectors/swatches, variant images,
prices, inventory, stock validation, cart quantities, coupons, checkout, shipping,
payment gateways and customer accounts use the core platform. The theme does
not implement mock checkout, hardcoded colors or fake availability. Existing
product labels and sales render from extension data; product/home extension
slots and the enabled Newsletter footer remain connected. Payment gateways
are chosen and configured in the platform Payments page, then used at checkout.
Saved-for-later hearts are local to this browser/device, not an account wishlist.
The bundled Frequently Bought Together extension update accepts the live selected
variant and includes it in bundle totals and cart additions. Use the updated
platform extension files when installing this theme on another checkout.

Reference-only Home/Catalog/Product design-switch buttons are omitted.
System sans-serif typography is portable and respects core font settings.
