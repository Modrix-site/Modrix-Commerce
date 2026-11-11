# Changelog

All notable changes to the `modrixcommerce/store-pickup` module will be documented in this file.

## [1.1.0] - 2026-11-11

### Added

- Dynamic Cash on Delivery handling for in-store pickup:
    - Default payment method is set to Cash on Delivery.
    - When regular home or instant delivery is selected, payment method is Cash on Delivery with courier collection fee.
    - When in-store or branch pickup is selected, Cash on Delivery dynamically transforms to "Pay in store" (or hidden based on settings).
    - Automatically waives the cash on delivery courier collection fee for in-store pickup orders in checkout and order summary.
    - Customizable Pay in Store label and instructions via admin settings.
- Real-time client-side and server-side payment synchronization when toggling between delivery and store pickup.

## [1.0.0] - 2026-11-11

### Added

- Initial release of Store Pickup & Click & Collect module.
- Multi-location branch and retail store management.
- Shipping rate provider integrating in-store pickup directly into checkout shipping methods.
- Checkout validator ensuring valid branch selection before order placement.
- Storefront interactive branch picker slot for checkout (`checkout.before_place_order`, `checkout.after_summary`).
- Support for authorized alternative collector details.
- Preferred pickup date and time window scheduling.
- Automated order pickup recording on `CartCheckedOut` and `OrderPlaced`.
- Storefront order confirmation and account order summary cards.
- Admin dashboard for pickup orders with preparation workflow (`Preparing Order` → `Ready for Pickup` → `Picked Up / Completed`).
- Order event timeline logging when orders are marked ready or collected.
