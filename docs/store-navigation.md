# Store navigation

Open **Admin → Navigation** to manage the store's shared menu trees. Choose Top
navigation, Left sidebar, Mobile menu, or one of the three footer menus.

Each placement has a title, visibility switch, and horizontal or vertical layout.
Horizontal menus display dropdown submenus. Vertical menus expand in place.
Mobile drawers always use vertical trees. The sidebar appears beside the content
on desktop and as an expandable department menu on phones and tablets.

Select a link in the tree to edit its label and destination. Links can point to a
custom path, an HTTPS website, a store page, or a product category. Use **Add
submenu link** to create a child. Arrow controls reorder siblings, indent under
the previous sibling, or outdent to the parent level. A branch can be collapsed
in the editor; deleting it removes its descendants when the changes are saved.

**Add category tree** imports active categories and their descendants. You can
edit this imported structure independently afterwards. Page and category targets
remain connected to their records, so their URLs update when slugs change.
Draft or deleted pages and inactive categories hide the entire associated branch
from the storefront while keeping it in the editor.

Mobile inherits the top navigation by default. Uncheck **Use the top navigation
tree on mobile** to create a separate mobile menu. An empty or disabled sidebar
does not consume storefront space. Menu changes survive theme switches.

There are at most four levels and 150 links across all placements. Root limits
are 30 links for header/sidebar/mobile and 20 for each footer menu. Existing flat
menus remain valid without migration.

## Theme integration

All bundled themes use `StoreMenuTree` from
`resources/js/components/storefront/store-menu.tsx`. It supports the resolved
`storefront.navigation.header`, `.sidebar`, `.mobile`, and `.footer` menus.
Each menu contains `title`, `enabled`, `layout`, and recursive `links`; each link
contains `label`, `url`, and optional `children`.

Render a header with `<StoreMenuTree menu={storefront.navigation.header} />`,
a drawer with `orientation="vertical"` and an `onNavigate` close callback, and
footer menus with `orientation={menu.layout ?? 'vertical'}`. The shared storefront
layout owns `StoreNavigationShell`, so themes automatically receive the left
sidebar without duplicating it. HTTPS destinations use regular anchors; internal
destinations use Inertia. Submenu controls support keyboard activation, Escape,
focus outlines, and native links without replacing ordinary navigation behavior.
