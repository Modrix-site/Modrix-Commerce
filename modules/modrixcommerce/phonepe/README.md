# PhonePe Payment Gateway for Modrix Commerce

Package: `modrixcommerce/phonepe` · Publisher: **Modrix team** · Version: **1.0.0**

PhonePe payment gateway integration for India, supporting UPI (PhonePe Intent, QR Code, Collect), Google Pay, Paytm, Net Banking across all major Indian banks, and Credit/Debit Cards.

## Key Features

- **Standard Checkout (pg/v1)**: Leverages PhonePe's official Pay Page for optimal mobile-first conversion rates.
- **X-VERIFY Checksum Verification**: Request and webhook validation using SHA256 checksums concatenated with Salt Key and Salt Index.
- **Server Status Inquiry**: Authoritative status inquiry (`/pg/v1/status/{merchantId}/{transactionId}`) eliminates reliance on client-side redirects.
- **API Refunds**: Process refunds with unique transaction identifiers directly from the Modrix admin.
- **Encrypted Secrets**: Sensitive credentials (Salt Key) are stored encrypted via `SecretSettings` and audited upon update.

## Setup Instructions

1. Obtain your **Merchant ID**, **Salt Key**, and **Salt Index** from the [PhonePe Merchant Portal](https://business.phonepe.com/).
2. Install and enable the module:
    ```bash
    php artisan ecomodular:modules install modrixcommerce/phonepe
    php artisan ecomodular:modules enable modrixcommerce/phonepe --trust
    ```
3. In **Admin → Configuration → PhonePe**, enter your Merchant ID, Salt Key, and Salt Index.
4. Set the Webhook Callback URL in PhonePe Portal to:
   `https://<your-store>/webhooks/payments/phonepe`
5. Compile frontend assets:
    ```bash
    npm run build
    ```
