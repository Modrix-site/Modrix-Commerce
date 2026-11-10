# Paytm Payment Gateway for Modrix Commerce

Accept payments via **Paytm UPI**, **Paytm Wallet**, **Net Banking across all Indian banks**, and **Credit / Debit Cards** with Paytm's All-in-One Payment Gateway.

## Features

- **All-in-One Flow**: Seamless handoff with automatic form submission to Paytm's secure hosted payment page.
- **PaytmChecksum AES-128 / SHA256**: Full cryptographic checksum computation for API requests and callback verification.
- **Server-to-Server Verification**: Authoritative transaction verification against Paytm's Order Status API (`/v3/order/status`).
- **Direct Refunds**: Automated full and partial refunds through Paytm's Refund API (`/refund/apply`).
- **Encrypted Credentials**: Merchant Key stored with encryption via `SecretSettings`, never visible in the browser.

## Endpoints Used

- **Staging / Test**: `https://securegw-stage.paytm.in`
- **Production**: `https://securegw.paytm.in`
- **Initiate Transaction**: `POST /theia/api/v1/initiateTransaction?mid={mid}&orderId={orderId}`
- **Payment Page**: `POST /theia/api/v1/showPaymentPage?mid={mid}&orderId={orderId}`
- **Transaction Status**: `POST /v3/order/status`
- **Refund API**: `POST /refund/apply`
