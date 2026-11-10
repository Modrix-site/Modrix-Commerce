# Instamojo Payments for Modrix Commerce

Accept payments across India using **Instamojo** (UPI, Credit/Debit Cards, Net Banking across all Indian banks, and Wallets).

## Features

- **Hosted Payment Request**: Seamless checkout redirect to Instamojo's payment page (`longurl`).
- **HMAC-SHA1 MAC Verification**: Full verification of incoming webhook notifications (`mac`) against your merchant Private Salt.
- **Server-to-Server Inquiry**: Authoritative retrieval of payment request statuses and payment records (`Credit`/`Failed`).
- **Direct Refunds**: Automated full and partial refunds through Instamojo's Refund API (`/refunds/`).
- **Encrypted Credentials**: API Key, Auth Token, and Private Salt are stored encrypted using `SecretSettings`.

## Endpoints Used

- **Sandbox API**: `https://test.instamojo.com/api/1.1/`
- **Live API**: `https://www.instamojo.com/api/1.1/`
- **Create Payment Request**: `POST /payment-requests/`
- **Get Payment Request**: `GET /payment-requests/{id}/`
- **Refund Endpoint**: `POST /refunds/`
