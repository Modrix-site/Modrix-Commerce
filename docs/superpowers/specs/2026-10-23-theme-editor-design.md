# Theme editor (sections everywhere): design

Status: approved in conversation 2026-10-23 (sections 2-5 decided during the build at the owner's request).

## Goal

A theme editor that works like Shopify's online store editor, driven entirely
by data the theme ships. Nothing about a theme's sections is hard-coded in the
platform.

## Decisions (owner)

- Every storefront page is built from sections: header and footer groups, and
  templates `index`, `product`, `collection`, `search`, `cart`, `page`.
- Sections are theme-only (as on Shopify): each theme declares its section
  types and ships their components. The platform ships no section library.
- Save and Publish behave like Shopify: on an inactive theme (badge "Draft")
  Save stores changes and Publish activates the theme; on the live theme Save
  goes live immediately.
- Circuit and Marketplace are ported first. The other themes keep the current
  customizer and rendering until each is ported. The platform's built-in
  section types (`ThemeSchema::sectionTypes`) remain only for those themes.
- Live preview: the real storefront in an iframe (approach 1).

## Theme package format (sections v2)

A theme opts in with the capability `sections` in `theme.json`.

```
sections/<type>.json        schema
src/sections/<type>.tsx     component (default export)
templates/<template>.json   default template
groups/header.json          default header group
groups/footer.json          default footer group
```

Section schema:

```json
{
  "name": "Slideshow",
  "settings": [{ "type": "range", "id": "speed", "label": "Speed", "min": 2, "max": 10, "step": 1, "default": 5 }],
  "blocks": [{ "type": "slide", "name": "Slide", "settings": [...] }],
  "max_blocks": 8,
  "presets": [{ "name": "Slideshow", "blocks": [{ "type": "slide" }] }],
  "enabled_on": { "templates": ["index"], "groups": [] },
  "limit": 1
}
```

Setting types: `header` (label only), `text`, `textarea`, `richtext` (plain
paragraphs), `number`, `range`, `checkbox`, `select`, `radio`, `color`,
`image`, `url`, `collection`, `product`. Values are validated by the platform
from the type; nothing is evaluated.

Template/group JSON (Shopify's shape):

```json
{
  "sections": {
    "hero": { "type": "slideshow", "settings": {}, "blocks": { "s1": { "type": "slide", "settings": {} } }, "block_order": ["s1"], "disabled": false }
  },
  "order": ["hero"]
}
```

Limits: 40 sections per template, 50 blocks per section, ids `^[a-z0-9_-]{1,40}$`.

## Storage

Merchant edits: setting `theme.<vendor>.<name>.templates` = map of
`template:<name>` / `group:<name>` => JSON. Missing entries use the package
default. Reset removes them.

## Rendering

The server sends `themeSections` with each storefront page: the resolved
header and footer groups and the page's template. Settings of type `image`,
`url`, `collection` and `product` are resolved server-side into display data
(URLs, product cards, collection products) next to the raw value. The
storefront renders sections in order with the theme's components, passing the
page's own props (product, listing, cart) as `page`.

## Editor

Full-screen page `admin/themes/{theme}/editor`:

- Top bar: exit, theme name with Draft/Live badge, template picker, desktop
  or mobile, undo/redo, Save, Publish (inactive themes).
- Left rail: Sections, Theme settings.
- Left panel: Header group, Template, Footer group; sections with nested
  blocks, Add section (schemas allowed there, presets), Add block, drag to
  reorder, hide, remove.
- Right panel: settings of the selected section or block.
- Centre: storefront iframe. Every change is debounced into an editor draft
  (cache, per user and theme, 2 h) and the iframe reloads its sections data;
  nothing is stored on the theme until Save. Hovering a section in the
  iframe outlines it; clicking selects it in the editor and the other way
  round (postMessage, same origin only).
- The iframe runs the theme as the staff member's preview. Only in that mode
  may the storefront be framed, and only by its own origin
  (`frame-ancestors 'self'`).

## Out of scope for the first build

Duplicate theme, app embeds tab, section-level custom CSS, alternate
templates (`product.alt`), localisation of section text through the editor
(text keeps using the existing content translations).
