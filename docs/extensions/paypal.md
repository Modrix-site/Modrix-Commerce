# PayPal

PayPal takes the payment. This store creates an order, sends the customer to PayPal, and captures the money only after PayPal says the order is approved. Card numbers are never posted to this store.

The gateway code is `paypal`. Official references:

- [Orders v2](https://developer.paypal.com/docs/api/orders/v2/)
- [How to use the Orders API](https://developer.paypal.com/api/rest/integration/orders-api)
- [Webhooks](https://developer.paypal.com/api/rest/webhooks/rest/)
- [Currency codes](https://developer.paypal.com/api/rest/reference/currency-codes/)

## Install

1. Admin → Extensions → Upload, and choose `dist/ecomodular-paypal-1.0.0.zip`. `module.json` is at the root of the archive. An upload does not replace a copy that is already in `modules/ecomodular/paypal`.
2. Enable **PayPal**.
3. Run `npm run build` so the settings page is in the Vite manifest. Skip this only when `npm run dev` is already serving the store.
4. If route caching is on, rebuild it: `php artisan route:cache`.
5. Admin → Payments → **PayPal**, or open `/admin/m/ecomodular/paypal`. Saving credentials asks you to confirm your password first.

You can also copy `modules/ecomodular/paypal` into place and enable it from Extensions.

## Configure

| Field | Value |
| --- | --- |
| Mode | Sandbox calls `https://api-m.sandbox.paypal.com`. Live calls `https://api-m.paypal.com`. |
| Client ID | REST app client ID from the PayPal developer dashboard. |
| Secret | REST app secret. Stored encrypted. |
| Webhook ID | The ID PayPal assigns when you add the webhook. Used to verify each delivery. |

Live mode refuses a return address that is not `https://`. Sandbox can use the local store for the customer's return. PayPal itself cannot call `http://localhost`, so webhooks need a public `https://` `APP_URL`.

### Webhook

Endpoint: `POST /webhooks/payments/paypal`

Subscribe to:

- `CHECKOUT.ORDER.APPROVED`
- `PAYMENT.CAPTURE.COMPLETED`
- `PAYMENT.CAPTURE.DENIED`
- `PAYMENT.CAPTURE.PENDING`
- `PAYMENT.CAPTURE.REFUNDED`

Each delivery is checked with PayPal's `POST /v1/notifications/verify-webhook-signature`. The certificate URL must be on `api-m.paypal.com`, `api-m.sandbox.paypal.com`, `api.paypal.com` or `api.sandbox.paypal.com`. A failed check is discarded. A sale is not marked paid from the webhook body: the store reads the order and captures it when PayPal reports it approved.

## How a payment works

1. The customer chooses PayPal. The method is offered only when it is enabled and the client ID, secret and webhook ID are saved.
2. The store calls `POST /v2/checkout/orders` with `intent` `CAPTURE`, the amount as a decimal string, reference `p{paymentId}`, and `user_action` `PAY_NOW`. The request sends `PayPal-Request-Id`, so a retry does not create a second order.
3. The customer is sent to the `payer-action` link (or `approve` when PayPal returns that). The link must be `https://www.paypal.com` or `https://www.sandbox.paypal.com`.
4. PayPal sends the customer back. That return is not treated as payment. The store reads the order. When the status is `APPROVED`, it calls `POST /v2/checkout/orders/{id}/capture`. The order is marked paid only when the capture status is `COMPLETED` for the same amount and currency.
5. Approval links last about three hours. This store treats them as resumable for 165 minutes.

A pending capture, such as a payment that PayPal has not finished, stays processing and is not fulfilled. A declined capture fails the payment. A voided order expires it.

Amounts use two decimal places, except JPY, HUF and TWD, which PayPal accepts with no fractional digits.

## Refunds

Admin refunds call `POST /v2/payments/captures/{id}/refund` with `PayPal-Request-Id`. A completed refund is recorded immediately. A pending refund is completed when `PAYMENT.CAPTURE.REFUNDED` arrives. The refund is matched by PayPal's refund id. If the request times out, check the PayPal dashboard before refunding again.

## Sandbox

Create a sandbox business app and a sandbox personal buyer in the PayPal developer dashboard. Test connection requests an access token. A rejected client ID or secret is reported on the settings page. A live charge needs the merchant's own sandbox secret.
