# MyFatoorah Payment Gateway for Modrix Commerce

Accept payments across the **United Arab Emirates (UAE)** and the wider **GCC** (Saudi Arabia, Kuwait, Bahrain, Qatar, Oman) using **MyFatoorah** (Credit / Debit Cards, Apple Pay, Google Pay, Mada, KNET, Benefit).

## Features

- **Hosted Payment Link**: Seamless session execution via MyFatoorah's `/v2/ExecutePayment` API with automatic transfer to the hosted payment page.
- **Multi-Currency Processing**: Native support for AED, SAR, KWD, BHD, QAR, OMR, USD, and EUR.
- **Cryptographic Security**: HMAC-SHA256 signature verification for webhook events via `MyFatoorah-Signature`.
- **Server-to-Server Reconciliation**: Authoritative status inquiry through `/v2/GetPaymentStatus`.
- **Automated Refunds**: Instant online refund processing via `/v2/MakeRefund`.
- **Encrypted Credentials**: API Token and Webhook Secret are stored safely using `SecretSettings`.

## Endpoints Used

- **Execute Payment (Test)**: `https://apitest.myfatoorah.com/v2/ExecutePayment`
- **Execute Payment (Production)**: `https://api.myfatoorah.com/v2/ExecutePayment`
- **Get Payment Status (Test)**: `https://apitest.myfatoorah.com/v2/GetPaymentStatus`
- **Get Payment Status (Production)**: `https://api.myfatoorah.com/v2/GetPaymentStatus`
- **Make Refund (Test)**: `https://apitest.myfatoorah.com/v2/MakeRefund`
- **Make Refund (Production)**: `https://api.myfatoorah.com/v2/MakeRefund`

## Installation & Setup

1. Install and enable the module:
    ```bash
    php artisan ecomodular:modules install modrixcommerce/myfatoorah
    php artisan ecomodular:modules enable modrixcommerce/myfatoorah --trust
    ```
2. Navigate to **Admin -> Configuration -> MyFatoorah**.
3. Enter your **API Token (Key)** and optional **Webhook Secret**.
4. Configure test mode for staging or live mode for production.
5. Save settings and click **Test Connection** to confirm API accessibility.
