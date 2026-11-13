# Changelog

All notable changes to `modrixcommerce/multi-vendor` are documented in this file.

## [1.2.0] - 2026-11-12

### Added
- **Theme-Native Amazon-Style Marketplace Catalog (`/m/modrixcommerce/multi-vendor/catalog`)**:
  - Added full paginated all-products & multi-seller catalog rendered natively inside the currently active theme (`ThemeSectionList` for sections-v2 themes such as Babylane, Voltmarket, Linden, Atelier, Relay, Sable, Circuit; `themeCatalog` for legacy themes; and `ProductGrid` fallback).
  - Upgraded `/m/modrixcommerce/multi-vendor/vendors/{slug}` vendor storefronts to use the active theme's native collection section/catalog layout alongside paginated filtering, sorting, and grid/list views.
  - Added Amazon-style faceted catalog controls: live search across products/SKUs/brands/sellers, department/category filter tabs, Seller selector (`direct`, `marketplace`, or specific store), KYB Verified Sellers Only toggle, Today's Deals (`on_sale`), `4★ & Up` Seller Rating filter, In Stock toggle, Price range filters, Variant attribute options, Best Sellers (`order_items` sales), Rating sort, and removable filter chips.
  - Added `MarketplaceCatalogStripSlot` (`catalog.before_products`), upgraded `FeaturedVendorsBlock` with live themed product highlights, upgraded `ProductVendorBoxSlot` with an Amazon-style `"Ships from / Sold by"` Buy Box block, and upgraded the Sellers Directory with live vendor product previews.

## [1.1.0] - 2026-11-12

### Added
- **Seller Account Creation & Dedicated Seller Login**:
  - Added **Seller Login Account Credentials** (Full Name, Login Email, Password, and Password Confirmation) to the public Vendor Registration form (`/m/modrixcommerce/multi-vendor/register`), automatically provisioning the `User` account and signing the vendor in.
  - Added dedicated **Marketplace Seller Login** page (`GET/POST /m/modrixcommerce/multi-vendor/login`) and **Seller Sign Out** (`POST /m/modrixcommerce/multi-vendor/logout`).
  - Added **Create / Reset Seller Login Account** (`PUT /admin/m/modrixcommerce/multi-vendor/vendors/{vendor}/credentials`) in the Admin Vendors panel so administrators can set or reset login credentials for any vendor store.

## [1.0.0] - 2026-11-12

### Added
- **Vendor Registration & KYB/KYC Business Verification**:
  - Public seller onboarding at `/m/modrixcommerce/multi-vendor/register` with legal business entity type, trade license number, tax ID/VAT, signatory identity reference, and headquarters address.
  - Admin KYB Compliance Review Queue (`verified`, `more_info_required`, `rejected`) and Verified Business badges across product cards, product detail pages, and vendor storefronts.
- **Vendor Product Catalog & Storefronts**:
  - Self-service product creation in the Vendor Seller Portal (`/m/modrixcommerce/multi-vendor/dashboard`), Admin Product Editor sidebar vendor assignment (`AdminProductVendorPanel`), Public Marketplace Sellers Directory (`/m/modrixcommerce/multi-vendor/vendors`), and Dedicated Vendor Storefront Catalog page (`/m/modrixcommerce/multi-vendor/vendors/{slug}`).
- **Order Splitting & 3-Tier Commission Engine**:
  - Automatic order splitting on `OrderPlaced` (`marketplace_vendor_orders`) with product-level, vendor-level, and global default commission rate resolution, net vendor balance crediting, and seller order fulfillment with tracking numbers.
- **Store-to-Vendor Payouts**:
  - Support for Bank Wire/IBAN, PayPal, Stripe Connect, Wise, and Manual payouts with 3 payout modes (`vendor_request`, `automatic_on_order`, `admin_batch`), per-vendor `auto_payout_enabled` toggle, admin payout disbursal with transaction reference, and automatic balance refund on rejection.
- **Core & Theme Integration**:
  - `ProductLabels` seller badges on all theme product cards, `ProductVendorBoxSlot` (`product.after_details`), `FeaturedVendorsBlock` movable storefront block, `OrderVendorsSlot` (`order.confirmation` & `account.order`), `MarketplaceFooterLinksSlot` (`storefront.footer`), `AdminProductVendorPanel` (`admin.product.sidebar`), and `MultiVendorOverviewWidget` (`DashboardWidgets`).
