# Tap Payments

Tap takes the payment. This store creates a charge, sends the customer to the page Tap returns, and marks the order paid only when a later read of that charge says `CAPTURED` for the same amount and currency. Card numbers are never posted to this store.

The gateway code is `tap`. Checkout themes receive it from the core payment list, so no theme page is required. Official references:

- [Charges](https://developers.tap.company/reference/charges)
- [Redirect](https://developers.tap.company/docs/redirect)
- [Webhook](https://developers.tap.company/docs/webhook)
- [Refunds](https://developers.tap.company/reference/refunds)

## Install

1. Admin → Extensions → Upload, and choose `dist/ecomodular-tap-1.0.0.zip`. `module.json` is at the root of the archive. An upload does not replace a copy that is already in `modules/modrixcommerce/tap`.
2. Enable **Tap Payments**.
3. Run `npm run build` so the settings page is in the Vite manifest. Skip this only when `npm run dev` is already serving the store.
4. If route caching is on, rebuild it: `php artisan route:cache`.
5. Admin → Payments → **Tap Payments**, or open `/admin/m/modrixcommerce/tap`. Saving credentials asks you to confirm your password first.

You can also copy `modules/modrixcommerce/tap` into place and enable it from Extensions.

## Configure

| Field           | Value                                                                                                                                                 |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Payment methods | Cards only sends `src_card`. All enabled methods sends `src_all`, so KNET, mada, Benefit and the other methods turned on in Tap appear on Tap's page. |
| Merchant ID     | Optional. Sent as `merchant.id` when it is saved.                                                                                                     |
| Secret key      | `sk_test_…` uses the test account. `sk_live_…` uses the live account. Stored encrypted. The public key is not used.                                   |

Live keys refuse a return address that is not `https://`. A test key can use the local store for the customer's return. Tap cannot post to `http://localhost`, so the charge omits `post.url` unless the webhook address is `https://`. The return from Tap still retrieves the charge.

### Webhook

Endpoint: `POST /webhooks/payments/tap`

Tap signs the raw body. The `hashstring` header is HMAC-SHA256 of:

`x_id{id}x_amount{amount}x_currency{currency}x_gateway_reference{reference.gateway}x_payment_reference{reference.payment}x_status{status}x_created{transaction.created}`

The amount is formatted before hashing: three decimals for BHD, KWD, OMR and JOD, and two decimals for every other currency. A missing gateway or payment reference is an empty string. Refund notices use the refund's `created` value when `transaction.created` is absent. The secret key is the HMAC key.

A failed check is discarded. A sale is not marked paid from the webhook body: the store calls `GET /v2/charges/{id}` and uses that response. A refund notice is read with `GET /v2/refunds/{id}`.

## How a payment works

1. The customer chooses Tap. The method is offered only when it is enabled and a secret key is saved. Every theme checkout that uses the core payment list shows it.
2. The store calls `POST https://api.tap.company/v2/charges` with the amount, currency, `threeDSecure`, customer name and email, reference `p{paymentId}`, `redirect.url` set to the checkout return, and `post.url` when that address is https.
3. The customer is sent to `transaction.url`. The address must be https and must not contain a username, password or port. Local methods such as KNET may use their own host.
4. Tap sends the customer back with `tap_id`. That return is not treated as payment. The store reads the charge. The order is marked paid only when the status is `CAPTURED` for the same amount and currency.
5. Charge pages last about 30 minutes. This store treats them as resumable for 28 minutes.

`INITIATED` stays pending. `AUTHORIZED` stays processing and is not fulfilled. `FAILED`, `DECLINED` and `RESTRICTED` fail the payment. `ABANDONED`, `CANCELLED`, `TIMEDOUT` and `VOID` expire it.

Amounts use two decimal places, except BHD, KWD, OMR and JOD, which Tap accepts with three.

## Refunds

Admin refunds call `POST /v2/refunds` with the charge id, amount, currency and reason `requested_by_customer`. The refund id is remembered for seven days against the store's idempotency key, so a repeated refund reads the existing refund instead of creating another one. `REFUNDED`, `ACCEPTED` and `SUCCESS` are recorded immediately. `PENDING`, `IN_PROGRESS` and `INITIATED` stay pending until a signed refund notice is read back from Tap. The refund is matched to the original charge id.

## Sandbox

Use a secret key from the Tap test account. Test connection lists one charge. A rejected key is reported on the settings page. A live charge needs the merchant's own secret key.
