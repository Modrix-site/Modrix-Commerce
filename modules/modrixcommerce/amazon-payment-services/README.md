# Amazon Payment Services (APS / PayFort) for Modrix Commerce

Package: `modrixcommerce/amazon-payment-services` · Publisher: **Modrix team** · Version: **1.0.0**

Amazon Payment Services (formerly PayFort) hosted checkout gateway for the United Arab Emirates (UAE), Saudi Arabia (KSA), Egypt, and the wider MENA/GCC region.

## Key Features

- **Hosted Checkout & Redirection**: Minimizes PCI-DSS compliance scope by redirecting shoppers to the secure APS payment page (`sbcheckout.payfort.com` or `checkout.payfort.com`). Supports credit/debit cards, Apple Pay, and local schemes (Mada, KNET, Meeza).
- **Cryptographic Signatures**: End-to-end request and response verification with SHA-256 and SHA-512 algorithms using secure Request and Response passphrases.
- **Server Status Inquiry**: Automated payment reconciliation through the server-to-server `CHECK_STATUS` API command.
- **Online Refunds**: Process full or partial refunds directly from the Modrix admin with idempotency protection.
- **Multi-Currency Support**: Built-in support for GCC currencies (AED, SAR, QAR, KWD, BHD, OMR) with correct 2-decimal and 3-decimal unit conversions, plus USD, EUR, GBP, EGP.
- **Safe Mode & Audit Logging**: All configuration updates and credentials are encrypted via `SecretSettings` and recorded in the audit log.

## Setup Instructions

1. Obtain your **Merchant Identifier**, **Access Code**, **SHA Request Phrase**, and **SHA Response Phrase** from your Amazon Payment Services back-office under **Integration Settings** → **Security Settings**.
2. Enable the module under **Admin → Extensions** or run:
   ```bash
   php artisan ecomodular:modules install modrixcommerce/amazon-payment-services
   php artisan ecomodular:modules enable modrixcommerce/amazon-payment-services --trust
   ```
3. Open **Admin → Configuration → Amazon Payment Services** and fill in your merchant credentials.
4. Set the **Feedback / Return URL** in your APS back-office to:
   `https://<your-store>/m/modrixcommerce/amazon-payment-services/response`
5. Set the **Webhook / Direct Transaction Feedback URL** to:
   `https://<your-store>/webhooks/payments/amazon-payment-services`
6. Rebuild frontend assets:
   ```bash
   npm run build
   ```
