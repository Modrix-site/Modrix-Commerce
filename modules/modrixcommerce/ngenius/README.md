# Network International (N-Genius) Payment Gateway for Modrix Commerce

Accept cards, Apple Pay, and local currencies across the UAE, Middle East, and Africa using **Network International N-Genius Online**.

## Features

- **Hosted Payment Page (HPP)**: High-converting, fully responsive checkout with full 3D-Secure 2.x support.
- **Major Currencies**: Support for AED, SAR, USD, EUR, GBP, QAR, and 3-decimal currencies (OMR, BHD, KWD).
- **Direct Refund Integration**: Automated full or partial refunds via the N-Genius Transactions API directly from the Modrix Commerce admin dashboard.
- **Encrypted Secrets**: Outlet API keys and webhook secrets stored encrypted with zero risk of exposure in client-side bundles.
- **Webhooks & Auto-Reconciliation**: Real-time order state updates (`PURCHASED`, `CAPTURED`, `FAILED`) with fallback server-to-server inquiry on customer return.

## Requirements

- An active Network International merchant account and outlet reference ID.
- Service Account API key generated from the Network International portal.

## Endpoints Used

- **Sandbox API**: `https://api-gateway.sandbox.ngenius-payments.com`
- **Live API**: `https://api-gateway.ngenius-payments.com`
- **Auth Endpoint**: `POST /identity/auth/access-token`
- **Create Order**: `POST /transactions/outlets/{outlet_id}/orders`
- **Order Status**: `GET /transactions/outlets/{outlet_id}/orders/{order_reference}`
- **Refund Endpoint**: `POST /transactions/outlets/{outlet_id}/orders/{order_reference}/payments/{payment_reference}/refund`
