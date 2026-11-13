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

Nord (`themes/modrixcommerce/nord`) follows the supplied NORD fashion reference,
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

Hearth Home is the complete reference package in `themes/modrixcommerce/hearth`:
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

Export a default React component from each page. The index receives `HomeProps`; the catalog receives `ListingProps`. Shared storefront layout, commerce data, checkout and permissions stay in the platform. Put supporting TypeScript and scoped CSS under `src/`. PHP, server scripts and arbitrary entry paths are rejected. Review theme browser code before running the application build; ZIP upload itself never executes it. A theme with browser code needs either its prebuilt `dist/` (see "Override any storefront page or layout" below) or an `npm run build` after installing it. Themes without frontend entries retain the platform renderer.

Declare theme-owned controls in `customizer.fields` with `custom.*` keys, labels, groups, types and defaults. Valid types are string, text, color, integer, boolean, select, link and image. Saved values override defaults and are exposed through `storefront.theme.settings` without the `custom.` prefix. The Marketplace catalog demonstrates heading, heading alignment, columns, image height, filters and result count. Use `hide_core_fields` to hide unused core controls while retaining their defaults. Styling must be scoped to your theme to avoid changing other storefronts.

A theme decides how a store looks: colors, typography, spacing, header and footer style, product cards, the product page layout and the home page sections. It cannot change how the store works. Prices, cart, checkout, payments and permissions always run the same core code.

The bundled themes are working examples:

| Theme                    | Style                                                                                                    |
| ------------------------ | -------------------------------------------------------------------------------------------------------- |
| `modrixcommerce/essence` | The free default theme: neutral, light, classic header, column footer. Activated on installation.        |
| `modrixcommerce/noir`    | Dark luxury: gold accents, Didone headings, centered header, minimal cards, three columns, demo content. |
| `modrixcommerce/bloom`   | Warm and botanical: cream and sage, serif headings, framed rounded cards, dark colors, demo content.     |
| `modrixcommerce/harvest` | Warm and appetising, for grocers and food delivery: large cards, a delivery-first home page.             |
| `modrixcommerce/aurora`  | Crisp and modern: teal aurora accents, clean cards. Ships three palette presets (aurora, slate, ember).  |
| `modrixcommerce/prism`   | Geometric precision: zero radius, minimal layout. Custom facet style, accent shape and edge sharpness.   |

## Theme package boundaries

A theme package contains no PHP or server scripts. It contains:

- a manifest (`theme.json`);
- images (PNG, JPEG, WebP) and fonts (WOFF2);
- optionally `demo.json`, a README and a license.
- optionally declared React/TypeScript components and CSS under `src/`.

Browser source is accepted only when the manifest declares frontend entries. Raw HTML and server code are rejected.

The default renderer uses validated design settings, layout variants and section types. Themes can also declare compiled browser page components as described above. Theme browser code must be reviewed before deployment; themes cannot contain PHP or run server code.

Build new visual components inside the theme's `src/` directory. Use a module for server behavior and storefront slots for feature UI. Adding a theme or module does not require registering its name, routes, provider or components in core files.

## Override any storefront page or layout

Declare only the parts your theme replaces:

```json
"frontend": {
  "layout": "src/layout.tsx",
  "stylesheet": "src/style.css",
  "pages": {
    "storefront/checkout": "src/pages/checkout.tsx",
    "storefront/account/orders": "src/pages/account/orders.tsx",
    "storefront/page": "src/pages/content.tsx"
  }
}
```

`pages` keys are core Inertia component names beginning with `storefront/`.
They cannot replace admin, installation or module pages. Values must be existing
TSX files beneath `src/pages/`; nested directories are supported. A declared
page override takes precedence over the older `index`, `catalog`, `cart` and
`product` entries. Missing declarations or files absent from the compiled build
use the existing platform renderer. The current server-resolved theme controls
selection, including previews and theme switches.

Every page receives its original controller props and a `CorePage` component:

```tsx
import type { ThemePageProps } from '@/lib/theme-runtime';

export default function Checkout({ CorePage, ...props }: ThemePageProps) {
    return (
        <section className="custom-checkout">
            <CorePage {...props} />
        </section>
    );
}
```

Wrap `CorePage` when retaining checkout or account behavior. A full replacement
must use the existing server routes, validation and commerce data, and render
the relevant extension slots. Theme code does not replace pricing, payments,
stock calculations or authorization on the server.

An optional `layout.tsx` receives `children` and `DefaultLayout`:

```tsx
import type { ThemeLayoutProps } from '@/lib/theme-runtime';

export default function Layout({ children, DefaultLayout }: ThemeLayoutProps) {
    return <DefaultLayout>{children}</DefaultLayout>;
}
```

Use `DefaultLayout` to retain shared navigation, preview banner, theme root
attributes, SEO, header/footer and global extension overlays. A fully custom
layout owns those integrations, including `data-theme`, locale/direction,
`theme.css`, extension overlays and preview UI. `usePage()` exposes shared
storefront props. Prefer the existing header/footer entries when only chrome
changes. Declared `src/style.css` is loaded automatically on every storefront
page, so checkout and account pages do not depend on visiting the home page.
Scope CSS to `[data-theme="vendor/name"]`, including when importing it directly
from a component.

A theme's screens reach a store in one of two ways:

- **Prebuilt (how Modrix Cloud and ZIP installs work).** Before you package or
  release a theme, run `php artisan ecomodular:module:build <path to the theme>`
  from a Modrix Commerce checkout. It compiles every `src/**/*.tsx` file and the
  declared stylesheet into `dist/` and writes `dist/theme-ui.json`. Ship `dist/`
  in the package; it may only hold `.js`, `.css` and `.json` files. Before a
  storefront page or the customizer renders, the store loads the active
  theme's prebuilt files from `/theme-build/<vendor>/<name>/…`, so no store
  rebuilds its assets. Rebuild `dist/` whenever the screens change, and bump
  the version so stores receive it as an update. React, Inertia and the
  store's own files (`@/…`) are shared with the store, not bundled (see
  [Module Development](module-development.md#pages-and-components)).
- **Compiled with the store.** Themes in the repository when the store's assets
  are built (`npm run build`) are bundled directly; `dist/` is then not needed.

Import the store's files with `@/…`, never by a relative path that leaves the
theme package (`../../../../resources/…`): such a path only works while the
theme sits inside the store's own `themes/` folder.

For extensions, keep PHP, routes, migrations and UI in `modules/<vendor>/<name>`.
The module loader handles declared providers, autoloading and routes; the browser
discovers module pages and slot components by convention. Register behavior
through providers, events and platform registries, then render UI through
`ExtensionSlot` / `PlaceSlot`. See [Module Development](module-development.md)
and [Extension Blocks](extension-blocks.md). Do not import a particular theme
from a module or from core; themes consume the shared platform components.

## Package layout

```text
themes/<vendor>/<name>/
  theme.json            manifest (required)
  src/                  optional declared browser components and scoped CSS
  dist/                 prebuilt src/ (made by ecomodular:module:build; ship it)
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
| `requires`                                         |                | Modules that must be enabled, e.g. `{"modrixcommerce/product-reviews": "^1.0"}`.                                           |
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

The table above is the legacy home-page format. New themes should use theme sections, described next.

## Theme sections and the theme editor

A theme with the capability `sections` builds **every page** from its own sections, as on Shopify. The platform defines no section types: the theme declares each one in data and ships its React component. Merchants edit these themes in **Appearance → Themes → Edit theme**, a full-screen editor with live preview.

```text
sections/<type>.json         section schema
src/sections/<type>.tsx      its component (default export)
templates/<name>.json        index, product, collection, search, cart, page (all required)
groups/header.json           header section group
groups/footer.json           footer section group
```

### Section schema

```json
{
    "name": "Slideshow",
    "limit": 1,
    "max_blocks": 8,
    "enabled_on": { "templates": ["index"], "groups": [] },
    "settings": [
        {
            "type": "text",
            "id": "heading",
            "label": "Heading",
            "default": "Welcome"
        },
        {
            "type": "collection",
            "id": "collection",
            "label": "Products from",
            "products": 8,
            "fallback": "latest"
        }
    ],
    "blocks": [
        {
            "type": "slide",
            "name": "Slide",
            "limit": 8,
            "settings": [{ "type": "image", "id": "image", "label": "Image" }]
        }
    ],
    "presets": [
        {
            "name": "Slideshow",
            "blocks": [{ "type": "slide" }, { "type": "slide" }]
        }
    ]
}
```

- **`enabled_on`:** which templates (`"*"` for all) and groups the section can be added to. Without it, a section fits every template and no group.
- **`limit`:** how many times a section can appear in one template. `max_blocks` (up to 50) and a block's `limit` cap its blocks.
- **`presets`:** what **Add section** offers. A preset may set settings and starting blocks.
- **Setting types:**
    - `header` is a label only.
    - Value types are `text`, `textarea`, `richtext` (plain paragraphs), `number`, `range` (`min`, `max`, `step`), `checkbox`, `select` and `radio` (`options: [{value, label}]`), `color`, `image`, `url`, `collection`, `product` and `tags` (`limit`, `products`, `fallback: "popular"`).
    - `info` adds help text under a control.
    - Values are validated by type and never evaluated.

### Templates and groups

Shopify's JSON shape, with a section id and block id map plus an order:

```json
{
    "sections": {
        "hero": {
            "type": "slideshow",
            "settings": {},
            "blocks": { "s1": { "type": "slide", "settings": {} } },
            "block_order": ["s1"]
        }
    },
    "order": ["hero"]
}
```

`"disabled": true` hides a section or block. Ids are lowercase (`a-z`, `0-9`, `_`, `-`, up to 40 characters). A template holds up to 40 sections. Missing settings get their schema default. The package files are the defaults, and **Reset** returns to them.

### Section components

```tsx
import type { ThemeSectionProps } from '@/components/storefront/theme-sections';
import { blockAttributes } from '@/components/storefront/theme-sections';

export default function Slideshow({
    section,
    page,
}: ThemeSectionProps<{ heading: string }>) {
    return (
        <section>
            <h2>{section.settings.heading}</h2>
            {section.blocks.map((block) => (
                <img
                    key={block.id}
                    {...blockAttributes(block)}
                    src={String(block.settings.image ?? '')}
                    alt=""
                />
            ))}
        </section>
    );
}
```

Values arrive resolved for display:

- **`image`:** a URL, or `null`.
- **`url`:** a link, or `""` when it points to a page that no longer exists.
- **`collection`:** `{handle, title, url, image_url, products}`, with up to `products` product cards. An empty value uses `fallback` (`latest` or `featured`).
- **`product`:** a product card, or `null`.
- **`tags`:** a list of `{name, label, slug, url, count, products}` for the chosen product tags, in order, at most `limit` (default 8). Tags with no visible products are left out. `url` is the tag-filtered catalog (`/search?tag=…`), `count` the number of visible products and `products` up to `products` cards (default 0). An empty value with `fallback: "popular"` uses the store's most-used tags.
- **Text:** translated into the shopper's language.

`page` holds the page's own props:

- **product:** the product and the selected variant
- **collection** and **search:** the listing
- **cart:** the cart
- **page:** the content page

Spread `blockAttributes(block)` on a block's root element so clicking it in the preview selects the block.

### Saving, publishing and preview

- **Save, as on Shopify:** Save stores the edits on the theme. On the live theme they are live at once. An inactive theme shows **Draft**, and **Publish** activates it.
- **Live preview:** the preview is the real storefront in a frame. Unsaved edits are kept in a short-lived draft for the editing staff member only. The storefront may be framed only in that mode, and only by its own origin.

## Customizer

### Theme-owned schema and editor

Keep the theme's controls in its package, independently of the platform defaults:

```text
themes/acme/store/
  theme.json
  settings.json
  src/customizer.tsx
```

Declare them in `theme.json`:

```json
"customizer_file": "settings.json",
"frontend": {
  "customizer": "src/customizer.tsx"
}
```

`settings.json` replaces the inline `customizer` block when both exist. Use
`mode: "replace"` to show only the theme's fields and explicitly selected core
controls, instead of adding them to all platform controls:

```json
{
    "title": "My store design",
    "mode": "replace",
    "core_fields": ["color.primary", "font_heading", "logo"],
    "sections": false,
    "fields": {
        "custom.hero_heading": {
            "type": "string",
            "label": "Campaign heading",
            "group": "Campaign",
            "tab": "home",
            "default": "The new collection",
            "max": 100
        }
    }
}
```

`mode` defaults to `extend` for compatibility. In replacement mode, omitting
`core_fields` hides every core design control; it does not erase saved settings,
change commerce rules or disable core fallbacks. `hide_core_fields` still applies
after selection. `sections: false` hides the platform home-section editor without
deleting sections. Existing store-wide page settings keep their current scope.
Use `design` in `theme.json` for theme defaults, `fields.*.default` for custom
defaults, and `presets` for palettes. Saved merchant values take precedence.

An optional `src/customizer.tsx` replaces the editor UI for that theme:

```tsx
import type { ThemeCustomizerProps } from '@/lib/theme-customizer';

export default function Customizer({
    DefaultCustomizer,
    ...props
}: ThemeCustomizerProps) {
    return <DefaultCustomizer {...props} />;
}
```

Replace the return value with your own UI, or compose the supplied `controls`:
`Design`, `Sections`, `Images`, `Reset` and `Field`. `Design` accepts `themeId`,
`design`, `images` and a standard customizer `tab`; `Sections` accepts `themeId`,
`sections` and `images`. These reuse the existing validated save routes, uploads,
presets and error handling. All controller props are also supplied: `theme`,
`design`, `sections`, `images`, `blocks`, `extensionSettings`, and `customizer`.
Render extension block/settings controls when replacing the full UI. The Circuit
theme's `src/customizer.tsx` is a complete example using the shared controls.

For bespoke forms, submit `{design: {"custom.hero_heading": "New campaign"}}`
to `ThemeController.design.url(theme.id)` using Inertia `useForm`. Send only
changed fields; omitted settings remain untouched. Empty strings or null reset
a setting to its theme default. Server validation remains authoritative.

The editor is resolved from the theme being edited, including inactive themes,
and loaded only through the admin customizer. Missing compiled components or a
failed editor fall back to the default controls. Rebuild assets after adding
browser files. New `ecomodular:make:theme` packages include their own schema and
editor starter. Existing active themes use their applied manifest snapshot;
after updating package declarations, apply the theme update through Appearance
to use the new editor. Circuit 1.1.0 includes its new editor declaration.

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

### Translate storefront text from the customizer

Text settings and homepage section text have a **Translate** control. Choose an
additional language enabled in Languages, generate a draft with **Translate with
AI**, review or edit it, then **Save translation**. The AI Assistant extension
supplies the configured provider, model and API key through the platform's
translation registry. Theme managers do not need product-edit permission.
If AI is disabled, manual translation is still available. Drafts are never saved
automatically, and source settings are unchanged. Save the design or homepage
separately if the source itself was edited.

Reviewed translations take precedence over automatic and interface translations
in both language modes. They are matched by exact original text and target
language, using the existing shared content-translation store. Identical wording
can therefore reuse a translation across themes. Changing the source restores
the usual language fallback until its new translation is saved. Clearing and
saving a translation removes this reviewed override. Links, email addresses,
phone numbers, colors and asset values are excluded. Long text up to 10,000
characters is supported.

Package editors that compose `controls.Design`, `controls.Sections` or
`controls.Field` receive the translation UI automatically. Bespoke controls can
use `ThemeTextTranslation` from
`@/components/admin/themes/text-translation`, passing `fieldKey`, `label` and the
current effective `source` text. For a section field, also pass `sectionType`.
The customizer provides the translation context around both default and
package-owned editors; no theme-specific backend changes are needed.

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
- `tests/Feature/Themes/ThemeCompatibilityTest.php` exercises bundled themes. Keep tests for a new package alongside its development workflow; test edits are not required to register a theme at runtime.

### Managed pages and navigation targets

Configuration > Pages manages plain-text storefront content, publication status, SEO metadata and recoverable deletion. Published pages render at `/pages/{slug}` inside the active storefront layout. Navigation supports `type: link` with `url`, `type: page` with `page_id`, and `type: category` with `category_id`. Theme renderers continue consuming resolved `{label, url}` entries from `storefront.navigation`; URLs follow current slugs automatically. Draft/deleted pages and inactive categories are omitted. Admin navigation retains the underlying references so unavailable destinations can be corrected. Page management uses the existing `themes.manage` permission.
