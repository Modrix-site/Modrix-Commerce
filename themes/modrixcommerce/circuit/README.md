# Circuit Market

An electronics megastore theme for Modrix Commerce: navy header with a rounded search, a category icon rail and
recently viewed products; a homepage mosaic with a deal countdown, voucher strip, popular picks, promo mosaic,
brands, a product spotlight, service features and support cards; catalog filters with a grid switch; a detailed
product page; cart and footer.

Everything is editable in the customizer:

- **General:** colors, button shape. **Header:** wordmark or logo, announcements, top links, highlighted link.
- **Homepage:** every section (campaign tiles, circles, product rows, voucher, mosaic, brands, spotlight) plus the
  countdown date, voucher code, popular-picks card, service features and support cards.
- **Catalog:** default grid and the coupon card. **Product:** delivery box, size guide, stock threshold, payment
  badges, accordion texts, benefits strip, related and recently viewed headings.
- **Cart** note and badges. **Footer:** about text, contact, social links, newsletter texts, copyright.

Menus come from Navigation. The footer newsletter form uses the Newsletter extension's block. Demo content is
optional (Themes → Import demo). Photos and font credits: `assets/CREDITS.md`.

## Theme-owned customizer (1.1.0)

Circuit's admin editor lives in `src/customizer.tsx`; its field schema and defaults
live in `settings.json` and `theme.json`. Edit these package files to change the
studio or storefront without editing core. The editor composes the supplied
platform controls for validated saves, uploads, section management, resets and
extension settings. Run `npm run build` after browser changes. Existing active
installations must apply the 1.1.0 update in Appearance to use the new declaration;
saved customizations are retained.
