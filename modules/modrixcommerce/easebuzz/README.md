# Easebuzz Payment Gateway for Modrix Commerce

Accept payments in India using **Easebuzz** (Credit/Debit Cards, UPI, Net Banking across all major banks, Wallets, and EMI).

## Features

- **Hosted Payment Link**: Seamless token initiation via Easebuzz's `payment/initiateLink` API with automatic customer transfer to the secure checkout page.
- **Cryptographic Security**: SHA-512 request hashing with 10 user-defined parameter slots and reverse response hash verification (`sha512(salt|status|udf10..udf1|email|firstname|productinfo|amount|txnid|key)`).
- **Server-to-Server Reconciliation**: Authoritative status verification through the Easebuzz Dashboard Transaction Retrieve API (`/transaction/v1/retrieve`).
- **Automated Refunds**: Instant online refund processing via the Easebuzz Refund API (`/transaction/v1/refund`).
- **Encrypted Credentials**: Merchant Salt is protected via `SecretSettings`, never leaked in logs or client-side assets.

## Endpoints Used

- **Initiate API (Test)**: `https://testpay.easebuzz.in/payment/initiateLink`
- **Initiate API (Production)**: `https://pay.easebuzz.in/payment/initiateLink`
- **Payment Portal (Test)**: `https://testpay.easebuzz.in/pay/{access_key}`
- **Payment Portal (Production)**: `https://pay.easebuzz.in/pay/{access_key}`
- **Retrieve API (Test)**: `https://testdashboard.easebuzz.in/transaction/v1/retrieve`
- **Retrieve API (Production)**: `https://dashboard.easebuzz.in/transaction/v1/retrieve`
- **Refund API (Test)**: `https://testdashboard.easebuzz.in/transaction/v1/refund`
- **Refund API (Production)**: `https://dashboard.easebuzz.in/transaction/v1/refund`

## Installation & Setup

1. Install and enable the module:
    ```bash
    php artisan ecomodular:modules install modrixcommerce/easebuzz
    php artisan ecomodular:modules enable modrixcommerce/easebuzz --trust
    ```
2. Navigate to **Admin -> Configuration -> Easebuzz**.
3. Provide your **Merchant Key** and **Merchant Salt** obtained from your Easebuzz Merchant Dashboard.
4. Toggle **Test Mode** for staging verification or disable it for production transactions.
5. Save settings and click **Test Connection** to confirm API accessibility.
