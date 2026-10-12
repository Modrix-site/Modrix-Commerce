# Theme Development

A theme decides how a store looks: colors, typography, spacing, header and footer style, product cards, the product page layout and the home page sections. It cannot change how the store works. Prices, cart, checkout, payments and permissions always run the same core code.

The bundled themes are working examples:

| Theme                | Style                                                                                                    |
| -------------------- | -------------------------------------------------------------------------------------------------------- |
| `ecomodular/essence` | The free default theme: neutral, light, classic header, column footer. Activated on installation.        |
| `ecomodular/noir`    | Dark luxury: gold accents, Didone headings, centered header, minimal cards, three columns, demo content. |
| `ecomodular/bloom`   | Warm and botanical: cream and sage, serif headings, framed rounded cards, dark colors, demo content.     |

## Themes are data, not code

A theme package contains **no PHP, no JavaScript, no HTML and no CSS**. It contains:

- a manifest (`theme.json`);
- images (PNG, JPEG, WebP) and fonts (WOFF2);
- optionally `demo.json`, a README and a license.

Any other file type rejects the package.

The core owns every component and renders the theme's choices from a fixed vocabulary of design settings, layout variants and section types (ADR-031). Every value is validated before it reaches CSS or the page. That is why a theme from any source can be installed without the trust a module needs: it cannot run code on the server or in shoppers' browsers.

If a design needs new components, the platform gains a new section type or variant, or a module adds content through a storefront slot. Themes never ship code.

## Package layout

```text
themes/<vendor>/<name>/
  theme.json            manifest (required)
  assets/               images and WOFF2 fonts (only files in here are served)
  demo.json             optional demo categories and products
  README.md, LICENSE    optional
```

## Manifest reference

The schema is [`theme.schema.json`](theme.schema.json). Add `"$schema": "../../../docs/extensions/theme.schema.json"` for editor help. The platform validates every package with its own validator and shows errors under **Admin → Appearance**.

| Key                                                | Required       | Notes                                                                                                                      |
| -------------------------------------------------- | -------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `name`                                             | yes            | `vendor/theme`, matching the directory.                                                                                    |
| `display_name`, `description`, `author`, `license` | `display_name` | Shown to merchants.                                                                                                        |
| `version`                                          | yes            | Semantic version.                                                                                                          |
| `type`                                             | yes            | `"theme"`.                                                                                                                 |
| `theme_type`                                       | yes            | `"storefront"`.                                                                                                            |
| `platform_version`                                 | yes            | Constraint against the platform version (currently `0.4.0`).                                                               |
| `requires`                                         |                | Modules that must be enabled, e.g. `{"ecomodular/product-reviews": "^1.0"}`.                                               |
| `capabilities`                                     |                | `home.sections`, `custom_fonts`, `dark_mode`, `demo_content`. Required by the matching keys.                               |
| `assets`                                           |                | Directory for images and fonts.                                                                                            |
| `screenshot`                                       |                | An image in `assets`, shown in the theme list.                                                                             |
| `design`                                           |                | Defaults for the design settings below.                                                                                    |
| `dark_colors`                                      |                | Colors used when the shopper's device is in dark mode (`dark_mode`).                                                       |
| `fonts`                                            |                | `[{"family": "Name", "file": "assets/fonts/name.woff2", "weight": "400 700", "style": "normal"}]` (`custom_fonts`, max 6). |
| `sections`                                         |                | `{"home": [ … ]}` (`home.sections`, max 12 sections).                                                                      |
| `demo_content`                                     |                | Path to `demo.json` (`demo_content`).                                                                                      |
| `changelog`                                        |                | `[{"version", "date": "YYYY-MM-DD", "notes"}]`, shown to merchants before they apply an update.                            |

### Design settings

Every theme has the same settings; the theme supplies defaults and merchants may override any of them.

| Key                                                                                                                                                                                     | Values                                                                                                             |
| --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| `color.background`, `color.foreground`, `color.primary`, `color.primary_foreground`, `color.muted`, `color.muted_foreground`, `color.accent`, `color.accent_foreground`, `color.border` | `#rrggbb`                                                                                                          |
| `font_body`, `font_heading`                                                                                                                                                             | `sans`, `system`, `humanist`, `geometric`, `rounded`, `serif`, `didone`, `mono`, or `custom:<Family>` from `fonts` |
| `radius`                                                                                                                                                                                | `0`, `0.25rem`, `0.375rem`, `0.5rem`, `0.625rem`, `0.75rem`, `1rem`, `1.5rem`                                      |
| `density`                                                                                                                                                                               | `compact`, `comfortable`, `spacious` (space between home sections)                                                 |
| `container`                                                                                                                                                                             | `narrow`, `default`, `wide` (page width)                                                                           |
| `layout.header`                                                                                                                                                                         | `classic` (logo, search, account), `centered` (centered logo), `minimal` (inline categories)                       |
| `layout.footer`                                                                                                                                                                         | `simple`, `columns`                                                                                                |
| `layout.product_card`                                                                                                                                                                   | `standard`, `minimal` (borderless, centered), `framed` (bordered panel)                                            |
| `layout.grid_columns`                                                                                                                                                                   | `"3"`, `"4"`                                                                                                       |
| `layout.product_page`                                                                                                                                                                   | `split` (gallery beside details), `stacked`                                                                        |
| `logo`, `favicon`                                                                                                                                                                       | `asset:assets/…` (theme image) or a merchant upload                                                                |
| `announcement`                                                                                                                                                                          | Plain text, up to 160 characters (bar above the header)                                                            |
| `footer_text`                                                                                                                                                                           | Plain text, up to 500 characters                                                                                   |

### Sections

Home page sections are listed in order. Unknown section types or settings reject the package.

| Type                | Settings                                                                                                                      |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| `hero`              | `heading`*, `subheading`, `button_label`, `button_link`, `image`, `alignment` (left, center), `height` (small, medium, large) |
| `featured_products` | `title`, `source` (latest, category), `category` (slug), `limit` (2–12)                                                       |
| `category_grid`     | `title`, `limit` (2–12)                                                                                                       |
| `image_with_text`   | `heading`*, `body`, `image`, `image_position` (left, right), `button_label`, `button_link`                                    |
| `rich_text`         | `heading`, `body`*, `alignment`                                                                                               |
| `promo_banner`      | `text`*, `button_label`, `button_link`, `tone` (primary, accent, muted)                                                       |

\* required.

- **Text:** all text is plain text, rendered as text and never as HTML.
- **Links:** a link is an internal path (`/search`, `/c/mugs`) or an `https://` address. `javascript:`, `data:` and protocol-relative links are rejected.
- **Data:** the core loads products and categories for sections; themes never query anything.
- **Module content:** content placed by modules in the `home.after_featured` slot appears after the sections.

## Lifecycle

| Action       | What happens                                                                                                                                                                                                            |
| ------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Discover     | Packages in `themes/` appear under **Admin → Appearance**. Invalid packages are listed with their errors.                                                                                                               |
| Preview      | Staff with `themes.manage` browse the storefront with any valid theme, including their customizations. Only their session sees it; a banner offers Exit preview.                                                        |
| Activate     | The theme's current files are applied and the theme becomes the only active theme. The previous theme and its customizations are kept.                                                                                  |
| Customize    | Design settings, logo and favicon, announcement, footer text and home sections. Stored separately from the theme (`theme.<vendor>.<name>.design` / `.sections`).                                                        |
| Update       | Newer files on disk do **not** change the active storefront. The merchant sees the changelog and applies the update; customizations are kept, and any the new version no longer accepts are listed (kept, not deleted). |
| Reset        | Removes all customizations of a theme, after typing its name.                                                                                                                                                           |
| Demo content | Adds the theme's demo categories and products, after explicit confirmation. Anything whose slug or SKU already exists is skipped; existing content is never changed.                                                    |

If the active theme's files go missing, the store falls back to the default theme (and to a built-in design if that is missing too) until the files are restored. Every action is recorded in the lifecycle log and the audit log.

## Assets

Assets are served from `/theme-assets/<vendor>/<name>/<path>`:

- only from inside the declared `assets` directory;
- only PNG, JPEG, WebP and WOFF2;
- with `X-Content-Type-Options: nosniff`, a `sandbox` Content Security Policy, and long-lived caching keyed by the theme version.

SVG is not accepted anywhere, because it can contain scripts. Merchant uploads (logo, banners) are re-encoded before they are stored.

## Demo content (`demo.json`)

```json
{
    "categories": [
        { "name": "Jewelry", "slug": "noir-jewelry", "description": "…" }
    ],
    "products": [
        {
            "name": "Halo Ring",
            "slug": "noir-halo-ring",
            "sku": "NOIR-RING-01",
            "price": "180.00",
            "compare_at_price": "220.00",
            "stock": 12,
            "short_description": "…",
            "description": "…",
            "categories": ["noir-jewelry"],
            "image": "assets/images/demo/ring.webp"
        }
    ]
}
```

Prefix slugs and SKUs with your theme name so they do not collide with real catalog data. Prices are in the store's currency. There is a limit of 100 items and 512 KB.

## Compatibility and testing

- Declare `platform_version` as `"^1.0"` (themes written for `0.x` must add `^1.0` after testing). The design settings, variants and section types follow the platform's compatibility policy (see the module guide): within a major version they are only added to, never removed.
- `tests/Feature/Themes/ThemeCompatibilityTest.php` runs every bundled theme through home, category, search, product, cart, checkout (asserting identical totals) and account pages. Add your theme to its data provider.
