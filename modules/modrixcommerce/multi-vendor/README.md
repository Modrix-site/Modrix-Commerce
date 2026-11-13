# Multi-Vendor Marketplace (`modrixcommerce/multi-vendor`)

Transform your Modrix Commerce store into a complete **Multi-Vendor Marketplace** with self-service **Vendor Registration**, **KYB / KYC Business Verification**, **Dedicated Vendor Product Catalogs & Storefronts**, **Automatic Order Commission Splitting**, and **Store-to-Vendor Payouts**.

- **Package Identifier**: `modrixcommerce/multi-vendor`
- **Version**: `1.2.0`
- **Platform Compatibility**: `^0.4 || ^1.0`
- **License**: `MIT`

---

## Key Features Overview

### 1. Vendor Registration & KYB Business Verification
- **Public Seller Onboarding (`/m/modrixcommerce/multi-vendor/register`)**:
  - Prospective vendors register their store identity (Store Name, Slug, Contact Email/Phone, Tagline, Description) alongside official KYB compliance credentials and payout details in a single streamlined onboarding flow.
- **Multi-Stage KYB / KYC Verification Workflow**:
  - Tracks **Legal Entity Type** (`individual`, `sole_proprietorship`, `llc`, `corporation`, `partnership`), **Registered Legal Business Name**, **Commercial Trade License / Registration Number**, **Tax ID / VAT / TRN**, **Authorized Signatory ID / Passport Reference**, and **Registered Headquarters Address**.
  - Supports 5 verification states: `unverified`, `pending_review`, `verified`, `more_info_required`, and `rejected`.
- **Admin KYB Compliance Review Queue**:
  - Dedicated **Business Verification (KYB)** tab in `/admin/m/modrixcommerce/multi-vendor` allowing administrators to inspect legal credentials, add compliance notes, and **Verify Business & Approve**, **Request More Info**, or **Reject KYB** with one click.
- **Verified Business Badges**:
  - KYB-verified vendors automatically receive the **Verified Business (`✓`)** badge on storefront product cards, product detail pages, the public sellers directory, and their dedicated vendor storefront.

### 2. Vendor Product Catalog & Dedicated Storefronts
- **Vendor Seller Portal Catalog Management (`/m/modrixcommerce/multi-vendor/dashboard`)**:
  - Vendors can create and publish new products directly from their Seller Portal (Name, SKU, Price, Stock Quantity, Short Summary, Description, and Categories), powered by the core `SaveProduct` catalog action.
- **Admin Product Assignment & Moderation**:
  - **Admin Product Editor Sidebar (`admin.product.sidebar` slot)**: Assign any store product to a vendor and set an optional product-level commission rate override or vendor SKU directly from the product edit screen.
  - **Catalog Moderation Queue**: Approve or reject vendor-submitted products, with configurable `auto_approve_products` and `require_business_verification` policies.
- **Amazon-Style Marketplace All-Products Catalog (`/m/modrixcommerce/multi-vendor/catalog`)**:
  - Full paginated multi-vendor catalog rendered natively inside the currently active theme (`ThemeSectionList` for sections-v2 themes such as Babylane, Voltmarket, Linden, Atelier, Relay, Sable, Circuit; `themeCatalog` for legacy themes; and `ProductGrid` fallback).
  - Includes department tabs, Seller switcher (`All Sellers`, `Direct Store`, `Marketplace Vendors`, or individual stores), `Verified Sellers Only` filter, `Today's Deals` (`on_sale`), `4★ & Up Sellers`, `In Stock Ready to Ship`, price range filters, variant attributes, and `Best Sellers` / `Rating` / `Price` / `Newest` sorting.
- **Public Marketplace Sellers Directory (`/m/modrixcommerce/multi-vendor/vendors`)**:
  - Searchable directory of approved marketplace sellers featuring live product preview tiles, a **Verified Businesses Only** filter, seller ratings, product counts, and a direct link to browse the All-Products Catalog.
- **Dedicated Vendor Storefront (`/m/modrixcommerce/multi-vendor/vendors/{slug}`)**:
  - Branded storefront page rendered using the active theme's native collection layout featuring store banner/logo, Verified Business badge, Seller Deals & Ready-to-Ship tabs, in-store search, and faceted filtering.

### 3. Automatic Order Splitting & 3-Tier Commission Engine
- **Real-Time `OrderPlaced` Listener**:
  - When a customer places an order containing vendor products, `MultiVendorService::recordOrderCommissions()` automatically splits vendor line items into `marketplace_vendor_orders` sub-order records.
- **3-Tier Commission Resolution**:
  1. **Product-Level Override** (`marketplace_vendor_products.commission_override`)
  2. **Vendor-Level Custom Rate** (`marketplace_vendors.commission_rate`)
  3. **Global Marketplace Default** (`default_commission_rate`, default `10%`)
- **Automated Ledger & Vendor Fulfillment**:
  - Calculates `gross_amount`, `commission_amount`, and net `vendor_earning`, immediately crediting the vendor's `available_balance`.
  - Vendors can update per-item fulfillment status (`unfulfilled`, `processing`, `shipped`, `delivered`) and attach carrier tracking numbers from their Seller Portal, which customers see on their Order Confirmation and Account Order pages.

### 4. Store-to-Vendor Payout System
- **Multiple Payout Channels**:
  - **Bank Wire / IBAN Transfer** (Bank Name, Account Holder, IBAN/Account Number, SWIFT/BIC)
  - **PayPal Payouts**
  - **Stripe Connect**
  - **Wise Business Transfer**
  - **Manual / Store Check**
- **3 Configurable Payout Modes**:
  1. **Vendor Payout Request (`vendor_request`)**: Vendors request on-demand payouts from their Seller Portal once their `available_balance` meets `min_payout_amount`.
  2. **Automatic Payout on Order (`automatic_on_order` or per-vendor `auto_payout_enabled`)**: Automatically generates a payout disbursement record as soon as an order credits the vendor's balance past the minimum threshold.
  3. **Admin Batch Payouts (`admin_batch`)**: Administrators generate batch payouts for all eligible vendors with one click (`POST /admin/m/modrixcommerce/multi-vendor/payouts/batch`).
- **Disbursal & Balance Protection**:
  - Supports optional payout processing fee (`payout_fee_percent`), bank/wire transaction reference recording, and automatic balance restoration if a payout is rejected.

---

## Core & Theme Wiring (Zero Core Modifications)

| Extension Hook / Capability | Component / Handler | Storefront / Admin Placement |
| :--- | :--- | :--- |
| **`ProductLabels`** | `MultiVendorServiceProvider::registerProductCardLabels` | Renders `✓ {Store Name}` (Verified) or `By {Store Name}` badge on product cards across all storefront themes (`standard`, `minimal`, `framed`, `atelier`, `electronics`). |
| **`product.after_details` Slot** | `ProductVendorBoxSlot.tsx` | Renders the Amazon-style **"Ships from / Sold by"** marketplace Buy Box with Verified Business badge, rating, product count, **"Visit Store"**, and **"Marketplace Catalog"** links on product detail pages. |
| **Movable Theme Block (`featured-vendors`)** | `FeaturedVendorsBlock.tsx` | Showcases Featured Marketplace Sellers alongside live themed product cards on `home.after_featured`, `catalog.before_products`, or `catalog.after_products`. |
| **Movable Theme Block (`marketplace-catalog-strip`)** | `MarketplaceCatalogStripSlot.tsx` | Renders a compact **Marketplace Catalog & Seller Filter Bar** above core catalog listings (`catalog.before_products`) linking to All Products, Verified Sellers, and Deals. |
| **`order.confirmation` & `account.order` Slots** | `OrderVendorsSlot.tsx` | Displays per-item seller fulfillment status and carrier tracking numbers to customers. |
| **`storefront.footer` Slot** | `MarketplaceFooterLinksSlot.tsx` | Adds quick links to **Marketplace Sellers Directory**, **Become a Verified Seller**, and **Vendor Seller Portal** in the storefront footer. |
| **`admin.product.sidebar` Slot** | `AdminProductVendorPanel.tsx` | Sidebar card in the Admin Product Editor to assign a product to a vendor and set a commission override. |
| **`DashboardWidgets` (`overview`)** | `MultiVendorOverviewWidget.tsx` | Live KPI widget on the main Admin Dashboard showing active/verified sellers, vendor products, store commissions, and payouts. |
| **`OrderPlaced` Event Listener** | `MultiVendorService::recordOrderCommissions` | Automatically splits vendor order items, calculates store commission, credits vendor balance, and triggers auto-payouts. |

---

## Database Schema

1. **`marketplace_vendors`**:
   - Store identity (`store_name`, `slug`, `tagline`, `description`, `email`, `phone`, `logo_url`, `banner_url`)
   - KYB Business Verification (`verification_status`, `business_type`, `legal_business_name`, `tax_id`, `trade_license_number`, `identity_document_ref`, `business_address`, `city`, `country_code`, `verification_notes`, `rejection_reason`, `verified_at`, `verified_by`)
   - Commission & Payout configuration (`commission_rate`, `payout_method`, `auto_payout_enabled`, `bank_name`, `bank_account_name`, `bank_account_number`, `bank_swift_code`, `payout_email`, `payout_notes`)
   - Financial balances (`total_sales_amount`, `total_commission_amount`, `available_balance`, `paid_out_total`)
2. **`marketplace_vendor_products`**:
   - Links `vendor_id` and `product_id` (`unique(product_id)`), `vendor_sku`, `commission_override`, `approval_status` (`pending`, `approved`, `rejected`), `rejection_note`, `approved_at`.
3. **`marketplace_vendor_orders`**:
   - Per-order-item split record (`vendor_id`, `order_id`, `order_item_id`, `product_id`, `order_number`, `product_name`, `quantity`, `currency`, `gross_amount`, `commission_rate`, `commission_amount`, `vendor_earning`, `fulfillment_status`, `payout_status`, `payout_id`, `tracking_number`).
4. **`marketplace_vendor_payouts`**:
   - Store-to-vendor payout record (`vendor_id`, `reference`, `currency`, `amount`, `fee_amount`, `net_amount`, `payout_method`, `destination_summary`, `status`, `requested_by_vendor`, `transaction_reference`, `notes`, `processed_by`, `processed_at`).

---

## Routes Reference

### Storefront Routes
- `GET /m/modrixcommerce/multi-vendor/vendors` — Public Marketplace Sellers Directory
- `GET /m/modrixcommerce/multi-vendor/vendors/{slug}` — Dedicated Vendor Storefront & Product Catalog
- `GET /m/modrixcommerce/multi-vendor/register` — Vendor Registration, KYB Verification & Payout Onboarding
- `POST /m/modrixcommerce/multi-vendor/register` — Submit Vendor Registration & KYB Application
- `GET /m/modrixcommerce/multi-vendor/dashboard` — Vendor Seller Portal
- `POST /m/modrixcommerce/multi-vendor/dashboard/verification` — Submit/Update KYB Business Verification
- `PUT /m/modrixcommerce/multi-vendor/dashboard/payout-settings` — Update Vendor Payout Method & Auto-Payout Option
- `POST /m/modrixcommerce/multi-vendor/dashboard/products` — Create New Vendor Catalog Product
- `PUT /m/modrixcommerce/multi-vendor/dashboard/orders/{vendorOrder}/fulfillment` — Update Seller Order Fulfillment & Tracking Number
- `POST /m/modrixcommerce/multi-vendor/dashboard/payouts/request` — Request Payout from Marketplace Store

### Admin Routes (`multi_vendor.manage` permission)
- `GET /admin/m/modrixcommerce/multi-vendor` — Admin Multi-Vendor Marketplace Control Center
- `PUT /admin/m/modrixcommerce/multi-vendor/settings` — Update Marketplace Settings
- `POST /admin/m/modrixcommerce/multi-vendor/vendors` — Create Marketplace Vendor
- `PUT /admin/m/modrixcommerce/multi-vendor/vendors/{vendor}/status` — Approve, Suspend, Feature, or Update Vendor Commission
- `PUT /admin/m/modrixcommerce/multi-vendor/vendors/{vendor}/verification` — Review KYB Business Verification (`verified`, `more_info_required`, `rejected`)
- `POST /admin/m/modrixcommerce/multi-vendor/products/assign` — Assign Catalog Product to Vendor
- `PUT /admin/m/modrixcommerce/multi-vendor/products/{vendorProduct}/review` — Approve or Reject Vendor Product
- `PUT /admin/m/modrixcommerce/multi-vendor/payouts/{payout}/process` — Approve, Mark Paid (with TX Ref), or Reject Payout
- `POST /admin/m/modrixcommerce/multi-vendor/payouts/batch` — Generate Batch Payouts for All Eligible Vendors

---

## Packaging & Installation

```bash
# Run feature tests
php artisan test tests/Feature/Extensions/MultiVendorTest.php

# Build frontend assets
npm run build

# Package distributable ZIP for Modrix Cloud App Store
php artisan ecomodular:package modrixcommerce/multi-vendor --output=dist
```
