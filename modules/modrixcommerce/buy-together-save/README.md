# Buy Together & Save Module for Modrix Commerce

Increase average order value (AOV) by offering attractive **"Buy Together & Save"** product bundles on single product pages with customizable combo savings and 1-click cart addition.

## Features

- **Storefront Single-Product Bundle Widget**: Renders a dedicated promotional card on product pages showing the main product paired with curated or related companion products connected with clean `+` icons.
- **Customizable Bundle Offers**:
  - Set custom promotional titles and badge text (e.g. "Save 15%", "Combo Deal", "Starter Pack").
  - Target bundles by specific product, entire categories, or store-wide.
  - Choose between percentage discounts (e.g. 15% off) or fixed amount discounts (e.g. $20 off).
- **Smart Automatic Fallback**: When no manual bundle deal is defined for a product, automatically recommends companion items from the same category or bestsellers with the default discount rule.
- **Interactive Shopper Selection**: Shoppers can uncheck optional companion items with live reactive subtotal and savings calculation.
- **Seamless Cart & Pricing Integration**:
  - 1-click **"Add Bundle to Cart & Save"** adds all selected bundle items to the cart in a single request.
  - Integrated with Modrix Commerce's `CartPricer` via `BuyTogetherPricingStep` to apply real offer discounts to cart lines.
  - Automatic cleanup if an item leaves the cart.
- **Comprehensive Admin Hub**:
  - Full CRUD management of bundle deals with priorities and date scheduling.
  - Performance analytics tracking impressions, add-to-cart conversions, and conversion rates.
  - Full settings panel for customizing widget headings, subheadings, CTA buttons, badges, and fallback rules.

## Installation & Setup

1. Install and enable the module:
   ```bash
   php artisan ecomodular:modules install modrixcommerce/buy-together-save
   php artisan ecomodular:modules enable modrixcommerce/buy-together-save --trust
   ```
2. Navigate to **Admin -> Marketing -> Buy Together & Save**.
3. Create your first curated bundle deal or adjust global settings to activate automatic category bundles.
4. Product pages will now showcase the **"Buy Together & Save"** widget!
