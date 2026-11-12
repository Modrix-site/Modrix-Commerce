# Extension blocks and places — design

- **Date:** 2026-10-17
- **Status:** Approved in conversation; awaiting review of this document
- **Sub-project:** 2 of 2 of page settings (sub-project 1: `2026-10-17-page-settings-design.md`).

## Goal

Extensions (modules) can:

- show blocks in more places on storefront pages;
- let the merchant choose, per page tab in the customizer, which blocks show, in which of their supported places, and in what order;
- put their own settings into the matching customizer tab.

## 1. Places

`StorefrontSlots::SLOTS` gains these places (★ new):

| Place                                                                                          | Tab      | Context                                      | Movable    |
| ---------------------------------------------------------------------------------------------- | -------- | -------------------------------------------- | ---------- |
| ★ `header.actions`                                                                             | header   | —                                            | yes        |
| `home.after_featured`                                                                          | home     | —                                            | yes        |
| ★ `catalog.before_products`                                                                    | catalog  | category, brand, query                       | yes        |
| `catalog.after_products`                                                                       | catalog  | category, brand, query                       | yes        |
| ★ `product.card`                                                                               | catalog  | — (the card's product is passed on the page) | yes        |
| ★ `product.after_price`                                                                        | product  | product                                      | yes        |
| ★ `product.after_add_to_cart`                                                                  | product  | product                                      | yes        |
| `product.after_details`                                                                        | product  | product                                      | yes        |
| ★ `product.after_description`                                                                  | product  | product                                      | yes        |
| ★ `cart.after_lines`                                                                           | cart     | cart                                         | yes        |
| `cart.after_totals`                                                                            | cart     | cart                                         | yes        |
| `checkout.after_summary`                                                                       | checkout | cart                                         | yes        |
| ★ `checkout.before_place_order`                                                                | checkout | cart                                         | yes        |
| `storefront.footer`                                                                            | footer   | —                                            | yes        |
| `storefront.overlay`, `checkout.payment_method`, `order.confirmation`, `admin.product.sidebar` | —        | as today                                     | no (fixed) |

`StorefrontSlots::PLACES` maps each movable place to its label (for example "Under the price") and its tab.

## 2. Blocks

- **Registering a block.** A module registers it with `StorefrontSlots::block(string $module, string $key, string $label, string $component, array $places, string $default, Closure $props, int $order = 100)`:
    - `$places` is the list of movable places the component works in;
    - `$default` is one of them;
    - the props closure receives the place's context plus `place`.
- **Existing registrations.** `register($slot, …)` keeps working as a one-place block (key = component name, label = component name), so no module breaks.
- **Product cards.** For `product.card`, the server renders the place once per page (shared storefront prop `cardBlocks`, context empty). Each card then renders those components with `product` added on the page, so a listing costs no extra queries per card.

## 3. Placement (merchant's choice)

- **Storage:** store-wide setting `extensions.blocks` = `{ "<module>:<key>": { "place": string, "order": int, "enabled": bool } }`, the same for every theme.
- **`render($place)` shows:**
    - blocks placed there, or whose default is there when no placement is saved;
    - enabled ones only;
    - ordered by saved order, then registration order.
- **Fallbacks:**
    - a saved place the block no longer supports falls back to its default;
    - entries for unknown blocks are ignored and kept, so they return if the module is re-enabled;
    - a failing props closure still hides only that block.
- **Saving:** `PUT admin/themes/blocks` (same permission as customizing themes) validates:
    - each id is a registered block;
    - its place is one of the block's places;
    - order is an integer from 0 to 999;
    - enabled is a boolean.

## 4. Settings in the tabs

- **Declaring:** a module setting in `module.json` may add `"tab": "<customizer tab>"`.
- **Display:** the customizer tab then shows an "Extension settings" card, grouped by module, with those fields.
- **Saving:** through the module's own settings validation and storage (`ModuleSettingsSchema`), so the module's settings page and the tab edit the same value.
- **Visibility:** only enabled modules appear.

## 5. Customizer UI

Each page tab (Header, Homepage, Catalog, Product, Cart, Checkout, Footer) shows:

- **"Extension blocks"**: the blocks that can appear on that tab's page. Each row has the block name and module, an on/off switch, a place select (only that block's places, shown by label; places on other pages appear with their tab name) and up/down buttons. "Save blocks" saves.
- **"Extension settings"**: see §4.

Nothing shows on a tab with no blocks and no settings.

## 6. Themes

- **Shared pages:** the new places render with `ExtensionSlot` in the shared header, listing, product card, product, cart and checkout pages.
- **Own-page themes:** Nord, Voltora, Vellaro and Hearth (and the Atelier/Electronics header) render the same places in their own files, which are working-tree only as in sub-project 1.

## 7. Bundled modules

| Module                     | Places (default first)                          | Setting in a tab                     |
| -------------------------- | ----------------------------------------------- | ------------------------------------ |
| Product reviews            | after details, below the description            | "Show the review summary" (product)  |
| Tabby                      | under the price, under add to cart              | "Show the instalment note" (product) |
| Tamara                     | under the price, under add to cart              | "Show the instalment note" (product) |
| Frequently bought together | after details, under add to cart                | "Number of products" (product)       |
| Newsletter (sign-up block) | after featured (home), footer                   | "Show the sign-up form" (home)       |
| Flash sales                | after featured (home), above products (catalog) | —                                    |

These modules' current places stay their defaults, so an untouched store renders as before.

## 8. Out of scope

- Free drag-and-drop page building.
- Per-theme placement.
- Moving the fixed places.

## Testing

- **Registration:**
    - an unknown place or a default not in the list is rejected;
    - `register()` still works.
- **Render:**
    - default place;
    - moved block;
    - disabled block;
    - order;
    - an unsupported saved place falls back;
    - a failing block hides alone.
- **Save endpoint:**
    - validation errors;
    - stored for every theme;
    - permission required.
- **Settings in tabs:** a tab field appears on the customize page with its value; saving updates the module setting.
- **Product cards:** `cardBlocks` is shared once; no per-card queries.
- **Browser:** move the reviews block, turn Tabby off, and edit a module setting from the Product tab. Check in Aurora (shared pages) and Voltora (own pages).
