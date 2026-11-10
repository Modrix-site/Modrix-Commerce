# PayBy (Astra Tech) Payment Gateway for Modrix Commerce

Accept payments across the United Arab Emirates using **PayBy** (Credit / Debit Cards, Botim Wallet, Apple Pay, and QR payments in AED).

## Features

- **Hosted Checkout (PAYPAGE)**: Mobile-optimized payment screen with direct Botim Wallet, Card, and Apple Pay flows.
- **RSA-SHA256 Security**: Asymmetric cryptographic signature calculation and verification ensuring complete tamper-proofing.
- **Order Synchronization**: Real-time server-to-server order status queries (`/order/query`) on customer callback.
- **API Refunds**: Full and partial refunds executed directly via PayBy's refund API (`/refund/placeOrder`).
- **Encrypted Credentials**: Merchant RSA Private Key and PayBy Public Key are stored encrypted via `SecretSettings`.

## Endpoints Used

- **UAT / Sandbox**: `https://uat.test2pay.com`
- **Production**: `https://api.payby.com`
- **Place Order**: `POST /sgs/api/acquire2/placeOrder`
- **Query Order**: `POST /sgs/api/acquire2/order/query`
- **Refund Order**: `POST /sgs/api/acquire2/refund/placeOrder`
