# Multi-Vendor Marketplace Extension (`modrixcommerce/multi-vendor`)

## Overview

The **Multi-Vendor Marketplace** extension (`modrixcommerce/multi-vendor`) turns any Modrix Commerce store into a full multi-seller marketplace without modifying core files. It wires directly into Core & Theme extension hooks (`ProductLabels`, `StorefrontSlots`, `DashboardWidgets`, `OrderPlaced` events, and `admin.product.sidebar`).

## Core Capabilities

1. **Vendor Registration & Business Verification (KYB / KYC)**:
   - Public seller registration & KYB submission (`/m/modrixcommerce/multi-vendor/register`).
   - Collects **Legal Entity Type** (`individual`, `sole_proprietorship`, `llc`, `corporation`, `partnership`), **Registered Legal Business Name**, **Commercial Trade License Number**, **Tax ID / VAT / TRN**, **Signatory Identity Reference**, and **Registered Business Address**.
   - Multi-stage verification workflow (`unverified`, `pending_review`, `verified`, `more_info_required`, `rejected`) with Admin KYB Review Queue and **Verified Business (`✓`)** badges across all storefront themes.

2. **Amazon-Style Theme-Native Marketplace Catalog & Dedicated Storefronts**:
   - **All-Products Marketplace Catalog (`/m/modrixcommerce/multi-vendor/catalog`)**: Renders the full multi-vendor catalog inside the currently active storefront theme (`ThemeSectionList` for sections-v2 themes, `themeCatalog` for legacy themes, and `ProductGrid` fallback) with department tabs, seller switcher (`All Sellers`, `Direct Store`, individual sellers), KYB Verified Sellers Only toggle, Today's Deals (`on_sale`), `4★ & Up` Seller Rating filter, In Stock toggle, price range, variant options, and `Best Sellers` / `Rating` / `Price` / `Newest` sorting.
   - **Vendor Seller Portal (`/m/modrixcommerce/multi-vendor/dashboard`)**: Vendors can add and manage their own catalog products using the core `SaveProduct` action and log in via `/m/modrixcommerce/multi-vendor/login`.
   - **Admin Product Editor Integration (`admin.product.sidebar`)**: Assign any existing product to a vendor and configure a custom commission override.
   - **Public Sellers Directory (`/m/modrixcommerce/multi-vendor/vendors`)**: Searchable marketplace directory with Verified Business filter and live product showcase tiles.
   - **Dedicated Vendor Storefront (`/m/modrixcommerce/multi-vendor/vendors/{slug}`)**: Branded Amazon-style vendor storefront and full faceted catalog rendered inside the active storefront theme.

3. **Automatic Order Splitting & Commission Calculation**:
   - Listens to `App\Domains\Orders\Events\OrderPlaced` and splits vendor line items into `marketplace_vendor_orders`.
   - Resolves commission rates using a 3-tier hierarchy:
     1. Product-level override (`marketplace_vendor_products.commission_override`)
     2. Vendor-level custom rate (`marketplace_vendors.commission_rate`)
     3. Global marketplace default (`default_commission_rate`, default `10%`)
   - Credits net `vendor_earning` to the vendor's `available_balance` and lets sellers update item fulfillment status (`processing`, `shipped`, `delivered`) and tracking numbers.

4. **Option for Vendors to Receive Payouts from the Marketplace Store**:
   - Supports **Bank Wire / IBAN Transfer**, **PayPal Payouts**, **Stripe Connect**, **Wise Business Transfer**, and **Manual / Store Check**.
   - Three payout modes:
     - `vendor_request`: Vendors request payouts on demand from their Seller Portal.
     - `automatic_on_order`: Store automatically generates a payout record when an order pushes a vendor's balance over `min_payout_amount` (also controllable per vendor via `auto_payout_enabled`).
     - `admin_batch`: Store admins generate one-click batch payouts for all eligible vendors.
   - Includes payout fee calculation (`payout_fee_percent`), transaction reference recording when marking payouts `paid`, and automatic balance restoration when a payout is `rejected`.
