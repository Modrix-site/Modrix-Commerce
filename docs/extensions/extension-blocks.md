# Extension blocks and places

Modules can show React components ("blocks") in named places on storefront
pages. Merchants decide, per customizer tab, whether each block shows, in
which of its places, and in what order. The choice is the same for every
theme.

## Places

| Place                         | Customizer tab | Context passed to props                  |
| ----------------------------- | -------------- | ---------------------------------------- |
| `header.actions`              | Header         | —                                        |
| `home.after_featured`         | Homepage       | —                                        |
| `catalog.before_products`     | Catalog        | `category`, `brand`, `query`             |
| `product.card`                | Catalog        | — (each card adds `product` on the page) |
| `product.after_title`         | Product        | `product`                                |
| `product.after_price`         | Product        | `product`                                |
| `product.after_add_to_cart`   | Product        | `product`                                |
| `product.after_details`       | Product        | `product`                                |
| `product.after_description`   | Product        | `product`                                |
| `cart.after_lines`            | Cart           | `cart`                                   |
| `checkout.before_place_order` | Checkout       | `cart`                                   |

Fixed slots stay where the module puts them; the merchant cannot move them:

- `order.confirmation`: the order confirmation page;
- `admin.product.sidebar`: the sidebar of the admin product editor.

## Registering a block

```php
public function boot(StorefrontSlots $slots): void
{
    $slots->block(
        module: 'acme/reviews',
        key: 'reviews',                        // stable: saved placements use "acme/reviews:reviews"
        label: 'Product reviews',              // shown to merchants
        component: 'ProductReviews',           // resources/js/slots/ProductReviews.tsx
        places: ['product.after_details', 'product.after_description'],
        default: 'product.after_details',      // where it shows until the merchant moves it
        props: fn (array $context) => ['productId' => $context['product']->id],
    );
}
```

How a block behaves:

- **Props closure:** it receives the place's context plus `place`. Return `null` to show nothing; an exception hides only this block.
- **Places:** list only places your component works in. If a module update drops a place a merchant chose, the block shows at its default.
- **Product cards:** `product.card` blocks render once per page. Read the card's product from the `product` prop.
- **Older registrations:** `register($slot, …)` still works. In a movable place it becomes a one-place block named after the component (`PayPromo` shows as "Pay Promo"). Registering the same component twice in one place keeps both; the second id ends in `#2`.

## Settings in customizer tabs

Add `"tab"` to a setting in `module.json` to also show it in that customizer
tab. It is the same setting: the tab and the module's settings page edit
one stored value.

```json
{
    "key": "max_products",
    "tab": "product",
    "type": "integer",
    "label": "Products shown",
    "default": 3,
    "min": 1,
    "max": 6
}
```

Valid tabs are `general`, `header`, `home`, `catalog`, `product`, `cart`,
`checkout` and `footer`.
