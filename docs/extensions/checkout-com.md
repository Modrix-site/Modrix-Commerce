# Checkout.com

Checkout.com takes the payment. This store creates the session, sends the customer to Checkout.com (or mounts Flow), and marks the order paid only after Checkout.com confirms the captured amount. Card numbers are never posted to this store.

The gateway code is `checkout_com`. The built-in Payments tab named Checkout is guest checkout, so this method does not use that name.

Official references:

- [Hosted Payments Page](https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-a-hosted-page)
- [Flow](https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-your-website/get-started-with-flow)
- [Webhooks](https://www.checkout.com/docs/developer-resources/event-notifications/receive-webhooks)
- [Amount format](https://www.checkout.com/docs/payments/accept-payments/format-the-amount-value)

## Install

1. Admin → Extensions → Upload, and choose `dist/ecomodular-checkout-com-1.0.0.zip`. The archive must contain `module.json` at its root. An upload does not replace a copy that is already in `modules/modrixcommerce/checkout-com`.
2. Enable **Checkout.com**.
3. Run `npm run build` so the settings page and the Flow page are in the Vite manifest. Skip this only when `npm run dev` is already serving the store.
4. If route caching is on, rebuild it: `php artisan route:cache`.
5. Admin → Payments → **Checkout.com**, or open `/admin/m/modrixcommerce/checkout-com`. Saving credentials asks you to confirm your password first.

You can also copy `modules/modrixcommerce/checkout-com` into place and enable it from Extensions. The zip is the install path for a store that does not already have the folder.

## Configure

| Field                 | Value                                                                                                                                                           |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| API base URL          | Sandbox: `https://api.sandbox.checkout.com`. Live: `https://api.checkout.com`. An account prefix such as `https://prefix.api.sandbox.checkout.com` is accepted. |
| Secret key            | `sk_sbox_…` for sandbox, `sk_…` for live. Stored encrypted. A sandbox key is refused when the API URL is live, and the reverse.                                 |
| Public key            | Required for Flow. `pk_sbox_…` or `pk_…`, matching the secret key's environment. Used in the browser.                                                           |
| Processing channel    | Optional `pc_…`. Send it when the account has more than one channel.                                                                                            |
| Display               | **Hosted payment page** (default) or **Flow**.                                                                                                                  |
| Webhook signature key | From Developers → Webhooks in the Checkout.com dashboard. Stored encrypted.                                                                                     |

Checkout.com cannot reach `http://localhost`. Set `APP_URL` to the store's public `https://` address before relying on webhooks. The return from the hosted page still works on localhost because the customer comes back in the browser; the store then asks Checkout.com for the payment.

### Webhook

Endpoint: `POST /webhooks/payments/checkout_com`

Subscribe to:

- `payment_captured`, `payment_approved`, `payment_declined`
- `payment_canceled`, `payment_expired`, `payment_voided`
- `payment_refunded`, `payment_refund_declined`

The signature is HMAC-SHA256 of the raw body, hex-encoded, in `Cko-Signature`. A mismatched signature is rejected. A sale webhook does not mark the order paid by itself: the store calls `GET /payments/{id}` and accepts the payment only when Checkout.com reports it captured for the same amount and currency.

## How a payment works

1. The customer chooses Checkout.com. The method is offered only when it is enabled and the required keys are saved.
2. The store sends `POST /hosted-payments` or, for Flow, `POST /payment-sessions`. Amounts are minor units. The reference is `p{paymentId}`. `capture` is true, so a successful payment is captured rather than left authorized. The request sends `Cko-Idempotency-Key`.
3. Hosted mode redirects to `_links.redirect.href` on `checkout.com`. Checkout.com does not allow that page inside an iframe, so this module never frames it.
4. Flow mode opens `/m/modrixcommerce/checkout-com/pay/{payment}` for the customer who placed the order and mounts `CheckoutWebComponents` from `https://checkout-web-components.checkout.com/index.js`. The payment session stays in the server cache; it is not put in the URL.
5. The customer returns to this store. That return is not treated as payment. The store reads the hosted payment or the payment session, then the payment, and marks the order paid only when the status is Captured (or a later refund state) for the expected amount.
6. Sessions last 24 hours at Checkout.com. This store treats them as resumable for 23 hours.

A billing country (ISO 3166-1 alpha-2) is required. It is taken from the order's billing address, or the shipping address when there is no billing address.

### Status map

| Checkout.com                                 | This store                                   |
| -------------------------------------------- | -------------------------------------------- |
| Captured, Partially Refunded, Refunded, Paid | Paid                                         |
| Authorized, Partially Captured               | Still processing; the order is not fulfilled |
| Declined                                     | Failed                                       |
| Canceled, Voided, Expired                    | Expired                                      |
| Pending, Payment Pending                     | Waiting                                      |

## Refunds

Admin refunds call `POST /payments/{pay_id}/refunds` with the amount and `Cko-Idempotency-Key`. Checkout.com answers before the refund is settled, so the store records it as pending and completes it when `payment_refunded` arrives. The refund is matched by Checkout.com's `action_id`. If the request times out, check the Checkout.com dashboard before refunding again.

## Sandbox test card

`4242 4242 4242 4242`, any future expiry, CVV `100`.

Test connection reads an unknown hosted-payment id. A 404 means the secret key was accepted. A 401 means it was rejected. A live charge needs the merchant's own sandbox secret key.

## Flow and content security

The module allows these Checkout.com hosts in the store's content security policy: `checkout-web-components.checkout.com`, `cdn.checkout.com`, `js.checkout.com`, `pay.checkout.com`, `pay.sandbox.checkout.com`, `api.checkout.com` and `api.sandbox.checkout.com`. Hosted mode does not need the browser to call the API. Flow uses the classic API hosts above. An account-prefixed API host is used by the server for hosted payments; the browser policy cannot list a wildcard, so use `api.sandbox.checkout.com` or `api.checkout.com` when the display is Flow.
