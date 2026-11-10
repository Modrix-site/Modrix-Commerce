# Prism

An editorial, gallery-calm theme. Centred wordmark, a wide white canvas, near-black type and understated product tiles. Built for considered design, lifestyle and accessories brands — leather goods, stationery and everyday essentials.

## What it shows

- **Custom fields.** Prism declares its own controls under `customizer.fields` — hero eyebrow, hero treatment, wordmark spacing, heading scale, product-tile tone, wishlist and "Explore all" toggles. They appear in **Admin → Appearance → Customize → Design**.
- **Presets.** Three ready-made looks (`gallery`, `paper`, `ink`) that set core and custom fields at once.
- **Dark mode.** Full `dark_colors` declaration.
- **Editorial layout.** Centred header, minimal (borderless) product cards, simple footer, wide container, spacious rhythm.

## Design defaults

| Setting              | Default                    |
| -------------------- | -------------------------- |
| Background           | `#ffffff`                  |
| Foreground / primary | `#111111` (near-black CTA) |
| Body / heading font  | `sans`                     |
| Radius               | `0.25rem`                  |
| Density              | `spacious`                 |
| Container            | `wide`                     |
| Header               | `centered`                 |
| Footer               | `simple`                   |
| Product cards        | `minimal`                  |
| Grid                 | 4 per row                  |
| Product page         | `split`                    |

## Custom fields

| Key                        | Type    | Label                   | Group      | Default                |
| -------------------------- | ------- | ----------------------- | ---------- | ---------------------- |
| `custom.hero_eyebrow`      | string  | Hero eyebrow            | Hero       | `Objects of intention` |
| `custom.hero_tone`         | select  | Hero treatment          | Hero       | `image`                |
| `custom.wordmark_tracking` | select  | Wordmark spacing        | Typography | `wide`                 |
| `custom.heading_scale`     | select  | Heading scale           | Typography | `editorial`            |
| `custom.card_tone`         | select  | Product tile            | Layout     | `stone`                |
| `custom.show_wishlist`     | boolean | Show wishlist on tiles  | Layout     | `true`                 |
| `custom.show_explore_all`  | boolean | Show "Explore all" link | Layout     | `true`                 |

## Install

Drop the `prism/` folder into `themes/modrixcommerce/`, or upload its ZIP under **Admin → Appearance → Upload theme**. The theme is discovered on the next sync; preview it, then activate it.

## Demo content

`demo.json` adds three categories and six products. Slugs and SKUs are prefixed `prism-` so they never collide with real catalog data.

## License

MIT. The bundled demo images are generated artwork and are free to use with this theme.
