# Authorize.Net

Authorize.Net hosts the card form. This store requests an Accept Hosted token, sends the customer to that form, and marks the order paid only after Authorize.Net confirms the capture. Card numbers are never posted to this store.

The gateway code is `authorize_net`. Official references:

- [Accept Hosted](https://developer.authorize.net/api/reference/features/accept-hosted.html)
- [Webhooks](https://developer.authorize.net/api/reference/features/webhooks.html)
- [Get transaction details](https://developer.authorize.net/api/reference/index.html#transaction-reporting-get-transaction-details)

## Install

1. Admin → Extensions → Upload, and choose `dist/ecomodular-authorize-net-1.0.0.zip`. `module.json` is at the root of the archive. An upload does not replace a copy that is already in `modules/ecomodular/authorize-net`.
2. Enable **Authorize.Net**.
3. Run `npm run build` so the settings page and the checkout handoff page are in the Vite manifest. Skip this only when `npm run dev` is already serving the store.
4. If route caching is on, rebuild it: `php artisan route:cache`.
5. Admin → Payments → **Authorize.Net**, or open `/admin/m/ecomodular/authorize-net`. Saving credentials asks you to confirm your password first.

You can also copy `modules/ecomodular/authorize-net` into place and enable it from Extensions.

## Configure

| Field | Value |
| --- | --- |
| Mode | Sandbox calls `https://apitest.authorize.net/xml/v1/request.api` and posts the form to `https://test.authorize.net/payment/payment`. Live uses `https://api.authorize.net` and `https://accept.authorize.net`. |
| API Login ID | From Account → Settings → Security Settings → API Credentials and Keys. |
| Transaction key | From the same page. Stored encrypted. |
| Signature key | Hexadecimal key from the same page. Stored encrypted. Used to verify each webhook. |

The store currency must be the currency of the Authorize.Net merchant account. Amounts are sent as two-decimal strings. Live mode refuses a return address that is not `https://`. Sandbox can use the local store for the customer's return. Authorize.Net itself cannot call `http://localhost`, so webhooks need a public `https://` `APP_URL`.

### Webhook

Endpoint: `POST /webhooks/payments/authorize_net`

In Account → Settings → Webhooks, subscribe to:

- `net.authorize.payment.authcapture.created`
- `net.authorize.payment.fraud.held`
- `net.authorize.payment.fraud.approved`
- `net.authorize.payment.fraud.declined`
- `net.authorize.payment.refund.created`
- `net.authorize.payment.void.created`

Each delivery is checked with HMAC-SHA512. The signature key is hex-decoded before it is used as the key, and the hash is compared with the `X-ANET-Signature` header (`sha512=` plus the digest). A mismatch is rejected. A verified notification is not treated as payment on its own: the store calls `getTransactionDetails` and accepts the capture only when the invoice and amount match the payment.

## Checkout

Checkout creates an `authCaptureTransaction` and stores the form token for 13 minutes. The customer is sent to this store's handoff page, which posts the token to Authorize.Net. The token is not placed in the address bar. A redirect checkout always shows Authorize.Net's receipt; Continue brings the customer back, and the return URL does not mark the order paid by itself.

A capture that is held for fraud review stays processing until a later fraud-approved or fraud-declined notification. An unsettled capture can be voided in full. A partial refund is sent only after the transaction has settled, using the last four digits Authorize.Net returns for the card.

Test connection calls `authenticateTestRequest`. It confirms the API Login ID and transaction key. It does not charge a card.
