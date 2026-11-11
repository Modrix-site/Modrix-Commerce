# Telr payments

Telr is a hosted payment gateway. The customer enters card details on Telr, either on Telr's full payment page or inside an iframe on your store. This store never receives the card number, so the checkout form itself does not require PCI DSS certification. Telr still expects you to complete their merchant onboarding.

An order is marked paid only after this store asks Telr, with the secret authentication key, and Telr reports a captured sale for the same amount, currency and test mode. The browser return URL is not treated as proof of payment.

Official references:

- [How the hosted payment page works](https://docs.telr.com/reference/how-the-hosted-payment-page-works)
- [Create session (`order.json`)](https://docs.telr.com/reference/payment-page)
- [Check status](https://docs.telr.com/reference/check-status-1)
- [iFrame display](https://telr-api.readme.io/reference/iframe-display)
- [Transaction advice / webhook signature](https://docs.telr.com/reference/webhook)
- [Remote API refunds](https://docs.telr.com/reference/request-methods)

## Install

Upload `dist/ecomodular-telr-1.0.0.zip` from **Extensions → Upload extension**. The archive has `module.json` at its root. The upload adds the package and does not enable it or run its code. Then install and enable **Telr** on the Extensions screen.

Saving credentials requires the `payments.manage` permission and a recent password confirmation. Open **Payments**, then the **Telr** tab.

Uploads never replace a package that is already on disk. Remove the existing `modules/modrixcommerce/telr` directory before uploading this zip onto a store that already has those files. After upload, run `npm run build` so the settings page and the iframe page are in the storefront assets. If you cache routes (`php artisan route:cache`), rebuild the cache after enabling the module.

To build the zip again from a checkout:

```bash
php artisan ecomodular:package modrixcommerce/telr --output=dist
```

## Credentials

From the Telr merchant admin (**Integrations**):

| Field in this store       | Telr value                                                                                                                             |
| ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Store ID                  | Numeric store ID. No letters.                                                                                                          |
| Authentication key        | Hosted payment page authentication key. Used for `order.json` (create and check).                                                      |
| Remote authentication key | Optional. Use this only when Telr issued a separate key for the remote API. Leave it blank to refund with the hosted payment page key. |
| Webhook secret            | Secret key Telr uses in the SHA-1 `tran_check` hash on transaction advice.                                                             |

All three secrets are stored encrypted. The admin page shows only a hint after you save. Leave a secret field blank to keep the saved value.

**Test mode** sends `order.test = 1`. Leave test mode on until Telr has approved the live account. A test payment cannot mark an order paid while the store is in live mode, and the reverse is also refused.

## Checkout display

**Hosted payment page.** The customer is sent to `https://secure.telr.com`. Telr shows your logo and CSS from the Telr merchant admin (payment page customisation). When they finish, Telr redirects them to this store. `framed` is `0`.

**Iframe.** The customer stays on your store. This store loads Telr's process URL in an iframe that is at least 600×550 pixels, with the sandbox flags Telr recommends (`allow-forms`, `allow-scripts`, `allow-top-navigation`, `allow-same-origin`, and the popup flags). The create request sends `framed = 3`: the form starts in the iframe, and after the payment Telr leaves the frame and returns the customer to your order page. Iframe mode requires the store to be served over `https`. Telr will not frame a payment page for an `http` shop.

Logo, colours and CSS are configured in the Telr merchant admin. This module does not upload a stylesheet. Inside an iframe, Telr hides the payment page content header.

The store's content security policy allows frames, connections and form posts only to `https://secure.telr.com`.

## What the customer does

1. At checkout they choose **Telr**.
2. The store creates a Telr order (`POST https://secure.telr.com/gateway/order.json`, method `create`) with the cart id `p{payment id}`, the amount in major units (for example `10.50`), the order currency, and `trantype` `sale` so a successful payment is captured.
3. Telr returns an order reference and a `process.html` URL. The reference is stored on the payment. The URL is used at once; Telr order references are short-lived (this store treats them as valid for 30 minutes).
4. Authorised and declined shoppers return to the store's payment return URL. Cancelled shoppers return to the order page. The return page asks Telr for the order status before changing the order.
5. Each create request also registers this store's webhook URL when `APP_URL` is `https`.

The cart id is unique per payment attempt. Telr rejects a repeated cart id (`E56`).

## Transaction advice

In the Telr merchant admin, set the transaction advice URL to:

```text
https://your-store.example/webhooks/payments/telr
```

The same address is shown on the Telr settings page. Telr must be able to reach a public `https` URL. Set `APP_URL` to that address.

Telr posts form fields (`tran_store`, `tran_type`, `tran_ref`, `tran_status`, `tran_check`, and the other fields in their webhook guide). This store rebuilds:

```text
{secret}:{tran_store}:{tran_type}:{tran_class}:{tran_test}:{tran_ref}:{tran_prevref}:{tran_firstref}:{tran_currency}:{tran_amount}:{tran_cartid}:{tran_desc}:{tran_status}:{tran_authcode}:{tran_authmessage}
```

and compares SHA-1 with `tran_check`. If Telr has enabled `tran_order` on the account, that value is included between `tran_firstref` and `tran_currency`, as Telr specifies. A mismatch is discarded.

`tran_order` is not sent unless you ask Telr support to enable it. Without it, the advice is matched to the payment by the cart id (`p{payment id}`).

Advice for a sale or authorisation does not mark the order paid by itself. The store calls `order.json` with method `check` and applies that result:

| Telr order status | Meaning                            | This store                               |
| ----------------- | ---------------------------------- | ---------------------------------------- |
| 3                 | Paid (captured sale)               | Paid, when the amount and currency match |
| 2                 | Authorised, not captured (on hold) | Processing                               |
| 1 or 4            | Pending / payment requested        | Still unpaid                             |
| -3                | Declined                           | Failed                                   |
| -1, -2, -4        | Expired, cancelled, or replaced    | Expired                                  |

A reported amount that does not match the order is not marked paid. Staff are asked to check the Telr dashboard.

Refund advice (`tran_type` refund) is applied from the signed message, because the check call describes the original sale. Status `A` is a completed refund, `H` is on hold.

## Refunds

Refund the order from the admin order page. This store sends a remote refund to `https://secure.telr.com/gateway/remote.xml`:

- `type` refund, `class` ecom
- the original Telr transaction reference
- the same currency, amount and test flag as the payment

Telr must enable the remote integration on the account before refunds work. Ask Telr if refunds return an authentication error. If a refund request times out, check the Telr dashboard before submitting it again: Telr does not offer the same idempotency key as the hosted session, so a second successful request can refund twice. The cart id sent with the refund is this store's refund key, which makes a duplicate request more likely to be rejected, but you should still confirm the result in Telr when the admin shows an error.

## Test a payment

1. Save the test store ID and test authentication key. Leave **Test mode** on.
2. Use **Test connection**. A rejected store ID or key is reported. An "order not found" style reply means Telr accepted the credentials.
3. Place a small order and choose Telr.
4. Pay with a test card from the Telr merchant admin or from [Telr's test documentation](https://docs.telr.com). Do not use a live card while test mode is on.
5. Confirm the order becomes paid only after you land back on the store, or after transaction advice arrives. Closing the browser before the return is covered by the webhook and by the store's payment reconciliation.

## Go live

1. Finish Telr's live merchant checks and payment page branding (logo and CSS).
2. Replace the store ID and keys with the live values. Set the webhook secret to the live secret.
3. Switch **Mode** to **Live** and save.
4. Point Telr's live transaction advice URL at `https://your-store.example/webhooks/payments/telr`.
5. Take a small live payment and refund it from the order page.

## Limits

- This module does not collect card numbers and does not call Telr's direct remote sale API.
- Apple Pay, STC Pay and similar methods appear when they are enabled on the Telr account. Iframe mode uses `framed = 3` so a method that cannot stay inside a frame can break out to a full page and still return the customer here.
- Unpaid Telr sessions expire at Telr. This store does not call a cancel API, because the hosted payment page API does not provide one.
- The payment page language and currency follow the Telr account and the order currency. The store currency must be one your Telr store is allowed to charge.
