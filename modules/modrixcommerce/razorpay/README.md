# Razorpay for Modrix Commerce

Package: `modrixcommerce/razorpay` · Publisher: **Modrix team** · Version: **1.0.0**

Razorpay Standard Checkout through the core payment gateway contract. Payment details are collected by Razorpay; this module stores no card details. This release supports one-time payments, optional capture of authorized payments, and full/partial refunds. Subscriptions and saved cards are outside this release.

## Features

- Core checkout, order workflow, inventory holds, invoices, payment reconciliation and refund accounting.
- Separate encrypted test/live API secrets and webhook secrets, with original payment mode and credential fingerprints retained for existing orders.
- Independent controls for checkout, webhooks, API refunds, authorized-payment capture, customer prefill, retries, close confirmation and automatic checkout opening.
- Merchant-enabled payment methods or a selected set, supported currency selection, checkout label, brand name, theme color and a 5–60 minute local checkout lifetime. Merchant permissions still determine available currencies and methods.
- Server-created Orders with exact minor-unit amounts. Signed returns bind the saved order and payment; only API-confirmed capture can mark an order paid. Failed attempts leave the Razorpay Order retryable.
- Raw-body signed webhooks with sanitized core inbox records, duplicate protection and provider API verification before financial updates.
- Persistent refund request reservations and provider idempotency keys prevent blind resends after timeouts. Unknown outcomes block further API refunds until reconciled. Webhooks correlate reservations by known reference or request receipt; a separate dashboard refund of the same amount cannot release an unrelated reservation.
- Protected admin settings, masked credentials, safe validation failures, audit events, module migrations and signed marketplace licensing.

## Publish and install

From the repository root:

```text
php marketplace-catalog/bin/publish-package.php modrixcommerce/razorpay
```

The publisher retains the source ZIP, generates a downloadable package and SHA-256, signs the release and preserves the configured free/paid offer.

1. Open `/item/modrixcommerce-razorpay` on the marketplace. Claim its free license or purchase the configured paid offer.
2. Configure the target store's marketplace URL and trusted catalog public signing key, preserving existing trusted keys.
3. Add the license in the core Marketplace. License activation pulls missing or newer signed files. Install and enable the module in Extensions to run its migrations and register the gateway. A clean target store exercises the full download path; this source checkout already contains the module files.
4. Run `npm run build` on the target store after pulling the package to compile its React pages with the core frontend.
5. Open **Payments → Razorpay**. Save the test key ID (`rzp_test_…`) and test API secret. Enable checkout and select currencies/methods active on the merchant account. Keep automatic capture configured in Razorpay, or explicitly enable this module's authorized-payment capture option.
6. Serve the store over public HTTPS. In the Razorpay dashboard configure `/webhooks/payments/razorpay` and save the same webhook secret in the matching test/live settings. Subscribe to `payment.authorized`, `payment.captured`, `payment.failed`, `order.paid`, `refund.created`, `refund.processed` and `refund.failed`.
7. Run the platform queue worker and scheduler. Core reconciliation checks open payments when shoppers do not return. Disable only checkout to stop new sales while retaining reconciliation; disabling the entire extension removes its gateway and routes.
8. Test the complete payment/refund flow before selecting live mode and saving the live key pair and webhook secret. Keep both modes' original credentials saved for outstanding payments. Replacing an original key requires restoring it to reconcile those payments.
9. Enable API refunds when needed. After an ambiguous refund request, inspect the merchant dashboard and use **Reconcile a refund** with the original core payment ID and verified `rfnd_…` reference for that request. This explicit administrative action resolves a matching uncertain reservation. There is no blind reset/resend action.

Local session expiry prevents reopening checkout; it cannot revoke a provider checkout already opened. API-confirmed late captures still reconcile. Merchant account activation and provider settlement remain external requirements.

The two module tables retain payment context and refund reservations. Back them up and retain them for historical reconciliation and duplicate protection. Uninstall runs migrations that remove both tables. Marketplace outages follow core licensing policy and do not automatically disable installed gateways.

## Verification

```text
php vendor/phpunit/phpunit/phpunit tests/Feature/Payments/RazorpayTest.php tests/Feature/Marketplace/RazorpayPackageTest.php
npm run types:check
npm run build
```

Tests use isolated databases, provider HTTP fakes and a temporary signed marketplace, including license activation and installation into an empty module directory. Real merchant credentials and public HTTPS are required to validate live charges and settlement.

## Provider documentation

- [Standard Checkout integration](https://razorpay.com/docs/payments/payment-gateway/web-integration/standard/integration-steps/)
- [Orders API](https://razorpay.com/docs/api/orders/)
- [Fetch an Order's payments](https://razorpay.com/docs/api/payments/fetch-payments-orders/)
- [Idempotent refunds](https://razorpay.com/docs/api/refunds/normal-refunds-idempotent)
- [Webhook validation](https://razorpay.com/docs/webhooks/validate-test/)
- [Checkout payment methods](https://razorpay.com/docs/payments/payment-gateway/web-integration/standard/configure-payment-methods/)
