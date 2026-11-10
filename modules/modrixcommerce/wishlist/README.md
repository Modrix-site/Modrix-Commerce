# Wishlist & Save For Later for ModrixCommerce

The **Wishlist & Save For Later** module allows shoppers to save their favorite items, view their saved products on a dedicated `/wishlist` page, move items to cart with one click, and share their wishlist via public links.

## Features

- **Guest & Account Persistence:** Guests can save items instantly using session tokens. When they sign in or register, their saved items are automatically merged into their customer account.
- **Storefront Blocks:**
  - `WishlistButton`: Placed on product pages (`product.after_add_to_cart`, `product.after_price`, `product.after_details`) with 1-click animated heart toggle.
  - `WishlistHeaderIcon`: Injected into `header.actions` with a live count badge.
- **Dedicated Storefront Page (`/wishlist`):**
  - Item overview with prices, image previews, and stock status.
  - 1-click **Move to Cart** (adds item to cart and removes from wishlist).
  - Shareable wishlist link.
- **Admin Demand Analytics:**
  - Total saved items and wishlist counts.
  - Top 10 most-wishlisted products with save counts and SKU tracking to guide restocking and promotional decisions.
