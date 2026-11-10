# Tiered Pricing & Volume Discounts for ModrixCommerce

The **Tiered Pricing & Volume Discounts** module enables quantity-break wholesale and volume discounts for ModrixCommerce stores.

## Features

- **Quantity Breaks:** Define custom quantity thresholds (e.g. 2–4: 5% off, 5–9: 10% off, 10+: 15% off).
- **Flexible Targeting:** Target specific products, whole categories, or storewide catalog.
- **Discount Types:** Supports percentage off per unit (`percent`) or fixed currency savings per unit (`fixed_per_unit`).
- **Storefront Block:** Injects interactive tier tables into product pages (`product.after_price`, `product.after_add_to_cart`, `product.after_details`).
- **1-Click Quantity Selection:** Shoppers can click any tier in the table to automatically set their product quantity.
- **Cart & Checkout Pricing Engine:** Seamlessly hooks into `CartPricer` via `PricingStep` to apply real volume discounts to qualifying cart lines.
- **Admin Management:** Comprehensive admin interface for managing rules, scheduling, priority, and global settings.
