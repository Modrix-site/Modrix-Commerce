# Voltora 1.0.0

Built from the publicly shared MagicPath component merrily-hour-1817,
component 456405628496285696, published revision 456415732427804672.
Reference: https://designs.magicpath.ai/v1/merrily-hour-1817

Upload the ZIP in Appearance > Upload theme. Preview before activating.
On another checkout, review src/ and run npm run build after installation.
Requires the platform theme frontend entries, custom font and customizer APIs.
Installing does not activate the theme or import demo products.

The separate settings.json contains 120 controls, plus core palette, font and
page design controls. Images upload directly into each slide, promotional tile,
spotlight tab, lifestyle tile, logo, catalog banner and cart promotion field.
Homepage product sources, counts and titles use the core section editor. Other
core section types also render through HomeSections. Menus use Admin Navigation,
with real page, category and custom links; existing merchant menus take priority.

The theme owns index.tsx, catalog.tsx, product.tsx, cart.tsx, header.tsx,
footer.tsx, components.tsx and scoped style.css. Nunito Sans is bundled under
its SIL Open Font License in assets/NunitoSans-OFL.txt, with system fallback
for characters outside the bundled Latin font subset.

Commerce uses real catalog prices, currencies, stock, swatches, variant images,
quantities, sale discounts, coupon validation, Buy now/sticky-cart controls,
shipping, taxes, payment gateways and customer accounts. Product-label and sale
extension data remain connected. No fake Express shipping, stock, ratings or
20-percent discounts are copied from the static design prototype.

Extension compatibility follows the platform's public contract: home, product,
cart totals, checkout payment method, footer, global overlay and order confirmation
slots. Future extensions using those hooks render without a theme edit after
normal frontend builds. Extensions introducing new APIs or placements may need
integration. Unknown future extensions cannot be guaranteed compatible.
Identical home/footer widgets are rendered once in the footer; distinct props
are preserved. The Newsletter extension owns consent and subscriptions.
The theme does not install, enable or configure payment extensions.

Optional demo.json imports six simple electronics products into one category.
It is additive and never automatic. Change demo specifications before selling.
Saved-for-later is an optional device-local list, not an account wishlist.
The reference's preview-only page switchers and mocked cart state are replaced
by real routes and core commerce operations.
