## Theme-owned chrome and product pages

Themes may now declare `header`, `footer` and `product` alongside `index`,
`catalog`, `cart` and `stylesheet` in `frontend`. Each entry must point to
`src/<entry>.tsx`; the stylesheet remains `src/style.css`. Header/footer
components receive shared storefront props through Inertia `usePage()`.
Product components receive `ThemeProductProps`: actual product data, the
resolved variant, image/option selection and the core callbacks. Use core cart
actions and `VariantSelector`; do not create a second pricing/stock model.
The platform keeps its fallback renderer when an entry is absent.

Independent `settings.json` schemas support up to 120 validated custom fields,
including direct image uploads. Theme CSS must stay scoped to its root
`[data-theme="vendor/name"]`. Shared `storefront.footerSlots` exposes optional
extension content; Hearth uses the Newsletter extension without requiring it.
ZIP installation still validates paths and files and never executes code.
Rebuild frontend assets after installing packages containing browser entries.

Nord (`themes/ecomodular/nord`) follows the supplied NORD fashion reference,
including its mobile header, campaign imagery, catalog and product gallery.
It provides 105 independent settings and styles the core cart and checkout.
Default theme navigation supports up to twelve links; saved shared Navigation
menus continue taking precedence. The optional six-product demo stays separate
from installation and activation.

Catalog pages now supply `slots` from `catalog.after_products`, with `category`,
`brand` and `query` context (unused values are null). A custom catalog renderer
should pass these items to `ExtensionSlot`, including for empty results.
Checkout also renders `checkout.after_summary`, with the same priced `cart`
as payment-method extensions. Its slots refresh together with shipping quotes.
Existing home, product, cart, payment, footer and overlay slots remain supported.

Hearth Home is the complete reference package in `themes/ecomodular/hearth`:
header, footer, homepage, catalog, product, cart, scoped CSS, 109 settings,
reference images and optional sample furniture. It installs from a root-level
`theme.json` ZIP and is not automatically activated or demo-imported.

# Theme Development

## Theme-owned frontend and catalog settings

### Shared header and footer navigation

Admin → Navigation owns the store's header links and three footer columns. Menus keep their order and survive theme switches. Before the first save, existing theme menu defaults and customizations supply the initial values. After saving, shared navigation takes precedence. Menu link fields are managed there instead of the theme customizer.

Custom theme components should render `storefront.navigation.header.links` and `storefront.navigation.footer` (each menu has a title and ordered `{label, url}` links). The platform's standard, electronics and atelier chrome consume these shared menus. Use normal anchors for external HTTPS addresses and Inertia links for store paths.

Each bundled theme owns a `settings.json` customizer definition, referenced by `"customizer_file": "settings.json"` in `theme.json`. The file contains the same title, description, fields, presets and hidden core controls supported by an inline `customizer`. The platform validates and snapshots these values when applying the theme; merchant overrides remain separate. Settings files must be JSON inside the package and no larger than 128 KB.

Marketplace's definition covers homepage spacing and extension content, header branding and search text, catalog heading/columns/image size/filter visibility, and cart headings/button labels/coupon visibility. Its `src/cart.tsx` is the cart entry, declared as `"cart": "src/cart.tsx"` in `frontend`; it receives `CartProps` and uses the platform cart actions and pricing.

Every image picker in the customizer includes a direct PNG/JPEG/WebP upload (up to 5 MB). The sanitized image is selected for that specific field without refreshing or losing other edits. Save design or Save home page to publish the selection.

Themes can opt into compiled browser code by declaring `frontend` in `theme.json`:

```json
"frontend": {
  "index": "src/index.tsx",
  "catalog": "src/catalog.tsx",
  "stylesheet": "src/style.css"
}
```

Export a default React component from each page. The index receives `HomeProps`; the catalog receives `ListingProps`. Shared storefront layout, commerce data, checkout and permissions stay in the platform. Put supporting TypeScript and scoped CSS under `src/`. PHP, server scripts and arbitrary entry paths are rejected. Review theme browser code before running the application build; ZIP upload itself never executes it. Run `npm run build` after installing a theme with browser code. Themes without frontend entries retain the platform renderer.

Declare theme-owned controls in `customizer.fields` with `custom.*` keys, labels, groups, types and defaults. Valid types are string, text, color, integer, boolean, select, link and image. Saved values override defaults and are exposed through `storefront.theme.settings` without the `custom.` prefix. The Marketplace catalog demonstrates heading, heading alignment, columns, image height, filters and result count. Use `hide_core_fields` to hide unused core controls while retaining their defaults. Styling must be scoped to your theme to avoid changing other storefronts.

A theme decides how a store looks: colors, typography, spacing, header and footer style, product cards, the product page layout and the home page sections. It cannot change how the store works. Prices, cart, checkout, payments and permissions always run the same core code.

The bundled themes are working examples:

| Theme                | Style                                                                                                    |
| -------------------- | -------------------------------------------------------------------------------------------------------- |
| `ecomodular/essence` | The free default theme: neutral, light, classic header, column footer. Activated on installation.        |
| `ecomodular/noir`    | Dark luxury: gold accents, Didone headings, centered header, minimal cards, three columns, demo content. |
| `ecomodular/bloom`   | Warm and botanical: cream and sage, serif headings, framed rounded cards, dark colors, demo content.     |
| `ecomodular/harvest` | Warm and appetising, for grocers and food delivery: large cards, a delivery-first home page.             |
| `ecomodular/aurora`  | Crisp and modern: teal aurora accents, clean cards. Ships three palette presets (aurora, slate, ember).  |
| `ecomodular/prism`   | Geometric precision: zero radius, minimal layout. Custom facet style, accent shape and edge sharpness.   |

## Themes are data, not code

A theme package contains **no PHP, no JavaScript, no HTML and no CSS**. It contains:

- a manifest (`theme.json`);
- images (PNG, JPEG, WebP) and fonts (WOFF2);
- optionally `demo.json`, a README and a license.

Any other file type rejects the package.

The default renderer uses validated design settings, layout variants and section types. Themes can also declare compiled browser page components as described above. Theme browser code must be reviewed before deployment; themes cannot contain PHP or run server code.

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

## Customizer

A theme can ship a `customizer` block that changes the heading, hides core fields and adds its own fields. Every field key must start with `custom.` and is stored under the theme's settings namespace.

```json
"customizer": {
  "title": "Brand",
  "description": "Tune the look of your store.",
  "hide_core_fields": ["layout.product_card"],
  "fields": {
    "custom.hero_eyebrow": {
      "type": "string",
      "label": "Hero eyebrow",
      "group": "Hero",
      "default": "New season",
      "max": 60
    }
  }
}
```

Field types: `string`, `text`, `color`, `boolean`, `integer`, `select`, `link`, `image` and `font`. A `font` field offers every built-in stack plus the theme's own `custom:<Family>` entries.

### Palettes

A theme may also ship named palettes under `customizer.presets`. Applying one sets several design fields at once; every field stays editable afterwards, so a merchant can start from a palette and then tweak individual swatches.

```json
"customizer": {
  "presets": {
    "warm": {
      "color.background": "#fbf7f1",
      "color.primary": "#3f6b4f",
      "font_heading": "serif",
      "radius": "1rem"
    },
    "cool": {
      "color.background": "#f5f7fa",
      "color.primary": "#073b7a",
      "font_heading": "sans",
      "radius": "0.375rem"
    }
  }
}
```

A palette may only set fields the theme declares (core or custom); unknown keys, and values that fail the field's validation, reject the package. At most 12 palettes are allowed.

## Lifecycle

| Action            | What happens                                                                                                                                                                                                            |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Discover          | Packages in `themes/` appear under **Admin → Appearance**. Invalid packages are listed with their errors.                                                                                                               |
| Preview           | Staff with `themes.manage` browse the storefront with any valid theme, including their customizations. Only their session sees it; a banner offers Exit preview.                                                        |
| Activate / Enable | The theme's current files are applied and the theme becomes the only active theme. The previous theme and its customizations are kept.                                                                                  |
| Disable           | Stops using the theme and prevents previews until it is enabled again. If active, the storefront uses its configured fallback or built-in design.                                                                       |
| Delete            | Available after disabling an active theme. Archives the package in `storage/app/private/theme-archives`, removes it from Appearance and preserves customizations. Restore it through Upload theme using the ZIP.        |
| Customize         | Design settings, logo and favicon, announcement, footer text and home sections. Stored separately from the theme (`theme.<vendor>.<name>.design` / `.sections`).                                                        |
| Update            | Newer files on disk do **not** change the active storefront. The merchant sees the changelog and applies the update; customizations are kept, and any the new version no longer accepts are listed (kept, not deleted). |
| Reset             | Removes all customizations of a theme, after typing its name.                                                                                                                                                           |
| Demo content      | Adds the theme's demo categories and products, after explicit confirmation. Anything whose slug or SKU already exists is skipped; existing content is never changed.                                                    |

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

### Managed pages and navigation targets

Configuration > Pages manages plain-text storefront content, publication status, SEO metadata and recoverable deletion. Published pages render at `/pages/{slug}` inside the active storefront layout. Navigation supports `type: link` with `url`, `type: page` with `page_id`, and `type: category` with `category_id`. Theme renderers continue consuming resolved `{label, url}` entries from `storefront.navigation`; URLs follow current slugs automatically. Draft/deleted pages and inactive categories are omitted. Admin navigation retains the underlying references so unavailable destinations can be corrected. Page management uses the existing `themes.manage` permission.
