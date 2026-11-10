# Ziina Payment Gateway for Modrix Commerce

Accept payments in the United Arab Emirates using **Ziina** (Apple Pay, Google Pay, Visa, and Mastercard in AED).

## Features

- **Direct Checkout**: High-converting, mobile-first payment page with native Apple Pay and Google Pay integration.
- **UAE Central Bank Licensed**: Fully compliant payment infrastructure built specifically for the UAE market.
- **REST API v2**: Modern Payment Intent architecture (`/api/payment_intent`).
- **Real-Time Webhooks**: HMAC-SHA256 signed webhooks (`X-Hmac-Signature`) for instant payment reconciliation.
- **API Refunds**: Full or partial refunds executed directly via Ziina's refund API.
- **Encrypted Credentials**: API Token and Webhook Secret are stored with encryption using `SecretSettings`.

## Endpoints Used

- **Base URL**: `https://api-v2.ziina.com/api`
- **Create Payment Intent**: `POST /payment_intent`
- **Retrieve Payment Intent**: `GET /payment_intent/{id}`
- **Refund API**: `POST /refund`
