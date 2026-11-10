# Cashfree Payments for Modrix Commerce

Package: `modrixcommerce/cashfree` · Publisher: **Modrix team** · Version: **1.0.0**

Cashfree Payments gateway for India supporting UPI (Google Pay, PhonePe, Paytm, BHIM), Net Banking across 50+ Indian banks, Domestic & International Cards, Wallets, and Instant Refunds.

## Key Features

- **Hosted Web Checkout**: Seamlessly triggers Cashfree's Web SDK v3 (`sdk.cashfree.com/js/v3/cashfree.js`) for full-page or modal checkout with UPI, Net Banking, Cards, and Wallets.
- **REST API v2023-08-01**: Implements the official server-to-server Order Creation (`/orders`), Status Inquiry (`/orders/{order_id}`), and Instant Refunds (`/orders/{order_id}/refunds`).
- **HMAC-SHA256 Webhook Verification**: High-security signature verification using raw payload, timestamp header (`x-webhook-timestamp`), and secret key.
- **Server-Side Status Verification**: Never relies on client redirects alone. The return URL triggers authoritative server-to-server reconciliation.
- **Protected Secrets**: App ID and Secret Key are stored encrypted via `SecretSettings` and audited on modification.

## Setup Instructions

1. Obtain your **App ID** and **Secret Key** from your [Cashfree Merchant Dashboard](https://merchant.cashfree.com/merchants/login) under **Developers** → **API Keys**.
2. Install and enable the module:
   ```bash
   php artisan ecomodular:modules install modrixcommerce/cashfree
   php artisan ecomodular:modules enable modrixcommerce/cashfree --trust
   ```
3. In **Admin → Configuration → Cashfree Payments**, enter your App ID and Secret Key.
4. Set the Webhook URL in Cashfree Developer Settings to:
   `https://<your-store>/webhooks/payments/cashfree`
5. Compile frontend assets:
   ```bash
   npm run build
   ```
