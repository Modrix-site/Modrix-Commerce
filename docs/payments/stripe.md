# Stripe Setup

EcoModular takes card payments through [Stripe Checkout](https://docs.stripe.com/payments/checkout): the customer pays on Stripe's hosted page, so card numbers never reach your server. An order is marked paid only when Stripe confirms the payment, either by a signed webhook or by the store asking Stripe directly with your secret key. The redirect back from Stripe is never trusted on its own.

## What you need

- A Stripe account. Start in **test mode** and switch to live keys only after a successful test purchase.
- A publicly reachable HTTPS URL for webhooks. For local development, use the Stripe CLI (below).
- A running queue worker (`php artisan queue:work`) and the scheduler (`php artisan schedule:work` or a cron entry for `schedule:run`). Webhooks are processed on the queue, and the scheduler runs the reconciliation safety net every five minutes.

## 1. Get your keys

In the Stripe Dashboard (test mode), open **Developers → API keys** and copy the **Secret key** (`sk_test_…`). A [restricted key](https://docs.stripe.com/keys#limit-access) (`rk_test_…`) also works if it has write access to Checkout Sessions and Refunds and read access to Balance.

The publishable key is not needed, because Checkout runs on Stripe's page.

## 2. Create the webhook endpoint

Open **Developers → Webhooks → Add endpoint**:

- **Endpoint URL:** `https://your-store.example/webhooks/payments/stripe` (also shown on **Admin → Payments → Stripe**).
- **Events:**
    - `checkout.session.completed`
    - `checkout.session.async_payment_succeeded`
    - `checkout.session.async_payment_failed`
    - `checkout.session.expired`
    - `refund.created`
    - `refund.updated`
    - `refund.failed`

Copy the endpoint's **Signing secret** (`whsec_…`).

## 3. Configure the store

Sign in as an owner or administrator and open **Admin → Payments → Configure Stripe**. You will be asked to confirm your password. Then:

1. Paste the secret key and the signing secret.
2. Tick **Offer card payments at checkout** and save.
3. Click **Test connection**.

Keys are encrypted with `APP_KEY` before they are stored. The admin page only ever shows the last four characters. If you rotate `APP_KEY`, re-enter the keys.

## 4. Local development with the Stripe CLI

```bash
stripe login
```

```bash
stripe listen --forward-to http://localhost:8000/webhooks/payments/stripe
```

`stripe listen` prints a signing secret (`whsec_…`). Use that one in the admin page while developing; it differs from the Dashboard endpoint's secret. Keep a queue worker running:

```bash
php artisan queue:work
```

## 5. Test purchase

1. Add a product to the cart and choose the card method at checkout.
2. On Stripe's page, pay with the test card `4242 4242 4242 4242`, any future expiry date and any CVC.
3. You return to the order page, which shows "Confirming your payment…" until the webhook arrives. The order then becomes **Paid** and the confirmation email is sent.

Other useful [test cards](https://docs.stripe.com/testing):

| Card                  | Result                                                                  |
| --------------------- | ----------------------------------------------------------------------- |
| `4000 0000 0000 9995` | Declined (insufficient funds). The customer can retry on Stripe's page. |
| `4000 0025 0000 3155` | Requires 3-D Secure authentication.                                     |

To test an abandoned payment, close the Stripe page. When the session expires (30 minutes by default, set with `STRIPE_SESSION_TTL_MINUTES`), the order is cancelled and its stock released. To try this sooner, run `stripe trigger checkout.session.expired`, or expire the session from the Dashboard.

## How payments are confirmed

| Source                                  | Trusted? | What it does                                                                                                 |
| --------------------------------------- | -------- | ------------------------------------------------------------------------------------------------------------ |
| Redirect to `/checkout/orders/…/return` | No       | Triggers a server-side lookup of the session with the secret key. Only Stripe's answer can change the order. |
| Signed webhook                          | Yes      | Verified (HMAC-SHA256, 5-minute tolerance), stored once per event id, processed on the queue.                |
| `ecomodular:payments:reconcile`         | Yes      | Every five minutes, asks Stripe about payments that are still open, in case a webhook was missed.            |

Every confirmation checks that the amount and currency match the order. A mismatch is not applied: staff see a note on the order and an audit entry.

Edge cases handled:

- **Duplicate webhooks:** acknowledged without side effects.
- **Out-of-order events:** an `expired` event after `completed` changes nothing.
- **A payment after cancellation, or a second payment for the same order:** recorded and flagged for refund on the order page.
- **Bank-debit methods:** `completed` with `unpaid` marks the payment as processing until `async_payment_succeeded` or `async_payment_failed` arrives.

## Refunds

Refunds for card orders are made from the admin order page as usual:

1. The store creates the refund at Stripe first, with an idempotency key, and records it only once Stripe accepts it.
2. The `refund.updated` and `refund.failed` webhooks confirm or reverse it.
3. Refunds issued in the Stripe Dashboard are imported automatically, without restocking items.

## Troubleshooting

- **Admin → Payments → Payment events** lists every verified webhook with its status. Failed events can be retried.
- **Webhooks rejected with 400:** check that the signing secret matches the endpoint (the CLI and the Dashboard use different secrets) and that the server clock is correct.
- **Orders stay "Confirming":** check that a queue worker is running and that the webhook endpoint is reachable from the internet.
